import { defineStore } from "pinia"
import { $api } from "@/utils/api"

const EMPTY_SECTIONS = { security: 0, database: 0, backend: 0, frontend: 0 }

export const useAuthStore = defineStore("auth", {
    state: () => ({
        user: JSON.parse(localStorage.getItem("user") || "null"),
        credits: 0,
        sections: { ...EMPTY_SECTIONS },
    }),
    getters: {
        sectionsTotal: (state) => Object.values(state.sections).reduce((a, b) => a + b, 0),
        availableCategories: (state) =>
            Object.keys(state.sections).filter((k) => state.sections[k] > 0),
    },
    actions: {
        setUser(user) {
            this.user = user
            if (user) localStorage.setItem("user", JSON.stringify(user))
            else localStorage.removeItem("user")
        },
        // Write document.cookie synchronously. We can't rely on
        // useCookie() — its watch fires async (next microtask), but
        // fetchMe() runs immediately after and reads document.cookie
        // synchronously, so on fast local networks the Bearer header
        // is empty and /auth/me returns 401.
        setToken(token) {
            const enc = encodeURIComponent(token ?? "")
            if (token) {
                document.cookie = `accessToken=${enc}; path=/; max-age=${60 * 60 * 24 * 30}; samesite=lax`
            } else {
                document.cookie = `accessToken=; path=/; max-age=-1`
            }
        },
        setCredits(n) { this.credits = n || 0 },
        setSections(sections) {
            this.sections = { ...EMPTY_SECTIONS, ...(sections || {}) }
            this.credits = Object.values(this.sections).reduce((a, b) => a + b, 0)
        },

        async login({ email, password }) {
            const res = await $api("/auth/login", { method: "POST", body: { email, password } })
            this.setToken(res.token)
            this.setUser(res.user)
            await this.fetchMe()
            return res
        },
        async register({ name, email, password, password_confirmation }) {
            // Backend no longer returns a token — user must verify their
            // email and then log in. We just resolve with the API response.
            return await $api("/auth/register", {
                method: "POST",
                body: { name, email, password, password_confirmation },
            })
        },
        async logout() {
            try { await $api("/auth/logout", { method: "POST" }) } catch { /* ignore */ }
            this.setUser(null)
            this.setSections({ ...EMPTY_SECTIONS })
            this.setToken(null)
        },
        async fetchMe() {
            try {
                const res = await $api("/auth/me")
                this.setUser(res.user)
                this.setSections(res.sections)
                return res.user
            } catch (e) {
                // Token bad/expired → drop it so the router can bounce the
                // user to /login instead of keeping the navbar in a "cookie
                // exists but user is null" zombie state.
                const status = e?.status ?? e?.response?.status
                if (status === 401 || status === 403) {
                    this.setToken(null)
                }
                this.setUser(null)
                this.setSections({ ...EMPTY_SECTIONS })
                return null
            }
        },
        async refreshCredits() {
            try {
                const res = await $api("/me/credits")
                this.setSections(res.byCategory)
                return res.total
            } catch {
                return this.credits
            }
        },
    },
})
