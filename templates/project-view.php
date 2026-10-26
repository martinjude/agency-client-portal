<?php
if (!defined('ABSPATH')) { exit; }

$client_slug = get_query_var('agency_client');
$project_slug = get_query_var('agency_project');

$client = Agency_Client::get_by_slug($client_slug);
if (!$client) {
    wp_die(esc_html__('Client not found', 'agency-client-portal'), 404);
}

$project = Agency_Project::get_by_slug($client->id, $project_slug);
if (!$project) {
    wp_die(esc_html__('Project not found', 'agency-client-portal'), 404);
}

$blocks = Agency_Block::get_by_project($project->id);
$is_manager = current_user_can('manage_agency_projects');
$token_authenticated = Agency_Security::can_access_client_by_token($client->id);
$external_links = Agency_External_Link::get_by_project($project->id);
$create_block_nonce = wp_create_nonce('agency_create_block_' . $project->id);

// Création d'un bloc via AJAX (non-natif)
if ($is_manager && isset($_POST['ajax_create_block']) && isset($_POST['block_type']) && isset($_POST['block_title']) && !empty($_POST['block_type'])) {
    $posted_nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($posted_nonce, 'agency_create_block_' . $project->id)) {
        status_header(403);
        exit;
    }

    $raw_type = isset($_POST['block_type']) ? sanitize_key(wp_unslash($_POST['block_type'])) : '';
    $default_title = isset($_POST['block_title']) ? sanitize_text_field(wp_unslash($_POST['block_title'])) : '';
    if ($default_title === '' && $raw_type === 'separator') {
        $default_title = '—';
    }
    if ($default_title === '') {
        exit;
    }
    // Default content from settings
    $type = sanitize_text_field($raw_type);
    $default_content_keys = array('script', 'video', 'versioned_media', 'storyboard');
    $default_content = in_array($type, $default_content_keys) ? get_option('agency_block_default_text_' . $type, '') : '';
    $block_data = array(
        'project_id' => $project->id,
        'type' => $type,
        'title' => $default_title,
        'content' => $default_content,
        'grid_width' => ($type === 'separator') ? 12 : 3,
        'status' => 'active',
        'created_at' => current_time('mysql'),
    );
    Agency_Block::create($block_data);
    exit;
}

