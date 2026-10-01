<!--
  Panel oscuro con un título y un texto JSON. Se usa dos veces (crudo y normalizado).
  Recibe el texto ya armado: no sabe de dónde viene ni cómo se formatea.
-->
<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: "" },
    content: { type: String, default: null },
    placeholder: { type: String, default: "" },
});
</script>

<template>
    <section
        class="flex min-h-[28rem] flex-col overflow-hidden rounded-xl bg-stone-900 text-stone-100"
    >
        <header
            class="flex items-center justify-between gap-4 border-b border-white/10 px-5 py-4"
        >
            <h2 class="text-sm font-semibold uppercase tracking-wider">
                {{ title }}
            </h2>
            <span
                v-if="subtitle"
                class="truncate font-mono text-xs text-stone-400"
                >{{ subtitle }}</span
            >
        </header>

        <pre
            v-if="content"
            class="flex-1 overflow-auto p-5 font-mono text-sm leading-relaxed"
            >{{ content }}</pre
        >
        <p v-else class="flex-1 p-5 text-sm text-stone-400">
            {{ placeholder }}
        </p>
    </section>
</template>
