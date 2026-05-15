import { defineStore } from "pinia"
import { runCodeCheck, fetchAnalysisStatus, cancelAnalysis } from "@/utils/codeCheck"
import { useAuthStore } from "@/stores/auth"

const STORAGE_KEY = "analysisRun"
const POLL_MS = 4000

const WAIT_LABELS = [
    "Reserving credits…",
    "Fetching repository…",
    "Reading source files…",
    "Mapping the stack…",
    "Auditing with AI…",
    "Checking for known footguns…",
    "Synthesizing findings…",
    "Cross-checking severity…",
    "Almost done…",
]

const loadPersisted = () => {
    try {
        const raw = localStorage.getItem(STORAGE_KEY)
        if (!raw) return null
        return JSON.parse(raw)
    } catch {
        return null
    }
}

export const useAnalysisRunStore = defineStore("analysisRun", {
    state: () => {
        const persisted = loadPersisted()
        return {
            id: persisted?.id || null,
            status: persisted?.status || null, // pending | running | completed | failed
            repoFullName: persisted?.repoFullName || "",
            errorMessage: persisted?.errorMessage || "",
            startedAt: persisted?.startedAt || null,
            progress: 0,
            label: WAIT_LABELS[0],
            _pollTimer: null,
            _tickTimer: null,
            _labelIdx: 0,
        }
    },
    getters: {
        isActive: (s) => !!s.id && (s.status === "pending" || s.status === "running"),
        isDone: (s) => s.status === "completed",
        isFailed: (s) => s.status === "failed",
    },
    actions: {
        _persist() {
            const snapshot = {
                id: this.id,
                status: this.status,
                repoFullName: this.repoFullName,
                errorMessage: this.errorMessage,
                startedAt: this.startedAt,
            }
            if (this.id) localStorage.setItem(STORAGE_KEY, JSON.stringify(snapshot))
            else localStorage.removeItem(STORAGE_KEY)
        },

        async start(opts) {
            // Server returns 202 with { analysis: { id, ... }, sectionsRemaining }
            let r
            try {
                r = await runCodeCheck(opts)
            } catch (e) {
                // 409 audit_in_progress means there's already an active run for
                // this user. Adopt it into the store so the banner + takeover
                // card resurface, then surface the existing analysis to the
                // caller instead of throwing a bare error.
                if (e?.data?.code === "audit_in_progress" && e?.data?.analysis) {
                    this.adopt(e.data.analysis)
                    return e.data.analysis
                }
                throw e
            }
            if (!r?.analysis?.id) throw new Error("Could not start analysis.")

            this.adopt(r.analysis, { resetProgress: true })
            if (r.sectionsRemaining) useAuthStore().setSections(r.sectionsRemaining)
            return r.analysis
        },

        adopt(analysis, { resetProgress = false } = {}) {
            this.id = analysis.id
            this.status = analysis.status || "pending"
            this.repoFullName = analysis.repoName || analysis.repoFullName || this.repoFullName
            this.errorMessage = analysis.errorMessage || ""
            this.startedAt = this.startedAt || Date.now()
            if (resetProgress) {
                this.progress = 5
                this._labelIdx = 0
                this.label = WAIT_LABELS[0]
            }
            this._persist()
            this._startPolling()
        },

        async cancel() {
            if (!this.id) return
            try {
                const r = await cancelAnalysis(this.id)
                if (r?.sectionsRemaining) useAuthStore().setSections(r.sectionsRemaining)
            } catch (e) {
                // If the server says it's already done, that's fine — the
                // next poll/status will reconcile.
                if (e?.status !== 422) throw e
            }
            this.clear()
        },

        resumeIfActive() {
            // Called on app boot / layout mount to resume polling if a
            // previous tab left an in-flight run in localStorage.
            if (this.isActive) this._startPolling()
        },

        _startPolling() {
            this._stopTimers()
            this._tickTimer = window.setInterval(() => {
                // Fake-progress ticker: ease toward 95% so the banner feels alive
                // while we wait for the queue worker.
                this.progress = Math.min(95, this.progress + Math.max(0.4, (95 - this.progress) * 0.05))
                this._labelIdx = (this._labelIdx + 1) % WAIT_LABELS.length
                this.label = WAIT_LABELS[this._labelIdx]
            }, 2000)
            this._pollTimer = window.setInterval(() => this._poll(), POLL_MS)
            // Fire one immediately so a near-instant queue run doesn't wait POLL_MS
            this._poll()
        },

        _stopTimers() {
            if (this._pollTimer) { window.clearInterval(this._pollTimer); this._pollTimer = null }
            if (this._tickTimer) { window.clearInterval(this._tickTimer); this._tickTimer = null }
        },

        async _poll() {
            if (!this.id) { this._stopTimers(); return }
            try {
                const r = await fetchAnalysisStatus(this.id)
                this.status = r.status
                this.repoFullName = r.repoFullName || this.repoFullName
                this.errorMessage = r.errorMessage || ""

                if (r.sectionsRemaining) useAuthStore().setSections(r.sectionsRemaining)

                if (r.status === "completed" || r.status === "failed") {
                    this._stopTimers()
                    this.progress = r.status === "completed" ? 100 : this.progress
                    this._persist()
                    window.dispatchEvent(new CustomEvent("analysisRun:finished", {
                        detail: { id: this.id, status: r.status },
                    }))
                } else {
                    this._persist()
                }
            } catch (e) {
                // Transient errors are OK — keep polling. A 404 means the row
                // was deleted; clear so the banner doesn't haunt the UI.
                if (e?.status === 404 || e?.data?.status === 404) {
                    this.clear()
                }
            }
        },

        clear() {
            this._stopTimers()
            this.id = null
            this.status = null
            this.repoFullName = ""
            this.errorMessage = ""
            this.startedAt = null
            this.progress = 0
            this._persist()
        },
    },
})
