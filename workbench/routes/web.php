<?php

use Illuminate\Support\Facades\Route;
use Workbench\App\Http\Controllers\Admin\ReportExportsController;
use Workbench\App\Http\Controllers\Admin\ReportsController;
use Workbench\App\Models\Admin;
use Workbench\App\Models\User;

// Login and set token to session
Route::get('/_workbench', function () {
    $admin = Admin::first();
    $user = User::first();

    $tokenAdmin = $admin->createToken('workbench-admin', ['admins']);
    $tokenUser = $user->createToken('workbench-user', ['users']);

    // Store tokens in session
    session([
        'admin_token' => $tokenAdmin->plainTextToken,
        'user_token' => $tokenUser->plainTextToken,
        'admin_user' => $admin->toArray(),
        'user_user' => $user->toArray(),
    ]);

    return redirect()->route('home');
})->name('_workbench');

// Home page with links to all test pages
Route::get('/home', function () {
    return view('index');
})->name('home');

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

Route::group([], __DIR__.'/foundry/web.php');
