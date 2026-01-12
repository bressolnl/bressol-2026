<?php
declare(strict_types=1);

namespace Bressol\Modules\GuidedShopping\Frontend;

use Bressol\Modules\GuidedShopping\Wizard\WizardSession;
use Bressol\Modules\GuidedShopping\Wizard\PackRecommender;
use Bressol\Modules\GuidedShopping\Wizard\UpsellRecommender;

if (!defined('ABSPATH')) {
    exit;
}

final class WizardShortcode
{
    public function register(): void
    {
        add_shortcode('bressol_guided_shopping', [$this, 'render']);
    }

    public function render(): string
    {
        $session = new WizardSession();

        // Manejo de POST (guardar o reset)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bressol_gs_action'])) {
            if (!isset($_POST['bressol_gs_nonce']) || !wp_verify_nonce((string) $_POST['bressol_gs_nonce'], 'bressol_gs')) {
                return '<p>Nonce inválido. Recarga la página.</p>';
            }

            $action = sanitize_text_field((string) $_POST['bressol_gs_action']);

            if ($action === 'reset') {
                $session->clear();
            }

            if ($action === 'save') {
                $occasion   = isset($_POST['occasion']) ? sanitize_text_field((string) $_POST['occasion']) : '';
                $budget     = isset($_POST['budget']) ? sanitize_text_field((string) $_POST['budget']) : '';
                $preference = isset($_POST['preference']) ? sanitize_text_field((string) $_POST['preference']) : '';

                $session->set('occasion', $occasion);
                $session->set('budget', $budget);
                $session->set('preference', $preference);
            }
        }

        // Estado actual desde sesión (siempre)
        $occasion   = $session->get('occasion', '');
        $budget     = $session->get('budget', '');
        $preference = $session->get('preference', '');

        // Recomendaciones
        $recommender = new PackRecommender();
        $recommendations = $recommender->recommend($budget ?: null, $occasion ?: null);

        // Upsells
        $upsellRecommender = new UpsellRecommender();
        $upsells = $upsellRecommender->recommend($budget ?: null, $occasion ?: null, $recommendations);

        // URL actual (para volver al wizard después de add-to-cart)
        $scheme = is_ssl() ? 'https://' : 'http://';
        $host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '';
        $uri  = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $currentUrl = esc_url_raw($scheme . $host . $uri);

        ob_start();
        ?>
        <div class="bressol-guided-shopping" style="border:1px solid #ddd;padding:16px;max-width:520px;">
            <h3 style="margin-top:0;">Encuentra tu pack ideal</h3>

            <form method="post">
                <?php wp_nonce_field('bressol_gs', 'bressol_gs_nonce'); ?>
                <input type="hidden" name="bressol_gs_action" value="save" />

                <div style="margin:12px 0;">
                    <label style="display:block;font-weight:600;margin-bottom:6px;">¿Es para regalo o para ti?</label>
                    <select name="occasion" style="width:100%;padding:8px;">
                        <option value="">-- Selecciona --</option>
                        <option value="gift" <?php selected($occasion, 'gift'); ?>>Regalo</option>
                        <option value="self" <?php selected($occasion, 'self'); ?>>Para mí</option>
                    </select>
                </div>

                <div style="margin:12px 0;">
                    <label style="display:block;font-weight:600;margin-bottom:6px;">Presupuesto</label>
                    <select name="budget" style="width:100%;padding:8px;">
                        <option value="">-- Selecciona --</option>
                        <option value="low" <?php selected($budget, 'low'); ?>>Bajo</option>
                        <option value="mid" <?php selected($budget, 'mid'); ?>>Medio</option>
                        <option value="high" <?php selected($budget, 'high'); ?>>Alto</option>
                    </select>
                </div>

                <div style="margin:12px 0;">
                    <label style="display:block;font-weight:600;margin-bottom:6px;">¿Qué te apetece?</label>
                    <select name="preference" style="width:100%;padding:8px;">
                        <option value="">-- Selecciona --</option>
                        <option value="borrel" <?php selected($preference, 'borrel'); ?>>Borrel / Tapas</option>
                        <option value="oil" <?php selected($preference, 'oil'); ?>>Aceites & Smaakmakers</option>
                        <option value="sweet" <?php selected($preference, 'sweet'); ?>>Dulce / Postre</option>
                        <option value="paella" <?php selected($preference, 'paella'); ?>>Paella / Arroces</option>
                        <option value="drinks" <?php selected($preference, 'drinks'); ?>>Vinos & Bebidas</option>
                    </select>
                </div>

