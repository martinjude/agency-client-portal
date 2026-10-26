<?php if (!defined('ABSPATH')) { exit; } ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <?php wp_head(); ?>
</head>

<?php
// Pré-définition des variables nécessaires avant que le template principal les définisse
$_video_block_id   = absint(get_query_var('agency_block'));
$_video_settings   = array();
if ($_video_block_id) {
    $_video_block_obj = Agency_Block::get_by_id($_video_block_id);
    if ($_video_block_obj) {
        $_video_settings = Agency_Block::get_settings($_video_block_obj);
    }
}
?>

<?php ob_start(); ?>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof AgencyModal === 'undefined') {
        console.error('AgencyModal not loaded');
        return;
    }

    // Modals initialization
    const uploadModal = new AgencyModal('upload-video-modal');
    const embedModal = new AgencyModal('embed-video-modal');
    const linkModal = new AgencyModal('new-external-link-modal');
    const editLinkModal = new AgencyModal('edit-external-link-modal');

    // Modal cancel buttons
    document.querySelectorAll('.modal-cancel, .agency-modal-close').forEach(btn => {
        btn.addEventListener('click', function() {
            uploadModal.close();
            embedModal.close();
            linkModal.close();
            editLinkModal.close();
        });
    });

    // Upload video
    const uploadBtn = document.getElementById('upload-video-btn');
    const uploadSubmitBtn = document.getElementById('upload-video-submit-btn');
    const videoFileInput = document.getElementById('video-file');

    if (uploadBtn) {
        uploadBtn.addEventListener('click', () => uploadModal.open());
    }

    // Pre-check taille fichier à la sélection
    if (videoFileInput) {
        videoFileInput.addEventListener('change', function() {
            const maxBytes = 512 * 1024 * 1024; // 512 MB
            const file = this.files[0];
            if (file && file.size > maxBytes) {
                // Afficher un avertissement mais ne pas bloquer - le serveur donnera la réponse finale
                const sizeMB = Math.round(file.size / 1024 / 1024);
                alert('<?php echo esc_js(/* translators: %d: file size in MB */ __('Warning: this file is %d MB. The recommended limit is 512 MB. If the upload fails, contact your administrator.', 'agency-client-portal')); ?>'.replace('%d', sizeMB));
            }
        });
    }

    if (uploadSubmitBtn && videoFileInput) {
        uploadSubmitBtn.addEventListener('click', function() {
            if (!videoFileInput.files || !videoFileInput.files[0]) {
                alert(<?php echo wp_json_encode(__('Please select a file', 'agency-client-portal')); ?>);
                return;
            }

            const fd = new FormData();
            fd.append('block_id', <?php echo esc_js($_video_block_id); ?>);
            fd.append('file', videoFileInput.files[0]);

            uploadSubmitBtn.disabled = true;
            uploadSubmitBtn.textContent = <?php echo wp_json_encode(__('Importing...', 'agency-client-portal')); ?>;

            const overlay = document.getElementById('upload-progress-overlay');
            if (overlay) overlay.style.display = 'flex';

            fetch('<?php echo esc_url(rest_url('agency/v1/assets/upload')); ?>', {
                method: 'POST',
                headers: { 'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>' },
                body: fd
            })
            .then(res => {
                // Vérifier les codes d'erreur HTTP
                if (res.status === 413) {
                    if (overlay) overlay.style.display = 'none';
                    uploadSubmitBtn.disabled = false;
                    uploadSubmitBtn.textContent = <?php echo wp_json_encode(__('Import', 'agency-client-portal')); ?>;
                    alert(<?php echo wp_json_encode(__('The file exceeds the 512 MB limit. Please contact the administrator if needed.', 'agency-client-portal')); ?>);
                    return;
                } else if (res.status === 0 || res.status === 408) {
                    if (overlay) overlay.style.display = 'none';
                    uploadSubmitBtn.disabled = false;
                    uploadSubmitBtn.textContent = <?php echo wp_json_encode(__('Import', 'agency-client-portal')); ?>;
                    alert(<?php echo wp_json_encode(__('The upload timed out. The file may be too large. Try a smaller file.', 'agency-client-portal')); ?>);
                    return;
                }
                return res.json();
            })
            .then(data => {
                if (!data) return; // Si on a return plus tôt à cause d'une erreur HTTP
                if (overlay) overlay.style.display = 'none';
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || <?php echo wp_json_encode(__('Error during upload', 'agency-client-portal')); ?>);
                    uploadSubmitBtn.disabled = false;
                    uploadSubmitBtn.textContent = <?php echo wp_json_encode(__('Import', 'agency-client-portal')); ?>;
                }
            })
            .catch(err => {
                if (overlay) overlay.style.display = 'none';
                console.error(err);
                uploadSubmitBtn.disabled = false;
                uploadSubmitBtn.textContent = <?php echo wp_json_encode(__('Import', 'agency-client-portal')); ?>;
                // Message d'erreur amélioré pour les erreurs de connexion/timeout
                var errorMsg = <?php echo wp_json_encode(__('Error during upload', 'agency-client-portal')); ?>;
                if (err.name === 'TypeError' && err.message.includes('fetch')) {
                    errorMsg = <?php echo wp_json_encode(__('The upload failed. The file may be too large. Try a smaller file.', 'agency-client-portal')); ?>;
                }
                alert(errorMsg);
            });
        });
    }

    // Embed video
    const embedBtn = document.getElementById('embed-video-btn');
    const embedSubmitBtn = document.getElementById('embed-video-submit-btn');
    const embedUrlInput = document.getElementById('embed-video-url');
    const embedTitleInput = document.getElementById('embed-video-title');

    if (embedBtn) {
        embedBtn.addEventListener('click', () => embedModal.open());
    }

    if (embedSubmitBtn && embedUrlInput) {
        embedSubmitBtn.addEventListener('click', function() {
            const url = embedUrlInput.value.trim();
            const title = embedTitleInput.value.trim();

            if (!url) {
                alert(<?php echo wp_json_encode(__('Please enter a URL', 'agency-client-portal')); ?>);
                return;
            }

            embedSubmitBtn.disabled = true;
            embedSubmitBtn.textContent = <?php echo wp_json_encode(__('Embedding...', 'agency-client-portal')); ?>;

            // Get current settings
            const currentSettings = <?php echo json_encode($_video_settings); ?>;
            const embedUrls = currentSettings.embed_urls || [];
            embedUrls.push({ url: url, title: title });

            fetch('<?php echo esc_url(rest_url('agency/v1/blocks/' . $_video_block_id)); ?>', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>'
                },
                body: JSON.stringify({
                    settings: { embed_urls: embedUrls }
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(<?php echo wp_json_encode(__('Error while embedding', 'agency-client-portal')); ?>);
                    embedSubmitBtn.disabled = false;
                    embedSubmitBtn.textContent = <?php echo wp_json_encode(__('Embed', 'agency-client-portal')); ?>;
                }
            })
            .catch(err => {
                console.error(err);
                embedSubmitBtn.disabled = false;
                embedSubmitBtn.textContent = <?php echo wp_json_encode(__('Embed', 'agency-client-portal')); ?>;
                alert(<?php echo wp_json_encode(__('Error while embedding', 'agency-client-portal')); ?>);
            });
        });
    }

    // Delete embed
    document.querySelectorAll('.delete-embed').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!confirm(<?php echo wp_json_encode(__('Are you sure you want to delete this embedded video?', 'agency-client-portal')); ?>)) {
                return;
            }

            const embedIndex = parseInt(this.getAttribute('data-embed-index'));
            const currentSettings = <?php echo json_encode($_video_settings); ?>;
            const embedUrls = currentSettings.embed_urls || [];
            embedUrls.splice(embedIndex, 1);

            fetch('<?php echo esc_url(rest_url('agency/v1/blocks/' . $_video_block_id)); ?>', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>'
                },
                body: JSON.stringify({
                    settings: { embed_urls: embedUrls }
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Supprimer l'élément embed du DOM
                    const embedItem = btn.closest('.video-embed-item');
                    if (embedItem) {
                        embedItem.remove();
                    }
                    
                    // Masquer le ghost de vidéo uploadée si vide
                    const uploadedSection = document.querySelector('.agency-video-player-wrapper');
                    if (uploadedSection && !uploadedSection.querySelector('video, source[src]')) {
                        uploadedSection.style.display = 'none';
                    }
                    
                    // Afficher le formulaire d'ajout si plus aucune vidéo
                    const remainingEmbeds = document.querySelectorAll('.video-embed-item');
                    const hasUploadedVideo = document.querySelector('.agency-video-player-wrapper video');
                    if (remainingEmbeds.length === 0 && !hasUploadedVideo) {
                        const addForms = document.querySelectorAll('#video-add-form, .video-embed-form');
                        addForms.forEach(form => form.style.display = 'block');
                    }
                } else {
                    alert(<?php echo wp_json_encode(__('Error while deleting', 'agency-client-portal')); ?>);
                }
            });
        });
    });

    // External links - Create
    const addLinkBtn = document.getElementById('add-external-link-btn');
    const createLinkBtn = document.getElementById('create-external-link-btn');

    if (addLinkBtn) {
        addLinkBtn.addEventListener('click', () => linkModal.open());
    }

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
                    block_id: <?php echo esc_js($_video_block_id); ?>,
                    emoji: emoji,
                    text: text,
                    url: url
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(<?php echo wp_json_encode(__('Error while creating the link', 'agency-client-portal')); ?>);
                    createLinkBtn.disabled = false;
                    createLinkBtn.textContent = <?php echo wp_json_encode(__('Add link', 'agency-client-portal')); ?>;
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

    // External links - Edit
    document.querySelectorAll('.edit-external-link').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const linkId = this.getAttribute('data-link-id');
            const linkEmoji = this.getAttribute('data-link-emoji');
            const linkText = this.getAttribute('data-link-text');
            const linkUrl = this.getAttribute('data-link-url');

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

    // External links - Update
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
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(<?php echo wp_json_encode(__('Error while updating the link', 'agency-client-portal')); ?>);
                    updateLinkBtn.disabled = false;
                    updateLinkBtn.textContent = <?php echo wp_json_encode(__('Update', 'agency-client-portal')); ?>;
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

    // External links - Delete
    document.querySelectorAll('.delete-external-link').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            if (!confirm(<?php echo wp_json_encode(__('Are you sure you want to delete this link?', 'agency-client-portal')); ?>)) {
                return;
            }

            const linkId = this.getAttribute('data-link-id');

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

    // Save notes
    document.querySelectorAll('.save-notes-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const assetId = this.getAttribute('data-asset-id');
            const textarea = document.getElementById('notes-' + assetId);
            const statusSpan = document.querySelector('.notes-save-status[data-asset-id="' + assetId + '"]');
            const content = textarea.value;

            btn.disabled = true;
            btn.textContent = <?php echo wp_json_encode(__('Saving...', 'agency-client-portal')); ?>;

            fetch('<?php echo esc_url(rest_url('agency/v1/assets/')); ?>' + assetId, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>'
                },
                body: JSON.stringify({ voiceover: content })
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.textContent = <?php echo wp_json_encode(__('Save notes', 'agency-client-portal')); ?>;

                if (data.success) {
                    statusSpan.textContent = '✓ ' + <?php echo wp_json_encode(__('Saved', 'agency-client-portal')); ?>;
                    statusSpan.style.color = 'green';
                } else {
                    statusSpan.textContent = '✗ ' + <?php echo wp_json_encode(__('Error', 'agency-client-portal')); ?>;
                    statusSpan.style.color = 'red';
                }

                setTimeout(() => { statusSpan.textContent = ''; }, 3000);
            })
            .catch(err => {
                console.error(err);
                btn.disabled = false;
                btn.textContent = <?php echo wp_json_encode(__('Save notes', 'agency-client-portal')); ?>;
                alert(<?php echo wp_json_encode(__('Error while saving', 'agency-client-portal')); ?>);
            });
        });
    });

    // Delete asset
    document.querySelectorAll('.delete-asset').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!confirm(<?php echo wp_json_encode(__('Are you sure you want to delete this video?', 'agency-client-portal')); ?>)) {
                return;
            }

            const assetId = this.getAttribute('data-asset-id');

            fetch('<?php echo esc_url(rest_url('agency/v1/assets/')); ?>' + assetId, {
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
});
<?php wp_print_inline_script_tag(ob_get_clean()); ?>

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

    // Description auto-save (video)
    var videoDesc = document.getElementById('video-description');
    if (videoDesc && typeof agencyData !== 'undefined') {
        var videoDescTimer = null;
        videoDesc.addEventListener('input', function() {
            clearTimeout(videoDescTimer);
            videoDescTimer = setTimeout(function() {
                fetch(agencyData.rest_url + 'blocks/' + videoDesc.getAttribute('data-block-id'), {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': agencyData.nonce },
                    body: JSON.stringify({ content: videoDesc.innerHTML })
                });
            }, 1200);
        });
    }
});

