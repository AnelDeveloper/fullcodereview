// Hoisted to entry CSS — the icons sheet is ~2.5MB and Vite was splitting
// it into a non-blocking chunk, causing icon glyphs to render blank on
// first navigation until the chunk landed.
import "@/plugins/iconify/icons.css"

import { createApp } from "vue"
import App from "@/App.vue"
import { registerPlugins } from "@core/utils/plugins"

import "@core-scss/template/index.scss"
import "@styles/styles.scss"

const app = createApp(App)

registerPlugins(app)

app.mount("#app")
