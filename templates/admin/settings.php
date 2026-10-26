<?php
/**
 * Template Settings
 *
 * @package Agency
 */

if (!defined('ABSPATH')) {
    exit;
}

// Traitement du formulaire
if (isset($_POST['agency_save_settings']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['agency_settings_nonce'])), 'agency_save_settings')) {
    // Paramètres généraux
    if (isset($_POST['max_file_size'])) {
        update_option('agency_max_file_size', intval($_POST['max_file_size']));
    }
    if (isset($_POST['allowed_file_types'])) {
        update_option('agency_allowed_file_types', sanitize_text_field(wp_unslash($_POST['allowed_file_types'])));
    }

    // Branding : logo de l'agence (seul réglage d'apparence)
    if (isset($_POST['agency_brand_logo_url'])) {
        update_option('agency_brand_logo_url', esc_url_raw(wp_unslash($_POST['agency_brand_logo_url'])));
    }

    // Labels de projets
    if (isset($_POST['agency_project_labels'])) {
        $raw_labels = sanitize_textarea_field(wp_unslash($_POST['agency_project_labels']));
        $labels = array_values(array_filter(array_map('trim', explode("\n", $raw_labels))));
        update_option('agency_project_labels', $labels);
    }

    // Blocks - textes par défaut
    $block_defaults_keys = array('script', 'video', 'versioned_media', 'storyboard');
    foreach ($block_defaults_keys as $btype) {
        $key = 'agency_block_default_text_' . $btype;
        if (isset($_POST[$key])) {
            update_option($key, wp_kses_post(wp_unslash($_POST[$key])));
        }
    }

    // Mode Développeur
    $dev_mode_value = isset($_POST['agency_developer_mode']) ? 1 : 0;
    update_option('agency_developer_mode', $dev_mode_value);

    // Modèle d'email — assaini avec une liste blanche compatible email.
    if (isset($_POST['email_template_html'])) {
        $email_html = wp_kses(wp_unslash($_POST['email_template_html']), Agency_Mailer::get_allowed_html());
        update_option('agency_email_template_html', $email_html);
    }

    echo'<div class="notice notice-success"><p>' . esc_html(__('Settings saved.', 'agency-client-portal')) . '</p></div>';
}

$current_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'general';


$max_file_size = get_option('agency_max_file_size', 50);
$allowed_file_types = get_option('agency_allowed_file_types', 'jpg,jpeg,png,gif,pdf,doc,docx,mp4');
$brand_logo_url = get_option('agency_brand_logo_url', '');
$project_labels = get_option('agency_project_labels', array());
$block_default_texts = array(
    'script'         => get_option('agency_block_default_text_script', ''),
    'video'          => get_option('agency_block_default_text_video', ''),
    'versioned_media'=> get_option('agency_block_default_text_versioned_media', ''),
    'storyboard'     => get_option('agency_block_default_text_storyboard', ''),
);
?>

