<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Workbench\App\Http\Controllers\Admin\ReportExportsController;
use Workbench\App\Http\Controllers\Admin\ReportsController;

// Reports & Analytics
Route::group([
    'as' => 'reports.',
    'prefix' => 'admin/reports',
    'middleware' => ['auth:admin'],
], function () {
    // Dashboard & Overview
    Route::get('charts', [ReportsController::class, 'charts'])->name('charts');
    Route::get('metrics', [ReportsController::class, 'metrics'])->name('metrics');
    Route::get('kpis', [ReportsController::class, 'kpis'])->name('kpis');
    Route::post('clear-cache', [ReportsController::class, 'clearCache'])->name('clear-cache');

    // Report Exports Management
    Route::group([
        'as' => 'exports.',
        'prefix' => 'exports',
    ], function () {
        Route::get('/', [ReportExportsController::class, 'index'])->name('index');
        Route::post('/', [ReportExportsController::class, 'store'])->name('store');
        Route::get('/data', [ReportExportsController::class, 'data'])->name('data');
        Route::get('/available', [ReportExportsController::class, 'available'])->name('available');
        Route::get('/metadata', [ReportExportsController::class, 'metadata'])->name('metadata');
        Route::post('cleanup', [ReportExportsController::class, 'cleanup'])->name('cleanup');
        Route::delete('destroy', [ReportExportsController::class, 'destroyMultiple'])->name('destroy-multiple');
        Route::get('{reportExport}', [ReportExportsController::class, 'show'])->name('show');
        Route::get('{reportExport}/download', [ReportExportsController::class, 'download'])->name('download');
        Route::delete('{reportExport}', [ReportExportsController::class, 'destroy'])->name('destroy');
        Route::post('{reportExport}/retry', [ReportExportsController::class, 'retry'])->name('retry');
    });
});

Route::get('/login', function () {
    return view('auth.login');
})->middleware(['web', 'guest:user'])->name('login');

Route::post('/login', function (Request $request) {
    if (Auth::guard('user')->attempt($request->only('email', 'password'))) {
        return redirect()->intended('/dashboard');
    }

    return back();
})->middleware(['web', 'guest:user'])->name('login.store');

Route::get('/admin/login', function () {
    return view('admin.login');
})->middleware(['web', 'guest:admin'])->name('admin.login');

Route::post('/admin/login', function (Request $request) {
    if (Auth::guard('admin')->attempt($request->only('email', 'password'))) {
        return redirect()->intended('/admin');
    }

    return back();
})->middleware(['web', 'guest:admin'])->name('admin.login.store');

Route::group([], __DIR__.'/foundry/web.php');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['web', 'auth:user'])->name('dashboard');

Route::get('/admin', function () {
    return view('admin.dashboard');
})->middleware(['web', 'auth:admin'])->name('admin.dashboard');

Route::post('/logout', function () {
    Auth::guard('user')->logout();

    return redirect()->route('login');
})->middleware(['web'])->name('logout');

Route::post('/admin/logout', function () {
    Auth::guard('admin')->logout();

    return redirect()->route('admin.login');
})->middleware(['web'])->name('admin.logout');
