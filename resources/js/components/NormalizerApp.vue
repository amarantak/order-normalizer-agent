<!--
  Componente raíz de la isla de Vue: tiene el estado y es el único que llama al endpoint.
  Los componentes hijos solo reciben datos y avisan eventos.
  Se llama NormalizerApp y no OrderNormalizer para no confundirlo con la interfaz del dominio.
-->
<script setup>
import { computed, ref, watch } from "vue";
import { normalizeOrder } from "../api/normalizeOrder";
import { samples } from "../samples/index.js";
import { formatOrderJson } from "../utils/formatOrderJson";
import SourceSelector from "./SourceSelector.vue";
import JsonPanel from "./JsonPanel.vue";
import LoopSteps from "./LoopSteps.vue";
import ResultPanel from "./ResultPanel.vue";

const selectedId = ref(samples[0].id);
const selectedSample = computed(() =>
    samples.find((sample) => sample.id === selectedId.value),
);

const loading = ref(false);
const result = ref(null);
const error = ref(null);

// Texto del panel normalizado: null si todavía no hay pedido (needs_review o failed pueden venir sin order)
const normalizedText = computed(() =>
    result.value?.order ? formatOrderJson(result.value.order) : null,
);

const normalizedPlaceholder = computed(() => {
    if (loading.value) {
        return "Analyzing…";
    }
    if (result.value) {
        return "No normalized order was produced. See the result.";
    }
    return "Run the analysis to see the normalized order.";
});

// Un resultado pertenece al pedido que se analizó: al cambiar de ejemplo, se borra
watch(selectedId, () => {
    result.value = null;
    error.value = null;
});

async function analyze() {
    loading.value = true;
    result.value = null;
    error.value = null;

    const { source, rawOrder } = selectedSample.value;

    try {
        result.value = await normalizeOrder(source, rawOrder);
    } catch (e) {
        error.value = e.message;
    } finally {
        // finally: el botón se reactiva tanto si salió bien como si falló
        loading.value = false;
    }
}
</script>

<template>
    <div>
        <section
            class="flex flex-col gap-4 rounded-xl border border-stone-200 bg-white/60 p-4 lg:flex-row lg:items-center lg:justify-between"
        >
            <SourceSelector
                v-model="selectedId"
                :samples="samples"
                :disabled="loading"
            />

            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-stone-900 px-5 py-3 font-semibold text-white hover:bg-stone-700 disabled:opacity-50"
                :disabled="loading"
                @click="analyze"
            >
                <svg
                    aria-hidden="true"
                    viewBox="0 0 16 16"
                    class="h-4 w-4 fill-none stroke-current stroke-2"
                >
                    <path d="M4 3l9 5-9 5z" />
                </svg>
                {{ loading ? "Analyzing…" : "Analyze order" }}
            </button>
        </section>

        <p v-if="error" class="mt-4 text-red-700">{{ error }}</p>
        <LoopSteps class="mt-6" :result="result" :loading="loading" />
        <div class="mt-6 grid gap-4 lg:grid-cols-3">
            <JsonPanel
                title="Raw input"
                :subtitle="selectedSample.id"
                :content="selectedSample.rawText"
            />

            <JsonPanel
                title="Normalized"
                subtitle="unified schema"
                :content="normalizedText"
                :placeholder="normalizedPlaceholder"
            />

            <ResultPanel :result="result" :loading="loading" />
        </div>
    </div>
</template>
