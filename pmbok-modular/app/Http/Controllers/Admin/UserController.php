<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $search = request('search');
        $status = request('status', 'active');

        $query = User::latest();

        if ($status === 'inactive') {
            $query->onlyTrashed();
        }

        $query->when(
            $search,
            fn($q) => $q
                ->where('name', 'ilike', "%{$search}%")
                ->orWhere('email', 'ilike', "%{$search}%")
        );

        return Inertia::render('Admin/Users/Index', [
            'users' => $query
                ->paginate(10)
                ->withQueryString()
                ->through(fn($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'avatar_url' => $user->avatar_url,
                    'created_at' => $user->created_at->format('d/m/Y H:i'),
                    'deleted_at' => $user->deleted_at?->format('d/m/Y H:i'),
                ]),
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['avatar']);

        $user = User::create($data);

        if ($request->hasFile('avatar')) {
            $ext = $request->file('avatar')->getClientOriginalExtension();
            $path = $request->file('avatar')->storeAs(
                'avatars',
                $user->id . '_' . time() . '.' . $ext,
                's3'
            );
            $user->update(['avatar_path' => $path]);
        }

        return back()->with('success', 'Usuário criado com sucesso.');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->safe()->except(['avatar', 'remove_avatar']);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        if ($request->boolean('remove_avatar') && $user->avatar_path) {
            Storage::disk('s3')->delete($user->avatar_path);
            $data['avatar_path'] = null;
        } elseif ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('s3')->delete($user->avatar_path);
            }
            $ext = $request->file('avatar')->getClientOriginalExtension();
            $path = $request->file('avatar')->storeAs(
                'avatars',
                $user->id . '_' . time() . '.' . $ext,
                's3'
            );
            $data['avatar_path'] = $path;
        }

        $user->update($data);

        return back()->with('success', 'Usuário atualizado com sucesso.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Você não pode remover sua própria conta.');
        }

        if (in_array($user->email, ['aurora@pmbok.sys', 'prisma@pmbok.sys'])) {
            return back()->with('error', 'Este usuário é protegido e não pode ser removido.');
        }

        $user->delete();

        return back()->with('success', 'Usuário desativado com sucesso.');
    }

    public function restore(int $id): RedirectResponse
    {
        $user = User::onlyTrashed()->findOrFail($id);
        $user->restore();

        return back()->with('success', 'Usuário restaurado com sucesso.');
    }
}
