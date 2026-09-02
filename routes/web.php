<?php

use App\Http\Controllers\CityController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EstimateController;
use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::resource('clients', ClientController::class);
    Route::resource('projects', ProjectController::class);

    Route::post('cities', [CityController::class, 'store'])->name('cities.store');

    Route::get('estimates', [EstimateController::class, 'index'])->name('estimates.index');
    Route::get('projects/{project}/estimate', [EstimateController::class, 'edit'])->name('projects.estimate.edit');
    Route::put('projects/{project}/estimate', [EstimateController::class, 'update'])->name('projects.estimate.update');

    Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('documents/create', [DocumentController::class, 'create'])->name('documents.create');
    Route::get('documents/{type}/preview/{project}', [DocumentController::class, 'preview'])->name('documents.preview');
    Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
