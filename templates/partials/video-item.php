<?php
/**
 * Carte d'une vidéo (embed ou fichier) + panneau de commentaires (planche 11).
 *
 * Attendu en scope : $block, $is_manager, $client, $vi_asset, $vi_kind ('embed'|'upload'),
 * $vi_title, $vi_player (HTML déjà échappé), $vi_tags (liste de libellés),
 * $vi_actions (HTML déjà échappé : boutons d'en-tête), $vi_attrs (attributs data-*).
 *
 * @package Agency
 */
if (!defined('ABSPATH')) { exit; }

$agency_vi_uploader = !empty($agency_vi_asset->uploaded_by) ? get_userdata((int) $agency_vi_asset->uploaded_by) : null;
$agency_vi_added    = '';
if (!empty($agency_vi_asset->created_at)) {
    $agency_vi_added = sprintf(
        /* translators: %s: relative time, e.g. "2 hours ago" */
        __('Added %s', 'agency-client-portal'),
        Agency_Template::time_ago($agency_vi_asset->created_at)
    );
    if ($agency_vi_uploader) {
        /* translators: 1: "Added 2 hours ago", 2: uploader name */
        $agency_vi_added = sprintf(__('%1$s · by %2$s', 'agency-client-portal'), $agency_vi_added, $agency_vi_uploader->display_name);
    }
}
$agency_vi_can_comment = Agency_Block::client_can_comment_allowed($agency_block);
$agency_vi_notes       = isset($agency_vi_asset->voiceover) ? $agency_vi_asset->voiceover : '';
?>
<div class="agency-bp-grid video-item video-<?php echo esc_attr($agency_vi_kind); ?>-item<?php echo !$agency_vi_can_comment ? ' agency-bp-grid--solo' : ''; ?>"
     data-asset-id="<?php echo esc_attr($agency_vi_asset->id); ?>" <?php echo $agency_vi_attrs; // phpcs:ignore WordPress.Security.EscapeOutput -- construit par le template appelant avec esc_attr ?>>
    <div class="agency-bp-main">
        <section class="agency-bp-card">
            <header class="agency-bp-card__head">
                <h4 class="agency-bp-card__title video-embed-title"><?php echo esc_html($agency_vi_title); ?></h4>
                <div class="agency-bp-card__tools video-actions"><?php echo $agency_vi_actions; // phpcs:ignore WordPress.Security.EscapeOutput -- construit par le template appelant ?></div>
            </header>

            <?php echo $agency_vi_player; // phpcs:ignore WordPress.Security.EscapeOutput -- lecteur construit et filtré par le template appelant ?>

            <div class="agency-bp-tags">
                <?php foreach ($agency_vi_tags as $agency_vi_tag): ?>
                <span class="agency-tag agency-tag--outline"><?php echo esc_html($agency_vi_tag); ?></span>
                <?php endforeach; ?>
                <?php if ($agency_vi_added): ?><span class="agency-bp-tags__meta"><?php echo esc_html($agency_vi_added); ?></span><?php endif; ?>
            </div>

            <div class="agency-bp-notes video-notes">
                <label class="agency-bp-label" for="notes-<?php echo esc_attr($agency_vi_asset->id); ?>"><?php esc_html_e('Notes', 'agency-client-portal'); ?></label>
                <textarea id="notes-<?php echo esc_attr($agency_vi_asset->id); ?>"
                          class="asset-notes-input"
                          data-asset-id="<?php echo esc_attr($agency_vi_asset->id); ?>"
                          rows="3"
                          placeholder="<?php esc_attr_e('Add notes...', 'agency-client-portal'); ?>"
                          <?php echo $agency_is_manager ? '' : 'readonly'; ?>><?php echo esc_textarea($agency_vi_notes); ?></textarea>
                <?php if ($agency_is_manager): ?>
                <div class="agency-bp-notes__foot">
                    <button type="button" class="agency-btn agency-btn-primary save-notes-btn" data-asset-id="<?php echo esc_attr($agency_vi_asset->id); ?>">
                        <?php esc_html_e('Save notes', 'agency-client-portal'); ?>
                    </button>
                    <span class="notes-save-status" data-asset-id="<?php echo esc_attr($agency_vi_asset->id); ?>"></span>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <?php if ($agency_vi_can_comment): ?>
    <aside class="agency-bp-aside">
        <?php
        $agency_cm_asset_id  = (int) $agency_vi_asset->id;
        $agency_cm_comments  = Agency_Comment::get_by_asset($agency_vi_asset->id);
        include AGENCY_PLUGIN_DIR . 'templates/partials/block-comments.php';
        ?>
    </aside>
    <?php endif; ?>
</div>