// Fix Dropbox video persistence on back/forward navigation
window.addEventListener('pageshow', function(event) {
    if (event.persisted) {
        document.querySelectorAll('video').forEach(function(video) {
            video.querySelectorAll('source').forEach(function(source) {
                // Re-assign src to break the cache
                source.src = source.src;
            });
            video.load();
        });
    }
});

<?php wp_print_inline_script_tag(ob_get_clean()); ?>

<body <?php body_class(); ?>>

<?php
$client_slug = get_query_var('agency_client');
$project_slug = get_query_var('agency_project');
$block_id = get_query_var('agency_block');

$client = Agency_Client::get_by_slug($client_slug);
$project = $client ? Agency_Project::get_by_slug($client->id, $project_slug) : null;
$block = Agency_Block::get_by_id($block_id);

if (!$client || !$project || !$block || $block->type !== 'video') {
    wp_die(esc_html__('Content not found', 'agency-client-portal'), 404);
}

$assets = Agency_Asset::get_by_block($block_id);
$is_manager = current_user_can('manage_agency_projects');
$external_links = Agency_External_Link::get_by_block($block_id);

// Récupérer les URLs embeddées depuis les settings
$settings = Agency_Block::get_settings($block);
$embed_urls = isset($settings['embed_urls']) ? $settings['embed_urls'] : array();
$allow_media_download = Agency_Block::is_media_download_allowed($block);
$can_download_media = $is_manager || $allow_media_download;
$token_authenticated = Agency_Security::can_access_client_by_token($client->id);
$preview_only_for_token_client = !$is_manager && $token_authenticated && !$allow_media_download;

