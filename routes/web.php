<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminPageController;
use App\Http\Controllers\EmployeePageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UpdateDropdownListController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Auth/Login', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ── Employee & Admin ────────────────────────────────────────────────
    Route::controller(EmployeePageController::class)->group(function () {
        Route::get('/employee/page',                'display_employee') ->name('employee.display_employee');
        Route::post('/employee/new/record',         'store_new_record') ->name('employee.deseased_new_record');
        Route::put('/employee/edit/record/{id}',    'update_record')    ->name('desease.update_record');
        Route::delete('/employee/record/{id}',      'destroy')          ->name('employee.deseased_delete');
        Route::get('/employee/export',              'export')           ->name('employee.export');
        Route::get('/employee/export-check',        'exportCheck')      ->name('employee.export_check');
    });

    // ── Employee & Admin ────────────────────────────────────────────────
    Route::controller(UpdateDropdownListController::class)->group(function () {
        Route::post('/update/status',              'update_status')       ->name('update.status');
        Route::post('/update/rental/type',         'update_rental_type')  ->name('update.rental_type');
        Route::post('/update/location',            'update_location')     ->name('update.location');
    });

    // ── Admin ────────────────────────────────────────────────
    Route::controller(AdminPageController::class)->group(function () {
        Route::get('/user/accounts', 'user_page')->name('admin.display_accounts');
    });

    Route::controller(AccountController::class)->group(function () {
        Route::post('/user/change-status', 'change_status')->name('accounts.change_status');
        Route::post('/user/update/password', 'update_password')->name('update.password');
    });

});

require __DIR__.'/auth.php';
require __DIR__.'/cedula.php';
