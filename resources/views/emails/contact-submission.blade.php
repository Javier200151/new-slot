<!doctype html>
<html lang="es">
<body style="font-family:Arial,sans-serif;color:#111">
    <h2>{{ $submission->is_recruitment ? 'Solicitud de alistamiento' : 'Consulta web' }}</h2>
    <p><strong>Email:</strong> {{ $submission->email }}</p>
    <p><strong>Mensaje:</strong></p>
    <p>{!! nl2br(e($submission->message)) !!}</p>

    @if($submission->is_recruitment)
        <hr>
        <h3>Alistamiento</h3>
        <ul>
            <li>Normativa aceptada: {{ $submission->accepted_rules ? 'Sí' : 'No' }}</li>
            <li>Mayor de edad: {{ $submission->is_adult ? 'Sí' : 'No' }}</li>
            <li>Acepta aportaciones económicas: {{ $submission->accepts_contributions ? 'Sí' : 'No' }}</li>
            <li>Arma 3 + contenidos requeridos: {{ $submission->has_required_game_content ? 'Sí' : 'No' }}</li>
            <li>Disponible martes: {{ $submission->tuesday_available ? 'Sí' : 'No' }}</li>
            <li>Disponible viernes: {{ $submission->friday_available ? 'Sí' : 'No' }}</li>
            <li>Experiencia previa en simulación: {{ $submission->has_previous_experience ? 'Sí' : 'No' }}</li>
        </ul>
    @endif
</body>
</html>
