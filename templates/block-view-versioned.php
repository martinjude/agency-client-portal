<?php if (!defined('ABSPATH')) { exit; } ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> >
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>

<?php
$client_slug = get_query_var('agency_client');
$project_slug = get_query_var('agency_project');
$block_id = get_query_var('agency_block');

$client = Agency_Client::get_by_slug($client_slug);
$project = $client ? Agency_Project::get_by_slug($client->id, $project_slug) : null;
$block   = Agency_Block::get_by_id($block_id);

if (!$client || !$project || !$block || $block->type !== 'versioned_media') {
    wp_die(esc_html__('Content not found', 'agency-client-portal'), 404);
}

$assets      = Agency_Asset::get_by_block($block_id);
$comments    = Agency_Comment::get_by_block($block_id);
$is_manager  = current_user_can('manage_agency_projects');
$external_links = Agency_External_Link::get_by_block($block_id);
$settings = Agency_Block::get_settings($block);
$allow_media_download = Agency_Block::is_media_download_allowed($block);
$can_download_media = $is_manager || $allow_media_download;
$token_authenticated = Agency_Security::can_access_client_by_token($client->id);
$preview_only_for_token_client = !$is_manager && $token_authenticated && !$allow_media_download;

// Trier les versions: la plus récente (numéro le plus grand) en premier
usort($assets, function($a, $b) {
    $va = isset($a->version_number) ? intval($a->version_number) : 1;
    $vb = isset($b->version_number) ? intval($b->version_number) : 1;
    if ($va === $vb) {
        return strtotime($b->created_at) <=> strtotime($a->created_at);
    }
    return $vb <=> $va;
});

$active_asset_id = !empty($assets) ? $assets[0]->id : null;
?>

<div class="agency-wrapper">

    <?php include AGENCY_PLUGIN_DIR . 'templates/partials/header.php'; ?>

    <!-- Sidebar manager -->
    <?php include AGENCY_PLUGIN_DIR . 'templates/partials/sidebar.php'; ?>

    <main class="agency-main">
        <div class="agency-container">
            <div class="agency-block-view agency-block-versioned" data-block-id="<?php echo esc_attr($block->id); ?>">
                <?php include AGENCY_PLUGIN_DIR . 'templates/partials/block-head.php'; ?>

                <div class="block-header-full agency-bv-bar">
