<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class AvatarController extends Controller
{
    public function show($filename)
    {
        $path = 'avatars/' . $filename;

        if (! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        $file     = Storage::disk('public')->get($path);
        $mimeType = Storage::disk('public')->mimeType($path);

        return Response::make($file, 200, [
            'Content-Type'  => $mimeType ?: 'image/jpeg',
            'Cache-Control' => 'public, max-age=604800', // Cache for 1 week
        ]);
    }
}