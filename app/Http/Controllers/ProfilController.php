<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfilRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Profil sendiri — setiap pengguna yang telah log masuk boleh mengemas kini
 * nama, nama pengguna, emel dan kata laluannya.
 *
 * Peranan tidak diurus di sini; ia kekal milik modul Pentadbiran supaya
 * pengguna tidak boleh menaikkan kebenaran sendiri.
 */
class ProfilController extends Controller
{
    public function edit(Request $request)
    {
        return view('profil.edit', ['user' => $request->user()]);
    }

    public function update(UpdateProfilRequest $request)
    {
        $user = $request->user();

        $data = $request->safe()->except(['password', 'password_confirmation']);

        // Medan kata laluan yang dibiarkan kosong bermaksud "jangan tukar".
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->validated('password'));
            // Kata laluan kini pilihan sendiri, bukan lagi yang sementara.
            $data['must_change_password'] = false;
        }

        $user->update($data);

        return redirect()
            ->route('profil.edit')
            ->with('success', 'Profil anda telah dikemaskini.');
    }
}
