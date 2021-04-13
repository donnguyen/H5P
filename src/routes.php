<?php

use Illuminate\Support\Facades\Route;
use EscolaLms\HeadlessH5P\Http\Controllers\LibraryApiController;
use EscolaLms\HeadlessH5P\Http\Controllers\EditorApiController;

Route::group(['middleware' => ['api'], 'prefix' => 'api/hh5p'], function () {
    Route::get('/', function () {
        return 'Hello World';
    })->name('hh5p.index'); // DO not remove this is needed as prefix for editor ajax calls
    Route::post('library', [LibraryApiController::class, 'store'])->name('hh5p.library.store');
    Route::get('library', [LibraryApiController::class, 'index'])->name('hh5p.library.list');
    Route::delete('library/{id}', [LibraryApiController::class, 'destroy'])->name('hh5p.library.delete');
    Route::get('editor', [EditorApiController::class])->name('hh5p.editor.settings');
    Route::get('libraries', [LibraryApiController::class, 'libraries'])->name('hh5p.library.libraries');
    Route::post('libraries', [LibraryApiController::class, 'libraries'])->name('hh5p.library.libraries');
});