<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function sendLink(
        Request $request
    ): RedirectResponse {
        $request->validateWithBag(
            'forgotPassword',
            [
                'email' => [
                    'required',
                    'email',
                    'max:255',
                ],
            ],
            [
                'email.required' =>
                    'Introduce tu correo electrónico.',

                'email.email' =>
                    'Introduce una dirección de correo válida.',
            ]
        );

        /*
         * No revelamos si existe o no una cuenta
         * asociada al correo introducido.
         */
        Password::sendResetLink([
            'email' => $request->input('email'),
        ]);

        return redirect()
            ->route('home', [
                'modal' => 'forgot-password',
            ])
            ->with(
                'status',
                'password-reset-link-sent'
            );
    }

    public function show(
        Request $request,
        string $token
    ): View {
        return view(
            'auth.reset-password',
            [
                'token' => $token,
                'email' => $request->query('email'),
            ]
        );
    }

    public function reset(
        Request $request
    ): RedirectResponse {
        $validated = $request->validate([
            'token' => [
                'required',
            ],

            'email' => [
                'required',
                'email',
            ],

            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(8),
            ],
        ], [
            'email.required' =>
                'El correo electrónico es obligatorio.',

            'email.email' =>
                'Introduce una dirección de correo válida.',

            'password.required' =>
                'Introduce una nueva contraseña.',

            'password.confirmed' =>
                'Las contraseñas no coinciden.',

            'password.min' =>
                'La contraseña debe tener al menos 8 caracteres.',
        ]);

        $status = Password::reset(
            [
                'email' => $validated['email'],
                'password' => $validated['password'],
                'password_confirmation' =>
                    $request->input(
                        'password_confirmation'
                    ),
                'token' => $validated['token'],
            ],
            function (
                User $user,
                string $password
            ): void {
                /*
                 * User ya tiene:
                 *
                 * 'password' => 'hashed'
                 *
                 * Por eso NO usamos Hash::make().
                 */
                $user->forceFill([
                    'password' => $password,
                    'remember_token' =>
                        Str::random(60),
                ])->save();

                event(
                    new PasswordReset($user)
                );
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()
                ->route('home', [
                    'modal' => 'login',
                ])
                ->with(
                    'status',
                    'password-reset'
                );
        }

        throw ValidationException::withMessages([
            'email' => [
                $status === Password::INVALID_TOKEN
                    ? 'El enlace de recuperación no es válido o ha caducado.'
                    : 'No se ha podido restablecer la contraseña.',
            ],
        ]);
    }
}