<?php
                    $_bv_subtitle = !empty($settings['subtitle']) ? $settings['subtitle'] : '';
                    $_bv_description = $block->content ?? '';
                    ?>
                    <?php if ($is_manager || $_bv_description): ?>
                    <div class="block-description versioned-description"
                         data-placeholder="<?php esc_attr_e('Add a description…', 'agency-client-portal'); ?>"
                         <?php if ($is_manager): ?>
                         contenteditable="true"
                         id="versioned-description"
                         data-block-id="<?php echo esc_attr($block->id); ?>"
                         <?php endif; ?>>
                        <?php echo wp_kses_post($_bv_description); ?>
                    </div>
                    <?php endif; ?>

                    <div class="agency-links">
                        <?php if (!empty($external_links)): ?>
                            <div class="external-links-display">
                                <?php foreach ($external_links as $link): ?>
                                    <a href="<?php echo esc_url($link->url); ?>" class="external-link-item" target="_blank" rel="noopener" data-link-id="<?php echo esc_attr($link->id); ?>">
                                        <span class="link-emoji"><?php echo esc_html($link->emoji); ?></span>
                                        <span class="link-text"><?php echo esc_html($link->text); ?></span>
                                        <?php if ($is_manager): ?>
                                            <button class="edit-external-link" data-link-id="<?php echo esc_attr($link->id); ?>" data-link-emoji="<?php echo esc_attr($link->emoji); ?>" data-link-text="<?php echo esc_attr($link->text); ?>" data-link-url="<?php echo esc_attr($link->url); ?>" title="<?php esc_attr_e('Edit', 'agency-client-portal'); ?>"><?php Agency_Icons::render('edit', 14); ?></button>
                                            <button class="delete-external-link" data-link-id="<?php echo esc_attr($link->id); ?>" title="<?php esc_attr_e('Delete', 'agency-client-portal'); ?>">×</button>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($is_manager): ?>
                            <button class="agency-btn agency-btn-sm agency-btn-secondary" id="add-external-link-btn" type="button">
                                <?php Agency_Icons::render('link', 16); ?> <?php esc_html_e('Add a link', 'agency-client-portal'); ?>
                            </button>
                        <?php endif; ?>
                    </div>

                    <?php if ($is_manager): ?>
                        <div class="agency-actions">
                            <button class="agency-btn agency-btn-primary" id="upload-version-btn" type="button" data-accept="image/*,.svg,.pdf" data-multiple="false">
                                <?php Agency_Icons::render('paperclip', 16); ?> <?php esc_html_e('New version', 'agency-client-portal'); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <?php
                $vm_can_comment = Agency_Block::client_can_comment_allowed($block);
                $vm_statuses = array(
                    'draft'             => __('Draft', 'agency-client-portal'),
                    'approved'          => __('Approved', 'agency-client-portal'),
                    'pending'           => __('Pending', 'agency-client-portal'),
                    'rejected'          => __('Rejected', 'agency-client-portal'),
                    'in_review'         => __('In Review', 'agency-client-portal'),
                    'archived'          => __('Archived', 'agency-client-portal'),
                    'changes_requested' => __('Changes requested', 'agency-client-portal'),
                );

                // Activité : envois de versions + commentaires, du plus récent au plus ancien
                $vm_events = array();
                $vm_comments_by_asset = array();
                foreach ($assets as $ev_asset) {
                    $ev_vnum = isset($ev_asset->version_number) ? intval($ev_asset->version_number) : 1;
                    $ev_user = !empty($ev_asset->uploaded_by) ? get_userdata((int) $ev_asset->uploaded_by) : null;
                    $vm_events[] = array(
                        'type' => $ev_vnum <= 1 ? 'create' : 'upload',
                        'who'  => $ev_user ? $ev_user->display_name : get_bloginfo('name'),
                        'v'    => $ev_vnum,
                        'at'   => $ev_asset->created_at,
                    );
                    $vm_comments_by_asset[$ev_asset->id] = Agency_Comment::get_by_asset($ev_asset->id);
                    foreach ($vm_comments_by_asset[$ev_asset->id] as $ev_comment) {
                        $vm_events[] = array(
                            'type' => 'comment',
                            'who'  => isset($ev_comment->user->display_name) ? $ev_comment->user->display_name : __('Client', 'agency-client-portal'),
                            'v'    => $ev_vnum,
                            'at'   => $ev_comment->created_at,
                        );
                    }
                }
                usort($vm_events, function($x, $y) { return strtotime($y['at']) <=> strtotime($x['at']); });
                $vm_events = array_slice($vm_events, 0, 8);
                $vm_event_labels = array(
                    'create'  => array('plus', __('%1$s created %2$s', 'agency-client-portal')),
                    'upload'  => array('upload', __('%1$s uploaded %2$s', 'agency-client-portal')),
                    'comment' => array('comment', __('%1$s commented on %2$s', 'agency-client-portal')),
                );
                ?>

                <?php if (empty($assets)): ?>
                    <p class="no-assets"><?php esc_html_e('No versions yet. Add a first version.', 'agency-client-portal'); ?></p>
                <?php endif; ?>

                <?php foreach ($assets as $asset): ?>
                    <?php
                        $asset_comments = $vm_comments_by_asset[$asset->id];
                        $asset_view_url = $preview_only_for_token_client ? Agency_Asset::get_preview_url($asset->id) : Agency_Asset::get_url($asset->id);
                        $vnum      = isset($asset->version_number) ? intval($asset->version_number) : 1;
                        $vlabel    = $asset->version_label ? $asset->version_label : sprintf(/* translators: %d: version number */ __('Version %d', 'agency-client-portal'), $vnum);
                        $is_active = ($asset->id === $active_asset_id);
                        $is_latest = ($asset->id === $active_asset_id);
                        $file_ext  = strtoupper(pathinfo($asset->original_filename, PATHINFO_EXTENSION));
                        $uploader  = !empty($asset->uploaded_by) ? get_userdata((int) $asset->uploaded_by) : null;
                        $added     = sprintf(/* translators: %s: relative time */ __('Added %s', 'agency-client-portal'), Agency_Template::time_ago($asset->created_at));
                        if ($uploader) {
                            /* translators: 1: "Added 2 hours ago", 2: uploader name */
                            $added = sprintf(__('%1$s · by %2$s', 'agency-client-portal'), $added, $uploader->display_name);
                        }
                    ?>
                    <div class="agency-bp-grid version-panel <?php echo $is_active ? 'is-active' : ''; ?><?php echo !$vm_can_comment ? ' agency-bp-grid--solo' : ''; ?>"
                         data-version-id="<?php echo esc_attr($asset->id); ?>"
                         data-label="<?php echo esc_attr($asset->version_label); ?>"
                         data-notes="<?php echo esc_attr($asset->version_notes); ?>">

                        <div class="agency-bp-main">
                            <section class="agency-bp-card">
                                <header class="agency-bp-card__head">
                                    <h3 class="agency-bp-card__title"><?php echo esc_html($block->title . ' — V' . $vnum); ?></h3>
                                    <div class="agency-bp-card__tools media-actions">
                                        <?php if ($can_download_media): ?>
                                        <a href="<?php echo esc_url(Agency_Asset::get_download_url($asset->id)); ?>" class="agency-btn-icon agency-btn-icon--sm" target="_blank"
                                           title="<?php esc_attr_e('Download', 'agency-client-portal'); ?>" aria-label="<?php esc_attr_e('Download', 'agency-client-portal'); ?>"><?php Agency_Icons::render('download', 16); ?></a>
                                        <?php endif; ?>
                                        <?php if ($is_manager): ?>
                                        <button type="button" class="agency-btn-icon agency-btn-icon--sm delete-asset" data-asset-id="<?php echo esc_attr($asset->id); ?>"
                                                title="<?php esc_attr_e('Delete', 'agency-client-portal'); ?>" aria-label="<?php esc_attr_e('Delete', 'agency-client-portal'); ?>"><?php Agency_Icons::render('trash', 16); ?></button>
                                        <?php endif; ?>
                                    </div>
                                </header>

                                <div class="version-media">
                                    <span class="storyboard-pill storyboard-pill--version" style="left: 12px; right: auto;">V<?php echo esc_html($vnum); ?></span>
                                    <?php if ($is_latest): ?>
                                    <span class="agency-pill agency-pill--ok version-current"><?php esc_html_e('Current version', 'agency-client-portal'); ?></span>
                                    <?php endif; ?>
                                    <?php if ($asset->file_type === 'image'): ?>
                                        <img src="<?php echo esc_url($asset_view_url); ?>" alt="<?php echo esc_attr($asset->original_filename); ?>" class="versioned-image" loading="lazy">
                                    <?php elseif ($asset->file_type === 'document' && strtolower(pathinfo($asset->original_filename, PATHINFO_EXTENSION)) === 'pdf'): ?>
                                        <div class="versioned-pdf-preview">
                                            <iframe src="<?php echo esc_url($asset_view_url); ?>" class="pdf-preview-frame" title="<?php echo esc_attr($asset->original_filename); ?>"></iframe>
                                            <div class="pdf-download-fallback">
                                                <span class="file-name"><?php echo esc_html($asset->original_filename); ?></span>
                                                <a href="<?php echo esc_url($asset_view_url); ?>" target="_blank" class="agency-btn agency-btn-sm"><?php esc_html_e('Open PDF', 'agency-client-portal'); ?></a>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="storyboard-file-icon">
                                            <span class="file-icon"><?php Agency_Icons::render('file', 16); ?></span>
                                            <span class="file-name"><?php echo esc_html($asset->original_filename); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="agency-bp-tags">
                                    <span class="agency-tag"><?php echo esc_html(Agency_Block::get_type_label($block->type)); ?></span>
                                    <span class="agency-tag agency-tag--outline"><?php echo esc_html(trim($file_ext . ($asset->file_size ? ' · ' . size_format($asset->file_size) : ''))); ?></span>
                                    <?php if (!empty($asset->version_status)): ?>
                                    <span class="version-status agency-pill version-status-<?php echo esc_attr($asset->version_status); ?>" data-status="<?php echo esc_attr($asset->version_status); ?>" data-version-id="<?php echo esc_attr($asset->id); ?>"><?php echo esc_html(isset($vm_statuses[$asset->version_status]) ? $vm_statuses[$asset->version_status] : $asset->version_status); ?></span>
                                    <?php endif; ?>
                                    <span class="agency-bp-tags__meta"><?php echo esc_html($added); ?></span>
                                </div>

                                <?php if (!empty($asset->version_notes)): ?>
                                <p class="version-notes"><?php echo esc_html($asset->version_notes); ?></p>
                                <?php endif; ?>

                                <div class="agency-bp-notes storyboard-voiceover">
                                    <label for="voiceover-<?php echo esc_attr($asset->id); ?>" class="agency-bp-label"><?php esc_html_e('Agency note', 'agency-client-portal'); ?></label>
                                    <textarea id="voiceover-<?php echo esc_attr($asset->id); ?>" class="asset-voiceover-input" data-asset-id="<?php echo esc_attr($asset->id); ?>" rows="3" placeholder="<?php esc_attr_e('Enter an internal note...', 'agency-client-portal'); ?>" <?php echo $is_manager ? '' : 'readonly'; ?>><?php echo isset($asset->voiceover) ? esc_textarea($asset->voiceover) : ''; ?></textarea>
                                    <?php if ($is_manager): ?>
                                    <div class="agency-bp-notes__foot">
                                        <button type="button" class="agency-btn agency-btn-primary save-voiceover-btn" data-asset-id="<?php echo esc_attr($asset->id); ?>"><?php esc_html_e('Save note', 'agency-client-portal'); ?></button>
                                        <span class="voiceover-save-status" data-asset-id="<?php echo esc_attr($asset->id); ?>"></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </section>

                            <?php if (!empty($vm_events)): ?>
                            <section class="agency-bp-card version-activity">
                                <header class="agency-bp-card__head agency-bp-card__head--rule">
                                    <h3 class="agency-bp-card__title"><?php esc_html_e('Activity', 'agency-client-portal'); ?></h3>
                                </header>
                                <ol class="version-activity__list">
                                    <?php foreach ($vm_events as $ev): ?>
                                    <li class="version-activity__item">
                                        <span class="version-activity__icon"><?php Agency_Icons::render($vm_event_labels[$ev['type']][0], 14); ?></span>
                                        <div>
                                            <p class="version-activity__text"><?php
                                            echo wp_kses(sprintf($vm_event_labels[$ev['type']][1], '<strong>' . esc_html($ev['who']) . '</strong>', '<strong>V' . intval($ev['v']) . '</strong>'), array('strong' => array()));
                                            ?></p>
                                            <span class="version-activity__time"><?php echo esc_html(Agency_Template::time_ago($ev['at'])); ?></span>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                </ol>
                            </section>
                            <?php endif; ?>
                        </div>

                        <aside class="agency-bp-aside">
                            <?php if (count($assets) > 1 || $is_manager): ?>
                            <section class="agency-bp-card version-list" aria-label="<?php esc_attr_e('Versions', 'agency-client-portal'); ?>">
                                <header class="agency-bp-card__head agency-bp-card__head--rule">
                                    <h3 class="agency-bp-card__title"><?php esc_html_e('Versions', 'agency-client-portal'); ?></h3>
                                    <span class="agency-count"><?php echo intval(count($assets)); ?></span>
                                </header>
                                <div class="version-list__rows">
                                    <?php foreach ($assets as $row_idx => $row): ?>
                                    <?php
                                        $row_vnum = isset($row->version_number) ? intval($row->version_number) : 1;
                                        $row_user = !empty($row->uploaded_by) ? get_userdata((int) $row->uploaded_by) : null;
                                        $row_meta = date_i18n(get_option('date_format'), strtotime($row->created_at)) . ($row_user ? ' · ' . $row_user->display_name : '');
                                    ?>
                                    <div class="version-tab version-row<?php echo $row->id === $asset->id ? ' is-active' : ''; ?><?php echo $row_idx >= 4 ? ' version-row--more' : ''; ?>" role="button" tabindex="0" data-version-id="<?php echo esc_attr($row->id); ?>"<?php echo $row_idx >= 4 ? ' hidden' : ''; ?>>
                                        <span class="version-row__icon"><?php Agency_Icons::render('file', 14); ?></span>
                                        <span class="version-row__text">
                                            <strong class="version-row__name"><?php echo esc_html($row->version_label ? $row->version_label : sprintf(/* translators: %d: version number */ __('Version %d', 'agency-client-portal'), $row_vnum)); ?></strong>
                                            <span class="version-row__meta"><?php echo esc_html($row_meta); ?></span>
                                        </span>
                                        <?php if ($row->id === $asset->id): ?><span class="version-row__check"><?php Agency_Icons::render_ui('check', 16); ?></span><?php endif; ?>
                                        <?php if ($is_manager): ?>
                                        <button type="button" class="version-delete-btn" data-version-id="<?php echo esc_attr($row->id); ?>" title="<?php esc_attr_e('Delete this version', 'agency-client-portal'); ?>" aria-label="<?php esc_attr_e('Delete this version', 'agency-client-portal'); ?>">×</button>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (count($assets) > 4): ?>
                                <button type="button" class="agency-btn version-list__more" data-more-label="<?php esc_attr_e('See more', 'agency-client-portal'); ?>" data-less-label="<?php esc_attr_e('See less', 'agency-client-portal'); ?>"><?php esc_html_e('See more', 'agency-client-portal'); ?></button>
                                <?php endif; ?>
                            </section>
                            <?php endif; ?>

                            <?php if ($vm_can_comment): ?>
                                <?php
                                $cm_asset_id = (int) $asset->id;
                                $cm_comments = $asset_comments;
                                include AGENCY_PLUGIN_DIR . 'templates/partials/block-comments.php';
                                ?>
                            <?php endif; ?>
                        </aside>
                    </div>
                <?php endforeach; ?>

            </div>
        </div>
    </main>

    <?php include AGENCY_PLUGIN_DIR . 'templates/partials/footer.php'; ?>
