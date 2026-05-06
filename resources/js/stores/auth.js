import { defineStore } from "pinia"
import { $api } from "@/utils/api"

export const useAuthStore = defineStore("auth", {
    state: () => ({
        user: JSON.parse(localStorage.getItem("user") || "null"),
        credits: 0,
    }),
    actions: {
        setUser(user) {
            this.user = user
            if (user) localStorage.setItem("user", JSON.stringify(user))
            else localStorage.removeItem("user")
        },
        setToken(token) {
            useCookie("accessToken").value = token
        },
        setCredits(n) {
            this.credits = n || 0
        },
        async login({ email, password }) {
            const res = await $api("/auth/login", {
                method: "POST",
                body: { email, password },
            })
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
            this.setCredits(0)
            return res
        },
        async logout() {
            try {
                await $api("/auth/logout", { method: "POST" })
            } catch { /* ignore */ }
            this.setUser(null)
            this.setCredits(0)
            useCookie("accessToken").value = null
        },
        async fetchMe() {
            try {
                const res = await $api("/auth/me")
                this.setUser(res.user)
                this.setCredits(res.credits)
                return res.user
            } catch {
                this.setUser(null)
                this.setCredits(0)
                return null
            }
        },
        async refreshCredits() {
            try {
                const res = await $api("/me/credits")
                this.setCredits(res.count)
                return res.count
            } catch {
                return this.credits
            }
        },
    },
})
