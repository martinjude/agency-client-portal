<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Block View: Storyboard
 * Per-slide versioning + client suggestions
 */
$agency_client_slug  = get_query_var('agency_client');
$agency_project_slug = get_query_var('agency_project');
$agency_block_id     = get_query_var('agency_block');

$agency_client  = Agency_Client::get_by_slug($agency_client_slug);
$agency_project = $agency_client ? Agency_Project::get_by_slug($agency_client->id, $agency_project_slug) : null;
$agency_block   = Agency_Block::get_by_id($agency_block_id);

if (!$agency_client || !$agency_project || !$agency_block || $agency_block->type !== 'storyboard') {
    wp_die(esc_html__('Content not found', 'agency-client-portal'), 404);
}

// Vérifier l'accès
$agency_token_authenticated = Agency_Security::can_access_client_by_token($agency_client->id);
$agency_wp_authenticated    = is_user_logged_in() && agency()->security->user_can_access_client($agency_client->id);
if (!$agency_token_authenticated && !$agency_wp_authenticated) {
    wp_die(esc_html__('Access denied', 'agency-client-portal'), 403);
}

$agency_is_manager     = current_user_can('manage_agency_projects');
$agency_external_links = Agency_External_Link::get_by_block($agency_block_id);
$agency_all_assets     = Agency_Asset::get_by_block($agency_block_id);

// Group assets: root slides (parent_asset_id = 0/null) vs version children
$agency_root_assets      = array();
$agency_version_children = array(); // keyed by parent_asset_id

foreach ($agency_all_assets as $agency_a) {
    $agency_pid = isset($agency_a->parent_asset_id) ? intval($agency_a->parent_asset_id) : 0;
    if ($agency_pid === 0) {
        $agency_root_assets[] = $agency_a;
    } else {
        $agency_version_children[$agency_pid][] = $agency_a;
    }
}

// Sort version children: highest version_number first (newest)
foreach ($agency_version_children as $agency_pid => &$agency_vers) {
    usort($agency_vers, function($agency_a, $b) {
        return (isset($b->version_number) ? intval($b->version_number) : 1)
             - (isset($agency_a->version_number) ? intval($agency_a->version_number) : 1);
    });
}
unset($agency_vers);

$agency_settings = Agency_Block::get_settings($agency_block);
$agency_subtitle  = !empty($agency_settings['subtitle']) ? $agency_settings['subtitle'] : '';
$agency_sb_description = $agency_block->content ?? '';
$agency_allow_media_download = Agency_Block::is_media_download_allowed($agency_block);
$agency_can_download_media = $agency_is_manager || $agency_allow_media_download;
$agency_preview_only_for_token_client = !$agency_is_manager && $agency_token_authenticated && !$agency_allow_media_download;

// Google Slides
$agency_google_slides_url = !empty($agency_settings['google_slides_url']) ? $agency_settings['google_slides_url'] : '';
$agency_google_slides_embed_url = '';
$agency_google_slides_id = '';

if ($agency_google_slides_url) {
    if (preg_match(
        '#docs\.google\.com/presentation/d/([a-zA-Z0-9_-]+)#',
        $agency_google_slides_url,
        $gsm
    )) {
        $agency_google_slides_id = $gsm[1];
        $agency_google_slides_embed_url = 'https://docs.google.com/presentation/d/'
            . esc_attr($agency_google_slides_id)
            . '/embed?start=false&loop=false&delayms=3000';
    }
}

$agency_has_google_slides = !empty($agency_google_slides_embed_url);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>

