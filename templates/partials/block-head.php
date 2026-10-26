<?php
/**
 * En-tête des pages de bloc (planches 11–17) : fil d'Ariane, tuile du type,
 * titre, tag, méta et actions (Notifier le client, Copier le lien).
 *
 * Attendu en scope : $block, $client, $project, $is_manager.
 *
 * @package Agency
 */
if (!defined('ABSPATH')) { exit; }

$agency_bh_assets   = Agency_Asset::count_by_block($block->id);
$agency_bh_comments = intval(Agency_Comment::count_by_block($block->id));
$agency_bh_meta     = array();
if ($agency_bh_assets > 0 && !in_array($block->type, array('script', 'link', 'code'), true)) {
    /* translators: %d: number of files */
    $agency_bh_meta[] = sprintf(_n('%d file', '%d files', $agency_bh_assets, 'agency-client-portal'), $agency_bh_assets);
}
/* translators: %d: number of comments */
$agency_bh_meta[] = sprintf(_n('%d comment', '%d comments', $agency_bh_comments, 'agency-client-portal'), $agency_bh_comments);
/* translators: %s: relative time, e.g. "2 hours ago" */
$agency_bh_meta[] = sprintf(__('updated %s', 'agency-client-portal'), Agency_Template::time_ago(!empty($block->updated_at) ? $block->updated_at : $block->created_at));

Agency_Template::crumbs(array(
    array($is_manager ? $client->name : __('Projects', 'agency-client-portal'), home_url("/client/{$client->slug}/")),
    array($project->name, home_url("/client/{$client->slug}/project/{$project->slug}/")),
    array($block->title, ''),
));
?>
<div class="agency-page-head agency-bv-head">
    <div class="agency-page-head__main">
        <span class="agency-page-tile"><?php Agency_Icons::render_glyph($block->type, 22); ?></span>
        <div class="agency-page-head__text">
            <div class="agency-page-head__title-row">
                <h1 class="agency-page-title agency-page-title--sm"><?php echo esc_html($block->title); ?></h1>
                <span class="agency-tag agency-tag--client-outline"><?php echo esc_html(Agency_Block::get_type_label($block->type)); ?></span>
            </div>
            <div class="agency-page-meta"><?php echo esc_html(implode(' · ', $agency_bh_meta)); ?></div>
        </div>
    </div>
    <?php if ($is_manager): ?>
    <div class="agency-page-head__actions">
        <?php include AGENCY_PLUGIN_DIR . 'templates/partials/notify-client-button.php'; ?>
        <button type="button" class="agency-btn copy-block-link-btn"
                data-block-id="<?php echo esc_attr($block->id); ?>"
                data-client-id="<?php echo esc_attr($client->id); ?>"
                data-client-slug="<?php echo esc_attr($client->slug); ?>"
                data-project-slug="<?php echo esc_attr($project->slug); ?>">
            <?php Agency_Icons::render('copy', 16); ?> <?php esc_html_e('Copy link', 'agency-client-portal'); ?>
        </button>
    </div>
    <?php endif; ?>
</div>
