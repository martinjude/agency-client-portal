<?php
/**
 * Meta Box - Accès & Sécurité Client
 *
 * @package Agency
 */

if (!defined('ABSPATH')) {
    exit;
}

$post_id = get_the_ID();
$client_id = get_post_meta($post_id, 'agency_client_id', true);

if (!$client_id) {
    echo '<div class="notice notice-error"><p>' . esc_html(__('Error: client ID not found. Please save the client first.', 'agency-client-portal')) . '</p></div>';
    return;
}

$client_data = Agency_Client::get_by_id($client_id);
$status = $client_data ? $client_data->status : 'active';
$created_at = $client_data ? $client_data->created_at : '';
$has_token = $client_data ? Agency_Client::has_active_token($client_id) : false;
$token_created = $client_data ? $client_data->token_created_at : '';
?>

<div class="agency-meta-box">
    <div class="agency-form-group">
        <label><?php esc_html_e('Client Access', 'agency-client-portal'); ?></label>
        
        <?php if ($has_token): ?>
            <div class="agency-token-status active">
                <?php Agency_Icons::render('check', 14); ?>
                <?php esc_html_e('Access token active', 'agency-client-portal'); ?>
                <?php if ($token_created): ?>
                    <br><small><?php /* translators: %s: date */ echo esc_html(sprintf(__('Generated on %s', 'agency-client-portal'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($token_created)))); ?></small>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="agency-token-status inactive">
                <?php Agency_Icons::render('alert', 14); ?>
                <?php esc_html_e('No active access token', 'agency-client-portal'); ?>
            </div>
        <?php endif; ?>
        
        <div class="agency-token-actions" style="margin-top: 15px;">
            <?php if (!$has_token): ?>
                <button type="button" class="button button-primary" id="generate-access-link">
                    <?php Agency_Icons::render('link', 14); ?>
                    <?php esc_html_e('Generate Access Link', 'agency-client-portal'); ?>
                </button>
            <?php else: ?>
                <button type="button" class="button" id="regenerate-token" data-client-id="<?php echo esc_attr($client_id); ?>">
                    <?php Agency_Icons::render('save', 14); ?>
                    <?php esc_html_e('Regenerate Token', 'agency-client-portal'); ?>
                </button>
                
                <button type="button" class="button button-link-delete" id="revoke-token" data-client-id="<?php echo esc_attr($client_id); ?>">
                    <?php Agency_Icons::render('lock', 14); ?>
                    <?php esc_html_e('Revoke Access', 'agency-client-portal'); ?>
                </button>
            <?php endif; ?>
        </div>
        
        <?php if ($has_token): ?>
        <div id="access-link-result" style="margin-top: 15px;">
            <label><?php esc_html_e('Client access link:', 'agency-client-portal'); ?></label>
            <div class="agency-copy-container">
                <input type="text" id="access-link-input" class="widefat" readonly>
                <button type="button" class="button" id="copy-access-link">
                    <?php Agency_Icons::render('paperclip', 14); ?>
                    <?php esc_html_e('Copy', 'agency-client-portal'); ?>
                </button>
            </div>
            <p class="description">
                <?php esc_html_e('Share this link with your client so they can access their space directly without a password.', 'agency-client-portal'); ?>
                <br><strong><?php esc_html_e('⚠️ Never share this link publicly!', 'agency-client-portal'); ?></strong>
            </p>
        </div>
        <?php else: ?>
        <div id="access-link-result" style="display: none; margin-top: 15px;">
            <label><?php esc_html_e('Client access link:', 'agency-client-portal'); ?></label>
            <div class="agency-copy-container">
                <input type="text" id="access-link-input" class="widefat" readonly>
                <button type="button" class="button" id="copy-access-link">
                    <?php Agency_Icons::render('paperclip', 14); ?>
                    <?php esc_html_e('Copy', 'agency-client-portal'); ?>
                </button>
            </div>
            <p class="description">
                <?php esc_html_e('Share this link with your client so they can access their space directly without a password.', 'agency-client-portal'); ?>
                <br><strong><?php esc_html_e('⚠️ Never share this link publicly!', 'agency-client-portal'); ?></strong>
            </p>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="agency-form-group">
        <label for="client_status"><?php esc_html_e('Status', 'agency-client-portal'); ?></label>
        <select id="client_status" name="client_status" class="widefat">
            <option value="active" <?php selected($status, 'active'); ?>><?php esc_html_e('Active', 'agency-client-portal'); ?></option>
            <option value="inactive" <?php selected($status, 'inactive'); ?>><?php esc_html_e('Inactive', 'agency-client-portal'); ?></option>
        </select>
        <p class="description"><?php esc_html_e('An inactive client will no longer be able to log in.', 'agency-client-portal'); ?></p>
    </div>
    
    <?php if ($created_at): ?>
        <div class="agency-form-group">
            <label><?php esc_html_e('Creation Date', 'agency-client-portal'); ?></label>
            <input type="text" value="<?php echo esc_attr(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($created_at))); ?>" class="widefat" disabled>
        </div>
    <?php endif; ?>
    
    <?php if ($client_data): ?>
        <hr style="margin: 20px 0;">
        
        <h4><?php esc_html_e('Statistics', 'agency-client-portal'); ?></h4>
        <?php
        $projects_count = count(Agency_Project::get_by_client($client_data->id));
        $blocks_count = 0;
        $comments_count = 0;
        
        foreach (Agency_Project::get_by_client($client_data->id) as $project) {
            $blocks = Agency_Block::get_by_project($project->id);
            $blocks_count += count($blocks);
            
            foreach ($blocks as $block) {
                $comments_count += Agency_Comment::count_by_block($block->id);
            }
        }
        ?>
        
        <table class="widefat">
            <tbody>
                <tr>
                    <td><strong><?php esc_html_e('Projects', 'agency-client-portal'); ?></strong></td>
                    <td><?php echo esc_html($projects_count); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Blocks', 'agency-client-portal'); ?></strong></td>
                    <td><?php echo esc_html($blocks_count); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Comments', 'agency-client-portal'); ?></strong></td>
                    <td><?php echo esc_html($comments_count); ?></td>
                </tr>
            </tbody>
        </table>
        
        <!-- Hidden forms removed: token actions now use AJAX -->
    <?php endif; ?>
