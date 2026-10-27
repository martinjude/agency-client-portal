<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Block View Router
 * Routes to the appropriate block view template based on block type
 */

$agency_client_slug = get_query_var('agency_client');
$agency_project_slug = get_query_var('agency_project');
$agency_block_id = get_query_var('agency_block');

$agency_client = Agency_Client::get_by_slug($agency_client_slug);
$agency_project = $agency_client ? Agency_Project::get_by_slug($agency_client->id, $agency_project_slug) : null;
$agency_block = Agency_Block::get_by_id($agency_block_id);

if (!$agency_client || !$agency_project || !$agency_block) {
    wp_die(esc_html__('Content not found', 'agency-client-portal'), 404);
}

// Route to the appropriate template based on block type
$agency_template_file = '';
switch ($agency_block->type) {
    case 'script':
        $agency_template_file = 'block-view-script.php';
        break;
    case 'storyboard':
        $agency_template_file = 'block-view-storyboard.php';
        break;
    case 'versioned_media':
        $agency_template_file = 'block-view-versioned.php';
        break;
    case 'video':
        $agency_template_file = 'block-view-video.php';
        break;
    case 'link':
        $agency_template_file = 'block-view-link.php';
        break;
    case 'file_deposit':
        $agency_template_file = 'block-view-file-deposit.php';
        break;
    case 'code':
        $agency_template_file = 'block-view-code.php';
        break;
    default:
        // For other block types, use the default template below
        $agency_template_file = '';
        break;
}

// If a specific template exists, include it and exit
if ($agency_template_file) {
    $agency_template_path = plugin_dir_path(__FILE__) . $agency_template_file;
    if (file_exists($agency_template_path)) {
        include $agency_template_path;
        return;
    }
}

// Default template for other block types
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

$assets = Agency_Asset::get_by_block($block_id);
$comments = Agency_Comment::get_by_block($block_id);
$is_manager = current_user_can('manage_agency_projects');
?>

<div class="agency-wrapper">
    <!-- Header -->
    <?php include AGENCY_PLUGIN_DIR . 'templates/partials/header.php'; ?>

    <!-- Sidebar manager -->
    <?php include AGENCY_PLUGIN_DIR . 'templates/partials/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="agency-main">


        <div class="agency-container">
            <div class="agency-block-view">
                <?php include AGENCY_PLUGIN_DIR . 'templates/partials/block-head.php'; ?>

                <div class="block-header-full agency-bv-bar">
                    
                    <?php if ($agency_is_manager): ?>
                        <div class="agency-actions">
                            <button class="agency-btn agency-btn-secondary" id="edit-block-btn" data-block-id="<?php echo esc_attr($agency_block->id); ?>">
                                <?php esc_html_e('Edit', 'agency-client-portal'); ?>
                            </button>
                            <button class="agency-btn agency-btn-primary" id="upload-asset-btn">
                                <?php Agency_Icons::render('paperclip', 16); ?> <?php esc_html_e('Add files', 'agency-client-portal'); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Contenu du bloc -->
                <div class="block-content-full">
                    <?php if ($agency_block->content): ?>
                        <div class="block-text-content">
                            <?php echo wp_kses_post($agency_block->content); ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Assets -->
                    <?php if (!empty($agency_assets)): ?>
                        <div class="block-assets">
                            <h3><?php esc_html_e('Files', 'agency-client-portal'); ?></h3>
                            <div class="assets-grid">
                                <?php foreach ($agency_assets as $agency_asset): ?>
                                    <div class="asset-item" data-asset-id="<?php echo esc_attr($agency_asset->id); ?>">
                                        <?php if ($agency_asset->file_type === 'image'): ?>
                                            <img src="<?php echo esc_url(Agency_Asset::get_url($agency_asset->id)); ?>" alt="<?php echo esc_attr($agency_asset->original_filename); ?>" loading="lazy">
                                        <?php else: ?>
                                            <div class="asset-icon"><?php Agency_Icons::render('file', 32); ?></div>
                                        <?php endif; ?>
                                        
                                        <div class="asset-info">
                                            <span class="asset-name"><?php echo esc_html($agency_asset->original_filename); ?></span>
                                            <span class="asset-size"><?php echo esc_html(size_format($agency_asset->file_size)); ?></span>
                                        </div>
                                        
                                        <div class="asset-actions">
                                            <a href="<?php echo esc_url(Agency_Asset::get_url($agency_asset->id)); ?>" class="agency-btn-icon" target="_blank" title="<?php esc_attr_e('Download', 'agency-client-portal'); ?>">
                                                <?php Agency_Icons::render('arrow-down', 16); ?>
                                            </a>
                                            <?php if ($agency_is_manager): ?>
                                                <button class="agency-btn-icon delete-asset" data-asset-id="<?php echo esc_attr($agency_asset->id); ?>" title="<?php esc_attr_e('Delete', 'agency-client-portal'); ?>">
                                                    <?php Agency_Icons::render('trash', 16); ?>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Section Commentaires -->
                <div class="block-comments">
                    <h3><?php esc_html_e('Comments & Suggestions', 'agency-client-portal'); ?></h3>
                    
                    <div class="comments-list" id="comments-list">
                        <?php if (empty($agency_comments)): ?>
                            <p class="no-comments"><?php esc_html_e('No comments yet.', 'agency-client-portal'); ?></p>
                        <?php else: ?>
                            <?php foreach ($agency_comments as $comment): ?>
                                <div class="comment-item" data-comment-id="<?php echo esc_attr($comment->id); ?>">
                                    <div class="comment-header">
                                        <span class="comment-author"><?php echo esc_html($comment->user->display_name); ?></span>
                                        <span class="comment-date"><?php echo esc_html(Agency_Template::time_ago($comment->created_at)); ?></span>
                                    </div>
                                    <div class="comment-content">
                                        <?php echo wp_kses_post($comment->content); ?>
                                    </div>
                                    <?php if ($agency_is_manager || get_current_user_id() === (int)$comment->user_id): ?>
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
                    
                    <!-- Formulaire nouveau commentaire -->
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
    </main>

    <!-- Footer -->
    <?php include AGENCY_PLUGIN_DIR . 'templates/partials/footer.php'; ?>
</div>

<?php wp_footer(); ?>
</body>
</html>
