<?php
/**
 * Template – Édition frontend d'un client
 *
 * @package Agency
 */

if (!defined('ABSPATH')) { exit; }

$client_id = intval(get_query_var('agency_edit_client'));
$client    = Agency_Client::get_by_id($client_id);

if (!$client) { wp_die(esc_html__('Client not found.', 'agency-client-portal'), 404); }

$has_token    = Agency_Client::has_active_token($client_id);
$portal_url   = home_url('/client/' . $client->slug . '/');
$dashboard_url = admin_url('admin.php?page=agency-portal');

// Statistiques
$projects       = Agency_Project::get_by_client($client_id);
$projects_count = count($projects);
$blocks_count   = 0;
$comments_count = 0;
foreach ($projects as $project) {
    $blocks = Agency_Block::get_by_project($project->id);
    $blocks_count += count($blocks);
    foreach ($blocks as $block) {
        $comments_count += Agency_Comment::count_by_block($block->id);
    }
}

$logo_url    = !empty($client->logo_url) ? $client->logo_url : '';
$bg_url      = !empty($client->background_image_url) ? $client->background_image_url : '';
$pri_color   = !empty($client->primary_color) ? $client->primary_color : '#000000';
$status      = !empty($client->status) ? $client->status : 'active';
$created_at  = !empty($client->created_at)
    ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($client->created_at))
    : '';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html($client->name); ?> — <?php esc_html_e('Edit client', 'agency-client-portal'); ?></title>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>

<?php // Toast : créé dynamiquement par AgencyUI.toast() (assets/js/ui.js) ?>

<?php
// Variables nécessaires au header et à la sidebar (définies AVANT les includes,
// la sidebar s'auto-masque si $is_manager est vide).
$is_manager      = current_user_can('manage_agency_projects');
$sidebar_context = 'client';
?>

<!-- ===== Main layout (même structure que client-view / project-view) ===== -->
<div class="agency-wrapper">

    <!-- Header partagé en mode "edit" (boutons Aperçu + Enregistrer) -->
    <?php $header_mode = 'edit'; include AGENCY_PLUGIN_DIR . 'templates/partials/header.php'; ?>

    <!-- Sidebar manager (contexte client : liste les projets du client) -->
    <?php include AGENCY_PLUGIN_DIR . 'templates/partials/sidebar.php'; ?>

