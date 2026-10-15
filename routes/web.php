<?php

use App\Http\Controllers\Auth\EntrarController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/entrar');

// Endereço antigo do login do Filament (favoritos salvos) cai no login único.
Route::redirect('/admin/login', '/entrar');

Route::get('/entrar', [EntrarController::class, 'show'])->name('entrar');
Route::post('/entrar', [EntrarController::class, 'store'])->middleware('throttle:5,1')->name('entrar.store');
