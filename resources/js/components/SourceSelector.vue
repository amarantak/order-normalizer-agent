<!--
  Selector de ejemplo crudo. Solo muestra opciones y avisa cuál se eligió:
  no sabe que existe un endpoint ni qué se hace con el ejemplo.
-->
<script setup>
defineProps({
    samples: { type: Array, required: true },
    disabled: { type: Boolean, default: false },
});

// v-model: el id del ejemplo elegido. defineModel arma la prop y el evento de actualización.
const selected = defineModel({ type: String, required: true });
</script>

<template>
    <div class="flex flex-wrap items-center gap-4">
        <span class="text-xs font-semibold uppercase tracking-wider text-stone-500">Source</span>

        <div role="group" aria-label="Source" class="flex flex-wrap gap-1 rounded-lg bg-stone-200/70 p-1">
            <button
                v-for="sample in samples"
                :key="sample.id"
                type="button"
                :disabled="disabled"
                :aria-pressed="selected === sample.id"
                class="rounded-md px-4 py-2 text-sm transition disabled:cursor-not-allowed"
                :class="selected === sample.id
                    ? 'bg-white font-semibold text-stone-900 shadow-sm'
                    : 'text-stone-600 hover:text-stone-900'"
                @click="selected = sample.id"
            >
                {{ sample.label }}
            </button>
        </div>
    </div>
</template>
