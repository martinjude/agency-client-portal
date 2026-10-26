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

$vi_uploader = !empty($vi_asset->uploaded_by) ? get_userdata((int) $vi_asset->uploaded_by) : null;
$vi_added    = '';
if (!empty($vi_asset->created_at)) {
    $vi_added = sprintf(
        /* translators: %s: relative time, e.g. "2 hours ago" */
        __('Added %s', 'agency-client-portal'),
        Agency_Template::time_ago($vi_asset->created_at)
    );
    if ($vi_uploader) {
        /* translators: 1: "Added 2 hours ago", 2: uploader name */
        $vi_added = sprintf(__('%1$s · by %2$s', 'agency-client-portal'), $vi_added, $vi_uploader->display_name);
    }
}
$vi_can_comment = Agency_Block::client_can_comment_allowed($block);
$vi_notes       = isset($vi_asset->voiceover) ? $vi_asset->voiceover : '';
?>
<div class="agency-bp-grid video-item video-<?php echo esc_attr($vi_kind); ?>-item<?php echo !$vi_can_comment ? ' agency-bp-grid--solo' : ''; ?>"
     data-asset-id="<?php echo esc_attr($vi_asset->id); ?>" <?php echo $vi_attrs; // phpcs:ignore WordPress.Security.EscapeOutput -- construit par le template appelant avec esc_attr ?>>
    <div class="agency-bp-main">
        <section class="agency-bp-card">
            <header class="agency-bp-card__head">
                <h4 class="agency-bp-card__title video-embed-title"><?php echo esc_html($vi_title); ?></h4>
                <div class="agency-bp-card__tools video-actions"><?php echo $vi_actions; // phpcs:ignore WordPress.Security.EscapeOutput -- construit par le template appelant ?></div>
            </header>

            <?php echo $vi_player; // phpcs:ignore WordPress.Security.EscapeOutput -- lecteur construit et filtré par le template appelant ?>

            <div class="agency-bp-tags">
                <?php foreach ($vi_tags as $vi_tag): ?>
                <span class="agency-tag agency-tag--outline"><?php echo esc_html($vi_tag); ?></span>
                <?php endforeach; ?>
                <?php if ($vi_added): ?><span class="agency-bp-tags__meta"><?php echo esc_html($vi_added); ?></span><?php endif; ?>
            </div>

            <div class="agency-bp-notes video-notes">
                <label class="agency-bp-label" for="notes-<?php echo esc_attr($vi_asset->id); ?>"><?php esc_html_e('Notes', 'agency-client-portal'); ?></label>
                <textarea id="notes-<?php echo esc_attr($vi_asset->id); ?>"
                          class="asset-notes-input"
                          data-asset-id="<?php echo esc_attr($vi_asset->id); ?>"
                          rows="3"
                          placeholder="<?php esc_attr_e('Add notes...', 'agency-client-portal'); ?>"
                          <?php echo $is_manager ? '' : 'readonly'; ?>><?php echo esc_textarea($vi_notes); ?></textarea>
                <?php if ($is_manager): ?>
                <div class="agency-bp-notes__foot">
                    <button type="button" class="agency-btn agency-btn-primary save-notes-btn" data-asset-id="<?php echo esc_attr($vi_asset->id); ?>">
                        <?php esc_html_e('Save notes', 'agency-client-portal'); ?>
                    </button>
                    <span class="notes-save-status" data-asset-id="<?php echo esc_attr($vi_asset->id); ?>"></span>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <?php if ($vi_can_comment): ?>
    <aside class="agency-bp-aside">
        <?php
        $cm_asset_id  = (int) $vi_asset->id;
        $cm_comments  = Agency_Comment::get_by_asset($vi_asset->id);
        include AGENCY_PLUGIN_DIR . 'templates/partials/block-comments.php';
        ?>
    </aside>
    <?php endif; ?>
</div>
