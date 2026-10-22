<script setup>
defineProps({
  modelValue: [String, Number],
  bordered: {
    type: Boolean,
    default: true,
  },
})
const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <div class="w-full">
    <div
      class="relative"
      :class="bordered ? 'border border-gray-300 rounded-md' : ''"
    >
      <select
        :value="modelValue"
        @change="emit('update:modelValue', $event.target.value)"
        class="w-full appearance-none bg-transparent border-0 focus:border-0 focus:ring-0 focus:outline-none pl-3 pr-8 py-4"
      >
        <slot />
      </select>
    </div>
  </div>
</template>
