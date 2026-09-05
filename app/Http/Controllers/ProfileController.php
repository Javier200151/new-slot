<?php

namespace App\Http\Controllers;

use App\Rules\NotReservedUsername;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user()->load([
            'promo',
            'status',
            'tutor',
            'sqaGroups',
            'mainSqaGroup',
        ]);

        return view('profile.show', compact('user'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $steamId = trim((string) $request->input('steam_id', ''));

        $request->merge([
            'steam_id' => $steamId !== '' ? $steamId : null,
        ]);

        $validated = $request->validateWithBag(
            'profileUpdate',
            [
                'nick' => [
                    'required',
                    'string',
                    'min:3',
                    'max:30',
                    'regex:/^[A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)*$/',

                    Rule::when(
                        $request->input('nick') !== $user->nick,
                        [
                            new NotReservedUsername(),
                        ],
                    ),

                    Rule::unique('users', 'nick')->ignore($user),
                ],

                'email' => [
                    'required',
                    'email',
                    'max:255',
                    Rule::unique('users', 'email')->ignore($user),
                ],

                'quote' => [
                    'nullable',
                    'string',
                    'max:500',
                ],

                'discord_id' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'steam_id' => [
                    'nullable',
                    'string',
                    'max:255',
                    Rule::unique('users', 'steam_id')->ignore($user),
                ],

                'birth_at' => [
                    'nullable',
                    'date',
                    'before_or_equal:today',
                ],

                'image' => [
                    'nullable',
                    File::image()
                        ->max('2mb')
                        ->dimensions(
                            Rule::dimensions()
                                ->maxWidth(1600)
                                ->maxHeight(1600)
                        ),
                ],
            ],
            [
                'nick.required' => 'El nick es obligatorio.',
                'nick.min' => 'El nick debe tener al menos 3 caracteres.',
                'nick.max' => 'El nick no puede tener más de 30 caracteres.',
                'nick.regex' => 'El nick solo puede contener letras sin tildes, números, guiones, guiones bajos y puntos.',
                'nick.unique' => 'Este nick ya está en uso.',

                'email.required' => 'El correo electrónico es obligatorio.',
                'email.email' => 'Introduce un correo electrónico válido.',
                'email.unique' => 'Este correo electrónico ya está en uso.',

                'steam_id.unique' => 'Este Steam ID ya está asignado a otro usuario.',

                'quote.max' => 'La frase no puede superar los 500 caracteres.',
                'birth_at.before_or_equal' => 'La fecha de nacimiento no puede ser futura.',

                'image.max' => 'La imagen no puede superar los 2 MB.',
                'image.image' => 'El archivo debe ser una imagen válida.',
                'image.dimensions' => 'La imagen no puede superar 1600 × 1600 píxeles.',
            ],
        );

        $newEmail = Str::lower(trim($validated['email']));

        $emailChanged = Str::lower((string) $user->email) !== $newEmail;

        $oldImage = $user->image;
        $newImage = null;

        if ($request->hasFile('image')) {
            $newImage = $request
                ->file('image')
                ->store(
                    "profile-images/{$user->id}",
                    'public',
                );
        }

        try {
            $user->fill([
                'nick' => $validated['nick'],
                'email' => $newEmail,
                'quote' => $validated['quote'] ?? null,
                'discord_id' => $validated['discord_id'] ?? null,
                'steam_id' => $validated['steam_id'] ?? null,
                'birth_at' => $validated['birth_at'] ?? null,
            ]);

            if ($newImage !== null) {
                $user->image = $newImage;
            }

            if ($emailChanged) {
                $user->email_verified_at = null;
            }

            $user->save();
        } catch (Throwable $exception) {
            if ($newImage !== null) {
                Storage::disk('public')->delete($newImage);
            }

            throw $exception;
        }

        if (
            $newImage !== null
            && filled($oldImage)
            && $oldImage !== $newImage
        ) {
            Storage::disk('public')->delete($oldImage);
        }

        if ($emailChanged) {
            try {
                $user->sendEmailVerificationNotification();
            } catch (Throwable $exception) {
                report($exception);

                return redirect()
                    ->route('profile.show')
                    ->with(
                        'warning',
                        'Los datos se guardaron, pero no se pudo enviar el correo de verificación. Revisa la configuración de correo o utiliza el botón para reenviarlo.',
                    );
            }
        }

        return redirect()
            ->route('profile.show')
            ->with(
                'status',
                $emailChanged
                    ? 'profile-updated-email-changed'
                    : 'profile-updated',
            );
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag(
            'passwordUpdate',
            [
                'current_password' => [
                    'required',
                    'current_password',
                ],

                'password' => [
                    'required',
                    'confirmed',
                    Password::min(8),
                ],
            ],
            [
                'current_password.required' => 'Introduce tu contraseña actual.',
                'current_password.current_password' => 'La contraseña actual no es correcta.',

                'password.required' => 'Introduce la nueva contraseña.',
                'password.confirmed' => 'La confirmación de la contraseña no coincide.',
                'password.min' => 'La nueva contraseña debe tener al menos 8 caracteres.',
            ],
        );

        $user = $request->user();

        $user->update([
            'password' => $validated['password'],
        ]);

        app(\App\Services\AuditLogger::class)
            ->security(
                event: 'password_changed',
                subject: $user,
                properties: [
                    'method' => 'profile',
                ],
                causer: $user,
            );

        $request->session()->regenerate();

        return redirect()
            ->route('profile.show')
            ->with('status', 'password-updated');
    }

    public function deleteImage(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (filled($user->image)) {
            Storage::disk('public')->delete($user->image);

            $user->forceFill([
                'image' => null,
            ])->save();
        }

        return redirect()
            ->route('profile.show')
            ->with('status', 'image-deleted');
    }

    public function sendVerification(
        Request $request,
    ): RedirectResponse {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()
                ->route('profile.show')
                ->with('status', 'email-already-verified');
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('profile.show')
                ->with(
                    'warning',
                    'No se pudo enviar el correo de verificación. Revisa la configuración de correo de Laravel.',
                );
        }

        return redirect()
            ->route('profile.show')
            ->with('status', 'verification-link-sent');
    }
}