</div>

<?php if ($is_manager): ?>
<!-- Modal nouvelle version -->
<div class="agency-modal" id="new-version-modal">
    <div class="agency-modal-content">
        <div class="agency-modal-header">
            <h3 class="agency-modal-title"><?php esc_html_e('Add a version', 'agency-client-portal'); ?></h3>
            <button class="agency-modal-close" type="button">&times;</button>
        </div>
        <div class="agency-modal-body">
            <div class="agency-form-group">
                <label class="agency-form-label" for="version-file"><?php esc_html_e('File (JPEG/PNG/SVG/PDF)', 'agency-client-portal'); ?></label>
                <input type="file" id="version-file" class="agency-form-input" accept="image/*,.svg,.pdf" required>
            </div>
            <div class="agency-form-group">
                <label class="agency-form-label" for="version-label"><?php esc_html_e('Version name', 'agency-client-portal'); ?></label>
                <input type="text" id="version-label" class="agency-form-input" placeholder="<?php esc_attr_e('e.g. V2 - Color adjustments', 'agency-client-portal'); ?>">
            </div>
            <div class="agency-form-group">
                <label class="agency-form-label" for="version-status"><?php esc_html_e('Status', 'agency-client-portal'); ?></label>
                <select id="version-status" class="agency-form-input">
                    <option value="draft"><?php esc_html_e('Draft', 'agency-client-portal'); ?></option>
                    <option value="in_review"><?php esc_html_e('In review', 'agency-client-portal'); ?></option>
                    <option value="approved"><?php esc_html_e('Approved', 'agency-client-portal'); ?></option>
                    <option value="archived"><?php esc_html_e('Archived', 'agency-client-portal'); ?></option>
                </select>
            </div>
            <div class="agency-form-group">
                <label class="agency-form-label" for="version-notes"><?php esc_html_e('Notes (optional)', 'agency-client-portal'); ?></label>
                <textarea id="version-notes" class="agency-form-input" rows="3" placeholder="<?php esc_attr_e('Internal notes or context for this version', 'agency-client-portal'); ?>"></textarea>
            </div>
        </div>
        <div class="agency-modal-footer">
            <button class="agency-btn agency-btn-secondary modal-cancel" type="button"><?php esc_html_e('Cancel', 'agency-client-portal'); ?></button>
            <button class="agency-btn agency-btn-primary" id="create-version-btn" type="button"><?php esc_html_e('Add version', 'agency-client-portal'); ?></button>
        </div>
    </div>
