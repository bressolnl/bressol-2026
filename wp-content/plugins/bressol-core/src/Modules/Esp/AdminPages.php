<?php
declare(strict_types=1);

namespace Bressol\Modules\Esp;

if (!defined('ABSPATH')) {
    exit;
}

final class AdminPages
{
    public static function renderOverview(): void
    {
        self::renderHeader('Bressol ESP');
        echo '<p>Panel general del ESP interno. Aquí agruparemos campañas, plantillas, segmentos y métricas.</p>';
        self::renderFooter();
    }

    public static function renderCampaigns(): void
    {
        self::renderHeader('Campañas');
        echo '<p>Gestión de campañas multilingües (va/es/nl/en), con snapshot de audiencia al enviar.</p>';
        self::renderFooter();
    }

    public static function renderTemplates(): void
    {
        self::renderHeader('Plantillas');
        echo '<p>Plantillas HTML responsive. Se requieren 4 versiones obligatorias: va, es, nl, en.</p>';
        self::renderFooter();
    }

    public static function renderSegments(): void
    {
        self::renderHeader('Segmentos');
        echo '<p>Segmentación avanzada basada en datos de WooCommerce.</p>';
        self::renderFooter();
    }

    public static function renderManualEmails(): void
    {
        self::renderHeader('Emails manuales');
        echo '<p>Emails 1-a-1 para post-compra y envío. Un solo idioma por email, elegido manualmente.</p>';
        self::renderFooter();
    }

    public static function renderMetrics(): void
    {
        self::renderHeader('Métricas');
        echo '<p>Métricas de aperturas, clicks y bajas con gráficos. Aplica a campañas y emails manuales.</p>';
        self::renderFooter();
    }

    public static function renderConsents(): void
    {
        self::renderHeader('Consentimientos');
        echo '<p>Gestión de consentimientos y lista de exclusión para cumplimiento RGPD/LSSI.</p>';
        self::renderFooter();
    }

    public static function renderExports(): void
    {
        self::renderHeader('Exportaciones');
        echo '<p>Exportación de métricas y eventos en CSV.</p>';
        self::renderFooter();
    }

    public static function renderSettings(): void
    {
        self::renderHeader('Configuración SMTP');
        echo '<p>Configuración del servidor SMTP propio (perfil único) y límites de envío.</p>';
        self::renderFooter();
    }

    public static function renderAudit(): void
    {
        self::renderHeader('Auditoría');
        echo '<p>Auditoría de acciones: quién crea, edita y envía campañas o emails manuales.</p>';
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
}
