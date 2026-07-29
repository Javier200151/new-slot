<?php

namespace App\Http\Controllers;

use App\Models\Status;
use App\Models\User;
use App\Rules\NotReservedUsername;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Events\Registered;

class PublicRegisterController extends Controller
{
    public function show()
    {
        return view('auth.public-register');
    }

    public function store(Request $request)
    {
        $validated = $request->validate(
            [
                'nick' => [
                    'required',
                    'string',
                    'min:3',
                    'max:30',
                    'regex:/^[A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)*$/',
                    new NotReservedUsername(),
                    'unique:users,nick',
                ],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'confirmed', 'min:8'],
            ],
            [
                'nick.required' => 'El nick es obligatorio.',
                'nick.min' => 'El nick debe tener al menos 3 caracteres.',
                'nick.max' => 'El nick no puede tener más de 30 caracteres.',
                'nick.regex' => 'El nick solo puede contener letras sin tildes, números, guiones (-), guiones bajos (_) y puntos (.). No puede comenzar ni terminar con un punto, ni contener puntos consecutivos.',
                'nick.unique' => 'Este nick ya está en uso.',
            ],
        );

        $statusId = Status::where('name', 'USUARIO')->value('id');

        $user = User::create([
            'nick' => $validated['nick'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'status_id' => Status::where('name', 'USUARIO')->value('id'),
        ]);

        $user->assignRole('user');
        event(new Registered($user));
        Auth::login($user);

        return redirect('/')->with('success', 'Usuario registrado correctamente.');
    }
}