<div class="agency-wrapper">


    <?php include AGENCY_PLUGIN_DIR . 'templates/partials/header.php'; ?>

    <!-- Sidebar manager -->
    <?php include AGENCY_PLUGIN_DIR . 'templates/partials/sidebar.php'; ?>

    <main class="agency-main">

        <div class="agency-container">
            <div class="agency-block-view agency-block-storyboard" data-block-id="<?php echo esc_attr($agency_block->id); ?>">

                <?php include AGENCY_PLUGIN_DIR . 'templates/partials/block-head.php'; ?>

                <div class="block-header-full agency-bv-bar">
                    <?php if ($agency_is_manager || $agency_sb_description): ?>
                    <div class="block-description storyboard-description"
                         data-placeholder="<?php esc_attr_e('Add a description…', 'agency-client-portal'); ?>"
                         <?php if ($agency_is_manager): ?>
                         contenteditable="true"
                         id="storyboard-description"
                         data-block-id="<?php echo esc_attr($agency_block->id); ?>"
                         <?php endif; ?>>
                        <?php echo wp_kses_post($agency_sb_description); ?>
                    </div>
                    <?php endif; ?>

                    <div class="agency-links">
                        <?php if (!empty($agency_external_links)): ?>
                            <div class="external-links-display">
                                <?php foreach ($agency_external_links as $link): ?>
                                    <a href="<?php echo esc_url($link->url); ?>" class="external-link-item" target="_blank" rel="noopener" data-link-id="<?php echo esc_attr($link->id); ?>">
                                        <span class="link-emoji"><?php echo esc_html($link->emoji); ?></span>
                                        <span class="link-text"><?php echo esc_html($link->text); ?></span>
                                        <?php if ($agency_is_manager): ?>
                                            <button class="edit-external-link" data-link-id="<?php echo esc_attr($link->id); ?>" data-link-emoji="<?php echo esc_attr($link->emoji); ?>" data-link-text="<?php echo esc_attr($link->text); ?>" data-link-url="<?php echo esc_attr($link->url); ?>" title="<?php esc_attr_e('Edit', 'agency-client-portal'); ?>"><?php Agency_Icons::render('edit', 14); ?></button>
                                            <button class="delete-external-link" data-link-id="<?php echo esc_attr($link->id); ?>" title="<?php esc_attr_e('Delete', 'agency-client-portal'); ?>">×</button>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($agency_is_manager): ?>
                            <button class="agency-btn agency-btn-sm agency-btn-secondary" id="add-external-link-btn" type="button">
                                <?php Agency_Icons::render('link', 16); ?> <?php esc_html_e('Add a link', 'agency-client-portal'); ?>
                            </button>
                        <?php endif; ?>
                    </div>

                    <?php if ($agency_is_manager): ?>
                        <div class="agency-actions">
                            <button class="agency-btn agency-btn-primary" id="upload-asset-btn" type="button">
                                <?php Agency_Icons::render('paperclip', 16); ?> <?php esc_html_e('Add a slide', 'agency-client-portal'); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                    
                </div>

                <?php if ($agency_is_manager && !$agency_has_google_slides): ?>
                <section class="agency-bp-card agency-google-slides-setup">
                    <label class="agency-bp-label" for="agency-google-slides-input"><?php esc_html_e('Google Slides integration', 'agency-client-portal'); ?></label>
                    <div class="agency-google-slides-input-row">
                        <input type="url"
                               id="agency-google-slides-input"
                               class="agency-input"
                               placeholder="<?php esc_attr_e('Paste a Google Slides link…', 'agency-client-portal'); ?>"
                               aria-label="<?php esc_attr_e('Google Slides URL', 'agency-client-portal'); ?>">
                        <button type="button" class="agency-btn"
                                id="agency-google-slides-save"
                                data-block-id="<?php echo esc_attr($agency_block->id); ?>">
                            <?php Agency_Icons::render('link', 16); ?> <?php esc_html_e('Embed', 'agency-client-portal'); ?>
                        </button>
                    </div>
                </section>
                <?php endif; ?>

                <!-- Storyboard slides -->
                <?php if ($agency_has_google_slides): ?>
                    <!-- MODE GOOGLE SLIDES -->
                    <div class="agency-google-slides-container">
                        <?php if ($agency_is_manager): ?>
                        <?php if ($agency_is_manager && !empty($agency_google_slides_url)): ?>
<div class="agency-info-box agency-info-box-warning" id="google-slides-info-box">
    <span class="agency-info-box-icon"><?php Agency_Icons::render('info', 18); ?></span>
    <p class="agency-info-box-text"><?php esc_html_e('Google Slides does not allow embedding by default. You can create a "Link" block to reduce the number of clicks.', 'agency-client-portal'); ?></p>
    <button type="button" onclick="this.closest('#google-slides-info-box').style.display='none'; localStorage.setItem('agency_google_slides_info_dismissed','1');" class="agency-info-box-close" aria-label="<?php esc_attr_e('Close', 'agency-client-portal'); ?>">×</button>
