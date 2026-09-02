<?php

namespace Database\Seeders;

use App\Models\SlotType;
use App\Models\SlotTypeQuickName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SlotTypeQuickNameSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->definitions() as $definition) {
            $slotType = $this->findSlotType($definition['types']);

            if (! $slotType) {
                continue;
            }

            foreach ($definition['names'] as $index => $quickName) {
                SlotTypeQuickName::query()->updateOrCreate(
                    [
                        'slot_type_id' => $slotType->id,
                        'name' => $quickName['name'],
                    ],
                    [
                        'category' => $slotType->name,
                        'shortcut' => null,
                        'is_course_student' => (bool) ($quickName['student'] ?? false),
                        'sort_order' => ($index + 1) * 10,
                    ],
                );
            }
        }
    }

    private function findSlotType(array $candidates): ?SlotType
    {
        $normalized = collect($candidates)
            ->map(fn (string $name): string => $this->normalize($name));

        return SlotType::query()
            ->get()
            ->first(
                fn (SlotType $slotType): bool =>
                    $normalized->contains($this->normalize($slotType->name))
            );
    }

    private function normalize(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }

    private function definitions(): array
    {
        return [
            [
                'types' => ['Mando Global'],
                'names' => [
                    ['name' => 'HQ'],
                    ['name' => 'Mando global'],
                    ['name' => 'Capitán'],
                    ['name' => 'Teniente'],
                ],
            ],
            [
                'types' => ['Líder de Escuadra'],
                'names' => [
                    ['name' => 'Líder de escuadra'],
                    ['name' => 'Sargento'],
                ],
            ],
            [
                'types' => ['Lider de Equipo', 'Líder de Equipo'],
                'names' => [
                    ['name' => 'Líder de equipo'],
                    ['name' => 'Cabo'],
                ],
            ],
            [
                'types' => ['Asistente de Mando'],
                'names' => [
                    ['name' => 'Asistente de mando'],
                    ['name' => 'Enlace'],
                    ['name' => 'Intérprete'],
                ],
            ],
            [
                'types' => ['JTAC'],
                'names' => [
                    ['name' => 'JTAC'],
                    ['name' => 'FAC'],
                    ['name' => 'FO'],
                ],
            ],
            [
                'types' => ['RTO'],
                'names' => [
                    ['name' => 'RTO'],
                    ['name' => 'Operador de radio'],
                ],
            ],
            [
                'types' => ['Médico', 'Médico y Sanitario'],
                'names' => [
                    ['name' => 'Médico'],
                    ['name' => 'Doctor'],
                    ['name' => 'Sanitario'],
                ],
            ],
            [
                'types' => ['Fusilero'],
                'names' => [
                    ['name' => 'Fusilero'],
                    ['name' => 'Operador'],
                    ['name' => 'Guerrillero'],
                ],
            ],
            [
                'types' => ['Granadero'],
                'names' => [
                    ['name' => 'Granadero'],
                ],
            ],
            [
                'types' => ['Ametrallador'],
                'names' => [
                    ['name' => 'Fusilero automático'],
                ],
            ],
            [
                'types' => ['Especialista'],
                'names' => [
                    ['name' => 'Breacher'],
                ],
            ],
            [
                'types' => ['Tirador'],
                'names' => [
                    ['name' => 'Tirador selecto'],
                    ['name' => 'Francotirador'],
                ],
            ],
            [
                'types' => ['Spotter', 'Observador'],
                'names' => [
                    ['name' => 'Observador'],
                ],
            ],
            [
                'types' => ['Ametrallador'],
                'names' => [
                    ['name' => 'Ametrallador'],
                ],
            ],
            [
                'types' => ['AT-AA', 'Fusilero AT'],
                'names' => [
                    ['name' => 'Fusilero LAT'],
                    ['name' => 'Operador LAT'],
                    ['name' => 'Fusilero HAT'],
                    ['name' => 'Operador HAT'],
                    ['name' => 'Fusilero AA'],
                    ['name' => 'Operador AA'],
                ],
            ],
            [
                'types' => ['Asistentes', 'Asistente de Ametrallador'],
                'names' => [
                    ['name' => 'Portamuniciones'],
                    ['name' => 'Asistente de HAT'],
                    ['name' => 'Asistente de AA'],
                    ['name' => 'Asistente de ametrallador'],
                ],
            ],
            [
                'types' => ['Operador de Dron'],
                'names' => [
                    ['name' => 'Operador UAV'],
                    ['name' => 'Operador de dron'],
                ],
            ],
            [
                'types' => ['Instructor'],
                'names' => [
                    ['name' => 'Instructor'],
                ],
            ],
            [
                'types' => ['Alumno'],
                'names' => [
                    ['name' => 'Alumno', 'student' => true],
                ],
            ],
            [
                'types' => ['Carrista'],
                'names' => [
                    ['name' => 'Comandante'],
                    ['name' => 'Conductor'],
                    ['name' => 'Artillero'],
                ],
            ],
            [
                'types' => ['Piloto'],
                'names' => [
                    ['name' => 'Piloto'],
                    ['name' => 'Copiloto'],
                ],
            ],
            [
                'types' => ['Tripulante'],
                'names' => [
                    ['name' => 'Artillero de puerta'],
                ],
            ],
            [
                'types' => ['Operador de artilleria', 'Operador de artillería'],
                'names' => [
                    ['name' => 'Operador de artillería'],
                    ['name' => 'Asistente de artillería'],
                    ['name' => 'Operador de mortero'],
                    ['name' => 'Asistente de mortero'],
                ],
            ],
            [
                'types' => ['Ingeniero'],
                'names' => [
                    ['name' => 'EOD'],
                    ['name' => 'EOR'],
                    ['name' => 'Ingeniero'],
                    ['name' => 'Zapador'],
                ],
            ],
            [
                'types' => ['Especialista'],
                'names' => [
                    ['name' => 'Especialista en explosivos'],
                    ['name' => 'Artificiero'],
                ],
            ],
        ];
    }
}
