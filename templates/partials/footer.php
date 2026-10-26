<?php
/**
 * Pied de page compact (côté client uniquement — planche « 0 Header and Footer »).
 */
if (!defined('ABSPATH')) { exit; }

if (current_user_can('manage_agency_projects')) {
    return;
}

$agency_footer_logo = (string) get_option('agency_brand_logo_url', '');
$agency_footer_name = get_bloginfo('name');
?>
<footer class="agency-footer">
    <div class="agency-footer__inner">
        <div class="agency-footer__brand">
            <span class="agency-footer__logo">
                <?php if ($agency_footer_logo): ?>
                    <img src="<?php echo esc_url($agency_footer_logo); ?>" alt="" onerror="this.remove()">
                <?php else: ?>
                    <?php Agency_Icons::render_glyph('leaf', 16); ?>
                <?php endif; ?>
            </span>
            <span>&copy; <?php echo esc_html(wp_date('Y') . ' ' . $agency_footer_name); ?></span>
        </div>
        <span class="agency-footer__secure">
            <?php Agency_Icons::render_glyph('lock', 14); ?>
            <?php esc_html_e('Secure link · do not share', 'agency-client-portal'); ?>
        </span>
    </div>
</footer>