// Collect embed asset IDs so uploaded-asset section can exclude them
$embed_asset_ids = array();
?>

<div class="agency-wrapper">

    <?php include AGENCY_PLUGIN_DIR . 'templates/partials/header.php'; ?>

    <!-- Sidebar manager -->
    <?php include AGENCY_PLUGIN_DIR . 'templates/partials/sidebar.php'; ?>

    <main class="agency-main">

        <div class="agency-container">
            <div class="agency-block-view agency-block-video" data-block-id="<?php echo esc_attr($block->id); ?>">
                <?php include AGENCY_PLUGIN_DIR . 'templates/partials/block-head.php'; ?>

                <div class="block-header-full agency-bv-bar">
<?php
                    $_bv_description = $block->content ?? '';
                    ?>
                    <?php if ($is_manager || $_bv_description): ?>
                    <div class="block-description video-description"
                         data-placeholder="<?php esc_attr_e('Add a description…', 'agency-client-portal'); ?>"
                         <?php if ($is_manager): ?>
                         contenteditable="true"
                         id="video-description"
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
                            <button class="agency-btn agency-btn-primary" id="upload-video-btn" type="button">
                                <?php Agency_Icons::render('upload', 16); ?> <?php esc_html_e('Import a video', 'agency-client-portal'); ?>
                            </button>
                            <button class="agency-btn agency-btn-secondary" id="embed-video-btn" type="button">
                                <?php Agency_Icons::render('link', 16); ?> <?php esc_html_e('Embed a video', 'agency-client-portal'); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Vidéos intégrées (Embeds) -->
                <?php if (!empty($embed_urls)): ?>
                    <div class="video-embeds">
                        <h3 class="agency-bp-section-title"><?php esc_html_e('Embedded videos', 'agency-client-portal'); ?> <span class="agency-count"><?php echo intval(count($embed_urls)); ?></span></h3>
                        <div class="video-embed-grid">
                            <?php foreach ($embed_urls as $index => $embed): ?>
                                <?php
                                // Créer ou récupérer l'asset pour cet embed
                                $embed_asset = Agency_Asset::get_or_create_embed_asset(
                                    $block_id,
                                    $embed['url'],
                                    isset($embed['title']) ? $embed['title'] : ''
                                );
                                $embed_asset_ids[] = $embed_asset->id;
                                $embed_url  = $embed['url'];
                                $embed_html = '';
                                $embed_provider = '';

                                if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $embed_url, $matches)) {
                                    $embed_provider = 'YouTube';
                                    $embed_html = '<iframe src="https://www.youtube.com/embed/' . esc_attr($matches[1]) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
                                } elseif (preg_match('/vimeo\.com\/([0-9]+)/', $embed_url, $matches)) {
                                    $embed_provider = 'Vimeo';
                                    $embed_html = '<iframe src="https://player.vimeo.com/video/' . esc_attr($matches[1]) . '" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>';
                                } elseif (preg_match('/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/', $embed_url, $matches)) {
                                    $embed_provider = 'Google Drive';
                                    $embed_html = '<iframe src="https://drive.google.com/file/d/' . esc_attr($matches[1]) . '/preview" frameborder="0" allow="autoplay"></iframe>';
                                } elseif (strpos($embed_url, 'dropbox.com') !== false) {
                                    $embed_provider = 'Dropbox';
                                    $embed_url_modified = str_replace('www.dropbox.com', 'dl.dropboxusercontent.com', $embed_url);
                                    $embed_html = '<video controls><source src="' . esc_url($embed_url_modified) . '" type="video/mp4"></video>';
                                } else {
                                    $embed_html = '<p class="embed-error">' . Agency_Icons::get('alert', 15) . ' ' . esc_html__('URL not recognized. Use YouTube, Vimeo, Google Drive or Dropbox.', 'agency-client-portal') . '</p>';
                                }

                                $vi_player = '<div class="video-embed-wrapper">' . wp_kses($embed_html, array(
                                    'iframe' => array('src' => true, 'frameborder' => true, 'allow' => true, 'allowfullscreen' => true),
                                    'video'  => array('controls' => true),
                                    'source' => array('src' => true, 'type' => true),
                                    'p'      => array('class' => true),
                                ) + Agency_Icons::allowed_html()) . '</div>';

                                $vi_asset   = $embed_asset;
                                $vi_kind    = 'embed';
                                $vi_title   = !empty($embed['title']) ? $embed['title'] : sprintf(/* translators: %d: position of the video */ __('Video %d', 'agency-client-portal'), $index + 1);
                                $vi_tags    = array_filter(array(__('Video', 'agency-client-portal'), $embed_provider));
                                $vi_attrs   = 'data-embed-index="' . esc_attr($index) . '"';
                                $vi_actions = '';
                                if ($is_manager) {
                                    $vi_actions = '<button type="button" class="agency-btn-icon delete-embed" data-embed-index="' . esc_attr($index) . '" title="' . esc_attr__('Delete', 'agency-client-portal') . '" aria-label="' . esc_attr__('Delete', 'agency-client-portal') . '">' . Agency_Icons::get('trash', 16) . '</button>';
                                }
                                include AGENCY_PLUGIN_DIR . 'templates/partials/video-item.php';
                                ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Vidéos uploadées -->
                <?php
                $upload_assets = empty($embed_asset_ids)
                    ? $assets
                    : array_values(array_filter($assets, function($a) use ($embed_asset_ids) {
                        return !in_array($a->id, $embed_asset_ids);
                    }));
                ?>
                <?php if (!empty($upload_assets)): ?>
                    <div class="video-uploads">
                        <h3 class="agency-bp-section-title"><?php esc_html_e('Imported videos', 'agency-client-portal'); ?> <span class="agency-count"><?php echo intval(count($upload_assets)); ?></span></h3>
                        <div class="video-upload-grid">
                            <?php foreach ($upload_assets as $asset): ?>
                                <?php
                                $asset_view_url = $preview_only_for_token_client ? Agency_Asset::get_preview_url($asset->id) : Agency_Asset::get_url($asset->id);

                                ob_start();
                                ?>
                                <div class="video-player-wrapper">
                                    <?php if ($asset->file_type === 'video'): ?>
                                        <video controls class="video-player" <?php if ($preview_only_for_token_client): ?>controlsList="nodownload noplaybackrate" disablepictureinpicture oncontextmenu="return false;"<?php endif; ?>>
                                            <source src="<?php echo esc_url($asset_view_url); ?>" type="video/mp4">
                                            <?php esc_html_e('Your browser does not support video playback.', 'agency-client-portal'); ?>
                                        </video>
                                    <?php else: ?>
                                        <div class="video-file-placeholder">
                                            <span class="file-icon"><?php Agency_Icons::render('film', 16); ?></span>
                                            <span class="file-name"><?php echo esc_html($asset->original_filename); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php
                                $vi_player = ob_get_clean();

                                $vi_asset   = $asset;
                                $vi_kind    = 'upload';
                                $vi_title   = pathinfo($asset->original_filename, PATHINFO_FILENAME);
                                $vi_ext     = strtoupper(pathinfo($asset->original_filename, PATHINFO_EXTENSION));
                                $vi_tags    = array_filter(array(__('Video', 'agency-client-portal'), $vi_ext, $asset->file_size ? size_format($asset->file_size) : ''));
                                $vi_attrs   = '';
                                $vi_actions = '';
                                if ($can_download_media) {
                                    $vi_actions .= '<a href="' . esc_url(Agency_Asset::get_download_url($asset->id)) . '" class="agency-btn-icon" target="_blank" title="' . esc_attr__('Download', 'agency-client-portal') . '" aria-label="' . esc_attr__('Download', 'agency-client-portal') . '">' . Agency_Icons::get('download', 16) . '</a>';
                                }
                                if ($is_manager) {
                                    $vi_actions .= '<button type="button" class="agency-btn-icon delete-asset" data-asset-id="' . esc_attr($asset->id) . '" title="' . esc_attr__('Delete', 'agency-client-portal') . '" aria-label="' . esc_attr__('Delete', 'agency-client-portal') . '">' . Agency_Icons::get('trash', 16) . '</button>';
                                }
                                include AGENCY_PLUGIN_DIR . 'templates/partials/video-item.php';
                                ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </main>

    <?php include AGENCY_PLUGIN_DIR . 'templates/partials/footer.php'; ?>
