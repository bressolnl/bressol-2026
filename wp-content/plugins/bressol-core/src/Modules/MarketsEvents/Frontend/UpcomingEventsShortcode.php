<?php
declare(strict_types=1);

namespace Bressol\Modules\MarketsEvents\Frontend;

use Bressol\Modules\MarketsEvents\Repositories\EventRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class UpcomingEventsShortcode
{
    private const ALLOWED_TYPES = ['market', 'event'];

    private EventRepository $repository;

    public function __construct(?EventRepository $repository = null)
    {
        $this->repository = $repository ?? new EventRepository();
    }

    public function register(): void
    {
        add_shortcode('bressol_upcoming_events', [$this, 'render']);
    }

    /** @param array<string, mixed> $atts */
    public function render(array $atts = []): string
    {
        $atts = shortcode_atts([
            'limit' => 4,
        ], $atts, 'bressol_upcoming_events');

        $limit = max(1, min(12, absint($atts['limit'] ?? 4)));
        $from = new \DateTimeImmutable('now', wp_timezone());
        $prefetch = max(10, $limit * 3);

        $events = $this->repository->list_upcoming_confirmed($prefetch, $from);
        $events = $this->filter_types($events);
        $events = array_slice($events, 0, $limit);

        if ($events === []) {
            return $this->render_empty();
        }

        $momentsUrl = $this->resolve_page_url('momenten', home_url('/momenten/'));

        ob_start();
        ?>
        <div class="bressol-agenda">
            <div class="bressol-agenda__grid">
                <?php foreach ($events as $event) : ?>
                    <?php
                    $title = (string) ($event['title'] ?? '');
                    $type = (string) ($event['type'] ?? '');
                    $location = (string) ($event['location_name'] ?? '');
                    $dateLabel = $this->format_event_date($event);
                    $metaParts = array_filter([$dateLabel, $location], static function ($value): bool {
                        return $value !== '';
                    });
                    $meta = implode(' · ', $metaParts);
                    $url = $this->resolve_event_url($event, $momentsUrl);
                    ?>
                    <article class="bressol-card bressol-agenda__card">
                        <span class="bressol-card__badge bressol-agenda__badge <?php echo esc_attr($this->badge_class($type)); ?>">
                            <?php echo esc_html($this->label_for_type($type)); ?>
                        </span>
                        <h3 class="bressol-card__title">
                            <a class="bressol-card__link" href="<?php echo esc_url($url); ?>">
                                <?php echo esc_html($title); ?>
                            </a>
                        </h3>
                        <?php if ($meta !== '') : ?>
                            <p class="bressol-card__meta"><?php echo esc_html($meta); ?></p>
                        <?php endif; ?>
                        <a class="bressol-link bressol-agenda__detail" href="<?php echo esc_url($url); ?>">
                            <?php esc_html_e('Details', 'bressol-core'); ?>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /** @param array<int, array<string, mixed>> $events
     *  @return array<int, array<string, mixed>>
     */
    private function filter_types(array $events): array
    {
        return array_values(array_filter($events, function (array $event): bool {
            $type = isset($event['type']) ? sanitize_key((string) $event['type']) : '';
            return in_array($type, self::ALLOWED_TYPES, true);
        }));
    }

    /** @param array<string, mixed> $event */
    private function format_event_date(array $event): string
    {
        $raw = (string) ($event['start_at'] ?? '');
        if ($raw === '') {
            return '';
        }

        $timezone = $this->resolve_timezone($event['timezone'] ?? null);
        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $raw, $timezone);
        if (!$date) {
            return '';
        }

        $timestamp = $date->getTimestamp();
        return wp_date('j M', $timestamp, $timezone);
    }

    private function resolve_timezone($value): \DateTimeZone
    {
        $raw = is_string($value) ? trim($value) : '';
        if ($raw !== '') {
            try {
                return new \DateTimeZone($raw);
            } catch (\Throwable $exception) {
                // Fallback below.
            }
        }

        return wp_timezone();
    }

    /** @param array<string, mixed> $event */
    private function resolve_event_url(array $event, string $fallbackUrl): string
    {
        $mapsUrl = isset($event['google_maps_url']) ? (string) $event['google_maps_url'] : '';
        $mapsUrl = trim($mapsUrl);
        if ($mapsUrl !== '') {
            $sanitized = esc_url_raw($mapsUrl);
            if ($sanitized !== '') {
                return $sanitized;
            }
        }

        return $fallbackUrl;
    }

    private function label_for_type(string $type): string
    {
        $type = sanitize_key($type);
        if ($type === 'market') {
            return __('Market', 'bressol-core');
        }
        if ($type === 'event') {
            return __('Event', 'bressol-core');
        }
        return __('Agenda', 'bressol-core');
    }

    private function badge_class(string $type): string
    {
        $type = sanitize_key($type);
        if ($type === 'market') {
            return 'bressol-agenda__badge--market';
        }
        if ($type === 'event') {
            return 'bressol-agenda__badge--event';
        }
        return 'bressol-agenda__badge--generic';
    }

    private function render_empty(): string
    {
        $adviesUrl = $this->resolve_page_url('advies', home_url('/'));
        $pakkettenUrl = $this->resolve_page_url('pakketten', home_url('/'));

        ob_start();
        ?>
        <div class="bressol-agenda">
            <div class="bressol-agenda__empty">
                <p class="bressol-empty"><?php esc_html_e('Geen bevestigde markten of events gepland.', 'bressol-core'); ?></p>
                <div class="bressol-agenda__cta">
                    <a class="bressol-link" href="<?php echo esc_url($adviesUrl); ?>">
                        <?php esc_html_e('Start met advies', 'bressol-core'); ?>
                    </a>
                    <a class="bressol-link" href="<?php echo esc_url($pakkettenUrl); ?>">
                        <?php esc_html_e('Bekijk pakketten', 'bressol-core'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function resolve_page_url(string $slug, string $fallback): string
    {
        $page = get_page_by_path($slug);
        if ($page instanceof \WP_Post) {
            $link = get_permalink($page);
            if (is_string($link) && $link !== '') {
                return $link;
            }
        }

        return $fallback;
    }
}
