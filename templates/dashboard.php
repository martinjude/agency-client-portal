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

// Récupérer tous les clients actifs
$all_clients = Agency_Client::get_all();
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

$dashboard_url = home_url('/agency/dashboard/');
$header_mode   = 'dashboard';
$client        = null; // Pas de client sélectionné sur le dashboard

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
    0 => __('Have a great week! It all starts now. 💪', 'agency-client-portal'),
    1 => __('A new week, new projects.', 'agency-client-portal'),
    2 => __('Tuesday is a day for creating.', 'agency-client-portal'),
    3 => __('Wednesday already! The week is moving.', 'agency-client-portal'),
    4 => __('Thursday — a few more sprints before the weekend.', 'agency-client-portal'),
    5 => __('Friday! Finish the week in style. ✨', 'agency-client-portal'),
    6 => __('Have a great weekend! Get some rest. 🎉', 'agency-client-portal'),
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

<?php include AGENCY_PLUGIN_DIR . 'templates/partials/header.php'; ?>

<div class="agency-header-spacer agd-welcome">
    <h1 class="agd-welcome-title">
        <?php /* translators: 1: greeting, 2: user first name */ echo esc_html(sprintf(__('%1$s, %2$s!', 'agency-client-portal'), $_salut, $_first_name)); ?>
    </h1>
    <p class="agd-welcome-sub"><?php echo esc_html($_day_sub); ?></p>
    <div id="agd-api-message" class="agd-api-message" style="display:none;"></div>
</div>

<div class="agency-wrapper">
<main class="agency-main">
<div class="agd-layout">

    <!-- Barre d'actions -->
    <div class="agd-actions-bar">
        <button type="button" id="agd-new-client-btn" class="agency-btn agency-btn-primary">
            <?php esc_html_e('+ New client', 'agency-client-portal'); ?>
        </button>
    </div>

    <!-- En-tête de section -->
    <div class="agd-section-header">
        <h2 class="agd-section-title">
            <?php
            echo esc_html(sprintf(
                /* translators: %d: number of clients */ _n('%d client', '%d clients', $total_clients, 'agency-client-portal'),
                $total_clients
            ));
            ?>
        </h2>
    </div>

    <?php if (empty($all_clients)): ?>
    <!-- État vide -->
    <div class="agd-empty-state">
        <span class="agd-empty-icon"><?php Agency_Icons::render('users', 48); ?></span>
        <p><?php esc_html_e('No clients yet.', 'agency-client-portal'); ?></p>
        <button type="button" id="agd-new-client-cta" class="agency-btn agency-btn-primary">
            <?php esc_html_e('+ Create your first client', 'agency-client-portal'); ?>
        </button>
    </div>
    <?php else: ?>
    <!-- Grille des clients -->
    <div class="agd-clients-grid" id="agd-clients-grid">
        <?php foreach ($all_clients as $client_row):
            $nb_projects = isset($project_counts[intval($client_row->id)]) ? $project_counts[intval($client_row->id)] : 0;
            $has_token   = !empty($client_row->token_hash) && !empty($client_row->token_active);
            $logo_url    = !empty($client_row->logo_url) ? $client_row->logo_url : '';
            $initials    = mb_strtoupper(mb_substr(trim($client_row->name), 0, 2));
            $accent      = !empty($client_row->primary_color) ? $client_row->primary_color : '#3b82f6';
            $edit_url    = home_url('/agency/edit-client/' . intval($client_row->id) . '/');
            $portal_url  = home_url('/client/' . $client_row->slug . '/');
            $is_active   = ($client_row->status === 'active');
        ?>
        <div class="agd-client-card" data-client-id="<?php echo esc_attr($client_row->id); ?>">

            <!-- Avatar + infos + badges -->
            <div class="agd-client-card-top">

                <!-- Avatar : logo ou initiales -->
                <div class="agd-client-avatar"
                     <?php if ($logo_url): ?>
                     style="background-image:url('<?php echo esc_url($logo_url); ?>')"
                     <?php else: ?>
                     style="background-color:<?php echo esc_attr($accent); ?>"
                     <?php endif; ?>>
                    <?php if (!$logo_url): ?><?php echo esc_html($initials); ?><?php endif; ?>
                </div>

                <!-- Nom + compteur projets -->
                <div class="agd-client-info">
                    <div class="agd-client-name" title="<?php echo esc_attr($client_row->name); ?>">
                        <?php echo esc_html($client_row->name); ?>
                    </div>
                    <div class="agd-client-meta">
                        <?php echo esc_html(sprintf(
                            /* translators: %d: number of projects */ _n('%d project', '%d projects', $nb_projects, 'agency-client-portal'),
                            $nb_projects
                        )); ?>
                    </div>
                </div>

                <!-- Badges statut + token -->
                <div class="agd-badges">
                    <span class="agd-badge <?php echo $is_active ? 'agd-badge-active' : 'agd-badge-inactive'; ?>">
                        <?php echo $is_active ? esc_html__('Active', 'agency-client-portal') : esc_html__('Inactive', 'agency-client-portal'); ?>
                    </span>
                    <?php if ($has_token): ?>
                    <span class="agd-badge agd-badge-token" title="<?php esc_attr_e('Access link active', 'agency-client-portal'); ?>">
                        <?php Agency_Icons::render('key', 13); ?>
                    </span>
                    <?php endif; ?>
                </div>

            </div><!-- /.agd-client-card-top -->

            <!-- Boutons d'action -->
            <div class="agd-client-card-actions">
                <a href="<?php echo esc_url($edit_url); ?>"
                   class="agency-btn agency-btn-secondary agd-btn-sm">
                    <?php esc_html_e('Edit', 'agency-client-portal'); ?>
                </a>
                <a href="<?php echo esc_url($portal_url); ?>"
                   class="agency-btn agency-btn-secondary agd-btn-sm">
                    <?php esc_html_e('Projects', 'agency-client-portal'); ?>
                </a>
                <?php if ($has_token): ?>
                <button type="button"
                        class="agency-btn agency-btn-secondary agd-btn-sm agd-copy-link"
                        data-client-id="<?php echo esc_attr($client_row->id); ?>">
                    <?php esc_html_e('Copy link', 'agency-client-portal'); ?>
                </button>
                <?php endif; ?>
            </div>

        </div><!-- /.agd-client-card -->
        <?php endforeach; ?>
    </div><!-- /.agd-clients-grid -->
    <?php endif; ?>

