<!--
  Las 4 tarjetas del loop. Recibe el resultado y si está cargando;
  qué estado tiene cada etapa lo decide describeLoopSteps (función pura), acá solo se dibuja.

  Revelación en orden: el loop corre entero en una sola llamada, así que mientras espera
  las 4 etapas quedan "running" a la vez. Cuando llega el resultado, se revelan 1 → 4
  con una pausa corta: repite el ORDEN real de las etapas, no su duración (que no se conoce).
-->
<script setup>
import { computed, onBeforeUnmount, ref, watch } from "vue";
import { describeLoopSteps } from "../utils/loopSteps";

const props = defineProps({
    result: { type: Object, default: null },
    loading: { type: Boolean, default: false },
});

const STEP_COUNT = 4;
const REVEAL_DELAY_MS = 300;

// Cuántas etapas ya muestran su estado real (las demás siguen en "running")
const revealed = ref(STEP_COUNT);
let timer = null;

// Etapas "en curso" con su descripción general, para las que todavía no se revelaron
const runningSteps = describeLoopSteps(null, true);

const steps = computed(() =>
    describeLoopSteps(props.result, props.loading).map((step, index) =>
        index < revealed.value ? step : runningSteps[index],
    ),
);

watch(
    () => props.result,
    (result) => {
        clearTimeout(timer);

        // Sin resultado, o con "reducir movimiento" activado en el sistema: se muestra todo de una vez
        if (!result || prefersReducedMotion()) {
            revealed.value = STEP_COUNT;
            return;
        }

        revealed.value = 0;
        revealNext();
    },
);

function revealNext() {
    revealed.value += 1;
    if (revealed.value < STEP_COUNT) {
        timer = setTimeout(revealNext, REVEAL_DELAY_MS);
    }
}

function prefersReducedMotion() {
    return window.matchMedia("(prefers-reduced-motion: reduce)").matches;
}

// Si el componente desaparece a mitad de la revelación, no dejar un temporizador colgado
onBeforeUnmount(() => clearTimeout(timer));

// Texto para lectores de pantalla: el estado no puede depender solo del ícono o el color
const STATE_LABELS = {
    idle: "Not started",
    running: "Running",
    done: "Done",
    failed: "Failed",
    skipped: "Skipped",
    review: "Needs review",
};
</script>

<template>
    <div>
        <ol class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <li
                v-for="(step, index) in steps"
                :key="index"
                class="rounded-xl border p-5"
                :class="
                    step.state === 'skipped'
                        ? 'border-dashed border-stone-300'
                        : 'border-stone-200 bg-white/60'
                "
            >
                <div class="flex items-center gap-3">
                    <!-- Ícono según el estado -->
                    <span
                        aria-hidden="true"
                        class="flex h-7 w-7 items-center justify-center rounded-full text-sm font-bold"
                        :class="{
                            'border-2 border-stone-300 text-stone-400':
                                step.state === 'idle',
                            'bg-stone-300 motion-safe:animate-pulse':
                                step.state === 'running',
                            'bg-teal-800 text-white': step.state === 'done',
                            'bg-red-700 text-white': step.state === 'failed',
                            'bg-stone-200 text-stone-500':
                                step.state === 'skipped',
                            'bg-amber-500 text-white': step.state === 'review',
                        }"
                    >
                        <template v-if="step.state === 'done'">✓</template>
                        <template v-else-if="step.state === 'failed'"
                            >✕</template
                        >
                        <template v-else-if="step.state === 'skipped'"
                            >–</template
                        >
                        <template v-else-if="step.state === 'review'"
                            >!</template
                        >
                        <template v-else-if="step.state === 'idle'">{{
                            index + 1
                        }}</template>
                    </span>

                    <span
                        class="text-xs font-semibold uppercase tracking-wider text-stone-500"
                        >Step {{ index + 1 }}</span
                    >
                    <span class="sr-only">{{ STATE_LABELS[step.state] }}</span>
                </div>

                <h3 class="mt-3 text-lg font-semibold text-stone-900">
                    {{ step.title }}
                </h3>
                <p class="mt-1 text-sm text-stone-600">{{ step.detail }}</p>
            </li>
        </ol>

        <!-- aria-live: un lector de pantalla anuncia el cambio sin que el usuario busque -->
        <p aria-live="polite" class="mt-2 min-h-5 text-sm text-stone-500">
            <template v-if="loading">
                Running the full loop in one request: the steps update when it
                finishes.
            </template>
        </p>
    </div>
</template>
