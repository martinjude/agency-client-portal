<?php
/**
 * Block View – Dépôt de fichiers
 *
 * @package Agency
 */

if (!defined('ABSPATH')) {
    exit;
}

$client_slug  = get_query_var('agency_client');
$project_slug = get_query_var('agency_project');
$block_id     = get_query_var('agency_block');

$client  = Agency_Client::get_by_slug($client_slug);
$project = $client ? Agency_Project::get_by_slug($client->id, $project_slug) : null;
$block   = Agency_Block::get_by_id($block_id);

if (!$client || !$project || !$block || $block->type !== 'file_deposit') {
    wp_die(esc_html__('Content not found', 'agency-client-portal'), 404);
}

$is_manager = current_user_can('manage_agency_projects');
$assets     = Agency_Asset::get_by_block($block_id);

// Settings du bloc
$block_settings      = !empty($block->settings) ? json_decode($block->settings, true) : array();
$allowed_extensions  = !empty($block_settings['allowed_extensions'])
    ? (array) $block_settings['allowed_extensions']
    : array('zip');
$max_size_mb         = !empty($block_settings['max_size_mb']) ? intval($block_settings['max_size_mb']) : 100;
$allowed_ext_str     = implode(', ', $allowed_extensions);
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
            <div class="agency-block-view agency-block-file-deposit">

                <!-- Block Header -->
                <?php include AGENCY_PLUGIN_DIR . 'templates/partials/block-head.php'; ?>

                <div class="block-header-full agency-bv-bar">

                    <?php if ($block->content): ?>
                    <div class="block-description">
                        <?php echo wp_kses_post($block->content); ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Manager : configuration du dépôt -->
                <?php if ($is_manager): ?>
                <div class="file-deposit-settings">
                    <h3><?php esc_html_e('Deposit settings', 'agency-client-portal'); ?></h3>
                    <div class="file-deposit-settings-form">
                        <div class="agency-form-group">
                            <label class="agency-form-label" for="fd-allowed-ext">
                                <?php esc_html_e('Allowed extensions (comma-separated)', 'agency-client-portal'); ?>
                            </label>
                            <input type="text"
                                   id="fd-allowed-ext"
                                   class="agency-form-input"
                                   value="<?php echo esc_attr($allowed_ext_str); ?>"
                                   placeholder="zip, pdf, docx">
                        </div>
                        <div class="agency-form-group">
                            <label class="agency-form-label" for="fd-max-size">
                                <?php esc_html_e('Maximum size (MB)', 'agency-client-portal'); ?>
                            </label>
                            <input type="number"
                                   id="fd-max-size"
                                   class="agency-form-input"
                                   value="<?php echo esc_attr($max_size_mb); ?>"
                                   min="1"
                                   max="2048">
                        </div>
                        <button class="agency-btn agency-btn-primary" id="fd-save-settings-btn"
                                data-block-id="<?php echo esc_attr($block->id); ?>">
                            <?php esc_html_e('Save settings', 'agency-client-portal'); ?>
                        </button>
                        <span class="fd-settings-status" style="margin-left:10px;font-size:13px;color:var(--agency-text-muted);"></span>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Zone de dépôt -->
                <div class="file-deposit-upload">
                    <h3><?php esc_html_e('Upload a file', 'agency-client-portal'); ?></h3>
                    <p class="file-deposit-constraints">
                        <?php echo wp_kses_post(sprintf(
                            /* translators: 1: allowed file extensions, 2: maximum size in MB */
                            __('Accepted files: <strong>%1$s</strong> — Max size: <strong>%2$d MB</strong>', 'agency-client-portal'),
                            esc_html($allowed_ext_str),
                            (int) $max_size_mb
                        )); ?>
                    </p>
                    <div class="file-deposit-zone" id="file-deposit-zone">
                        <span class="file-deposit-zone-icon"><?php Agency_Icons::render('folder', 40); ?></span>
                        <p><?php esc_html_e('Drag and drop a file here or click to choose', 'agency-client-portal'); ?></p>
                        <input type="file"
                               id="file-deposit-input"
                               class="file-deposit-input-hidden"
                               accept=".<?php echo esc_attr(implode(',.', $allowed_extensions)); ?>">
                    </div>
                    <div id="file-deposit-progress" style="display:none;">
                        <div class="file-deposit-progress-bar"><div class="file-deposit-progress-fill"></div></div>
                        <span class="file-deposit-progress-label"><?php esc_html_e('Uploading…', 'agency-client-portal'); ?></span>
                    </div>
                    <p id="file-deposit-error" class="file-deposit-error" style="display:none;"></p>
                </div>

                <!-- Liste des fichiers déposés -->
                <?php
                $client_assets = array();
                $agency_assets = array();
                if (!empty($assets)) {
                    foreach ($assets as $asset) {
                        if (!empty($asset->suggested_by_client)) {
                            $client_assets[] = $asset;
                        } else {
                            $agency_assets[] = $asset;
                        }
                    }
                }
                ?>
                <div class="file-deposit-list" id="file-deposit-list">
                    <?php if (empty($assets)): ?>
                        <h3><?php echo esc_html($is_manager ? __('Received files', 'agency-client-portal') : __('My uploads', 'agency-client-portal')); ?></h3>
                        <p class="file-deposit-empty"><?php esc_html_e('No files uploaded yet.', 'agency-client-portal'); ?></p>
                    <?php else: ?>
                        <div class="file-deposit-columns" style="display: flex; gap: 30px; flex-wrap: wrap;">
                            <?php if (!empty($client_assets)): ?>
                            <div class="file-deposit-column" style="flex: 1; min-width: 300px;">
                                <h3><?php /* translators: %s: name of the uploader (client or site) */ echo esc_html(sprintf(__('Files from %s', 'agency-client-portal'), $client->name)); ?></h3>
                                <div class="file-deposit-items">
                                    <?php foreach ($client_assets as $asset): ?>
                                    <div class="file-deposit-item" data-asset-id="<?php echo esc_attr($asset->id); ?>">
                                        <span class="file-deposit-item-icon"><?php Agency_Icons::render('file', 16); ?></span>
                                        <div class="file-deposit-item-info">
                                            <span class="file-deposit-item-name"><?php echo esc_html($asset->original_filename); ?></span>
                                            <span class="file-deposit-item-meta">
                                                <?php echo esc_html(size_format($asset->file_size)); ?>
                                                &middot;
                                                <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($asset->created_at))); ?>
                                            </span>
                                        </div>
                                        <div class="file-deposit-item-actions">
                                            <a href="<?php echo esc_url(Agency_Asset::get_url($asset->id)); ?>"
                                               class="agency-btn agency-btn-sm agency-btn-secondary"
                                               download>
                                                <?php Agency_Icons::render('arrow-down', 15); ?> <?php esc_html_e('Download', 'agency-client-portal'); ?>
                                            </a>
                                            <?php if ($is_manager): ?>
                                            <button class="agency-btn-icon delete-deposit-asset"
                                                    data-asset-id="<?php echo esc_attr($asset->id); ?>"
                                                    title="<?php esc_attr_e('Delete', 'agency-client-portal'); ?>"><?php Agency_Icons::render('trash', 16); ?></button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($agency_assets)): ?>
                            <div class="file-deposit-column" style="flex: 1; min-width: 300px;">
                                <h3><?php /* translators: %s: name of the uploader (client or site) */ echo esc_html(sprintf(__('Files from %s', 'agency-client-portal'), get_bloginfo('name'))); ?></h3>
                                <div class="file-deposit-items">
                                    <?php foreach ($agency_assets as $asset): ?>
                                    <div class="file-deposit-item" data-asset-id="<?php echo esc_attr($asset->id); ?>">
                                        <span class="file-deposit-item-icon"><?php Agency_Icons::render('file', 16); ?></span>
                                        <div class="file-deposit-item-info">
                                            <span class="file-deposit-item-name"><?php echo esc_html($asset->original_filename); ?></span>
                                            <span class="file-deposit-item-meta">
                                                <?php echo esc_html(size_format($asset->file_size)); ?>
                                                &middot;
                                                <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($asset->created_at))); ?>
                                            </span>
                                        </div>
                                        <div class="file-deposit-item-actions">
                                            <a href="<?php echo esc_url(Agency_Asset::get_url($asset->id)); ?>"
                                               class="agency-btn agency-btn-sm agency-btn-secondary"
                                               download>
                                                <?php Agency_Icons::render('arrow-down', 15); ?> <?php esc_html_e('Download', 'agency-client-portal'); ?>
                                            </a>
                                            <?php if ($is_manager): ?>
                                            <button class="agency-btn-icon delete-deposit-asset"
                                                    data-asset-id="<?php echo esc_attr($asset->id); ?>"
                                                    title="<?php esc_attr_e('Delete', 'agency-client-portal'); ?>"><?php Agency_Icons::render('trash', 16); ?></button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </main>

    <?php include AGENCY_PLUGIN_DIR . 'templates/partials/footer.php'; ?>
