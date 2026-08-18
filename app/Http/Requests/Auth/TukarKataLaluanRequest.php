<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Tukar kata laluan sementara pada log masuk pertama.
 *
 * Syarat kekuatan sama seperti borang pentadbiran dan profil supaya kata
 * laluan pilihan sendiri tidak boleh lebih lemah daripada yang dikeluarkan.
 */
class TukarKataLaluanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'password' => [
                'required',
                'confirmed',
                Password::min(12)->mixedCase()->numbers()->symbols(),
            ],
        ];
    }

    /**
     * Mengekalkan kata laluan sementara menewaskan tujuan skrin ini, jadi
     * kata laluan baharu mesti benar-benar berbeza daripada yang sedia ada.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $baharu = $this->input('password');

            if (! is_string($baharu) || $baharu === '') {
                return;
            }

            if (Hash::check($baharu, (string) $this->user()->password)) {
                $validator->errors()->add(
                    'password',
                    'Kata laluan baharu mesti berbeza daripada kata laluan sementara.',
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'password.confirmed' => 'Pengesahan kata laluan tidak sepadan.',
        ];
    }
}
