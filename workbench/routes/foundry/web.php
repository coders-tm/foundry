<?php

use Illuminate\Support\Facades\Route;
use Workbench\App\Http\Controllers\Admin\WalletController as AdminWalletController;
use Workbench\App\Http\Controllers\AdminController;
use Workbench\App\Http\Controllers as App;
use Workbench\App\Http\Controllers as Foundry;
use Workbench\App\Http\Controllers\Auth;
use Workbench\App\Http\Controllers\OrderController;
use Workbench\App\Http\Controllers\Payment;
use Workbench\App\Http\Controllers\Subscription;
use Workbench\App\Http\Controllers\SupportTicketController;
use Workbench\App\Http\Controllers\User\WalletController;
use Workbench\App\Http\Controllers\UserController;

// Subscription Promo Code Check Route
Route::post('subscriptions/check-promo-code', [Subscription\SubscriptionController::class, 'checkPromoCode'])
    ->name('subscriptions.check-promo-code');

// Auth Routes
Route::group([
    'as' => 'auth.',
    'prefix' => 'auth/{guard?}',
], function () {
    Route::controller(Auth\AuthController::class)->group(function () {
        Route::post('signup', 'signup')->name('signup');
        Route::post('login', 'login')->name('login');
        Route::middleware('auth:user,admin')->group(function () {
            Route::post('logout', 'logout')->name('logout');
            Route::post('update', 'update')->name('update');
            Route::post('change-password', 'password')->name('change-password');
            Route::post('me', 'me')->name('current');
            Route::post('request-account-deletion', 'requestAccountDeletion')->name('request-account-deletion');
            Route::post('add-device-token', 'addDeviceToken')->name('add-device-token');
        });
    });
    Route::group([
        'as' => 'password.',
        'controller' => Auth\ForgotPasswordController::class,
    ], function () {
        Route::post('request-password', 'request')->name('request');
        Route::post('reset-password', 'reset')->name('reset');
    });
});

// Core Routes (admin-only)
Route::middleware(['auth:admin'])->group(function () {
    // Notification templates
    Route::group([
        'as' => 'settings.notifications.',
        'prefix' => 'settings/notifications',
        'middleware' => 'can:update,Foundry\Models\Notification',
        'controller' => Foundry\NotificationController::class,
    ], function () {
        Route::post('{notification}/mark-as-default', 'markAsDefault')->name('mark-as-default');
        Route::post('{notification}/duplicate', 'duplicate')->name('duplicate');
    });
    Route::apiResource('settings/notifications', Foundry\NotificationController::class, [
        'as' => 'settings',
        'middleware' => 'can:update,Foundry\Models\Notification',
        'only' => ['index', 'show', 'update', 'destroy'],
    ]);

    // Application Settings
    Route::group([
        'as' => 'application.',
        'prefix' => 'application',
        'controller' => Foundry\ApplicationController::class,
    ], function () {
        Route::get('stats', 'stats')->name('stats');
        Route::post('test-mail-config', 'testMailConfig')->name('test-mail-config');
        Route::get('settings/{key}', 'getSettings')->name('get-settings');
        Route::middleware('can:update,Foundry\Models\Setting')->group(function () {
            Route::post('settings', 'updateSettings')->name('update-settings');
        });
    });

    // Admins
    Route::group([
        'as' => 'admins.',
        'prefix' => 'admins',
        'controller' => AdminController::class,
    ], function () {
        Route::get('options', 'options')->name('options');
        Route::post('import', 'import')->name('import');
        Route::get('modules', 'modules')->name('modules');
        Route::group(['middleware' => 'can:update,admin'], function () {
            Route::post('{admin}/reset-password-request', 'resetPasswordRequest')->name('reset-password-request');
            Route::post('{admin}/change-active', 'changeActive')->name('change-active');
            Route::post('{admin}/change-admin', 'changeAdmin')->name('change-admin');
        });
    });
    Route::resource('admins', AdminController::class);

    // Groups
    Route::group([
        'as' => 'groups.',
        'prefix' => 'groups',
        'controller' => Foundry\GroupController::class,
    ], function () {
        Route::delete('destroy-selected', 'destroySelected')->name('destroy-selected');
        Route::patch('{id}/restore', 'restore')->name('restore');
        Route::patch('restore-selected', 'restoreSelected')->name('restore-selected');
    });
    Route::resource('groups', Foundry\GroupController::class);

    // Logs
    Route::post('logs/{log}/reply', [Foundry\LogController::class, 'reply'])->name('logs.reply');
    Route::resource('logs', Foundry\LogController::class)->only(['show', 'update', 'destroy']);

    // App Payment Methods
    Route::group([
        'controller' => Foundry\PaymentMethodController::class,
        'as' => 'payment-methods.',
        'prefix' => 'payment-methods',
    ], function () {
        Route::post('{payment_method}/disable', 'disable')->name('disable');
        Route::post('{payment_method}/enable', 'enable')->name('enable');
    });
    Route::resource('payment-methods', Foundry\PaymentMethodController::class)->only(['index', 'store', 'show', 'update']);
});