$project_emoji = !empty($project->emoji) ? $project->emoji : '📋';
// Full-size pour la couverture pleine page, thumbnail utilisé dans les cartes (client-view)
$project_cover_url = Agency_Project::get_cover_full_url($project);


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


    <!-- Project cover image (behind header) -->
    <?php if ($project_cover_url): ?>
    <div class="agency-project-cover" style="--cover-url: url('<?php echo esc_url($project_cover_url); ?>');"></div>
    <?php endif; ?>

    <!-- Header -->
    <?php include AGENCY_PLUGIN_DIR . 'templates/partials/header.php'; ?>

    <!-- Sidebar manager -->
    <?php $sidebar_context = 'project'; include AGENCY_PLUGIN_DIR . 'templates/partials/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="agency-main">
        
        <div class="agency-container">
            <div class="agency-project-view">
                <div class="project-header">
                    <h1 class="agency-title"><?php
                        // FIX: Output emoji directly without escaping, but escape the project name
                        echo esc_html($project_emoji) . ' ' . esc_html($project->name);
                    ?></h1>
                    <?php if ($is_manager): ?>
                    <div class="project-cover-actions">
                        <button class="agency-btn agency-btn-sm agency-btn-secondary" id="set-project-cover-btn" type="button">
                            <?php Agency_Icons::render('image', 16); ?> <?php echo esc_html($project_cover_url ? __('Change cover', 'agency-client-portal') : __('Add a cover', 'agency-client-portal')); ?>
                        </button>
						<?php if ($project_cover_url): ?>
                        <button class="agency-btn agency-btn-sm agency-btn-danger" id="remove-project-cover-btn" type="button">
                            <?php Agency_Icons::render('x', 16); ?> <?php esc_html_e('Remove cover', 'agency-client-portal'); ?>
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php
                    $subtitle_text = !empty($project->subtitle)
                        ? esc_html($project->subtitle)
                        : esc_html__('Find all your project deliverables here', 'agency-client-portal');
                    ?>
                    <?php if ($is_manager): ?>
                    <h4 class="agency-subtitle agency-subtitle-editable"
                        contenteditable="true"
                        data-project-id="<?php echo intval($project->id); ?>"
                        data-original="<?php echo esc_attr(!empty($project->subtitle) ? $project->subtitle : __('Find all your project deliverables here', 'agency-client-portal')); ?>"
                        title="<?php esc_attr_e('Click to edit', 'agency-client-portal'); ?>"><?php echo esc_html($subtitle_text); ?></h4>
                    <?php else: ?>
                    <h4 class="agency-subtitle"><?php echo esc_html($subtitle_text); ?></h4>
                    <?php endif; ?>
                    
                    <?php if ($is_manager): ?>
                    <div class="agency-actions">
                            <button class="agency-btn agency-btn-primary" id="add-block-btn" type="button">
                                <?php esc_html_e('+ Add a block', 'agency-client-portal'); ?>
                            </button>
                            <button class="agency-btn agency-btn-secondary"
                                    id="add-progress-bar-btn" type="button"
                                    data-project-id="<?php echo intval($project->id); ?>">
                                <?php Agency_Icons::render('bar-chart', 16); ?> <?php esc_html_e('Add a progress bar', 'agency-client-portal'); ?>
                            </button>
                            <button class="agency-btn agency-btn-secondary" id="add-separator-btn" type="button">
                                <?php Agency_Icons::render('minus', 16); ?> <?php esc_html_e('Add a separator', 'agency-client-portal'); ?>
                            </button>
                            <button class="agency-btn agency-btn-secondary"
                                    id="save-preset-btn" type="button"
                                    data-project-id="<?php echo intval($project->id); ?>">
                                <?php Agency_Icons::render('save', 16); ?> <?php esc_html_e('Save a layout', 'agency-client-portal'); ?>
                            </button>
                            <button class="agency-btn agency-btn-secondary"
                                    id="import-preset-btn" type="button"
                                    data-project-id="<?php echo intval($project->id); ?>">
                                <?php Agency_Icons::render('download', 16); ?> <?php esc_html_e('Import a layout', 'agency-client-portal'); ?>
                            </button>
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
                            <button class="agency-btn agency-btn-secondary" id="add-external-link-btn" type="button">
                                <?php Agency_Icons::render('link', 16); ?> <?php esc_html_e('Add a link', 'agency-client-portal'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($is_manager): ?>
                        
                        <!-- Modal de création de bloc -->
                        <div class="agency-modal" id="new-block-modal">
                            <div class="agency-modal-content">
                                <div class="agency-modal-header">
                                    <h3 class="agency-modal-title"><?php esc_html_e('New Block', 'agency-client-portal'); ?></h3>
                                    <button class="agency-modal-close" type="button">&times;</button>
                                </div>
                                <div class="agency-modal-body">
                                    <div class="agency-form-group">
                                        <label class="agency-form-label" for="new-block-type">
                                            <?php esc_html_e('Block type', 'agency-client-portal'); ?>
                                        </label>
                                        <select id="new-block-type" class="agency-form-input">
                                            <option value=""><?php esc_html_e('-- Choose a type --', 'agency-client-portal'); ?></option>
                                            <?php 
                                            $block_types = Agency_Block::CLIENT_TYPES;
                                            foreach ($block_types as $type_key => $type_label): 
                                            ?>
                                                <option value="<?php echo esc_attr($type_key); ?>">
                                                    <?php echo esc_html($type_label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="agency-form-group">
                                        <label class="agency-form-label" for="new-block-title">
                                            <?php esc_html_e('Block title', 'agency-client-portal'); ?>
                                        </label>
                                        <input type="text" 
                                               id="new-block-title" 
                                               class="agency-form-input"
                                               placeholder="<?php esc_attr_e('e.g. Final script', 'agency-client-portal'); ?>" 
                                               required>
                                    </div>
                                </div>
                                <div class="agency-modal-footer">
                                    <button class="agency-btn agency-btn-secondary modal-cancel" type="button">
                                        <?php esc_html_e('Cancel', 'agency-client-portal'); ?>
                                    </button>
                                    <button class="agency-btn agency-btn-primary" id="create-block-btn" type="button">
                                        <?php esc_html_e('Create block', 'agency-client-portal'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Modal barre de progression -->
                        <div class="agency-modal" id="progress-bar-modal">
                            <div class="agency-modal-content">
                                <div class="agency-modal-header">
                                    <h3 class="agency-modal-title"><?php esc_html_e('Progress bar', 'agency-client-portal'); ?></h3>
                                    <button class="agency-modal-close" id="progress-bar-modal-close" type="button">&times;</button>
                                </div>
                                <div class="agency-modal-body">
                                    <p class="agency-form-help" class="agency-muted-note"><?php esc_html_e('Define the steps and their progress.', 'agency-client-portal'); ?></p>
                                    <div id="progress-bar-steps-list"></div>
                                    <button type="button" class="agency-btn agency-btn-sm agency-btn-secondary" id="progress-bar-add-step" class="agency-mt-8">
                                        + <?php esc_html_e('Add a step', 'agency-client-portal'); ?>
                                    </button>
                                </div>
                                <div class="agency-modal-footer">
                                    <button type="button" class="agency-btn agency-btn-secondary" id="progress-bar-cancel"><?php esc_html_e('Cancel', 'agency-client-portal'); ?></button>
                                    <button type="button" class="agency-btn agency-btn-primary" id="progress-bar-save"><?php esc_html_e('Save', 'agency-client-portal'); ?></button>
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

                        <!-- Modal d'édition de bloc -->
                        <div class="agency-modal" id="edit-block-modal">
                            <div class="agency-modal-content">
                                <div class="agency-modal-header">
                                    <h3 class="agency-modal-title"><?php esc_html_e('Edit Block', 'agency-client-portal'); ?></h3>
                                    <button class="agency-modal-close" type="button">&times;</button>
                                </div>
                                <div class="agency-modal-body">
                                    <input type="hidden" id="edit-block-id">
                                    <input type="hidden" id="edit-block-type">
                                    <div class="agency-form-group">
                                        <label class="agency-form-label" for="edit-block-title">
                                            <?php esc_html_e('Block title', 'agency-client-portal'); ?>
                                        </label>
                                        <input type="text"
                                               id="edit-block-title"
                                               class="agency-form-input"
                                               required>
                                    </div>
                                    <div class="agency-form-group">
                                        <label class="agency-form-label" for="edit-block-image-url">
                                            <?php esc_html_e('Background image (optional)', 'agency-client-portal'); ?>
                                        </label>
                                        <div class="agency-form-inline">
                                            <input type="url"
                                                   id="edit-block-image-url"
                                                   class="agency-form-input"
                                                   placeholder="<?php esc_attr_e('Image URL', 'agency-client-portal'); ?>">
                                            <button type="button" class="agency-btn agency-btn-secondary" id="edit-block-image-btn">
                                                <?php esc_html_e('Media Library', 'agency-client-portal'); ?>
                                            </button>
                                            <button type="button" class="agency-btn agency-btn-secondary" id="edit-block-project-assets-btn"
                                                    data-project-id="<?php echo intval($project->id); ?>">
                                                <?php esc_html_e('Project assets', 'agency-client-portal'); ?>
                                            </button>
                                        </div>
                                        <div class="agency-image-preview" id="edit-block-image-preview" style="display:none;"></div>
                                    </div>
                                    <div class="agency-form-group" id="edit-block-download-group" style="display:none;">
                                        <label class="agency-checkbox-label"
                                               for="edit-block-allow-media-download">
                                            <input type="checkbox" id="edit-block-allow-media-download" checked>
                                            <?php esc_html_e('Allow media download', 'agency-client-portal'); ?>
                                        </label>
                                    </div>
                                    <div class="agency-form-group">
                                        <label class="agency-checkbox-label"
                                               for="edit-block-client-comment">
                                            <input type="checkbox" id="edit-block-client-comment">
                                            <?php esc_html_e('Allow client comments', 'agency-client-portal'); ?>
                                        </label>
                                    </div>
                                    <div class="agency-form-group">
                                        <label class="agency-checkbox-label"
                                               for="edit-block-notify-on-comment">
                                            <input type="checkbox" id="edit-block-notify-on-comment" checked>
                                            <?php esc_html_e('Receive notifications', 'agency-client-portal'); ?>
                                        </label>
                                    </div>
                                </div>
                                <div class="agency-modal-footer">
                                    <button class="agency-btn agency-btn-secondary modal-cancel" type="button">
                                        <?php esc_html_e('Cancel', 'agency-client-portal'); ?>
                                    </button>
                                    <button class="agency-btn agency-btn-primary" id="update-block-btn" type="button">
                                        <?php esc_html_e('Update', 'agency-client-portal'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Mini-popin : sélection d'asset image du projet -->
                        <div class="agency-modal" id="project-assets-picker-modal">
                            <div class="agency-modal-content agency-modal-content--wide">
                                <div class="agency-modal-header">
                                    <h3 class="agency-modal-title"><?php esc_html_e('Choose an image from the project', 'agency-client-portal'); ?></h3>
                                    <button class="agency-modal-close" type="button" id="project-assets-picker-close">&times;</button>
                                </div>
                                <div class="agency-modal-body">
                                    <div id="project-assets-picker-grid" class="project-assets-picker-grid">
                                        <p class="project-assets-picker-loading"><?php esc_html_e('Loading…', 'agency-client-portal'); ?></p>
                                    </div>
                                    <p id="project-assets-picker-empty" style="display:none;"><?php esc_html_e('No images found in this project.', 'agency-client-portal'); ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Enregistrer une disposition (preset) -->
                        <div class="agency-modal" id="save-preset-modal">
                            <div class="agency-modal-content">
                                <div class="agency-modal-header">
                                    <h3 class="agency-modal-title"><?php esc_html_e('Save a layout', 'agency-client-portal'); ?></h3>
                                    <button class="agency-modal-close" type="button" id="save-preset-modal-close">&times;</button>
                                </div>
                                <div class="agency-modal-body">
                                    <div class="agency-form-group">
                                        <label class="agency-form-label" for="preset-name"><?php esc_html_e('Layout name', 'agency-client-portal'); ?></label>
                                        <input type="text" id="preset-name" class="agency-form-input" placeholder="<?php esc_attr_e('e.g. Standard layout', 'agency-client-portal'); ?>" required>
                                    </div>
                                    <div class="agency-form-group">
                                        <p class="agency-form-label"><?php esc_html_e('Include in the layout:', 'agency-client-portal'); ?></p>
                                        <label class="agency-checkbox-label"><input type="checkbox" id="preset-include-blocks" checked> <?php esc_html_e('Blocks (titles + types)', 'agency-client-portal'); ?></label>
                                        <label class="agency-checkbox-label"><input type="checkbox" id="preset-include-grid" checked> <?php esc_html_e('Grid layout (sizes)', 'agency-client-portal'); ?></label>
                                        <label class="agency-checkbox-label"><input type="checkbox" id="preset-include-descriptions"> <?php esc_html_e('Descriptions / content', 'agency-client-portal'); ?></label>
                                        <label class="agency-checkbox-label"><input type="checkbox" id="preset-include-links"> <?php esc_html_e('Block external links', 'agency-client-portal'); ?></label>
                                        <label class="agency-checkbox-label"><input type="checkbox" id="preset-include-separators" checked> <?php esc_html_e('Separators', 'agency-client-portal'); ?></label>
                                        <label class="agency-checkbox-label"><input type="checkbox" id="preset-include-card-thumbnails"> <?php esc_html_e('Card thumbnails', 'agency-client-portal'); ?></label>
                                        <label class="agency-checkbox-label"><input type="checkbox" id="preset-include-thumbnail"> <?php esc_html_e('Project thumbnail', 'agency-client-portal'); ?></label>
                                        <label class="agency-checkbox-label"><input type="checkbox" id="preset-include-settings" checked> <?php esc_html_e('Block settings (client comments, downloads)', 'agency-client-portal'); ?></label>
                                    </div>
                                </div>
                                <div class="agency-modal-footer">
                                    <button class="agency-btn agency-btn-secondary" type="button" id="save-preset-cancel"><?php esc_html_e('Cancel', 'agency-client-portal'); ?></button>
                                    <button class="agency-btn agency-btn-primary" type="button" id="save-preset-confirm"><?php Agency_Icons::render('save', 16); ?> <?php esc_html_e('Save', 'agency-client-portal'); ?></button>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Importer une disposition (preset) -->
                        <div class="agency-modal" id="import-preset-modal">
                            <div class="agency-modal-content agency-modal-content--wide">
                                <div class="agency-modal-header">
                                    <h3 class="agency-modal-title"><?php esc_html_e('Import a layout', 'agency-client-portal'); ?></h3>
                                    <button class="agency-modal-close" type="button" id="import-preset-modal-close">&times;</button>
                                </div>
                                <div class="agency-modal-body">
                                    <p class="agency-muted-note"><?php esc_html_e('Select a layout to import. New blocks will be created in this project.', 'agency-client-portal'); ?></p>
                                    <div id="presets-list" class="presets-list">
                                        <p class="presets-list-loading"><?php esc_html_e('Loading…', 'agency-client-portal'); ?></p>
                                    </div>
                                    <p id="presets-list-empty" style="display:none;"><?php esc_html_e('No saved layouts.', 'agency-client-portal'); ?></p>
                                </div>
                                <div class="agency-modal-footer">
                                    <button class="agency-btn agency-btn-secondary" type="button" id="import-preset-cancel"><?php esc_html_e('Close', 'agency-client-portal'); ?></button>
                                </div>
                            </div>
                        </div>

                        <?php ob_start(); ?>
                        document.addEventListener('DOMContentLoaded', function() {
                            var addBtn = document.getElementById('add-block-btn');
                            var createBtn = document.getElementById('create-block-btn');
                            var addLinkBtn = document.getElementById('add-external-link-btn');
                            var createLinkBtn = document.getElementById('create-external-link-btn');
                            
                            if (!addBtn || !createBtn) {
                                console.error('Block buttons not found');
                                return;
                            }
                            
                            if (typeof AgencyModal === 'undefined') {
                                console.error('AgencyModal not loaded');
                                return;
                            }
                            
                            var modal = new AgencyModal('new-block-modal');
                            var linkModal = addLinkBtn ? new AgencyModal('new-external-link-modal') : null;
                            var editLinkModal = new AgencyModal('edit-external-link-modal');
                            var editBlockModal = new AgencyModal('edit-block-modal');

                            // Bouton annuler
                            document.querySelectorAll('.modal-cancel').forEach(function(btn) {
                                btn.addEventListener('click', function() {
                                    modal.close();
                                    if (linkModal) linkModal.close();
                                    editLinkModal.close();
                                    editBlockModal.close();
                                });
                            });
                            
                            addBtn.addEventListener('click', function() {
                                modal.open();
                            });
                            
                            // External link modal
                            if (addLinkBtn && createLinkBtn && linkModal) {
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
                                            project_id: <?php echo intval($project->id); ?>,
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
                            }
                            
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
                            
                            createBtn.addEventListener('click', function() {
                                var typeSelect = document.getElementById('new-block-type');
                                var titleInput = document.getElementById('new-block-title');
                                var type = typeSelect ? typeSelect.value : '';
                                var title = titleInput ? titleInput.value.trim() : '';
                                
                                if (!type || !title) {
                                    alert(<?php echo wp_json_encode(__('Please fill in all fields', 'agency-client-portal')); ?>);
                                    return;
                                }
                                
                                createBtn.disabled = true;
                                createBtn.textContent = <?php echo wp_json_encode(__('Creating...', 'agency-client-portal')); ?>;
                                
                                var xhr = new XMLHttpRequest();
                                xhr.open('POST', window.location.href, true);
                                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                                xhr.onload = function() {
                                    if (xhr.status === 200) {
                                        window.location.reload();
                                    } else {
                                        createBtn.disabled = false;
                                        createBtn.textContent = <?php echo wp_json_encode(__('Create block', 'agency-client-portal')); ?>;
                                        alert(<?php echo wp_json_encode(__('Error while creating the block', 'agency-client-portal')); ?>);
                                    }
                                };
                                xhr.send(
                                    'ajax_create_block=1&block_type=' + encodeURIComponent(type) +
                                    '&block_title=' + encodeURIComponent(title) +
                                    '&nonce=' + encodeURIComponent(<?php echo wp_json_encode($create_block_nonce); ?>)
                                );
                            });
                        });
                        <?php wp_print_inline_script_tag(ob_get_clean()); ?>
                    <?php endif; ?>
</div>

                <?php if ($project->description): ?>
                    <div class="project-description">
                        <?php echo wp_kses_post($project->description); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($blocks)): ?>
                    <p class="agency-empty-state"><?php esc_html_e('No blocks for this project.', 'agency-client-portal'); ?></p>
                <?php else: ?>
                    <div class="agency-blocks-grid <?php echo $is_manager ? 'sortable' : ''; ?>" id="blocks-list">
                        <?php foreach ($blocks as $block): ?>
                            <?php
                            $comments_count = Agency_Comment::count_by_block($block->id);
                            $assets_count = Agency_Asset::count_by_block($block->id);
                            $assets = Agency_Asset::get_by_block($block->id);
                            
                            // Pour versioned_media, trier par version_number DESC pour avoir la dernière version en premier
                            if ($block->type === 'versioned_media' && !empty($assets)) {
                                usort($assets, function($a, $b) {
                                    $va = isset($a->version_number) ? intval($a->version_number) : 1;
                                    $vb = isset($b->version_number) ? intval($b->version_number) : 1;
                                    return $vb <=> $va;
                                });
                            }
                            
                            $first_asset = !empty($assets) ? $assets[0] : null;
                            $grid_width = isset($block->grid_width) ? intval($block->grid_width) : 3;
                            $grid_height = isset($block->grid_height) ? intval($block->grid_height) : 1;
                            $block_settings = Agency_Block::get_settings($block);
                            $allow_media_download = Agency_Block::is_media_download_allowed($block);
                            $preview_only_for_token_client = !$is_manager && $token_authenticated && !$allow_media_download;

                            // Déterminer l'URL de l'image de fond
                            $background_image_url = '';

                            // D'abord vérifier si une image de fond est définie dans les paramètres
                            if (!empty($block_settings['background_image_url'])) {
                                $background_image_url = $block_settings['background_image_url'];
                            }

                            // Sinon utiliser le premier média image comme fallback
                            if (empty($background_image_url) && $first_asset && $first_asset->file_type === 'image') {
                                $background_image_url = Agency_Asset::get_thumbnail_url($first_asset->id);
                            }

                            // URL de destination de la card (client seulement)
                            // Pour les blocs "lien", redirecter directement vers l'URL définie
                            $block_card_url = home_url("/client/{$client->slug}/project/{$project->slug}/block/{$block->id}");
                            if (!$is_manager && $block->type === 'link') {
                                if (!empty($block_settings['link_url'])) {
                                    $block_card_url = $block_settings['link_url'];
                                }
                            }
                            ?>

                            <?php if ($block->type === 'progress_bar'): ?>
                                <?php
                                $pb_steps = !empty($block_settings['steps']) && is_array($block_settings['steps']) ? $block_settings['steps'] : array();
                                ?>
                                <div class="agency-block agency-progress-bar-block"
                                     data-block-id="<?php echo esc_attr($block->id); ?>"
                                     data-block-type="progress_bar"
                                     data-steps="<?php echo esc_attr(wp_json_encode($pb_steps)); ?>"
                                     class="agency-grid-full-row">
                                    <div class="progress-bar-steps">
                                        <?php if (empty($pb_steps)): ?>
                                            <p class="progress-bar-empty"><?php esc_html_e('No steps defined', 'agency-client-portal'); ?></p>
                                        <?php else: ?>
                                            <?php foreach ($pb_steps as $pb_step): ?>
                                            <?php $pb_pct = intval($pb_step['percent'] ?? 0); ?>
                                            <div class="progress-bar-step">
                                                <div class="progress-bar-step-header">
                                                    <span class="progress-bar-step-label"><?php echo esc_html($pb_step['name'] ?? ''); ?></span>
                                                </div>
                                                <div class="progress-bar-track">
                                                    <div class="progress-bar-fill<?php echo esc_attr($pb_pct >= 100 ? ' is-complete' : ''); ?>" style="width: <?php echo esc_attr($pb_pct); ?>%;"></div>
                                                </div>
                                                <span class="progress-bar-percent"><?php echo esc_html($pb_pct); ?>%</span>
                                            </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($is_manager): ?>
                                    <div class="progress-bar-actions">
                                        <button class="agency-btn-icon agency-edit-progress-bar"
                                                data-block-id="<?php echo esc_attr($block->id); ?>"
                                                title="<?php esc_attr_e('Edit', 'agency-client-portal'); ?>"><?php Agency_Icons::render('edit', 16); ?></button>
                                        <button class="agency-btn-icon delete-block"
                                                data-block-id="<?php echo esc_attr($block->id); ?>"
                                                title="<?php esc_attr_e('Delete', 'agency-client-portal'); ?>"><?php Agency_Icons::render('trash', 16); ?></button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($block->type === 'separator'): ?>
                                <?php
                                $sep_settings = $block_settings;
                                $sep_label = !empty($sep_settings['separator_label']) ? $sep_settings['separator_label'] : esc_html($block->title);
                                ?>
                                <div class="agency-block agency-separator-block"
                                     data-block-id="<?php echo esc_attr($block->id); ?>"
                                     data-block-type="separator"
                                     class="agency-grid-full-row">
                                    <div class="separator-inner">
                                        <?php if ($is_manager): ?>
                                            <span class="separator-label"
                                                  contenteditable="true"
                                                  data-block-id="<?php echo esc_attr($block->id); ?>"><?php echo esc_html($sep_label); ?></span>
                                            <button class="agency-btn-icon delete-block" data-block-id="<?php echo esc_attr($block->id); ?>" title="<?php esc_attr_e('Delete', 'agency-client-portal'); ?>"><?php Agency_Icons::render('trash', 16); ?></button>
                                        <?php else: ?>
                                            <span class="separator-label"><?php echo esc_html($sep_label); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>

                            <div class="agency-block<?php echo $background_image_url ? ' has-block-bg' : ''; ?><?php echo !$is_manager ? ' clickable-card' : ''; ?>"
                                  data-block-id="<?php echo esc_attr($block->id); ?>"
                                  data-block-type="<?php echo esc_attr($block->type); ?>"
                                    data-allow-media-download="<?php echo $allow_media_download ? '1' : '0'; ?>"
                                    data-client-can-comment="<?php echo Agency_Block::client_can_comment_allowed($block) ? '1' : '0'; ?>"
                                    data-notify-on-comment="<?php echo Agency_Block::notify_on_comment_enabled($block) ? '1' : '0'; ?>"
                                  data-grid-width="<?php echo esc_attr($grid_width); ?>"
                                  data-grid-height="<?php echo esc_attr($grid_height); ?>"
                                  <?php if ($background_image_url): ?>data-background-image="<?php echo esc_attr($background_image_url); ?>"<?php endif; ?>
                                  <?php if (!$is_manager): ?>data-block-url="<?php echo esc_url($block_card_url); ?>"<?php if ($block->type === 'link' && $block_card_url !== home_url("/client/{$client->slug}/project/{$project->slug}/block/{$block->id}")): ?> data-block-target="_blank"<?php endif; ?><?php endif; ?>
                                  style="grid-column: span <?php echo esc_attr($grid_width); ?>; grid-row: span <?php echo esc_attr($grid_height); ?>; --agency-grid-height: <?php echo esc_attr($grid_height); ?>;"
                                  <?php if ($background_image_url): ?>data-bg-lazy="<?php echo esc_url($background_image_url); ?>"<?php endif; ?>>

                                <div class="block-content-wrapper">
                                    <div class="block-type-badge"><?php echo esc_html(Agency_Block::get_type_label($block->type)); ?></div>
                                    <h3 class="block-title"><?php echo esc_html($block->title); ?></h3>

                                    <div class="block-content">
                                        <?php if (($block->type === 'storyboard' || $block->type === 'versioned_media') && $first_asset): ?>
                                            <div class="block-preview-media">
                                                <?php if ($first_asset->file_type === 'image'): ?>
                                                    <img src="<?php echo esc_url(Agency_Asset::get_thumbnail_url($first_asset->id)); ?>"
                                                         alt="<?php echo esc_attr($first_asset->original_filename); ?>"
                                                         class="preview-image"
                                                         loading="lazy">
                                                    <?php if ($block->type === 'versioned_media' && !empty($first_asset->version_number)): ?>
                                                        <span class="version-badge">V<?php echo intval($first_asset->version_number); ?></span>
                                                    <?php endif; ?>
                                                <?php elseif ($first_asset->file_type === 'video'): ?>
                                                    <video class="preview-video">
                                                        <source src="<?php echo esc_url($preview_only_for_token_client ? Agency_Asset::get_preview_url($first_asset->id) : Agency_Asset::get_url($first_asset->id)); ?>">
                                                    </video>
                                                <?php endif; ?>
                                                <?php if ($assets_count > 1): ?>
                                                    <span class="media-count">+<?php echo esc_html(($assets_count - 1)); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php elseif ($block->content): ?>
                                            <div class="block-snippet">
                                                <?php
                                                $snippet = wp_strip_all_tags($block->content);
                                                echo esc_html(mb_substr($snippet, 0, 150)) . (mb_strlen($snippet) > 150 ? '...' : '');
                                                ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="block-footer">
                                        <?php
                                        $footer_url = $block_card_url;
                                        $footer_target = (!$is_manager && $block->type === 'link' && $footer_url !== home_url("/client/{$client->slug}/project/{$project->slug}/block/{$block->id}")) ? '_blank' : '_self';
                                        ?>
                                        <a href="<?php echo esc_url($footer_url); ?>"
                                           class="agency-btn agency-btn-sm agency-btn-primary"
                                           <?php if ($footer_target === '_blank'): ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>>
                                            <?php esc_html_e('View details', 'agency-client-portal'); ?>
                                            <?php if ($comments_count > 0): ?>
                                                <span class="comments-badge"><?php echo esc_html($comments_count); ?></span>
                                            <?php endif; ?>
                                        </a>

                                        <?php if ($is_manager): ?>
                                            <div class="block-actions">
                                                <button class="agency-btn-icon edit-block" data-block-id="<?php echo esc_attr($block->id); ?>" title="<?php esc_attr_e('Edit', 'agency-client-portal'); ?>">
                                                    <?php Agency_Icons::render('edit', 16); ?>
                                                </button>
                                                <button class="agency-btn-icon delete-block" data-block-id="<?php echo esc_attr($block->id); ?>" title="<?php esc_attr_e('Delete', 'agency-client-portal'); ?>">
                                                    <?php Agency_Icons::render('trash', 16); ?>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($is_manager): ?>
                                    <!-- Resize icon (bottom-right corner) -->
                                    <div class="block-resize-icon" data-handle="se" title="<?php esc_attr_e('Resize', 'agency-client-portal'); ?>">
                                        ⇲
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php endif; // separator check ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <?php include AGENCY_PLUGIN_DIR . 'templates/partials/footer.php'; ?>
</div>

<?php ob_start(); ?>
// Make block cards clickable for clients
document.addEventListener('DOMContentLoaded', function() {
    var clickableCards = document.querySelectorAll('.agency-block.clickable-card');
    clickableCards.forEach(function(card) {
        card.style.cursor = 'pointer';
        card.addEventListener('click', function(e) {
            // Don't trigger if clicking on a button or link
            if (e.target.closest('button') || e.target.closest('a')) {
                return;
            }
            var url = card.getAttribute('data-block-url');
            var target = card.getAttribute('data-block-target');
            if (url) {
                if (target === '_blank') {
                    window.open(url, '_blank', 'noopener,noreferrer');
                } else {
                    window.location.href = url;
                }
            }
        });
    });

    <?php if ($is_manager): ?>
    // Separator button: create a separator block
    var addSeparatorBtn = document.getElementById('add-separator-btn');
    if (addSeparatorBtn) {
        addSeparatorBtn.addEventListener('click', function() {
            addSeparatorBtn.disabled = true;
            var xhr = new XMLHttpRequest();
            xhr.open('POST', window.location.href, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if (xhr.status === 200) {
                    window.location.reload();
                } else {
                    addSeparatorBtn.disabled = false;
                }
            };
            xhr.send(
                'ajax_create_block=1&block_type=separator&block_title=&nonce=' +
                encodeURIComponent(<?php echo wp_json_encode($create_block_nonce); ?>)
            );
        });
    }

    // Separator label: auto-save on blur
    document.querySelectorAll('.separator-label[contenteditable]').forEach(function(el) {
        var blockId = el.getAttribute('data-block-id');
        el.addEventListener('blur', function() {
            var text = el.textContent.trim();
            fetch(agencyData.rest_url + 'blocks/' + blockId, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': agencyData.nonce
                },
                body: JSON.stringify({ settings: { separator_label: text } })
            });
        });
    });

    // Barre de progression — modal de création et d'édition
    (function() {
        var modal = document.getElementById('progress-bar-modal');
        if (!modal) return;

        var currentBlockId = null;
        var currentProjectId = null;
        var stepsList = document.getElementById('progress-bar-steps-list');

        function openModal(steps) {
            stepsList.innerHTML = '';
            if (!steps || steps.length === 0) {
                addStepRow('', 0);
            } else {
                steps.forEach(function(s) { addStepRow(s.name || '', s.percent || 0); });
            }
            modal.classList.add('active');
        }

        function closeModal() {
            modal.classList.remove('active');
            currentBlockId = null;
            currentProjectId = null;
        }

        function addStepRow(name, percent) {
            var row = document.createElement('div');
            row.className = 'progress-bar-step-row';
            row.innerHTML =
                '<input type="text" class="agency-form-input pb-step-name" placeholder="<?php esc_attr_e('Step name', 'agency-client-portal'); ?>">' +
                '<input type="number" class="agency-form-input pb-step-percent" min="0" max="100" placeholder="%">' +
                '<button type="button" class="agency-btn-icon progress-bar-remove-step" title="<?php esc_attr_e('Delete', 'agency-client-portal'); ?>">×</button>';
            row.querySelector('.pb-step-name').value = name;
            row.querySelector('.pb-step-percent').value = percent;
            stepsList.appendChild(row);
        }

        function collectSteps() {
            var steps = [];
            stepsList.querySelectorAll('.progress-bar-step-row').forEach(function(row) {
                var name = row.querySelector('.pb-step-name').value.trim();
                var pct = parseInt(row.querySelector('.pb-step-percent').value, 10);
                if (isNaN(pct)) pct = 0;
                pct = Math.max(0, Math.min(100, pct));
                steps.push({ name: name, percent: pct });
            });
            return steps;
        }

        // Ouvrir modal (création)
        var addBtn = document.getElementById('add-progress-bar-btn');
        if (addBtn) {
            addBtn.addEventListener('click', function() {
                currentBlockId = null;
                currentProjectId = addBtn.getAttribute('data-project-id');
                openModal([]);
            });
        }

        // Ouvrir modal (édition)
        document.addEventListener('click', function(e) {
            var editBtn = e.target.closest('.agency-edit-progress-bar');
            if (!editBtn) return;
            currentBlockId = editBtn.getAttribute('data-block-id');
            currentProjectId = null;
            var blockEl = document.querySelector('.agency-progress-bar-block[data-block-id="' + currentBlockId + '"]');
            var steps = [];
            if (blockEl) {
                try { steps = JSON.parse(blockEl.getAttribute('data-steps') || '[]'); } catch(e2) {}
            }
            openModal(steps);
        });

        // Fermer modal
        document.getElementById('progress-bar-modal-close').addEventListener('click', closeModal);
        document.getElementById('progress-bar-cancel').addEventListener('click', closeModal);
        modal.addEventListener('click', function(e) { if (e.target === modal) closeModal(); });

        // Ajouter une étape
        document.getElementById('progress-bar-add-step').addEventListener('click', function() {
            addStepRow('', 0);
        });

        // Supprimer une étape (délégué)
        stepsList.addEventListener('click', function(e) {
            var btn = e.target.closest('.progress-bar-remove-step');
            if (btn) btn.closest('.progress-bar-step-row').remove();
        });

        // Enregistrer
        document.getElementById('progress-bar-save').addEventListener('click', function() {
            var saveBtn = this;
            var steps = collectSteps();
            saveBtn.disabled = true;

            if (currentBlockId) {
                fetch(agencyData.rest_url + 'blocks/' + currentBlockId, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': agencyData.nonce },
                    body: JSON.stringify({ settings: { steps: steps } })
                }).then(function(res) {
                    if (res.ok) { window.location.reload(); }
                    else { saveBtn.disabled = false; }
                }).catch(function() { saveBtn.disabled = false; });
            } else {
                fetch(agencyData.rest_url + 'blocks', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': agencyData.nonce },
                    body: JSON.stringify({
                        project_id: parseInt(currentProjectId),
                        type: 'progress_bar',
                        title: 'Barre de progression',
                        settings: { steps: steps },
                        grid_width: 12
                    })
                }).then(function(res) {
                    if (res.ok) { window.location.reload(); }
                    else { saveBtn.disabled = false; }
                }).catch(function() { saveBtn.disabled = false; });
            }
        });
    })();

    // Subtitle éditable — sauvegarde auto
    (function() {
        var subtitleEl = document.querySelector('.agency-subtitle-editable');
        if (!subtitleEl) return;

        var saveTimer = null;
        var projectId = subtitleEl.dataset.projectId;
        var restUrl = (typeof agencyData !== 'undefined' && agencyData.rest_url) ? agencyData.rest_url : '';
        var nonce = (typeof agencyData !== 'undefined' && agencyData.nonce) ? agencyData.nonce : '';

        function saveSubtitle(text) {
            if (!restUrl || !projectId) return;
            fetch(restUrl + 'projects/' + projectId, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify({ subtitle: text })
            })
            .then(function(res) {
                if (!res.ok) {
                    console.error('Agency: subtitle save error', res.status);
                }
            })
            .catch(function(err) {
                console.error('Agency: subtitle network error', err);
            });
        }

        subtitleEl.addEventListener('blur', function() {
            clearTimeout(saveTimer);
            var text = this.textContent.trim();
            saveSubtitle(text);
        });

        subtitleEl.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.blur();
            }
            if (e.key === 'Escape') {
                this.textContent = this.dataset.original;
                this.blur();
            }
        });

        subtitleEl.addEventListener('input', function() {
            clearTimeout(saveTimer);
            var text = this.textContent.trim();
            saveTimer = setTimeout(function() {
                saveSubtitle(text);
            }, 500);
        });
    })();
    <?php endif; ?>

    <?php if ($is_manager): ?>
    // Project cover image management
    var setCoverBtn    = document.getElementById('set-project-cover-btn');
	var removeCoverBtn = document.getElementById('remove-project-cover-btn');

    function saveProjectCover(url, assetId, thumbnailUrl) {
        var payload = { cover_image_url: url || '' };
        if (typeof thumbnailUrl !== 'undefined') {
            payload.cover_image_thumbnail_url = thumbnailUrl || '';
        }
        if (assetId) {
            payload.cover_image_asset_id = parseInt(assetId);
        } else {
            payload.cover_image_asset_id = null;  // Clear asset ID when using URL directly
        }
        
        fetch(agencyData.rest_url + 'projects/<?php echo intval($project->id); ?>', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': agencyData.nonce },
            body: JSON.stringify(payload)
        }).then(function(r) { return r.json(); }).then(function(data) {
            if (data.success) window.location.reload();
        });
    }

    if (setCoverBtn && typeof wp !== 'undefined' && wp.media) {
        var _coverUploader = null;
        setCoverBtn.addEventListener('click', function(e) {
            e.preventDefault();
            // Définir le contexte d'upload avant d'ouvrir la médiathèque
            jQuery.post(agencyData.ajax_url, {
                action: 'agency_set_upload_context',
                nonce: agencyData.nonce,
                client_slug: '<?php echo esc_js($client->slug); ?>',
                project_slug: '<?php echo esc_js($project->slug); ?>'
            }).always(function() {
                if (!_coverUploader) {
                    _coverUploader = wp.media({
                        title: <?php echo wp_json_encode(__('Choose a cover image', 'agency-client-portal')); ?>,
                        button: { text: <?php echo wp_json_encode(__('Use this image', 'agency-client-portal')); ?> },
                        multiple: false,
                        library: { type: 'image' }
                    });
                    _coverUploader.on('select', function() {
                        var attachment = _coverUploader.state().get('selection').first().toJSON();
                        var thumbUrl = (attachment.sizes && attachment.sizes.large)
                            ? attachment.sizes.large.url
                            : attachment.url;
                        saveProjectCover(attachment.url, null, thumbUrl);
                    });
                }
                _coverUploader.open();
            });
        });
    }

	if (removeCoverBtn) {
        removeCoverBtn.addEventListener('click', function() {
            if (!confirm(<?php echo wp_json_encode(__('Delete the cover image?', 'agency-client-portal')); ?>)) return;
            saveProjectCover('', null, '');  // Clear URL, thumbnail URL, and asset ID
        });
    }
    <?php endif; ?>
});
<?php wp_print_inline_script_tag(ob_get_clean()); ?>

<?php wp_footer(); ?>
</body>
</html>
