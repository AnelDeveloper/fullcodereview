import { createVuetify } from "vuetify"
import { VBtn } from "vuetify/components/VBtn"
import defaults from "./defaults"
import { icons } from "./icons"
import { themes } from "./theme"
import { themeConfig } from "@themeConfig"

import "@core-scss/template/libs/vuetify/index.scss"
import "vuetify/styles"

export default function (app) {
    const vuetify = createVuetify({
        aliases: {
            IconBtn: VBtn,
        },
        defaults,
        icons,
        theme: {
            defaultTheme: resolveVuetifyTheme(themeConfig.app.theme),
            themes,
        },
    })

    app.use(vuetify)
}
