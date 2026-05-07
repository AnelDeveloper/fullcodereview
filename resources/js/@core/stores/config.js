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

  // Update theme-color meta tag for iPhone safe area and bottom bar
  const updateThemeColorMeta = (themeName) => {
    const metaThemeColor = document.querySelector('#theme-color-meta')
    const metaMsNavButton = document.querySelector('#msapplication-navbutton-color')
    const metaAppleStatusBar = document.querySelector('#apple-status-bar-style')
    
    const color = themeName === 'dark' ? '#25293C' : '#ffffff'
    
    if (metaThemeColor) {
      metaThemeColor.setAttribute('content', color)
    }
    if (metaMsNavButton) {
      metaMsNavButton.setAttribute('content', color)
    }
    if (metaAppleStatusBar) {
      metaAppleStatusBar.setAttribute('content', 'black-translucent')
    }
    
    // Force Safari to re-read the theme-color by removing and re-adding it
    if (metaThemeColor) {
      const parent = metaThemeColor.parentNode
      const nextSibling = metaThemeColor.nextSibling
      parent.removeChild(metaThemeColor)
      metaThemeColor.setAttribute('content', color)
      parent.insertBefore(metaThemeColor, nextSibling)
    }
  }

  watch([() => configStore.theme, userPreferredColorScheme], () => {
    const newTheme = configStore.theme === 'system'
      ? userPreferredColorScheme.value === 'dark'
        ? 'dark'
        : 'light'
      : configStore.theme

    vuetifyTheme.global.name.value = newTheme

    // Update meta tag for iPhone safe area
    updateThemeColorMeta(newTheme)
  }, { immediate: true })
  
  onMounted(() => {
    if (configStore.theme === 'system') {
      vuetifyTheme.global.name.value = userPreferredColorScheme.value
      updateThemeColorMeta(userPreferredColorScheme.value)
    } else {
      updateThemeColorMeta(configStore.theme)
    }
  })
}
// !SECTION
