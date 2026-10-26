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

                <div class="block-columns">
                    <!-- Left column: link content -->
                    <div class="block-column-main">

                        <?php if ($is_manager): ?>
                        <!-- Manager: URL config + background image -->
                        <div class="agency-link-manager-panel">
                            <div class="agency-form-group">
                                <label class="agency-form-label" for="link-block-url"><?php esc_html_e('Target URL', 'agency-client-portal'); ?></label>
                                <div class="agency-form-inline">
                                    <input type="url"
                                           id="link-block-url"
                                           class="agency-form-input"
                                           value="<?php echo esc_attr($link_url); ?>"
                                           placeholder="<?php esc_attr_e('https://...', 'agency-client-portal'); ?>">
                                    <button type="button" class="agency-btn agency-btn-primary" id="save-link-url-btn">
                                        <?php esc_html_e('Save', 'agency-client-portal'); ?>
                                    </button>
                                </div>
                                <p class="agency-form-hint"><?php esc_html_e('The client will be redirected to this URL when clicking.', 'agency-client-portal'); ?></p>
                            </div>

                            <div class="agency-form-group">
                                <label class="agency-form-label" for="link-block-bg"><?php esc_html_e('Background image (optional)', 'agency-client-portal'); ?></label>
                                <div class="agency-form-inline">
                                    <input type="url"
                                           id="link-block-bg"
                                           class="agency-form-input"
                                           value="<?php echo esc_attr($bg_image_url); ?>"
                                           placeholder="<?php esc_attr_e('Image URL', 'agency-client-portal'); ?>">
                                    <button type="button" class="agency-btn agency-btn-secondary" id="link-bg-choose-btn">
                                        <?php esc_html_e('Choose', 'agency-client-portal'); ?>
                                    </button>
                                    <?php if ($bg_image_url): ?>
                                    <button type="button" class="agency-btn agency-btn-danger" id="link-bg-clear-btn"><?php Agency_Icons::render('x', 14); ?></button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Link display (visible to all) -->
                        <?php if ($link_url): ?>
                        <div class="agency-link-display<?php echo $bg_image_url ? ' has-block-bg' : ''; ?>"
                             <?php if ($bg_image_url): ?>style="background-image: url('<?php echo esc_url($bg_image_url); ?>'); background-size: cover; background-position: center;"<?php endif; ?>>
                            <div class="agency-link-display-inner">
                                <span class="link-icon"><?php Agency_Icons::render('link', 16); ?></span>
                                <p class="link-label"><?php echo esc_html($block->title); ?></p>
                                <a href="<?php echo esc_url($link_url); ?>"
                                   class="agency-btn agency-btn-primary agency-link-open-btn"
                                   target="_blank"
                                   rel="noopener noreferrer">
                                    <?php esc_html_e('Open link', 'agency-client-portal'); ?> →
                                </a>
                            </div>
                        </div>
                        <?php elseif ($is_manager): ?>
                        <div class="agency-link-empty">
                            <p><?php esc_html_e('No URL set. Enter the URL above.', 'agency-client-portal'); ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($block->content)): ?>
                        <div class="block-content-text" style="margin-top: 24px;">
                            <?php echo wp_kses_post($block->content); ?>
                        </div>
                        <?php endif; ?>

                    </div>

                    <!-- Right column: Comments -->
                    <div class="block-column-right">
                        <div class="block-comments">
                            <h3><?php esc_html_e('Comments', 'agency-client-portal'); ?></h3>

                            <div class="comments-list" id="comments-list">
                                <?php if (empty($comments)): ?>
                                    <p class="no-comments"><?php esc_html_e('No comments yet.', 'agency-client-portal'); ?></p>
                                <?php else: ?>
                                    <?php foreach ($comments as $comment): ?>
                                        <div class="comment-item" data-comment-id="<?php echo esc_attr($comment->id); ?>">
                                            <div class="comment-header">
                                                <span class="comment-author"><?php echo esc_html($comment->user->display_name); ?></span>
                                                <span class="comment-date"><?php echo esc_html(Agency_Template::time_ago($comment->created_at)); ?></span>
                                            </div>
                                            <div class="comment-content">
                                                <?php echo wp_kses_post($comment->content); ?>
                                            </div>
                                            <?php if ($is_manager || get_current_user_id() === (int)$comment->user_id): ?>
                                                <div class="comment-actions">
                                                    <button class="agency-btn-text delete-comment" data-comment-id="<?php echo esc_attr($comment->id); ?>">
                                                        <?php esc_html_e('Delete', 'agency-client-portal'); ?>
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <div class="comment-form">
                                <h4><?php esc_html_e('Add a comment', 'agency-client-portal'); ?></h4>
                                <form id="new-comment-form">
                                    <textarea name="comment_content" id="comment-content" rows="4" placeholder="<?php esc_attr_e('Your comment...', 'agency-client-portal'); ?>" required></textarea>
                                    <button type="submit" class="agency-btn agency-btn-primary">
                                        <?php esc_html_e('Send', 'agency-client-portal'); ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
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

    // Comments: submit
    var commentForm = document.getElementById('new-comment-form');
    if (commentForm) {
        commentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var content = document.getElementById('comment-content').value.trim();
            if (!content) return;
            var submitBtn = commentForm.querySelector('button[type=submit]');
            submitBtn.disabled = true;

            fetch(agencyData.rest_url + 'comments', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': agencyData.nonce },
                body: JSON.stringify({ block_id: <?php echo intval($block->id); ?>, content: content })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    window.location.reload();
                } else {
                    submitBtn.disabled = false;
                }
            })
            .catch(function() { submitBtn.disabled = false; });
        });
    }

    // Comments: delete
    document.querySelectorAll('.delete-comment').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (!confirm(<?php echo wp_json_encode(__('Delete this comment?', 'agency-client-portal')); ?>)) return;
            var id = btn.getAttribute('data-comment-id');
            fetch(agencyData.rest_url + 'comments/' + id, {
                method: 'DELETE',
                headers: { 'X-WP-Nonce': agencyData.nonce }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) { if (data.success) window.location.reload(); });
        });
    });
});
<?php wp_print_inline_script_tag(ob_get_clean()); ?>

<?php wp_footer(); ?>
</body>
</html>
