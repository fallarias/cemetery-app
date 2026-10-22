<script setup>
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'

const props = defineProps({
    href:   { type: String, required: true },
    active: { type: Boolean, default: false },
    icon:   { type: String, default: '' },
})

// Sidebar mode is used whenever an icon is passed
const isSidebar = computed(() => !!props.icon)

// Original Breeze classes (top nav), used only when there is no icon
const classes = computed(() =>
    props.active
        ? 'inline-flex items-center px-1 pt-1 border-b-2 border-indigo-400 text-sm font-medium leading-5 text-gray-900 focus:outline-none focus:border-indigo-700 transition duration-150 ease-in-out'
        : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out',
)

// Inertia navigation, but keep a real href so open-in-new-tab still works
const go = (e) => {
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) return
    e.preventDefault()
    router.visit(props.href)
}
</script>

<template>
    <!-- Sidebar item -->
    <v-list-item
        v-if="isSidebar"
        :href="href"
        :active="active"
        :class="{ 'is-active': active }"
        rounded="lg"
        link
        @click="go"
    >
        <template #prepend>
            <v-icon size="20">{{ icon }}</v-icon>
        </template>
        <slot />
    </v-list-item>

    <!-- Original Breeze link -->
    <Link v-else :href="href" :class="classes">
        <slot />
    </Link>
</template>
