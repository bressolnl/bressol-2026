<?php
if (!defined('ABSPATH')) { exit; }

$items = $args['data'] ?? [];

// Fallback “4 tiles” si no hay data aún
if (!is_array($items) || $items === []) {
    $items = [
        ['title' => 'Borrel & Dranken', 'copy' => 'Voor de borrel, zoals in Valencia.', 'url' => '#'],
        ['title' => 'Kook zoals een chef', 'copy' => 'Rijst, olie en smaakmakers.', 'url' => '#'],
        ['title' => 'Cadeaus', 'copy' => 'Om te geven, om te delen.', 'url' => '#'],
        ['title' => 'El dolçet per al cafè', 'copy' => 'Iets zoets bij de koffie.', 'url' => '#'],
    ];
}
?>

<section class="bressol-section bressol-shortcuts" aria-label="Shortcuts">
  <div class="bressol-container">
    <div class="bressol-section__header">
      <h2 class="bressol-section__title">Waar begint jouw moment?</h2>
      <p class="bressol-section__lead">Kies een startpunt dat past bij je moment.</p>
    </div>

    <div class="bressol-shortcuts__grid">
      <?php foreach ($items as $i => $item) :
        $title = $item['title'] ?? 'Shortcut';
        $copy  = $item['copy'] ?? '';
        $url   = $item['url'] ?? '#';
      ?>
        <a class="bressol-shortcut-card" href="<?php echo esc_url($url); ?>" aria-label="<?php echo esc_attr($title); ?>">
          <div class="bressol-shortcut-card__media" aria-hidden="true">
            <span class="bressol-shortcut-card__media-inner bressol-media-placeholder"></span>
          </div>

          <div class="bressol-shortcut-card__body">
            <h3 class="bressol-shortcut-card__title"><?php echo esc_html($title); ?></h3>
            <?php if ($copy !== '') : ?>
              <p class="bressol-shortcut-card__copy"><?php echo esc_html($copy); ?></p>
            <?php endif; ?>
            <span class="bressol-shortcut-card__cta">Ontdek →</span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>