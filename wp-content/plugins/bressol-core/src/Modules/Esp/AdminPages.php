<?php
declare(strict_types=1);

namespace Bressol\Modules\Esp;

if (!defined('ABSPATH')) {
    exit;
}

final class AdminPages
{
    private const MANUAL_EMAIL_LANGUAGES = [
        'va' => 'Valenciano',
        'es' => 'Castellano',
        'nl' => 'Neerlandés',
        'en' => 'Inglés',
    ];
    private const TEMPLATE_LANGUAGES = [
        'va' => 'Valenciano',
        'es' => 'Castellano',
        'nl' => 'Neerlandés',
        'en' => 'Inglés',
    ];
    private const SNAPSHOT_DEFAULT_LANGUAGE = 'es';
    private const MANUAL_EMAIL_TYPES = [
        'post_purchase' => 'Post-compra',
        'shipping' => 'Envío / delivery',
    ];

    public static function renderOverview(): void
    {
        self::renderHeader('Bressol ESP');
        echo '<p>Panel general del ESP interno. Aquí agruparemos campañas, plantillas, segmentos y métricas.</p>';
        self::renderFooter();
    }

    public static function renderCampaigns(): void
    {
        self::renderHeader('Campañas');
        self::handleCampaignDelete();
        self::handleCampaignSubmit();

        echo '<p>Gestión de campañas multilingües (va/es/nl/en), con snapshot de audiencia al enviar.</p>';
        self::renderCampaignsTable();
        self::renderCampaignForm();
        self::renderFooter();
    }

    public static function renderTemplates(): void
    {
        self::renderHeader('Plantillas');
        self::handleTemplateDelete();
        self::handleTemplateSubmit();

        echo '<p>Plantillas HTML responsive. Se requieren 4 versiones obligatorias: va, es, nl, en.</p>';
        self::renderTemplatesTable();
        self::renderTemplateForm();
        self::renderFooter();
    }

    public static function renderSegments(): void
    {
        self::renderHeader('Segmentos');
        self::handleSegmentDelete();
        self::handleSegmentSubmit();

        echo '<p>Segmentación avanzada basada en datos de WooCommerce.</p>';
        self::renderSegmentsTable();
        self::renderSegmentForm();
        self::renderFooter();
    }

    public static function renderManualEmails(): void
    {
        self::renderHeader('Emails manuales');
        self::handleManualEmailSubmit();

        echo '<p>Emails 1-a-1 para post-compra y envío. Un solo idioma por email, elegido manualmente.</p>';
        self::renderManualEmailForm();
        self::renderManualEmailsTable();
        self::renderFooter();
    }

    public static function renderMetrics(): void
    {
        self::renderHeader('Métricas');
        self::handleMetricsExport();

        echo '<p>Métricas de aperturas, clicks y bajas con gráficos. Aplica a campañas y emails manuales.</p>';
        self::renderMetricsStyles();
        self::renderManualEmailMetrics();
        self::renderCampaignMetrics();
        self::renderFooter();
    }

    public static function renderConsents(): void
    {
        self::renderHeader('Consentimientos');
        self::handleConsentsAction();
        self::handleConsentsSubmit();
        echo '<p>Gestión de consentimientos y lista de exclusión para cumplimiento RGPD/LSSI.</p>';
        self::renderConsentsForm();
        self::renderConsentsTable();
        self::renderFooter();
    }

    public static function renderExports(): void
    {
        self::renderHeader('Exportaciones');
        echo '<p>Exportación de métricas y eventos en CSV.</p>';
        self::renderFooter();
    }

    public static function renderQueue(): void
    {
        self::renderHeader('Cola de envíos');
        self::handleQueueSubmit();
        self::handleQueueSchedule();

        echo '<p>Gestión de la cola de envíos para campañas y emails manuales.</p>';
        self::renderQueueForm();
        self::renderQueueTable();
        self::renderFooter();
    }

    public static function renderSettings(): void
    {
        self::renderHeader('Configuración SMTP');
        self::handleSmtpSettingsSubmit();
        echo '<p>Configuración del servidor SMTP propio (perfil único) y límites de envío.</p>';
        self::renderSmtpSettingsForm();
        self::renderFooter();
    }

    public static function renderAudit(): void
    {
        self::renderHeader('Auditoría');
        echo '<p>Auditoría de acciones: quién crea, edita y envía campañas o emails manuales.</p>';
        self::renderAuditTable();
        self::renderFooter();
    }

    private static function renderHeader(string $title): void
    {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html($title) . '</h1>';
    }

    private static function renderFooter(): void
    {
        echo '</div>';
    }

