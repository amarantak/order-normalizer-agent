<!--
  Columna de resultado: estado, consistency checks y anomalías del crudo.
  Qué se muestra lo decide describeResult (función pura); acá solo se dibuja.
-->
<script setup>
import { computed } from "vue";
import { CONSISTENCY_RULES, describeResult } from "../utils/describeResult";

const props = defineProps({
    result: { type: Object, default: null },
    loading: { type: Boolean, default: false },
});

const view = computed(() =>
    props.result ? describeResult(props.result) : null,
);

const TONE_CLASSES = {
    approved: "bg-teal-800 text-white",
    review: "border border-amber-300 bg-amber-100 text-amber-950",
    failed: "bg-red-800 text-white",
};
</script>

<template>
    <div class="flex flex-col gap-4 lg:h-[32rem] lg:overflow-y-auto">
        <!-- Estado -->
        <section
            class="rounded-xl p-5"
            :class="
                view
                    ? TONE_CLASSES[view.tone]
                    : 'border border-stone-200 bg-white/60 text-stone-900'
            "
        >
            <h2
                class="text-xs font-semibold uppercase tracking-wider opacity-80"
            >
                Result
            </h2>

            <template v-if="view">
                <p class="mt-2 font-serif text-3xl font-semibold">
                    {{ view.title }}
                </p>
                <p class="mt-2 text-sm">{{ view.message }}</p>
                <p v-if="view.details" class="mt-2 font-mono text-xs">
                    Details: {{ view.details }}
                </p>

                <dl
                    v-if="view.facts.length"
                    class="mt-4 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm"
                >
                    <template v-for="fact in view.facts" :key="fact.label">
                        <dt class="opacity-80">{{ fact.label }}</dt>
                        <dd class="font-mono">{{ fact.value }}</dd>
                    </template>
                </dl>
            </template>

            <p v-else class="mt-2 text-sm text-stone-500">
                {{
                    loading
                        ? "Analyzing…"
                        : "Choose a source and run the analysis."
                }}
            </p>
        </section>

        <!-- Consistency checks -->
        <section
            v-if="view"
            class="rounded-xl border border-stone-200 bg-white/60 p-5"
        >
            <h2
                class="text-xs font-semibold uppercase tracking-wider text-stone-500"
            >
                Consistency checks
            </h2>

            <ul class="mt-3 space-y-2">
                <li
                    v-for="rule in CONSISTENCY_RULES"
                    :key="rule"
                    class="flex items-center gap-3"
                >
                    <span
                        aria-hidden="true"
                        class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                        :class="
                            view.checks.state === 'passed'
                                ? 'bg-teal-800 text-white'
                                : 'border border-stone-300'
                        "
                    >
                        <template v-if="view.checks.state === 'passed'"
                            >✓</template
                        >
                    </span>
                    <span class="font-mono text-sm text-stone-800">{{
                        rule
                    }}</span>
                </li>
            </ul>

            <p class="mt-3 text-sm text-stone-600">{{ view.checks.note }}</p>
            <ul v-if="view.checks.violations.length" class="mt-2 space-y-1">
                <li
                    v-for="violation in view.checks.violations"
                    :key="violation"
                    class="rounded-md bg-red-50 px-3 py-2 font-mono text-xs text-red-900"
                >
                    {{ violation }}
                </li>
            </ul>
        </section>

        <!-- Anomalías del crudo (solo si hay pedido) -->
        <section
            v-if="view?.anomalies"
            class="rounded-xl border border-stone-200 bg-white/60 p-5"
        >
            <div class="flex items-center justify-between">
                <h2
                    class="text-xs font-semibold uppercase tracking-wider text-stone-500"
                >
                    Anomalies in source
                </h2>
                <span class="font-mono text-xs text-stone-500">{{
                    view.anomalies.length
                }}</span>
            </div>

            <ul v-if="view.anomalies.length" class="mt-3 space-y-2">
                <li
                    v-for="anomaly in view.anomalies"
                    :key="anomaly"
                    class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-950"
                >
                    {{ anomaly }}
                </li>
            </ul>
            <p v-else class="mt-3 text-sm text-stone-600">
                No anomalies in the source.
            </p>
        </section>
    </div>
</template>
