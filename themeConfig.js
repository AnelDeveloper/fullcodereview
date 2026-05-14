import { breakpointsVuetifyV3 } from '@vueuse/core'
import { VIcon } from 'vuetify/components/VIcon'
import { defineThemeConfig } from '@core'
import { Skins } from '@core/enums'
import { AppContentLayoutNav, ContentWidth, FooterType, NavbarType } from '@layouts/enums'

// Single white-shark asset rendered as background-image so we can crop the
// SVG's empty canvas padding and make the shark fill the box. Light theme
// inverts to black via CSS filter (see styles.scss).
const logoMark = h('span', {
    'aria-label': 'QodeShark',
    role: 'img',
    class: 'app-logo-mark',
    style: [
        'display:inline-block',
        'width:36px',
        'height:36px',
        'flex-shrink:0',
        "background-image:url('/logos/Shark Logo Itself white.svg')",
        'background-size:195% auto',
        'background-position:center 40%',
        'background-repeat:no-repeat',
    ].join(';'),
})

export const { themeConfig, layoutConfig } = defineThemeConfig({
    app: {
        title: 'QodeShark',
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
