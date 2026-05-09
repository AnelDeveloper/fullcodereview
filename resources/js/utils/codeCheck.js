import { $api } from "@/utils/api"

export const fetchCatalog = () => $api("/catalog")

export const fetchCredits = () => $api("/me/credits")

export const fetchDashboard = () => $api("/me/dashboard")

export const startCheckout = (categories) =>
    $api("/lemon-squeezy/checkout", { method: "POST", body: { categories } })

export const syncLemonOrders = () =>
    $api("/lemon-squeezy/sync", { method: "POST" })

export const fetchUserRepos = () => $api("/github/repos")

export const githubLoginUrl = () => {
    const token = useCookie("accessToken").value || ""
    return `/api/github/login?token=${encodeURIComponent(token)}`
}

export const disconnectGithub = () =>
    $api("/github/disconnect", { method: "POST" })

export const runCodeCheck = (opts) =>
    $api("/analyses/run", { method: "POST", body: opts })

export const fetchHistory = () =>
    $api("/analyses/history")

export const fetchAnalysis = (id) =>
    $api(`/analyses/${id}`)

// Verification workflow
export const submitForReview = (id) =>
    $api(`/analyses/${id}/verification/submit-for-review`, { method: "POST" })

export const approveAnalysis = (id, notes = "", internal = "") =>
    $api(`/analyses/${id}/verification/approve`, {
        method: "POST",
        body: { reviewer_notes: notes, internal_comments: internal },
    })

export const finalizeAnalysis = (id) =>
    $api(`/analyses/${id}/verification/finalize`, { method: "POST" })

export const fetchReviewerQueue = () =>
    $api("/reviewer/queue")