</div>

<?php /* Styles : déplacés dans assets/css/admin.css (.agency-token-status, .agency-copy-container) */ ?>

<?php ob_start(); ?>
jQuery(document).ready(function($) {
    // Vérifier que les variables nécessaires sont disponibles
    if (typeof agencyAdmin === 'undefined') {
        console.error('agencyAdmin object is not defined. Make sure admin.js is loaded.');
        return;
    }
    
    console.log('Agency Access Meta Box loaded');
    console.log('AJAX URL:', agencyAdmin.ajax_url);
    
    // Charger le lien d'accès si encore disponible (transient < 1h).
    <?php if ($has_token): ?>
    $.post(agencyAdmin.ajax_url, {
        action: 'agency_get_access_url',
        client_id: <?php echo intval($client_id); ?>,
        nonce: agencyAdmin.nonce
    }, function(response) {
        if (response.success) {
            $('#access-link-input').val(response.data.access_url);
        } else if (response.data && response.data.code === 'token_plaintext_expired') {
            // Plaintext expiré : on n'affiche pas le champ, on incite à régénérer.
            $('#access-link-input').attr('placeholder', response.data.message || '');
        }
    });
    <?php endif; ?>
    
    // Générer le lien d'accès
    $('#generate-access-link').on('click', function(e) {
        e.preventDefault();
        console.log('Generate access link button clicked');
        
        var button = $(this);
        var originalText = button.text();
        
        button.prop('disabled', true).text('<?php echo esc_js(__('Generating...', 'agency-client-portal')); ?>');
        
        var ajaxData = {
            action: 'agency_get_access_url',
            client_id: <?php echo intval($client_id); ?>,
            nonce: agencyAdmin.nonce
        };
        
        console.log('Sending AJAX request with data:', ajaxData);
        
        $.post(agencyAdmin.ajax_url, ajaxData, function(response) {
            console.log('AJAX response received:', response);
            if (response.success) {
                $('#access-link-input').val(response.data.access_url);
                $('#access-link-result').show();
            } else {
                var msg = response.data && response.data.message
                    ? response.data.message
                    : (typeof response.data === 'string' ? response.data : 'Unknown error');
                alert('<?php echo esc_js(__('Error: ', 'agency-client-portal')); ?>' + msg);
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            console.error('AJAX request failed:', textStatus, errorThrown);
            console.error('Response:', jqXHR.responseText);
            alert('<?php echo esc_js(__('AJAX error: ', 'agency-client-portal')); ?>' + textStatus);
        }).always(function() {
            button.prop('disabled', false).text(originalText);
        });
    });
    
    // Copier le lien
    $('#copy-access-link').on('click', function() {
        var input = document.getElementById('access-link-input');
        input.select();
        document.execCommand('copy');
        
        var button = $(this);
        var originalText = button.text();
        button.text('<?php echo esc_js(__('Copied!', 'agency-client-portal')); ?>');
        
        setTimeout(function() {
            button.text(originalText);
        }, 2000);
    });
    
    // Regénérer token
    $('#regenerate-token').on('click', function() {
        if (!confirm('<?php echo esc_js(__('Are you sure you want to regenerate the token? The old access link will become invalid.', 'agency-client-portal')); ?>')) {
            return;
        }
        var btn = $(this);
        btn.prop('disabled', true);
        $.post(agencyAdmin.ajax_url, {
            action: 'agency_regenerate_token',
            client_id: <?php echo intval($client_id); ?>,
            nonce: agencyAdmin.nonce
        }, function(resp) {
            if (resp && resp.success) {
                // Refresh the access link with the new token
                $.post(agencyAdmin.ajax_url, {
                    action: 'agency_get_access_url',
                    client_id: <?php echo intval($client_id); ?>,
                    nonce: agencyAdmin.nonce
                }, function(response) {
                    if (response.success) {
                        $('#access-link-input').val(response.data.access_url);
                    }
                });
            } else {
                alert('<?php echo esc_js(__('Error: ', 'agency-client-portal')); ?>' + (resp.data || '<?php echo esc_js(__('Failed', 'agency-client-portal')); ?>'));
            }
        }).always(function(){
            btn.prop('disabled', false);
        });
    });
    
    // Révoquer token
    $('#revoke-token').on('click', function() {
        if (!confirm('<?php echo esc_js(__('Are you sure you want to revoke access? The client will no longer be able to access their space.', 'agency-client-portal')); ?>')) {
            return;
        }
        var btn = $(this);
        btn.prop('disabled', true);
        $.post(agencyAdmin.ajax_url, {
            action: 'agency_revoke_token',
            client_id: <?php echo intval($client_id); ?>,
            nonce: agencyAdmin.nonce
        }, function(resp) {
            if (resp && resp.success) {
                // Reload the page to refresh the UI
                location.reload();
            } else {
                alert('<?php echo esc_js(__('Error: ', 'agency-client-portal')); ?>' + (resp.data || '<?php echo esc_js(__('Failed', 'agency-client-portal')); ?>'));
            }
        }).always(function(){
            btn.prop('disabled', false);
        });
    });
});
<?php wp_print_inline_script_tag(ob_get_clean()); ?>
