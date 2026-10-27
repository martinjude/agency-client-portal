<?php
/**
 * Template – Édition frontend d'un client
 *
 * @package Agency
 */

if (!defined('ABSPATH')) { exit; }

$agency_client_id = intval(get_query_var('agency_edit_client'));
$agency_client    = Agency_Client::get_by_id($agency_client_id);

if (!$agency_client) { wp_die(esc_html__('Client not found.', 'agency-client-portal'), 404); }

$agency_has_token    = Agency_Client::has_active_token($agency_client_id);
$agency_portal_url   = home_url('/client/' . $agency_client->slug . '/');
$agency_dashboard_url = home_url('/agency/dashboard/');

// Statistiques (requêtes groupées)
$agency_projects       = Agency_Project::get_by_client($agency_client_id);
$agency_projects_count = count($agency_projects);
$agency_stat_blocks    = Agency_Block::get_nav_by_projects(wp_list_pluck($agency_projects, 'id'));
$agency_block_ids      = array();
foreach ($agency_stat_blocks as $agency_list) {
    $agency_block_ids = array_merge($agency_block_ids, wp_list_pluck($agency_list, 'id'));
}
$agency_blocks_count   = count($agency_block_ids);
$agency_comments_count = array_sum(Agency_Comment::count_by_blocks($agency_block_ids));

$agency_logo_url    = !empty($agency_client->logo_url) ? $agency_client->logo_url : '';
$agency_bg_url      = !empty($agency_client->background_image_url) ? $agency_client->background_image_url : '';
$agency_pri_color   = Agency_Template::client_color($agency_client);
$agency_status      = !empty($agency_client->status) ? $agency_client->status : 'active';
$agency_is_active   = $agency_status === 'active';
$agency_created_at  = !empty($agency_client->created_at)
    ? date_i18n(get_option('date_format') . ' · ' . get_option('time_format'), strtotime($agency_client->created_at))
    : '';
$agency_updated_at  = !empty($agency_client->updated_at) ? date_i18n(get_option('date_format'), strtotime($agency_client->updated_at)) : '';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html($agency_client->name); ?> — <?php esc_html_e('Edit client', 'agency-client-portal'); ?></title>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>

<div class="agency-wrapper">
    <?php include AGENCY_PLUGIN_DIR . 'templates/partials/sidebar.php'; ?>

