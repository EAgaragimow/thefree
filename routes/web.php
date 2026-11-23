<?php

use App\Http\Controllers\DashboardRedirectController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::livewire('/become-a-freelancer', 'pages::freelancer.apply')->name('freelancer.apply');
Route::livewire('/freelancer/activate/{token}', 'pages::freelancer.activate')
    ->middleware('guest')
    ->name('freelancer.activate');

Route::middleware(['auth', 'active', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardRedirectController::class)->name('dashboard');

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::livewire('/profile', 'pages::settings.profile')->name('profile');
        Route::livewire('/password', 'pages::settings.password')->name('password');
        Route::livewire('/account', 'pages::settings.account')->name('account');
    });

    Route::middleware('role:client')->prefix('client')->name('client.')->group(function () {
        Route::livewire('/dashboard', 'pages::client.dashboard')->name('dashboard');
        Route::livewire('/brand-profile', 'pages::client.brand-profile')->name('brand-profile');
    });

    Route::middleware('role:freelancer')->prefix('freelancer')->name('freelancer.')->group(function () {
        Route::livewire('/dashboard', 'pages::freelancer.dashboard')->name('dashboard');
        Route::livewire('/profile', 'pages::freelancer.profile')->name('profile');
    });
});
