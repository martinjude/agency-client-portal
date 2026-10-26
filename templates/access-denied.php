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

$brand_logo_url = get_option('agency_brand_logo_url', '');
$theme_mode     = get_option('agency_theme_mode', 'light');
$is_dark        = ($theme_mode === 'dark');
$bg_color       = $is_dark ? get_option('agency_bg_color_dark', '#0b0f14') : get_option('agency_bg_color_light', '#f8f9fa');
$text_color     = $is_dark ? '#e4e4e7' : '#1a1a2e';
$muted_color    = $is_dark ? '#8b8b9e' : '#6b6b80';
$card_bg        = $is_dark ? '#16191f' : '#ffffff';
$home_url       = home_url('/');
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
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: <?php echo esc_attr($bg_color); ?>;
            color: <?php echo esc_attr($text_color); ?>;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .agency-access-denied {
            text-align: center;
            max-width: 480px;
            width: 100%;
        }
        .agency-access-denied__logo {
            margin-bottom: 40px;
        }
        .agency-access-denied__logo img {
            max-width: 180px;
            max-height: 60px;
            height: auto;
        }
        .agency-access-denied__card {
            background: <?php echo esc_attr($card_bg); ?>;
            border-radius: 16px;
            padding: 48px 36px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 4px 24px rgba(0,0,0,0.04);
        }
        .agency-access-denied__icon {
            font-size: 48px;
            margin-bottom: 20px;
            line-height: 1;
        }
        .agency-access-denied__title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 12px;
            line-height: 1.4;
        }
        .agency-access-denied__reason {
            font-size: 15px;
            color: <?php echo esc_attr($muted_color); ?>;
            line-height: 1.6;
            margin-bottom: 32px;
        }
        .agency-access-denied__link {
            display: inline-block;
            padding: 10px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            color: <?php echo esc_attr($muted_color); ?>;
            border: 1px solid <?php echo $is_dark ? '#2a2d35' : '#e0e0e0'; ?>;
            transition: opacity 0.2s;
        }
        .agency-access-denied__link:hover { opacity: 0.7; }
    <?php
    wp_register_style('agency-access-denied', false, array(), AGENCY_VERSION);
    wp_add_inline_style('agency-access-denied', ob_get_clean());
    wp_print_styles('agency-access-denied');
    ?>
</head>
<body>
    <div class="agency-access-denied">
        <?php if ($brand_logo_url) : ?>
            <div class="agency-access-denied__logo">
                <img src="<?php echo esc_url($brand_logo_url); ?>" alt="<?php bloginfo('name'); ?>">
            </div>
        <?php endif; ?>

        <div class="agency-access-denied__card">
            <div class="agency-access-denied__icon">&#128274;</div>
            <h1 class="agency-access-denied__title">
                <?php echo esc_html__('Please use your link to access your space.', 'agency-client-portal'); ?>
            </h1>
            <?php if (!empty($error_reason)) : ?>
                <p class="agency-access-denied__reason">
                    <?php echo esc_html($error_reason); ?>
                </p>
            <?php endif; ?>
            <a href="<?php echo esc_url($home_url); ?>" class="agency-access-denied__link">
                <?php echo esc_html__('Back to home', 'agency-client-portal'); ?>
            </a>
        </div>
    </div>
</body>
</html>
