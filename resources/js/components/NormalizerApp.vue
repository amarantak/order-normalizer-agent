<!--
  Componente raíz de la isla de Vue.
  PROVISORIO: un botón que analiza pos1-clean y muestra la respuesta cruda,
  para verificar la llamada al endpoint antes de armar la UI del mockup.
  Se llama NormalizerApp y no OrderNormalizer para no confundirlo con la interfaz del dominio.
-->
<script setup>
import { ref } from "vue";
import { normalizeOrder } from "../api/normalizeOrder";
import pos1Clean from "../samples/pos1-clean.json";

const loading = ref(false);
const result = ref(null);
const error = ref(null);

async function analyze() {
    loading.value = true;
    result.value = null;
    error.value = null;

    try {
        result.value = await normalizeOrder("pos1", pos1Clean);
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
        <button
            type="button"
            class="rounded-md bg-slate-900 px-4 py-2 text-white hover:bg-slate-700 disabled:opacity-50"
            :disabled="loading"
            @click="analyze"
        >
            {{ loading ? "Analyzing…" : "Analyze pos1-clean" }}
        </button>

        <p v-if="error" class="mt-4 text-red-700">{{ error }}</p>

        <pre
            v-if="result"
            class="mt-4 overflow-x-auto rounded-md bg-slate-900 p-4 text-sm text-slate-100"
            >{{ JSON.stringify(result, null, 2) }}</pre
        >
    </div>
</template>
