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

$agency_bh_assets   = Agency_Asset::count_by_block($agency_block->id);
$agency_bh_comments = intval(Agency_Comment::count_by_block($agency_block->id));
$agency_bh_meta     = array();
if ($agency_bh_assets > 0 && !in_array($agency_block->type, array('script', 'link', 'code'), true)) {
    /* translators: %d: number of files */
    $agency_bh_meta[] = sprintf(_n('%d file', '%d files', $agency_bh_assets, 'agency-client-portal'), $agency_bh_assets);
}
/* translators: %d: number of comments */
$agency_bh_meta[] = sprintf(_n('%d comment', '%d comments', $agency_bh_comments, 'agency-client-portal'), $agency_bh_comments);
/* translators: %s: relative time, e.g. "2 hours ago" */
$agency_bh_meta[] = sprintf(__('updated %s', 'agency-client-portal'), Agency_Template::time_ago(!empty($agency_block->updated_at) ? $agency_block->updated_at : $agency_block->created_at));

Agency_Template::crumbs(array(
    array($agency_is_manager ? $agency_client->name : __('Projects', 'agency-client-portal'), home_url("/client/{$agency_client->slug}/")),
    array($agency_project->name, home_url("/client/{$agency_client->slug}/project/{$agency_project->slug}/")),
    array($agency_block->title, ''),
));
?>
<div class="agency-page-head agency-bv-head">
    <div class="agency-page-head__main">
        <span class="agency-page-tile"><?php Agency_Icons::render_glyph($agency_block->type, 22); ?></span>
        <div class="agency-page-head__text">
            <div class="agency-page-head__title-row">
                <h1 class="agency-page-title agency-page-title--sm"><?php echo esc_html($agency_block->title); ?></h1>
                <span class="agency-tag agency-tag--client-outline"><?php echo esc_html(Agency_Block::get_type_label($agency_block->type)); ?></span>
            </div>
            <div class="agency-page-meta"><?php echo esc_html(implode(' · ', $agency_bh_meta)); ?></div>
        </div>
    </div>
    <?php if ($agency_is_manager): ?>
    <div class="agency-page-head__actions">
        <?php include AGENCY_PLUGIN_DIR . 'templates/partials/notify-client-button.php'; ?>
        <button type="button" class="agency-btn copy-block-link-btn"
                data-block-id="<?php echo esc_attr($agency_block->id); ?>"
                data-client-id="<?php echo esc_attr($agency_client->id); ?>"
                data-client-slug="<?php echo esc_attr($agency_client->slug); ?>"
                data-project-slug="<?php echo esc_attr($agency_project->slug); ?>">
            <?php Agency_Icons::render('copy', 16); ?> <?php esc_html_e('Copy link', 'agency-client-portal'); ?>
        </button>
        <div class="agency-dropdown">
            <button type="button" class="agency-btn-icon" data-agency-dropdown aria-haspopup="true" aria-expanded="false" aria-label="<?php esc_attr_e('More actions', 'agency-client-portal'); ?>">
                <?php Agency_Icons::render_ui('more', 16); ?>
            </button>
            <div class="agency-menu" role="menu">
                <a class="agency-menu__item" href="<?php echo esc_url(home_url("/client/{$agency_client->slug}/project/{$agency_project->slug}/")); ?>"><?php Agency_Icons::render_ui('external', 16); ?> <?php esc_html_e('Back to project', 'agency-client-portal'); ?></a>
                <hr class="agency-menu__sep">
                <button type="button" class="agency-menu__item agency-menu__item--danger delete-block"
                        data-block-id="<?php echo esc_attr($agency_block->id); ?>"
                        data-redirect="<?php echo esc_url(home_url("/client/{$agency_client->slug}/project/{$agency_project->slug}/")); ?>">
                    <?php Agency_Icons::render('trash', 16); ?> <?php esc_html_e('Delete', 'agency-client-portal'); ?>
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
