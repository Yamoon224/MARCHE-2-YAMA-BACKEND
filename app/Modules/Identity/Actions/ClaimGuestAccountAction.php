<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Exceptions\AccountClaimNotAllowed;
use App\Modules\Identity\Models\User;

final class ClaimGuestAccountAction
{
    /**
     * Transforme un client invité en vrai compte : ses coordonnées de contact deviennent identifiant de connexion et il
     * choisit son mot de passe. Ses commandes lui restent rattachées (même identifiant interne).
     *
     * @throws AccountClaimNotAllowed
     */
    public function execute(User $user, string $password): User
    {
        if (! $user->is_guest) {
            throw AccountClaimNotAllowed::notAGuest();
        }

        $email = $user->contact_email;

        if ($email === null || User::where('email', $email)->where('id', '!=', $user->id)->exists()) {
            throw AccountClaimNotAllowed::emailTaken();
        }

        $phone = $user->contact_phone;
        $phoneFree = $phone !== null && ! User::where('phone', $phone)->where('id', '!=', $user->id)->exists();

        $user->forceFill([
            'email' => $email,
            // Un téléphone déjà pris par un autre compte n'empêche pas la création : seul l'e-mail identifie.
            'phone' => $phoneFree ? $phone : null,
            'password' => $password,
            'is_guest' => false,
        ])->save();

        return $user;
    }
}
