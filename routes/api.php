<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\Admin\PostController as AdminPostController;
use App\Http\Controllers\Admin\StatsController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// ------------------------------------------------------------------
// Authentication routes
// ------------------------------------------------------------------
Route::prefix('auth')->group(function () {
    // Public — rate-limited (5 requests / minute per IP)
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/login',    [AuthController::class, 'login'])->middleware('throttle:5,1');

    // Protected
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me',      [AuthController::class, 'me']);
    });
});

// ------------------------------------------------------------------
// Public routes
// ------------------------------------------------------------------
Route::get('/posts',                   [PostController::class, 'index']);
Route::get('/posts/pinned',            [PostController::class, 'pinned']);
Route::get('/posts/{slug}',            [PostController::class, 'show']);
Route::get('/posts/{id}/comments',     [CommentController::class, 'index']);
Route::get('/about',                   [AboutController::class, 'show']);
Route::get('/tags',                    [TagController::class, 'index']);

// ------------------------------------------------------------------
// Authenticated routes (auth:sanctum)
// ------------------------------------------------------------------
Route::middleware('auth:sanctum')->group(function () {
    // Post status (likes/favorites for current user)
    Route::get('/posts/{slug}/status', [PostController::class, 'status']);
    
    // Likes
    Route::post('/posts/{id}/like',    [LikeController::class, 'store']);
    Route::delete('/posts/{id}/like',  [LikeController::class, 'destroy']);

    // Favorites
    Route::post('/posts/{id}/favorite',    [FavoriteController::class, 'store']);
    Route::delete('/posts/{id}/favorite',  [FavoriteController::class, 'destroy']);
    Route::get('/user/favorites',          [FavoriteController::class, 'index']);

    // Comments
    Route::post('/posts/{id}/comments',  [CommentController::class, 'store']);
    Route::put('/comments/{id}',         [CommentController::class, 'update']);
    Route::delete('/comments/{id}',      [CommentController::class, 'destroy']);
    Route::post('/comments/{id}/reply',  [CommentController::class, 'reply']);

    // Conversations & Messages
    Route::get('/conversations',           [ConversationController::class, 'index']);
    Route::get('/conversations/{userId}',  [ConversationController::class, 'show']);
    Route::post('/messages',               [MessageController::class, 'store']);
    Route::patch('/messages/{id}/read',    [MessageController::class, 'markRead']);

    // Notifications
    Route::get('/notifications',              [NotificationController::class, 'index']);
    Route::patch('/notifications/read-all',   [NotificationController::class, 'readAll']);
    Route::patch('/notifications/{id}/read',  [NotificationController::class, 'read']);

    // Profile
    Route::get('/profile',         [ProfileController::class, 'show']);
    Route::put('/profile',         [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'avatar']);
});

// ------------------------------------------------------------------
// Admin routes (auth:sanctum + admin middleware)
// ------------------------------------------------------------------
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    // Posts
    Route::get('/posts',               [AdminPostController::class, 'index']);
    Route::get('/posts/stats',         [StatsController::class, 'index']);
    Route::get('/posts/{id}',          [AdminPostController::class, 'show']);
    Route::post('/posts',              [AdminPostController::class, 'store']);
    Route::put('/posts/{id}',          [AdminPostController::class, 'update']);
    Route::delete('/posts/{id}',       [AdminPostController::class, 'destroy']);
    Route::patch('/posts/{id}/pin',    [AdminPostController::class, 'pin']);
    Route::patch('/posts/{id}/publish',[AdminPostController::class, 'publish']);

    // About (admin update)
    Route::put('/about', [AboutController::class, 'update']);
    Route::post('/about/photo', [AboutController::class, 'uploadPhoto']);
    
    // Comments (admin)
    Route::get('/comments', [CommentController::class, 'adminIndex']);
});
