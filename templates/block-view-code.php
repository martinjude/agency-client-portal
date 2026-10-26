<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Block View: Code
 * Affiche un bloc de code avec coloration syntaxique (Prism.js).
 */
$client_slug  = get_query_var('agency_client');
$project_slug = get_query_var('agency_project');
$block_id     = get_query_var('agency_block');

$client  = Agency_Client::get_by_slug($client_slug);
$project = $client ? Agency_Project::get_by_slug($client->id, $project_slug) : null;
$block   = Agency_Block::get_by_id($block_id);

if (!$client || !$project || !$block || $block->type !== 'code') {
    wp_die(esc_html__('Content not found', 'agency-client-portal'), 404);
}

// Vérifier l'accès
$token_authenticated = Agency_Security::can_access_client_by_token($client->id);
$wp_authenticated    = is_user_logged_in() && agency()->security->user_can_access_client($client->id);
if (!$token_authenticated && !$wp_authenticated) {
    wp_die(esc_html__('Access denied', 'agency-client-portal'), 403);
}

$is_manager    = current_user_can('manage_agency_projects');
$comments      = Agency_Block::client_can_comment_allowed($block) ? Agency_Comment::get_by_block($block_id) : array();
$external_links = Agency_External_Link::get_by_block($block_id);

$settings  = !empty($block->settings) ? json_decode($block->settings, true) : array();
$file_name = !empty($settings['file_name']) ? $settings['file_name'] : '';
$language  = !empty($settings['language'])  ? $settings['language']  : 'plaintext';
$subtitle  = !empty($settings['subtitle'])  ? $settings['subtitle']  : '';
$code      = !empty($block->content)        ? $block->content        : '';

