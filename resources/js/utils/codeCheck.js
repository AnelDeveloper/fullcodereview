import { $api } from "@/utils/api"

export const fetchCatalog = () => $api("/catalog")

export const fetchCredits = () => $api("/me/credits")

export const fetchDashboard = () => $api("/me/dashboard")

export const startCheckout = (categories) =>
    $api("/lemon-squeezy/checkout", { method: "POST", body: { categories } })

export const fetchUserRepos = () => $api("/github/repos")

export const githubLoginUrl = () => {
    const token = useCookie("accessToken").value || ""
    return `/api/github/login?token=${encodeURIComponent(token)}`
}

export const runCodeCheck = (opts) =>
    $api("/analyses/run", { method: "POST", body: opts })

export const fetchHistory = () =>
    $api("/analyses/history")

export const fetchAnalysis = (id) =>
    $api(`/analyses/${id}`)
