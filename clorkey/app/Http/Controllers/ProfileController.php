<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
            $newPath = 'avatars/' . $user->id . '_' . Str::uuid()->toString() . '.' . $ext;

            $stored = Storage::disk('s3')->putFileAs('avatars', $file, basename($newPath));
            if (!$stored) {
                return back()->withErrors([
                    'avatar' => 'Não foi possível enviar a foto de perfil. Tente novamente.',
                ]);
            }

            if ($user->avatar_path) {
                Storage::disk('s3')->delete($user->avatar_path);
            }

            $user->avatar_path = $stored;
        } elseif ($request->boolean('remove_avatar')) {
            if ($user->avatar_path) {
                Storage::disk('s3')->delete($user->avatar_path);
            }
            $user->avatar_path = null;
        }

        $user->save();

        return Redirect::route('profile.edit');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        if ($user->avatar_path) {
            Storage::disk('s3')->delete($user->avatar_path);
        }

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
