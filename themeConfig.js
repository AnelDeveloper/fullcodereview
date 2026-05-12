import { breakpointsVuetifyV3 } from '@vueuse/core'
import { VIcon } from 'vuetify/components/VIcon'
import { defineThemeConfig } from '@core'
import { Skins } from '@core/enums'
import { AppContentLayoutNav, ContentWidth, FooterType, NavbarType } from '@layouts/enums'

// White shark wrapped in a black rounded square so it's visible on either
// light or dark navbar backgrounds (mirrors the landing page favicon).
const logoMark = h(
    'span',
    {
        style: [
            'display:inline-flex',
            'align-items:center',
            'justify-content:center',
            'width:36px',
            'height:36px',
            'border-radius:9px',
            'background:#000',
            'flex-shrink:0',
        ].join(';'),
    },
    [
        h('img', {
            src: '/logos/Shark Logo Itself white.svg',
            alt: 'QodeShark',
            style: 'width:28px;height:28px;object-fit:contain;display:block;',
        }),
    ],
)

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
