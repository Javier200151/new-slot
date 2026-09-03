<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        // No sobrescribir una página creada/editada manualmente con este slug.
        if (DB::table('pages')->where('slug', 'faqs')->exists()) {
            return;
        }

        $content = <<<'HTML'
<h2>¿Qué necesito para ser Recluta de Squad ALPHA?</h2>
<ul>
<li>Tener más de 18 años el día que envías la Solicitud de Alistamiento.</li>
<li>Tener una copia original de Arma 3, incluidos el DLC APEX, el CDLC S.O.G. Prairie Fire y el CDLC Spearhead 1944. Ambas CDLC no son necesarias en el momento de enviar la Solicitud de Alistamiento ni durante el reclutamiento, pero sí debes comprometerte a comprarlas si promocionas a Miembro Activo/a.</li>
<li>Tener un PC capaz de correr Arma 3 con cierta soltura y al menos 150 GB de espacio disponible para addons.</li>
<li>Tener un micrófono con auriculares decentes.</li>
<li>No formar parte, ni estar en proceso de reclutamiento, de otro grupo oficial de Arma, sea cual sea su versión.</li>
<li>Haber leído la Normativa y estar en disposición de aceptarla y cumplirla.</li>
<li>Rellenar y enviar la Solicitud de Reclutamiento, ser preseleccionado/a entre las solicitudes recibidas y pasar favorablemente una entrevista o charla informativa con uno/a de nuestros/as reclutadores/as.</li>
</ul>

<h2>Ya he enviado la Solicitud de Alistamiento, ¿ahora qué hago?</h2>
<p>Una vez recibida tu solicitud, será revisada por nuestro equipo de reclutadores, que se encargará de evaluar y filtrar las candidaturas según los requisitos y criterios del grupo.</p>
<p>Si tu perfil resulta preseleccionado, uno de nuestros reclutadores se pondrá en contacto contigo para concretar una cita para una charla o entrevista.</p>
<p>Tras enviar la solicitud recibirás automáticamente un correo de confirmación. En él encontrarás, de forma opcional, una invitación para unirte a nuestra comunidad de Discord. Allí podrás permanecer en el canal de recepción, donde se publican actualizaciones sobre el estado del reclutamiento: apertura o cierre de campañas, información relevante y otros avisos.</p>
<p>La entrevista de reclutamiento será la primera toma de contacto. Nos servirá para obtener unas primeras impresiones y, al mismo tiempo, para que conozcas con más detalle el funcionamiento del grupo, el proceso de incorporación y lo que esperamos de los nuevos/as reclutas.</p>

<h2>¿Qué necesito para ser Miembro Activo de Squad ALPHA?</h2>
<p>Deberás superar el periodo de reclutamiento, que tiene una duración aproximada de un mes y medio.</p>
<p>Tras completar las tutorías, podrás apuntarte a todos nuestros eventos, con la única limitación de los llamados «roles de responsabilidad».</p>
<p>Llegado el momento, el Grupo de Tutores y el equipo de Administración evaluarán tu promoción a Miembro Activo, teniendo en cuenta tu comportamiento, tu participación, tu capacidad de aprendizaje y tu compromiso con el grupo, más allá de tus aptitudes dentro del juego.</p>
<p>Las habilidades más valoradas en un/a Recluta son la actitud, las ganas de aprender, el interés, la proactividad y la capacidad de integrarse en el grupo.</p>

<h2>¿Hay que abonar algo?</h2>
<p>Sí. Para costear nuestro servidor de juego, la web, el foro, etc. Somos un grupo sin ánimo de lucro y todo lo que se recibe de estas cuotas se invierte en el grupo.</p>
<p>Los/as Reclutas abonan una señal de 6 € al inicio que posteriormente se restará de la primera cuota de Miembro Activo/a, que es de 9 € por trimestre.</p>

<h2>¿Qué días y a qué hora jugáis?</h2>
<p>Los horarios de los Operativos Oficiales son:</p>
<ul>
<li><strong>Martes:</strong> desde las 20:00 h hasta las 22:30 h aproximadamente.</li>
<li><strong>Viernes:</strong> desde las 22:30 h hasta fin de operativo.</li>
<li><strong>Sábados:</strong> desde las 23:00 h hasta fin de operativo.</li>
</ul>
<p>Otros días también pueden organizarse distintos tipos de eventos, como operaciones «extra» (misiones de logística, reconocimiento, etc., para dar apoyo a campañas), instrucciones, maniobras y prácticas; estas últimas no oficiales.</p>
<p>Para este tipo de actividades no existe un horario establecido oficialmente, por lo que puede variar según las necesidades del grupo.</p>

<h2>¿Qué medios usáis para jugar a Arma 3?</h2>
<p>Además de Arma 3, deberás instalar unas versiones específicas de TeamSpeak 3 y Arma3Sync. El primero es nuestro método de comunicación directa tanto dentro como fuera de los Operativos y el segundo te servirá para sincronizar los addons entre nuestro servidor y tu PC.</p>
<p>Si no has usado nunca alguno de ellos, tu tutor te ayudará a descargarlos, instalarlos, configurarlos y manejarlos con soltura.</p>

