<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Actions\CreateGuestCustomerAction;
use App\Modules\Identity\Actions\LoginAction;
use App\Modules\Identity\Actions\RegisterCustomerAction;
use App\Modules\Identity\Http\Requests\GuestRequest;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Http\Requests\RegisterRequest;
use App\Modules\Identity\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
