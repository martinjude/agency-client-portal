<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Block View: Lien
 * Opens a URL when the client clicks the block.
 */
$client_slug  = get_query_var('agency_client');
$project_slug = get_query_var('agency_project');
$block_id     = get_query_var('agency_block');

$client  = Agency_Client::get_by_slug($client_slug);
$project = $client ? Agency_Project::get_by_slug($client->id, $project_slug) : null;
$block   = Agency_Block::get_by_id($block_id);

if (!$client || !$project || !$block || $block->type !== 'link') {
    wp_die(esc_html__('Content not found', 'agency-client-portal'), 404);
}

// Vérifier l'accès
$token_authenticated = Agency_Security::can_access_client_by_token($client->id);
$wp_authenticated    = is_user_logged_in() && agency()->security->user_can_access_client($client->id);
if (!$token_authenticated && !$wp_authenticated) {
    wp_die(esc_html__('Access denied', 'agency-client-portal'), 403);
}

$is_manager = current_user_can('manage_agency_projects');
$comments   = Agency_Comment::get_by_block($block_id);
$external_links = Agency_External_Link::get_by_block($block_id);

$settings          = !empty($block->settings) ? json_decode($block->settings, true) : array();
$link_url          = !empty($settings['link_url']) ? $settings['link_url'] : '';
$bg_image_url      = !empty($settings['background_image_url']) ? $settings['background_image_url'] : '';
$subtitle          = !empty($settings['subtitle']) ? $settings['subtitle'] : '';

$back_url = home_url("/client/{$client->slug}/project/{$project->slug}");
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
            <div class="agency-block-view agency-block-link" data-block-id="<?php echo esc_attr($block->id); ?>">

                <?php include AGENCY_PLUGIN_DIR . 'templates/partials/block-head.php'; ?>

                <div class="block-header-full agency-bv-bar">
                    <?php if ($subtitle || $is_manager): ?>
                    <p class="block-description<?php echo $is_manager ? ' agency-block-subtitle--editable' : ''; ?>"
                       <?php if ($is_manager): ?>contenteditable="true" data-block-id="<?php echo esc_attr($block->id); ?>"<?php endif; ?>>
                        <?php echo esc_html($subtitle); ?>
                    </p>
                    <?php endif; ?>
                    <?php if ($link_url): ?>
                    <div class="agency-actions">
                        <a href="<?php echo esc_url($link_url); ?>" class="agency-btn" target="_blank" rel="noopener noreferrer">
                            <?php Agency_Icons::render_ui('external', 16); ?> <?php esc_html_e('Open link', 'agency-client-portal'); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="agency-bp-grid">
                    <div class="agency-bp-main">

                        <?php if ($is_manager): ?>
                        <!-- Manager : URL cible + image de fond -->
                        <section class="agency-bp-card agency-link-manager-panel">
                            <header class="agency-bp-card__head agency-bp-card__head--rule">
                                <h3 class="agency-bp-card__title"><?php esc_html_e('Destination', 'agency-client-portal'); ?></h3>
                            </header>

                            <label class="agency-bp-label" for="link-block-url"><?php esc_html_e('Target URL', 'agency-client-portal'); ?></label>
                            <div class="agency-form-inline">
                                <input type="url" id="link-block-url" class="agency-form-input"
                                       value="<?php echo esc_attr($link_url); ?>"
                                       placeholder="<?php esc_attr_e('https://...', 'agency-client-portal'); ?>">
                                <button type="button" class="agency-btn agency-btn-primary" id="save-link-url-btn">
                                    <?php esc_html_e('Save', 'agency-client-portal'); ?>
                                </button>
                            </div>
                            <p class="agency-bp-hint agency-bp-hint--icon">
                                <?php Agency_Icons::render('lock', 14); ?>
                                <?php esc_html_e('The client will be redirected to this URL when clicking.', 'agency-client-portal'); ?>
                            </p>

                            <div class="agency-bp-notes">
                                <label class="agency-bp-label" for="link-block-bg"><?php esc_html_e('Background image (optional)', 'agency-client-portal'); ?></label>
                                <div class="agency-form-inline">
                                    <input type="url" id="link-block-bg" class="agency-form-input"
                                           value="<?php echo esc_attr($bg_image_url); ?>"
                                           placeholder="<?php esc_attr_e('Image URL', 'agency-client-portal'); ?>">
                                    <button type="button" class="agency-btn" id="link-bg-choose-btn">
                                        <?php Agency_Icons::render('image', 16); ?> <?php esc_html_e('Choose', 'agency-client-portal'); ?>
                                    </button>
                                    <?php if ($bg_image_url): ?>
                                    <button type="button" class="agency-btn-icon agency-btn-icon--danger" id="link-bg-clear-btn" aria-label="<?php esc_attr_e('Remove', 'agency-client-portal'); ?>"><?php Agency_Icons::render_ui('x', 16); ?></button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </section>
                        <?php endif; ?>

                        <?php if (!empty($block->content)): ?>
                        <div class="block-content-text">
                            <?php echo wp_kses_post($block->content); ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!$is_manager): ?>
                        <?php include AGENCY_PLUGIN_DIR . 'templates/partials/link-preview-card.php'; ?>
                        <?php endif; ?>

                    </div>

                    <aside class="agency-bp-aside">
                        <?php if ($is_manager): ?>
                        <?php include AGENCY_PLUGIN_DIR . 'templates/partials/link-preview-card.php'; ?>
                        <?php endif; ?>
                        <?php $cm_comments = $comments; include AGENCY_PLUGIN_DIR . 'templates/partials/block-comments.php'; ?>
                    </aside>
                </div>

            </div>
        </div>
    </main>

    <?php include AGENCY_PLUGIN_DIR . 'templates/partials/footer.php'; ?>
