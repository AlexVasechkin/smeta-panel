<?php

use App\Http\Controllers\CityController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::post('cities', [CityController::class, 'store'])->name('cities.store');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
