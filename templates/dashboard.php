<?php
/**
 * Template : Tableau de bord frontend Agency (agency/dashboard)
 *
 * Accessible uniquement aux utilisateurs connectés avec la capacité manage_agency_clients.
 *
 * @package Agency
 */
if (!defined('ABSPATH')) { exit; }

global $wpdb;

// Clients actifs et inactifs (les supprimés sont exclus)
$all_clients = Agency_Client::get_all_listable();
$total_clients = count($all_clients);

// Comptage de projets par client en une seule requête
$project_counts = array();
if (!empty($all_clients)) {
    $client_ids = array_map(function ($c) { return intval($c->id); }, $all_clients);
    $ids_placeholder = implode(',', array_fill(0, count($client_ids), '%d'));
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $ids_placeholder ne contient que des %d.
    $counts = $wpdb->get_results($wpdb->prepare(
        "SELECT client_id, COUNT(*) as cnt
         FROM {$wpdb->prefix}agency_projects
         WHERE client_id IN ($ids_placeholder) AND status = 'active'
         GROUP BY client_id",
        $client_ids
    ));
    foreach ($counts as $row) {
        $project_counts[intval($row->client_id)] = intval($row->cnt);
    }
}

$dashboard_url  = home_url('/agency/dashboard/');
$client         = null; // Pas de client sélectionné sur le dashboard
$total_projects = array_sum($project_counts);

// Salutation contextuelle
$_current_user = wp_get_current_user();
$_first_name   = !empty($_current_user->first_name) ? $_current_user->first_name : $_current_user->display_name;
$_hour         = intval(wp_date('G')); // 0–23
if ($_hour < 12) {
    $_salut = __('Good morning', 'agency-client-portal');
} elseif ($_hour < 18) {
    $_salut = __('Good afternoon', 'agency-client-portal');
} else {
    $_salut = __('Good evening', 'agency-client-portal');
}
// Sous-message : une variation par jour de la semaine
$_day_messages = array(
    0 => __('Have a great week! It all starts now.', 'agency-client-portal'),
    1 => __('A new week, new projects.', 'agency-client-portal'),
    2 => __('Tuesday is a day for creating.', 'agency-client-portal'),
    3 => __('Wednesday already! The week is moving.', 'agency-client-portal'),
    4 => __('Thursday — a few more sprints before the weekend.', 'agency-client-portal'),
    5 => __('Friday! Finish the week in style.', 'agency-client-portal'),
    6 => __('Have a great weekend! Get some rest.', 'agency-client-portal'),
);
$_day_sub = $_day_messages[intval(wp_date('w'))];
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php esc_html_e('Dashboard', 'agency-client-portal'); ?> — <?php echo esc_html(get_bloginfo('name')); ?></title>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>

<?php // Toast : créé dynamiquement par AgencyUI.toast() (assets/js/ui.js) ?>

