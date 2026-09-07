<?php

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportPptxController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\VkOAuthController;
use App\Http\Controllers\VkSyncController;
use Illuminate\Support\Facades\Route;

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

Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
Route::post('/settings/vkid', [SettingsController::class, 'vkid'])->name('settings.vkid');

Route::get('/vk/connect', [VkOAuthController::class, 'connect'])->name('vk.connect');
Route::get('/vk/callback', [VkOAuthController::class, 'callback'])->name('vk.callback');

Route::post('/upload', [UploadController::class, 'store']);
