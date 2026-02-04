<?php
declare(strict_types=1);

namespace Bressol\Modules\MarketsEvents\Services;

use Bressol\Modules\MarketsEvents\Repositories\EventRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class EventService
{
    private const TYPES = ['market', 'event', 'delivery', 'other'];
    private const STATUSES = ['planned', 'confirmed', 'paid', 'completed', 'cancelled', 'done'];
    private const CHANNELS = ['pos', 'web', 'both'];

    private EventRepository $repository;

    public function __construct(?EventRepository $repository = null)
    {
        $this->repository = $repository ?? new EventRepository();
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    public function normalize(array $payload): array
    {
        $timezone = $this->normalize_timezone(isset($payload['timezone']) ? (string) $payload['timezone'] : '');

        $startAt = $this->normalize_datetime($payload['start_at'] ?? null, $timezone, false);
        $endAt = $this->normalize_datetime($payload['end_at'] ?? null, $timezone, true);

        $distanceKm = $this->normalize_decimal($payload['distance_km'] ?? null, 2);
        $travelMinutes = $this->normalize_int($payload['travel_time_min'] ?? null, null);

        return [
            'title' => isset($payload['title']) ? trim(sanitize_text_field((string) $payload['title'])) : '',
            'type' => isset($payload['type']) ? sanitize_key((string) $payload['type']) : 'event',
            'status' => isset($payload['status']) ? sanitize_key((string) $payload['status']) : 'planned',
            'start_at' => $startAt,
            'end_at' => $endAt,
            'timezone' => $timezone,
            'location_name' => isset($payload['location_name']) ? trim(sanitize_text_field((string) $payload['location_name'])) : '',
            'address' => isset($payload['address']) ? trim(sanitize_textarea_field((string) $payload['address'])) : '',
            'google_maps_url' => $this->normalize_url($payload['google_maps_url'] ?? null),
            'distance_km' => $distanceKm,
            'travel_time_min' => $travelMinutes,
            'booth_fee_cents' => $this->normalize_int($payload['booth_fee_cents'] ?? 0, 0),
            'other_costs_cents' => $this->normalize_int($payload['other_costs_cents'] ?? 0, 0),
            'expected_sales_cents' => $this->normalize_int($payload['expected_sales_cents'] ?? null, null),
            'notes' => isset($payload['notes']) ? trim(sanitize_textarea_field((string) $payload['notes'])) : '',
            'channels' => isset($payload['channels']) ? sanitize_key((string) $payload['channels']) : 'both',
        ];
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, string>
     */
    public function validate(array $payload): array
    {
        $errors = [];
        $timezone = isset($payload['timezone']) ? (string) $payload['timezone'] : '';
        $tz = null;
        if ($timezone !== '') {
            try {
                $tz = new \DateTimeZone($timezone);
            } catch (\Throwable $exception) {
                $tz = null;
            }
        }

        if (($payload['title'] ?? '') === '') {
            $errors['title'] = 'Título obligatorio.';
        }

        if (!in_array((string) ($payload['type'] ?? ''), self::TYPES, true)) {
            $errors['type'] = 'Tipo inválido.';
        }

        if (!in_array((string) ($payload['status'] ?? ''), self::STATUSES, true)) {
            $errors['status'] = 'Estado inválido.';
        }

        if (!in_array((string) ($payload['channels'] ?? ''), self::CHANNELS, true)) {
            $errors['channels'] = 'Canal inválido.';
        }

        if (($payload['start_at'] ?? '') === '') {
            $errors['start_at'] = 'Fecha inicio obligatoria.';
        }

        if (($payload['end_at'] ?? '') === '') {
            $errors['end_at'] = 'Fecha fin obligatoria.';
        }

        if (($payload['location_name'] ?? '') === '') {
            $errors['location_name'] = 'Ubicación obligatoria.';
        }

        if (($payload['address'] ?? '') === '') {
            $errors['address'] = 'Dirección obligatoria.';
        }

        if ($tz instanceof \DateTimeZone && ($payload['start_at'] ?? '') !== '' && ($payload['end_at'] ?? '') !== '') {
            $start = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $payload['start_at'], $tz);
            $end = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $payload['end_at'], $tz);
            if ($start && $end && $end < $start) {
                $errors['end_at'] = 'La fecha fin debe ser posterior a la de inicio.';
            }
        }

        return $errors;
    }

    /** @param array<string, mixed> $payload */
    public function create(array $payload, int $actorId): int
    {
        $normalized = $this->normalize($payload);
        $errors = $this->validate($normalized);
        if ($errors !== []) {
            return 0;
        }

        $now = current_time('mysql');
        $normalized['created_at'] = $now;
        $normalized['updated_at'] = $now;

        $id = $this->repository->insert($normalized);

        // TODO: auditoría (sin PII) cuando se integre en PR posteriores.
        return $id;
    }

    /** @param array<string, mixed> $payload */
    public function update(int $id, array $payload, int $actorId): bool
    {
        if ($id <= 0) {
            return false;
        }

        $normalized = $this->normalize($payload);
        $errors = $this->validate($normalized);
        if ($errors !== []) {
            return false;
        }

        $normalized['updated_at'] = current_time('mysql');

        $ok = $this->repository->update($id, $normalized);
        if (!$ok) {
            return false;
        }

        // TODO: auditoría (sin PII) cuando se integre en PR posteriores.
        return true;
    }

    public function duplicate(int $id, int $actorId): int
    {
        if ($id <= 0) {
            return 0;
        }

        $event = $this->repository->find_by_id($id);
        if (!$event) {
            return 0;
        }

        $payload = $event;
        unset($payload['id'], $payload['created_at'], $payload['updated_at']);
        $payload['title'] = '[COPY] ' . (string) ($event['title'] ?? '');
        $payload['status'] = 'planned';
        $payload = $this->shift_event_dates($payload, $event);

        $newId = $this->create($payload, $actorId);

        // TODO: auditoría (sin PII) cuando se integre en PR posteriores.
        return $newId;
    }

    private function normalize_datetime($value, string $timezone, bool $endOfDay): string
    {
        if ($value instanceof \DateTimeImmutable) {
            try {
                return $value->setTimezone(new \DateTimeZone($timezone))->format('Y-m-d H:i:s');
            } catch (\Throwable $exception) {
                return '';
            }
        }

        $raw = is_string($value) ? trim($value) : '';
        if ($raw === '') {
            return '';
        }

        try {
            $tz = new \DateTimeZone($timezone);
        } catch (\Throwable $exception) {
            return '';
        }
        $dateTime = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $raw, $tz);
        if ($dateTime instanceof \DateTimeImmutable && $dateTime->format('Y-m-d H:i:s') === $raw) {
            return $dateTime->format('Y-m-d H:i:s');
        }

        $dateOnly = \DateTimeImmutable::createFromFormat('Y-m-d', $raw, $tz);
        if ($dateOnly instanceof \DateTimeImmutable && $dateOnly->format('Y-m-d') === $raw) {
            $time = $endOfDay ? '23:59:59' : '00:00:00';
            return $dateOnly->format('Y-m-d') . ' ' . $time;
        }

        return '';
    }

    private function normalize_url($value): ?string
    {
        $raw = is_string($value) ? trim($value) : '';
        if ($raw === '') {
            return null;
        }

        $url = esc_url_raw($raw);
        return $url !== '' ? $url : null;
    }

    private function normalize_decimal($value, int $precision): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $float = is_numeric($value) ? (float) $value : null;
        if ($float === null) {
            return null;
        }

        if ($float < 0) {
            $float = 0.0;
        }

        return number_format($float, $precision, '.', '');
    }

    private function normalize_int($value, ?int $default): ?int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        $int = (int) $value;
        if ($int < 0) {
            $int = 0;
        }

        return $int;
    }

    private function normalize_timezone(string $timezone): string
    {
        $timezone = trim($timezone);
        if ($this->is_valid_timezone($timezone)) {
            return $timezone;
        }

        $fallback = wp_timezone_string();
        if ($this->is_valid_timezone($fallback)) {
            return $fallback;
        }

        return 'UTC';
    }

    private function is_valid_timezone(string $timezone): bool
    {
        if ($timezone === '') {
            return false;
        }

        try {
            new \DateTimeZone($timezone);
        } catch (\Throwable $exception) {
            return false;
        }

        return true;
    }

    /** @param array<string, mixed> $payload
     *  @param array<string, mixed> $source
     *  @return array<string, mixed>
     */
    private function shift_event_dates(array $payload, array $source): array
    {
        $timezone = isset($source['timezone']) ? (string) $source['timezone'] : '';
        $timezone = $this->normalize_timezone($timezone);
        $tz = new \DateTimeZone($timezone);

        $startRaw = isset($source['start_at']) ? (string) $source['start_at'] : '';
        $endRaw = isset($source['end_at']) ? (string) $source['end_at'] : '';
        if ($startRaw === '' || $endRaw === '') {
            return $payload;
        }

        $start = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $startRaw, $tz);
        $end = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $endRaw, $tz);
        if (!$start || !$end) {
            return $payload;
        }

        $duration = $end->getTimestamp() - $start->getTimestamp();
        $newStart = $start->modify('+7 days');
        $newEnd = $duration > 0 ? $newStart->modify('+' . $duration . ' seconds') : $end->modify('+7 days');

        $payload['start_at'] = $newStart->format('Y-m-d H:i:s');
        $payload['end_at'] = $newEnd->format('Y-m-d H:i:s');
        $payload['timezone'] = $timezone;

        return $payload;
    }
}