<div class="agency-wrapper">
<?php include AGENCY_PLUGIN_DIR . 'templates/partials/sidebar.php'; ?>
<main class="agency-main">
<div class="agency-container">

    <div class="agency-page-head">
        <div class="agency-page-head__text">
            <h1 class="agency-page-title">
                <?php /* translators: 1: greeting, 2: user first name */ echo esc_html(sprintf(__('%1$s, %2$s!', 'agency-client-portal'), $_salut, $_first_name)); ?>
            </h1>
            <div class="agency-page-meta">
                <?php
                echo esc_html(
                    $_day_sub
                    . ' · '
                    /* translators: %d: number of clients */
                    . sprintf(_n('%d client', '%d clients', $total_clients, 'agency-client-portal'), $total_clients)
                    . ' · '
                    /* translators: %d: number of projects */
                    . sprintf(_n('%d project', '%d projects', $total_projects, 'agency-client-portal'), $total_projects)
                );
                ?>
            </div>
        </div>
    </div>
    <div id="agd-api-message" class="agency-alert agency-alert--info agency-mb-20" style="display:none;"></div>

    <div class="agency-toolbar">
        <label class="agency-search">
            <?php Agency_Icons::render_ui('search', 16); ?>
            <input type="search" id="agd-client-search" placeholder="<?php esc_attr_e('Search a client', 'agency-client-portal'); ?>" autocomplete="off">
        </label>
        <div class="agency-dropdown">
            <button type="button" class="agency-btn" data-agency-dropdown aria-haspopup="true" aria-expanded="false">
                <?php Agency_Icons::render_ui('filter', 16); ?> <?php esc_html_e('Filter', 'agency-client-portal'); ?>
            </button>
            <div class="agency-menu" id="agd-status-filter">
                <button type="button" class="agency-label-filter active" data-status="all"><?php esc_html_e('All', 'agency-client-portal'); ?></button>
                <button type="button" class="agency-label-filter" data-status="active"><?php esc_html_e('Active', 'agency-client-portal'); ?></button>
                <button type="button" class="agency-label-filter" data-status="inactive"><?php esc_html_e('Inactive', 'agency-client-portal'); ?></button>
            </div>
        </div>
        <div class="agency-seg" data-agency-view-toggle="agd-clients-grid" role="group" aria-label="<?php esc_attr_e('Display', 'agency-client-portal'); ?>">
            <button type="button" data-view="grid" class="is-on" aria-label="<?php esc_attr_e('Grid', 'agency-client-portal'); ?>"><?php Agency_Icons::render_ui('grid', 16); ?></button>
            <button type="button" data-view="list" aria-label="<?php esc_attr_e('List', 'agency-client-portal'); ?>"><?php Agency_Icons::render_ui('list', 16); ?></button>
        </div>
        <button type="button" id="agd-new-client-btn" class="agency-btn agency-btn-primary">
            <?php Agency_Icons::render_ui('plus', 16); ?> <?php esc_html_e('New client', 'agency-client-portal'); ?>
        </button>
    </div>

    <h2 class="agency-sec-h">
        <?php esc_html_e('Clients', 'agency-client-portal'); ?>
        <span class="agency-badge"><?php echo esc_html($total_clients); ?></span>
    </h2>

    <?php if (empty($all_clients)): ?>
    <div class="agency-empty">
        <span class="agency-page-tile"><?php Agency_Icons::render_glyph('folder', 22); ?></span>
        <p class="agency-empty__title"><?php esc_html_e('No clients yet.', 'agency-client-portal'); ?></p>
        <p class="agency-empty__text"><?php esc_html_e('Create a client to get a private portal you can share with a single link.', 'agency-client-portal'); ?></p>
        <button type="button" id="agd-new-client-cta" class="agency-btn agency-btn-primary">
            <?php Agency_Icons::render_ui('plus', 16); ?> <?php esc_html_e('Create your first client', 'agency-client-portal'); ?>
        </button>
    </div>
    <?php else: ?>
    <div class="agency-card-grid agency-client-grid" id="agd-clients-grid">
        <?php foreach ($all_clients as $client_row):
            $nb_projects = isset($project_counts[intval($client_row->id)]) ? $project_counts[intval($client_row->id)] : 0;
            $has_token   = !empty($client_row->token_hash) && !empty($client_row->token_active);
            $logo_url    = !empty($client_row->logo_url) ? $client_row->logo_url : '';
            $initial     = mb_strtoupper(mb_substr(trim($client_row->name), 0, 1));
            $accent      = sanitize_hex_color($client_row->primary_color) ?: '#7C83FD';
            $edit_url    = home_url('/agency/edit-client/' . intval($client_row->id) . '/');
            $portal_url  = home_url('/client/' . $client_row->slug . '/');
            $is_active   = ($client_row->status === 'active');
        ?>
        <div class="agency-client-card<?php echo $is_active ? '' : ' is-inactive'; ?>"
             data-client-id="<?php echo esc_attr($client_row->id); ?>"
             data-client-name="<?php echo esc_attr($client_row->name); ?>"
             data-client-status="<?php echo $is_active ? 'active' : 'inactive'; ?>">
            <a href="<?php echo esc_url($portal_url); ?>" class="agency-card__link" aria-label="<?php echo esc_attr($client_row->name); ?>"></a>
            <div class="agency-client-card__top">
                <span class="agency-avatar agency-avatar--40" style="background:<?php echo esc_attr($accent); ?>">
                    <?php if ($logo_url): ?>
                        <img src="<?php echo esc_url($logo_url); ?>" alt="" data-initial="<?php echo esc_attr($initial); ?>" onerror="this.parentNode.textContent=this.dataset.initial">
                    <?php else: ?>
                        <?php echo esc_html($initial); ?>
                    <?php endif; ?>
                </span>
                <div class="agency-client-card__who">
                    <div class="agency-client-card__name" title="<?php echo esc_attr($client_row->name); ?>"><?php echo esc_html($client_row->name); ?></div>
                    <div class="agency-client-card__meta">
                        <?php echo esc_html(sprintf(
                            /* translators: %d: number of projects */ _n('%d project', '%d projects', $nb_projects, 'agency-client-portal'),
                            $nb_projects
                        )); ?>
                    </div>
                </div>
                <span class="agency-pill <?php echo $is_active ? 'agency-pill--ok' : 'agency-pill--off'; ?>">
                    <?php echo $is_active ? esc_html__('Active', 'agency-client-portal') : esc_html__('Inactive', 'agency-client-portal'); ?>
                </span>
            </div>
            <div class="agency-client-card__btns">
                <a href="<?php echo esc_url($edit_url); ?>" class="agency-btn agency-btn-sm"><?php esc_html_e('Edit', 'agency-client-portal'); ?></a>
                <a href="<?php echo esc_url($portal_url); ?>" class="agency-btn agency-btn-sm"><?php esc_html_e('Projects', 'agency-client-portal'); ?></a>
                <?php if ($has_token): ?>
                <button type="button" class="agency-btn-icon agency-btn-icon--sm agency-copy-access" data-client-id="<?php echo esc_attr($client_row->id); ?>" title="<?php esc_attr_e('Copy portal link', 'agency-client-portal'); ?>" aria-label="<?php esc_attr_e('Copy portal link', 'agency-client-portal'); ?>">
                    <?php Agency_Icons::render('copy', 14); ?>
                </button>
                <?php endif; ?>
                <a href="<?php echo esc_url($edit_url . '#agec-access'); ?>" class="agency-btn-icon agency-btn-icon--sm" title="<?php esc_attr_e('Access key', 'agency-client-portal'); ?>" aria-label="<?php esc_attr_e('Access key', 'agency-client-portal'); ?>">
                    <?php Agency_Icons::render('key', 14); ?>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <p class="agency-empty-state" id="agd-no-results" hidden><?php esc_html_e('No client matches your search.', 'agency-client-portal'); ?></p>
    <?php endif; ?>

