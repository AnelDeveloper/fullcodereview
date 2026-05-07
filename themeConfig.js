import { breakpointsVuetifyV3 } from '@vueuse/core'
import { VIcon } from 'vuetify/components/VIcon'
import { defineThemeConfig } from '@core'
import { Skins } from '@core/enums'
import { AppContentLayoutNav, ContentWidth, FooterType, NavbarType } from '@layouts/enums'

const logoMark = h('img', {
    src: '/favicon.png',
    alt: 'Full Code Review',
    style: 'width:34px;height:34px;border-radius:9px;flex-shrink:0;',
})

export const { themeConfig, layoutConfig } = defineThemeConfig({
    app: {
        title: 'Full Code Review',
        logo: logoMark,
        logoDark: logoMark,
        logoIconLight: logoMark,
        logoIconDark: logoMark,
        contentWidth: ContentWidth.Fluid,
        contentLayoutNav: AppContentLayoutNav.Vertical,
        overlayNavFromBreakpoint: breakpointsVuetifyV3.lg - 1,
        i18n: {
            enable: false,
            defaultLocale: 'en',
            langConfig: [],
        },
        theme: 'light',
        skin: Skins.Default,
        iconRenderer: VIcon,
    },
    navbar: {
        type: NavbarType.Sticky,
        navbarBlur: true,
    },
    footer: { type: FooterType.Static },
    verticalNav: {
        isVerticalNavCollapsed: false,
        defaultNavItemIconProps: { icon: 'tabler-circle' },
        isVerticalNavSemiDark: false,
    },
    horizontalNav: {
        type: 'sticky',
        transition: 'slide-y-reverse-transition',
        popoverOffset: 6,
    },
    icons: {
        chevronDown: { icon: 'tabler-chevron-down' },
        chevronRight: { icon: 'tabler-chevron-right', size: 20 },
        close: { icon: 'tabler-x', size: 20 },
        verticalNavPinned: { icon: 'tabler-circle-dot', size: 20 },
        verticalNavUnPinned: { icon: 'tabler-circle', size: 20 },
        sectionTitlePlaceholder: { icon: 'tabler-minus' },
    },
})
