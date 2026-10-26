<?php
/**
 * Carte « Aperçu client » du bloc lien (planche 15).
 *
 * Attendu en scope : $block, $link_url, $bg_image_url.
 *
 * @package Agency
 */
if (!defined('ABSPATH')) { exit; }

$agency_lp_host = $link_url ? wp_parse_url($link_url, PHP_URL_HOST) : '';
$agency_lp_host = $agency_lp_host ? preg_replace('/^www\./i', '', $agency_lp_host) : '';
?>
<section class="agency-bp-card agency-link-preview">
    <span class="agency-bp-label"><?php esc_html_e('Client preview', 'agency-client-portal'); ?></span>
    <div class="agency-link-display<?php echo $bg_image_url ? ' has-block-bg' : ''; ?>"
         <?php if ($bg_image_url): ?>style="background-image: url('<?php echo esc_url($bg_image_url); ?>');"<?php endif; ?>>
        <?php if ($link_url): ?>
        <div class="agency-link-display-inner">
            <span class="agency-link-tile"><?php Agency_Icons::render_glyph('link', 22); ?></span>
            <p class="link-label"><?php echo esc_html($block->title); ?></p>
            <?php if ($agency_lp_host): ?><p class="agency-link-host"><?php echo esc_html($agency_lp_host); ?></p><?php endif; ?>
            <a href="<?php echo esc_url($link_url); ?>" class="agency-btn agency-btn-primary agency-link-open-btn" target="_blank" rel="noopener noreferrer">
                <?php esc_html_e('Open link', 'agency-client-portal'); ?> →
            </a>
        </div>
        <?php else: ?>
        <p class="agency-link-empty"><?php echo esc_html__('No link yet.', 'agency-client-portal'); ?></p>
        <?php endif; ?>
    </div>
</section>
