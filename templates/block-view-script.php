<?php if (!defined('ABSPATH')) { exit; } ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
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
$block = Agency_Block::get_by_id($block_id);

if (!$client || !$project || !$block || $block->type !== 'script') {
    wp_die(esc_html__('Content not found', 'agency-client-portal'), 404);
}

$comments = Agency_Comment::get_by_block($block_id);
$is_manager = current_user_can('manage_agency_projects');
$external_links = Agency_External_Link::get_by_block($block_id);
?>

<div class="agency-wrapper">

    <!-- Header -->
    <?php include AGENCY_PLUGIN_DIR . 'templates/partials/header.php'; ?>

    <!-- Sidebar manager -->
    <?php include AGENCY_PLUGIN_DIR . 'templates/partials/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="agency-main">

        <div class="agency-container">
            <div class="agency-block-view agency-block-script">
                <!-- Block Header -->
                <?php include AGENCY_PLUGIN_DIR . 'templates/partials/block-head.php'; ?>

                <div class="block-header-full agency-bv-bar">
<?php
                    $_bv_subtitle_raw = !empty($block->settings) ? json_decode($block->settings, true) : array();
                    $_bv_subtitle = !empty($_bv_subtitle_raw['subtitle']) ? $_bv_subtitle_raw['subtitle'] : '';
                    $_bv_script_content = isset($_bv_subtitle_raw['script_content']) ? $_bv_subtitle_raw['script_content'] : '';
                    $_bv_description = $block->content ?? '';
                    $_bv_google_doc_url = !empty($_bv_subtitle_raw['google_doc_url']) ? $_bv_subtitle_raw['google_doc_url'] : '';
                    // Transforme les URLs Google Docs/Sheets en URL d'aperçu intégrable
                    $_bv_google_doc_embed = '';
                    if ($_bv_google_doc_url) {
                        if (preg_match('#docs\.google\.com/document(?:/u/\d+)?/d/([a-zA-Z0-9_-]+)#', $_bv_google_doc_url, $_bv_gdoc_match)) {
                            $_bv_google_doc_embed = 'https://docs.google.com/document/d/' . $_bv_gdoc_match[1] . '/preview';
                        } elseif (preg_match('#docs\.google\.com/spreadsheets(?:/u/\d+)?/d/([a-zA-Z0-9_-]+)#', $_bv_google_doc_url, $_bv_gsheet_match)) {
                            $_bv_google_doc_embed = 'https://docs.google.com/spreadsheets/d/' . $_bv_gsheet_match[1] . '/preview';
                        }
                    }
                    ?>
                    <?php if ($is_manager || $_bv_description): ?>
                    <div class="block-description script-description"
                         data-placeholder="<?php esc_attr_e('Add a description…', 'agency-client-portal'); ?>"
                         <?php if ($is_manager): ?>
                         contenteditable="true"
                         id="script-description"
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
                            <?php if (empty($_bv_google_doc_embed)): ?>
                            <button class="agency-btn agency-btn-sm agency-btn-secondary" id="integrate-google-doc-btn" type="button"
                                    data-block-id="<?php echo esc_attr($block->id); ?>">
                                <?php Agency_Icons::render('file', 16); ?> <?php esc_html_e('Embed Google Docs/Sheets', 'agency-client-portal'); ?>
                            </button>
                            <?php else: ?>
                            <button class="agency-btn agency-btn-sm agency-btn-danger" id="disintegrate-google-doc-btn" type="button"
                                    data-block-id="<?php echo esc_attr($block->id); ?>">
                                <?php Agency_Icons::render('x', 14); ?> <?php esc_html_e('Remove the embedded doc', 'agency-client-portal'); ?>
                            </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Two-Column Layout: 2/3 + 1/3 -->
                <?php if (!empty($_bv_google_doc_embed)): ?>
                <div class="google-doc-section">
                    <div class="google-doc-cta">
                        <a href="<?php echo esc_url($_bv_google_doc_url); ?>"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="agency-btn agency-btn-secondary">
                            <?php Agency_Icons::render('edit', 16); ?> <?php esc_html_e('Edit document', 'agency-client-portal'); ?>
                        </a>
                    </div>
                    <iframe
                        src="<?php echo esc_url($_bv_google_doc_embed); ?>"
                        class="google-doc-iframe"
                        frameborder="0"
                        allowfullscreen
                    ></iframe>
                </div>
                <?php else: ?>
                <div class="block-two-column-layout<?php echo !Agency_Block::client_can_comment_allowed($block) ? ' no-comments' : ''; ?>">
                    <!-- Left Column: Script Content (2/3) -->
                    <div class="block-column-left">

                        <div class="script-editor-section">
                            <h3><?php esc_html_e('Script', 'agency-client-portal'); ?></h3>

                            <?php if ($is_manager): ?>
                                <!-- Rich text editor for manager -->
                                <div class="script-editor-wrapper">
                                    <div class="script-toolbar">
                                        <button type="button" class="toolbar-btn" data-command="bold" title="<?php esc_attr_e('Bold', 'agency-client-portal'); ?>">
                                            <strong>B</strong>
                                        </button>
                                        <button type="button" class="toolbar-btn" data-command="highlight" title="<?php esc_attr_e('Highlight', 'agency-client-portal'); ?>">
                                            <span style="background: yellow; padding: 2px 4px;">H</span>
                                        </button>
                                        <button type="button" class="toolbar-btn" data-command="highlight-comment" title="<?php esc_attr_e('Highlight with comment', 'agency-client-portal'); ?>">
                                            <?php Agency_Icons::render('comment', 16); ?>
                                        </button>
                                    </div>
                                    <div
                                        id="script-content"
                                        class="script-editor"
                                        contenteditable="true"
                                        placeholder="<?php esc_attr_e('Enter the script content here...', 'agency-client-portal'); ?>"
                                    ><?php echo wp_kses_post($_bv_script_content); ?></div>
                                    <div class="script-actions">
                                        <button class="agency-btn agency-btn-primary" id="save-script-btn">
                                            <?php Agency_Icons::render('save', 16); ?> <?php esc_html_e('Save script', 'agency-client-portal'); ?>
                                        </button>
                                        <span class="script-save-status"></span>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- Read-only view for clients -->
                                <div class="script-content-display">
                                    <?php if ($_bv_script_content): ?>
                                        <?php echo wp_kses_post($_bv_script_content); ?>
                                    <?php else: ?>
                                        <p class="no-content"><?php esc_html_e('No script available yet.', 'agency-client-portal'); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                        <?php if (Agency_Block::client_can_comment_allowed($block)) : ?>
                        <!-- Right Column: Comments (1/3) -->
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
                                
                                <!-- Comment Form -->
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
                        <?php endif; ?>
                </div>
                <?php endif; /* google_doc_embed */ ?>
            </div>
        </div>
    </main>

    <?php include AGENCY_PLUGIN_DIR . 'templates/partials/footer.php'; ?>
