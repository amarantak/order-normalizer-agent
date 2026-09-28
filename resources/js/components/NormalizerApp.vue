<!--
  Componente raíz de la isla de Vue (temporal: solo prueba que Vue y Tailwind funcionan).
  Se llama NormalizerApp y no OrderNormalizer para no confundirlo con la interfaz del dominio.
-->
<script setup>
import { ref } from 'vue';

// Estado reactivo: cuando cambia, Vue vuelve a dibujar solo lo que depende de él
const clicks = ref(0);
</script>

<template>
    <main class="mx-auto max-w-2xl p-8">
        <h1 class="text-2xl font-semibold text-slate-900">Order Normalizer Agent</h1>
        <p class="mt-2 text-slate-600">Vue and Tailwind are working.</p>

        <!-- Si el número sube al hacer clic, la reactividad de Vue funciona -->
        <button
            type="button"
            class="mt-6 rounded-md bg-slate-900 px-4 py-2 text-white hover:bg-slate-700"
            @click="clicks++"
        >
            Clicked {{ clicks }} times
        </button>
    </main>
</template>
