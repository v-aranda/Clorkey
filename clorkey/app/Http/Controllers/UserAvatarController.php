<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class UserAvatarController extends Controller
{
    public function show(User $user)
    {
        abort_unless($user->avatar_path, 404);
        abort_unless(Storage::disk('s3')->exists($user->avatar_path), 404);

        $stream = Storage::disk('s3')->readStream($user->avatar_path);
        abort_unless($stream, 404);

        $mime = Storage::disk('s3')->mimeType($user->avatar_path) ?: 'application/octet-stream';
        $size = Storage::disk('s3')->size($user->avatar_path);

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, Response::HTTP_OK, array_filter([
            'Content-Type' => $mime,
            'Content-Length' => $size ?: null,
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]));
    }
}