</div>
<?php ob_start(); ?>
if (localStorage.getItem('agency_google_slides_info_dismissed') === '1') {
    var box = document.getElementById('google-slides-info-box');
    if (box) box.style.display = 'none';
}
<?php wp_print_inline_script_tag(ob_get_clean()); ?>
<?php endif; ?>
                        <div class="agency-google-slides-toolbar">
                            <a href="<?php echo esc_url($agency_google_slides_url); ?>"
                               target="_blank" rel="noopener noreferrer"
                               class="agency-btn agency-btn-secondary agency-google-slides-cta">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                                    <polyline points="15 3 21 3 21 9"/>
                                    <line x1="10" y1="14" x2="21" y2="3"/>
                                </svg>
                                <?php esc_html_e('Edit in Google Slides', 'agency-client-portal'); ?>
                            </a>
                            <button class="agency-btn agency-btn-danger agency-google-slides-remove"
                                    data-block-id="<?php echo esc_attr($agency_block->id); ?>">
                                <?php esc_html_e('Remove the Google Slides embed', 'agency-client-portal'); ?>
                            </button>
                        </div>
                        <?php else: ?>
                        <div class="agency-google-slides-toolbar">
                            <a href="<?php echo esc_url($agency_google_slides_url); ?>"
                               target="_blank" rel="noopener noreferrer"
                               class="agency-btn agency-btn-secondary agency-google-slides-cta">
                                <?php esc_html_e('View in Google Slides', 'agency-client-portal'); ?>
                            </a>
                        </div>
                        <?php endif; ?>

                        <div class="agency-google-slides-embed">
                            <iframe src="<?php echo esc_url($agency_google_slides_embed_url); ?>"
                                    frameborder="0"
                                    allowfullscreen="true"
                                    mozallowfullscreen="true"
                                    webkitallowfullscreen="true"
                                    title="<?php echo esc_attr($agency_block->title); ?>">
                            </iframe>
                        </div>
                    </div>
                <?php else: ?>
                <div class="storyboard-items <?php echo $agency_is_manager ? 'sortable' : ''; ?>" id="storyboard-items">

                    <?php if (empty($agency_root_assets)): ?>
                        <p class="no-assets"><?php esc_html_e('No slides yet.', 'agency-client-portal'); ?></p>
                    <?php else: ?>

                        <?php foreach ($agency_root_assets as $agency_slide_idx => $agency_asset):
                            $agency_children      = isset($agency_version_children[$agency_asset->id]) ? $agency_version_children[$agency_asset->id] : array();
                            $agency_latest        = !empty($agency_children) ? $agency_children[0] : $agency_asset;
                            $agency_version_count = count($agency_children) + 1; // V1 = root
                            $agency_latest_vnum   = !empty($agency_children)
                                ? (isset($agency_children[0]->version_number) ? intval($agency_children[0]->version_number) : count($agency_children) + 1)
                                : 1;
                            $agency_asset_comments = Agency_Comment::get_by_asset($agency_latest->id);
                        ?>

                        <?php if ($agency_slide_idx === 0): ?>
                        <!-- Insert before first slide -->
                        <div class="storyboard-insert-row" data-after-asset="0">
                            <button class="storyboard-insert-btn" title="<?php esc_attr_e('Insert a slide here', 'agency-client-portal'); ?>"><?php Agency_Icons::render('plus', 16); ?></button>
                        </div>
                        <?php endif; ?>

                        <?php
                        $agency_sb_can_comment = Agency_Block::client_can_comment_allowed($agency_block);
                        $agency_latest_media_url = $agency_preview_only_for_token_client ? Agency_Asset::get_preview_url($agency_latest->id) : Agency_Asset::get_url($agency_latest->id);
                        ?>
                        <div class="agency-bp-grid storyboard-item<?php echo !$agency_sb_can_comment ? ' agency-bp-grid--solo no-comments' : ''; ?>" data-asset-id="<?php echo esc_attr($agency_asset->id); ?>" data-root-id="<?php echo esc_attr($agency_asset->id); ?>">
                            <div class="agency-bp-main">
                                <section class="agency-bp-card">
                                    <header class="agency-bp-card__head storyboard-slide-header">
                                        <div class="agency-bp-card__tools">
                                            <?php if ($agency_is_manager): ?>
                                                <span class="storyboard-drag-handle" title="<?php esc_attr_e('Move slide', 'agency-client-portal'); ?>"><?php Agency_Icons::render('grip', 16); ?></span>
                                            <?php endif; ?>
                                            <span class="storyboard-slide-num agency-bp-label"><?php echo esc_html(sprintf(/* translators: %d: slide number */ __('Slide %d', 'agency-client-portal'), $agency_slide_idx + 1)); ?></span>
                                        </div>
                                        <div class="agency-bp-card__tools media-actions">
                                            <?php if ($agency_can_download_media): ?>
                                            <a href="<?php echo esc_url(Agency_Asset::get_download_url($agency_latest->id)); ?>" class="agency-btn-icon agency-btn-icon--sm" target="_blank"
                                               title="<?php esc_attr_e('Download', 'agency-client-portal'); ?>" aria-label="<?php esc_attr_e('Download', 'agency-client-portal'); ?>"><?php Agency_Icons::render('download', 16); ?></a>
                                            <?php endif; ?>
                                            <?php if ($agency_is_manager): ?>
                                            <button type="button" class="agency-btn-icon agency-btn-icon--sm delete-asset" data-asset-id="<?php echo esc_attr($agency_asset->id); ?>"
                                                    title="<?php esc_attr_e('Delete slide', 'agency-client-portal'); ?>" aria-label="<?php esc_attr_e('Delete slide', 'agency-client-portal'); ?>"><?php Agency_Icons::render('trash', 16); ?></button>
                                            <?php endif; ?>
                                        </div>
                                    </header>

                                    <div class="storyboard-media">
                                        <span class="storyboard-pill storyboard-pill--plan"><?php echo esc_html(sprintf(/* translators: %d: shot number */ __('Shot %d', 'agency-client-portal'), $agency_slide_idx + 1)); ?></span>
                                        <?php if ($agency_version_count > 1): ?>
                                            <span class="storyboard-pill storyboard-pill--version version-badge">V<?php echo esc_html($agency_latest_vnum); ?></span>
                                        <?php endif; ?>
                                        <?php if ($agency_latest->file_type === 'image'): ?>
                                            <img src="<?php echo esc_url($agency_latest_media_url); ?>"
                                                 alt="<?php echo esc_attr($agency_latest->original_filename); ?>"
                                                 class="storyboard-image"
                                                 loading="lazy">
                                        <?php elseif ($agency_latest->file_type === 'video'): ?>
                                            <video controls class="storyboard-video" <?php if ($agency_preview_only_for_token_client): ?>controlsList="nodownload noplaybackrate" disablepictureinpicture oncontextmenu="return false;"<?php endif; ?>>
                                                <source src="<?php echo esc_url($agency_latest_media_url); ?>">
                                            </video>
                                        <?php else: ?>
                                            <div class="storyboard-file-icon">
                                                <span class="file-icon"><?php Agency_Icons::render('file', 16); ?></span>
                                                <span class="file-name"><?php echo esc_html($agency_latest->original_filename); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="agency-bp-tags storyboard-actions">
                                        <?php if ($agency_is_manager): ?>
                                        <button type="button" class="agency-btn new-version-btn"
                                                data-root-id="<?php echo esc_attr($agency_asset->id); ?>"
                                                data-version-num="<?php echo esc_attr($agency_latest_vnum + 1); ?>"
                                                title="<?php esc_attr_e('New version of this slide', 'agency-client-portal'); ?>">
                                            <?php Agency_Icons::render('upload', 16); ?> <?php esc_html_e('New version', 'agency-client-portal'); ?>
                                        </button>
                                        <?php endif; ?>
                                        <?php if ($agency_version_count > 1): ?>
                                        <button class="agency-btn version-history-toggle" type="button" data-root-id="<?php echo esc_attr($agency_asset->id); ?>">
                                            <?php Agency_Icons::render('file', 16); ?>
                                            <?php echo esc_html(sprintf(/* translators: %d: number of versions */ _n('History · %d version', 'History · %d versions', $agency_version_count, 'agency-client-portal'), $agency_version_count)); ?>
                                        </button>
                                        <?php endif; ?>
                                        <span class="agency-bp-tags__meta"><?php echo esc_html(sprintf(/* translators: %s: relative time */ __('Updated %s', 'agency-client-portal'), Agency_Template::time_ago(!empty($agency_latest->created_at) ? $agency_latest->created_at : ''))); ?></span>
                                    </div>

                                    <?php if ($agency_version_count > 1): ?>
                                    <div class="version-history-list" id="version-history-<?php echo esc_attr($agency_asset->id); ?>" style="display:none;">
                                        <div class="version-item">
                                            <span class="version-label">V1</span>
                                            <?php if ($agency_asset->file_type === 'image'): ?>
                                                <img src="<?php echo esc_url(Agency_Asset::get_thumbnail_url($agency_asset->id)); ?>" alt="V1" class="version-thumb" loading="lazy">
                                            <?php else: ?>
                                                <span class="version-filename"><?php echo esc_html($agency_asset->original_filename); ?></span>
                                            <?php endif; ?>
                                            <?php if ($agency_can_download_media): ?>
                                            <a href="<?php echo esc_url(Agency_Asset::get_download_url($agency_asset->id)); ?>" target="_blank" class="agency-btn-icon agency-btn-icon--sm" title="<?php esc_attr_e('Download', 'agency-client-portal'); ?>"><?php Agency_Icons::render('download', 16); ?></a>
                                            <?php endif; ?>
                                        </div>
                                        <?php foreach (array_reverse($agency_children) as $agency_ver):
                                            $agency_vnum = isset($agency_ver->version_number) ? intval($agency_ver->version_number) : '?';
                                        ?>
                                        <div class="version-item">
                                            <span class="version-label">V<?php echo esc_html($agency_vnum); ?></span>
                                            <?php if ($agency_ver->file_type === 'image'): ?>
                                                <img src="<?php echo esc_url(Agency_Asset::get_thumbnail_url($agency_ver->id)); ?>" alt="V<?php echo esc_attr($agency_vnum); ?>" class="version-thumb" loading="lazy">
                                            <?php else: ?>
                                                <span class="version-filename"><?php echo esc_html($agency_ver->original_filename); ?></span>
                                            <?php endif; ?>
                                            <?php if ($agency_can_download_media): ?>
                                            <a href="<?php echo esc_url(Agency_Asset::get_download_url($agency_ver->id)); ?>" target="_blank" class="agency-btn-icon agency-btn-icon--sm" title="<?php esc_attr_e('Download', 'agency-client-portal'); ?>"><?php Agency_Icons::render('download', 16); ?></a>
                                            <?php endif; ?>
                                            <?php if ($agency_is_manager): ?>
                                            <button type="button" class="agency-btn-icon agency-btn-icon--sm delete-asset"
                                                    data-asset-id="<?php echo esc_attr($agency_ver->id); ?>"
                                                    title="<?php esc_attr_e('Delete this version', 'agency-client-portal'); ?>"><?php Agency_Icons::render('trash', 16); ?></button>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>

                                    <div class="agency-bp-notes storyboard-voiceover">
                                        <label for="voiceover-<?php echo esc_attr($agency_asset->id); ?>" class="agency-bp-label storyboard-voiceover-label">
                                            <?php esc_html_e('Voice-over', 'agency-client-portal'); ?>
                                        </label>
                                        <textarea id="voiceover-<?php echo esc_attr($agency_asset->id); ?>"
                                                  class="asset-voiceover-input"
                                                  data-asset-id="<?php echo esc_attr($agency_asset->id); ?>"
                                                  rows="3"
                                                  placeholder="<?php esc_attr_e('Enter the voice-over...', 'agency-client-portal'); ?>"
                                                  <?php echo $agency_is_manager ? '' : 'readonly'; ?>><?php echo isset($agency_asset->voiceover) ? esc_textarea($agency_asset->voiceover) : ''; ?></textarea>
                                        <?php if ($agency_is_manager): ?>
                                        <div class="agency-bp-notes__foot">
                                            <button type="button" class="agency-btn agency-btn-primary save-voiceover-btn" data-asset-id="<?php echo esc_attr($agency_asset->id); ?>">
                                                <?php esc_html_e('Save voice-over', 'agency-client-portal'); ?>
                                            </button>
                                            <span class="voiceover-save-status" data-asset-id="<?php echo esc_attr($agency_asset->id); ?>"></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </section>
                            </div>

                            <?php if ($agency_sb_can_comment): ?>
                            <aside class="agency-bp-aside">
                                <?php
                                $agency_cm_asset_id = (int) $agency_latest->id;
                                $agency_cm_comments = $agency_asset_comments;
                                include AGENCY_PLUGIN_DIR . 'templates/partials/block-comments.php';
                                ?>
                            </aside>
                            <?php endif; ?>
                        </div>

                        <!-- Insert after this slide -->
                        <div class="storyboard-insert-row" data-after-asset="<?php echo esc_attr($agency_asset->id); ?>">
                            <button class="storyboard-insert-btn" title="<?php esc_attr_e('Insert a slide here', 'agency-client-portal'); ?>"><?php Agency_Icons::render('plus', 16); ?></button>
                        </div>

                        <?php endforeach; ?>
                    <?php endif; ?>

                </div><!-- /.storyboard-items -->

                <?php endif; ?>

            </div>
        </div>
    </main>

    <?php include AGENCY_PLUGIN_DIR . 'templates/partials/footer.php'; ?>
