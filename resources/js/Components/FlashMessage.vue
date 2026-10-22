<template>
  <transition name="flash-fade">
    <div
      v-if="visible"
      :class="[
        'fixed top-6 right-6 z-50 px-4 py-3 rounded-lg shadow-lg text-sm font-medium flex items-center gap-2 max-w-sm',
        type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
      ]"
    >
      <!-- success icon -->
      <svg v-if="type === 'success'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
      </svg>
      <!-- error icon -->
      <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <circle cx="12" cy="12" r="10"/>
        <line x1="12" y1="8" x2="12" y2="12"/>
        <line x1="12" y1="16" x2="12.01" y2="16"/>
      </svg>
      {{ message }}
    </div>
  </transition>
</template>

<script setup>
import { ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'

const page    = usePage()
const message = ref(null)
const type    = ref('success')
const visible = ref(false)
let   timer   = null

/**
 * FIX: added { immediate: true, deep: true }
 *
 * Without `immediate`, the watcher only fires when the value *changes*.
 * After an Inertia redirect the flash data is already present when the
 * component mounts, so the watcher never triggered and the toast was
 * never shown. `immediate` fires it once on mount with whatever value
 * is already there.
 *
 * `deep: true` ensures nested changes inside the flash object are caught.
 */
watch(
  () => page.props.flash,
  (flash) => {
    if (flash?.success) {
      message.value = flash.success
      type.value    = 'success'
      showToast()
    } else if (flash?.error) {
      message.value = flash.error
      type.value    = 'error'
      showToast()
    }
  },
  { immediate: true, deep: true }
)

function showToast() {
  if (timer) clearTimeout(timer)   // reset if a second flash fires quickly
  visible.value = true
  timer = setTimeout(() => {
    visible.value = false
    message.value = null
  }, 5000)
}
</script>

<style scoped>
.flash-fade-enter-active,
.flash-fade-leave-active {
  transition: opacity 0.35s ease, transform 0.35s ease;
}
.flash-fade-enter-from,
.flash-fade-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}
</style>
