<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('name')->paginate(15);

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        return view('admin.users.create');
    }

    public function store(StoreUserRequest $request)
    {
        User::create([
            ...$request->safe()->except('password_confirmation'),
            'password' => Hash::make($request->validated('password')),
            'email_verified_at' => now(),
        ]);

        return redirect()
            ->route('administration.users.index')
            ->with('success', 'Pengguna baharu berjaya ditambah.');
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->safe()->except(['password', 'password_confirmation']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->validated('password'));
        }

        $user->update($data);

        return redirect()
            ->route('administration.users.index')
            ->with('success', 'Maklumat pengguna telah dikemaskini.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors([
                'user' => 'Anda tidak boleh memadam akaun anda sendiri.',
            ]);
        }

        // Tanpa seorang pun Pentadbir Sistem, tiada siapa boleh menambah
        // pengguna lagi dan sistem terkunci.
        $pentadbirTerakhir = $user->hasRole(User::ROLE_ADMINISTRATOR)
            && ! User::query()->administrators()->whereKeyNot($user->getKey())->exists();

        if ($pentadbirTerakhir) {
            return back()->withErrors([
                'user' => 'Akaun ini ialah Pentadbir Sistem yang terakhir dan tidak boleh dipadam.',
            ]);
        }

        $user->delete();

        return redirect()
            ->route('administration.users.index')
            ->with('success', 'Pengguna telah dipadamkan.');
    }
}