    private static function handleSmtpSettingsSubmit(): void
    {
        if (!isset($_POST['bressol_esp_smtp_submit'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }

        check_admin_referer('bressol_esp_smtp_settings');

        $host = isset($_POST['smtp_host']) ? sanitize_text_field(wp_unslash($_POST['smtp_host'])) : '';
        $port = isset($_POST['smtp_port']) ? absint($_POST['smtp_port']) : 0;
        $username = isset($_POST['smtp_username']) ? sanitize_text_field(wp_unslash($_POST['smtp_username'])) : '';
        $password = isset($_POST['smtp_password']) ? sanitize_text_field(wp_unslash($_POST['smtp_password'])) : '';
        $encryption = isset($_POST['smtp_encryption']) ? sanitize_text_field(wp_unslash($_POST['smtp_encryption'])) : '';
        $fromEmail = isset($_POST['smtp_from_email']) ? sanitize_email(wp_unslash($_POST['smtp_from_email'])) : '';
        $fromName = isset($_POST['smtp_from_name']) ? sanitize_text_field(wp_unslash($_POST['smtp_from_name'])) : '';

        if ($host === '' || $port === 0 || $fromEmail === '') {
            echo '<div class="notice notice-error"><p>Host, puerto y email remitente son obligatorios.</p></div>';
            return;
        }

        $allowedEncryption = ['none', 'ssl', 'tls'];
        if (!in_array($encryption, $allowedEncryption, true)) {
            $encryption = 'none';
        }

        update_option('bressol_esp_smtp', [
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password' => $password,
            'encryption' => $encryption,
            'from_email' => $fromEmail,
            'from_name' => $fromName,
        ]);

        echo '<div class="notice notice-success"><p>Configuración SMTP guardada.</p></div>';
    }

    private static function renderSmtpSettingsForm(): void
    {
        $settings = get_option('bressol_esp_smtp', []);
        $host = $settings['host'] ?? '';
        $port = $settings['port'] ?? '';
        $username = $settings['username'] ?? '';
        $password = $settings['password'] ?? '';
        $encryption = $settings['encryption'] ?? 'none';
        $fromEmail = $settings['from_email'] ?? '';
        $fromName = $settings['from_name'] ?? '';

        echo '<hr />';
        echo '<h2>Credenciales SMTP</h2>';
        echo '<form method="post">';
        wp_nonce_field('bressol_esp_smtp_settings');

        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="smtp_host">Host</label></th>';
        echo '<td><input type="text" name="smtp_host" id="smtp_host" class="regular-text" value="' . esc_attr($host) . '" required></td></tr>';

        echo '<tr><th scope="row"><label for="smtp_port">Puerto</label></th>';
        echo '<td><input type="number" name="smtp_port" id="smtp_port" class="regular-text" value="' . esc_attr((string) $port) . '" required></td></tr>';

        echo '<tr><th scope="row"><label for="smtp_username">Usuario</label></th>';
        echo '<td><input type="text" name="smtp_username" id="smtp_username" class="regular-text" value="' . esc_attr($username) . '"></td></tr>';

        echo '<tr><th scope="row"><label for="smtp_password">Contraseña</label></th>';
        echo '<td><input type="password" name="smtp_password" id="smtp_password" class="regular-text" value="' . esc_attr($password) . '"></td></tr>';

        echo '<tr><th scope="row"><label for="smtp_encryption">Cifrado</label></th><td>';
        echo '<select name="smtp_encryption" id="smtp_encryption">';
        foreach (['none' => 'Sin cifrado', 'ssl' => 'SSL', 'tls' => 'TLS'] as $value => $label) {
            $selected = selected($encryption, $value, false);
            echo '<option value="' . esc_attr($value) . '"' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th scope="row"><label for="smtp_from_email">Email remitente</label></th>';
        echo '<td><input type="email" name="smtp_from_email" id="smtp_from_email" class="regular-text" value="' . esc_attr($fromEmail) . '" required></td></tr>';

        echo '<tr><th scope="row"><label for="smtp_from_name">Nombre remitente</label></th>';
        echo '<td><input type="text" name="smtp_from_name" id="smtp_from_name" class="regular-text" value="' . esc_attr($fromName) . '"></td></tr>';
        echo '</tbody></table>';

        echo '<p class="submit"><button type="submit" name="bressol_esp_smtp_submit" class="button button-primary">Guardar configuración</button></p>';
        echo '</form>';
    }

    private static function handleManualEmailSubmit(): void
    {
        if (!isset($_POST['bressol_esp_manual_email_submit'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }

        check_admin_referer('bressol_esp_manual_email');

        $orderId = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $emailType = isset($_POST['email_type']) ? sanitize_text_field(wp_unslash($_POST['email_type'])) : '';
        $subject = isset($_POST['subject']) ? sanitize_text_field(wp_unslash($_POST['subject'])) : '';
        $htmlBody = isset($_POST['html_body']) ? wp_kses_post(wp_unslash($_POST['html_body'])) : '';
        $textBody = isset($_POST['text_body']) ? sanitize_textarea_field(wp_unslash($_POST['text_body'])) : '';
        $giftBlock = isset($_POST['gift_block']) ? wp_kses_post(wp_unslash($_POST['gift_block'])) : '';
        $language = isset($_POST['language']) ? sanitize_text_field(wp_unslash($_POST['language'])) : 'es';

        if (!$orderId || !$emailType || !$subject || !$htmlBody) {
            echo '<div class="notice notice-error"><p>Faltan campos obligatorios (pedido, tipo, asunto o cuerpo HTML).</p></div>';
            return;
        }

        if (!array_key_exists($emailType, self::MANUAL_EMAIL_TYPES)) {
            echo '<div class="notice notice-error"><p>Tipo de email no válido.</p></div>';
            return;
        }

        if (!array_key_exists($language, self::MANUAL_EMAIL_LANGUAGES)) {
            $language = 'es';
        }

        if (!function_exists('wc_get_order')) {
            echo '<div class="notice notice-error"><p>WooCommerce no está disponible.</p></div>';
            return;
        }

        $order = wc_get_order($orderId);
        if (!$order) {
            echo '<div class="notice notice-error"><p>No se encontró el pedido indicado.</p></div>';
            return;
        }

        $recipientEmail = $order->get_billing_email();
        if (!$recipientEmail) {
            echo '<div class="notice notice-error"><p>El pedido no tiene email de facturación.</p></div>';
            return;
        }

        global $wpdb;
        $consentsTable = $wpdb->prefix . 'bressol_esp_consents';
        $table = $wpdb->prefix . 'bressol_esp_manual_emails';

        $consentStatus = $wpdb->get_var(
            $wpdb->prepare("SELECT status FROM {$consentsTable} WHERE email = %s LIMIT 1", $recipientEmail)
        );

        if ($consentStatus === 'opt_out') {
            echo '<div class="notice notice-error"><p>El destinatario está en la lista de exclusión (opt-out).</p></div>';
            return;
        }

        if ($emailType === 'post_purchase' && $giftBlock !== '') {
            $htmlBody .= '<hr />' . $giftBlock;
            if ($textBody !== '') {
                $textBody .= "\n\n" . wp_strip_all_tags($giftBlock);
            }
        }

        $trackingKey = wp_generate_password(32, false, false);
        $htmlBody = self::rewriteLinksForClickTracking($htmlBody, $trackingKey);
        $htmlBody = self::appendOpenTrackingPixel($htmlBody, $trackingKey);
        [$htmlBody, $textBody] = self::appendUnsubscribeLinks($htmlBody, $textBody, $trackingKey);

        $inserted = $wpdb->insert(
            $table,
            [
                'order_id' => $orderId,
                'user_id' => get_current_user_id(),
                'email_type' => $emailType,
                'recipient_email' => $recipientEmail,
                'subject' => $subject,
                'html_body' => $htmlBody,
                'text_body' => $textBody,
                'gift_block' => $giftBlock,
                'metadata' => null,
                'tracking_key' => $trackingKey,
                'language' => $language,
                'created_at' => current_time('mysql'),
                'sent_at' => null,
            ],
            [
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            ]
        );

        if ($inserted === false) {
            echo '<div class="notice notice-error"><p>No se pudo guardar el email manual.</p></div>';
            return;
        }

        self::insertAuditLog(
            'manual_email_created',
            'manual_email',
            $wpdb->insert_id,
            [
                'order_id' => $orderId,
                'email_type' => $emailType,
                'recipient_email' => $recipientEmail,
            ]
        );

        $manualEmailId = (int) $wpdb->insert_id;
        $queueTable = $wpdb->prefix . 'bressol_esp_send_queue';
        $jobsTable = $wpdb->prefix . 'bressol_esp_send_queue_jobs';
        $now = current_time('mysql');

        $queueInserted = $wpdb->insert(
            $queueTable,
            [
                'campaign_id' => null,
                'manual_email_id' => $manualEmailId,
                'recipient_email' => $recipientEmail,
                'status' => 'pending',
                'attempts' => 0,
                'scheduled_at' => $now,
                'sent_at' => null,
                'last_error' => null,
                'created_at' => $now,
            ]
        );

        if ($queueInserted === false) {
            echo '<div class="notice notice-error"><p>El email se guardó, pero no se pudo encolar.</p></div>';
            return;
        }

        $queueId = (int) $wpdb->insert_id;
        $jobInserted = $wpdb->insert(
            $jobsTable,
            [
                'queue_id' => $queueId,
                'status' => 'scheduled',
                'run_at' => $now,
                'attempts' => 0,
                'last_error' => null,
                'created_at' => $now,
                'updated_at' => null,
            ],
            [
                '%d',
                '%s',
                '%s',
                '%d',
                '%s',
                '%s',
                '%s',
            ]
        );

        if ($jobInserted === false) {
            echo '<div class="notice notice-error"><p>El email se guardó, pero no se pudo programar el envío.</p></div>';
            return;
        }

        self::insertAuditLog(
            'manual_email_queued',
            'manual_email',
            $manualEmailId,
            [
                'order_id' => $orderId,
                'recipient_email' => $recipientEmail,
                'queue_id' => $queueId,
            ]
        );

        echo '<div class="notice notice-success"><p>Email manual guardado y encolado para envío.</p></div>';
    }

    private static function renderManualEmailForm(): void
    {
        $currentLanguage = isset($_POST['language']) ? sanitize_text_field(wp_unslash($_POST['language'])) : 'es';
        $currentLanguage = array_key_exists($currentLanguage, self::MANUAL_EMAIL_LANGUAGES) ? $currentLanguage : 'es';

        echo '<hr />';
        echo '<h2>Crear email manual</h2>';
        echo '<form method="post">';
        wp_nonce_field('bressol_esp_manual_email');

        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="order_id">ID de pedido</label></th>';
        echo '<td><input type="number" name="order_id" id="order_id" class="regular-text" required></td></tr>';

        echo '<tr><th scope="row"><label for="email_type">Tipo de email</label></th><td>';
        echo '<select name="email_type" id="email_type" required>';
        echo '<option value="">Selecciona un tipo</option>';
        foreach (self::MANUAL_EMAIL_TYPES as $value => $label) {
            echo '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th scope="row"><label for="language">Idioma</label></th><td>';
        echo '<select name="language" id="language">';
        foreach (self::MANUAL_EMAIL_LANGUAGES as $value => $label) {
            $selected = selected($currentLanguage, $value, false);
            echo '<option value="' . esc_attr($value) . '"' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th scope="row"><label for="subject">Asunto</label></th>';
        echo '<td><input type="text" name="subject" id="subject" class="regular-text" required></td></tr>';

        echo '<tr><th scope="row"><label for="html_body">Cuerpo HTML</label></th>';
        echo '<td><textarea name="html_body" id="html_body" class="large-text" rows="10" required></textarea></td></tr>';

        echo '<tr><th scope="row"><label for="text_body">Texto plano</label></th>';
        echo '<td><textarea name="text_body" id="text_body" class="large-text" rows="6"></textarea></td></tr>';

        echo '<tr><th scope="row"><label for="gift_block">Bloque regalo (opcional)</label></th>';
        echo '<td><textarea name="gift_block" id="gift_block" class="large-text" rows="4"></textarea></td></tr>';
        echo '</tbody></table>';

        echo '<p class="submit"><button type="submit" name="bressol_esp_manual_email_submit" class="button button-primary">Guardar y enviar email</button></p>';
        echo '</form>';
    }

    private static function renderManualEmailsTable(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bressol_esp_manual_emails';
        $items = $wpdb->get_results("SELECT id, order_id, email_type, recipient_email, language, subject, created_at, sent_at FROM {$table} ORDER BY created_at DESC LIMIT 10");

        echo '<h2>Últimos emails manuales</h2>';

        if (empty($items)) {
            echo '<p>No hay emails manuales guardados todavía.</p>';
            return;
        }

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>ID</th><th>Pedido</th><th>Tipo</th><th>Email</th><th>Idioma</th><th>Asunto</th><th>Creado</th><th>Enviado</th>';
        echo '</tr></thead><tbody>';

        foreach ($items as $item) {
            $typeLabel = self::MANUAL_EMAIL_TYPES[$item->email_type] ?? $item->email_type;
            $languageLabel = self::MANUAL_EMAIL_LANGUAGES[$item->language] ?? $item->language;
            echo '<tr>';
            echo '<td>' . esc_html((string) $item->id) . '</td>';
            echo '<td>' . esc_html((string) $item->order_id) . '</td>';
            echo '<td>' . esc_html($typeLabel) . '</td>';
            echo '<td>' . esc_html($item->recipient_email) . '</td>';
            echo '<td>' . esc_html($languageLabel) . '</td>';
            echo '<td>' . esc_html($item->subject) . '</td>';
            echo '<td>' . esc_html($item->created_at) . '</td>';
            echo '<td>' . esc_html($item->sent_at ?? '-') . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private static function handleTemplateSubmit(): void
    {
        if (!isset($_POST['bressol_esp_template_submit'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }

        check_admin_referer('bressol_esp_template');

        $templateId = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $language = isset($_POST['language']) ? sanitize_text_field(wp_unslash($_POST['language'])) : 'es';
        $html = isset($_POST['html']) ? wp_kses_post(wp_unslash($_POST['html'])) : '';
        $textPlain = isset($_POST['text_plain']) ? sanitize_textarea_field(wp_unslash($_POST['text_plain'])) : '';

        if ($name === '' || $html === '') {
            echo '<div class="notice notice-error"><p>Faltan campos obligatorios (nombre o HTML).</p></div>';
            return;
        }

        if (!array_key_exists($language, self::TEMPLATE_LANGUAGES)) {
            $language = 'es';
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bressol_esp_templates';

        $data = [
            'name' => $name,
            'html' => $html,
            'text_plain' => $textPlain,
            'language' => $language,
            'created_at' => current_time('mysql'),
        ];

        $format = [
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
        ];

        if ($templateId > 0) {
            $updated = $wpdb->update(
                $table,
                [
                    'name' => $name,
                    'html' => $html,
                    'text_plain' => $textPlain,
                    'language' => $language,
                ],
                [
                    'id' => $templateId,
                ],
                [
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                ],
                [
                    '%d',
                ]
            );

            if ($updated === false) {
                echo '<div class="notice notice-error"><p>No se pudo actualizar la plantilla.</p></div>';
                return;
            }

            echo '<div class="notice notice-success"><p>Plantilla actualizada.</p></div>';
            return;
        }

        $inserted = $wpdb->insert($table, $data, $format);
        if ($inserted === false) {
            echo '<div class="notice notice-error"><p>No se pudo crear la plantilla.</p></div>';
            return;
        }

        echo '<div class="notice notice-success"><p>Plantilla creada.</p></div>';
    }

    private static function handleSegmentSubmit(): void
    {
        if (!isset($_POST['bressol_esp_segment_submit'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }

        check_admin_referer('bressol_esp_segment');

        $segmentId = isset($_POST['segment_id']) ? absint($_POST['segment_id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $rules = isset($_POST['rules_json']) ? wp_unslash($_POST['rules_json']) : '';
        $builderConfig = isset($_POST['builder_config']) ? wp_unslash($_POST['builder_config']) : '';
        $minTotalSpent = isset($_POST['min_total_spent']) ? floatval(wp_unslash($_POST['min_total_spent'])) : null;
        $minOrderCount = isset($_POST['min_order_count']) ? absint($_POST['min_order_count']) : null;
        $lastOrderDays = isset($_POST['last_order_days']) ? absint($_POST['last_order_days']) : null;
        $productIdsRaw = isset($_POST['product_ids']) ? sanitize_text_field(wp_unslash($_POST['product_ids'])) : '';
        $categoryIdsRaw = isset($_POST['category_ids']) ? sanitize_text_field(wp_unslash($_POST['category_ids'])) : '';
        $productIds = self::parseIdList($productIdsRaw);
        $categoryIds = self::parseIdList($categoryIdsRaw);

        if ($name === '' || ($rules === '' && $minTotalSpent === null && $minOrderCount === null && $lastOrderDays === null && empty($productIds) && empty($categoryIds))) {
            echo '<div class="notice notice-error"><p>Faltan campos obligatorios (nombre o reglas).</p></div>';
            return;
        }

        if ($minTotalSpent !== null || $minOrderCount !== null || $lastOrderDays !== null || !empty($productIds) || !empty($categoryIds)) {
            $rulesPayload = [
                'min_total_spent' => $minTotalSpent !== null ? $minTotalSpent : null,
                'min_order_count' => $minOrderCount !== null ? $minOrderCount : null,
                'last_order_days' => $lastOrderDays !== null ? $lastOrderDays : null,
                'product_ids' => !empty($productIds) ? $productIds : null,
                'category_ids' => !empty($categoryIds) ? $categoryIds : null,
            ];
            $rules = wp_json_encode(array_filter($rulesPayload, static fn ($value) => $value !== null));
            $builderConfig = $rules;
        }

        $rules = wp_check_invalid_utf8($rules, true) ? $rules : '';
        $builderConfig = wp_check_invalid_utf8($builderConfig, true) ? $builderConfig : '';

        global $wpdb;
        $table = $wpdb->prefix . 'bressol_esp_segments';

        if ($segmentId > 0) {
            $updated = $wpdb->update(
                $table,
                [
                    'name' => $name,
                    'rules_json' => $rules,
                    'builder_config' => $builderConfig,
                ],
                [
                    'id' => $segmentId,
                ],
                [
                    '%s',
                    '%s',
                    '%s',
                ],
                [
                    '%d',
                ]
            );

            if ($updated === false) {
                echo '<div class="notice notice-error"><p>No se pudo actualizar el segmento.</p></div>';
                return;
            }

            echo '<div class="notice notice-success"><p>Segmento actualizado.</p></div>';
            return;
        }

        $inserted = $wpdb->insert(
            $table,
            [
                'name' => $name,
                'rules_json' => $rules,
                'builder_config' => $builderConfig,
                'created_at' => current_time('mysql'),
            ],
            [
                '%s',
                '%s',
                '%s',
                '%s',
            ]
        );

        if ($inserted === false) {
            echo '<div class="notice notice-error"><p>No se pudo crear el segmento.</p></div>';
            return;
        }

        echo '<div class="notice notice-success"><p>Segmento creado.</p></div>';
    }

    private static function handleSegmentDelete(): void
    {
        if (!isset($_GET['bressol_esp_segment_delete'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }

        check_admin_referer('bressol_esp_segment_delete');

        $segmentId = absint($_GET['bressol_esp_segment_delete']);
        if ($segmentId === 0) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bressol_esp_segments';
        $wpdb->delete($table, ['id' => $segmentId], ['%d']);

        echo '<div class="notice notice-success"><p>Segmento eliminado.</p></div>';
    }

    private static function handleCampaignSubmit(): void
    {
        if (!isset($_POST['bressol_esp_campaign_submit'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }

        check_admin_referer('bressol_esp_campaign');

        $campaignId = isset($_POST['campaign_id']) ? absint($_POST['campaign_id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $type = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : '';
        $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';
        $templateId = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;
        $segmentId = isset($_POST['segment_id']) ? absint($_POST['segment_id']) : 0;
        $scheduledAt = isset($_POST['scheduled_at']) ? sanitize_text_field(wp_unslash($_POST['scheduled_at'])) : '';
        $audienceEmails = isset($_POST['audience_emails']) ? sanitize_textarea_field(wp_unslash($_POST['audience_emails'])) : '';
        $defaultLanguage = isset($_POST['default_language']) ? sanitize_text_field(wp_unslash($_POST['default_language'])) : self::SNAPSHOT_DEFAULT_LANGUAGE;
        $templateSelections = isset($_POST['template_language']) && is_array($_POST['template_language'])
            ? array_map('absint', wp_unslash($_POST['template_language']))
            : [];

        if ($name === '' || $type === '' || $status === '') {
            echo '<div class="notice notice-error"><p>Faltan campos obligatorios (nombre, tipo o estado).</p></div>';
            return;
        }

        $scheduledAt = $scheduledAt !== '' ? $scheduledAt : null;

        if (!array_key_exists($defaultLanguage, self::TEMPLATE_LANGUAGES)) {
            $defaultLanguage = self::SNAPSHOT_DEFAULT_LANGUAGE;
        }

        $templateMap = [];
        foreach (self::TEMPLATE_LANGUAGES as $lang => $label) {
            if (!empty($templateSelections[$lang])) {
                $templateMap[$lang] = $templateSelections[$lang];
            }
        }

        $templateSnapshot = wp_json_encode([
            'templates' => $templateMap,
            'default_language' => $defaultLanguage,
        ]);

        if (isset($templateMap[$defaultLanguage])) {
            $templateId = (int) $templateMap[$defaultLanguage];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bressol_esp_campaigns';

        $data = [
            'name' => $name,
            'type' => $type,
            'status' => $status,
            'template_id' => $templateId ?: null,
            'segment_id' => $segmentId ?: null,
            'scheduled_at' => $scheduledAt,
            'template_snapshot' => $templateSnapshot,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];

        $format = [
            '%s',
            '%s',
            '%s',
            '%d',
            '%d',
            '%s',
            '%s',
            '%s',
            '%s',
        ];

        if ($campaignId > 0) {
            $updated = $wpdb->update(
                $table,
                [
                    'name' => $name,
                    'type' => $type,
                    'status' => $status,
                    'template_id' => $templateId ?: null,
                    'segment_id' => $segmentId ?: null,
                    'scheduled_at' => $scheduledAt,
                    'template_snapshot' => $templateSnapshot,
                    'updated_at' => current_time('mysql'),
                ],
                [
                    'id' => $campaignId,
                ],
                [
                    '%s',
                    '%s',
                    '%s',
                    '%d',
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                ],
                [
                    '%d',
                ]
            );

            if ($updated === false) {
                echo '<div class="notice notice-error"><p>No se pudo actualizar la campaña.</p></div>';
                return;
            }

            echo '<div class="notice notice-success"><p>Campaña actualizada.</p></div>';
            self::maybeBuildAudienceSnapshot($campaignId, $status, $scheduledAt, $audienceEmails);
            return;
        }

        $inserted = $wpdb->insert($table, $data, $format);
        if ($inserted === false) {
            echo '<div class="notice notice-error"><p>No se pudo crear la campaña.</p></div>';
            return;
        }

        echo '<div class="notice notice-success"><p>Campaña creada.</p></div>';
        self::maybeBuildAudienceSnapshot((int) $wpdb->insert_id, $status, $scheduledAt, $audienceEmails);
    }

    private static function handleCampaignDelete(): void
    {
        if (!isset($_GET['bressol_esp_campaign_delete'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }

        check_admin_referer('bressol_esp_campaign_delete');

        $campaignId = absint($_GET['bressol_esp_campaign_delete']);
        if ($campaignId === 0) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bressol_esp_campaigns';
        $wpdb->delete($table, ['id' => $campaignId], ['%d']);

        echo '<div class="notice notice-success"><p>Campaña eliminada.</p></div>';
    }

    private static function handleQueueSubmit(): void
    {
        if (!isset($_POST['bressol_esp_queue_submit'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }

        check_admin_referer('bressol_esp_queue');

        $campaignId = isset($_POST['campaign_id']) ? absint($_POST['campaign_id']) : 0;
        $manualEmailId = isset($_POST['manual_email_id']) ? absint($_POST['manual_email_id']) : 0;
        $recipientEmail = isset($_POST['recipient_email']) ? sanitize_email(wp_unslash($_POST['recipient_email'])) : '';
        $scheduledAt = isset($_POST['scheduled_at']) ? sanitize_text_field(wp_unslash($_POST['scheduled_at'])) : '';

        if ($recipientEmail === '') {
            echo '<div class="notice notice-error"><p>El email del destinatario es obligatorio.</p></div>';
            return;
        }

        if ($campaignId === 0 && $manualEmailId === 0) {
            echo '<div class="notice notice-error"><p>Debes indicar una campaña o un email manual.</p></div>';
            return;
        }

        $scheduledAt = $scheduledAt !== '' ? $scheduledAt : current_time('mysql');

        global $wpdb;
        $queueTable = $wpdb->prefix . 'bressol_esp_send_queue';

        $inserted = $wpdb->insert(
            $queueTable,
            [
                'campaign_id' => $campaignId ?: null,
                'manual_email_id' => $manualEmailId ?: null,
                'recipient_email' => $recipientEmail,
                'status' => 'pending',
                'attempts' => 0,
                'scheduled_at' => $scheduledAt,
                'sent_at' => null,
                'last_error' => null,
                'created_at' => current_time('mysql'),
            ],
            [
                '%d',
                '%d',
                '%s',
                '%s',
                '%d',
                '%s',
                '%s',
                '%s',
            ]
        );

        if ($inserted === false) {
            echo '<div class="notice notice-error"><p>No se pudo encolar el envío.</p></div>';
            return;
        }

        echo '<div class="notice notice-success"><p>Elemento añadido a la cola de envíos.</p></div>';
    }

    private static function handleQueueSchedule(): void
    {
        if (!isset($_POST['bressol_esp_queue_schedule'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }

        check_admin_referer('bressol_esp_queue_schedule');

        global $wpdb;
        $queueTable = $wpdb->prefix . 'bressol_esp_send_queue';
        $jobsTable = $wpdb->prefix . 'bressol_esp_send_queue_jobs';

        $queueId = isset($_POST['queue_id']) ? absint($_POST['queue_id']) : 0;
        $runAt = isset($_POST['run_at']) ? sanitize_text_field(wp_unslash($_POST['run_at'])) : '';

        if ($queueId === 0 || $runAt === '') {
            echo '<div class="notice notice-error"><p>Falta el ID de cola o la fecha de ejecución.</p></div>';
            return;
        }

        $queue = $wpdb->get_row(
            $wpdb->prepare("SELECT id FROM {$queueTable} WHERE id = %d", $queueId)
        );

        if (!$queue) {
            echo '<div class="notice notice-error"><p>No se encontró el item de cola.</p></div>';
            return;
        }

        $inserted = $wpdb->insert(
            $jobsTable,
            [
                'queue_id' => $queueId,
                'status' => 'scheduled',
                'run_at' => $runAt,
                'attempts' => 0,
                'last_error' => null,
                'created_at' => current_time('mysql'),
                'updated_at' => null,
            ],
            [
                '%d',
                '%s',
                '%s',
                '%d',
                '%s',
                '%s',
                '%s',
            ]
        );

        if ($inserted === false) {
            echo '<div class="notice notice-error"><p>No se pudo crear el job de ejecución.</p></div>';
            return;
        }

        echo '<div class="notice notice-success"><p>Job de ejecución programado.</p></div>';
    }

    private static function handleTemplateDelete(): void
    {
        if (!isset($_GET['bressol_esp_template_delete'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }

        check_admin_referer('bressol_esp_template_delete');

        $templateId = absint($_GET['bressol_esp_template_delete']);
        if ($templateId === 0) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bressol_esp_templates';
        $wpdb->delete($table, ['id' => $templateId], ['%d']);

        echo '<div class="notice notice-success"><p>Plantilla eliminada.</p></div>';
    }

    private static function renderSegmentsTable(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bressol_esp_segments';
        $items = $wpdb->get_results("SELECT id, name, created_at FROM {$table} ORDER BY created_at DESC LIMIT 50");

        echo '<h2>Segmentos existentes</h2>';

        if (empty($items)) {
            echo '<p>No hay segmentos creados todavía.</p>';
            return;
        }

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>ID</th><th>Nombre</th><th>Creado</th><th>Acciones</th>';
        echo '</tr></thead><tbody>';

        foreach ($items as $item) {
            $deleteUrl = wp_nonce_url(
                add_query_arg(['bressol_esp_segment_delete' => $item->id], menu_page_url('bressol-esp-segments', false)),
                'bressol_esp_segment_delete'
            );
            $editUrl = add_query_arg(['segment_id' => $item->id], menu_page_url('bressol-esp-segments', false));

            echo '<tr>';
            echo '<td>' . esc_html((string) $item->id) . '</td>';
            echo '<td>' . esc_html($item->name) . '</td>';
            echo '<td>' . esc_html($item->created_at) . '</td>';
            echo '<td>';
            echo '<a href="' . esc_url($editUrl) . '">Editar</a> | ';
            echo '<a href="' . esc_url($deleteUrl) . '" onclick="return confirm(\'¿Eliminar este segmento?\')">Eliminar</a>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private static function renderSegmentForm(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bressol_esp_segments';
        $segmentId = isset($_GET['segment_id']) ? absint($_GET['segment_id']) : 0;

        $name = '';
        $rules = '';
        $builderConfig = '';
        $rulesPreview = '';
        $builderConfigPreview = '';
        $productIds = '';
        $categoryIds = '';
        $formTitle = 'Crear segmento';

        if ($segmentId > 0) {
            $segment = $wpdb->get_row(
                $wpdb->prepare("SELECT id, name, rules_json, builder_config FROM {$table} WHERE id = %d", $segmentId)
            );
            if ($segment) {
                $name = $segment->name;
                $rules = $segment->rules_json;
                $builderConfig = $segment->builder_config ?? '';
                $rulesPreview = $rules;
                $builderConfigPreview = $builderConfig;
                $decodedRules = json_decode((string) $rules, true);
                if (is_array($decodedRules)) {
                    if (!empty($decodedRules['product_ids']) && is_array($decodedRules['product_ids'])) {
                        $productIds = implode(',', array_map('intval', $decodedRules['product_ids']));
                    }
                    if (!empty($decodedRules['category_ids']) && is_array($decodedRules['category_ids'])) {
                        $categoryIds = implode(',', array_map('intval', $decodedRules['category_ids']));
                    }
                }
                $formTitle = 'Editar segmento';
            }
        }

        echo '<hr />';
        echo '<h2>' . esc_html($formTitle) . '</h2>';
        echo '<form method="post">';
        wp_nonce_field('bressol_esp_segment');
        echo '<input type="hidden" name="segment_id" value="' . esc_attr((string) $segmentId) . '" />';

        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="name">Nombre</label></th>';
        echo '<td><input type="text" name="name" id="name" class="regular-text" value="' . esc_attr($name) . '" required></td></tr>';

        echo '<tr><th scope="row"><label for="min_total_spent">Gasto mínimo (€)</label></th>';
        echo '<td><input type="number" step="0.01" name="min_total_spent" id="min_total_spent" class="regular-text"></td></tr>';

        echo '<tr><th scope="row"><label for="min_order_count">Pedidos mínimos</label></th>';
        echo '<td><input type="number" name="min_order_count" id="min_order_count" class="regular-text"></td></tr>';

        echo '<tr><th scope="row"><label for="last_order_days">Último pedido hace &le; (días)</label></th>';
        echo '<td><input type="number" name="last_order_days" id="last_order_days" class="regular-text"></td></tr>';

        echo '<tr><th scope="row"><label for="product_ids">IDs de productos (CSV)</label></th>';
        echo '<td><input type="text" name="product_ids" id="product_ids" class="regular-text" value="' . esc_attr($productIds) . '"></td></tr>';

        echo '<tr><th scope="row"><label for="category_ids">IDs de categorías (CSV)</label></th>';
        echo '<td><input type="text" name="category_ids" id="category_ids" class="regular-text" value="' . esc_attr($categoryIds) . '"></td></tr>';

        echo '<tr><th scope="row"><label for="rules_json">Reglas (JSON)</label></th>';
        echo '<td><textarea name="rules_json" id="rules_json" class="large-text" rows="6">' . esc_textarea($rulesPreview) . '</textarea></td></tr>';

        echo '<tr><th scope="row"><label for="builder_config">Builder config (JSON)</label></th>';
        echo '<td><textarea name="builder_config" id="builder_config" class="large-text" rows="6">' . esc_textarea($builderConfigPreview) . '</textarea></td></tr>';
        echo '</tbody></table>';

        echo '<p class="submit"><button type="submit" name="bressol_esp_segment_submit" class="button button-primary">Guardar segmento</button></p>';
        echo '</form>';
    }

    private static function renderCampaignsTable(): void
    {
        global $wpdb;
        $campaignsTable = $wpdb->prefix . 'bressol_esp_campaigns';
        $templatesTable = $wpdb->prefix . 'bressol_esp_templates';
        $segmentsTable = $wpdb->prefix . 'bressol_esp_segments';

        $items = $wpdb->get_results(
            "SELECT c.id, c.name, c.type, c.status, c.scheduled_at, c.created_at,
                    t.name AS template_name,
                    s.name AS segment_name
             FROM {$campaignsTable} c
             LEFT JOIN {$templatesTable} t ON t.id = c.template_id
             LEFT JOIN {$segmentsTable} s ON s.id = c.segment_id
             ORDER BY c.created_at DESC
             LIMIT 50"
        );

        echo '<h2>Campañas existentes</h2>';

        if (empty($items)) {
            echo '<p>No hay campañas creadas todavía.</p>';
            return;
        }

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>ID</th><th>Nombre</th><th>Tipo</th><th>Estado</th><th>Plantilla</th><th>Segmento</th><th>Programada</th><th>Creada</th><th>Acciones</th>';
        echo '</tr></thead><tbody>';

        foreach ($items as $item) {
            $deleteUrl = wp_nonce_url(
                add_query_arg(['bressol_esp_campaign_delete' => $item->id], menu_page_url('bressol-esp-campaigns', false)),
                'bressol_esp_campaign_delete'
            );
            $editUrl = add_query_arg(['campaign_id' => $item->id], menu_page_url('bressol-esp-campaigns', false));

            echo '<tr>';
            echo '<td>' . esc_html((string) $item->id) . '</td>';
            echo '<td>' . esc_html($item->name) . '</td>';
            echo '<td>' . esc_html($item->type) . '</td>';
            echo '<td>' . esc_html($item->status) . '</td>';
            echo '<td>' . esc_html($item->template_name ?? '-') . '</td>';
            echo '<td>' . esc_html($item->segment_name ?? '-') . '</td>';
            echo '<td>' . esc_html($item->scheduled_at ?? '-') . '</td>';
            echo '<td>' . esc_html($item->created_at) . '</td>';
            echo '<td>';
            echo '<a href="' . esc_url($editUrl) . '">Editar</a> | ';
            echo '<a href="' . esc_url($deleteUrl) . '" onclick="return confirm(\'¿Eliminar esta campaña?\')">Eliminar</a>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private static function renderCampaignForm(): void
    {
        global $wpdb;
        $campaignsTable = $wpdb->prefix . 'bressol_esp_campaigns';
        $templatesTable = $wpdb->prefix . 'bressol_esp_templates';
        $segmentsTable = $wpdb->prefix . 'bressol_esp_segments';

        $campaignId = isset($_GET['campaign_id']) ? absint($_GET['campaign_id']) : 0;

        $name = '';
        $type = '';
        $status = '';
        $templateId = 0;
        $segmentId = 0;
        $scheduledAt = '';
        $audienceEmails = '';
        $defaultLanguage = self::SNAPSHOT_DEFAULT_LANGUAGE;
        $templateSelections = array_fill_keys(array_keys(self::TEMPLATE_LANGUAGES), 0);
        $formTitle = 'Crear campaña';

        if ($campaignId > 0) {
            $campaign = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, name, type, status, template_id, segment_id, scheduled_at, template_snapshot
                     FROM {$campaignsTable}
                     WHERE id = %d",
                    $campaignId
                )
            );
            if ($campaign) {
                $name = $campaign->name;
                $type = $campaign->type;
                $status = $campaign->status;
                $templateId = (int) $campaign->template_id;
                $segmentId = (int) $campaign->segment_id;
                $scheduledAt = $campaign->scheduled_at ?? '';
                if ($campaign->template_snapshot) {
                    $snapshot = json_decode($campaign->template_snapshot, true);
                    if (is_array($snapshot)) {
                        $defaultLanguage = $snapshot['default_language'] ?? $defaultLanguage;
                        if (!empty($snapshot['templates']) && is_array($snapshot['templates'])) {
                            foreach ($snapshot['templates'] as $lang => $value) {
                                if (array_key_exists($lang, $templateSelections)) {
                                    $templateSelections[$lang] = (int) $value;
                                }
                            }
                        }
                    }
                }
                $formTitle = 'Editar campaña';
            }
        }

        $templates = $wpdb->get_results("SELECT id, name, language FROM {$templatesTable} ORDER BY created_at DESC");
        $segments = $wpdb->get_results("SELECT id, name FROM {$segmentsTable} ORDER BY created_at DESC");

        echo '<hr />';
        echo '<h2>' . esc_html($formTitle) . '</h2>';
        echo '<form method="post">';
        wp_nonce_field('bressol_esp_campaign');
        echo '<input type="hidden" name="campaign_id" value="' . esc_attr((string) $campaignId) . '" />';

        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="name">Nombre</label></th>';
        echo '<td><input type="text" name="name" id="name" class="regular-text" value="' . esc_attr($name) . '" required></td></tr>';

        echo '<tr><th scope="row"><label for="type">Tipo</label></th><td>';
        echo '<select name="type" id="type" required>';
        echo '<option value="">Selecciona un tipo</option>';
        foreach (['newsletter' => 'Newsletter', 'transactional' => 'Transaccional'] as $value => $label) {
            $selected = selected($type, $value, false);
            echo '<option value="' . esc_attr($value) . '"' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th scope="row"><label for="status">Estado</label></th><td>';
        echo '<select name="status" id="status" required>';
        echo '<option value="">Selecciona un estado</option>';
        foreach (['draft' => 'Borrador', 'scheduled' => 'Programada', 'sending' => 'Enviando', 'finished' => 'Finalizada'] as $value => $label) {
            $selected = selected($status, $value, false);
            echo '<option value="' . esc_attr($value) . '"' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th scope="row"><label for="template_id">Plantilla</label></th><td>';
        echo '<select name="template_id" id="template_id">';
        echo '<option value="">Sin plantilla</option>';
        foreach ($templates as $template) {
            $label = $template->name . ' (' . strtoupper((string) $template->language) . ')';
            $selected = selected($templateId, $template->id, false);
            echo '<option value="' . esc_attr((string) $template->id) . '"' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th scope="row"><label for="segment_id">Segmento</label></th><td>';
        echo '<select name="segment_id" id="segment_id">';
        echo '<option value="">Sin segmento</option>';
        foreach ($segments as $segment) {
            $selected = selected($segmentId, $segment->id, false);
            echo '<option value="' . esc_attr((string) $segment->id) . '"' . $selected . '>' . esc_html($segment->name) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th scope="row"><label for="default_language">Idioma por defecto</label></th><td>';
        echo '<select name="default_language" id="default_language">';
        foreach (self::TEMPLATE_LANGUAGES as $lang => $label) {
            $selected = selected($defaultLanguage, $lang, false);
            echo '<option value="' . esc_attr($lang) . '"' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';

        foreach (self::TEMPLATE_LANGUAGES as $lang => $label) {
            $selectedTemplate = $templateSelections[$lang] ?? 0;
            echo '<tr><th scope="row"><label for="template_language_' . esc_attr($lang) . '">Plantilla ' . esc_html($label) . '</label></th><td>';
            echo '<select name="template_language[' . esc_attr($lang) . ']" id="template_language_' . esc_attr($lang) . '">';
            echo '<option value="">Sin plantilla</option>';
            foreach ($templates as $template) {
                $optionLabel = $template->name . ' (' . strtoupper((string) $template->language) . ')';
                $selected = selected($selectedTemplate, $template->id, false);
                echo '<option value="' . esc_attr((string) $template->id) . '"' . $selected . '>' . esc_html($optionLabel) . '</option>';
            }
            echo '</select></td></tr>';
        }

        echo '<tr><th scope="row"><label for="scheduled_at">Programar (YYYY-MM-DD HH:MM:SS)</label></th>';
        echo '<td><input type="text" name="scheduled_at" id="scheduled_at" class="regular-text" value="' . esc_attr($scheduledAt) . '"></td></tr>';

        echo '<tr><th scope="row"><label for="audience_emails">Audiencia (emails, uno por línea)</label></th>';
        echo '<td><textarea name="audience_emails" id="audience_emails" class="large-text" rows="6">' . esc_textarea($audienceEmails) . '</textarea></td></tr>';
        echo '</tbody></table>';

        echo '<p class="submit"><button type="submit" name="bressol_esp_campaign_submit" class="button button-primary">Guardar campaña</button></p>';
        echo '</form>';
    }

    private static function maybeBuildAudienceSnapshot(
        int $campaignId,
        string $status,
        ?string $scheduledAt,
        string $audienceEmails
    ): void {
        if ($campaignId === 0 || $status !== 'scheduled') {
            return;
        }

        $recipients = $audienceEmails !== '' ? self::wrapRecipients(self::parseEmailList($audienceEmails)) : self::resolveSegmentRecipients($campaignId);
        if (empty($recipients)) {
            echo '<div class="notice notice-error"><p>No se encontraron emails válidos para la audiencia.</p></div>';
            return;
        }

        global $wpdb;
        $snapshotTable = $wpdb->prefix . 'bressol_esp_campaign_audience_snapshot';
        $queueTable = $wpdb->prefix . 'bressol_esp_send_queue';
        $jobsTable = $wpdb->prefix . 'bressol_esp_send_queue_jobs';
        $consentsTable = $wpdb->prefix . 'bressol_esp_consents';

        $wpdb->delete($snapshotTable, ['campaign_id' => $campaignId], ['%d']);

        $campaignDefaultLanguage = self::SNAPSHOT_DEFAULT_LANGUAGE;
        $campaign = $wpdb->get_row(
            $wpdb->prepare("SELECT template_snapshot FROM {$wpdb->prefix}bressol_esp_campaigns WHERE id = %d", $campaignId)
        );
        if ($campaign && $campaign->template_snapshot) {
            $snapshot = json_decode($campaign->template_snapshot, true);
            if (is_array($snapshot) && !empty($snapshot['default_language'])) {
                $campaignDefaultLanguage = $snapshot['default_language'];
            }
        }

        $scheduledAtValue = $scheduledAt ?: current_time('mysql');
        $now = current_time('mysql');

        foreach ($recipients as $recipient) {
            $email = $recipient['email'];
            $language = $recipient['language'] ?? $campaignDefaultLanguage;
            $consentStatus = $wpdb->get_var(
                $wpdb->prepare("SELECT status FROM {$consentsTable} WHERE email = %s LIMIT 1", $email)
            );

            if ($consentStatus === 'opt_out') {
                continue;
            }

            $wpdb->insert(
                $snapshotTable,
                [
                    'campaign_id' => $campaignId,
                    'user_id' => null,
                    'email' => $email,
                    'snapshot_data' => null,
                    'tracking_key' => wp_generate_password(32, false, false),
                    'language' => $language,
                    'created_at' => $now,
                ],
                [
                    '%d',
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                ]
            );

            $wpdb->insert(
                $queueTable,
                [
                    'campaign_id' => $campaignId,
                    'manual_email_id' => null,
                    'recipient_email' => $email,
                    'status' => 'pending',
                    'attempts' => 0,
                    'scheduled_at' => $scheduledAtValue,
                    'sent_at' => null,
                    'last_error' => null,
                    'created_at' => $now,
                ],
                [
                    '%d',
                    '%d',
                    '%s',
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                ]
            );

            $queueId = (int) $wpdb->insert_id;
            if ($queueId > 0) {
                $wpdb->insert(
                    $jobsTable,
                    [
                        'queue_id' => $queueId,
                        'status' => 'scheduled',
                        'run_at' => $scheduledAtValue,
                        'attempts' => 0,
                        'last_error' => null,
                        'created_at' => $now,
                        'updated_at' => null,
                    ],
                    [
                        '%d',
                        '%s',
                        '%s',
                        '%d',
                        '%s',
                        '%s',
                        '%s',
                    ]
                );
            }
        }

        echo '<div class="notice notice-success"><p>Snapshot de audiencia generado y encolado.</p></div>';
    }

    private static function parseEmailList(string $raw): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        if (!$lines) {
            return [];
        }

        $emails = [];
        foreach ($lines as $line) {
            $email = sanitize_email(trim($line));
            if ($email !== '' && is_email($email)) {
                $emails[] = $email;
            }
        }

        return array_values(array_unique($emails));
    }

    private static function wrapRecipients(array $emails): array
    {
        $recipients = [];
        foreach ($emails as $email) {
            $recipients[] = [
                'email' => $email,
                'language' => self::SNAPSHOT_DEFAULT_LANGUAGE,
            ];
        }

        return $recipients;
    }

    private static function parseIdList(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/[\s,]+/', $raw);
        if (!$parts) {
            return [];
        }

        $ids = [];
        foreach ($parts as $part) {
            $value = absint($part);
            if ($value > 0) {
                $ids[] = $value;
            }
        }

        return array_values(array_unique($ids));
    }

    private static function resolveSegmentRecipients(int $campaignId): array
    {
        global $wpdb;
        $campaignsTable = $wpdb->prefix . 'bressol_esp_campaigns';
        $segmentsTable = $wpdb->prefix . 'bressol_esp_segments';

        $campaign = $wpdb->get_row(
            $wpdb->prepare("SELECT segment_id FROM {$campaignsTable} WHERE id = %d", $campaignId)
        );

        if (!$campaign || !$campaign->segment_id) {
            return [];
        }

        $segment = $wpdb->get_row(
            $wpdb->prepare("SELECT rules_json FROM {$segmentsTable} WHERE id = %d", (int) $campaign->segment_id)
        );

        if (!$segment || !$segment->rules_json) {
            return [];
        }

        $rules = json_decode($segment->rules_json, true);
        if (!is_array($rules)) {
            return [];
        }

        if (!function_exists('wc_get_orders')) {
            echo '<div class="notice notice-error"><p>WooCommerce no está disponible para resolver segmentos.</p></div>';
            return [];
        }

        $orders = wc_get_orders([
            'status' => ['wc-completed', 'wc-processing'],
            'limit' => -1,
            'return' => 'objects',
        ]);

        $stats = [];
        $productCategories = [];
        foreach ($orders as $order) {
            if (!$order) {
                continue;
            }
            $email = $order->get_billing_email();
            if (!$email) {
                continue;
            }
            $email = strtolower($email);

            if (!isset($stats[$email])) {
                $stats[$email] = [
                    'total' => 0.0,
                    'count' => 0,
                    'last_order' => null,
                    'language' => self::SNAPSHOT_DEFAULT_LANGUAGE,
                    'products' => [],
                    'categories' => [],
                ];
            }

            $stats[$email]['total'] += (float) $order->get_total();
            $stats[$email]['count'] += 1;
            $orderDate = $order->get_date_created();
            if ($orderDate) {
                $timestamp = $orderDate->getTimestamp();
                if ($timestamp >= ($stats[$email]['last_order'] ?? 0)) {
                    $stats[$email]['language'] = self::resolveLanguageFromOrder($order);
                }
                $stats[$email]['last_order'] = max($stats[$email]['last_order'] ?? 0, $timestamp);
            }

            foreach ($order->get_items() as $item) {
                $productId = (int) $item->get_product_id();
                if ($productId <= 0) {
                    continue;
                }
                $stats[$email]['products'][$productId] = true;

                if (!array_key_exists($productId, $productCategories)) {
                    $product = wc_get_product($productId);
                    $productCategories[$productId] = $product ? $product->get_category_ids() : [];
                }

                foreach ($productCategories[$productId] as $categoryId) {
                    $stats[$email]['categories'][(int) $categoryId] = true;
                }
            }
        }

        $minTotal = isset($rules['min_total_spent']) ? (float) $rules['min_total_spent'] : null;
        $minCount = isset($rules['min_order_count']) ? (int) $rules['min_order_count'] : null;
        $maxDays = isset($rules['last_order_days']) ? (int) $rules['last_order_days'] : null;
        $productFilter = isset($rules['product_ids']) && is_array($rules['product_ids']) ? array_map('intval', $rules['product_ids']) : [];
        $categoryFilter = isset($rules['category_ids']) && is_array($rules['category_ids']) ? array_map('intval', $rules['category_ids']) : [];
        $now = time();

        $recipients = [];
        foreach ($stats as $email => $data) {
            if ($minTotal !== null && $data['total'] < $minTotal) {
                continue;
            }
            if ($minCount !== null && $data['count'] < $minCount) {
                continue;
            }
            if ($maxDays !== null) {
                if (!$data['last_order']) {
                    continue;
                }
                $daysSince = (int) floor(($now - $data['last_order']) / DAY_IN_SECONDS);
                if ($daysSince > $maxDays) {
                    continue;
                }
            }
            if (!empty($productFilter)) {
                $purchasedProducts = array_keys($data['products']);
                if (empty(array_intersect($productFilter, $purchasedProducts))) {
                    continue;
                }
            }
            if (!empty($categoryFilter)) {
                $purchasedCategories = array_keys($data['categories']);
                if (empty(array_intersect($categoryFilter, $purchasedCategories))) {
                    continue;
                }
            }
            $recipients[] = [
                'email' => $email,
                'language' => $data['language'] ?? self::SNAPSHOT_DEFAULT_LANGUAGE,
            ];
        }

        $unique = [];
        foreach ($recipients as $recipient) {
            $unique[$recipient['email']] = $recipient;
        }

        return array_values($unique);
    }

    private static function resolveLanguageFromOrder(\WC_Order $order): string
    {
        $language = $order->get_meta('language');
        if (is_string($language) && $language !== '') {
            return self::normalizeLanguage($language);
        }

        $customerId = $order->get_customer_id();
        if ($customerId) {
            $locale = get_user_locale($customerId);
            if ($locale) {
                return self::normalizeLanguage($locale);
            }
        }

        $country = $order->get_billing_country();
        if ($country) {
            return self::normalizeLanguage($country);
        }

        return self::SNAPSHOT_DEFAULT_LANGUAGE;
    }

    private static function normalizeLanguage(string $value): string
    {
        $value = strtolower($value);

        if (str_starts_with($value, 'ca') || $value === 'va') {
            return 'va';
        }
        if (str_starts_with($value, 'es')) {
            return 'es';
        }
        if (str_starts_with($value, 'nl')) {
            return 'nl';
        }
        if (str_starts_with($value, 'en')) {
            return 'en';
        }

        $map = [
            'es' => 'es',
            'nl' => 'nl',
            'gb' => 'en',
            'us' => 'en',
            'ie' => 'en',
            'uk' => 'en',
            'nl-nl' => 'nl',
            'es-es' => 'es',
        ];

        return $map[$value] ?? self::SNAPSHOT_DEFAULT_LANGUAGE;
    }

    private static function renderTemplatesTable(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bressol_esp_templates';
        $items = $wpdb->get_results("SELECT id, name, language, created_at FROM {$table} ORDER BY created_at DESC LIMIT 50");

        echo '<h2>Plantillas existentes</h2>';

        if (empty($items)) {
            echo '<p>No hay plantillas creadas todavía.</p>';
            return;
        }

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>ID</th><th>Nombre</th><th>Idioma</th><th>Creado</th><th>Acciones</th>';
        echo '</tr></thead><tbody>';

        foreach ($items as $item) {
            $languageLabel = self::TEMPLATE_LANGUAGES[$item->language] ?? $item->language;
            $deleteUrl = wp_nonce_url(
                add_query_arg(['bressol_esp_template_delete' => $item->id], menu_page_url('bressol-esp-templates', false)),
                'bressol_esp_template_delete'
            );
            $editUrl = add_query_arg(['template_id' => $item->id], menu_page_url('bressol-esp-templates', false));

            echo '<tr>';
            echo '<td>' . esc_html((string) $item->id) . '</td>';
            echo '<td>' . esc_html($item->name) . '</td>';
            echo '<td>' . esc_html($languageLabel) . '</td>';
            echo '<td>' . esc_html($item->created_at) . '</td>';
            echo '<td>';
            echo '<a href="' . esc_url($editUrl) . '">Editar</a> | ';
            echo '<a href="' . esc_url($deleteUrl) . '" onclick="return confirm(\'¿Eliminar esta plantilla?\')">Eliminar</a>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private static function renderTemplateForm(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bressol_esp_templates';
        $templateId = isset($_GET['template_id']) ? absint($_GET['template_id']) : 0;

        $name = '';
        $language = 'es';
        $html = '';
        $textPlain = '';
        $formTitle = 'Crear plantilla';

        if ($templateId > 0) {
            $template = $wpdb->get_row(
                $wpdb->prepare("SELECT id, name, language, html, text_plain FROM {$table} WHERE id = %d", $templateId)
            );
            if ($template) {
                $name = $template->name;
                $language = $template->language;
                $html = $template->html;
                $textPlain = $template->text_plain ?? '';
                $formTitle = 'Editar plantilla';
            }
        }

        $language = array_key_exists($language, self::TEMPLATE_LANGUAGES) ? $language : 'es';

        echo '<hr />';
        echo '<h2>' . esc_html($formTitle) . '</h2>';
        echo '<form method="post">';
        wp_nonce_field('bressol_esp_template');
        echo '<input type="hidden" name="template_id" value="' . esc_attr((string) $templateId) . '" />';

        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="name">Nombre</label></th>';
        echo '<td><input type="text" name="name" id="name" class="regular-text" value="' . esc_attr($name) . '" required></td></tr>';

        echo '<tr><th scope="row"><label for="language">Idioma</label></th><td>';
        echo '<select name="language" id="language" required>';
        foreach (self::TEMPLATE_LANGUAGES as $value => $label) {
            $selected = selected($language, $value, false);
            echo '<option value="' . esc_attr($value) . '"' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th scope="row"><label for="html">HTML</label></th>';
        echo '<td><textarea name="html" id="html" class="large-text" rows="10" required>' . esc_textarea($html) . '</textarea></td></tr>';

        echo '<tr><th scope="row"><label for="text_plain">Texto plano</label></th>';
        echo '<td><textarea name="text_plain" id="text_plain" class="large-text" rows="6">' . esc_textarea($textPlain) . '</textarea></td></tr>';
        echo '</tbody></table>';

        echo '<p class="submit"><button type="submit" name="bressol_esp_template_submit" class="button button-primary">Guardar plantilla</button></p>';
        echo '</form>';
    }

    private static function renderQueueForm(): void
    {
        echo '<hr />';
        echo '<h2>Encolar envío</h2>';
        echo '<form method="post">';
        wp_nonce_field('bressol_esp_queue');

        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="campaign_id">ID de campaña (opcional)</label></th>';
        echo '<td><input type="number" name="campaign_id" id="campaign_id" class="regular-text"></td></tr>';

        echo '<tr><th scope="row"><label for="manual_email_id">ID de email manual (opcional)</label></th>';
        echo '<td><input type="number" name="manual_email_id" id="manual_email_id" class="regular-text"></td></tr>';

        echo '<tr><th scope="row"><label for="recipient_email">Email destinatario</label></th>';
        echo '<td><input type="email" name="recipient_email" id="recipient_email" class="regular-text" required></td></tr>';

        echo '<tr><th scope="row"><label for="scheduled_at">Programar (YYYY-MM-DD HH:MM:SS)</label></th>';
        echo '<td><input type="text" name="scheduled_at" id="scheduled_at" class="regular-text"></td></tr>';
        echo '</tbody></table>';

        echo '<p class="submit"><button type="submit" name="bressol_esp_queue_submit" class="button button-primary">Añadir a la cola</button></p>';
        echo '</form>';

        echo '<hr />';
        echo '<h2>Programar ejecución</h2>';
        echo '<form method="post">';
        wp_nonce_field('bressol_esp_queue_schedule');

        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="queue_id">ID de cola</label></th>';
        echo '<td><input type="number" name="queue_id" id="queue_id" class="regular-text" required></td></tr>';

        echo '<tr><th scope="row"><label for="run_at">Ejecutar en (YYYY-MM-DD HH:MM:SS)</label></th>';
        echo '<td><input type="text" name="run_at" id="run_at" class="regular-text" required></td></tr>';
        echo '</tbody></table>';

        echo '<p class="submit"><button type="submit" name="bressol_esp_queue_schedule" class="button button-primary">Programar</button></p>';
        echo '</form>';
    }

    private static function renderQueueTable(): void
    {
        global $wpdb;
        $queueTable = $wpdb->prefix . 'bressol_esp_send_queue';
        $jobsTable = $wpdb->prefix . 'bressol_esp_send_queue_jobs';

        $rows = $wpdb->get_results(
            "SELECT q.id, q.campaign_id, q.manual_email_id, q.recipient_email, q.status, q.attempts, q.scheduled_at, q.sent_at,
                    j.status AS job_status, j.run_at
             FROM {$queueTable} q
             LEFT JOIN {$jobsTable} j ON j.queue_id = q.id
             ORDER BY q.created_at DESC
             LIMIT 50"
        );

        echo '<h2>Cola de envíos</h2>';

        if (empty($rows)) {
            echo '<p>No hay envíos en la cola todavía.</p>';
            return;
        }

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>ID</th><th>Campaña</th><th>Email manual</th><th>Destinatario</th><th>Estado</th><th>Intentos</th><th>Programado</th><th>Enviado</th><th>Job</th>';
        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $jobLabel = $row->job_status ? $row->job_status . ' @ ' . $row->run_at : '-';
            echo '<tr>';
            echo '<td>' . esc_html((string) $row->id) . '</td>';
            echo '<td>' . esc_html((string) ($row->campaign_id ?? '-')) . '</td>';
            echo '<td>' . esc_html((string) ($row->manual_email_id ?? '-')) . '</td>';
            echo '<td>' . esc_html($row->recipient_email) . '</td>';
            echo '<td>' . esc_html($row->status) . '</td>';
            echo '<td>' . esc_html((string) $row->attempts) . '</td>';
            echo '<td>' . esc_html($row->scheduled_at ?? '-') . '</td>';
            echo '<td>' . esc_html($row->sent_at ?? '-') . '</td>';
            echo '<td>' . esc_html($jobLabel) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private static function handleConsentsSubmit(): void
    {
        if (!isset($_POST['bressol_esp_consent_submit']) && !isset($_POST['bressol_esp_consent_bulk_submit'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }

        $isBulk = isset($_POST['bressol_esp_consent_bulk_submit']);
        $nonceAction = $isBulk ? 'bressol_esp_consent_bulk' : 'bressol_esp_consent_single';
        check_admin_referer($nonceAction);

        $status = isset($_POST['consent_status']) ? sanitize_text_field(wp_unslash($_POST['consent_status'])) : '';
        if (!in_array($status, ['opt_in', 'opt_out'], true)) {
            echo '<div class="notice notice-error"><p>Estado no válido.</p></div>';
            return;
        }

        $source = isset($_POST['consent_source']) ? sanitize_text_field(wp_unslash($_POST['consent_source'])) : '';
        if ($source === '') {
            $source = $isBulk ? 'manual_bulk' : 'manual_admin';
        }

        $emails = [];
        if ($isBulk) {
            $raw = isset($_POST['consent_emails']) ? sanitize_textarea_field(wp_unslash($_POST['consent_emails'])) : '';
            $emails = self::parseEmailList($raw);
        } else {
            $email = isset($_POST['consent_email']) ? sanitize_email(wp_unslash($_POST['consent_email'])) : '';
            if ($email !== '' && is_email($email)) {
                $emails[] = $email;
            }
        }

        if (empty($emails)) {
            echo '<div class="notice notice-error"><p>No se encontraron emails válidos.</p></div>';
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bressol_esp_consents';
        $now = current_time('mysql');
        $updated = 0;

        foreach ($emails as $email) {
            $result = $wpdb->replace(
                $table,
                [
                    'user_id' => null,
                    'email' => $email,
                    'status' => $status,
                    'source' => $source,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                ]
            );
            if ($result !== false) {
                $updated++;
                self::insertAuditLog('consent_update', 'consent', 0, [
                    'email' => $email,
                    'status' => $status,
                    'source' => $source,
                    'bulk' => $isBulk,
                ]);
            }
        }

        echo '<div class="notice notice-success"><p>Consentimientos actualizados: ' . esc_html((string) $updated) . '.</p></div>';
    }

    private static function handleConsentsAction(): void
    {
        if (!isset($_GET['bressol_esp_consent_action'], $_GET['consent_email'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }

        check_admin_referer('bressol_esp_consent_action');

        $action = sanitize_text_field(wp_unslash($_GET['bressol_esp_consent_action']));
        $email = sanitize_email(wp_unslash($_GET['consent_email']));

        if ($email === '' || !is_email($email)) {
            echo '<div class="notice notice-error"><p>Email no válido.</p></div>';
            return;
        }

        $status = null;
        if ($action === 'opt_in') {
            $status = 'opt_in';
        }
        if ($action === 'opt_out') {
            $status = 'opt_out';
        }

        if ($status === null) {
            echo '<div class="notice notice-error"><p>Acción no válida.</p></div>';
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bressol_esp_consents';
        $now = current_time('mysql');

        $result = $wpdb->replace(
            $table,
            [
                'user_id' => null,
                'email' => $email,
                'status' => $status,
                'source' => 'manual_action',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            ]
        );

        if ($result === false) {
            echo '<div class="notice notice-error"><p>No se pudo actualizar el consentimiento.</p></div>';
            return;
        }

        self::insertAuditLog('consent_update', 'consent', 0, [
            'email' => $email,
            'status' => $status,
            'source' => 'manual_action',
            'bulk' => false,
        ]);

        echo '<div class="notice notice-success"><p>Consentimiento actualizado para ' . esc_html($email) . '.</p></div>';
    }

    private static function renderConsentsForm(): void
    {
        echo '<hr />';
        echo '<h2>Actualizar consentimiento</h2>';
        echo '<form method="post">';
        wp_nonce_field('bressol_esp_consent_single');
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="consent_email">Email</label></th>';
        echo '<td><input type="email" name="consent_email" id="consent_email" class="regular-text" required></td></tr>';
        echo '<tr><th scope="row"><label for="consent_status">Estado</label></th><td>';
        echo '<select name="consent_status" id="consent_status" required>';
        echo '<option value="opt_in">Opt-in</option>';
        echo '<option value="opt_out">Opt-out</option>';
        echo '</select></td></tr>';
        echo '<tr><th scope="row"><label for="consent_source">Origen</label></th>';
        echo '<td><input type="text" name="consent_source" id="consent_source" class="regular-text" placeholder="manual_admin"></td></tr>';
        echo '</tbody></table>';
        echo '<p class="submit"><button type="submit" name="bressol_esp_consent_submit" class="button button-primary">Guardar consentimiento</button></p>';
        echo '</form>';

        echo '<hr />';
        echo '<h2>Actualización masiva</h2>';
        echo '<form method="post">';
        wp_nonce_field('bressol_esp_consent_bulk');
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="consent_emails">Emails (uno por línea)</label></th>';
        echo '<td><textarea name="consent_emails" id="consent_emails" class="large-text" rows="5" placeholder="email1@dominio.com"></textarea></td></tr>';
        echo '<tr><th scope="row"><label for="consent_status_bulk">Estado</label></th><td>';
        echo '<select name="consent_status" id="consent_status_bulk" required>';
        echo '<option value="opt_in">Opt-in</option>';
        echo '<option value="opt_out">Opt-out</option>';
        echo '</select></td></tr>';
        echo '<tr><th scope="row"><label for="consent_source_bulk">Origen</label></th>';
        echo '<td><input type="text" name="consent_source" id="consent_source_bulk" class="regular-text" placeholder="manual_bulk"></td></tr>';
        echo '</tbody></table>';
        echo '<p class="submit"><button type="submit" name="bressol_esp_consent_bulk_submit" class="button">Actualizar en bloque</button></p>';
        echo '</form>';
    }

    private static function renderConsentsTable(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bressol_esp_consents';
        $search = isset($_GET['consent_search']) ? sanitize_text_field(wp_unslash($_GET['consent_search'])) : '';
        $statusFilter = isset($_GET['consent_status']) ? sanitize_text_field(wp_unslash($_GET['consent_status'])) : '';

        $where = [];
        $params = [];
        if ($search !== '') {
            $where[] = 'email LIKE %s';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }
        if (in_array($statusFilter, ['opt_in', 'opt_out'], true)) {
            $where[] = 'status = %s';
            $params[] = $statusFilter;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $query = "SELECT email, status, source, created_at, updated_at
             FROM {$table}
             {$whereSql}
             ORDER BY COALESCE(updated_at, created_at) DESC
             LIMIT 50";

        $rows = $params ? $wpdb->get_results($wpdb->prepare($query, $params)) : $wpdb->get_results($query);

        echo '<h2>Últimos consentimientos</h2>';
        echo '<form method="get" style="margin:12px 0;">';
        echo '<input type="hidden" name="page" value="bressol-esp-consents" />';
        echo '<input type="search" name="consent_search" placeholder="Buscar email" value="' . esc_attr($search) . '" />';
        echo '<select name="consent_status">';
        echo '<option value="">Todos los estados</option>';
        foreach (['opt_in' => 'Opt-in', 'opt_out' => 'Opt-out'] as $value => $label) {
            $selected = selected($statusFilter, $value, false);
            echo '<option value="' . esc_attr($value) . '"' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select> ';
        echo '<button type="submit" class="button">Filtrar</button>';
        echo '</form>';

        if (empty($rows)) {
            echo '<p>No hay consentimientos registrados todavía.</p>';
            return;
        }

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>Email</th><th>Estado</th><th>Origen</th><th>Creado</th><th>Actualizado</th><th>Acciones</th>';
        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $optInUrl = wp_nonce_url(
                add_query_arg(
                    [
                        'bressol_esp_consent_action' => 'opt_in',
                        'consent_email' => $row->email,
                    ],
                    menu_page_url('bressol-esp-consents', false)
                ),
                'bressol_esp_consent_action'
            );
            $optOutUrl = wp_nonce_url(
                add_query_arg(
                    [
                        'bressol_esp_consent_action' => 'opt_out',
                        'consent_email' => $row->email,
                    ],
                    menu_page_url('bressol-esp-consents', false)
                ),
                'bressol_esp_consent_action'
            );

            echo '<tr>';
            echo '<td>' . esc_html($row->email) . '</td>';
            echo '<td>' . esc_html($row->status) . '</td>';
            echo '<td>' . esc_html($row->source ?? '-') . '</td>';
            echo '<td>' . esc_html($row->created_at) . '</td>';
            echo '<td>' . esc_html($row->updated_at ?? '-') . '</td>';
            echo '<td>';
            echo '<a href="' . esc_url($optInUrl) . '">Opt-in</a> | ';
            echo '<a href="' . esc_url($optOutUrl) . '">Opt-out</a>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private static function renderAuditTable(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bressol_esp_audit_logs';
        $rows = $wpdb->get_results(
            "SELECT actor_user_id, action, target_type, target_id, metadata, created_at
             FROM {$table}
             ORDER BY created_at DESC
             LIMIT 50"
        );

        echo '<h2>Últimas acciones</h2>';

        if (empty($rows)) {
            echo '<p>No hay auditorías registradas todavía.</p>';
            return;
        }

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>Usuario</th><th>Acción</th><th>Tipo</th><th>ID</th><th>Detalle</th><th>Fecha</th>';
        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $metadata = $row->metadata ? wp_json_encode(json_decode($row->metadata, true), JSON_UNESCAPED_UNICODE) : '-';
            echo '<tr>';
            echo '<td>' . esc_html((string) $row->actor_user_id) . '</td>';
            echo '<td>' . esc_html($row->action) . '</td>';
            echo '<td>' . esc_html($row->target_type) . '</td>';
            echo '<td>' . esc_html((string) $row->target_id) . '</td>';
            echo '<td>' . esc_html($metadata) . '</td>';
            echo '<td>' . esc_html($row->created_at) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private static function renderManualEmailMetrics(): void
    {
        global $wpdb;
        $manualTable = $wpdb->prefix . 'bressol_esp_manual_emails';
        $eventsTable = $wpdb->prefix . 'bressol_esp_events';

        $sentCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$manualTable} WHERE sent_at IS NOT NULL");
        $openCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$eventsTable} WHERE manual_email_id IS NOT NULL AND event_type = 'open'");
        $clickCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$eventsTable} WHERE manual_email_id IS NOT NULL AND event_type = 'click'");

        $openRate = $sentCount > 0 ? round(($openCount / $sentCount) * 100, 2) : 0;
        $clickRate = $sentCount > 0 ? round(($clickCount / $sentCount) * 100, 2) : 0;

        echo '<h2>Métricas de emails manuales</h2>';
        echo '<table class="widefat fixed striped"><tbody>';
        echo '<tr><th>Enviados</th><td>' . esc_html((string) $sentCount) . '</td></tr>';
        echo '<tr><th>Aperturas</th><td>' . esc_html((string) $openCount) . ' (' . esc_html((string) $openRate) . '%)</td></tr>';
        echo '<tr><th>Clicks</th><td>' . esc_html((string) $clickCount) . ' (' . esc_html((string) $clickRate) . '%)</td></tr>';
        echo '</tbody></table>';

        self::renderBarChart('Emails manuales (volumen)', [
            ['label' => 'Enviados', 'value' => $sentCount],
            ['label' => 'Aperturas', 'value' => $openCount],
            ['label' => 'Clicks', 'value' => $clickCount],
        ]);

        echo '<form method="post" style="margin-top:16px;">';
        wp_nonce_field('bressol_esp_manual_metrics_export');
        echo '<input type="hidden" name="bressol_esp_manual_metrics_export" value="1" />';
        echo '<button type="submit" class="button">Exportar CSV</button>';
        echo '</form>';
    }

    private static function renderCampaignMetrics(): void
    {
        global $wpdb;
        $campaignsTable = $wpdb->prefix . 'bressol_esp_campaigns';
        $queueTable = $wpdb->prefix . 'bressol_esp_send_queue';
        $eventsTable = $wpdb->prefix . 'bressol_esp_events';
        $snapshotTable = $wpdb->prefix . 'bressol_esp_campaign_audience_snapshot';

        $rows = $wpdb->get_results(
            "SELECT c.id,
                    c.name,
                    COUNT(DISTINCT q.id) AS sent_count,
                    COUNT(DISTINCT s.id) AS audience_count,
                    SUM(CASE WHEN e.event_type = 'open' THEN 1 ELSE 0 END) AS opens,
                    SUM(CASE WHEN e.event_type = 'click' THEN 1 ELSE 0 END) AS clicks,
                    SUM(CASE WHEN e.event_type = 'unsub' THEN 1 ELSE 0 END) AS unsubs
             FROM {$campaignsTable} c
             LEFT JOIN {$queueTable} q ON q.campaign_id = c.id AND q.sent_at IS NOT NULL
             LEFT JOIN {$snapshotTable} s ON s.campaign_id = c.id
             LEFT JOIN {$eventsTable} e ON e.campaign_id = c.id
             GROUP BY c.id
             ORDER BY c.created_at DESC
             LIMIT 50"
        );

        echo '<h2>Métricas de campañas</h2>';

        if (empty($rows)) {
            echo '<p>No hay campañas con métricas todavía.</p>';
            return;
        }

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>ID</th><th>Campaña</th><th>Audiencia</th><th>Enviados</th><th>Aperturas</th><th>Clicks</th><th>Bajas</th>';
        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $sent = (int) $row->sent_count;
            $opens = (int) ($row->opens ?? 0);
            $clicks = (int) ($row->clicks ?? 0);
            $unsubs = (int) ($row->unsubs ?? 0);
            $audience = (int) ($row->audience_count ?? 0);
            echo '<tr>';
            echo '<td>' . esc_html((string) $row->id) . '</td>';
            echo '<td>' . esc_html($row->name) . '</td>';
            echo '<td>' . esc_html((string) $audience) . '</td>';
            echo '<td>' . esc_html((string) $sent) . '</td>';
            echo '<td>' . esc_html((string) $opens) . '</td>';
            echo '<td>' . esc_html((string) $clicks) . '</td>';
            echo '<td>' . esc_html((string) $unsubs) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        $topCampaigns = $wpdb->get_results(
            "SELECT c.name,
                    SUM(CASE WHEN e.event_type = 'open' THEN 1 ELSE 0 END) AS opens,
                    SUM(CASE WHEN e.event_type = 'click' THEN 1 ELSE 0 END) AS clicks
             FROM {$campaignsTable} c
             LEFT JOIN {$eventsTable} e ON e.campaign_id = c.id
             GROUP BY c.id
             ORDER BY opens DESC
             LIMIT 5"
        );

        if (!empty($topCampaigns)) {
            $openData = [];
            $clickData = [];
            foreach ($topCampaigns as $row) {
                $label = $row->name ?: 'Campaña';
                $openData[] = ['label' => $label, 'value' => (int) ($row->opens ?? 0)];
                $clickData[] = ['label' => $label, 'value' => (int) ($row->clicks ?? 0)];
            }

            self::renderBarChart('Top campañas por aperturas', $openData);
            self::renderBarChart('Top campañas por clicks', $clickData);
        }

        echo '<form method="post" style="margin-top:16px;">';
        wp_nonce_field('bressol_esp_campaign_metrics_export');
        echo '<input type="hidden" name="bressol_esp_campaign_metrics_export" value="1" />';
        echo '<button type="submit" class="button">Exportar CSV campañas</button>';
        echo '</form>';
    }

    private static function renderMetricsStyles(): void
    {
        echo '<style>
            .bressol-esp-chart{max-width:720px;margin:16px 0 24px;}
            .bressol-esp-chart h3{margin:0 0 12px;}
            .bressol-esp-chart-row{display:flex;align-items:center;gap:12px;margin-bottom:10px;}
            .bressol-esp-chart-label{width:160px;font-weight:600;}
            .bressol-esp-chart-bar{flex:1;height:14px;background:#f1f1f1;border-radius:7px;overflow:hidden;position:relative;}
            .bressol-esp-chart-bar span{display:block;height:100%;background:#2271b1;}
            .bressol-esp-chart-value{min-width:64px;text-align:right;font-variant-numeric:tabular-nums;}
        </style>';
    }

    private static function renderBarChart(string $title, array $data): void
    {
        $maxValue = 0;
        foreach ($data as $item) {
            $value = isset($item['value']) ? (int) $item['value'] : 0;
            if ($value > $maxValue) {
                $maxValue = $value;
            }
        }

        if ($maxValue === 0) {
            $maxValue = 1;
        }

        echo '<div class="bressol-esp-chart">';
        echo '<h3>' . esc_html($title) . '</h3>';
        foreach ($data as $item) {
            $label = isset($item['label']) ? (string) $item['label'] : '';
            $value = isset($item['value']) ? (int) $item['value'] : 0;
            $width = min(100, (int) round(($value / $maxValue) * 100));

            echo '<div class="bressol-esp-chart-row">';
            echo '<div class="bressol-esp-chart-label">' . esc_html($label) . '</div>';
            echo '<div class="bressol-esp-chart-bar"><span style="width:' . esc_attr((string) $width) . '%"></span></div>';
            echo '<div class="bressol-esp-chart-value">' . esc_html((string) $value) . '</div>';
            echo '</div>';
        }
        echo '</div>';
    }

    private static function handleMetricsExport(): void
    {
        if (isset($_POST['bressol_esp_campaign_metrics_export'])) {
            self::handleCampaignMetricsExport();
            return;
        }

        if (!isset($_POST['bressol_esp_manual_metrics_export'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }

        check_admin_referer('bressol_esp_manual_metrics_export');

        global $wpdb;
        $manualTable = $wpdb->prefix . 'bressol_esp_manual_emails';
        $eventsTable = $wpdb->prefix . 'bressol_esp_events';

        $rows = $wpdb->get_results(
            "SELECT m.id,
                    m.order_id,
                    m.email_type,
                    m.recipient_email,
                    m.subject,
                    m.created_at,
                    m.sent_at,
                    SUM(CASE WHEN e.event_type = 'open' THEN 1 ELSE 0 END) AS opens,
                    SUM(CASE WHEN e.event_type = 'click' THEN 1 ELSE 0 END) AS clicks
             FROM {$manualTable} m
             LEFT JOIN {$eventsTable} e ON e.manual_email_id = m.id
             GROUP BY m.id
             ORDER BY m.created_at DESC
             LIMIT 100"
        );

        $filename = 'esp-manual-emails-metrics-' . gmdate('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');
        fputcsv($output, ['id', 'order_id', 'email_type', 'recipient_email', 'subject', 'created_at', 'sent_at', 'opens', 'clicks']);
        foreach ($rows as $row) {
            fputcsv($output, [
                $row->id,
                $row->order_id,
                $row->email_type,
                $row->recipient_email,
                $row->subject,
                $row->created_at,
                $row->sent_at,
                $row->opens ?? 0,
                $row->clicks ?? 0,
            ]);
        }
        fclose($output);
        exit;
    }

    private static function handleCampaignMetricsExport(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }

        check_admin_referer('bressol_esp_campaign_metrics_export');

        global $wpdb;
        $campaignsTable = $wpdb->prefix . 'bressol_esp_campaigns';
        $queueTable = $wpdb->prefix . 'bressol_esp_send_queue';
        $eventsTable = $wpdb->prefix . 'bressol_esp_events';
        $snapshotTable = $wpdb->prefix . 'bressol_esp_campaign_audience_snapshot';

        $rows = $wpdb->get_results(
            "SELECT c.id,
                    c.name,
                    COUNT(DISTINCT q.id) AS sent_count,
                    COUNT(DISTINCT s.id) AS audience_count,
                    SUM(CASE WHEN e.event_type = 'open' THEN 1 ELSE 0 END) AS opens,
                    SUM(CASE WHEN e.event_type = 'click' THEN 1 ELSE 0 END) AS clicks,
                    SUM(CASE WHEN e.event_type = 'unsub' THEN 1 ELSE 0 END) AS unsubs
             FROM {$campaignsTable} c
             LEFT JOIN {$queueTable} q ON q.campaign_id = c.id AND q.sent_at IS NOT NULL
             LEFT JOIN {$snapshotTable} s ON s.campaign_id = c.id
             LEFT JOIN {$eventsTable} e ON e.campaign_id = c.id
             GROUP BY c.id
             ORDER BY c.created_at DESC
             LIMIT 100"
        );

        $filename = 'esp-campaign-metrics-' . gmdate('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');
        fputcsv($output, ['id', 'name', 'audience', 'sent', 'opens', 'clicks', 'unsubs']);
        foreach ($rows as $row) {
            fputcsv($output, [
                $row->id,
                $row->name,
                $row->audience_count ?? 0,
                $row->sent_count ?? 0,
                $row->opens ?? 0,
                $row->clicks ?? 0,
                $row->unsubs ?? 0,
            ]);
        }
        fclose($output);
        exit;
    }

    private static function appendOpenTrackingPixel(string $htmlBody, string $trackingKey): string
    {
        $trackingUrl = add_query_arg(
            [
                'bressol_esp_open' => '1',
                'tracking_key' => $trackingKey,
            ],
            home_url('/')
        );

        $pixel = '<img src="' . esc_url($trackingUrl) . '" alt="" width="1" height="1" style="display:none;" />';

        return $htmlBody . $pixel;
    }

    private static function appendUnsubscribeLinks(string $htmlBody, string $textBody, string $trackingKey): array
    {
        $unsubscribeUrl = add_query_arg(
            [
                'bressol_esp_unsub' => '1',
                'tracking_key' => $trackingKey,
            ],
            home_url('/')
        );

        $htmlLink = '<p style="font-size:12px;color:#666;">Si no quieres recibir más mensajes, puedes darte de baja aquí: '
            . '<a href="' . esc_url($unsubscribeUrl) . '">darte de baja</a>.</p>';

        $textLink = "\n\nSi no quieres recibir más mensajes, puedes darte de baja aquí: {$unsubscribeUrl}";

        return [
            $htmlBody . $htmlLink,
            $textBody !== '' ? $textBody . $textLink : $textBody,
        ];
    }

    private static function rewriteLinksForClickTracking(string $htmlBody, string $trackingKey): string
    {
        if ($htmlBody === '') {
            return $htmlBody;
        }

        $previousErrors = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $htmlBody);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        if (!$loaded) {
            return $htmlBody;
        }

        $links = $dom->getElementsByTagName('a');
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if ($href === '' || str_starts_with($href, '#')) {
                continue;
            }

            $link->setAttribute('href', self::buildClickTrackingUrl($trackingKey, $href));
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body) {
            return $htmlBody;
        }

        $output = '';
        foreach ($body->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }

        return $output !== '' ? $output : $htmlBody;
    }

    private static function buildClickTrackingUrl(string $trackingKey, string $targetUrl): string
    {
        $encodedTarget = self::encodeUrlForTracking($targetUrl);

        return add_query_arg(
            [
                'bressol_esp_click' => '1',
                'tracking_key' => $trackingKey,
                'target' => $encodedTarget,
            ],
            home_url('/')
        );
    }

    private static function encodeUrlForTracking(string $targetUrl): string
    {
        $encoded = base64_encode($targetUrl);
        return rtrim(strtr($encoded, '+/', '-_'), '=');
    }

    private static function insertAuditLog(string $action, string $targetType, int $targetId, array $metadata = []): void
    {
        global $wpdb;
        $auditTable = $wpdb->prefix . 'bressol_esp_audit_logs';

        $wpdb->insert(
            $auditTable,
            [
                'actor_user_id' => get_current_user_id(),
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'metadata' => $metadata ? wp_json_encode($metadata) : null,
                'created_at' => current_time('mysql'),
            ],
            [
                '%d',
                '%s',
                '%s',
                '%d',
                '%s',
                '%s',
            ]
        );
    }
}
