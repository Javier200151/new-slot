<h1>NEW SLOT</h1>

@if(session('success'))
    <p>{{ session('success') }}</p>
@endif

@if(auth()->check())

    <p>Conectado como <strong>{{ auth()->user()->nick }}</strong></p>

    @if(auth()->user()->hasRole('admin'))
        <p>
            <a href="/admin/login">Panel de administración</a>
        </p>
    @endif

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Cerrar sesión</button>
    </form>

@else

    <p>
        <a href="/register">Crear cuenta</a>
    </p>

    <p>
        <a href="/login">Iniciar sesión</a>
    </p>

@endif