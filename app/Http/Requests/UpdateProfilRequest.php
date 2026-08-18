<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Kemas kini profil sendiri.
 *
 * Peraturan sengaja mencerminkan Admin\UpdateUserRequest supaya syarat kata
 * laluan tidak terpesong antara dua laluan kemas kini yang sama.
 *
 * `role` TIADA di sini dengan sengaja: pengguna mengemas kini akaunnya
 * sendiri, jadi peranan tidak boleh datang daripada borang. Pengawal hanya
 * menyimpan kunci yang lulus pengesahan, maka medan `role` yang diselitkan
 * ke dalam permintaan tidak akan sampai ke pangkalan data.
 */
class UpdateProfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($userId)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            // Kosong bermaksud kekalkan kata laluan sedia ada.
            'password' => [
                'nullable',
                'confirmed',
                Password::min(12)->mixedCase()->numbers()->symbols(),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'password.confirmed' => 'Pengesahan kata laluan tidak sepadan.',
        ];
    }
}