</div>

<!-- Hidden file inputs for versioning and insert -->
<?php if ($agency_is_manager): ?>
<input type="file" id="new-version-file-input" style="display:none;" accept="image/*,video/*">
<input type="file" id="insert-slide-file-input" style="display:none;" accept="image/*,video/*">
<input type="file" id="upload-asset-file-input" style="display:none;" accept="image/*,video/*" multiple>

<!-- Upload progress overlay -->
<div class="agency-modal" id="upload-progress-overlay" style="display:none;">
    <div class="agency-modal-content agency-upload-modal">
        <h3><?php esc_html_e('Uploading...', 'agency-client-portal'); ?></h3>
        <div class="upload-progress-info">
            <span id="upload-count">0</span> / <span id="upload-total">0</span> <?php esc_html_e('files', 'agency-client-portal'); ?>
        </div>
        <div class="upload-progress-bar-container">
            <div class="upload-progress-bar-fill" id="upload-progress-fill" style="width:0%"></div>
        </div>
    </div>
</div>

<!-- External link modals -->
<div class="agency-modal" id="new-external-link-modal">
    <div class="agency-modal-content">
        <div class="agency-modal-header">
            <h3 class="agency-modal-title"><?php esc_html_e('Add an external link', 'agency-client-portal'); ?></h3>
            <button class="agency-modal-close" type="button">&times;</button>
        </div>
        <div class="agency-modal-body">
            <div class="agency-form-group">
                <label class="agency-form-label"><?php esc_html_e('Emoji', 'agency-client-portal'); ?></label>
                <div data-agency-emoji-picker data-default-emoji="🔗" data-input-field="new-link-emoji"></div>
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
                <div data-agency-emoji-picker data-default-emoji="🔗" data-input-field="edit-link-emoji"></div>
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

