import { breakpointsVuetifyV3 } from '@vueuse/core'
import { VIcon } from 'vuetify/components/VIcon'
import { defineThemeConfig } from '@core'
import { Skins } from '@core/enums'
import { AppContentLayoutNav, ContentWidth, FooterType, NavbarType } from '@layouts/enums'

const logoMark = h('div', {
    style: 'display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:9px;background:linear-gradient(135deg,#8B5CF6 0%,#EC4899 100%);box-shadow:0 4px 16px rgba(139,92,246,0.4);color:#fff;flex-shrink:0;',
}, [
    h('svg', {
        xmlns: 'http://www.w3.org/2000/svg',
        viewBox: '0 0 24 24',
        width: 18,
        height: 18,
        fill: 'none',
        stroke: 'currentColor',
        'stroke-width': 2.5,
        'stroke-linecap': 'round',
        'stroke-linejoin': 'round',
    }, [
        h('polyline', { points: '16 18 22 12 16 6' }),
        h('polyline', { points: '8 6 2 12 8 18' }),
    ]),
])

export const { themeConfig, layoutConfig } = defineThemeConfig({
    app: {
        title: 'Code Review',
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
        theme: 'dark',
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
