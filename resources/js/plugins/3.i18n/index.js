import { createI18n } from "vue-i18n"

export default function (app) {
    const i18n = createI18n({
        legacy: false,
        locale: "en",
        fallbackLocale: "en",
        globalInjection: true,
        messages: { en: {} },
        missingWarn: false,
        fallbackWarn: false,
    })

    app.use(i18n)
    window.$i18n = i18n
}