                <button type="submit" style="padding:10px 14px;">Ver recomendaciones</button>
            </form>

            <form method="post" style="margin-top:10px;">
                <?php wp_nonce_field('bressol_gs', 'bressol_gs_nonce'); ?>
                <input type="hidden" name="bressol_gs_action" value="reset" />
                <button type="submit" style="padding:8px 12px;">Reset</button>
            </form>

            <hr style="margin:14px 0;" />

            <div>
                <strong>Estado actual:</strong>
                <ul style="margin:8px 0 0 18px;">
                    <li>occasion: <code><?php echo esc_html($occasion ?: '(vacío)'); ?></code></li>
                    <li>budget: <code><?php echo esc_html($budget ?: '(vacío)'); ?></code></li>
                    <li>preference: <code><?php echo esc_html($preference ?: '(vacío)'); ?></code></li>
                </ul>
            </div>

            <div style="margin-top:12px;">
                <h4 style="margin:0 0 8px 0;">Recomendaciones</h4>

                <?php if (!$budget): ?>
                    <p style="color:#666;margin:0;">Elige un presupuesto para ver recomendaciones.</p>
                <?php elseif (!$recommendations): ?>
                    <p style="color:#666;margin:0;">No hay packs que encajen con esta selección (todavía).</p>
                <?php else: ?>
                    <ul style="margin:0 0 0 18px;">
                        <?php foreach ($recommendations as $pid):
                            $p = wc_get_product($pid);
                            if (!$p) continue;
                            ?>
                            <li style="margin:6px 0;">
                                <a href="<?php echo esc_url(get_permalink($pid)); ?>">
                                    <?php echo esc_html($p->get_name()); ?>
                                </a>
                                — <strong><?php echo wp_kses_post(wc_price((float) $p->get_price())); ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div style="margin-top:14px;">
                <h4 style="margin:0 0 8px 0;">Mejoras recomendadas</h4>

                <?php if (empty($upsells)): ?>
                    <p style="color:#666;margin:0;">No hay mejoras recomendadas para esta selección.</p>
                <?php else: ?>
                    <ul style="margin:0 0 0 18px;">
                        <?php foreach ($upsells as $u): ?>
                            <li style="margin:8px 0;">
                                <strong><?php echo esc_html($u['title']); ?></strong><br/>
                                <span style="color:#666;"><?php echo esc_html($u['description']); ?></span><br/>

                                <?php if ($u['type'] === 'pack_upgrade' && !empty($u['url'])): ?>
                                    <a href="<?php echo esc_url($u['url']); ?>"><?php echo esc_html($u['cta']); ?></a>

                                <?php elseif ($u['type'] === 'product' && !empty($u['product_id'])): ?>
                                    <?php
                                    $addUrl = add_query_arg([
                                        'add-to-cart' => (int) $u['product_id'],
                                        'quantity' => 1,
                                        'bressol_gs_return' => $currentUrl,
                                    ], $currentUrl);
                                    ?>
                                    <a class="bressol-upsell-add"
                                       data-upsell-type="product"
                                       data-upsell-id="<?php echo esc_attr((string) $u['product_id']); ?>"
                                       href="<?php echo esc_url($addUrl); ?>">
                                        <?php echo esc_html($u['cta']); ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color:#999;"><?php echo esc_html($u['cta']); ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <script>
            document.addEventListener('click', function(e) {
            const a = e.target.closest('a.bressol-upsell-add');
            if (!a) return;

            // Interceptamos para asegurar el push antes de navegar
            e.preventDefault();

            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({
                event: 'guided_upsell_add_to_cart',
                upsell_type: a.dataset.upsellType || null,
                upsell_id: a.dataset.upsellId || null,
                source: 'guided_shopping'
            });

            // Navegamos después del push (suficiente en la práctica)
            window.location.href = a.href;
            }, { capture: true });
            </script>

        </div>
        <?php
        return (string) ob_get_clean();
    }
}