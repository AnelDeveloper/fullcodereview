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
        setToken(token) { useCookie("accessToken").value = token },
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
            const res = await $api("/auth/register", {
                method: "POST",
                body: { name, email, password, password_confirmation },
            })
            this.setToken(res.token)
            this.setUser(res.user)
            this.setSections({ ...EMPTY_SECTIONS })
            return res
        },
        async logout() {
            try { await $api("/auth/logout", { method: "POST" }) } catch { /* ignore */ }
            this.setUser(null)
            this.setSections({ ...EMPTY_SECTIONS })
            useCookie("accessToken").value = null
        },
        async fetchMe() {
            try {
                const res = await $api("/auth/me")
                this.setUser(res.user)
                this.setSections(res.sections)
                return res.user
            } catch {
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