<h2>¿Qué medios de comunicación usáis?</h2>
<p>Además de TeamSpeak 3, nuestro medio principal de comunicación es el foro. También disponemos de una comunidad en Discord para otros fines.</p>
<p>Asimismo, contamos con varios grupos de Telegram y WhatsApp para coordinarnos de forma más inmediata.</p>

<h2>¿Es posible editar en Squad ALPHA?</h2>
<p>Para ser editor/a en Squad ALPHA lo único que se requiere es la condición de Miembro Activo/a. Eso sí, todos nuestros operativos deben ser aprobados por el Grupo de Edición y Testeo, que establece unos mínimos y criterios.</p>
<p>Asimismo, dispondrás de tutoriales, plantillas y toda la ayuda del resto de editores/as como soporte a la hora de editar.</p>

<h2>¿Qué unidades simuláis?</h2>
<p>Todas las que nuestros/as editores/as decidan y tengan a su alcance en el repositorio preparado por el Grupo de Edición y Testeo. Hemos simulado desde la intervención cubana en Angola a conflictos ficticios entre soviéticos y estadounidenses en la Guerra Fría, pasando por la Segunda Guerra Mundial, la Guerra de Vietnam y conflictos modernos en Iraq, Afganistán o Ucrania… y lo que se nos presente.</p>
<p>Siempre y cuando el contexto sea coherente —que no real— nos verás allí desplegados/as.</p>

<h2>¿En qué roles puedes jugar como Recluta? ¿Y como Miembro?</h2>
<p>Como Recluta, por mucha experiencia de juego que tengas, solo podrás tomar los roles básicos, aquellos que no sean de responsabilidad (Fusilero/a, Granadero/a, etc.).</p>
<p>Una vez seas Miembro Activo/a, podrás tomar cualquier rol siempre y cuando te veas capacitado/a para ello. Es responsabilidad de cada uno/a haberse preparado para el desempeño de sus funciones de forma correcta.</p>

<h2>¿Qué jerarquía hay dentro y fuera de un Operativo? ¿Usáis empleos militares?</h2>
<p>Dentro de un Operativo habrá un mando global que planeará las operaciones y unos mandos intermedios que transmitirán las órdenes. A todos/as se les mostrará el respeto correspondiente, simulando su cargo.</p>
<p>Fuera del Operativo, todos/as los/as Miembros Activos/as son iguales y tienen los mismos derechos. Podrán pertenecer a los diferentes Grupos de Trabajo, pero lo único que aportará esto serán responsabilidades.</p>
<p>No usamos ni empleos ni disciplina militar estricta, simplemente el respeto y el cumplimiento de nuestra Normativa.</p>

<h2>¿Tenéis algún tipo de orientación política, religiosa o deportiva?</h2>
<p>No. Este grupo está desprovisto de cualquier tipo de orientación política, deportiva o religiosa por considerarlos temas sensibles. Por lo tanto, queda terminantemente prohibido en un Medio Oficial del grupo hacer cualquier tipo de comentario, referencia o apología de las mismas, siendo esto sancionable.</p>

<h2>Una vez aceptado/a como Recluta, ¿puedo publicarlo en mi perfil de redes sociales y hacer retransmisiones online o similares de los eventos en los que participe?</h2>
<p>No hay problema en comentar en las redes sociales que se es Recluta de Squad ALPHA, pero tendrás que esperar a ser Miembro de pleno derecho para la retransmisión en streaming o en diferido de operativos (YouTube, Twitch u otros) en los que participes.</p>

<h2>¿Puedo alistarme si resido en LATAM?</h2>
<p>Lamentablemente, la respuesta es no. No puedes alistarte si resides en Latinoamérica. Squad ALPHA es una unidad de simulación con base en España, aunque nuestros operadores también pueden residir en otras zonas de Europa.</p>
<p>Esto es un requisito indispensable debido a varios factores como el ping a nuestro servidor, la disponibilidad horaria, los cambios de divisa, etc.</p>
<p>Si te encuentras en estas circunstancias, te aconsejamos buscar una comunidad de juego en tu zona de residencia mediante buscadores de internet, redes sociales, etc.</p>

<h2>¿Puedo alistarme si solo tengo Arma Reforger?</h2>
<p>Actualmente, la respuesta es no. No puedes alistarte si solo dispones de Arma Reforger, ya que, por el momento, nuestra actividad principal sigue siendo Arma 3.</p>
<p>No obstante, ya estamos jugando también en Arma Reforger. Contamos con un grupo de trabajo dedicado a organizar eventos, editar escenarios y seleccionar contenido (addons, etc.) para disfrutar del último título de la saga Arma.</p>
<p>Por lo tanto, si tienes Arma Reforger podrás jugarlo con nosotros, pero deberás disponer también de Arma 3 y participar en sus eventos para poder alistarte.</p>
HTML;

        $now = now();

        DB::table('pages')->insert([
            'title' => 'FAQs',
            'slug' => 'faqs',
            'content' => $content,
            'is_published' => true,
            'created_by' => null,
            'updated_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    public function down(): void
    {
        // Intencionadamente vacío: la página puede haber sido editada desde
        // Filament después de desplegarla y un rollback no debe borrar contenido.
    }
};