</div>

<!-- Modal d'édition de version -->
<div class="agency-modal" id="edit-version-modal">
    <div class="agency-modal-content">
        <div class="agency-modal-header">
            <h3 class="agency-modal-title"><?php esc_html_e('Edit version', 'agency-client-portal'); ?></h3>
            <button class="agency-modal-close" type="button">&times;</button>
        </div>
        <div class="agency-modal-body">
            <input type="hidden" id="edit-version-id">
            <div class="agency-form-group">
                <label class="agency-form-label" for="edit-version-label"><?php esc_html_e('Version name', 'agency-client-portal'); ?></label>
                <input type="text" id="edit-version-label" class="agency-form-input" placeholder="<?php esc_attr_e('e.g. V2 - Color adjustments', 'agency-client-portal'); ?>">
            </div>
            <div class="agency-form-group">
                <label class="agency-form-label" for="edit-version-status"><?php esc_html_e('Status', 'agency-client-portal'); ?></label>
                <select id="edit-version-status" class="agency-form-input">
                    <option value="draft"><?php esc_html_e('Draft', 'agency-client-portal'); ?></option>
                    <option value="in_review"><?php esc_html_e('In review', 'agency-client-portal'); ?></option>
                    <option value="approved"><?php esc_html_e('Approved', 'agency-client-portal'); ?></option>
                    <option value="archived"><?php esc_html_e('Archived', 'agency-client-portal'); ?></option>
                </select>
            </div>
            <div class="agency-form-group">
                <label class="agency-form-label" for="edit-version-notes"><?php esc_html_e('Notes (optional)', 'agency-client-portal'); ?></label>
                <textarea id="edit-version-notes" class="agency-form-input" rows="3" placeholder="<?php esc_attr_e('Internal notes or context for this version', 'agency-client-portal'); ?>"></textarea>
            </div>
        </div>
        <div class="agency-modal-footer">
            <button class="agency-btn agency-btn-secondary modal-cancel" type="button"><?php esc_html_e('Cancel', 'agency-client-portal'); ?></button>
            <button class="agency-btn agency-btn-primary" id="save-version-btn" type="button"><?php esc_html_e('Save', 'agency-client-portal'); ?></button>
        </div>
    </div>