// Shared authenticated routes (accessible to both users and admins)
Route::middleware(['auth:user,admin'])->group(function () {
    // Files
    Route::post('files/upload-from-source', [Foundry\FileController::class, 'uploadFromSource'])->name('files.upload-from-source');
    Route::resource('files', Foundry\FileController::class)->except(['destroySelected', 'restore', 'restoreSelected']);

    // Support Tickets
    Route::group([
        'controller' => SupportTicketController::class,
        'middleware' => 'can:update,support_ticket',
        'as' => 'support-tickets.',
        'prefix' => 'support-tickets',
    ], function () {
        Route::post('{support_ticket}/reply', 'reply')->name('reply');
        Route::post('{support_ticket}/change-user-archived', 'changeUserArchived')->name('change-user-archived');
        Route::post('{support_ticket}/change-archived', 'changeArchived')->name('change-archived');
    });
    Route::resource('support-tickets', SupportTicketController::class);

    // Subscription
    Route::group([
        'as' => 'subscriptions.',
        'prefix' => 'subscriptions',
    ], function () {
        Route::get('/', [Subscription\SubscriptionController::class, 'index'])->name('index');
        Route::get('/{subscription}', [Subscription\SubscriptionController::class, 'show'])->name('show');
        Route::post('/{subscription}/resume', [Subscription\SubscriptionController::class, 'resume'])->name('resume');
        Route::post('/{subscription}/cancel-downgrade', [Subscription\SubscriptionController::class, 'cancelDowngrade'])->name('cancel-downgrade');
        Route::post('/{subscription}/cancel', [Subscription\SubscriptionController::class, 'cancel'])->name('cancel');
        Route::get('/{subscription}/invoices', [Subscription\SubscriptionController::class, 'invoices'])->name('invoices');
    });
});

// User Routes (subscription management)
Route::middleware(['auth:user'])->group(function () {
    // Subscription
    Route::group([
        'as' => 'subscriptions.',
        'prefix' => 'subscriptions',
    ], function () {
        Route::get('/current', [Subscription\SubscriptionController::class, 'current'])->name('current');
        Route::post('/subscribe', [Subscription\SubscriptionController::class, 'subscribe'])->name('subscribe');
    });

    // Invoices
    Route::group([
        'as' => 'invoices.',
        'prefix' => 'invoices',
    ], function () {
        Route::post('/', [Foundry\InvoiceController::class, 'invoices']);
        Route::get('/{invoice}', [Foundry\InvoiceController::class, 'downloadInvoice'])->name('download');
    });

    // Subscription Orders
    Route::group([
        'as' => 'orders.',
        'prefix' => 'orders',
        'controller' => OrderController::class,
    ], function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{order}', 'show')->name('show');
        Route::post('/{order}/cancel', 'cancel')->name('cancel');
    });

    // Wallet
    Route::group([
        'as' => 'user.wallet.',
        'prefix' => 'user/wallet',
        'controller' => WalletController::class,
    ], function () {
        Route::get('balance', 'balance')->name('balance');
        Route::get('transactions', 'transactions')->name('transactions');
    });
});

