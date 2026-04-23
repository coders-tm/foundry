<?php

namespace Workbench\App\Http\Controllers\Auth;

use Foundry\Enum\AppStatus;
use Foundry\Events\UserSubscribed;
use Foundry\Foundry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Workbench\App\Http\Controllers\Controller;

class AuthController extends Controller
{
    public function login(Request $request, string $guard = 'users'): JsonResponse
    {
        $tables = ['user' => 'users', 'admin' => 'admins'];
        $table = $tables[$guard] ?? $guard;

        $request->validate(
            [
                'email' => "required|email|exists:{$table},email",
                'password' => 'required',
            ],
            [
                'email.required' => __('An email address is required.'),
                'email.exists' => __('Your email address doesn\'t exist.'),
            ]
        );

        if (! Auth::guard($guard)->attempt($request->only(['email', 'password']), $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'password' => [__('Your password doesn\'t match with our records.')],
            ]);
        }

        $user = $request->user($guard);

        if (! $user->is_active) {
            Auth::guard($guard)->logout();
            throw ValidationException::withMessages([
                'email' => [__('Your account has been disabled. Please contact the administrator.')],
            ]);
        }

        return response()->json($user->toLoginResponse(), 200);
    }

    public function signup(Request $request, string $guard = 'users'): JsonResponse
    {
        $this->validate($request, [
            'email' => 'required|email|unique:users',
            'first_name' => 'required',
            'last_name' => 'required',
            'phone_number' => 'required',
            'line1' => 'required',
            'city' => 'required',
            'postal_code' => 'required',
            'country' => 'required',
            'password' => 'required|min:6|confirmed',
        ]);

        $request->merge([
            'password' => Hash::make($request->password),
            'status' => AppStatus::PENDING->value,
        ]);

        $user = Foundry::$userModel::create($request->only([
            'email',
            'first_name',
            'last_name',
            'company_name',
            'phone_number',
            'password',
            'status',
        ]));

        $user->updateOrCreateAddress($request->input());

        event(new UserSubscribed($user));

        Auth::guard($guard)->login($user);

        return response()->json($user->toLoginResponse(), 200);
    }

    public function logout(Request $request, string $guard = 'users'): JsonResponse
    {
        Auth::guard($guard)->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => __('You have been successfully logged out!'),
        ], 200);
    }

    public function me(string $guard = 'users'): JsonResponse
    {
        $user = request()->user($guard);

        $user->loadMissing(['address']);

        return response()->json($user->toLoginResponse(), 200);
    }

    public function update(Request $request, string $guard = 'users'): JsonResponse
    {
        $user = user();

        $this->validate($request, [
            'first_name' => 'required',
            'last_name' => 'required',
            'address.line1' => 'required',
            'address.city' => 'required',
            'address.postal_code' => 'required',
            'address.country' => 'required',
            'email' => "email|unique:{$guard},email,{$user->id}",
        ]);

        $user->update($request->only([
            'first_name',
            'last_name',
            'email',
            'phone_number',
        ]));

        $user->updateOrCreateAddress($request->input('address'));

        if ($request->filled('avatar')) {
            $user->avatar()->sync([
                $request->input('avatar.id') => ['type' => 'avatar'],
            ]);
        }

        return $this->me($guard);
    }

    public function password(Request $request): JsonResponse
    {
        $this->validate($request, [
            'old_password' => 'required',
            'password' => 'min:6|confirmed',
        ]);

        $user = user();

        if (! Hash::check($request->old_password, $user->password)) {
            throw ValidationException::withMessages([
                'old_password' => [__('Old password doesn\'t match!')],
            ]);
        }

        $user->update([
            'password' => bcrypt($request->password),
        ]);

        return response()->json([
            'message' => __('Password has been changed successfully!'),
        ], 200);
    }

    public function requestAccountDeletion(Request $request, string $guard = 'users'): JsonResponse
    {
        user()->logs()->create([
            'type' => 'request-account-deletion',
            'message' => __('User requested deletion of their account.'),
        ]);

        return $this->me($guard);
    }

    public function addDeviceToken(Request $request): JsonResponse
    {
        $this->validate($request, [
            'device_token' => 'required|string',
        ]);

        try {
            user()->addDeviceToken($request->device_token);
        } catch (\Throwable) {
            // Device token registration is non-critical
        }

        return response()->json([
            'message' => __('Device token added successfully.'),
        ], 200);
    }
}
