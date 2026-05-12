import { storeToRefs } from 'pinia'
import { useTheme } from 'vuetify'
import { cookieRef, useLayoutConfigStore } from '@layouts/stores/config'
import { themeConfig } from '@themeConfig'

// SECTION Store
export const useConfigStore = defineStore('config', () => {
  // 👉 Theme
  const userPreferredColorScheme = usePreferredColorScheme()
  const cookieColorScheme = cookieRef('color-scheme', 'light')

  watch(userPreferredColorScheme, val => {
    if (val !== 'no-preference')
      cookieColorScheme.value = val
  }, { immediate: true })

  const theme = cookieRef('theme', themeConfig.app.theme)

  // 👉 isVerticalNavSemiDark
  const isVerticalNavSemiDark = cookieRef('isVerticalNavSemiDark', themeConfig.verticalNav.isVerticalNavSemiDark)

  // 👉 isVerticalNavSemiDark
  const skin = cookieRef('skin', themeConfig.app.skin)

  // ℹ️ We need to use `storeToRefs` to forward the state
  const { isLessThanOverlayNavBreakpoint, appContentWidth, navbarType, isNavbarBlurEnabled, appContentLayoutNav, isVerticalNavCollapsed, footerType, isAppRTL } = storeToRefs(useLayoutConfigStore())
  
  return {
    theme,
    isVerticalNavSemiDark,
    skin,

    // @layouts exports
    isLessThanOverlayNavBreakpoint,
    appContentWidth,
    navbarType,
    isNavbarBlurEnabled,
    appContentLayoutNav,
    isVerticalNavCollapsed,
    footerType,
    isAppRTL,
  }
})
// !SECTION
// SECTION Init
export const initConfigStore = () => {
  const userPreferredColorScheme = usePreferredColorScheme()
  const vuetifyTheme = useTheme()
  const configStore = useConfigStore()

  const updateThemeColorMeta = themeName => {
    const color = themeName === 'dark' ? '#000000' : '#ffffff'
    for (const id of ['#theme-color-meta', '#msapplication-navbutton-color']) {
      const meta = document.querySelector(id)
      if (meta) meta.setAttribute('content', color)
    }
    const statusBar = document.querySelector('#apple-status-bar-style')
    if (statusBar) statusBar.setAttribute('content', themeName === 'dark' ? 'black-translucent' : 'default')
  }

  watch([() => configStore.theme, userPreferredColorScheme], () => {
    const newTheme = configStore.theme === 'system'
      ? userPreferredColorScheme.value === 'dark' ? 'dark' : 'light'
      : configStore.theme

    vuetifyTheme.global.name.value = newTheme
    updateThemeColorMeta(newTheme)
  }, { immediate: true })

  onMounted(() => {
    const initial = configStore.theme === 'system'
      ? userPreferredColorScheme.value
      : configStore.theme
    updateThemeColorMeta(initial)
  })
}
// !SECTION
