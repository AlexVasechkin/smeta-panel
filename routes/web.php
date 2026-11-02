<?php

use App\Http\Controllers\CityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentTemplateController;
use App\Http\Controllers\PartnerOrganizationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Справочник организаций, с которыми можно работать.
    Route::resource('partners', PartnerOrganizationController::class)
        ->parameters(['partners' => 'organization']);

    // Хранилище шаблонов документов (.docx с плейсхолдерами {{ ... }}).
    Route::get('document-templates/{document_template}/download', [DocumentTemplateController::class, 'download'])
        ->name('document-templates.download');
    Route::resource('document-templates', DocumentTemplateController::class);

    Route::post('cities', [CityController::class, 'store'])->name('cities.store');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