</div>

<!-- Modal de création de lien externe -->
<div class="agency-modal" id="new-external-link-modal">
    <div class="agency-modal-content">
        <div class="agency-modal-header">
            <h3 class="agency-modal-title"><?php esc_html_e('Add an external link', 'agency-client-portal'); ?></h3>
            <button class="agency-modal-close" type="button">&times;</button>
        </div>
        <div class="agency-modal-body">
            <div class="agency-form-group">
                <label class="agency-form-label"><?php esc_html_e('Emoji', 'agency-client-portal'); ?></label>
                <div data-agency-emoji-picker
                     data-default-emoji="🔗"
                     data-input-field="new-link-emoji"></div>
                <input type="hidden" id="new-link-emoji" value="🔗">
            </div>
            <div class="agency-form-group">
                <label class="agency-form-label" for="new-link-text"><?php esc_html_e('Link text', 'agency-client-portal'); ?></label>
                <input type="text" id="new-link-text" class="agency-form-input" placeholder="<?php esc_attr_e('e.g. Presentation', 'agency-client-portal'); ?>" required>
            </div>
            <div class="agency-form-group">
                <label class="agency-form-label" for="new-link-url"><?php esc_html_e('URL', 'agency-client-portal'); ?></label>
                <input type="url" id="new-link-url" class="agency-form-input" placeholder="<?php esc_attr_e('https://...', 'agency-client-portal'); ?>" required>
            </div>
        </div>
        <div class="agency-modal-footer">
            <button class="agency-btn agency-btn-secondary modal-cancel" type="button"><?php esc_html_e('Cancel', 'agency-client-portal'); ?></button>
            <button class="agency-btn agency-btn-primary" id="create-external-link-btn" type="button"><?php esc_html_e('Add link', 'agency-client-portal'); ?></button>
        </div>
    </div>
