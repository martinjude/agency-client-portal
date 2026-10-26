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

                <?php if ($block->content): ?>
                <div class="block-header-full agency-bv-bar">
                    <div class="block-description">
                        <?php echo wp_kses_post($block->content); ?>
                    </div>
                </div>
                <?php endif; ?>

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
                $deposit_cols = array(
                    array(
                        'key'    => 'client',
                        'title'  => $is_manager ? __('Client files', 'agency-client-portal') : __('My uploads', 'agency-client-portal'),
                        'pill'   => '<span class="agency-tag agency-tag--client-outline">' . esc_html__('Client', 'agency-client-portal') . '</span>',
                        'assets' => $client_assets,
                    ),
                    array(
                        'key'    => 'agency',
                        'title'  => __('Agency files', 'agency-client-portal'),
                        'pill'   => '<span class="agency-pill agency-pill--off">' . esc_html__('Agency', 'agency-client-portal') . '</span>',
                        'assets' => $agency_assets,
                    ),
                );
                ?>

                <div class="agency-fd">

                <!-- Manager : configuration du dépôt -->
                <?php if ($is_manager): ?>
                <section class="agency-bp-card file-deposit-settings">
                    <header class="agency-bp-card__head">
                        <h3 class="agency-bp-card__title"><?php esc_html_e('Deposit settings', 'agency-client-portal'); ?></h3>
                        <span class="fd-settings-status"></span>
                    </header>
                    <div class="file-deposit-settings-form">
                        <div class="file-deposit-settings-field file-deposit-settings-field--grow">
                            <label class="agency-bp-label" for="fd-allowed-ext">
                                <?php esc_html_e('Allowed extensions', 'agency-client-portal'); ?>
                                <span class="agency-bp-label__hint"><?php esc_html_e('comma-separated', 'agency-client-portal'); ?></span>
                            </label>
                            <input type="text" id="fd-allowed-ext" class="agency-form-input"
                                   value="<?php echo esc_attr($allowed_ext_str); ?>" placeholder="zip, pdf, docx">
                        </div>
                        <div class="file-deposit-settings-field">
                            <label class="agency-bp-label" for="fd-max-size"><?php esc_html_e('Maximum size (MB)', 'agency-client-portal'); ?></label>
                            <div class="agency-input-suffix">
                                <input type="number" id="fd-max-size" class="agency-form-input"
                                       value="<?php echo esc_attr($max_size_mb); ?>" min="1" max="2048">
                                <span><?php esc_html_e('MB', 'agency-client-portal'); ?></span>
                            </div>
                        </div>
                        <button type="button" class="agency-btn agency-btn-primary" id="fd-save-settings-btn"
                                data-block-id="<?php echo esc_attr($block->id); ?>">
                            <?php esc_html_e('Save', 'agency-client-portal'); ?>
                        </button>
                    </div>
                </section>
                <?php endif; ?>

                <!-- Zone de dépôt + envois en cours -->
                <section class="agency-bp-card file-deposit-upload">
                    <div class="file-deposit-upload__grid">
                        <div>
                            <h3 class="agency-bp-card__title"><?php esc_html_e('Upload a file', 'agency-client-portal'); ?></h3>
                            <p class="file-deposit-constraints">
                                <?php echo esc_html(sprintf(
                                    /* translators: 1: allowed file extensions, 2: maximum size in MB */
                                    __('Accepted files: %1$s — max size: %2$d MB', 'agency-client-portal'),
                                    $allowed_ext_str,
                                    (int) $max_size_mb
                                )); ?>
                            </p>
                            <div class="file-deposit-zone" id="file-deposit-zone">
                                <span class="file-deposit-zone-icon"><?php Agency_Icons::render_glyph('file_deposit', 32); ?></span>
                                <p class="file-deposit-zone__title"><strong><?php esc_html_e('Choose a file', 'agency-client-portal'); ?></strong> <?php esc_html_e('or drag and drop', 'agency-client-portal'); ?></p>
                                <p class="file-deposit-zone__hint"><?php echo esc_html(strtoupper(implode(', ', $allowed_extensions)) . ' · ' . sprintf(/* translators: %d: maximum size in MB */ __('up to %d MB', 'agency-client-portal'), (int) $max_size_mb)); ?></p>
                                <input type="file" id="file-deposit-input" class="file-deposit-input-hidden"
                                       accept=".<?php echo esc_attr(implode(',.', $allowed_extensions)); ?>">
                            </div>
                            <p id="file-deposit-error" class="file-deposit-error" hidden></p>
                        </div>
                        <div class="file-deposit-queue-wrap">
                            <span class="agency-bp-label"><?php esc_html_e('In progress', 'agency-client-portal'); ?></span>
                            <div id="file-deposit-queue" class="file-deposit-queue"></div>
                        </div>
                    </div>
                </section>

                <!-- Fichiers déposés -->
                <div class="file-deposit-list" id="file-deposit-list">
                    <?php foreach ($deposit_cols as $col): ?>
                    <section class="agency-bp-card file-deposit-column file-deposit-column--<?php echo esc_attr($col['key']); ?>">
                        <header class="agency-bp-card__head agency-bp-card__head--rule">
                            <h3 class="agency-bp-card__title"><?php echo esc_html($col['title']); ?> <span class="agency-count"><?php echo intval(count($col['assets'])); ?></span></h3>
                            <?php echo $col['pill']; // phpcs:ignore WordPress.Security.EscapeOutput -- construit ci-dessus avec esc_html__ ?>
                        </header>
                        <?php if (empty($col['assets'])): ?>
                            <p class="file-deposit-empty"><?php esc_html_e('No files uploaded yet.', 'agency-client-portal'); ?></p>
                        <?php else: ?>
                        <div class="file-deposit-items">
                            <?php foreach ($col['assets'] as $asset):
                                if (!empty($asset->suggested_by_client)) {
                                    $author = $client->name;
                                } else {
                                    $uploader = !empty($asset->uploaded_by) ? get_userdata((int) $asset->uploaded_by) : null;
                                    $author   = $uploader ? $uploader->display_name : get_bloginfo('name');
                                }
                            ?>
                            <div class="file-deposit-item" data-asset-id="<?php echo esc_attr($asset->id); ?>">
                                <?php echo Agency_Template::file_tile($asset->original_filename); // phpcs:ignore WordPress.Security.EscapeOutput -- échappé dans file_tile() ?>
                                <div class="file-deposit-item-info">
                                    <span class="file-deposit-item-name"><?php echo esc_html($asset->original_filename); ?></span>
                                    <span class="file-deposit-item-meta"><?php echo esc_html(size_format($asset->file_size) . ' · ' . $author . ' · ' . Agency_Template::time_ago($asset->created_at)); ?></span>
                                </div>
                                <div class="file-deposit-item-actions">
                                    <a href="<?php echo esc_url(Agency_Asset::get_url($asset->id)); ?>" class="agency-btn-icon agency-btn-icon--ghost" download
                                       title="<?php esc_attr_e('Download', 'agency-client-portal'); ?>" aria-label="<?php esc_attr_e('Download', 'agency-client-portal'); ?>"><?php Agency_Icons::render('download', 16); ?></a>
                                    <?php if ($is_manager): ?>
                                    <button type="button" class="agency-btn-icon agency-btn-icon--ghost delete-deposit-asset"
                                            data-asset-id="<?php echo esc_attr($asset->id); ?>"
                                            title="<?php esc_attr_e('Delete', 'agency-client-portal'); ?>" aria-label="<?php esc_attr_e('Delete', 'agency-client-portal'); ?>"><?php Agency_Icons::render('trash', 16); ?></button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </section>
                    <?php endforeach; ?>
                </div>

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
    var errorEl   = document.getElementById('file-deposit-error');

    var queue = document.getElementById('file-deposit-queue');
    var maxBytes = <?php echo intval($max_size_mb) * 1024 * 1024; ?>;

    function showError(msg) {
        if (!errorEl) return;
        errorEl.textContent = msg;
        errorEl.hidden = false;
    }

    function fileTile(name) {
        var ext = (name.split('.').pop() || '').toLowerCase();
        var span = document.createElement('span');
        span.className = 'agency-ftile';
        span.textContent = ext.substr(0, 4).toUpperCase();
        return span;
    }

    function uploadFile(file) {
        if (!file) return;
        if (errorEl) errorEl.hidden = true;
        if (file.size > maxBytes) { showError('<?php echo esc_js(__('File is too large', 'agency-client-portal')); ?>'); return; }

        var formData = new FormData();
        formData.append('file', file);
        formData.append('block_id', <?php echo intval($block->id); ?>);

        // Ligne « en cours »
        var row = document.createElement('div');
        row.className = 'file-deposit-queue__row';
        var info = document.createElement('div');
        info.className = 'file-deposit-queue__info';
        var name = document.createElement('span');
        name.className = 'file-deposit-item-name';
        name.textContent = file.name;
        var bar = document.createElement('div');
        bar.className = 'file-deposit-progress-bar';
        var fill = document.createElement('div');
        fill.className = 'file-deposit-progress-fill';
        bar.appendChild(fill);
        var meta = document.createElement('div');
        meta.className = 'file-deposit-queue__meta';
        var size = document.createElement('span');
        size.textContent = (file.size / 1048576).toFixed(file.size > 10485760 ? 0 : 1) + ' <?php echo esc_js(__('MB', 'agency-client-portal')); ?>';
        var pctEl = document.createElement('span');
        pctEl.className = 'file-deposit-queue__pct';
        pctEl.textContent = '0 %';
        meta.appendChild(size);
        meta.appendChild(bar);
        meta.appendChild(pctEl);
        info.appendChild(name);
        info.appendChild(meta);
        row.appendChild(fileTile(file.name));
        row.appendChild(info);
        if (queue) queue.appendChild(row);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', agencyData.rest_url + 'assets/deposit-upload');
        xhr.setRequestHeader('X-WP-Nonce', agencyData.nonce);

        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                var pct = Math.round((e.loaded / e.total) * 100);
                fill.style.width = pct + '%';
                pctEl.textContent = pct + ' %';
            }
        };
        xhr.onload = function() {
            try {
                var data = JSON.parse(xhr.responseText);
                if (data.success) {
                    row.classList.add('is-done');
                    meta.innerHTML = '';
                    var done = document.createElement('span');
                    done.className = 'file-deposit-queue__done';
                    done.textContent = '<?php echo esc_js(__('Done', 'agency-client-portal')); ?>';
                    meta.appendChild(size);
                    meta.appendChild(done);
                    setTimeout(function() { window.location.reload(); }, 700);
                } else {
                    row.remove();
                    showError(data.message || '<?php echo esc_js(__('Error during upload', 'agency-client-portal')); ?>');
                }
            } catch(e) {
                row.remove();
                showError('<?php echo esc_js(__('Server error', 'agency-client-portal')); ?>');
            }
        };
        xhr.onerror = function() {
            row.remove();
            showError('<?php echo esc_js(__('Network error', 'agency-client-portal')); ?>');
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
