<script setup>
const props = defineProps({
    text: {
        type: String,
        default: "Export",
    },
    icon: {
        type: String,
        default: "mdi-file-export-outline",
    },
});
</script>
<template>
    <v-btn
        class="text-none tracking-normal"
        :prepend-icon="props.icon ?? 'mdi-file-export-outline'"
        rounded="l"
        :text="props.text"
        variant="flat"
        color="grey-lighten-3"
    ></v-btn>
</template>
