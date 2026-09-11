<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\GoogleOAuthController;
use App\Http\Controllers\InstagramOAuthController;
use App\Http\Controllers\InstagramSyncController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportPptxController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\TelegramAuthController;
use App\Http\Controllers\TelegramSyncController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\VkOAuthController;
use App\Http\Controllers\VkSyncController;
use App\Http\Controllers\YouTubeSyncController;
use App\Http\Middleware\PortalAuth;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'show'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ссылка для клиента: просмотр проекта и отчётов без входа
Route::get('/share/{token}', [ShareController::class, 'project'])->name('share.project');
Route::get('/share/{token}/reports/{report}', [ShareController::class, 'report'])->name('share.report');

Route::middleware(PortalAuth::class)->group(function () {

Route::get('/', fn () => redirect()->route('projects.index'));

Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
Route::post('/projects/{project}/share', [ProjectController::class, 'share'])->name('projects.share');
Route::delete('/projects/{project}/share', [ProjectController::class, 'unshare'])->name('projects.unshare');

Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');

Route::post('/projects/{project}/reports', [ReportController::class, 'store'])->name('reports.store');
Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reports.show');
Route::put('/reports/{report}', [ReportController::class, 'update'])->name('reports.update');
Route::delete('/reports/{report}', [ReportController::class, 'destroy'])->name('reports.destroy');
Route::get('/reports/{report}/pptx', [ReportPptxController::class, 'download'])->name('reports.pptx');
Route::post('/reports/{report}/vk-sync', [VkSyncController::class, 'sync'])->name('reports.vk-sync');
Route::post('/reports/{report}/youtube-sync', [YouTubeSyncController::class, 'sync'])->name('reports.youtube-sync');
Route::post('/reports/{report}/instagram-sync', [InstagramSyncController::class, 'sync'])->name('reports.instagram-sync');
Route::post('/reports/{report}/telegram-sync', [TelegramSyncController::class, 'sync'])->name('reports.telegram-sync');

Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
Route::post('/settings/vkid', [SettingsController::class, 'vkid'])->name('settings.vkid');
Route::post('/settings/google', [SettingsController::class, 'google'])->name('settings.google');
Route::post('/settings/instagram', [SettingsController::class, 'instagram'])->name('settings.instagram');
Route::post('/settings/telegram', [SettingsController::class, 'telegram'])->name('settings.telegram');
Route::post('/telegram/login/start', [TelegramAuthController::class, 'start'])->middleware('throttle:10,1')->name('telegram.login.start');
Route::post('/telegram/login/code', [TelegramAuthController::class, 'code'])->middleware('throttle:10,1')->name('telegram.login.code');
Route::post('/telegram/login/password', [TelegramAuthController::class, 'password'])->middleware('throttle:10,1')->name('telegram.login.password');
Route::post('/telegram/login/cancel', [TelegramAuthController::class, 'cancel'])->name('telegram.login.cancel');
Route::post('/telegram/logout', [TelegramAuthController::class, 'logout'])->name('telegram.logout');

Route::get('/vk/connect', [VkOAuthController::class, 'connect'])->name('vk.connect');
Route::get('/vk/callback', [VkOAuthController::class, 'callback'])->name('vk.callback');

Route::get('/google/connect', [GoogleOAuthController::class, 'connect'])->name('google.connect');
Route::get('/google/callback', [GoogleOAuthController::class, 'callback'])->name('google.callback');

Route::get('/projects/{project}/instagram/connect', [InstagramOAuthController::class, 'connect'])->name('instagram.connect');
Route::post('/projects/{project}/instagram/disconnect', [InstagramOAuthController::class, 'disconnect'])->name('instagram.disconnect');
Route::get('/instagram/callback', [InstagramOAuthController::class, 'callback'])->name('instagram.callback');

Route::post('/upload', [UploadController::class, 'store']);

});