<main class="agency-main">
<div class="agec-layout">

    <!-- Section : Détails du client -->
    <div class="agec-card">
        <div class="agec-card-title"><?php esc_html_e('Client details', 'agency-client-portal'); ?></div>

        <div class="agec-field">
            <label class="agec-label" for="agec-client-name"><?php esc_html_e('Client name', 'agency-client-portal'); ?> *</label>
            <input type="text" id="agec-client-name" class="agec-input" value="<?php echo esc_attr($client->name); ?>" required>
        </div>

        <div class="agec-field">
            <label class="agec-label"><?php esc_html_e('Slug', 'agency-client-portal'); ?></label>
            <input type="text" id="agec-slug-display" class="agec-input" value="<?php echo esc_attr($client->slug); ?>" readonly>
            <p class="agec-hint"><?php esc_html_e('Generated automatically from the name.', 'agency-client-portal'); ?></p>
        </div>

        <div class="agec-field">
            <label class="agec-label"><?php esc_html_e('Portal URL', 'agency-client-portal'); ?></label>
            <div class="agec-copy-row">
                <input type="text" id="agec-portal-url" class="agec-input" value="<?php echo esc_url($portal_url); ?>" readonly>
                <button type="button" class="agec-copy-btn agec-copy-portal-url"><?php esc_html_e('Copy', 'agency-client-portal'); ?></button>
            </div>
        </div>
    </div>

    <!-- Section : Logo & Couleurs -->
    <div class="agec-card">
        <div class="agec-card-title"><?php esc_html_e('Logo & Colors', 'agency-client-portal'); ?></div>

        <div class="agec-field">
            <label class="agec-label" for="agec-logo-url"><?php esc_html_e('Logo', 'agency-client-portal'); ?></label>
            <div class="agec-media-field">
                <input type="url" id="agec-logo-url" class="agec-input" value="<?php echo esc_url($logo_url); ?>" placeholder="https://…">
                <button type="button" id="agec-logo-btn" class="agec-media-btn"><?php esc_html_e('Choose', 'agency-client-portal'); ?></button>
            </div>
            <img id="agec-logo-preview" class="agec-preview-img" src="<?php echo esc_url($logo_url); ?>" alt="Logo" <?php if (!$logo_url) echo 'style="display:none"'; ?>>
        </div>

        <div class="agec-field">
            <label class="agec-label"><?php esc_html_e('Accent color', 'agency-client-portal'); ?></label>
            <div class="agec-color-field">
                <input type="color" id="agec-primary-color-picker" value="<?php echo esc_attr($pri_color); ?>">
                <input type="text" id="agec-primary-color-text" class="agec-input" value="<?php echo esc_attr($pri_color); ?>" maxlength="7">
            </div>
            <p class="agec-hint"><?php esc_html_e('The client\'s single color — used for buttons, the header, filters and active highlights.', 'agency-client-portal'); ?></p>
        </div>
    </div>

    <!-- Section : Image de fond -->
    <div class="agec-card">
        <div class="agec-card-title"><?php esc_html_e('Background image', 'agency-client-portal'); ?></div>

        <div class="agec-field">
            <label class="agec-label" for="agec-bg-url"><?php esc_html_e('Cover image', 'agency-client-portal'); ?></label>
            <div class="agec-media-field">
                <input type="url" id="agec-bg-url" class="agec-input" value="<?php echo esc_url($bg_url); ?>" placeholder="https://…">
                <button type="button" id="agec-bg-btn" class="agec-media-btn"><?php esc_html_e('Choose', 'agency-client-portal'); ?></button>
            </div>
            <img id="agec-bg-preview" class="agec-bg-preview" src="<?php echo esc_url($bg_url); ?>" alt="<?php esc_attr_e('Background image', 'agency-client-portal'); ?>" <?php if (!$bg_url) echo 'style="display:none"'; ?>>
            <p class="agec-hint"><?php esc_html_e('Displayed behind the client portal header.', 'agency-client-portal'); ?></p>
        </div>
    </div>

    <!-- Section : Accès & Sécurité -->
    <div class="agec-card">
        <div class="agec-card-title"><?php esc_html_e('Access & Security', 'agency-client-portal'); ?></div>

        <!-- Statut -->
        <div class="agec-field">
            <label class="agec-label" for="agec-client-status"><?php esc_html_e('Status', 'agency-client-portal'); ?></label>
            <select id="agec-client-status" class="agec-select">
                <option value="active" <?php selected($status, 'active'); ?>><?php esc_html_e('Active', 'agency-client-portal'); ?></option>
                <option value="inactive" <?php selected($status, 'inactive'); ?>><?php esc_html_e('Inactive', 'agency-client-portal'); ?></option>
            </select>
            <p class="agec-hint"><?php esc_html_e('An inactive client can no longer access their space.', 'agency-client-portal'); ?></p>
        </div>

        <!-- Token -->
        <div class="agec-field">
            <label class="agec-label"><?php esc_html_e('Access link', 'agency-client-portal'); ?></label>

            <div id="agec-token-status" class="agec-token-status <?php echo $has_token ? 'active' : 'inactive'; ?>">
                <?php if ($has_token): ?>
                    <span><?php Agency_Icons::render('check', 14); ?></span>
                    <span class="agec-token-label"><?php esc_html_e('Access token active', 'agency-client-portal'); ?></span>
                    <?php if ($client->token_created_at): ?>
                        <small style="margin-left:auto;font-weight:400;">
                            <?php echo esc_html(sprintf(
                                /* translators: %s: date */
                                __('Generated on %s', 'agency-client-portal'),
                                date_i18n(get_option('date_format'), strtotime($client->token_created_at))
                            )); ?>
                        </small>
                    <?php endif; ?>
                <?php else: ?>
                    <span><?php Agency_Icons::render('alert', 14); ?></span>
                    <span class="agec-token-label"><?php esc_html_e('No active access token', 'agency-client-portal'); ?></span>
                <?php endif; ?>
            </div>

            <div class="agec-token-actions">
                <button type="button" id="agec-generate-token" class="agec-btn agec-btn-secondary" <?php echo $has_token ? 'style="display:none"' : ''; ?>>
                    <?php esc_html_e('Generate an access link', 'agency-client-portal'); ?>
                </button>
                <button type="button" id="agec-regenerate-token" class="agec-btn agec-btn-secondary" <?php echo !$has_token ? 'style="display:none"' : ''; ?>>
                    <?php esc_html_e('Regenerate token', 'agency-client-portal'); ?>
                </button>
                <button type="button" id="agec-revoke-token" class="agec-btn agec-btn-danger" <?php echo !$has_token ? 'style="display:none"' : ''; ?>>
                    <?php esc_html_e('Revoke access', 'agency-client-portal'); ?>
                </button>
            </div>

            <div id="agec-access-link-wrap" <?php echo !$has_token ? 'style="display:none"' : ''; ?>>
                <div class="agec-copy-row">
                    <input type="text" id="agec-access-link-input" class="agec-input" readonly placeholder="<?php esc_attr_e('Loading…', 'agency-client-portal'); ?>">
                    <button type="button" id="agec-copy-access-link" class="agec-copy-btn"><?php esc_html_e('Copy', 'agency-client-portal'); ?></button>
                </div>
                <p class="agec-hint"><strong><?php esc_html_e('⚠️ Never share this link publicly!', 'agency-client-portal'); ?></strong> <?php esc_html_e('Share it only with the client concerned.', 'agency-client-portal'); ?></p>
            </div>
        </div>

        <?php if ($created_at): ?>
        <div class="agec-field">
            <label class="agec-label"><?php esc_html_e('Creation date', 'agency-client-portal'); ?></label>
            <input type="text" class="agec-input" value="<?php echo esc_attr($created_at); ?>" readonly>
        </div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="agec-field">
            <label class="agec-label"><?php esc_html_e('Statistics', 'agency-client-portal'); ?></label>
            <table class="agec-stats-table">
                <tbody>
                    <tr>
                        <td><?php esc_html_e('Projects', 'agency-client-portal'); ?></td>
                        <td><?php echo esc_html($projects_count); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Blocks', 'agency-client-portal'); ?></td>
                        <td><?php echo esc_html($blocks_count); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Comments', 'agency-client-portal'); ?></td>
                        <td><?php echo esc_html($comments_count); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Section : Zone Danger -->
    <div class="agec-card agec-danger-card">
        <div class="agec-card-title"><?php esc_html_e('Danger Zone', 'agency-client-portal'); ?></div>
        <p style="font-size:13px;color:#555;margin:0 0 16px;">
            <?php esc_html_e('This action cannot be undone. It permanently deletes the client and all of its projects, blocks, comments and associated files.', 'agency-client-portal'); ?>
        </p>
        <button type="button" id="agec-delete-btn" class="agec-btn agec-btn-danger">
            <?php esc_html_e('Permanently delete the client', 'agency-client-portal'); ?>
        </button>
    </div>

</div><!-- .agec-layout -->
</main><!-- .agency-main -->
</div><!-- .agency-wrapper -->

<?php wp_footer(); ?>
</body>
</html>