</div>

<?php if ($is_manager): ?>
<!-- Modal d'upload de vidéo -->
<div class="agency-modal" id="upload-video-modal">
    <div class="agency-modal-content">
        <div class="agency-modal-header">
            <h3 class="agency-modal-title"><?php esc_html_e('Import a video', 'agency-client-portal'); ?></h3>
            <button class="agency-modal-close" type="button">&times;</button>
        </div>
        <div class="agency-modal-body">
            <div class="agency-form-group">
                <label class="agency-form-label" for="video-file"><?php esc_html_e('Video file (MP4, MOV, AVI)', 'agency-client-portal'); ?></label>
                <input type="file" id="video-file" class="agency-form-input" accept="video/*" required>
            </div>
        </div>
        <div class="agency-modal-footer">
            <button class="agency-btn agency-btn-secondary modal-cancel" type="button">
                <?php esc_html_e('Cancel', 'agency-client-portal'); ?>
            </button>
            <button class="agency-btn agency-btn-primary" id="upload-video-submit-btn" type="button">
                <?php esc_html_e('Import', 'agency-client-portal'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Modal d'intégration vidéo -->
<div class="agency-modal" id="embed-video-modal">
    <div class="agency-modal-content">
        <div class="agency-modal-header">
            <h3 class="agency-modal-title"><?php esc_html_e('Embed a video', 'agency-client-portal'); ?></h3>
            <button class="agency-modal-close" type="button">&times;</button>
        </div>
        <div class="agency-modal-body">
            <div class="agency-form-group">
                <label class="agency-form-label" for="embed-video-url">
                    <?php esc_html_e('Video URL', 'agency-client-portal'); ?>
                </label>
                <input type="url"
                       id="embed-video-url"
                       class="agency-form-input"
                       placeholder="<?php esc_attr_e('YouTube, Vimeo, Google Drive or Dropbox', 'agency-client-portal'); ?>"
                       required>
                <small class="agency-form-help">
                    <?php esc_html_e('Supported formats: YouTube, Vimeo, Google Drive, Dropbox', 'agency-client-portal'); ?>
                </small>
            </div>
            <div class="agency-form-group">
                <label class="agency-form-label" for="embed-video-title">
                    <?php esc_html_e('Title (optional)', 'agency-client-portal'); ?>
                </label>
                <input type="text"
                       id="embed-video-title"
                       class="agency-form-input"
                       placeholder="<?php esc_attr_e('e.g. Final presentation', 'agency-client-portal'); ?>">
            </div>
        </div>
        <div class="agency-modal-footer">
            <button class="agency-btn agency-btn-secondary modal-cancel" type="button">
                <?php esc_html_e('Cancel', 'agency-client-portal'); ?>
            </button>
            <button class="agency-btn agency-btn-primary" id="embed-video-submit-btn" type="button">
                <?php esc_html_e('Embed', 'agency-client-portal'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Modal externe liens -->
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
                       placeholder="<?php esc_attr_e('e.g. Presentation', 'agency-client-portal'); ?>"
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

<!-- Loading overlay pour import vidéo -->
<div class="agency-modal" id="upload-progress-overlay" style="display:none;">
    <div class="agency-modal-content agency-upload-modal">
        <h3><?php esc_html_e('Importing...', 'agency-client-portal'); ?></h3>
        <p style="color:rgba(255,255,255,0.75);margin:4px 0 0;"><?php esc_html_e('Please wait.', 'agency-client-portal'); ?></p>
    </div>
</div>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
