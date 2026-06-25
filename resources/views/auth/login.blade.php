<h1>Iniciar sesión</h1>

@if ($errors->any())
    <p style="color:red">{{ $errors->first() }}</p>
@endif

<form method="POST" action="{{ route('public.login') }}">
    @csrf

    <p>
        <label>Email</label><br>
        <input type="email" name="email" value="{{ old('email') }}" required>
    </p>

    <p>
        <label>Contraseña</label><br>
        <input type="password" name="password" required>
    </p>

    <p>
        <label>
            <input type="checkbox" name="remember">
            Recordarme
        </label>
    </p>

    <button type="submit">Iniciar sesión</button>
</form>

<p>
    <a href="/register">Crear cuenta</a>
</p>

<p>
    <a href="/">Volver</a>
</p>