<?php ob_start(); ?>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof AgencyModal === 'undefined') return;

    var linkModal     = new AgencyModal('new-external-link-modal');
    var editLinkModal = new AgencyModal('edit-external-link-modal');

    document.querySelectorAll('.modal-cancel, .agency-modal-close').forEach(function(btn) {
        btn.addEventListener('click', function() { linkModal.close(); editLinkModal.close(); });
    });

    // ── Upload new slide (top button) ────────────────────────────────────────
    var uploadAssetBtn  = document.getElementById('upload-asset-btn');
    var uploadAssetFile = document.getElementById('upload-asset-file-input');

    if (uploadAssetBtn && uploadAssetFile) {
        uploadAssetBtn.addEventListener('click', function() { uploadAssetFile.click(); });
        uploadAssetFile.addEventListener('change', function() {
            var files = Array.from(uploadAssetFile.files);
            if (!files.length) return;
            uploadAssetFile.value = '';

            // Compter les slides existantes
            var existingItems = document.querySelectorAll('#storyboard-items .storyboard-item');
            var existingCount = existingItems ? existingItems.length : 0;

            function doUpload(afterAssetId) {
                var overlay = document.getElementById('upload-progress-overlay');
                var countEl = document.getElementById('upload-count');
                var totalEl = document.getElementById('upload-total');
                var fillEl  = document.getElementById('upload-progress-fill');
                var total = files.length;
                var uploaded = 0;

                overlay.style.display = 'flex';
                totalEl.textContent = total;
                countEl.textContent = '0';
                fillEl.style.width = '0%';

                var idx = files.length - 1;
                function uploadNext() {
                    if (idx < 0) {
                        overlay.style.display = 'none';
                        window.location.reload();
                        return;
                    }
                    uploadSlideFile(files[idx--], null, null, afterAssetId, function() {
                        uploaded++;
                        countEl.textContent = uploaded;
                        fillEl.style.width = Math.round((uploaded / total) * 100) + '%';
                        uploadNext();
                    });
                }
                uploadNext();
            }

            if (existingCount > 0) {
                // Afficher la modal de choix de position
                var modalOverlay = document.createElement('div');
                modalOverlay.className = 'agency-modal agency-position-modal active';
                modalOverlay.innerHTML =
                    '<div class="agency-modal-content">' +
                        '<p class="agency-modal-title">' + '<?php echo esc_js(__('Where should the new slides go?', 'agency-client-portal')); ?>' + '</p>' +
                        '<p class="agency-modal-sub">' + '<?php echo esc_js(/* translators: %d: number of existing slides */ __('There are already %d slide(s) in this storyboard.', 'agency-client-portal')); ?>'.replace('%d', existingCount) + '</p>' +
                        '<div class="agency-modal-actions">' +
                            '<button class="agency-btn agency-btn-secondary js-pos-before">↑ ' + '<?php echo esc_js(__('Before existing slides', 'agency-client-portal')); ?>' + '</button>' +
                            '<button class="agency-btn agency-btn-primary js-pos-after">↓ ' + '<?php echo esc_js(__('After existing slides', 'agency-client-portal')); ?>' + '</button>' +
                        '</div>' +
                    '</div>';
                document.body.appendChild(modalOverlay);

                modalOverlay.querySelector('.js-pos-before').addEventListener('click', function() {
                    modalOverlay.remove();
                    doUpload(0); // 0 = insérer avant tout
                });
                modalOverlay.querySelector('.js-pos-after').addEventListener('click', function() {
                    modalOverlay.remove();
                    // Trouver l'id du dernier asset existant
                    const allItems = document.querySelectorAll('#storyboard-items .storyboard-item');
                    const lastItem = allItems[allItems.length - 1];
                    const lastAssetId = lastItem ? lastItem.dataset.assetId : null;
                    doUpload(lastAssetId);  // passer le dernier id, pas null
                });
            } else {
                doUpload(null);
            }
        });
    }

    // ── New version per slide ────────────────────────────────────────────────
    var newVersionInput   = document.getElementById('new-version-file-input');
    var pendingRootId     = null;
    var pendingVersionNum = 1;

    document.querySelectorAll('.new-version-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            pendingRootId     = this.getAttribute('data-root-id');
            pendingVersionNum = parseInt(this.getAttribute('data-version-num')) || 2;
            newVersionInput.click();
        });
    });

    if (newVersionInput) {
        newVersionInput.addEventListener('change', function() {
            var file = newVersionInput.files[0];
            if (!file || !pendingRootId) return;
            uploadSlideFile(file, pendingRootId, pendingVersionNum);
            newVersionInput.value = '';
            pendingRootId = null;
        });
    }

    // ── Insert slide between slides ──────────────────────────────────────────
    var insertSlideInput = document.getElementById('insert-slide-file-input');
    var pendingAfterAssetId = null;

    document.querySelectorAll('.storyboard-insert-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var row = btn.closest('.storyboard-insert-row');
            pendingAfterAssetId = row ? row.getAttribute('data-after-asset') : '0';
            insertSlideInput.click();
        });
    });

    if (insertSlideInput) {
        insertSlideInput.addEventListener('change', function() {
            var file = insertSlideInput.files[0];
            if (!file) return;
            // Upload as a new root slide; after=X is informational for sort_order
            uploadSlideFile(file, null, null, pendingAfterAssetId);
            insertSlideInput.value = '';
            pendingAfterAssetId = null;
        });
    }

    // ── Shared upload function ───────────────────────────────────────────────
    function uploadSlideFile(file, parentAssetId, versionNum, afterAssetId, onSuccess) {
        var fd = new FormData();
        fd.append('block_id', <?php echo intval($agency_block->id); ?>);
        fd.append('file', file);
        if (parentAssetId) {
            fd.append('parent_asset_id', parentAssetId);
            fd.append('version_number', versionNum || 2);
        }
        if (afterAssetId && afterAssetId !== '0') {
            fd.append('after_asset_id', afterAssetId);
        }

        fetch('<?php echo esc_url(rest_url('agency/v1/assets/upload')); ?>', {
            method: 'POST',
            headers: { 'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>' },
            body: fd
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                if (typeof onSuccess === 'function') {
                    onSuccess();
                } else {
                    window.location.reload();
                }
            } else {
                alert(data.message || <?php echo wp_json_encode(__('Error during upload', 'agency-client-portal')); ?>);
            }
        })
        .catch(function(err) {
            console.error(err);
            alert(<?php echo wp_json_encode(__('Error during upload', 'agency-client-portal')); ?>);
        });
    }

    // ── Version history toggle ───────────────────────────────────────────────
    document.querySelectorAll('.version-history-toggle').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var rootId = this.getAttribute('data-root-id');
            var list   = document.getElementById('version-history-' + rootId);
            if (!list) return;
            var open = list.style.display !== 'none';
            list.style.display = open ? 'none' : 'block';
            this.textContent = this.textContent.replace(open ? '▴' : '▾', open ? '▾' : '▴');
        });
    });

    // ── Delete asset ─────────────────────────────────────────────────────────
    document.querySelectorAll('.delete-asset').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (!confirm(<?php echo wp_json_encode(__('Delete this item?', 'agency-client-portal')); ?>)) return;
            var assetId = this.getAttribute('data-asset-id');
            fetch('<?php echo esc_url(rest_url('agency/v1/assets/')); ?>' + assetId, {
                method: 'DELETE',
                headers: { 'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) window.location.reload();
                else alert(<?php echo wp_json_encode(__('Error while deleting', 'agency-client-portal')); ?>);
            });
        });
    });

    // ── Delete slide (cross button top-right) ────────────────────────────────
    document.querySelectorAll('.storyboard-slide-delete').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (!confirm(<?php echo wp_json_encode(__('Delete this slide and all its versions?', 'agency-client-portal')); ?>)) return;
            var assetId = this.getAttribute('data-asset-id');
            fetch('<?php echo esc_url(rest_url('agency/v1/assets/')); ?>' + assetId, {
                method: 'DELETE',
                headers: { 'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) window.location.reload();
                else alert(<?php echo wp_json_encode(__('Error while deleting', 'agency-client-portal')); ?>);
            });
        });
    });

    // NB : la sauvegarde de la voix-off est gérée par frontend.js (handler délégué
    // unique sur .save-voiceover-btn + auto-save debounced au blur). Ne pas
    // re-binder ici : le doublon provoquait 2 requêtes PUT par clic.

    // ── External links ───────────────────────────────────────────────────────
    var addLinkBtn    = document.getElementById('add-external-link-btn');
    var createLinkBtn = document.getElementById('create-external-link-btn');
    if (addLinkBtn)    addLinkBtn.addEventListener('click', function() { linkModal.open(); });
    if (createLinkBtn) {
        createLinkBtn.addEventListener('click', function() {
            var emoji = document.getElementById('new-link-emoji').value.trim();
            var text  = document.getElementById('new-link-text').value.trim();
            var url   = document.getElementById('new-link-url').value.trim();
            if (!emoji || !text || !url) { alert(<?php echo wp_json_encode(__('Please fill in all fields', 'agency-client-portal')); ?>); return; }
            createLinkBtn.disabled = true;
            createLinkBtn.textContent = <?php echo wp_json_encode(__('Creating...', 'agency-client-portal')); ?>;
            fetch('<?php echo esc_url(rest_url('agency/v1/external-links')); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>' },
                body: JSON.stringify({ block_id: <?php echo intval($agency_block->id); ?>, emoji: emoji, text: text, url: url })
            }).then(function(r){ return r.json(); }).then(function(data){
                if (data.success) window.location.reload();
                else { createLinkBtn.disabled = false; createLinkBtn.textContent = <?php echo wp_json_encode(__('Add link', 'agency-client-portal')); ?>; }
            });
        });
    }

    document.querySelectorAll('.delete-external-link').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault(); e.stopPropagation();
            if (!confirm(<?php echo wp_json_encode(__('Delete this link?', 'agency-client-portal')); ?>)) return;
            var lid = this.getAttribute('data-link-id');
            fetch('<?php echo esc_url(rest_url('agency/v1/external-links/')); ?>' + lid, {
                method: 'DELETE', headers: { 'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>' }
            }).then(function(r){ return r.json(); }).then(function(data){ if(data.success) window.location.reload(); });
        });
    });

    document.querySelectorAll('.edit-external-link').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault(); e.stopPropagation();
            document.getElementById('edit-link-id').value    = this.getAttribute('data-link-id');
            document.getElementById('edit-link-emoji').value = this.getAttribute('data-link-emoji');
            document.getElementById('edit-link-text').value  = this.getAttribute('data-link-text');
            document.getElementById('edit-link-url').value   = this.getAttribute('data-link-url');
            var emojiDisplay = document.querySelector('[data-input-field="edit-link-emoji"]');
            if (emojiDisplay) { var s = emojiDisplay.querySelector('.emoji-display'); if (s) s.textContent = this.getAttribute('data-link-emoji'); }
            editLinkModal.open();
        });
    });

    var updateLinkBtn = document.getElementById('update-external-link-btn');
    if (updateLinkBtn) {
        updateLinkBtn.addEventListener('click', function() {
            var lid   = document.getElementById('edit-link-id').value;
            var emoji = document.getElementById('edit-link-emoji').value.trim();
            var text  = document.getElementById('edit-link-text').value.trim();
            var url   = document.getElementById('edit-link-url').value.trim();
            if (!emoji || !text || !url) { alert(<?php echo wp_json_encode(__('Please fill in all fields', 'agency-client-portal')); ?>); return; }
            updateLinkBtn.disabled = true;
            updateLinkBtn.textContent = <?php echo wp_json_encode(__('Updating...', 'agency-client-portal')); ?>;
            fetch('<?php echo esc_url(rest_url('agency/v1/external-links/')); ?>' + lid, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>' },
                body: JSON.stringify({ emoji: emoji, text: text, url: url })
            }).then(function(r){ return r.json(); }).then(function(data){
                if (data.success) window.location.reload();
                else { updateLinkBtn.disabled = false; updateLinkBtn.textContent = <?php echo wp_json_encode(__('Update', 'agency-client-portal')); ?>; }
            });
        });
    }
});
<?php wp_print_inline_script_tag(ob_get_clean()); ?>
<?php endif; ?>

