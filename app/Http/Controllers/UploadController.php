<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:5120', // до 5 МБ
        ]);

        $path = $request->file('image')->store('content', 'public');

        return response()->json([
            'path' => $path,
            'url' => Storage::url($path),
        ]);
    }

    // файлы для публикаций контент-плана: картинки и видео (лимит задаётся и в php.ini/nginx на сервере)
    public function media(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,webp,gif,mp4,mov,m4v|max:512000', // до 500 МБ
        ]);

        $file = $request->file('file');
        $path = $file->store('posts', 'public');

        return response()->json([
            'path' => $path,
            'url' => Storage::url($path),
            'type' => str_starts_with((string) $file->getMimeType(), 'video') ? 'video' : 'image',
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
        ]);
    }
}