</div>

<!-- Modal d'édition de lien externe -->
<div class="agency-modal" id="edit-external-link-modal">
    <div class="agency-modal-content">
        <div class="agency-modal-header">
            <h3 class="agency-modal-title"><?php esc_html_e('Edit external link', 'agency-client-portal'); ?></h3>
            <button class="agency-modal-close" type="button">&times;</button>
        </div>
        <div class="agency-modal-body">
            <input type="hidden" id="edit-link-id">
            <div class="agency-form-group">
                <label class="agency-form-label"><?php esc_html_e('Emoji', 'agency-client-portal'); ?></label>
                <div data-agency-emoji-picker
                     data-default-emoji="🔗"
                     data-input-field="edit-link-emoji"></div>
                <input type="hidden" id="edit-link-emoji" value="🔗">
            </div>
            <div class="agency-form-group">
                <label class="agency-form-label" for="edit-link-text"><?php esc_html_e('Link text', 'agency-client-portal'); ?></label>
                <input type="text" id="edit-link-text" class="agency-form-input" required>
            </div>
            <div class="agency-form-group">
                <label class="agency-form-label" for="edit-link-url"><?php esc_html_e('URL', 'agency-client-portal'); ?></label>
                <input type="url" id="edit-link-url" class="agency-form-input" placeholder="<?php esc_attr_e('https://...', 'agency-client-portal'); ?>" required>
            </div>
        </div>
        <div class="agency-modal-footer">
            <button class="agency-btn agency-btn-secondary modal-cancel" type="button"><?php esc_html_e('Cancel', 'agency-client-portal'); ?></button>
            <button class="agency-btn agency-btn-primary" id="update-external-link-btn" type="button"><?php esc_html_e('Update', 'agency-client-portal'); ?></button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php ob_start(); ?>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.agency-block-subtitle--editable').forEach(function(el) {
        var blockId = el.getAttribute('data-block-id');
        el.addEventListener('blur', function() {
            var text = el.textContent.trim();
            if (typeof agencyData !== 'undefined') {
                fetch(agencyData.rest_url + 'blocks/' + blockId, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': agencyData.nonce },
                    body: JSON.stringify({ settings: { subtitle: text } })
                });
            }
        });
    });

    // Auto-save versioned description (saves to block content)
    var versionedDesc = document.getElementById('versioned-description');
    if (versionedDesc && typeof agencyData !== 'undefined') {
        var descTimer = null;
        versionedDesc.addEventListener('input', function() {
            clearTimeout(descTimer);
            descTimer = setTimeout(function() {
                fetch(agencyData.rest_url + 'blocks/' + versionedDesc.getAttribute('data-block-id'), {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': agencyData.nonce },
                    body: JSON.stringify({ content: versionedDesc.innerHTML })
                });
            }, 1200);
        });
    }
});
<?php wp_print_inline_script_tag(ob_get_clean()); ?>
<?php wp_footer(); ?>

