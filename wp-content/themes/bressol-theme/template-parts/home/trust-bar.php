<?php
if (!defined('ABSPATH')) {
    exit;
}

$items = $args['data'] ?? [];
?>

<section class="bressol-section bressol-trust" aria-label="Trust highlights">
    <div class="bressol-trust__inner">
        <ul class="bressol-trust__list">
            <li class="bressol-trust__item">
                <a class="bressol-trust__link" href="#" aria-label="Gratis verzending vanaf €75">
                    <span class="bressol-trust__icon" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M3 7h11v9H3zM14 9h4l3 3v4h-7" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="7.5" cy="18" r="2" stroke="currentColor" stroke-width="1.5" fill="none"/>
                            <circle cx="17.5" cy="18" r="2" stroke="currentColor" stroke-width="1.5" fill="none"/>
                        </svg>
                    </span>
                    <span class="bressol-trust__text">Gratis verzending vanaf €75</span>
                </a>
            </li>
            <li class="bressol-trust__item">
                <a class="bressol-trust__link" href="#" aria-label="Persoonlijke levering wanneer mogelijk">
                    <span class="bressol-trust__icon" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M4 14h6l3 2h7" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M10 8a3 3 0 1 1 4 3" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round"/>
                            <path d="M10 15l2-2 2 2" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span class="bressol-trust__text">Persoonlijke levering wanneer mogelijk</span>
                </a>
            </li>
            <li class="bressol-trust__item">
                <a class="bressol-trust__link" href="#" aria-label="Ambachtelijke selectie uit Valencia">
                    <span class="bressol-trust__icon" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M12 3l2.2 4.5 5 .7-3.6 3.4.9 4.9L12 14.8 7.5 16.5l.9-4.9L4.8 8.2l5-.7L12 3z" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span class="bressol-trust__text">Ambachtelijke selectie uit Valencia</span>
                </a>
            </li>
            <li class="bressol-trust__item">
                <a class="bressol-trust__link" href="#" aria-label="Klantenservice met aandacht">
                    <span class="bressol-trust__icon" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M4 5h16v11H7l-3 3z" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M9 11c1.5-2 4.5-2 6 0" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round"/>
                        </svg>
                    </span>
                    <span class="bressol-trust__text">Klantenservice met aandacht</span>
                </a>
            </li>
        </ul>
    </div>
</section>
