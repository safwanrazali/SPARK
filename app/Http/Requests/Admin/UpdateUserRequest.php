<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('access-administration');
    }

    public function rules(): array
    {
        $userId = $this->route('user')->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($userId)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            // Senarai peranan diambil daripada model supaya peranan baharu
            // (Fasa 9) tidak perlu didaftarkan di banyak tempat.
            // Sekurang-kurangnya satu peranan; setiap satu mesti peranan sah.
            'roles' => ['required', 'array'],
            'roles.*' => ['distinct', 'in:'.implode(',', User::roles())],
            'password' => [
                'nullable',
                'confirmed',
                Password::min(12)->mixedCase()->numbers()->symbols(),
            ],
        ];
    }

    /**
     * Hanya Pentadbir Sistem boleh menambah pengguna, jadi membuang peranan
     * itu daripada pentadbir yang terakhir akan mengunci sistem selama-lamanya.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $sasaran = $this->route('user');

            if (! $sasaran->hasRole(User::ROLE_ADMINISTRATOR)) {
                return;
            }

            if (in_array(User::ROLE_ADMINISTRATOR, (array) $this->input('roles', []), true)) {
                return;
            }

            $adaPentadbirLain = User::query()
                ->administrators()
                ->whereKeyNot($sasaran->getKey())
                ->exists();

            if ($adaPentadbirLain) {
                return;
            }

            $validator->errors()->add(
                'roles',
                'Sistem mesti sentiasa mempunyai sekurang-kurangnya satu Pentadbir Sistem. '
                .'Lantik pentadbir lain sebelum membuang peranan ini.',
            );
        });
    }

    public function messages(): array
    {
        return [
            'password.confirmed' => 'Pengesahan kata laluan tidak sepadan.',
            'roles.required' => 'Sila pilih sekurang-kurangnya satu peranan.',
            'roles.array' => 'Sila pilih sekurang-kurangnya satu peranan.',
            'roles.*.in' => 'Peranan yang dipilih tidak sah.',
        ];
    }
}