</div>

<?php ob_start(); ?>
(function() {
    // ---- Manager: save settings ----
    var saveBtn = document.getElementById('fd-save-settings-btn');
    if (saveBtn) {
        saveBtn.addEventListener('click', function() {
            var blockId = this.getAttribute('data-block-id');
            var extInput = document.getElementById('fd-allowed-ext');
            var sizeInput = document.getElementById('fd-max-size');
            var statusEl = document.querySelector('.fd-settings-status');
            var extensions = extInput.value.split(',').map(function(e) { return e.trim().replace(/^\./, ''); }).filter(Boolean);
            var maxMb = parseInt(sizeInput.value, 10) || 100;

            fetch(agencyData.rest_url + 'blocks/' + blockId, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': agencyData.nonce },
                body: JSON.stringify({ settings: { allowed_extensions: extensions, max_size_mb: maxMb } })
            }).then(function(r) { return r.json(); }).then(function(data) {
                if (statusEl) statusEl.textContent = data.success ? '✓ <?php echo esc_js(__('Saved', 'agency-client-portal')); ?>' : '✗ <?php echo esc_js(__('Error', 'agency-client-portal')); ?>';
            });
        });
    }

    // ---- Client: file upload ----
    var zone      = document.getElementById('file-deposit-zone');
    var fileInput = document.getElementById('file-deposit-input');
    var progress  = document.getElementById('file-deposit-progress');
    var errorEl   = document.getElementById('file-deposit-error');

    function uploadFile(file) {
        if (!file) return;
        var formData = new FormData();
        formData.append('file', file);
        formData.append('block_id', <?php echo intval($block->id); ?>);

        if (progress) { progress.style.display = 'flex'; }
        if (errorEl)  { errorEl.style.display = 'none'; }

        var xhr = new XMLHttpRequest();
        xhr.open('POST', agencyData.rest_url + 'assets/deposit-upload');
        xhr.setRequestHeader('X-WP-Nonce', agencyData.nonce);

        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                var pct = Math.round((e.loaded / e.total) * 100);
                var fill = document.querySelector('.file-deposit-progress-fill');
                if (fill) fill.style.width = pct + '%';
            }
        };
        xhr.onload = function() {
            if (progress) progress.style.display = 'none';
            try {
                var data = JSON.parse(xhr.responseText);
                if (data.success) {
                    window.location.reload();
                } else {
                    if (errorEl) { errorEl.textContent = data.message || '<?php echo esc_js(__('Error during upload', 'agency-client-portal')); ?>'; errorEl.style.display = 'block'; }
                }
            } catch(e) {
                if (errorEl) { errorEl.textContent = '<?php echo esc_js(__('Server error', 'agency-client-portal')); ?>'; errorEl.style.display = 'block'; }
            }
        };
        xhr.onerror = function() {
            if (progress) progress.style.display = 'none';
            if (errorEl) { errorEl.textContent = '<?php echo esc_js(__('Network error', 'agency-client-portal')); ?>'; errorEl.style.display = 'block'; }
        };
        xhr.send(formData);
    }

    if (zone) {
        zone.addEventListener('click', function() { if (fileInput) fileInput.click(); });
        zone.addEventListener('dragover', function(e) { e.preventDefault(); zone.classList.add('is-dragover'); });
        zone.addEventListener('dragleave', function() { zone.classList.remove('is-dragover'); });
        zone.addEventListener('drop', function(e) {
            e.preventDefault();
            zone.classList.remove('is-dragover');
            var files = e.dataTransfer.files;
            if (files.length) uploadFile(files[0]);
        });
    }
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (this.files.length) uploadFile(this.files[0]);
        });
    }

    // ---- Manager: delete deposited asset ----
    document.querySelectorAll('.delete-deposit-asset').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (!confirm('<?php echo esc_js(__('Delete this file?', 'agency-client-portal')); ?>')) return;
            var assetId = this.getAttribute('data-asset-id');
            fetch(agencyData.rest_url + 'assets/' + assetId, {
                method: 'DELETE',
                headers: { 'X-WP-Nonce': agencyData.nonce }
            }).then(function() { window.location.reload(); });
        });
    });
})();
<?php wp_print_inline_script_tag(ob_get_clean()); ?>

<?php wp_footer(); ?>
</body>
</html>
