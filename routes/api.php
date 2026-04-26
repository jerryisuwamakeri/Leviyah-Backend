<?php

use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\Auth\StaffAuthController;
use App\Http\Controllers\API\Auth\PasswordResetController;
use App\Http\Controllers\API\Shop\ProductController;
use App\Http\Controllers\API\Shop\CartController;
use App\Http\Controllers\API\Shop\OrderController;
use App\Http\Controllers\API\Shop\ChatController;
use App\Http\Controllers\API\Admin\DashboardController;
use App\Http\Controllers\API\Admin\ProductController as AdminProductController;
use App\Http\Controllers\API\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\API\Admin\StaffController;
use App\Http\Controllers\API\Admin\ChatController as AdminChatController;
use App\Http\Controllers\API\Admin\TransactionController;
use App\Http\Controllers\API\Admin\ActivityLogController;
use App\Http\Controllers\API\Admin\POSController;
use App\Http\Controllers\API\Admin\PromotionController;
use App\Http\Controllers\API\Admin\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'login']);
    Route::post('staff/login', [StaffAuthController::class, 'login']);
    Route::post('forgot-password',  [PasswordResetController::class, 'sendResetLink']);
    Route::post('reset-password',   [PasswordResetController::class, 'reset']);
});

// Shop - public
Route::prefix('shop')->group(function () {
    Route::get('categories',           [ProductController::class, 'categories']);
    Route::get('products',             [ProductController::class, 'index']);
    Route::get('products/featured',    [ProductController::class, 'featured']);
    Route::get('products/{slug}',      [ProductController::class, 'show']);
});

// Orders — checkout & verify are public (guest + auth)
Route::post('orders/checkout',               [OrderController::class, 'checkout']);
Route::post('orders/{order}/verify-payment', [OrderController::class, 'verifyPayment']);

// Cart (guest + auth)
Route::prefix('cart')->group(function () {
    Route::get('/',                        [CartController::class, 'index']);
    Route::post('add',                     [CartController::class, 'add']);
    Route::patch('items/{cartItem}',       [CartController::class, 'update']);
    Route::delete('items/{cartItem}',      [CartController::class, 'remove']);
    Route::post('coupon',                  [CartController::class, 'applyCoupon']);
    Route::delete('coupon',                [CartController::class, 'removeCoupon']);
    Route::delete('clear',                 [CartController::class, 'clear']);
});

// Public promotions (storefront banner)
Route::get('promotions/active', [PromotionController::class, 'active']);

// QR clock-in/out (public endpoint — validated by QR token)
Route::prefix('attendance')->group(function () {
    Route::post('clock-in',  [StaffController::class, 'clockIn']);
    Route::post('clock-out', [StaffController::class, 'clockOut']);
});

/*
|--------------------------------------------------------------------------
| Authenticated customer routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('logout',          [AuthController::class, 'logout']);
        Route::get('me',               [AuthController::class, 'me']);
        Route::post('profile',         [AuthController::class, 'updateProfile']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
    });

    Route::prefix('orders')->group(function () {
        Route::get('/',        [OrderController::class, 'index']);
        Route::get('{order}',  [OrderController::class, 'show']);
    });

    Route::prefix('chat')->group(function () {
        Route::get('my-conversation',                    [ChatController::class, 'myConversation']);
        Route::post('start',                             [ChatController::class, 'startConversation']);
        Route::post('{conversation}/message',            [ChatController::class, 'sendMessage']);
    });
});

/*
|--------------------------------------------------------------------------
| Staff auth routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:staff'])->prefix('staff')->group(function () {
    Route::post('logout', [StaffAuthController::class, 'logout']);
    Route::get('me',      [StaffAuthController::class, 'me']);
});

/*
|--------------------------------------------------------------------------
| Admin routes (staff only, role-based)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:staff'])->prefix('admin')->group(function () {

    Route::get('dashboard', [DashboardController::class, 'stats']);

    // Products
    Route::prefix('products')->group(function () {
        Route::get('/',                                [AdminProductController::class, 'index']);
        Route::post('/',                               [AdminProductController::class, 'store']);
        Route::get('categories',                       [AdminProductController::class, 'categories']);
        Route::post('categories',                      [AdminProductController::class, 'storeCategory']);
        Route::get('{product}',                        [AdminProductController::class, 'show']);
        Route::post('{product}',                       [AdminProductController::class, 'update']);
        Route::delete('{product}',                     [AdminProductController::class, 'destroy']);
        Route::post('{product}/variants',              [AdminProductController::class, 'storeVariant']);
        Route::patch('{product}/variants/{variant}',   [AdminProductController::class, 'updateVariant']);
        Route::delete('{product}/variants/{variant}',  [AdminProductController::class, 'destroyVariant']);
    });

    // Orders
    Route::prefix('orders')->group(function () {
        Route::get('/',                [AdminOrderController::class, 'index']);
        Route::get('{order}',          [AdminOrderController::class, 'show']);
        Route::patch('{order}/status', [AdminOrderController::class, 'updateStatus']);
    });

    // Transactions
    Route::prefix('transactions')->group(function () {
        Route::get('/',                     [TransactionController::class, 'index']);
        Route::get('{transaction}',         [TransactionController::class, 'show']);
        Route::patch('{transaction}',       [TransactionController::class, 'update']);
        Route::delete('{transaction}',      [TransactionController::class, 'destroy']);
    });

    // Staff management (admin/owner only)
    Route::prefix('staff')->group(function () {
        Route::get('/',                    [StaffController::class, 'index']);
        Route::post('/',                   [StaffController::class, 'store']);
        Route::get('{staff}',              [StaffController::class, 'show']);
        Route::post('{staff}',             [StaffController::class, 'update']);
        Route::delete('{staff}',           [StaffController::class, 'destroy']);
        Route::get('{staff}/qr-code',      [StaffController::class, 'qrCode']);
        Route::post('{staff}/regenerate-qr',[StaffController::class, 'regenerateQr']);
        Route::get('{staff}/attendance',   [StaffController::class, 'attendance']);
    });

    // Chat / Support
    Route::prefix('conversations')->group(function () {
        Route::get('/',                           [AdminChatController::class, 'index']);
        Route::get('{conversation}',              [AdminChatController::class, 'show']);
        Route::post('{conversation}/reply',       [AdminChatController::class, 'reply']);
        Route::patch('{conversation}/close',      [AdminChatController::class, 'close']);
        Route::patch('{conversation}/assign',     [AdminChatController::class, 'assign']);
    });

    // Profile (any authenticated staff)
    Route::get('profile',                  [ProfileController::class, 'show']);
    Route::post('profile',                 [ProfileController::class, 'update']);
    Route::post('profile/change-password', [ProfileController::class, 'changePassword']);

    // Activity logs
    Route::get('activity-logs', [ActivityLogController::class, 'index']);

    // Promotions
    Route::prefix('promotions')->group(function () {
        Route::get('/',                          [PromotionController::class, 'index']);
        Route::post('/',                         [PromotionController::class, 'store']);
        Route::patch('{promotion}',              [PromotionController::class, 'update']);
        Route::delete('{promotion}',             [PromotionController::class, 'destroy']);
        Route::patch('{promotion}/toggle',       [PromotionController::class, 'toggle']);
    });

    // POS
    Route::prefix('pos')->group(function () {
        Route::get('products',   [POSController::class, 'products']);
        Route::post('sale',      [POSController::class, 'createSale']);
    });
});
