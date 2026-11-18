<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Création (POST) et modification (PUT) d'un membre du personnel. */
class StaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        /** @var User|null $member */
        $member = $this->route('user');

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:120'],
            'email' => [$creating ? 'required' : 'sometimes', 'email', 'max:190', Rule::unique('users', 'email')->ignore($member?->id)],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9]{8,15}$/', Rule::unique('users', 'phone')->ignore($member?->id)],
            'role' => [$creating ? 'required' : 'sometimes', Rule::enum(Role::class)->only(Role::staffRoles())],
            'password' => [$creating ? 'required' : 'sometimes', 'string', 'min:8'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'role.enum' => 'Ce rôle ne peut pas être attribué au personnel.',
            'email.unique' => 'Cet e-mail est déjà utilisé.',
            'phone.unique' => 'Ce téléphone est déjà utilisé.',
        ];
    }
}