</div>

<?php ob_start(); ?>
document.addEventListener('DOMContentLoaded', function() {

    <?php if ($is_manager): ?>

    // Save link URL
    var saveLinkUrlBtn = document.getElementById('save-link-url-btn');
    if (saveLinkUrlBtn) {
        saveLinkUrlBtn.addEventListener('click', function() {
            var url = document.getElementById('link-block-url').value.trim();
            var bgUrl = document.getElementById('link-block-bg').value.trim();
            saveLinkUrlBtn.disabled = true;
            saveLinkUrlBtn.textContent = <?php echo wp_json_encode(__('Saving...', 'agency-client-portal')); ?>;
            fetch(agencyData.rest_url + 'blocks/<?php echo intval($block->id); ?>', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': agencyData.nonce },
                body: JSON.stringify({ settings: { link_url: url, background_image_url: bgUrl } })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    window.location.reload();
                } else {
                    saveLinkUrlBtn.disabled = false;
                    saveLinkUrlBtn.textContent = <?php echo wp_json_encode(__('Save', 'agency-client-portal')); ?>;
                }
            })
            .catch(function() {
                saveLinkUrlBtn.disabled = false;
                saveLinkUrlBtn.textContent = <?php echo wp_json_encode(__('Save', 'agency-client-portal')); ?>;
            });
        });
    }

    // WP Media uploader for background image
    var bgChooseBtn = document.getElementById('link-bg-choose-btn');
    if (bgChooseBtn && typeof wp !== 'undefined' && wp.media) {
        bgChooseBtn.addEventListener('click', function(e) {
            e.preventDefault();
            var uploader = wp.media({ title: <?php echo wp_json_encode(__('Choose an image', 'agency-client-portal')); ?>, button: { text: <?php echo wp_json_encode(__('Use this image', 'agency-client-portal')); ?> }, multiple: false, library: { type: 'image' } });
            uploader.on('select', function() {
                var attachment = uploader.state().get('selection').first().toJSON();
                document.getElementById('link-block-bg').value = attachment.url;
                var clearBtn = document.getElementById('link-bg-clear-btn');
                if (clearBtn) clearBtn.style.display = '';
            });
            uploader.open();
        });
    }

    // Clear background image
    var bgClearBtn = document.getElementById('link-bg-clear-btn');
    if (bgClearBtn) {
        bgClearBtn.addEventListener('click', function() {
            document.getElementById('link-block-bg').value = '';
            bgClearBtn.style.display = 'none';
        });
    }

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

    <?php endif; ?>
});
<?php wp_print_inline_script_tag(ob_get_clean()); ?>

<?php wp_footer(); ?>
</body>
</html>