</div>
</main>
</div><!-- /.agency-wrapper -->

<!-- ===== Modal : Nouveau client (planche 02) ===== -->
<div class="agency-modal" id="agd-new-client-modal" role="dialog" aria-modal="true" aria-labelledby="agd-modal-title">
    <div class="agency-modal-content">

        <div class="agency-modal-header">
            <h3 class="agency-modal-title" id="agd-modal-title"><?php esc_html_e('New client', 'agency-client-portal'); ?></h3>
            <button class="agency-modal-close" type="button" id="agd-modal-close" aria-label="<?php esc_attr_e('Close', 'agency-client-portal'); ?>">&times;</button>
        </div>

        <div class="agency-modal-body">
            <div class="agency-form-group">
                <label class="agency-form-label" for="agd-client-name"><?php esc_html_e('Client name', 'agency-client-portal'); ?> *</label>
                <input type="text" id="agd-client-name" class="agency-form-input" required placeholder="<?php esc_attr_e('e.g. ABC Company', 'agency-client-portal'); ?>">
            </div>

            <div class="agency-form-group">
                <label class="agency-form-label" for="agd-logo-url"><?php esc_html_e('Logo', 'agency-client-portal'); ?></label>
                <div class="agency-form-inline">
                    <input type="url" id="agd-logo-url" class="agency-form-input" placeholder="https://…">
                    <button type="button" id="agd-logo-btn" class="agency-btn"><?php esc_html_e('Choose', 'agency-client-portal'); ?></button>
                </div>
                <p class="agency-form-hint"><?php esc_html_e('Shown in the sidebar and in the portal.', 'agency-client-portal'); ?></p>
                <img id="agd-logo-preview" src="" alt="" class="agency-logo-preview" style="display:none;" onerror="this.style.display='none'">
            </div>

            <div class="agency-form-group">
                <label class="agency-form-label" for="agd-primary-color-text"><?php esc_html_e('Accent color', 'agency-client-portal'); ?></label>
                <?php
                $agency_accent_field_id     = 'agd-primary-color';
                $agency_accent_field_value  = '#7C83FD';
                include AGENCY_PLUGIN_DIR . 'templates/partials/accent-field.php';
                ?>
            </div>

            <div id="agd-modal-error" class="agency-alert agency-alert--err" style="display:none;"></div>
        </div>

        <div class="agency-modal-footer">
            <button type="button" id="agd-modal-cancel" class="agency-btn agency-btn-ghost"><?php esc_html_e('Cancel', 'agency-client-portal'); ?></button>
            <button type="button" id="agd-modal-save" class="agency-btn agency-btn-primary"><?php esc_html_e('Create client', 'agency-client-portal'); ?></button>
        </div>

    </div>
</div>

<?php wp_footer(); ?>
</body>
</html>
