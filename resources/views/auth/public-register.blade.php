<h1>Registro NEW SLOT</h1>

<form method="POST" action="{{ route('public.register.store') }}">
    @csrf

    <label>Nick</label>
    <input type="text" name="nick" value="{{ old('nick') }}">
    @error('nick') <p>{{ $message }}</p> @enderror

    <br>

    <label>Email</label>
    <input type="email" name="email" value="{{ old('email') }}">
    @error('email') <p>{{ $message }}</p> @enderror

    <br>

    <label>Contraseña</label>
    <input type="password" name="password">
    @error('password') <p>{{ $message }}</p> @enderror

    <br>

    <label>Confirmar contraseña</label>
    <input type="password" name="password_confirmation">

    <br><br>

    <button type="submit">Registrarse</button>
</form>

<a href="/">Volver</a>