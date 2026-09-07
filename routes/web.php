<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GoogleOAuthController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportPptxController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\VkOAuthController;
use App\Http\Controllers\VkSyncController;
use App\Http\Controllers\YouTubeSyncController;
use App\Http\Middleware\PortalAuth;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'show'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(PortalAuth::class)->group(function () {

Route::get('/', fn () => redirect()->route('projects.index'));

Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

Route::post('/projects/{project}/reports', [ReportController::class, 'store'])->name('reports.store');
Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reports.show');
Route::put('/reports/{report}', [ReportController::class, 'update'])->name('reports.update');
Route::delete('/reports/{report}', [ReportController::class, 'destroy'])->name('reports.destroy');
Route::get('/reports/{report}/pptx', [ReportPptxController::class, 'download'])->name('reports.pptx');
Route::post('/reports/{report}/vk-sync', [VkSyncController::class, 'sync'])->name('reports.vk-sync');
Route::post('/reports/{report}/youtube-sync', [YouTubeSyncController::class, 'sync'])->name('reports.youtube-sync');

Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
Route::post('/settings/vkid', [SettingsController::class, 'vkid'])->name('settings.vkid');
Route::post('/settings/google', [SettingsController::class, 'google'])->name('settings.google');

Route::get('/vk/connect', [VkOAuthController::class, 'connect'])->name('vk.connect');
Route::get('/vk/callback', [VkOAuthController::class, 'callback'])->name('vk.callback');

Route::get('/google/connect', [GoogleOAuthController::class, 'connect'])->name('google.connect');
Route::get('/google/callback', [GoogleOAuthController::class, 'callback'])->name('google.callback');

Route::post('/upload', [UploadController::class, 'store']);

});