<div class="wrap">
    <div class="agency-admin-wrap">
        <h1 class="agency-admin-title"><?php esc_html_e('Agency Settings', 'agency-client-portal'); ?></h1>

        <h2 class="nav-tab-wrapper">
            <a href="<?php echo esc_url(admin_url('admin.php?page=agency-settings&tab=general')); ?>" class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e('General', 'agency-client-portal'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=agency-settings&tab=branding')); ?>" class="nav-tab <?php echo $current_tab === 'branding' ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e('Branding', 'agency-client-portal'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=agency-settings&tab=blocks')); ?>" class="nav-tab <?php echo $current_tab === 'blocks' ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e('Blocks', 'agency-client-portal'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=agency-settings&tab=emails')); ?>" class="nav-tab <?php echo $current_tab === 'emails' ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e('Emails', 'agency-client-portal'); ?>
            </a>
        </h2>

        <form method="post" action="">
            <?php wp_nonce_field('agency_save_settings', 'agency_settings_nonce'); ?>
            <input type="hidden" name="agency_settings_tab" value="<?php echo esc_attr($current_tab); ?>">
            
            <?php if ($current_tab === 'branding') : ?>
                <h2><?php esc_html_e('Agency logo', 'agency-client-portal'); ?></h2>
                <div class="agency-form-group">
                    <label for="agency_brand_logo_url"><?php esc_html_e('Logo (URL)', 'agency-client-portal'); ?></label>
                    <input type="url" id="agency_brand_logo_url" name="agency_brand_logo_url" value="<?php echo esc_url($brand_logo_url); ?>" class="widefat">
                    <button type="button" class="button" id="upload-brand-logo-btn"><?php esc_html_e('Choose a logo', 'agency-client-portal'); ?></button>
                    <p class="description"><?php esc_html_e('Displayed in the agency sidebar and on public pages.', 'agency-client-portal'); ?></p>
                    <?php if ($brand_logo_url): ?>
                        <div style="margin-top: 10px;">
                            <img src="<?php echo esc_url($brand_logo_url); ?>" alt="Logo" style="max-width: 200px; height: auto; border: 1px solid #ddd; padding: 5px;">
                        </div>
                    <?php endif; ?>
                </div>


            <?php elseif ($current_tab === 'blocks') : ?>
                <h2><?php esc_html_e('Default Block Texts', 'agency-client-portal'); ?></h2>
                <p class="description agency-mb-20"><?php esc_html_e('These texts are automatically inserted into a block\'s content when it is created. You can use basic HTML.', 'agency-client-portal'); ?></p>

                <div class="agency-form-group">
                    <label for="block_default_script"><?php esc_html_e('Script — default text', 'agency-client-portal'); ?></label>
                    <textarea id="block_default_script" name="agency_block_default_text_script" rows="5" class="agency-w-full"><?php echo esc_textarea($block_default_texts['script']); ?></textarea>
                </div>

                <div class="agency-form-group">
                    <label for="block_default_video"><?php esc_html_e('Video — default text', 'agency-client-portal'); ?></label>
                    <textarea id="block_default_video" name="agency_block_default_text_video" rows="5" class="agency-w-full"><?php echo esc_textarea($block_default_texts['video']); ?></textarea>
                </div>

                <div class="agency-form-group">
                    <label for="block_default_versioned_media"><?php esc_html_e('Versioned media — default text', 'agency-client-portal'); ?></label>
                    <textarea id="block_default_versioned_media" name="agency_block_default_text_versioned_media" rows="5" class="agency-w-full"><?php echo esc_textarea($block_default_texts['versioned_media']); ?></textarea>
                </div>

                <div class="agency-form-group">
                    <label for="block_default_storyboard"><?php esc_html_e('Storyboard — default text', 'agency-client-portal'); ?></label>
                    <textarea id="block_default_storyboard" name="agency_block_default_text_storyboard" rows="5" class="agency-w-full"><?php echo esc_textarea($block_default_texts['storyboard']); ?></textarea>
                </div>

            <?php elseif ($current_tab === 'emails') : ?>
                <?php
                $email_template_html  = get_option('agency_email_template_html', '');
                if (trim((string) $email_template_html) === '') {
                    $email_template_html = Agency_Mailer::DEFAULT_TEMPLATE;
                }
                $email_tokens = Agency_Mailer::get_available_tokens();
                ?>
                <h2><?php esc_html_e('Email template', 'agency-client-portal'); ?></h2>
                <p class="description agency-mb-20">
                    <?php esc_html_e('This HTML template is used for all emails sent by Agency (comment notifications and manual client notifications). Use the tokens below; they will be replaced when the email is sent.', 'agency-client-portal'); ?>
                </p>


                <div class="agency-email-editor" style="display:flex; gap:24px; flex-wrap:wrap; align-items:flex-start;">
                    <div style="flex:1 1 380px; min-width:320px;">
                        <label for="email_template_html" class="agency-field-label"><?php esc_html_e('HTML code', 'agency-client-portal'); ?></label>
                        <textarea id="email_template_html" name="email_template_html" rows="22"
                                  style="width:100%; font-family:monospace; font-size:12px; line-height:1.5;"><?php echo esc_textarea($email_template_html); ?></textarea>
                        <p class="agency-mt-10">
                            <button type="button" class="button button-secondary" id="agency-email-reset">
                                <?php esc_html_e('Reset template', 'agency-client-portal'); ?>
                            </button>
                        </p>
                    </div>
                    <div style="flex:1 1 380px; min-width:320px;">
                        <label class="agency-field-label"><?php esc_html_e('Preview', 'agency-client-portal'); ?></label>
                        <iframe id="agency-email-preview" style="width:100%; height:520px; border:1px solid #ccc; border-radius:6px; background:#fff;"></iframe>
                    </div>
                </div>

                <h3 class="agency-mt-24"><?php esc_html_e('Available tokens', 'agency-client-portal'); ?></h3>
                <table class="widefat striped agency-maxw-760">
                    <tbody>
                    <?php foreach ($email_tokens as $token => $desc) : ?>
                        <tr>
                            <td style="width:200px;"><code>{{<?php echo esc_html($token); ?>}}</code></td>
                            <td><?php echo esc_html($desc); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <textarea id="agency-email-default-template" style="display:none;" aria-hidden="true"><?php echo esc_textarea(Agency_Mailer::DEFAULT_TEMPLATE); ?></textarea>

            <?php else : ?>
                <h2><?php esc_html_e('File Uploads', 'agency-client-portal'); ?></h2>

                <div class="agency-form-group">
                    <label for="max_file_size"><?php esc_html_e('Maximum file size (MB)', 'agency-client-portal'); ?></label>
                    <input type="number" id="max_file_size" name="max_file_size" value="<?php echo esc_attr($max_file_size); ?>" min="1" max="1000">
                    <p class="description"><?php esc_html_e('Maximum size allowed for uploaded files.', 'agency-client-portal'); ?></p>
                </div>

                <div class="agency-form-group">
                    <label for="allowed_file_types"><?php esc_html_e('Allowed file types', 'agency-client-portal'); ?></label>
                    <input type="text" id="allowed_file_types" name="allowed_file_types" value="<?php echo esc_attr($allowed_file_types); ?>" class="agency-w-full">
                    <p class="description"><?php esc_html_e('Allowed file extensions, comma-separated (e.g. jpg,png,pdf).', 'agency-client-portal'); ?></p>
                </div>

                <h2 class="agency-mt-30"><?php esc_html_e('Project Labels', 'agency-client-portal'); ?></h2>
                <div class="agency-form-group">
                    <label for="agency_project_labels"><?php esc_html_e('Available labels', 'agency-client-portal'); ?></label>
                    <textarea id="agency_project_labels" name="agency_project_labels" rows="6" class="agency-w-full" placeholder="<?php esc_attr_e('One label per line', 'agency-client-portal'); ?>"><?php echo esc_textarea(implode("\n", (array) $project_labels)); ?></textarea>
                    <p class="description"><?php esc_html_e('Enter one label per line. These labels will be available when creating or editing a project.', 'agency-client-portal'); ?></p>
                </div>

                <h2 class="agency-mt-30"><?php esc_html_e('Developer Mode', 'agency-client-portal'); ?></h2>
                <div class="agency-form-group">
                    <label for="agency_developer_mode">
                        <input type="checkbox" id="agency_developer_mode" name="agency_developer_mode" value="1" <?php checked(get_option('agency_developer_mode', 0), 1); ?>>
                        <?php esc_html_e('Enable developer mode', 'agency-client-portal'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Enables the Debug tab in the Agency menu for administrators.', 'agency-client-portal'); ?></p>
                </div>
            <?php endif; ?>
            
            
            <h2><?php esc_html_e('System Information', 'agency-client-portal'); ?></h2>
            
            <table class="widefat">
                <tbody>
                    <tr>
                        <td><strong><?php esc_html_e('Plugin Version', 'agency-client-portal'); ?></strong></td>
                        <td><?php echo esc_html(AGENCY_VERSION); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Database Version', 'agency-client-portal'); ?></strong></td>
                        <td><?php echo esc_html(get_option('agency_db_version', 'N/A')); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Upload Folder', 'agency-client-portal'); ?></strong></td>
                        <td>
                            <?php echo esc_html(AGENCY_UPLOAD_DIR); ?>
                            <?php if (wp_is_writable(AGENCY_UPLOAD_DIR)): ?>
                                <span style="color: var(--agency-success);"><?php Agency_Icons::render('check', 13); ?> <?php esc_html_e('Writable', 'agency-client-portal'); ?></span>
                            <?php else: ?>
                                <span style="color: var(--agency-danger);"><?php Agency_Icons::render('x', 13); ?> <?php esc_html_e('Not writable', 'agency-client-portal'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Installation Date', 'agency-client-portal'); ?></strong></td>
                        <td><?php echo esc_html(get_option('agency_installed_date', 'N/A')); ?></td>
                    </tr>
                </tbody>
            </table>
            
            <?php /* Styles de la section mise à jour : déplacés dans assets/css/admin.css */ ?>
            
            <p class="agency-mt-30">
                <input type="submit" name="agency_save_settings" class="button button-primary" value="<?php esc_attr_e('Save settings', 'agency-client-portal'); ?>">
            </p>
        </form>
        
        <hr class="agency-my-40">
        
        <h2><?php esc_html_e('Tools', 'agency-client-portal'); ?></h2>
        
        <p>
            <a href="<?php echo esc_url(admin_url('options-permalink.php')); ?>" class="button">
                <?php esc_html_e('Regenerate Permalinks', 'agency-client-portal'); ?>
            </a>
        </p>
        
        <div class="agency-notice warning agency-mt-20">
            <p><strong><?php esc_html_e('Warning:', 'agency-client-portal'); ?></strong></p>
            <p><?php esc_html_e('Deleting the plugin will erase all data (clients, projects, blocks, comments). This action cannot be undone.', 'agency-client-portal'); ?></p>
        </div>
    </div>
</div>

<?php if ($current_tab === 'branding') : ?>
<?php ob_start(); ?>
jQuery(document).ready(function($) {
    $('#upload-brand-logo-btn').on('click', function(e) {
        e.preventDefault();

        var mediaUploader = wp.media({
            title: '<?php echo esc_js(__('Choose a logo', 'agency-client-portal')); ?>',
            button: { text: '<?php echo esc_js(__('Use this logo', 'agency-client-portal')); ?>' },
            multiple: false
        });

        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#agency_brand_logo_url').val(attachment.url);
        });

        mediaUploader.open();
    });
});
<?php wp_print_inline_script_tag(ob_get_clean()); ?>
<?php endif; ?>


