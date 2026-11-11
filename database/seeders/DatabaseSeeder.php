<?php

namespace Database\Seeders;

use App\Models\Availability;
use App\Models\AvailabilityException;
use App\Models\Booking;
use App\Models\SessionType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $creator = User::updateOrCreate(
            ['email' => 'alex@esponsor.com'],
            [
                'name' => 'Alex Rivera',
                'username' => 'alex-dev',
                'bio' => 'Staff Full-Stack Engineer & Creador Tech. Sesiones 1 a 1 sobre arquitectura en Laravel, Vue 3, escalabilidad y mentoría de carrera.',
                'avatar_url' => 'https://ui-avatars.com/api/?name=Alex+Rivera&background=4f46e5&color=fff&size=256',
                'timezone' => 'America/Santiago',
                'password' => Hash::make('password'),
            ]
        );

        $sessionTypes = [
            [
                'slug' => 'mentoria-express-15',
                'title' => 'Mentoría Express 1 a 1',
                'description' => 'Sesión rápida para resolver dudas puntuales sobre tu carrera, CV o stack tecnológico.',
                'duration_minutes' => 15,
                'is_active' => true,
            ],
            [
                'slug' => 'revision-codigo-arquitectura-30',
                'title' => 'Revisión de Código & Arquitectura',
                'description' => 'Revisamos en vivo tu repositorio, diseño modular (Services/Actions) y buenas prácticas en Laravel y Vue.',
                'duration_minutes' => 30,
                'is_active' => true,
            ],
            [
                'slug' => 'consultoria-sistema-60',
                'title' => 'Consultoría Técnica Profunda',
                'description' => 'Sesión intensiva 1 a 1 de diseño de sistemas, concurrencia, zonas horarias en UTC y preparación de entrevistas.',
                'duration_minutes' => 60,
                'is_active' => true,
            ],
        ];

        $createdTypes = [];
        foreach ($sessionTypes as $typeData) {
            $createdTypes[$typeData['slug']] = SessionType::updateOrCreate(
                [
                    'user_id' => $creator->id,
                    'slug' => $typeData['slug'],
                ],
                $typeData
            );
        }

        // Horario semanal en la zona horaria del creador (America/Santiago): Lunes (1) a Viernes (5) 09:00 - 17:00
        for ($day = 0; $day <= 6; $day++) {
            $isWeekday = $day >= 1 && $day <= 5;
            Availability::updateOrCreate(
                [
                    'user_id' => $creator->id,
                    'day_of_week' => $day,
                ],
                [
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                    'is_enabled' => $isWeekday,
                ]
            );
        }

        // Próximo día hábil en la zona horaria del creador para crear una reserva de ejemplo en UTC
        $nextWeekdayLocal = CarbonImmutable::now($creator->timezone)->addDays(1)->startOfDay();
        while ($nextWeekdayLocal->isWeekend()) {
            $nextWeekdayLocal = $nextWeekdayLocal->addDay();
        }

        // Excepción de ejemplo: horario reducido dentro de 5 días
        $specialDate = $nextWeekdayLocal->addDays(4)->format('Y-m-d');
        AvailabilityException::updateOrCreate(
            [
                'user_id' => $creator->id,
                'exception_date' => $specialDate,
            ],
            [
                'is_available' => true,
                'start_time' => '10:00:00',
                'end_time' => '13:00:00',
                'reason' => 'Horario especial por grabación de contenido',
            ]
        );

        // Reserva de ejemplo guardada estrictamente en UTC (10:00 hora local de Santiago -> convertida a UTC)
        $sampleStartLocal = $nextWeekdayLocal->setTime(10, 0, 0);
        $sampleStartUtc = $sampleStartLocal->utc();
        $sampleSession = $createdTypes['revision-codigo-arquitectura-30'];
        $sampleEndUtc = $sampleStartUtc->addMinutes($sampleSession->duration_minutes);

        Booking::updateOrCreate(
            [
                'creator_id' => $creator->id,
                'start_at_utc' => $sampleStartUtc->format('Y-m-d H:i:s'),
            ],
            [
                'session_type_id' => $sampleSession->id,
                'guest_name' => 'Sofía Martínez',
                'guest_email' => 'sofia.martinez@example.com',
                'guest_timezone' => 'Europe/Madrid',
                'notes' => 'Me gustaría revisar la estructura de Actions y manejo de zonas horarias en mi proyecto.',
                'end_at_utc' => $sampleEndUtc->format('Y-m-d H:i:s'),
                'status' => Booking::STATUS_CONFIRMED,
            ]
        );
    }
}
