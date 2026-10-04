<?php

use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

// Plan du site pour les moteurs de recherche (référencé par le robots.txt du frontend)
Route::get('/sitemap.xml', SitemapController::class);

require __DIR__.'/auth.php';
