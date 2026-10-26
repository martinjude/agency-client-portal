<?php
/**
 * Meta Box - Branding
 *
 * @package Agency
 */

if (!defined('ABSPATH')) {
    exit;
}

$logo_url = $client_data ? $client_data->logo_url : '';
$primary_color = $client_data ? $client_data->primary_color : '#000000';
?>

<div class="agency-meta-box">
    <div class="agency-form-group">
        <label for="client_logo"><?php esc_html_e('Logo (URL)', 'agency-client-portal'); ?></label>
        <input type="url" id="client_logo" name="client_logo" value="<?php echo esc_url($logo_url); ?>" class="widefat">
        <button type="button" class="button" id="upload-logo-btn"><?php esc_html_e('Choose a logo', 'agency-client-portal'); ?></button>
        <p class="description"><?php esc_html_e('Client logo.', 'agency-client-portal'); ?></p>
        
        <?php if ($logo_url): ?>
            <div style="margin-top: 10px;">
                <img src="<?php echo esc_url($logo_url); ?>" alt="Logo" style="max-width: 200px; height: auto; border: 1px solid #ddd; padding: 5px;">
            </div>
        <?php endif; ?>
    </div>
    
    <div class="agency-form-group">
        <label for="primary_color"><?php esc_html_e('Accent color', 'agency-client-portal'); ?></label>
        <input type="color" id="primary_color" name="primary_color" value="<?php echo esc_attr($primary_color); ?>">
        <span class="agency-color-preview" style="background-color: <?php echo esc_attr($primary_color); ?>"></span>
        <p class="description"><?php esc_html_e('Single client color, used for primary buttons, the avatar and active highlights.', 'agency-client-portal'); ?></p>
    </div>
    
</div>

<?php ob_start(); ?>
jQuery(document).ready(function($) {
    // Media uploader pour le logo
    $('#upload-logo-btn').on('click', function(e) {
        e.preventDefault();
        
        var mediaUploader = wp.media({
            title: '<?php echo esc_js(__('Choose a logo', 'agency-client-portal')); ?>',
            button: {
                text: '<?php echo esc_js(__('Use this logo', 'agency-client-portal')); ?>'
            },
            multiple: false
        });
        
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#client_logo').val(attachment.url);
        });
        
        mediaUploader.open();
    });
});
<?php wp_print_inline_script_tag(ob_get_clean()); ?>
