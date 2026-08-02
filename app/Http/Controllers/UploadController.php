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
}