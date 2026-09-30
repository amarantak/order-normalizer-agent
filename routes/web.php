<?php

use App\Http\Controllers\NormalizeOrderController;
use Illuminate\Support\Facades\Route;

// Pantalla principal: solo muestra la vista, sin lógica (por eso Route::view y no un controller)
Route::view('/', 'normalizer');

// Endpoint Ajax de la pantalla: recibe source + raw_order y devuelve el resultado de la normalización.
// En web.php (no api.php): lo llama nuestra propia página, con protección CSRF incluida.
Route::post('/orders/normalize', NormalizeOrderController::class)->name('orders.normalize');
