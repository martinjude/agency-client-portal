<?php
/**
 * Meta Box - Détails du Client
 *
 * @package Agency
 */

if (!defined('ABSPATH')) {
    exit;
}

$client_name = $client_data ? $client_data->name : '';
$client_slug = $client_data ? $client_data->slug : '';
$user_id = $client_data ? $client_data->user_id : 0;
?>

<div class="agency-meta-box">
    <div class="agency-form-group">
        <label for="client_name"><?php esc_html_e('Client Name', 'agency-client-portal'); ?> *</label>
        <input type="text" id="client_name" name="client_name" value="<?php echo esc_attr($client_name); ?>" class="widefat" required>
        <p class="description"><?php esc_html_e('Your client\'s name (used to generate the slug).', 'agency-client-portal'); ?></p>
    </div>
    
    <?php if ($client_slug): ?>
        <div class="agency-form-group">
            <label><?php esc_html_e('Client URL', 'agency-client-portal'); ?></label>
            <input type="text" value="<?php echo esc_url(home_url('/client/' . $client_slug)); ?>" class="widefat" readonly style="background-color: #f0f0f1; cursor: not-allowed;">
            <p class="description"><?php esc_html_e('Client portal access URL (read-only).', 'agency-client-portal'); ?></p>
        </div>
        
        <div class="agency-form-group">
            <label><?php esc_html_e('Slug', 'agency-client-portal'); ?></label>
            <input type="text" value="<?php echo esc_attr($client_slug); ?>" class="widefat" disabled>
            <p class="description"><?php esc_html_e('Generated automatically from the name.', 'agency-client-portal'); ?></p>
        </div>
    <?php endif; ?>
    
    <!-- Removed legacy WP user association field: token-based access only -->
    
    
    <?php if ($client_data): ?>
        <div class="agency-notice">
            <p><strong><?php esc_html_e('Access URL:', 'agency-client-portal'); ?></strong></p>
            <div class="agency-access-url">
                <?php echo esc_url(home_url('/client/' . $client_slug)); ?>
            </div>
            <button type="button" class="agency-copy-btn" data-url="<?php echo esc_url(home_url('/client/' . $client_slug)); ?>">
                <?php esc_html_e('Copy URL', 'agency-client-portal'); ?>
            </button>
        </div>
    <?php endif; ?>

    <?php if ($client_data): ?>
        <div class="agency-danger-zone">
            <h3><?php esc_html_e('Danger Zone', 'agency-client-portal'); ?></h3>
            <p><?php esc_html_e('This action cannot be undone. It permanently deletes the client and all of its projects, blocks, comments and associated files.', 'agency-client-portal'); ?></p>
            <button type="button" class="agency-btn agency-btn-sm agency-btn-danger-solid" id="agency-hard-delete-client"
                    data-client-id="<?php echo esc_attr($client_data->id); ?>">
                <?php esc_html_e('Permanently delete the client', 'agency-client-portal'); ?>
            </button>
        </div>
    <?php endif; ?>
</div>
