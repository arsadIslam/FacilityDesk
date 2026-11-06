<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Resources\TenantResource;
use App\Http\Resources\UserResource;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ScribeResponse;
use Knuckles\Scribe\Attributes\Unauthenticated;

#[Group('Authentication', 'Tokens are per device. Send them as `Authorization: Bearer <token>`.')]
final class AuthController extends Controller
{
    /**
     * Log in
     *
     * Exchanges workspace, email and password for a bearer token. Limited to
     * 5 attempts a minute per IP. Wrong workspace, email or password all give
     * the same answer, so the endpoint cannot be used to discover accounts.
     */
    #[Unauthenticated]
    #[ScribeResponse(['token' => '1|aBcD…', 'token_type' => 'Bearer', 'expires_at' => '2026-06-03T10:00:00+00:00', 'user' => ['id' => 7, 'name' => 'Tom Tech', 'email' => 'tom@acme.test', 'phone' => null, 'role' => 'technician', 'client_id' => null]], 200)]
    #[ScribeResponse(['message' => 'These credentials do not match our records.', 'errors' => ['email' => ['These credentials do not match our records.']]], 422)]
    public function login(LoginRequest $request, TenantContext $context): JsonResponse
    {
        $tenant = Tenant::query()->where('slug', strtolower($request->string('tenant')->toString()))->first();
        $user = $tenant === null ? null : $context->run($tenant, fn () => User::query()
            ->where('email', strtolower($request->string('email')->toString()))
            ->first());

        if ($user === null || ! $user->is_active || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        // Only revealed to someone who already proved they own the account.
        abort_unless($tenant->isActive(), 403, 'This workspace has been suspended. Please contact support.');
        abort_unless($user->hasVerifiedEmail(), 403, 'Verify your email address before using the API.');

        $expiresAt = now()->addMinutes((int) config('sanctum.expiration'));
        $token = $user->createToken($request->string('device_name')->toString(), ['*'], $expiresAt);
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        $context->set($tenant);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toIso8601String(),
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Log out
     *
     * Revokes the token used for this request (other devices stay signed in).
     */
    #[Authenticated]
    #[ScribeResponse(status: 204)]
    public function logout(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $user->currentAccessToken()->delete();

        return response()->noContent();
    }

    /**
     * Current user
     *
     * The signed-in user, their workspace and permissions.
     */
    #[Authenticated]
    #[ScribeResponse(['data' => ['id' => 7, 'name' => 'Tom Tech', 'email' => 'tom@acme.test', 'phone' => null, 'role' => 'technician', 'client_id' => null, 'tenant' => ['id' => 1, 'name' => 'Acme Facilities', 'slug' => 'acme', 'timezone' => 'Asia/Kolkata'], 'permissions' => ['work_orders.work', 'work_orders.internal_comments']]])]
    public function me(Request $request, TenantContext $context): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => [
            ...(new UserResource($user))->resolve($request),
            'tenant' => new TenantResource($context->tenant()),
            'permissions' => $user->getAllPermissions()->pluck('name')->sort()->values(),
        ]]);
    }
}