</div>

<?php if ($is_manager): ?>
<!-- Modal : Intégrer Google Docs/Sheets -->
<div class="agency-modal" id="google-doc-modal">
    <div class="agency-modal-content">
        <div class="agency-modal-header">
            <h3 class="agency-modal-title"><?php esc_html_e('Embed Google Docs/Sheets', 'agency-client-portal'); ?></h3>
            <button class="agency-modal-close" type="button" id="google-doc-modal-close">&times;</button>
        </div>
        <div class="agency-modal-body">
            <div class="agency-form-group">
                <label class="agency-form-label" for="google-doc-url-input">
                    <?php esc_html_e('Google Docs or Sheets URL', 'agency-client-portal'); ?>
                </label>
                <input type="url"
                       id="google-doc-url-input"
                       class="agency-form-input"
                       placeholder="<?php esc_attr_e('https://docs.google.com/document/d/... or .../spreadsheets/d/...', 'agency-client-portal'); ?>">
                <p class="agency-form-help"><?php esc_html_e('Paste the full URL of a Google Doc or Google Sheet.', 'agency-client-portal'); ?></p>
            </div>
        </div>
        <div class="agency-modal-footer">
            <button class="agency-btn agency-btn-secondary" type="button" id="google-doc-modal-cancel">
                <?php esc_html_e('Cancel', 'agency-client-portal'); ?>
            </button>
            <button class="agency-btn agency-btn-primary" type="button" id="google-doc-modal-save"
                    data-block-id="<?php echo esc_attr($block->id); ?>">
                <?php esc_html_e('Embed', 'agency-client-portal'); ?>
            </button>
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
                <label class="agency-form-label" for="new-link-text">
                    <?php esc_html_e('Link text', 'agency-client-portal'); ?>
                </label>
                <input type="text" 
                       id="new-link-text" 
                       class="agency-form-input"
                       placeholder="<?php esc_attr_e('e.g. Google Slides presentation', 'agency-client-portal'); ?>" 
                       required>
            </div>
            <div class="agency-form-group">
                <label class="agency-form-label" for="new-link-url">
                    <?php esc_html_e('URL', 'agency-client-portal'); ?>
                </label>
                <input type="url" 
                       id="new-link-url" 
                       class="agency-form-input"
                       placeholder="<?php esc_attr_e('https://...', 'agency-client-portal'); ?>" 
                       required>
            </div>
        </div>
        <div class="agency-modal-footer">
            <button class="agency-btn agency-btn-secondary modal-cancel" type="button">
                <?php esc_html_e('Cancel', 'agency-client-portal'); ?>
            </button>
            <button class="agency-btn agency-btn-primary" id="create-external-link-btn" type="button">
                <?php esc_html_e('Add link', 'agency-client-portal'); ?>
            </button>
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
                <label class="agency-form-label" for="edit-link-text">
                    <?php esc_html_e('Link text', 'agency-client-portal'); ?>
                </label>
                <input type="text"
                       id="edit-link-text"
                       class="agency-form-input"
                       required>
            </div>
            <div class="agency-form-group">
                <label class="agency-form-label" for="edit-link-url">
                    <?php esc_html_e('URL', 'agency-client-portal'); ?>
                </label>
                <input type="url"
                       id="edit-link-url"
                       class="agency-form-input"
                       placeholder="<?php esc_attr_e('https://...', 'agency-client-portal'); ?>"
                       required>
            </div>
        </div>
        <div class="agency-modal-footer">
            <button class="agency-btn agency-btn-secondary modal-cancel" type="button">
                <?php esc_html_e('Cancel', 'agency-client-portal'); ?>
            </button>
            <button class="agency-btn agency-btn-primary" id="update-external-link-btn" type="button">
                <?php esc_html_e('Update', 'agency-client-portal'); ?>
            </button>
        </div>
    </div>
