import { ofetch } from "ofetch"

export const $api = ofetch.create({
    baseURL: "/api",
    credentials: "include",
    onRequest({ options }) {
        const token = useCookie("accessToken").value
        if (token) {
            options.headers = {
                ...(options.headers || {}),
                Authorization: `Bearer ${token}`,
            }
        }
        options.headers = {
            ...(options.headers || {}),
            Accept: "application/json",
        }
    },
})
