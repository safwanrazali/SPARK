<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Services\TetapSemulaKataLaluan;
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
            // Kata laluan ini diketahui pentadbir, jadi pemiliknya wajib
            // menggantikannya pada log masuk pertama.
            'must_change_password' => true,
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

        // Tetapan semula oleh pentadbir menghasilkan kata laluan sementara juga.
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->validated('password'));
            $data['must_change_password'] = true;
        }

        $user->update($data);

        return redirect()
            ->route('administration.users.index')
            ->with('success', 'Maklumat pengguna telah dikemaskini.');
    }

    /**
     * Tetapkan semula kata laluan pengguna atas permintaan mereka.
     *
     * Kata laluan sementara dipaparkan sekali sahaja kepada pentadbir untuk
     * disampaikan kepada pemilik akaun; ia tidak disimpan dalam bentuk yang
     * boleh dibaca semula.
     */
    public function tetapSemulaKataLaluan(User $user, TetapSemulaKataLaluan $tetapSemula)
    {
        // Pentadbir menukar kata laluannya sendiri melalui Profil Saya;
        // menetapkan semula di sini hanya akan mengunci dirinya ke skrin
        // tukar kata laluan tanpa sebab.
        if ($user->id === auth()->id()) {
            return back()->withErrors([
                'user' => 'Gunakan Profil Saya untuk menukar kata laluan anda sendiri.',
            ]);
        }

        $sementara = $tetapSemula->jalankan($user);

        return back()->with('kata_laluan_sementara', [
            'username' => $user->username,
            'kata_laluan' => $sementara,
        ]);
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
