<?php

namespace App\Http\Controllers;

use App\Models\Status;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PublicRegisterController extends Controller
{
    public function show()
    {
        return view('auth.public-register');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nick' => ['required', 'string', 'max:255', 'unique:users,nick'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $statusId = Status::where('name', 'USUARIO')->value('id');

        $user = User::create([
            'nick' => $validated['nick'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'status_id' => Status::where('name', 'USUARIO')->value('id'),
        ]);

        $user->assignRole('user');
        Auth::login($user);

        return redirect('/')->with('success', 'Usuario registrado correctamente.');
    }
}