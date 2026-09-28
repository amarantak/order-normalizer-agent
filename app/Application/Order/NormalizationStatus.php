<?php

namespace App\Application\Order;

// Estado final de una normalización. Cada estado pide una reacción distinta:
// - Approved:    el pedido está listo para usarse.
// - NeedsReview: problema de DATOS; una persona tiene que mirarlo (reintentar no sirve).
// - Failed:      problema de INTEGRACIÓN (API caída, respuesta inválida); reintentar
//                más tarde probablemente lo resuelva, sin molestar a nadie.
//
// Enum "backed" (con valor string) para poder mandarlo tal cual en el JSON al front en Vue.
enum NormalizationStatus: string
{
    case Approved = 'approved';
    case NeedsReview = 'needs_review';
    case Failed = 'failed';
}