$back_url = home_url("/client/{$client->slug}/project/{$project->slug}");
$can_comment = Agency_Block::client_can_comment_allowed($block);
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
            <div class="agency-block-view agency-block-code" data-block-id="<?php echo esc_attr($block->id); ?>">

                <?php include AGENCY_PLUGIN_DIR . 'templates/partials/block-head.php'; ?>

                <div class="block-header-full agency-bv-bar">

                    <?php if ($subtitle || $is_manager): ?>
                    <p class="block-description<?php echo $is_manager ? ' agency-block-subtitle--editable' : ''; ?>"
                       <?php if ($is_manager): ?>contenteditable="true" data-block-id="<?php echo esc_attr($block->id); ?>"<?php endif; ?>>
                        <?php echo esc_html($subtitle); ?>
                    </p>
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

                    <div class="agency-actions">
                        <?php if ($is_manager): ?>
                        <button class="agency-btn agency-btn-primary" id="edit-code-btn" type="button">
                            <?php esc_html_e('Edit code', 'agency-client-portal'); ?>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="agency-bp-grid<?php echo !$can_comment ? ' agency-bp-grid--solo' : ''; ?>">

                    <!-- Colonne principale : code -->
                    <div class="agency-bp-main">

                        <?php if ($code): ?>
                        <?php
                        $code_lines = substr_count(rtrim($code, "\r\n"), "\n") + 1;
                        $code_bytes = strlen($code);
                        $code_lang  = ($language === 'markup') ? 'HTML' : strtoupper($language === 'plaintext' ? 'TXT' : $language);
                        ?>
                        <section class="agency-bp-card agency-code-card">
                            <header class="agency-code-tabs">
                                <span class="agency-code-tab">
                                    <?php Agency_Icons::render('file', 16); ?>
                                    <span class="agency-code-tab__name"><?php echo esc_html($file_name ? $file_name : $block->title); ?></span>
                                </span>
                                <span class="agency-badge agency-code-lang"><?php echo esc_html($code_lang); ?></span>
                            </header>
                            <div class="agency-code-block-wrapper">
                                <pre class="line-numbers language-<?php echo esc_attr($language); ?>"><code id="code-content" class="language-<?php echo esc_attr($language); ?>"><?php echo esc_html($code); ?></code></pre>
                            </div>
                            <footer class="agency-code-foot">
                                <span class="agency-code-stats">
                                    <?php
                                    /* translators: 1: language, 2: number of lines, 3: size in bytes */
                                    echo esc_html(sprintf(__('%1$s · %2$s · %3$s', 'agency-client-portal'),
                                        $code_lang,
                                        sprintf(_n('%d line', '%d lines', $code_lines, 'agency-client-portal'), $code_lines),
                                        sprintf(_n('%s byte', '%s bytes', $code_bytes, 'agency-client-portal'), number_format_i18n($code_bytes))
                                    ));
                                    ?>
                                </span>
                                <button class="agency-btn agency-code-copy-btn" id="copy-code-btn" type="button"
                                        data-code-b64="<?php echo esc_attr(base64_encode($code)); ?>"
                                        data-label-copy="<?php esc_attr_e('Copy code', 'agency-client-portal'); ?>"
                                        data-label-copied="<?php esc_attr_e('Copied!', 'agency-client-portal'); ?>"
                                        data-label-error="<?php esc_attr_e('Error', 'agency-client-portal'); ?>"
                                        title="<?php esc_attr_e('Copy code', 'agency-client-portal'); ?>">
                                    <span class="agency-code-copy-icon" aria-hidden="true">
                                        <svg class="icon-copy" width="16" height="16" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6.2474 6.25033V2.91699H17.0807V13.7503H13.7474M13.7474 6.25033V17.0837H2.91406V6.25033H13.7474Z" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"/></svg>
                                        <svg class="icon-check" width="16" height="16" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><polyline points="3,10 8,15 17,5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </span>
                                    <span class="copy-btn-label"><?php esc_html_e('Copy code', 'agency-client-portal'); ?></span>
                                </button>
                            </footer>
                        </section>
                        <?php elseif ($is_manager): ?>
                        <section class="agency-bp-card agency-code-empty">
                            <?php Agency_Icons::render_glyph('code', 32); ?>
                            <p><?php esc_html_e('No code yet.', 'agency-client-portal'); ?></p>
                            <button class="agency-btn agency-btn-primary" id="edit-code-btn-empty" type="button">
                                <?php esc_html_e('Add code', 'agency-client-portal'); ?>
                            </button>
                        </section>
                        <?php else: ?>
                        <section class="agency-bp-card agency-code-empty">
                            <p><?php esc_html_e('No code available.', 'agency-client-portal'); ?></p>
                        </section>
                        <?php endif; ?>

                    </div>

                    <?php if ($can_comment): ?>
                    <aside class="agency-bp-aside">
                        <?php $cm_comments = $comments; include AGENCY_PLUGIN_DIR . 'templates/partials/block-comments.php'; ?>
                    </aside>
                    <?php endif; ?>

                </div>

            </div>
        </div>
    </main>

    <?php include AGENCY_PLUGIN_DIR . 'templates/partials/footer.php'; ?>

    <?php if ($is_manager): ?>
    <!-- Modal : nouveau lien externe -->
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
                    <input type="text" id="new-link-text" class="agency-form-input" placeholder="<?php esc_attr_e('e.g. Documentation', 'agency-client-portal'); ?>" required>
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

    <!-- Modal : modifier lien externe -->
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

    <!-- Modal : modifier le code -->
    <div class="agency-modal" id="code-edit-modal">
        <div class="agency-modal-content agency-modal-content--wide">
            <div class="agency-modal-header">
                <h3 class="agency-modal-title"><?php esc_html_e('Edit code', 'agency-client-portal'); ?></h3>
                <button class="agency-modal-close" type="button" id="code-edit-modal-close">&times;</button>
            </div>
            <div class="agency-modal-body">
                <div class="agency-form-group">
                    <label class="agency-form-label" for="code-block-filename"><?php esc_html_e('File name', 'agency-client-portal'); ?></label>
                    <input type="text" id="code-block-filename" class="agency-form-input"
                           value="<?php echo esc_attr($file_name); ?>"
                           placeholder="<?php esc_attr_e('app.py', 'agency-client-portal'); ?>">
                    <span class="agency-form-help"><?php esc_html_e('The extension determines the syntax highlighting.', 'agency-client-portal'); ?></span>
                </div>
                <div class="agency-form-group">
                    <label class="agency-form-label" for="code-block-content"><?php esc_html_e('Code', 'agency-client-portal'); ?></label>
                    <textarea id="code-block-content" class="agency-form-input"
                              rows="16"><?php echo esc_textarea($code); ?></textarea>
                </div>
            </div>
            <div class="agency-modal-footer">
                <button class="agency-btn agency-btn-secondary" type="button" id="code-edit-cancel"><?php esc_html_e('Cancel', 'agency-client-portal'); ?></button>
                <button class="agency-btn agency-btn-primary" type="button" id="code-edit-save"><?php esc_html_e('Save', 'agency-client-portal'); ?></button>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php ob_start(); ?>
