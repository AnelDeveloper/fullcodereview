/**
 * Static nav items. The `requiresReviewer` flag is filtered out for
 * non-reviewers in the layout components.
 */
export default [
    {
        title: "Dashboard",
        to: { path: "/" },
        icon: { icon: "tabler-layout-dashboard" },
    },
    {
        title: "New Review",
        to: { path: "/review" },
        icon: { icon: "tabler-shield-check" },
    },
    {
        title: "History",
        to: { path: "/history" },
        icon: { icon: "tabler-history" },
    },
    {
        title: "Reviewer queue",
        to: { path: "/reviewer/queue" },
        icon: { icon: "tabler-clipboard-check" },
        requiresReviewer: true,
    },
]
