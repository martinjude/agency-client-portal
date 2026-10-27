<?php
/**
 * Template : Page d'erreur d'accès refusé
 *
 * Page standalone affichée lorsqu'un client tente d'accéder
 * à un espace sans lien valide ou avec un token expiré.
 *
 * Variables disponibles :
 *   $error_reason  (string) — Message explicatif de l'erreur.
 *
 * @package Agency
 */

if (!defined('ABSPATH')) {
    exit;
}

$agency_brand_logo_url = get_option('agency_brand_logo_url', '');
$agency_home_url       = home_url('/');
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html__('Access denied', 'agency-client-portal'); ?> — <?php bloginfo('name'); ?></title>
    <?php ob_start(); ?>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 24px 16px;
            background: var(--ag-bg);
            color: var(--ag-text-1);
            font-family: var(--ag-font);
            -webkit-font-smoothing: antialiased;
        }
        .agency-access-denied { display: flex; flex-direction: column; align-items: center; gap: 24px; width: 100%; max-width: 480px; }
        .agency-access-denied__logo img { display: block; max-width: 160px; max-height: 48px; border-radius: 12px; }
        .agency-access-denied__card {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
            padding: 40px 48px 0;
            border: 1px solid var(--ag-border);
            border-radius: var(--ag-r-xl);
            background: var(--ag-panel);
            text-align: center;
        }
        .agency-access-denied__icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 56px;
            height: 56px;
            margin-bottom: 20px;
            border: 1px solid var(--ag-border);
            border-radius: var(--ag-r-xl);
            background: var(--ag-hover);
        }
        .agency-access-denied__title { font: 600 20px/28px var(--ag-font); letter-spacing: -.005em; }
        .agency-access-denied__reason { max-width: 360px; margin: 10px 0 0; color: var(--ag-text-2); font: 400 14px/21px var(--ag-font); }
        .agency-access-denied__link {
            display: inline-flex;
            align-items: center;
            height: 36px;
            margin-top: 24px;
            padding: 0 14px;
            border: 1px solid var(--ag-border);
            border-radius: var(--ag-r);
            background: var(--ag-hover);
            color: var(--ag-text-1);
            font: 500 13px/18px var(--ag-font);
            text-decoration: none;
        }
        .agency-access-denied__link:hover { background: var(--ag-active); border-color: var(--ag-line); }
        .agency-access-denied__foot {
            align-self: stretch;
            margin-top: 32px;
            padding: 14px 0 16px;
            border-top: 1px solid var(--ag-border);
            color: var(--ag-text-3);
            font: 400 11px/16px var(--ag-mono);
        }
    <?php
    wp_register_style('agency-tokens', AGENCY_PLUGIN_URL . 'assets/css/tokens.css', array(), AGENCY_VERSION);
    wp_register_style('agency-access-denied', false, array('agency-tokens'), AGENCY_VERSION);
    wp_add_inline_style('agency-access-denied', ob_get_clean());
    wp_print_styles('agency-access-denied');
    ?>
</head>
<body>
    <div class="agency-access-denied">
        <?php if ($agency_brand_logo_url) : ?>
            <div class="agency-access-denied__logo">
                <img src="<?php echo esc_url($agency_brand_logo_url); ?>" alt="<?php bloginfo('name'); ?>">
            </div>
        <?php endif; ?>

        <div class="agency-access-denied__card">
            <div class="agency-access-denied__icon"><?php Agency_Icons::render_glyph('lock', 24); ?></div>
            <h1 class="agency-access-denied__title">
                <?php echo esc_html__('Please use your link to access your space.', 'agency-client-portal'); ?>
            </h1>
            <p class="agency-access-denied__reason">
                <?php echo esc_html(!empty($error_reason) ? $error_reason : __('No access link detected. Please use the link provided by your agency.', 'agency-client-portal')); ?>
            </p>
            <a href="<?php echo esc_url($agency_home_url); ?>" class="agency-access-denied__link">
                <?php echo esc_html__('Back to home', 'agency-client-portal'); ?>
            </a>
            <div class="agency-access-denied__foot"><?php esc_html_e('Error 403 · link missing or expired', 'agency-client-portal'); ?></div>
        </div>
    </div>
</body>
</html>
