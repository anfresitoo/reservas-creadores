<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use DateTimeZone;
use InvalidArgumentException;

class TimezoneService
{
    /**
     * Common IANA timezones curated for quick selection in the UI,
     * while still accepting any valid PHP IANA timezone identifier.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getCuratedTimezones(): array
    {
        $identifiers = [
            'America/Santiago' => 'Santiago, Chile (America/Santiago)',
            'America/Argentina/Buenos_Aires' => 'Buenos Aires, Argentina (America/Argentina/Buenos_Aires)',
            'America/Lima' => 'Lima, Perú (America/Lima)',
            'America/Bogota' => 'Bogotá, Colombia (America/Bogota)',
            'America/Mexico_City' => 'Ciudad de México (America/Mexico_City)',
            'America/New_York' => 'Nueva York, EE.UU. (America/New_York)',
            'America/Los_Angeles' => 'Los Ángeles, EE.UU. (America/Los_Angeles)',
            'America/Sao_Paulo' => 'São Paulo, Brasil (America/Sao_Paulo)',
            'Europe/Madrid' => 'Madrid, España (Europe/Madrid)',
            'Europe/London' => 'Londres, Reino Unido (Europe/London)',
            'Europe/Berlin' => 'Berlín, Alemania (Europe/Berlin)',
            'Asia/Tokyo' => 'Tokio, Japón (Asia/Tokyo)',
            'UTC' => 'Tiempo Universal Coordinado (UTC)',
        ];

        $nowUtc = CarbonImmutable::now('UTC');
        $result = [];

        foreach ($identifiers as $tz => $label) {
            $offset = $nowUtc->setTimezone($tz)->format('P');
            $result[] = [
                'value' => $tz,
                'label' => "(UTC{$offset}) {$label}",
                'offset' => $offset,
            ];
        }

        return $result;
    }

    /**
     * Validate if a given string is a valid IANA timezone identifier.
     */
    public function isValidTimezone(string $timezone): bool
    {
        return in_array($timezone, DateTimeZone::listIdentifiers(), true);
    }

    /**
     * Normalize and validate timezone string, falling back to UTC if invalid.
     */
    public function normalizeTimezone(?string $timezone, string $fallback = 'UTC'): string
    {
        if ($timezone && $this->isValidTimezone($timezone)) {
            return $timezone;
        }

        return $fallback;
    }

    /**
     * Parse a local date (Y-m-d) and time (H:i or H:i:s) in a specific timezone
     * and convert it strictly to a UTC CarbonImmutable instance.
     */
    public function localWindowToUtc(string $dateYmd, string $timeHis, string $localTimezone): CarbonImmutable
    {
        if (! $this->isValidTimezone($localTimezone)) {
            throw new InvalidArgumentException("Invalid timezone [{$localTimezone}].");
        }

        $normalizedTime = strlen($timeHis) === 5 ? "{$timeHis}:00" : $timeHis;

        return CarbonImmutable::createFromFormat(
            'Y-m-d H:i:s',
            "{$dateYmd} {$normalizedTime}",
            $localTimezone
        )->utc();
    }

    /**
     * Convert a UTC datetime into the target local timezone for presentation.
     *
     * @return array{
     *     iso_utc: string,
     *     utc_formatted: string,
     *     local_iso: string,
     *     local_date: string,
     *     local_time: string,
     *     local_human: string,
     *     timezone: string,
     *     offset: string
     * }
     */
    public function formatUtcForTimezone(CarbonImmutable $utcDateTime, string $targetTimezone): array
    {
        $tz = $this->normalizeTimezone($targetTimezone, 'UTC');
        $utc = $utcDateTime->utc();
        $local = $utc->setTimezone($tz);

        return [
            'iso_utc' => $utc->format('Y-m-d\TH:i:s\Z'),
            'utc_formatted' => $utc->format('Y-m-d H:i').' UTC',
            'local_iso' => $local->toIso8601String(),
            'local_date' => $local->format('Y-m-d'),
            'local_time' => $local->format('H:i'),
            'local_human' => $local->locale('es')->isoFormat('ddd D [de] MMM YYYY, HH:mm'),
            'timezone' => $tz,
            'offset' => $local->format('P'),
        ];
    }
}
