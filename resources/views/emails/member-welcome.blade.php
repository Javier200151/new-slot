@php
    $headline = '¡Enhorabuena por tu promoción a miembro de Squad ALPHA!';
    $eyebrow = 'PROMOCIÓN COMPLETADA';
    $preheader = 'Tu promoción a miembro de Squad ALPHA está completada.';
    $sectionIntro = 'Ahora que formas parte de la comunidad, completa estos pasos para dejar tu incorporación preparada y mantenerte conectado con el resto de miembros.';
    $steps = [
        ['title' => 'CUOTA', 'icon' => '€', 'text' => 'Contacta con el grupo de Tesorería para ponerte al día con la cuota de membresía.'],
        ['title' => 'WIKI', 'icon' => '≡', 'text' => 'Visita la Wiki de Squad ALPHA para consultar manuales, procedimientos y guías del grupo.'],
        ['title' => 'GRUPOS', 'icon' => '↗', 'text' => 'Usa los accesos de este correo para incorporarte a los grupos oficiales de Telegram.'],
    ];
@endphp
@include('emails.partials.member-access-layout')
