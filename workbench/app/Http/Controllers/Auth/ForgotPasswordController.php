<?php

namespace Workbench\App\Http\Controllers\Auth;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Workbench\App\Http\Controllers\Controller;

class ForgotPasswordController extends Controller
{
    public function request(Request $request, $guard = null)
    {
        $request->validate([
            'email' => "required|email|exists:{$guard},email",
        ]);

        $status = Password::broker($guard)->sendResetLink($request->only('email'));
        if ($status === Password::INVALID_USER) {
            return response()->json([
                'message' => __('User not found!'),
            ], 404);
        } elseif ($status == Password::RESET_THROTTLED) {
            return response()->json([
                'message' => __('Reset password email already sent. Please try again after sometime!'),
            ], 429);
        }

        return response()->json([
            'status' => $status,
            'message' => __('Password reset link sent successfully!'),
        ], 200);
    }

    public function reset(Request $request, $guard = null)
    {
        $request->validate(
            [
                'password' => 'required|min:6|same:password_confirmation',
                'password_confirmation' => 'required',
            ],
            [
                'password_confirmation.required' => __('Password confirmation is required.'),
            ]
        );

        $status = Password::broker($guard)->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                $user->setRememberToken(Str::random(60));

                event(new PasswordReset($user));
            }
        );

        if ($status != Password::PASSWORD_RESET) {
            return response()->json([
                'message' => __('Invalid token or token may expired!'),
            ], 400);
        }

        return response()->json([
            'message' => __('Password reset successfully!'),
        ], 200);
    }
}
