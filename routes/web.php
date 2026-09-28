<?php

use Illuminate\Support\Facades\Route;

// Pantalla principal: solo muestra la vista, sin lógica (por eso Route::view y no un controller)
Route::view('/', 'normalizer');