// Admin Routes
Route::middleware(['auth:admin'])->prefix('admin')->name('admin.')->group(function () {
    // Exchange Rates
    Route::group(['prefix' => 'exchange-rates', 'as' => 'exchange-rates.'], function () {
        Route::get('/', [Foundry\ExchangeRateController::class, 'index'])->name('index');
        Route::post('/', [Foundry\ExchangeRateController::class, 'store'])->name('store');
        Route::post('/sync', [Foundry\ExchangeRateController::class, 'sync'])->name('sync');
        Route::delete('/{id}', [Foundry\ExchangeRateController::class, 'destroy'])->name('destroy');
    });

    // Subscription Management
    Route::group([
        'as' => 'subscriptions.',
        'prefix' => 'subscriptions',
    ], function () {
        Route::post('/', [Subscription\SubscriptionController::class, 'store'])->name('store');
        Route::post('/{subscription}', [Subscription\SubscriptionController::class, 'update'])->name('update');
        Route::post('/{subscription}/pay', [Subscription\SubscriptionController::class, 'pay'])->name('pay');
        Route::post('/{subscription}/freeze', [Subscription\SubscriptionController::class, 'freeze'])->name('freeze');
        Route::post('/{subscription}/unfreeze', [Subscription\SubscriptionController::class, 'unfreeze'])->name('unfreeze');
    });

    // Subscription Orders (admin view)
    Route::group([
        'as' => 'orders.',
        'prefix' => 'orders',
        'controller' => OrderController::class,
    ], function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('export', 'export')->name('export');
        Route::post('bulk-destroy', 'bulkDestroy')->name('bulk-destroy');
        Route::post('bulk-restore', 'bulkRestore')->name('bulk-restore');
        Route::get('{id}', 'show')->name('show');
        Route::match(['put', 'patch'], '{order}', 'update')->name('update');
        Route::delete('{id}', 'destroy')->name('destroy');
        Route::post('{id}/restore', 'restore')->name('restore');
        Route::get('{order}/logs', 'logs')->name('logs');
        Route::post('{order}/logs', 'storeLog')->name('store-log');
        Route::post('{order}/status', 'updateStatus')->name('update-status');
        Route::post('{order}/cancel', 'cancel')->name('cancel');
        Route::post('{order}/mark-as-paid', 'markAsPaid')->name('mark-as-paid');
        Route::post('{order}/send-invoice', 'sendInvoice')->name('send-invoice');
        Route::get('{order}/download-invoice', 'downloadInvoice')->name('download-invoice');
        Route::post('{order}/refund', 'refund')->name('refund');
        Route::post('calculator', 'calculator')->name('calculator');
    });

    // Users
    Route::group(['prefix' => 'users', 'as' => 'users.'], function () {
        Route::group(['controller' => UserController::class], function () {
            Route::post('options', 'options')->name('options');
            Route::post('import', 'import')->name('import');
            Route::post('{user}/change-active', 'changeActive')->name('change-active');
            Route::post('{user}/notes', 'notes')->name('notes');
            Route::post('{user}/mark-as-paid', 'markAsPaid')->name('mark-as-paid');
            Route::post('{user}/reset-password-request', 'resetPasswordRequest')->name('reset-password-request');
        });

        Route::group([
            'as' => 'wallet.',
            'prefix' => '{user}/wallet',
            'controller' => AdminWalletController::class,
        ], function () {
            Route::get('balance', 'balance')->name('balance');
            Route::get('transactions', 'transactions')->name('transactions');
            Route::post('credit', 'credit')->name('credit');
            Route::post('debit', 'debit')->name('debit');
        });
    });

    // Coupons
    Route::group([
        'as' => 'coupons.',
        'prefix' => 'coupons',
        'controller' => Subscription\CouponController::class,
    ], function () {
        Route::delete('destroy-selected', 'destroySelected')->name('destroy-selected');
        Route::patch('{id}/restore', 'restore')->name('restore');
        Route::patch('restore-selected', 'restoreSelected')->name('restore-selected');
        Route::post('{coupon}/change-active', 'changeActive')->name('change-active');
        Route::post('{coupon}/logs', 'logs')->name('logs');
        Route::get('products', 'products')->name('products');
        Route::get('plans', 'plans')->name('plans');
    });
    Route::resource('coupons', Subscription\CouponController::class);

    // Taxes
    Route::group([
        'as' => 'taxes.',
        'prefix' => 'taxes',
        'controller' => Foundry\TaxController::class,
    ], function () {
        Route::delete('destroy-selected', 'destroySelected')->name('destroy-selected');
    });
    Route::resource('taxes', Foundry\TaxController::class)->only(['index', 'store', 'update', 'destroy']);

    // Blogs
    Route::group([
        'as' => 'blogs.',
        'prefix' => 'blogs',
        'controller' => Foundry\BlogController::class,
    ], function () {
        Route::delete('destroy-selected', 'destroySelected')->name('destroy-selected');
        Route::patch('{id}/restore', 'restore')->name('restore');
        Route::patch('restore-selected', 'restoreSelected')->name('restore-selected');
    });
    Route::resource('blogs', Foundry\BlogController::class);

    Route::group(['prefix' => 'themes', 'as' => 'themes.'], function () {
        Route::get('/', [Foundry\ThemeController::class, 'index'])->name('index');
        Route::post('/{theme}/active', [Foundry\ThemeController::class, 'activate'])->name('activate');
        Route::delete('/{theme}/destroy', [Foundry\ThemeController::class, 'destroy'])->name('destroy');
        Route::post('/{theme}/clone', [Foundry\ThemeController::class, 'clone'])->name('clone');
        Route::post('/{theme}/assets', [Foundry\ThemeController::class, 'assetsUpload'])->name('assets');

        Route::group(['prefix' => '{theme}/files'], function () {
            Route::get('/', [Foundry\ThemeController::class, 'getFiles'])->name('files.list');
            Route::post('/', [Foundry\ThemeController::class, 'saveFile'])->name('files.save');
            Route::post('/create', [Foundry\ThemeController::class, 'createFile'])->name('files.create');
            Route::get('/content', [Foundry\ThemeController::class, 'getFileContent'])->name('files.content');
            Route::delete('/destroy', [Foundry\ThemeController::class, 'destroyThemeFile'])->name('files.destroy');
        });
    });
});

