@php
    $headline = 'TU REACTIVACIÓN ESTÁ EN MARCHA';
    $eyebrow = 'BIENVENIDO DE VUELTA';
    $preheader = 'Tu vuelta a ACTIVO en Squad ALPHA está completada.';
    $sectionIntro = 'Tu vuelta a la actividad ya está preparada. Revisa estos pasos para ponerte al día y volver a estar conectado con los grupos y recursos de Squad ALPHA.';
    $steps = [
        ['title' => 'CUOTA', 'icon' => '€', 'text' => 'Si necesitas revisar tu situación, contacta con Tesorería para ponerte al día con la cuota de membresía.'],
        ['title' => 'WIKI', 'icon' => '≡', 'text' => 'Consulta de nuevo la Wiki para revisar procedimientos, manuales y cualquier cambio producido durante tu reserva.'],
        ['title' => 'GRUPOS', 'icon' => '↗', 'text' => 'Utiliza los accesos de este correo para reincorporarte a los grupos oficiales de Telegram.'],
    ];
@endphp
@include('emails.partials.member-access-layout')
