<?php
declare(strict_types=1);

namespace Bressol\Modules\GuidedShopping\Frontend;

use Bressol\Modules\GuidedShopping\Wizard\WizardSession;
use Bressol\Modules\GuidedShopping\Wizard\PackRecommender;

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
                $occasion = isset($_POST['occasion']) ? sanitize_text_field((string) $_POST['occasion']) : '';
                $budget   = isset($_POST['budget']) ? sanitize_text_field((string) $_POST['budget']) : '';
                $recommender = new PackRecommender();
                $recommendations = $recommender->recommend($budget);

                if ($occasion !== '') {
                    $session->set('occasion', $occasion);
                }
                if ($budget !== '') {
                    $session->set('budget', $budget);
                }
            }
        }

        $occasion = $session->get('occasion', '');
        $budget   = $session->get('budget', '');

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
                </ul>
            </div>

            <div style="margin-top:12px;">
                <h4 style="margin:0 0 8px 0;">Recomendaciones</h4>

                <?php if (!$budget): ?>
                    <p style="color:#666;margin:0;">Elige un presupuesto para ver recomendaciones.</p>
                <?php elseif (!$recommendations): ?>
                    <p style="color:#666;margin:0;">No hay packs que encajen con este presupuesto (todavía).</p>
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
        </div>
        <?php
        return (string) ob_get_clean();
    }
}