<?php

use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/', fn() => redirect()->route('uploads.index'));

Route::get('/uploads',      [UploadController::class, 'index'])->name('uploads.index');
Route::post('/uploads',     [UploadController::class, 'store'])->name('uploads.store');
Route::get('/uploads/poll', [UploadController::class, 'poll'])->name('uploads.poll');


Route::get('/products', function () {
    return DB::table('products')->orderBy('id', 'desc')->limit(50)->get();
});


Route::get('/products', fn() => DB::table('products')->orderBy('id', 'desc')->limit(50)->get());