</div>

<?php ob_start(); ?>
document.addEventListener('DOMContentLoaded', function() {
    var addLinkBtn = document.getElementById('add-external-link-btn');
    var createLinkBtn = document.getElementById('create-external-link-btn');
    
    if (!addLinkBtn || !createLinkBtn) return;
    
    if (typeof AgencyModal === 'undefined') {
        console.error('AgencyModal not loaded');
        return;
    }
    
    var linkModal = new AgencyModal('new-external-link-modal');
    var editLinkModal = new AgencyModal('edit-external-link-modal');

    // Bouton annuler
    document.querySelectorAll('.modal-cancel').forEach(function(btn) {
        btn.addEventListener('click', function() {
            linkModal.close();
            editLinkModal.close();
        });
    });
    
    addLinkBtn.addEventListener('click', function() {
        linkModal.open();
    });
    
    createLinkBtn.addEventListener('click', function() {
        var emoji = document.getElementById('new-link-emoji').value.trim();
        var text = document.getElementById('new-link-text').value.trim();
        var url = document.getElementById('new-link-url').value.trim();
        
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
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert(<?php echo wp_json_encode(__('Error while creating the link', 'agency-client-portal')); ?>);
                createLinkBtn.disabled = false;
                createLinkBtn.textContent = <?php echo wp_json_encode(__('Add link', 'agency-client-portal')); ?>;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert(<?php echo wp_json_encode(__('Error while creating the link', 'agency-client-portal')); ?>);
            createLinkBtn.disabled = false;
            createLinkBtn.textContent = <?php echo wp_json_encode(__('Add link', 'agency-client-portal')); ?>;
        });
    });
    
    // Delete external link
    document.querySelectorAll('.delete-external-link').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if (!confirm(<?php echo wp_json_encode(__('Are you sure you want to delete this link?', 'agency-client-portal')); ?>)) {
                return;
            }
            
            var linkId = this.getAttribute('data-link-id');
            
            fetch('<?php echo esc_url(rest_url('agency/v1/external-links/')); ?>' + linkId, {
                method: 'DELETE',
                headers: {
                    'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>'
                }
            })
            .then(response => response.json())
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
    document.querySelectorAll('.edit-external-link').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var linkId = this.getAttribute('data-link-id');
            var linkEmoji = this.getAttribute('data-link-emoji');
            var linkText = this.getAttribute('data-link-text');
            var linkUrl = this.getAttribute('data-link-url');

            // Remplir le formulaire
            document.getElementById('edit-link-id').value = linkId;
            document.getElementById('edit-link-emoji').value = linkEmoji;
            document.getElementById('edit-link-text').value = linkText;
            document.getElementById('edit-link-url').value = linkUrl;

            // Mettre à jour l'emoji picker si nécessaire
            var emojiDisplay = document.querySelector('[data-input-field="edit-link-emoji"]');
            if (emojiDisplay) {
                var emojiSpan = emojiDisplay.querySelector('.emoji-display');
                if (emojiSpan) emojiSpan.textContent = linkEmoji;
            }

            editLinkModal.open();
        });
    });

    // Update external link
    var updateLinkBtn = document.getElementById('update-external-link-btn');
    if (updateLinkBtn) {
        updateLinkBtn.addEventListener('click', function() {
            var linkId = document.getElementById('edit-link-id').value;
            var emoji = document.getElementById('edit-link-emoji').value.trim();
            var text = document.getElementById('edit-link-text').value.trim();
            var url = document.getElementById('edit-link-url').value.trim();

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
                body: JSON.stringify({
                    emoji: emoji,
                    text: text,
                    url: url
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(<?php echo wp_json_encode(__('Error while updating the link', 'agency-client-portal')); ?>);
                    updateLinkBtn.disabled = false;
                    updateLinkBtn.textContent = <?php echo wp_json_encode(__('Update', 'agency-client-portal')); ?>;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert(<?php echo wp_json_encode(__('Error while updating the link', 'agency-client-portal')); ?>);
                updateLinkBtn.disabled = false;
                updateLinkBtn.textContent = <?php echo wp_json_encode(__('Update', 'agency-client-portal')); ?>;
            });
        });
    }

    // Script toolbar functionality
    var scriptContent = document.getElementById('script-content');
    var toolbarButtons = document.querySelectorAll('.script-toolbar .toolbar-btn');

    if (scriptContent && toolbarButtons.length > 0) {
        toolbarButtons.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var command = this.getAttribute('data-command');

                if (command === 'bold') {
                    document.execCommand('bold', false, null);
                } else if (command === 'highlight') {
                    // Get selected text
                    var selection = window.getSelection();
                    if (!selection.rangeCount) return;

                    var range = selection.getRangeAt(0);
                    if (range.toString().trim() === '') {
                        alert(<?php echo wp_json_encode(__('Please select text to highlight', 'agency-client-portal')); ?>);
                        return;
                    }

                    // Check if selection is inside script content
                    if (!scriptContent.contains(range.commonAncestorContainer)) {
                        alert(<?php echo wp_json_encode(__('Please select text in the script', 'agency-client-portal')); ?>);
                        return;
                    }

                    // Create highlight span
                    var span = document.createElement('span');
                    span.style.backgroundColor = 'yellow';
                    span.style.padding = '2px 0';
                    range.surroundContents(span);

                    // Clear selection
                    selection.removeAllRanges();
                } else if (command === 'highlight-comment') {
                    // Get selected text
                    var selection = window.getSelection();
                    if (!selection.rangeCount) return;

                    var range = selection.getRangeAt(0);
                    var selectedText = range.toString().trim();

                    if (selectedText === '') {
                        alert(<?php echo wp_json_encode(__('Please select text to highlight', 'agency-client-portal')); ?>);
                        return;
                    }

                    // Check if selection is inside script content
                    if (!scriptContent.contains(range.commonAncestorContainer)) {
                        alert(<?php echo wp_json_encode(__('Please select text in the script', 'agency-client-portal')); ?>);
                        return;
                    }

                    // Prompt for comment
                    var commentText = prompt(<?php echo wp_json_encode(__('Comment attached to the highlighted text:', 'agency-client-portal')); ?>, '');
                    if (!commentText || commentText.trim() === '') {
                        return;
                    }

                    // Create unique ID for this highlight
                    var highlightId = 'highlight-' + Date.now();

                    // Create highlight span with comment data
                    var span = document.createElement('span');
                    span.style.backgroundColor = '#fbbf24';
                    span.style.padding = '2px 0';
                    span.style.position = 'relative';
                    span.style.cursor = 'help';
                    span.setAttribute('data-comment', commentText);
                    span.setAttribute('data-highlight-id', highlightId);
                    span.setAttribute('title', commentText);
                    span.className = 'script-highlight-comment';

                    range.surroundContents(span);

                    // Clear selection
                    selection.removeAllRanges();

                    // Add click handler to show comment
                    span.addEventListener('click', function(e) {
                        e.preventDefault();
                        alert(<?php echo wp_json_encode(__('Comment:', 'agency-client-portal')); ?> + this.getAttribute('data-comment'));
                    });
                }
            });
        });
    }

    // Make existing highlighted-comment spans clickable
    if (scriptContent) {
        var existingHighlights = scriptContent.querySelectorAll('.script-highlight-comment');
        existingHighlights.forEach(function(span) {
            span.addEventListener('click', function(e) {
                e.preventDefault();
                alert(<?php echo wp_json_encode(__('Comment:', 'agency-client-portal')); ?> + this.getAttribute('data-comment'));
            });
        });
    }

    // Save script button (saves to settings.script_content)
    var saveScriptBtn = document.getElementById('save-script-btn');
    var saveStatus = document.querySelector('.script-save-status');

    if (saveScriptBtn && scriptContent) {
        saveScriptBtn.addEventListener('click', function() {
            var content = scriptContent.innerHTML;

            saveScriptBtn.disabled = true;
            saveScriptBtn.textContent = <?php echo wp_json_encode(__('Saving...', 'agency-client-portal')); ?>;
            saveStatus.textContent = '';

            fetch('<?php echo esc_url(rest_url('agency/v1/blocks/' . intval($block->id))); ?>', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>'
                },
                body: JSON.stringify({
                    settings: { script_content: content }
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    saveStatus.textContent = <?php echo wp_json_encode(__('✓ Saved', 'agency-client-portal')); ?>;
                    saveStatus.style.color = '#10b981';
                    setTimeout(function() {
                        saveStatus.textContent = '';
                    }, 3000);
                } else {
                    saveStatus.textContent = <?php echo wp_json_encode(__('✗ Error', 'agency-client-portal')); ?>;
                    saveStatus.style.color = '#ef4444';
                }
                saveScriptBtn.disabled = false;
                saveScriptBtn.innerHTML = agencyData.icons.save + ' ' + <?php echo wp_json_encode(__('Save script', 'agency-client-portal')); ?>;
            })
            .catch(error => {
                console.error('Error:', error);
                saveStatus.textContent = <?php echo wp_json_encode(__('✗ Error', 'agency-client-portal')); ?>;
                saveStatus.style.color = '#ef4444';
                saveScriptBtn.disabled = false;
                saveScriptBtn.innerHTML = agencyData.icons.save + ' ' + <?php echo wp_json_encode(__('Save script', 'agency-client-portal')); ?>;
            });
        });
    }

    // Auto-save description (saves to block content)
    var descriptionEl = document.getElementById('script-description');
    if (descriptionEl) {
        var descTimer = null;
        descriptionEl.addEventListener('input', function() {
            clearTimeout(descTimer);
            descTimer = setTimeout(function() {
                fetch('<?php echo esc_url(rest_url('agency/v1/blocks/' . intval($block->id))); ?>', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>' },
                    body: JSON.stringify({ content: descriptionEl.innerHTML })
                });
            }, 1200);
        });
    }
});
<?php wp_print_inline_script_tag(ob_get_clean()); ?>
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
});
<?php wp_print_inline_script_tag(ob_get_clean()); ?>

