<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Actions\ClaimGuestAccountAction;
use App\Modules\Identity\Actions\CreateGuestCustomerAction;
use App\Modules\Identity\Actions\LoginAction;
use App\Modules\Identity\Actions\RegisterCustomerAction;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Exceptions\AccountClaimNotAllowed;
use App\Modules\Identity\Http\Requests\ChangePasswordRequest;
use App\Modules\Identity\Http\Requests\ClaimAccountRequest;
use App\Modules\Identity\Http\Requests\ForgotPasswordRequest;
use App\Modules\Identity\Http\Requests\GuestRequest;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Http\Requests\RegisterRequest;
use App\Modules\Identity\Http\Requests\ResetPasswordRequest;
use App\Modules\Identity\Http\Requests\UpdateProfileRequest;
use App\Modules\Identity\Http\Resources\UserResource;
use App\Modules\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class AuthController
{
    #[OA\Post(
        path: '/api/v1/auth/register',
        summary: 'Inscription client',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name', 'password'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'Awa Koné'),
                new OA\Property(property: 'email', type: 'string', example: 'awa@example.com'),
                new OA\Property(property: 'phone', type: 'string', example: '+33612345678'),
                new OA\Property(property: 'password', type: 'string', example: 'secret1234'),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Compte créé', content: new OA\JsonContent(ref: '#/components/schemas/AuthResult')),
            new OA\Response(response: 422, description: 'Validation'),
        ],
    )]
    public function register(RegisterRequest $request, RegisterCustomerAction $action): JsonResponse
    {
        $user = $action->execute($request->validated());

        return response()->json([
            'user' => new UserResource($user),
            'token' => $user->createToken('api')->plainTextToken,
        ], 201);
    }

    #[OA\Post(
        path: '/api/v1/auth/guest',
        summary: 'Commander sans compte : ouvre une session invité',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name', 'email', 'phone'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'Awa Koné'),
                new OA\Property(property: 'email', type: 'string', example: 'awa@example.com'),
                new OA\Property(property: 'phone', type: 'string', example: '+33612345678'),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Session invité', content: new OA\JsonContent(ref: '#/components/schemas/AuthResult')),
            new OA\Response(response: 422, description: 'Validation'),
        ],
    )]
    public function guest(GuestRequest $request, CreateGuestCustomerAction $action): JsonResponse
    {
        $user = $action->execute($request->validated());

        return response()->json([
            'user' => new UserResource($user),
            'token' => $user->createToken('guest')->plainTextToken,
        ], 201);
    }

    #[OA\Post(
        path: '/api/v1/auth/claim-account',
        summary: 'Transformer une session invité en compte (e-mail de la commande + mot de passe choisi)',
        security: [['sanctum' => []]],
        tags: ['Auth'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['password'],
            properties: [new OA\Property(property: 'password', type: 'string', example: 'secret1234')],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Compte créé', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/User')])),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 422, description: 'Mot de passe invalide, déjà un compte, ou e-mail déjà utilisé'),
        ],
    )]
    public function claimAccount(ClaimAccountRequest $request, ClaimGuestAccountAction $action): UserResource
    {
        try {
            return new UserResource($action->execute($request->user(), $request->string('password')->toString()));
        } catch (AccountClaimNotAllowed $e) {
            throw ValidationException::withMessages(['account' => [$e->getMessage()]]);
        }
    }

    #[OA\Post(
        path: '/api/v1/auth/login',
        summary: 'Connexion par e-mail ou téléphone',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['login', 'password'],
            properties: [
                new OA\Property(property: 'login', type: 'string'),
                new OA\Property(property: 'password', type: 'string'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Jeton Sanctum', content: new OA\JsonContent(ref: '#/components/schemas/AuthResult')),
            new OA\Response(response: 422, description: 'Identifiants incorrects'),
        ],
    )]
    public function login(LoginRequest $request, LoginAction $action): JsonResponse
    {
        $result = $action->execute($request->string('login')->toString(), $request->string('password')->toString());

        return response()->json(['user' => new UserResource($result['user']), 'token' => $result['token']]);
    }

    #[OA\Post(
        path: '/api/v1/auth/forgot-password',
        summary: 'Envoie un lien de réinitialisation du mot de passe (compte du personnel). Réponse identique que le compte existe ou non.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['email'],
            properties: [new OA\Property(property: 'email', type: 'string', example: 'agent@example.com')],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Demande enregistrée'),
            new OA\Response(response: 422, description: 'Validation'),
        ],
    )]
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $email = $request->string('email')->toString();
        $user = User::where('email', $email)->first();

        // Rien n'est envoyé pour un compte inconnu, client ou invité (le backoffice leur est fermé),
        // et rien dans la réponse ne permet de le savoir.
        if ($user !== null && ! $user->is_guest && $user->role !== Role::Customer) {
            Password::broker()->sendResetLink(['email' => $email]);
        }

        return response()->json([
            'message' => "Si un compte correspond à cette adresse, un e-mail de réinitialisation vient d'être envoyé.",
        ]);
    }

    #[OA\Post(
        path: '/api/v1/auth/reset-password',
        summary: 'Choisit un nouveau mot de passe avec le jeton reçu par e-mail (usage unique, durée limitée)',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['token', 'email', 'password', 'password_confirmation'],
            properties: [
                new OA\Property(property: 'token', type: 'string'),
                new OA\Property(property: 'email', type: 'string'),
                new OA\Property(property: 'password', type: 'string'),
                new OA\Property(property: 'password_confirmation', type: 'string'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Mot de passe changé'),
            new OA\Response(response: 422, description: 'Lien invalide ou expiré, ou mot de passe refusé'),
        ],
    )]
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                // Toute session ouverte avec l'ancien mot de passe est fermée.
                $user->tokens()->delete();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => 'Ce lien de réinitialisation est invalide ou a expiré. Demandez-en un nouveau.'], 422);
        }

        return response()->json(['message' => 'Votre mot de passe a été changé. Vous pouvez vous connecter.']);
    }

    #[OA\Get(
        path: '/api/v1/auth/me',
        summary: 'Utilisateur courant',
        security: [['sanctum' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Utilisateur', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/User')])),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ],
    )]
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    #[OA\Put(
        path: '/api/v1/auth/me',
        summary: 'Modifier mon profil (nom, téléphone)',
        security: [['sanctum' => []]],
        tags: ['Auth'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'Awa Koné'),
                new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+33612345678'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Profil mis à jour', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/User')])),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 422, description: 'Validation'),
        ],
    )]
    public function updateProfile(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $user->update([
            'name' => $request->string('name')->trim()->toString(),
            'phone' => $request->filled('phone') ? $request->string('phone')->trim()->toString() : null,
        ]);

        return new UserResource($user->refresh());
    }

    #[OA\Post(
        path: '/api/v1/auth/change-password',
        summary: 'Changer mon mot de passe (les autres sessions sont fermées)',
        security: [['sanctum' => []]],
        tags: ['Auth'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['current_password', 'password', 'password_confirmation'],
            properties: [
                new OA\Property(property: 'current_password', type: 'string'),
                new OA\Property(property: 'password', type: 'string'),
                new OA\Property(property: 'password_confirmation', type: 'string'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Mot de passe changé'),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 422, description: 'Mot de passe actuel incorrect ou nouveau mot de passe refusé'),
        ],
    )]
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill(['password' => $request->string('password')->toString(), 'remember_token' => Str::random(60)])->save();
        // Les autres appareils doivent se reconnecter ; la session courante continue.
        $current = $user->currentAccessToken();
        $user->tokens()->when($current !== null, fn ($query) => $query->where('id', '!=', $current->id))->delete();

        return response()->json(['message' => 'Votre mot de passe a été changé.']);
    }

    #[OA\Post(
        path: '/api/v1/auth/logout',
        summary: 'Déconnexion',
        security: [['sanctum' => []]],
        tags: ['Auth'],
        responses: [new OA\Response(response: 204, description: 'Jeton révoqué')],
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }
}
