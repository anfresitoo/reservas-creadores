<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'creatorSlug' => 'alex-dev',
    ]);
})->name('home');