<?php if ($is_manager): ?>
<?php ob_start(); ?>
(function() {
    var integrateBtn   = document.getElementById('integrate-google-doc-btn');
    var disintegrateBtn = document.getElementById('disintegrate-google-doc-btn');
    var modal          = document.getElementById('google-doc-modal');
    var modalClose     = document.getElementById('google-doc-modal-close');
    var modalCancel    = document.getElementById('google-doc-modal-cancel');
    var modalSave      = document.getElementById('google-doc-modal-save');
    var urlInput       = document.getElementById('google-doc-url-input');

    function isSupportedGoogleIntegrationUrl(url) {
        return /^https?:\/\/docs\.google\.com\/(document|spreadsheets)(\/u\/\d+)?\/d\/[a-zA-Z0-9_-]+/i.test(url);
    }

    function openModal() {
        if (modal) { urlInput.value = ''; modal.classList.add('active'); }
    }
    function closeModal() {
        if (modal) modal.classList.remove('active');
    }

    function saveGoogleDocUrl(url) {
        var blockId = modalSave ? modalSave.getAttribute('data-block-id') : null;
        if (!blockId || typeof agencyData === 'undefined') return;
        fetch(agencyData.rest_url + 'blocks/' + blockId, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': agencyData.nonce },
            body: JSON.stringify({ settings: { google_doc_url: url } })
        }).then(function(r) { return r.json(); }).then(function(data) {
            if (data.success) window.location.reload();
        });
    }

    if (integrateBtn) integrateBtn.addEventListener('click', openModal);
    if (modalClose)  modalClose.addEventListener('click', closeModal);
    if (modalCancel) modalCancel.addEventListener('click', closeModal);

    if (modalSave) {
        modalSave.addEventListener('click', function() {
            var url = urlInput ? urlInput.value.trim() : '';
            if (!url || !isSupportedGoogleIntegrationUrl(url)) {
                alert('<?php echo esc_js(__('Please paste a valid Google Docs or Sheets URL.', 'agency-client-portal')); ?>');
                return;
            }
            saveGoogleDocUrl(url);
            closeModal();
        });
    }

    if (disintegrateBtn) {
        disintegrateBtn.addEventListener('click', function() {
            if (!confirm('<?php echo esc_js(__('Remove the embedded Google Doc and return to the native editor?', 'agency-client-portal')); ?>')) return;
            var blockId = disintegrateBtn.getAttribute('data-block-id');
            if (!blockId || typeof agencyData === 'undefined') return;
            fetch(agencyData.rest_url + 'blocks/' + blockId, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': agencyData.nonce },
                body: JSON.stringify({ settings: { google_doc_url: '' } })
            }).then(function(r) { return r.json(); }).then(function(data) {
                if (data.success) window.location.reload();
            });
        });
    }
})();
<?php wp_print_inline_script_tag(ob_get_clean()); ?>
<?php endif; ?>
<?php wp_footer(); ?>
</body>
</html>