document.addEventListener('DOMContentLoaded', function() {

    <?php if ($is_manager): ?>

    // ----- Language detection from file extension -----
    var extensionMap = {
        'php': 'php', 'js': 'javascript', 'ts': 'typescript', 'jsx': 'javascript', 'tsx': 'typescript',
        'css': 'css', 'html': 'markup', 'htm': 'markup', 'xml': 'markup',
        'py': 'python', 'sh': 'bash', 'bash': 'bash', 'sql': 'sql',
        'json': 'json', 'yaml': 'yaml', 'yml': 'yaml', 'md': 'markup'
    };
    function detectLanguage(filename) {
        var ext = filename.split('.').pop().toLowerCase();
        return extensionMap[ext] || 'plaintext';
    }

    // ----- External links -----
    var addLinkBtn = document.getElementById('add-external-link-btn');
    var createLinkBtn = document.getElementById('create-external-link-btn');
    if (addLinkBtn && createLinkBtn) {
        var linkModal = typeof AgencyModal !== 'undefined' ? new AgencyModal('new-external-link-modal') : null;
        var editLinkModal = typeof AgencyModal !== 'undefined' ? new AgencyModal('edit-external-link-modal') : null;

        document.querySelectorAll('#new-external-link-modal .modal-cancel, #edit-external-link-modal .modal-cancel').forEach(function(b) {
            b.addEventListener('click', function() {
                if (linkModal) linkModal.close(); if (editLinkModal) editLinkModal.close();
            });
        });
        document.querySelectorAll('#new-external-link-modal .agency-modal-close, #edit-external-link-modal .agency-modal-close').forEach(function(b) {
            b.addEventListener('click', function() {
                if (linkModal) linkModal.close(); if (editLinkModal) editLinkModal.close();
            });
        });

        addLinkBtn.addEventListener('click', function() { if (linkModal) linkModal.open(); });

        createLinkBtn.addEventListener('click', function() {
            var emoji = document.getElementById('new-link-emoji').value.trim();
            var text  = document.getElementById('new-link-text').value.trim();
            var url   = document.getElementById('new-link-url').value.trim();
            if (!text || !url) { alert(<?php echo wp_json_encode(__('Please fill in all fields', 'agency-client-portal')); ?>); return; }
            createLinkBtn.disabled = true;
            fetch('<?php echo esc_url(rest_url('agency/v1/external-links')); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>' },
                body: JSON.stringify({ block_id: <?php echo intval($block->id); ?>, emoji: emoji, text: text, url: url })
            }).then(function(r) { return r.json(); }).then(function(d) {
                if (d.success) window.location.reload();
                else { alert(<?php echo wp_json_encode(__('Error while creating the link', 'agency-client-portal')); ?>); createLinkBtn.disabled = false; }
            }).catch(function() { createLinkBtn.disabled = false; });
        });

        document.querySelectorAll('.delete-external-link').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault(); e.stopPropagation();
                if (!confirm(<?php echo wp_json_encode(__('Delete this link?', 'agency-client-portal')); ?>)) return;
                fetch('<?php echo esc_url(rest_url('agency/v1/external-links/')); ?>' + btn.getAttribute('data-link-id'), {
                    method: 'DELETE', headers: { 'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>' }
                }).then(function(r) { return r.json(); }).then(function(d) { if (d.success) window.location.reload(); });
            });
        });

        document.querySelectorAll('.edit-external-link').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault(); e.stopPropagation();
                document.getElementById('edit-link-id').value   = btn.getAttribute('data-link-id');
                document.getElementById('edit-link-emoji').value = btn.getAttribute('data-link-emoji');
                document.getElementById('edit-link-text').value  = btn.getAttribute('data-link-text');
                document.getElementById('edit-link-url').value   = btn.getAttribute('data-link-url');
                if (editLinkModal) editLinkModal.open();
            });
        });

        var updateLinkBtn = document.getElementById('update-external-link-btn');
        if (updateLinkBtn) {
            updateLinkBtn.addEventListener('click', function() {
                var linkId = document.getElementById('edit-link-id').value;
                var emoji  = document.getElementById('edit-link-emoji').value.trim();
                var text   = document.getElementById('edit-link-text').value.trim();
                var url    = document.getElementById('edit-link-url').value.trim();
                if (!text || !url) { alert(<?php echo wp_json_encode(__('Please fill in all fields', 'agency-client-portal')); ?>); return; }
                updateLinkBtn.disabled = true;
                fetch('<?php echo esc_url(rest_url('agency/v1/external-links/')); ?>' + linkId, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>' },
                    body: JSON.stringify({ emoji: emoji, text: text, url: url })
                }).then(function(r) { return r.json(); }).then(function(d) {
                    if (d.success) window.location.reload();
                    else { updateLinkBtn.disabled = false; }
                }).catch(function() { updateLinkBtn.disabled = false; });
            });
        }
    }

    // ----- Code modal open/close -----
    function openCodeModal() { document.getElementById('code-edit-modal').classList.add('active'); }
    function closeCodeModal() { document.getElementById('code-edit-modal').classList.remove('active'); }
    var editBtn = document.getElementById('edit-code-btn');
    if (editBtn) editBtn.addEventListener('click', openCodeModal);
    var editBtnEmpty = document.getElementById('edit-code-btn-empty');
    if (editBtnEmpty) editBtnEmpty.addEventListener('click', openCodeModal);
    document.getElementById('code-edit-modal-close').addEventListener('click', closeCodeModal);
    document.getElementById('code-edit-cancel').addEventListener('click', closeCodeModal);
    document.getElementById('code-edit-modal').addEventListener('click', function(e) { if (e.target === this) closeCodeModal(); });

    // ----- Save code -----
    document.getElementById('code-edit-save').addEventListener('click', function() {
        var saveBtn  = this;
        var content  = document.getElementById('code-block-content').value;
        var filename = document.getElementById('code-block-filename').value.trim();
        var lang     = detectLanguage(filename);
        saveBtn.disabled = true;
        saveBtn.textContent = <?php echo wp_json_encode(__('Saving...', 'agency-client-portal')); ?>;
        fetch(agencyData.rest_url + 'blocks/<?php echo intval($block->id); ?>', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': agencyData.nonce },
            body: JSON.stringify({ content: content, settings: { file_name: filename, language: lang } })
        }).then(function(r) { return r.json(); }).then(function(data) {
            if (data.success) { window.location.reload(); }
            else { saveBtn.disabled = false; saveBtn.textContent = <?php echo wp_json_encode(__('Save', 'agency-client-portal')); ?>; }
        }).catch(function() { saveBtn.disabled = false; saveBtn.textContent = <?php echo wp_json_encode(__('Save', 'agency-client-portal')); ?>; });
    });

    // ----- Subtitle auto-save -----
    document.querySelectorAll('.agency-block-subtitle--editable').forEach(function(el) {
        var blockId = el.getAttribute('data-block-id');
        el.addEventListener('blur', function() {
            fetch(agencyData.rest_url + 'blocks/' + blockId, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': agencyData.nonce },
                body: JSON.stringify({ settings: { subtitle: el.textContent.trim() } })
            });
        });
    });

    <?php endif; ?>

});
<?php wp_print_inline_script_tag(ob_get_clean()); ?>

<?php wp_footer(); ?>
</body>
</html>
