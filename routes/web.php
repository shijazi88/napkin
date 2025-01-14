<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\TextToImageController;

Route::get('/', [TextToImageController::class, 'index'])->name('home');
Route::post('/generate', [TextToImageController::class, 'generatePresentation'])->name('generate.presentation');