</div><!-- /.agd-layout -->
</main>
</div><!-- /.agency-wrapper -->

<!-- ===== Modal : Nouveau client ===== -->
<div class="agency-modal" id="agd-new-client-modal" role="dialog" aria-modal="true" aria-labelledby="agd-modal-title">
    <div class="agency-modal-content">

        <div class="agency-modal-header">
            <h3 class="agency-modal-title" id="agd-modal-title">
                <?php esc_html_e('New client', 'agency-client-portal'); ?>
            </h3>
            <button class="agency-modal-close" type="button" id="agd-modal-close" aria-label="<?php esc_attr_e('Close', 'agency-client-portal'); ?>">
                &times;
            </button>
        </div>

        <div class="agency-modal-body">

            <!-- Nom du client -->
            <div class="agec-field">
                <label class="agec-label" for="agd-client-name">
                    <?php esc_html_e('Client name', 'agency-client-portal'); ?> *
                </label>
                <input type="text"
                       id="agd-client-name"
                       class="agec-input"
                       required
                       placeholder="<?php esc_attr_e('e.g. ABC Company', 'agency-client-portal'); ?>">
            </div>

            <!-- Logo -->
            <div class="agec-field">
                <label class="agec-label">
                    <?php esc_html_e('Logo', 'agency-client-portal'); ?>
                </label>
                <div class="agec-media-field">
                    <input type="url"
                           id="agd-logo-url"
                           class="agec-input"
                           placeholder="https://…">
                    <button type="button" id="agd-logo-btn" class="agec-media-btn">
                        <?php esc_html_e('Choose', 'agency-client-portal'); ?>
                    </button>
                </div>
                <img id="agd-logo-preview"
                     src=""
                     alt="Logo"
                     style="display:none; max-width:150px; margin-top:8px; border-radius:var(--agency-radius-small);"
                     onerror="this.style.display='none'">
            </div>

            <!-- Couleur d'accent -->
            <div class="agec-field">
                <label class="agec-label">
                    <?php esc_html_e('Accent color', 'agency-client-portal'); ?>
                </label>
                <div class="agec-color-field">
                    <input type="color" id="agd-primary-color-picker" value="#000000">
                    <input type="text"
                           id="agd-primary-color-text"
                           class="agec-input"
                           value="#000000"
                           maxlength="7"
                           placeholder="#000000">
                </div>
            </div>

            <!-- Erreur -->
            <div id="agd-modal-error" class="agd-error-msg" style="display:none;"></div>

        </div><!-- /.agency-modal-body -->

        <div class="agency-modal-footer">
            <button type="button" id="agd-modal-cancel" class="agency-btn agency-btn-secondary">
                <?php esc_html_e('Cancel', 'agency-client-portal'); ?>
            </button>
            <button type="button" id="agd-modal-save" class="agency-btn agency-btn-primary">
                <?php esc_html_e('Create client', 'agency-client-portal'); ?>
            </button>
        </div>

    </div><!-- /.agency-modal-content -->
</div><!-- /#agd-new-client-modal -->

<?php wp_footer(); ?>
</body>
</html>
