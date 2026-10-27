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
$agency_client_slug = get_query_var('agency_client');
$agency_project_slug = get_query_var('agency_project');
$agency_block_id = get_query_var('agency_block');

$agency_client = Agency_Client::get_by_slug($agency_client_slug);
$agency_project = $agency_client ? Agency_Project::get_by_slug($agency_client->id, $agency_project_slug) : null;
$agency_block = Agency_Block::get_by_id($agency_block_id);

if (!$agency_client || !$agency_project || !$agency_block || $agency_block->type !== 'script') {
    wp_die(esc_html__('Content not found', 'agency-client-portal'), 404);
}

$agency_comments = Agency_Comment::get_by_block($agency_block_id);
$agency_is_manager = current_user_can('manage_agency_projects');
$agency_external_links = Agency_External_Link::get_by_block($agency_block_id);
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
                    $agency_bv_subtitle_raw = !empty($agency_block->settings) ? json_decode($agency_block->settings, true) : array();
                    $agency_bv_subtitle = !empty($agency_bv_subtitle_raw['subtitle']) ? $agency_bv_subtitle_raw['subtitle'] : '';
                    $agency_bv_script_content = isset($agency_bv_subtitle_raw['script_content']) ? $agency_bv_subtitle_raw['script_content'] : '';
                    $agency_bv_description = $agency_block->content ?? '';
                    $agency_bv_google_doc_url = !empty($agency_bv_subtitle_raw['google_doc_url']) ? $agency_bv_subtitle_raw['google_doc_url'] : '';
                    // Transforme les URLs Google Docs/Sheets en URL d'aperçu intégrable
                    $agency_bv_google_doc_embed = '';
                    if ($agency_bv_google_doc_url) {
                        if (preg_match('#docs\.google\.com/document(?:/u/\d+)?/d/([a-zA-Z0-9_-]+)#', $agency_bv_google_doc_url, $_bv_gdoc_match)) {
                            $agency_bv_google_doc_embed = 'https://docs.google.com/document/d/' . $_bv_gdoc_match[1] . '/preview';
                        } elseif (preg_match('#docs\.google\.com/spreadsheets(?:/u/\d+)?/d/([a-zA-Z0-9_-]+)#', $agency_bv_google_doc_url, $_bv_gsheet_match)) {
                            $agency_bv_google_doc_embed = 'https://docs.google.com/spreadsheets/d/' . $_bv_gsheet_match[1] . '/preview';
                        }
                    }
                    ?>
                    <?php if ($agency_is_manager || $agency_bv_description): ?>
                    <div class="block-description script-description"
                         data-placeholder="<?php esc_attr_e('Add a description…', 'agency-client-portal'); ?>"
                         <?php if ($agency_is_manager): ?>
                         contenteditable="true"
                         id="script-description"
                         data-block-id="<?php echo esc_attr($agency_block->id); ?>"
                         <?php endif; ?>>
                        <?php echo wp_kses_post($agency_bv_description); ?>
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
                            <?php if (empty($agency_bv_google_doc_embed)): ?>
                            <button class="agency-btn agency-btn-sm agency-btn-secondary" id="integrate-google-doc-btn" type="button"
                                    data-block-id="<?php echo esc_attr($agency_block->id); ?>">
                                <?php Agency_Icons::render('file', 16); ?> <?php esc_html_e('Embed Google Docs/Sheets', 'agency-client-portal'); ?>
                            </button>
                            <?php else: ?>
                            <button class="agency-btn agency-btn-sm agency-btn-danger" id="disintegrate-google-doc-btn" type="button"
                                    data-block-id="<?php echo esc_attr($agency_block->id); ?>">
                                <?php Agency_Icons::render('x', 14); ?> <?php esc_html_e('Remove the embedded doc', 'agency-client-portal'); ?>
                            </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Two-Column Layout: 2/3 + 1/3 -->
                <?php if (!empty($agency_bv_google_doc_embed)): ?>
                <div class="google-doc-section">
                    <div class="google-doc-cta">
                        <a href="<?php echo esc_url($agency_bv_google_doc_url); ?>"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="agency-btn agency-btn-secondary">
                            <?php Agency_Icons::render('edit', 16); ?> <?php esc_html_e('Edit document', 'agency-client-portal'); ?>
                        </a>
                    </div>
                    <iframe
                        src="<?php echo esc_url($agency_bv_google_doc_embed); ?>"
                        class="google-doc-iframe"
                        frameborder="0"
                        allowfullscreen
                    ></iframe>
                </div>
                <?php else: ?>
                <?php $agency_can_comment = Agency_Block::client_can_comment_allowed($agency_block); ?>
                <div class="agency-bp-grid<?php echo !$agency_can_comment ? ' agency-bp-grid--solo' : ''; ?>">
                    <!-- Colonne principale : contenu du script -->
                    <div class="agency-bp-main">
                        <section class="agency-bp-card script-editor-section">
                            <header class="agency-bp-card__head">
                                <h3 class="agency-bp-card__title"><?php esc_html_e('Script', 'agency-client-portal'); ?></h3>
                                <?php if ($agency_is_manager): ?>
                                <span class="script-save-status is-saved" data-label-saved="<?php esc_attr_e('Saved', 'agency-client-portal'); ?>" data-label-dirty="<?php esc_attr_e('Unsaved changes', 'agency-client-portal'); ?>" data-label-error="<?php esc_attr_e('Error', 'agency-client-portal'); ?>"><?php Agency_Icons::render_ui('check', 14); ?><span class="script-save-status__text"><?php esc_html_e('Saved', 'agency-client-portal'); ?></span></span>
                                <?php endif; ?>
                            </header>

                            <?php if ($agency_is_manager): ?>
                                <!-- Éditeur riche (manager) -->
                                <div class="script-editor-wrapper">
                                    <div class="script-toolbar" role="toolbar">
                                        <select class="script-tool-select" data-command="fontName" aria-label="<?php esc_attr_e('Font', 'agency-client-portal'); ?>">
                                            <option value="Inter">Inter</option>
                                            <option value="Georgia">Georgia</option>
                                            <option value="Courier New">Courier New</option>
                                        </select>
                                        <select class="script-tool-select script-tool-select--size" data-command="fontSize" aria-label="<?php esc_attr_e('Size', 'agency-client-portal'); ?>">
                                            <?php foreach (array(12, 14, 16, 18, 20, 24) as $agency_px): ?>
                                            <option value="<?php echo intval($agency_px); ?>"<?php selected($agency_px, 16); ?>><?php echo intval($agency_px); ?> px</option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span class="script-toolbar__sep"></span>
                                        <button type="button" class="toolbar-btn" data-command="bold" title="<?php esc_attr_e('Bold', 'agency-client-portal'); ?>"><strong>B</strong></button>
                                        <button type="button" class="toolbar-btn" data-command="italic" title="<?php esc_attr_e('Italic', 'agency-client-portal'); ?>"><em>I</em></button>
                                        <button type="button" class="toolbar-btn" data-command="underline" title="<?php esc_attr_e('Underline', 'agency-client-portal'); ?>"><u>U</u></button>
                                        <div class="script-color">
                                            <button type="button" class="toolbar-btn script-color__btn" data-command="color-toggle" title="<?php esc_attr_e('Text colour', 'agency-client-portal'); ?>" aria-haspopup="true" aria-expanded="false">
                                                <span class="script-color__dot" style="background: var(--ag-client);"></span>
                                                <?php Agency_Icons::render_ui('chevron-down', 12); ?>
                                            </button>
                                            <div class="script-color__pop" hidden>
                                                <div class="script-color__grid">
                                                    <?php foreach (array('#FFFFFF', '#A1A1AA', '#71717A', '#52525B', '#7C83FD', '#4ADE80', '#FBBF24', '#F87171', '#60A5FA', '#34D399', '#F59E0B', '#EF4444', '#A78BFA', '#22D3EE', '#FB923C', '#EC4899') as $agency_sw): ?>
                                                    <button type="button" class="script-color__swatch" data-color="<?php echo esc_attr($agency_sw); ?>" style="background: <?php echo esc_attr($agency_sw); ?>;" aria-label="<?php echo esc_attr($agency_sw); ?>"></button>
                                                    <?php endforeach; ?>
                                                </div>
                                                <div class="script-color__custom">
                                                    <span><?php esc_html_e('Custom', 'agency-client-portal'); ?></span>
                                                    <input type="text" class="script-color__hex" value="<?php echo esc_attr(Agency_Template::client_color($agency_client)); ?>" maxlength="7" aria-label="<?php esc_attr_e('Hex colour', 'agency-client-portal'); ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <span class="script-toolbar__sep"></span>
                                        <button type="button" class="toolbar-btn" data-command="justifyLeft" title="<?php esc_attr_e('Align left', 'agency-client-portal'); ?>"><svg class="agency-icon" width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M3 5h14M3 10h9M3 15h12"/></svg></button>
                                        <button type="button" class="toolbar-btn" data-command="insertUnorderedList" title="<?php esc_attr_e('List', 'agency-client-portal'); ?>"><?php Agency_Icons::render_ui('list', 16); ?></button>
                                        <button type="button" class="toolbar-btn" data-command="createLink" title="<?php esc_attr_e('Link', 'agency-client-portal'); ?>"><?php Agency_Icons::render('link', 16); ?></button>
                                        <span class="script-toolbar__sep"></span>
                                        <button type="button" class="toolbar-btn" data-command="highlight" title="<?php esc_attr_e('Highlight', 'agency-client-portal'); ?>"><span class="script-tool-hl">H</span></button>
                                        <button type="button" class="toolbar-btn" data-command="highlight-comment" title="<?php esc_attr_e('Highlight with comment', 'agency-client-portal'); ?>"><?php Agency_Icons::render('comment', 16); ?></button>
                                    </div>
                                    <div
                                        id="script-content"
                                        class="script-editor"
                                        contenteditable="true"
                                        data-placeholder="<?php esc_attr_e('Enter the script content here...', 'agency-client-portal'); ?>"
                                    ><?php echo wp_kses_post($agency_bv_script_content); ?></div>
                                    <div class="script-actions">
                                        <span class="script-count"></span>
                                        <button class="agency-btn agency-btn-primary" id="save-script-btn" type="button">
                                            <?php esc_html_e('Save', 'agency-client-portal'); ?>
                                        </button>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- Lecture seule (client) -->
                                <div class="script-content-display">
                                    <?php if ($agency_bv_script_content): ?>
                                        <?php echo wp_kses_post($agency_bv_script_content); ?>
                                    <?php else: ?>
                                        <p class="no-content"><?php esc_html_e('No script available yet.', 'agency-client-portal'); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </section>
                    </div>

                    <?php if ($agency_can_comment): ?>
                    <aside class="agency-bp-aside">
                        <?php $agency_cm_comments = $agency_comments; include AGENCY_PLUGIN_DIR . 'templates/partials/block-comments.php'; ?>
                    </aside>
                    <?php endif; ?>
                </div>
                <?php endif; /* google_doc_embed */ ?>
            </div>
        </div>
    </main>

    <?php include AGENCY_PLUGIN_DIR . 'templates/partials/footer.php'; ?>