// Users (protected by auth:admin but without admin prefix)
Route::middleware(['auth:admin'])->group(function () {
    // Users custom routes
    Route::group(['controller' => UserController::class, 'as' => 'users.', 'prefix' => 'users'], function () {
        Route::post('options', 'options')->name('options');
        Route::post('import', 'import')->name('import');
        Route::post('{user}/change-active', 'changeActive')->name('change-active');
        Route::post('{user}/notes', 'notes')->name('notes');
        Route::post('{user}/mark-as-paid', 'markAsPaid')->name('mark-as-paid');
        Route::post('{user}/reset-password-request', 'resetPasswordRequest')->name('reset-password-request');
    });

    // Users resource routes
    Route::group([
        'as' => 'users.',
        'prefix' => 'users',
        'controller' => UserController::class,
    ], function () {
        Route::delete('destroy-selected', 'destroySelected')->name('destroy-selected');
        Route::patch('{id}/restore', 'restore')->name('restore');
        Route::patch('restore-selected', 'restoreSelected')->name('restore-selected');
    });
    Route::resource('users', UserController::class);
});

Route::group(['prefix' => 'shared'], function () {
    Route::get('plans', [Foundry\PlanController::class, 'shared'])->name('plans.shared');
    Route::get('plans/features', [Foundry\PlanController::class, 'features'])->name('plans.features');
});

Route::group(['controller' => Foundry\ApplicationController::class, 'prefix' => 'application', 'as' => 'application.'], function () {
    Route::get('config', 'config')->name('config');
    Route::get('payment-methods', 'paymentMethods')->name('payment-methods');
});

// Payments
Route::group(['prefix' => 'payment', 'as' => 'payment.'], function () {
    Route::get('status/{token}', [Foundry\PaymentController::class, 'status'])->name('status');
    Route::post('setup-intent', [Foundry\PaymentController::class, 'setupPaymentIntent'])->name('setup-intent');
    Route::post('confirm', [Foundry\PaymentController::class, 'confirmPayment'])->name('confirm');
});

Route::get('/themes/{theme}/assets', [Foundry\ThemeController::class, 'assets'])->name('themes.assets.preview');

// Exchange Rates
Route::get('/exchange-rates/estimate', [Foundry\ExchangeRateController::class, 'estimate'])->name('exchange-rates.estimate');
Route::get('/exchange-rates', [Foundry\ExchangeRateController::class, 'index'])->name('exchange-rates.index');