<?php ob_start(); ?>
(function(){
    // Bascule entre versions (lignes du panneau « Versions »)
    const panels = document.querySelectorAll('.version-panel');
    function showVersion(id) {
        panels.forEach(p => p.classList.toggle('is-active', p.getAttribute('data-version-id') === String(id)));
    }
    document.querySelectorAll('.version-tab').forEach(tab => {
        tab.addEventListener('click', (e) => {
            if (e.target.closest('.version-delete-btn')) return;
            showVersion(tab.getAttribute('data-version-id'));
        });
        tab.addEventListener('keydown', (e) => {
            if ((e.key === 'Enter' || e.key === ' ') && e.target === tab) {
                e.preventDefault();
                tab.click();
            }
        });
    });

    // « Voir plus » : déplie les versions au-delà de la quatrième
    document.querySelectorAll('.version-list__more').forEach(btn => {
        btn.addEventListener('click', () => {
            const list = btn.closest('.version-list');
            const open = btn.getAttribute('data-open') === '1';
            list.querySelectorAll('.version-row--more').forEach(r => { r.hidden = open; });
            btn.setAttribute('data-open', open ? '0' : '1');
            btn.textContent = btn.getAttribute(open ? 'data-more-label' : 'data-less-label');
        });
    });

    <?php if ($is_manager): ?>
    // Delete version
    document.querySelectorAll('.version-delete-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (!confirm(<?php echo wp_json_encode(__('Are you sure you want to delete this version? All associated comments will also be deleted.', 'agency-client-portal')); ?>)) {
                return;
            }
            const versionId = this.getAttribute('data-version-id');
            fetch('<?php echo esc_url(rest_url('agency/v1/assets/')); ?>' + versionId, {
                method: 'DELETE',
                headers: { 'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(<?php echo wp_json_encode(__('Error while deleting', 'agency-client-portal')); ?>);
                }
            });
        });
    });

    // Edit version modal
    const editModal = new AgencyModal('edit-version-modal');
    const editCancelBtns = document.querySelectorAll('#edit-version-modal .modal-cancel, #edit-version-modal .agency-modal-close');
    editCancelBtns.forEach(btn => btn.addEventListener('click', () => editModal.close()));

    // Click sur le badge de statut pour ouvrir la modal d'édition
    document.querySelectorAll('.version-status').forEach(badge => {
        badge.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const versionId = this.getAttribute('data-version-id');
            const versionPanel = document.querySelector('.version-panel[data-version-id="' + versionId + '"]');
            if (!versionPanel) return;
            
            // Récupérer les données de la version depuis le panel
            const currentLabel = versionPanel.getAttribute('data-label') || '';
            const currentStatus = this.getAttribute('data-status') || 'draft';
            const currentNotes = versionPanel.getAttribute('data-notes') || '';
            
            // Remplir la modal
            document.getElementById('edit-version-id').value = versionId;
            document.getElementById('edit-version-label').value = currentLabel;
            document.getElementById('edit-version-status').value = currentStatus;
            document.getElementById('edit-version-notes').value = currentNotes;
            
            editModal.open();
        });
    });

    // Sauvegarder les modifications
    document.getElementById('save-version-btn').addEventListener('click', function() {
        const versionId = document.getElementById('edit-version-id').value;
        const label = document.getElementById('edit-version-label').value.trim();
        const status = document.getElementById('edit-version-status').value;
        const notes = document.getElementById('edit-version-notes').value.trim();
        
        this.disabled = true;
        this.textContent = <?php echo wp_json_encode(__('Saving...', 'agency-client-portal')); ?>;
        
        fetch('<?php echo esc_url(rest_url('agency/v1/assets/')); ?>' + versionId, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>'
            },
            body: JSON.stringify({
                version_label: label,
                version_status: status,
                version_notes: notes
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert(<?php echo wp_json_encode(__('Error while updating', 'agency-client-portal')); ?>);
                this.disabled = false;
                this.textContent = <?php echo wp_json_encode(__('Save', 'agency-client-portal')); ?>;
            }
        });
    });

    // Modal logic
    if (typeof AgencyModal !== 'undefined') {
        const modal = new AgencyModal('new-version-modal');
        const openBtn = document.getElementById('upload-version-btn');
        const closeBtns = document.querySelectorAll('#new-version-modal .agency-modal-close, #new-version-modal .modal-cancel');
        const createBtn = document.getElementById('create-version-btn');
        const fileInput = document.getElementById('version-file');

        if (openBtn && modal) {
            openBtn.addEventListener('click', () => { modal.open(); });
        }
        closeBtns.forEach(btn => btn.addEventListener('click', () => modal.close()));

        if (createBtn) {
            createBtn.addEventListener('click', function(){
                if (!fileInput.files || !fileInput.files[0]) {
                    alert(<?php echo wp_json_encode(__('Please select a file', 'agency-client-portal')); ?>);
                    return;
                }
                const fd = new FormData();
                fd.append('block_id', <?php echo intval($block->id); ?>);
                fd.append('file', fileInput.files[0]);
                fd.append('version_label', document.getElementById('version-label').value.trim());
                fd.append('version_status', document.getElementById('version-status').value);
                fd.append('version_notes', document.getElementById('version-notes').value.trim());

                createBtn.disabled = true;
                createBtn.textContent = <?php echo wp_json_encode(__('Creating...', 'agency-client-portal')); ?>;

                fetch('<?php echo esc_url(rest_url('agency/v1/assets/upload')); ?>', {
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>'
                    },
                    body: fd
                })
                .then(res => res.json())
                .then(data => {
                    createBtn.disabled = false;
                    createBtn.textContent = <?php echo wp_json_encode(__('Add version', 'agency-client-portal')); ?>;
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert(data.message || <?php echo wp_json_encode(__('Error during upload', 'agency-client-portal')); ?>);
                    }
                })
                .catch(err => {
                    console.error(err);
                    createBtn.disabled = false;
                    createBtn.textContent = <?php echo wp_json_encode(__('Add version', 'agency-client-portal')); ?>;
                    alert(<?php echo wp_json_encode(__('Error during upload', 'agency-client-portal')); ?>);
                });
            });
        }

        // External link modal
        const addLinkBtn = document.getElementById('add-external-link-btn');
        const createLinkBtn = document.getElementById('create-external-link-btn');
        const linkModal = new AgencyModal('new-external-link-modal');
        const linkCancelBtns = document.querySelectorAll('#new-external-link-modal .modal-cancel, #new-external-link-modal .agency-modal-close');

        if (addLinkBtn && linkModal) {
            addLinkBtn.addEventListener('click', () => linkModal.open());
        }
        linkCancelBtns.forEach(btn => btn.addEventListener('click', () => linkModal.close()));

        if (createLinkBtn) {
            createLinkBtn.addEventListener('click', function() {
                const emoji = document.getElementById('new-link-emoji').value.trim();
                const text = document.getElementById('new-link-text').value.trim();
                const url = document.getElementById('new-link-url').value.trim();

                if (!emoji || !text || !url) {
                    alert(<?php echo wp_json_encode(__('Please fill in all fields', 'agency-client-portal')); ?>);
                    return;
                }

                createLinkBtn.disabled = true;
                createLinkBtn.textContent = <?php echo wp_json_encode(__('Creating...', 'agency-client-portal')); ?>;

                fetch('<?php echo esc_url(rest_url('agency/v1/external-links')); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>'
                    },
                    body: JSON.stringify({
                        block_id: <?php echo intval($block->id); ?>,
                        emoji: emoji,
                        text: text,
                        url: url
                    })
                })
                .then(res => res.json())
                .then(data => {
                    createLinkBtn.disabled = false;
                    createLinkBtn.textContent = <?php echo wp_json_encode(__('Add link', 'agency-client-portal')); ?>;
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert(<?php echo wp_json_encode(__('Error while creating the link', 'agency-client-portal')); ?>);
                    }
                })
                .catch(err => {
                    console.error(err);
                    createLinkBtn.disabled = false;
                    createLinkBtn.textContent = <?php echo wp_json_encode(__('Add link', 'agency-client-portal')); ?>;
                    alert(<?php echo wp_json_encode(__('Error while creating the link', 'agency-client-portal')); ?>);
                });
            });
        }

        // Delete external link
        document.querySelectorAll('.delete-external-link').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (!confirm(<?php echo wp_json_encode(__('Are you sure you want to delete this link?', 'agency-client-portal')); ?>)) {
                    return;
                }
                const linkId = btn.getAttribute('data-link-id');
                fetch('<?php echo esc_url(rest_url('agency/v1/external-links/')); ?>' + linkId, {
                    method: 'DELETE',
                    headers: { 'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>' }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert(<?php echo wp_json_encode(__('Error while deleting the link', 'agency-client-portal')); ?>);
                    }
                });
            });
        });

        // Edit external link
        const editLinkModal = new AgencyModal('edit-external-link-modal');
        const editLinkCancelBtns = document.querySelectorAll('#edit-external-link-modal .modal-cancel, #edit-external-link-modal .agency-modal-close');
        editLinkCancelBtns.forEach(btn => btn.addEventListener('click', () => editLinkModal.close()));

        document.querySelectorAll('.edit-external-link').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const linkId = btn.getAttribute('data-link-id');
                const linkEmoji = btn.getAttribute('data-link-emoji');
                const linkText = btn.getAttribute('data-link-text');
                const linkUrl = btn.getAttribute('data-link-url');

                document.getElementById('edit-link-id').value = linkId;
                document.getElementById('edit-link-emoji').value = linkEmoji;
                document.getElementById('edit-link-text').value = linkText;
                document.getElementById('edit-link-url').value = linkUrl;

                const emojiDisplay = document.querySelector('[data-input-field="edit-link-emoji"]');
                if (emojiDisplay) {
                    const emojiSpan = emojiDisplay.querySelector('.emoji-display');
                    if (emojiSpan) emojiSpan.textContent = linkEmoji;
                }

                editLinkModal.open();
            });
        });

        // Update external link
        const updateLinkBtn = document.getElementById('update-external-link-btn');
        if (updateLinkBtn) {
            updateLinkBtn.addEventListener('click', function() {
                const linkId = document.getElementById('edit-link-id').value;
                const emoji = document.getElementById('edit-link-emoji').value.trim();
                const text = document.getElementById('edit-link-text').value.trim();
                const url = document.getElementById('edit-link-url').value.trim();

                if (!emoji || !text || !url) {
                    alert(<?php echo wp_json_encode(__('Please fill in all fields', 'agency-client-portal')); ?>);
                    return;
                }

                updateLinkBtn.disabled = true;
                updateLinkBtn.textContent = <?php echo wp_json_encode(__('Updating...', 'agency-client-portal')); ?>;

                fetch('<?php echo esc_url(rest_url('agency/v1/external-links/')); ?>' + linkId, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>'
                    },
                    body: JSON.stringify({ emoji: emoji, text: text, url: url })
                })
                .then(res => res.json())
                .then(data => {
                    updateLinkBtn.disabled = false;
                    updateLinkBtn.textContent = <?php echo wp_json_encode(__('Update', 'agency-client-portal')); ?>;
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert(<?php echo wp_json_encode(__('Error while updating the link', 'agency-client-portal')); ?>);
                    }
                })
                .catch(err => {
                    console.error(err);
                    updateLinkBtn.disabled = false;
                    updateLinkBtn.textContent = <?php echo wp_json_encode(__('Update', 'agency-client-portal')); ?>;
                    alert(<?php echo wp_json_encode(__('Error while updating the link', 'agency-client-portal')); ?>);
                });
            });
        }
    }
    <?php endif; ?>
})();
<?php wp_print_inline_script_tag(ob_get_clean()); ?>

</body>
</html>
