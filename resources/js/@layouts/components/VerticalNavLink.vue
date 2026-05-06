<script setup>
import { layoutConfig } from '@layouts'
import { can } from '@layouts/plugins/casl'
import { useLayoutConfigStore } from '@layouts/stores/config'
import {
  getComputedNavLinkToProp,
  getDynamicI18nProps,
  isNavLinkActive,
} from '@layouts/utils'
import { useTheme } from 'vuetify'

const props = defineProps({
  item: {
    type: null,
    required: true,
  },
})

const checkAdmin = (item, user) => {
    if (item.admin) {
        return user?.account_type === "sysadmin";
    }

    return true;
}

const checkAccess = item => {
    const user = useCookie("user").value

    if (item.admin) {
        return checkAdmin(item, user)
    }

    // TODO
    // if (item.access) {
    //     if (item.access.includes('some_access')) {
    //         if (user?.some_access) {
    //             return true
    //         }
    //     }
    //     return false;
    // }

    return true;
}

const configStore = useLayoutConfigStore()
const hideTitleAndBadge = configStore.isVerticalNavMini()

const { global } = useTheme()

// Get icon color based on theme
const getIconColor = computed(() => {
  if (props.item.iconColor) {
    return global.current.value.dark ? props.item.iconColor.dark : props.item.iconColor.light
  }
  return undefined
})

// Create icon props with color
const iconProps = computed(() => {
  const baseProps = props.item.icon || layoutConfig.verticalNav.defaultNavItemIconProps
  if (getIconColor.value) {
    return { ...baseProps, color: getIconColor.value }
  }
  return baseProps
})
</script>

<template>
  <li
    v-if="can(item.action, item.subject) && checkAccess(item)"
    class="nav-link"
    :class="{ disabled: item.disable }"
  >
    <Component
      :is="item.to ? 'RouterLink' : 'a'"
      v-bind="getComputedNavLinkToProp(item)"
      :class="{ 'router-link-active router-link-exact-active': isNavLinkActive(item, $router) }"
    >
      <Component
        :is="layoutConfig.app.iconRenderer || 'div'"
        v-bind="iconProps"
        class="nav-item-icon"
      />
      <TransitionGroup name="transition-slide-x">
        <!-- 👉 Title -->
        <Component
          :is="layoutConfig.app.i18n.enable ? 'i18n-t' : 'span'"
          v-show="!hideTitleAndBadge"
          key="title"
          class="nav-item-title"
          v-bind="getDynamicI18nProps(item.title, 'span')"
        >
          {{ item.title }}
        </Component>

        <!-- 👉 Badge -->
        <Component
          :is="layoutConfig.app.i18n.enable ? 'i18n-t' : 'span'"
          v-if="item.badgeContent"
          v-show="!hideTitleAndBadge"
          key="badge"
          class="nav-item-badge"
          :class="item.badgeClass"
          v-bind="getDynamicI18nProps(item.badgeContent, 'span')"
        >
          {{ item.badgeContent }}
        </Component>
      </TransitionGroup>
    </Component>
  </li>
</template>

<style lang="scss">
.layout-vertical-nav {
  .nav-link a {
    display: flex;
    align-items: center;
  }
}
</style>
