<?php

use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin/login');

Route::get('manifest.webmanifest', function () {
    return Response::json([
        'name' => config('app.name'),
        'short_name' => config('app.name'),
        'description' => 'Raiffeisen bank account and spending statistics',
        'start_url' => '/admin',
        'scope' => '/admin',
        'display' => 'standalone',
        'orientation' => 'portrait-primary',
        'background_color' => '#fffbeb',
        'theme_color' => '#d97706',
        'icons' => [
            [
                'src' => asset('icons/icon-192.png'),
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src' => asset('icons/icon-512.png'),
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src' => asset('icons/icon-maskable-512.png'),
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'maskable',
            ],
        ],
    ], 200, [
        'Content-Type' => 'application/manifest+json',
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->name('pwa.manifest');
