@props([
    'user' => null,
])

@if($user && ! $user->trashed())
    <a
        href="{{ route(
            'users.show',
            ['user' => $user->nick]
        ) }}"
        {{ $attributes }}
    >
        {{ $user->nick }}
    </a>
@else
    <span {{ $attributes }}>
        {{ $user?->nick ?? 'Usuario eliminado' }}
    </span>
@endif