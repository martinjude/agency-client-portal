<?php
/**
 * Template Nouveau Client
 *
 * @package Agency
 */

if (!defined('ABSPATH')) {
    exit;
}

// Traitement du formulaire
if (isset($_POST['agency_create_client']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['agency_client_nonce'])), 'agency_create_client')) {
    $agency_data = array(
        'name' => sanitize_text_field(wp_unslash($_POST['client_name'])),
        'logo_url' => esc_url_raw(wp_unslash($_POST['client_logo'])),
        'primary_color' => sanitize_hex_color(wp_unslash($_POST['primary_color'])),
    );

    // Créer le post CPT agency_client
    $agency_post_id = wp_insert_post(array(
        'post_title'   => $agency_data['name'],
        'post_type'    => 'agency_client',
        'post_status'  => 'publish',
    ));

    if ($agency_post_id && !is_wp_error($agency_post_id)) {
        // Associer les métadonnées au post
        update_post_meta($agency_post_id, 'logo_url', $agency_data['logo_url']);
        update_post_meta($agency_post_id, 'primary_color', $agency_data['primary_color']);

        // Créer l'entrée en base personnalisée (token-based access only)
        $agency_client_id = Agency_Client::create($agency_data);

        // Lier le post CPT à l'entrée DB personnalisée (meta)
        update_post_meta($agency_post_id, 'agency_client_id', $agency_client_id);

        // Legacy user meta/role removed

        echo '<div class="agency-notice success"><p>' . esc_html(__('Client created successfully!', 'agency-client-portal')) . '</p></div>';
        $agency_client = Agency_Client::get_by_id($agency_client_id);
    } else {
        echo '<div class="agency-notice error"><p>' . esc_html(__('Error while creating the client.', 'agency-client-portal')) . '</p></div>';
    }
}
?>

<div class="wrap">
    <div class="agency-admin-wrap">
        <h1 class="agency-admin-title"><?php esc_html_e('Create a New Client', 'agency-client-portal'); ?></h1>

        <?php if (isset($agency_client)): ?>
            <div class="agency-notice success">
                <p><strong><?php esc_html_e('Client created successfully!', 'agency-client-portal'); ?></strong></p>
                <p><?php esc_html_e('Access URL:', 'agency-client-portal'); ?></p>
                <div class="agency-access-url">
                    <?php echo esc_url(home_url('/client/' . $agency_client->slug)); ?>
                </div>
                <button class="agency-copy-btn" data-url="<?php echo esc_url(home_url('/client/' . $agency_client->slug)); ?>">
                    <?php esc_html_e('Copy URL', 'agency-client-portal'); ?>
                </button>
            </div>
        <?php endif; ?>
        
        <form method="post" action="">
            <?php wp_nonce_field('agency_create_client', 'agency_client_nonce'); ?>
            
            <div class="agency-form-group">
                <label for="client_name"><?php esc_html_e('Client Name *', 'agency-client-portal'); ?></label>
                <input type="text" id="client_name" name="client_name" required>
                <p class="description"><?php esc_html_e('Your client\'s name (e.g. "ABC Company").', 'agency-client-portal'); ?></p>
            </div>
            
            <div class="agency-form-group">
                <label for="client_logo"><?php esc_html_e('Client Logo', 'agency-client-portal'); ?></label>
                <input type="hidden" id="client_logo" name="client_logo">
                <button type="button" class="button" id="upload-logo-btn"><?php esc_html_e('Select a logo', 'agency-client-portal'); ?></button>
                <button type="button" class="button" id="remove-logo-btn" style="display:none;"><?php esc_html_e('Delete', 'agency-client-portal'); ?></button>
                <p class="description"><?php esc_html_e('Client logo displayed in the header. Leave empty to use the client name.', 'agency-client-portal'); ?></p>
                <div id="logo-preview" style="margin-top: 10px; display:none;">
                    <img src="" alt="Logo preview" style="max-width: 200px; height: auto; border: 1px solid #ddd; padding: 5px;">
                </div>
            </div>
            
            <div class="agency-meta-row">
                <div class="agency-meta-col">
                    <div class="agency-form-group">
                        <label for="primary_color"><?php esc_html_e('Primary Color *', 'agency-client-portal'); ?></label>
                        <input type="color" id="primary_color" name="primary_color" value="#000000" required>
                        <span class="agency-color-preview"></span>
                        <p class="description"><?php esc_html_e('Client\'s main color.', 'agency-client-portal'); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="agency-notice">
                <p><strong><?php esc_html_e('Next steps:', 'agency-client-portal'); ?></strong></p>
                <ol>
                    <li><?php esc_html_e('Create a WordPress user with the "Agency Client" role', 'agency-client-portal'); ?></li>
                    <li><?php esc_html_e('Associate the user with this client', 'agency-client-portal'); ?></li>
                    <li><?php esc_html_e('Share the access URL with the client', 'agency-client-portal'); ?></li>
                </ol>
            </div>
            
            <p>
                <input type="submit" name="agency_create_client" class="button button-primary button-large" value="<?php esc_attr_e('Create Client', 'agency-client-portal'); ?>">
            </p>
        </form>
    </div>
</div>

<?php ob_start(); ?>
jQuery(document).ready(function($) {
    var mediaUploader;
    
    // Media uploader for logo
    $('#upload-logo-btn').on('click', function(e) {
        e.preventDefault();
        
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        
        mediaUploader = wp.media({
            title: '<?php echo esc_js(__('Select a logo', 'agency-client-portal')); ?>',
            button: {
                text: '<?php echo esc_js(__('Use this logo', 'agency-client-portal')); ?>'
            },
            multiple: false,
            library: {
                type: 'image'
            }
        });
        
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#client_logo').val(attachment.url);
            $('#logo-preview img').attr('src', attachment.url);
            $('#logo-preview').show();
            $('#remove-logo-btn').show();
        });
        
        mediaUploader.open();
    });
    
    // Remove logo
    $('#remove-logo-btn').on('click', function(e) {
        e.preventDefault();
        $('#client_logo').val('');
        $('#logo-preview').hide();
        $('#logo-preview img').attr('src', '');
        $(this).hide();
    });
});
<?php wp_print_inline_script_tag(ob_get_clean()); ?>
