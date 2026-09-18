<?php

use App\Http\Controllers\ReplayController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Home', [
    'sampleLog' => Inertia::optional(fn (): string => (string) file_get_contents(resource_path('samples/froslass-vs-toucannon.txt'))),
]))->name('home');

Route::get('replays', [ReplayController::class, 'index'])->name('replays.index');
Route::post('replays', [ReplayController::class, 'store'])->middleware('throttle:10,1')->name('replays.store');
Route::get('replay/{replay:slug}', [ReplayController::class, 'show'])->name('replays.show');
Route::patch('replay/{replay:slug}', [ReplayController::class, 'update'])->name('replays.update');
Route::delete('replay/{replay:slug}', [ReplayController::class, 'destroy'])->name('replays.destroy');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('my-replays', [ReplayController::class, 'mine'])->name('replays.mine');
});

require __DIR__.'/settings.php';