</div>

<?php if ($agency_is_manager): ?>
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
                    data-block-id="<?php echo esc_attr($agency_block->id); ?>">
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
                block_id: <?php echo intval($agency_block->id); ?>,
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

    // Barre d'outils de l'éditeur de script
    var scriptContent = document.getElementById('script-content');
    var toolbar = document.querySelector('.script-toolbar');
    var saveScriptBtn = document.getElementById('save-script-btn');
    var saveStatus = document.querySelector('.script-save-status');
    var saveStatusText = saveStatus ? saveStatus.querySelector('.script-save-status__text') : null;
    var countEl = document.querySelector('.script-count');
    var savedRange = null;

    function setStatus(state) {
        if (!saveStatus) return;
        saveStatus.classList.remove('is-saved', 'is-dirty', 'is-error');
        saveStatus.classList.add('is-' + state);
        saveStatusText.textContent = saveStatus.getAttribute('data-label-' + (state === 'saved' ? 'saved' : state));
    }

    function updateCount() {
        if (!countEl || !scriptContent) return;
        var text = scriptContent.innerText.trim();
        var words = text ? text.split(/\s+/).length : 0;
        var seqs = scriptContent.querySelectorAll('h1, h2, h3, h4').length;
        var parts = [];
        if (seqs) parts.push(seqs + ' ' + (seqs > 1 ? <?php echo wp_json_encode(__('sequences', 'agency-client-portal')); ?> : <?php echo wp_json_encode(__('sequence', 'agency-client-portal')); ?>));
        parts.push(words + ' ' + (words === 1 ? <?php echo wp_json_encode(__('word', 'agency-client-portal')); ?> : <?php echo wp_json_encode(__('words', 'agency-client-portal')); ?>));
        countEl.textContent = parts.join(' · ');
    }

    // Conserve la sélection quand le focus passe sur la barre d'outils
    document.addEventListener('selectionchange', function() {
        var sel = window.getSelection();
        if (sel.rangeCount && scriptContent && scriptContent.contains(sel.anchorNode)) {
            savedRange = sel.getRangeAt(0).cloneRange();
        }
    });

    function restoreSelection() {
        if (!scriptContent) return;
        scriptContent.focus();
        if (savedRange) {
            var sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(savedRange);
        }
    }

    function exec(command, value) {
        restoreSelection();
        document.execCommand('styleWithCSS', false, true);
        document.execCommand(command, false, value || null);
        setStatus('dirty');
    }

    function applyFontSize(px) {
        restoreSelection();
        document.execCommand('fontSize', false, '7');
        scriptContent.querySelectorAll('font[size="7"]').forEach(function(f) {
            var span = document.createElement('span');
            span.style.fontSize = px + 'px';
            while (f.firstChild) span.appendChild(f.firstChild);
            f.parentNode.replaceChild(span, f);
        });
        setStatus('dirty');
    }

    function highlightSelection(withComment) {
        var selection = window.getSelection();
        if (!selection.rangeCount) return;
        var range = selection.getRangeAt(0);
        if (range.toString().trim() === '' || !scriptContent.contains(range.commonAncestorContainer)) {
            alert(<?php echo wp_json_encode(__('Please select text in the script', 'agency-client-portal')); ?>);
            return;
        }
        var span = document.createElement('span');
        if (withComment) {
            var commentText = prompt(<?php echo wp_json_encode(__('Comment attached to the highlighted text:', 'agency-client-portal')); ?>, '');
            if (!commentText || commentText.trim() === '') return;
            span.setAttribute('data-comment', commentText);
            span.setAttribute('data-highlight-id', 'highlight-' + Date.now());
            span.setAttribute('title', commentText);
            span.className = 'script-highlight-comment';
            span.addEventListener('click', function(e) {
                e.preventDefault();
                alert(<?php echo wp_json_encode(__('Comment:', 'agency-client-portal')); ?> + this.getAttribute('data-comment'));
            });
        } else {
            span.className = 'script-highlight';
        }
        range.surroundContents(span);
        selection.removeAllRanges();
        setStatus('dirty');
    }

    if (scriptContent && toolbar) {
        // Empêche la barre de voler la sélection
        toolbar.addEventListener('mousedown', function(e) {
            if (e.target.closest('select, input')) return;
            e.preventDefault();
        });

        toolbar.querySelectorAll('.toolbar-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var command = this.getAttribute('data-command');
                if (command === 'color-toggle') {
                    var pop = toolbar.querySelector('.script-color__pop');
                    pop.hidden = !pop.hidden;
                    this.setAttribute('aria-expanded', pop.hidden ? 'false' : 'true');
                } else if (command === 'createLink') {
                    var url = prompt(<?php echo wp_json_encode(__('Link URL:', 'agency-client-portal')); ?>, 'https://');
                    if (url && /^https?:\/\//i.test(url)) exec('createLink', url);
                } else if (command === 'highlight') {
                    highlightSelection(false);
                } else if (command === 'highlight-comment') {
                    highlightSelection(true);
                } else {
                    exec(command);
                }
            });
        });

        toolbar.querySelectorAll('.script-tool-select').forEach(function(sel) {
            sel.addEventListener('change', function() {
                if (this.getAttribute('data-command') === 'fontSize') {
                    applyFontSize(this.value);
                } else {
                    exec(this.getAttribute('data-command'), this.value);
                }
            });
        });

        function applyColor(color) {
            exec('foreColor', color);
            toolbar.querySelector('.script-color__dot').style.background = color;
            toolbar.querySelector('.script-color__hex').value = color;
        }

        toolbar.querySelectorAll('.script-color__swatch').forEach(function(sw) {
            sw.addEventListener('click', function() { applyColor(this.getAttribute('data-color')); });
        });

        toolbar.querySelector('.script-color__hex').addEventListener('change', function() {
            if (/^#[0-9a-f]{6}$/i.test(this.value)) applyColor(this.value);
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.script-color')) {
                var pop = toolbar.querySelector('.script-color__pop');
                if (pop) pop.hidden = true;
            }
        });

        scriptContent.addEventListener('input', function() {
            setStatus('dirty');
            updateCount();
        });
        updateCount();

        // Rend cliquables les surlignages commentés déjà enregistrés
        scriptContent.querySelectorAll('.script-highlight-comment').forEach(function(span) {
            span.addEventListener('click', function(e) {
                e.preventDefault();
                alert(<?php echo wp_json_encode(__('Comment:', 'agency-client-portal')); ?> + this.getAttribute('data-comment'));
            });
        });
    }

    // Sauvegarde du script (settings.script_content)
    if (saveScriptBtn && scriptContent) {
        saveScriptBtn.addEventListener('click', function() {
            saveScriptBtn.disabled = true;

            fetch('<?php echo esc_url(rest_url('agency/v1/blocks/' . intval($agency_block->id))); ?>', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>'
                },
                body: JSON.stringify({
                    settings: { script_content: scriptContent.innerHTML }
                })
            })
            .then(function(response) { return response.json(); })
            .then(function(data) { setStatus(data.success ? 'saved' : 'error'); })
            .catch(function() { setStatus('error'); })
            .finally(function() { saveScriptBtn.disabled = false; });
        });
    }

    // Auto-save description (saves to block content)
    var descriptionEl = document.getElementById('script-description');
    if (descriptionEl) {
        var descTimer = null;
        descriptionEl.addEventListener('input', function() {
            clearTimeout(descTimer);
            descTimer = setTimeout(function() {
                fetch('<?php echo esc_url(rest_url('agency/v1/blocks/' . intval($agency_block->id))); ?>', {
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

<?php if ($agency_is_manager): ?>
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