<main class="agency-main">
<div class="agency-container agec-layout">

    <?php Agency_Template::crumbs(array(
        array(__('Clients', 'agency-client-portal'), $agency_dashboard_url),
        array($agency_client->name, ''),
    )); ?>

    <div class="agency-page-head">
        <div class="agency-page-head__main">
            <span class="agency-avatar agency-avatar--48 agec-avatar" style="background:<?php echo esc_attr($agency_pri_color); ?>">
                <?php if ($agency_logo_url): ?>
                    <img src="<?php echo esc_url($agency_logo_url); ?>" alt="" data-initial="<?php echo esc_attr(Agency_Template::initial($agency_client->name)); ?>" onerror="this.parentNode.textContent=this.dataset.initial">
                <?php else: ?>
                    <?php echo esc_html(Agency_Template::initial($agency_client->name)); ?>
                <?php endif; ?>
            </span>
            <div class="agency-page-head__text">
                <div class="agency-page-head__title-row">
                    <h1 class="agency-page-title agency-page-title--sm" id="agec-title"><?php echo esc_html($agency_client->name); ?></h1>
                    <span class="agency-pill <?php echo $agency_is_active ? 'agency-pill--ok' : 'agency-pill--off'; ?>" id="agec-status-pill"><?php echo $agency_is_active ? esc_html__('Active', 'agency-client-portal') : esc_html__('Inactive', 'agency-client-portal'); ?></span>
                </div>
                <div class="agency-page-meta">
                    <?php
                    echo esc_html($agency_client->slug . ($agency_updated_at ? ' · ' . sprintf(/* translators: %s: date */ __('modified %s', 'agency-client-portal'), $agency_updated_at) : ''));
                    ?>
                </div>
            </div>
        </div>
        <div class="agency-page-head__actions">
            <a href="<?php echo esc_url($agency_portal_url); ?>" class="agency-btn"><?php Agency_Icons::render_ui('external', 16); ?> <?php esc_html_e('Portal preview', 'agency-client-portal'); ?></a>
            <?php if ($agency_has_token): ?>
            <button type="button" class="agency-btn agency-copy-access" data-client-id="<?php echo esc_attr($agency_client_id); ?>"><?php Agency_Icons::render('copy', 16); ?> <?php esc_html_e('Copy portal link', 'agency-client-portal'); ?></button>
            <?php endif; ?>
        </div>
    </div>

    <div class="agec-grid">
        <div class="agec-col">

            <section class="agency-panel agec-card">
                <h2 class="agency-panel__head"><?php esc_html_e('Client details', 'agency-client-portal'); ?></h2>
                <div class="agency-panel__body">
                    <div class="agency-form-group">
                        <label class="agency-field-label-mono" for="agec-client-name"><?php esc_html_e('Client name', 'agency-client-portal'); ?> *</label>
                        <input type="text" id="agec-client-name" class="agency-form-input" value="<?php echo esc_attr($agency_client->name); ?>" required>
                    </div>
                    <div class="agency-form-group">
                        <label class="agency-field-label-mono" for="agec-slug-display"><?php esc_html_e('Slug', 'agency-client-portal'); ?></label>
                        <input type="text" id="agec-slug-display" class="agency-form-input" value="<?php echo esc_attr($agency_client->slug); ?>" readonly>
                        <p class="agency-form-hint"><?php esc_html_e('Generated automatically from the name.', 'agency-client-portal'); ?></p>
                    </div>
                    <div class="agency-form-group">
                        <label class="agency-field-label-mono" for="agec-portal-url"><?php esc_html_e('Portal URL', 'agency-client-portal'); ?></label>
                        <div class="agency-form-inline">
                            <input type="text" id="agec-portal-url" class="agency-form-input" value="<?php echo esc_url($agency_portal_url); ?>" readonly>
                            <button type="button" class="agency-btn agec-copy-portal-url"><?php Agency_Icons::render('copy', 16); ?> <?php esc_html_e('Copy', 'agency-client-portal'); ?></button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="agency-panel agec-card">
                <h2 class="agency-panel__head"><?php esc_html_e('Logo & color', 'agency-client-portal'); ?></h2>
                <div class="agency-panel__body">
                    <div class="agency-form-group">
                        <label class="agency-field-label-mono" for="agec-logo-url"><?php esc_html_e('Logo', 'agency-client-portal'); ?></label>
                        <div class="agency-form-inline">
                            <input type="url" id="agec-logo-url" class="agency-form-input" value="<?php echo esc_url($agency_logo_url); ?>" placeholder="https://…">
                            <button type="button" id="agec-logo-btn" class="agency-btn"><?php Agency_Icons::render('image', 16); ?> <?php esc_html_e('Choose', 'agency-client-portal'); ?></button>
                        </div>
                        <img id="agec-logo-preview" class="agency-logo-preview" src="<?php echo esc_url($agency_logo_url); ?>" alt="" <?php if (!$agency_logo_url) echo 'style="display:none"'; ?> onerror="this.style.display='none'">
                    </div>
                    <div class="agency-form-group">
                        <label class="agency-field-label-mono" for="agec-primary-color-text"><?php esc_html_e('Accent color', 'agency-client-portal'); ?></label>
                        <?php
                        $agency_accent_field_id    = 'agec-primary-color';
                        $agency_accent_field_value = $agency_pri_color;
                        include AGENCY_PLUGIN_DIR . 'templates/partials/accent-field.php';
                        ?>
                        <p class="agency-form-hint"><?php esc_html_e('The client\'s single color — used for primary buttons, the avatar, filters and active highlights.', 'agency-client-portal'); ?></p>
                    </div>
                </div>
            </section>

            <section class="agency-panel agec-card">
                <h2 class="agency-panel__head"><?php esc_html_e('Background image', 'agency-client-portal'); ?></h2>
                <div class="agency-panel__body">
                    <div class="agency-form-group">
                        <label class="agency-field-label-mono" for="agec-bg-url"><?php esc_html_e('Cover image', 'agency-client-portal'); ?></label>
                        <div class="agency-form-inline">
                            <input type="url" id="agec-bg-url" class="agency-form-input" value="<?php echo esc_url($agency_bg_url); ?>" placeholder="https://…">
                            <button type="button" id="agec-bg-btn" class="agency-btn"><?php Agency_Icons::render('image', 16); ?> <?php esc_html_e('Choose', 'agency-client-portal'); ?></button>
                        </div>
                        <div class="agec-bg-frame<?php echo $agency_bg_url ? ' has-image' : ''; ?>">
                            <img id="agec-bg-preview" src="<?php echo esc_url($agency_bg_url); ?>" alt="" <?php if (!$agency_bg_url) echo 'style="display:none"'; ?> onerror="this.style.display='none'">
                            <span class="agec-bg-empty"><?php Agency_Icons::render('image', 18); ?></span>
                        </div>
                        <p class="agency-form-hint"><?php esc_html_e('Kept with the client; used as a cover in the portal.', 'agency-client-portal'); ?></p>
                    </div>
                </div>
            </section>

        </div>

        <div class="agec-col agec-col--side">

            <section class="agency-panel agec-card" id="agec-access">
                <h2 class="agency-panel__head"><?php esc_html_e('Access & security', 'agency-client-portal'); ?></h2>
                <div class="agency-panel__body">
                    <div class="agency-form-group agec-divided">
                        <span class="agency-field-label-mono"><?php esc_html_e('Status', 'agency-client-portal'); ?></span>
                        <label class="agec-switch">
                            <span class="agency-toggle"><input type="checkbox" id="agec-status-toggle" <?php checked($agency_is_active); ?>><span></span></span>
                            <span id="agec-status-label"><?php echo $agency_is_active ? esc_html__('Active', 'agency-client-portal') : esc_html__('Inactive', 'agency-client-portal'); ?></span>
                        </label>
                        <input type="hidden" id="agec-client-status" value="<?php echo esc_attr($agency_status); ?>">
                        <p class="agency-form-hint"><?php esc_html_e('An inactive client can no longer access their space.', 'agency-client-portal'); ?></p>
                    </div>

                    <div class="agency-form-group">
                        <span class="agency-field-label-mono"><?php esc_html_e('Access link', 'agency-client-portal'); ?></span>
                        <div id="agec-token-status" class="agency-alert agec-token-status <?php echo $agency_has_token ? 'agency-alert--ok active' : 'agency-alert--warn inactive'; ?>">
                            <?php Agency_Icons::render($agency_has_token ? 'check' : 'alert', 14); ?>
                            <span class="agec-token-label"><?php echo $agency_has_token ? esc_html__('Access token active', 'agency-client-portal') : esc_html__('No active access token', 'agency-client-portal'); ?></span>
                            <?php if ($agency_has_token && $agency_client->token_created_at): ?>
                                <span class="agec-token-date"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($agency_client->token_created_at))); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="agec-token-actions">
                            <button type="button" id="agec-generate-token" class="agency-btn" <?php echo $agency_has_token ? 'style="display:none"' : ''; ?>><?php esc_html_e('Generate an access link', 'agency-client-portal'); ?></button>
                            <button type="button" id="agec-regenerate-token" class="agency-btn" <?php echo !$agency_has_token ? 'style="display:none"' : ''; ?>><?php esc_html_e('Regenerate token', 'agency-client-portal'); ?></button>
                            <button type="button" id="agec-revoke-token" class="agency-btn agency-btn-danger" <?php echo !$agency_has_token ? 'style="display:none"' : ''; ?>><?php esc_html_e('Revoke access', 'agency-client-portal'); ?></button>
                        </div>

                        <div id="agec-access-link-wrap" <?php echo !$agency_has_token ? 'style="display:none"' : ''; ?>>
                            <div class="agency-form-inline">
                                <input type="text" id="agec-access-link-input" class="agency-form-input agec-mono" readonly placeholder="<?php esc_attr_e('Loading…', 'agency-client-portal'); ?>">
                                <button type="button" id="agec-copy-access-link" class="agency-btn-icon" aria-label="<?php esc_attr_e('Copy', 'agency-client-portal'); ?>"><?php Agency_Icons::render('copy', 14); ?></button>
                            </div>
                            <div class="agency-alert agency-alert--warn agec-warning">
                                <?php Agency_Icons::render('lock', 14); ?>
                                <span><strong><?php esc_html_e('Never share this link publicly.', 'agency-client-portal'); ?></strong><br><?php esc_html_e('Share it only with the client concerned.', 'agency-client-portal'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="agency-panel agec-card">
                <h2 class="agency-panel__head"><?php esc_html_e('Statistics', 'agency-client-portal'); ?></h2>
                <div class="agency-panel__body">
                    <div class="agec-stats">
                        <div class="agec-stat"><strong><?php echo esc_html($agency_projects_count); ?></strong><span><?php esc_html_e('Projects', 'agency-client-portal'); ?></span></div>
                        <div class="agec-stat"><strong><?php echo esc_html($agency_blocks_count); ?></strong><span><?php esc_html_e('Blocks', 'agency-client-portal'); ?></span></div>
                        <div class="agec-stat"><strong><?php echo esc_html($agency_comments_count); ?></strong><span><?php esc_html_e('Comments', 'agency-client-portal'); ?></span></div>
                    </div>
                    <?php if ($agency_created_at): ?>
                    <div class="agec-created"><span><?php esc_html_e('Created on', 'agency-client-portal'); ?></span><span><?php echo esc_html($agency_created_at); ?></span></div>
                    <?php endif; ?>
                </div>
            </section>

        </div>
    </div>

    <section class="agec-danger">
        <span class="agency-field-label-mono"><?php esc_html_e('Danger zone', 'agency-client-portal'); ?></span>
        <p><?php esc_html_e('This action cannot be undone. It permanently deletes the client and all of its projects, blocks, comments and associated files.', 'agency-client-portal'); ?></p>
        <button type="button" id="agec-delete-btn" class="agency-btn agency-btn-danger agency-btn-sm"><?php Agency_Icons::render('trash', 14); ?> <?php esc_html_e('Permanently delete the client', 'agency-client-portal'); ?></button>
    </section>

</div>
</main>

<div class="agec-savebar" id="agec-savebar">
    <div class="agec-savebar__inner">
        <span class="agec-savebar__state" id="agec-dirty-state"><span class="agec-savebar__dot"></span><span><?php esc_html_e('Unsaved changes', 'agency-client-portal'); ?></span></span>
        <button type="button" id="agec-cancel-btn" class="agency-btn"><?php esc_html_e('Cancel', 'agency-client-portal'); ?></button>
        <button type="button" id="agec-save-btn" class="agency-btn agency-btn-primary"><?php esc_html_e('Save changes', 'agency-client-portal'); ?></button>
    </div>
</div>
</div><!-- .agency-wrapper -->

<?php wp_footer(); ?>
</body>
</html>