<?php ob_start(); ?>
document.addEventListener('DOMContentLoaded', function() {
    // Subtitle auto-save
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

    // Description auto-save (storyboard)
    var sbDesc = document.getElementById('storyboard-description');
    if (sbDesc && typeof agencyData !== 'undefined') {
        var sbDescTimer = null;
        sbDesc.addEventListener('input', function() {
            clearTimeout(sbDescTimer);
            sbDescTimer = setTimeout(function() {
                fetch(agencyData.rest_url + 'blocks/' + sbDesc.getAttribute('data-block-id'), {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': agencyData.nonce },
                    body: JSON.stringify({ content: sbDesc.innerHTML })
                });
            }, 1200);
        });
    }

    <?php if (!$agency_is_manager): ?>
    // Client: click "+" to insert an empty placeholder slide
    document.querySelectorAll('.storyboard-insert-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var row = btn.closest('.storyboard-insert-row');
            var afterAssetId = row ? (row.getAttribute('data-after-asset') || '0') : '0';
            if (!confirm(<?php echo wp_json_encode(__('Add an empty slide here?', 'agency-client-portal')); ?>)) return;
            btn.disabled = true;
            fetch('<?php echo esc_url(rest_url('agency/v1/assets/create-placeholder')); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': agencyData.nonce },
                body: JSON.stringify({
                    block_id: <?php echo intval($agency_block->id); ?>,
                    after_asset_id: afterAssetId && afterAssetId !== '0' ? parseInt(afterAssetId) : null
                })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || <?php echo wp_json_encode(__('Error', 'agency-client-portal')); ?>);
                    btn.disabled = false;
                }
            })
            .catch(function() { btn.disabled = false; });
        });
    });

    <?php endif; ?>

    // Google Slides — Sauvegarder l'URL
    var gsSaveBtn = document.getElementById('agency-google-slides-save');
    console.log('[Agency] Google Slides save button:', gsSaveBtn);
    if (gsSaveBtn) {
        gsSaveBtn.addEventListener('click', function() {
            var blockId = this.getAttribute('data-block-id');
            var url = document.getElementById('agency-google-slides-input').value.trim();
            console.log('[Agency] Google Slides save clicked. Block:', blockId, 'URL:', url);
            if (!url) {
                console.warn('[Agency] Google Slides URL is empty');
                return;
            }

            // Valider que c'est une URL Google Slides
            if (url.indexOf('docs.google.com/presentation') === -1) {
                console.warn('[Agency] Invalid Google Slides URL:', url);
                alert('<?php echo esc_js(__('Please enter a valid Google Slides URL.', 'agency-client-portal')); ?>');
                return;
            }

            var endpoint = agencyData.rest_url + 'blocks/' + blockId + '/settings';
            console.log('[Agency] Sending POST to:', endpoint);

            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': agencyData.nonce
                },
                body: JSON.stringify({ google_slides_url: url })
            })
            .then(function(r) {
                console.log('[Agency] Response status:', r.status);
                return r.json();
            })
            .then(function(data) {
                console.log('[Agency] Response data:', data);
                if (data.success) {
                    console.log('[Agency] Success! Reloading...');
                    window.location.reload();
                } else {
                    console.error('[Agency] Error response:', data);
                    alert('<?php echo esc_js(__('Error', 'agency-client-portal')); ?>');
                }
            })
            .catch(function(err) {
                console.error('[Agency] Fetch error:', err);
                alert('<?php echo esc_js(__('Error', 'agency-client-portal')); ?>');
            });
        });
    }

    // Google Slides — Retirer l'intégration
    var gsRemoveBtn = document.querySelector('.agency-google-slides-remove');
    if (gsRemoveBtn) {
        gsRemoveBtn.addEventListener('click', function() {
            console.log('[Agency] Google Slides remove clicked');
            if (!confirm('<?php echo esc_js(__('Remove the Google Slides embed?', 'agency-client-portal')); ?>')) return;
            var blockId = this.getAttribute('data-block-id');

            fetch(agencyData.rest_url + 'blocks/' + blockId + '/settings', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': agencyData.nonce
                },
                body: JSON.stringify({ google_slides_url: '' })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('<?php echo esc_js(__('Error', 'agency-client-portal')); ?>');
                }
            })
            .catch(function() { alert('<?php echo esc_js(__('Error', 'agency-client-portal')); ?>'); });
        });
    }
});
<?php wp_print_inline_script_tag(ob_get_clean()); ?>

<?php wp_footer(); ?>
</body>
</html>
