<?php
/**
 * Sidebar agence (design/design_system.ai, planche « 09 Sidebar »).
 *
 * Instance unique, présente sur toutes les pages vues par un manager :
 * navigation, recherche, arbre clients → projets → blocs, menu utilisateur.
 * Dépliée (264 px) ou repliée en rail (56 px) ; état géré par manager.js.
 *
 * @package Agency
 */
if (!defined('ABSPATH')) { exit; }

if (!current_user_can('manage_agency_projects')) {
    return;
}

// Contexte courant, résolu depuis l'URL : le chemin actif est ouvert et surligné
$agency_sb_client_slug  = (string) get_query_var('agency_client');
$agency_sb_edit_id      = intval(get_query_var('agency_edit_client'));
$agency_sb_project_slug = (string) get_query_var('agency_project');
$agency_sb_block_id     = intval(get_query_var('agency_block'));
$agency_sb_on_dashboard = !empty(get_query_var('agency_dashboard'));

$agency_sb_clients  = Agency_Client::get_all_listable();
$agency_sb_projects = Agency_Project::get_by_clients(wp_list_pluck($agency_sb_clients, 'id'));

$agency_sb_project_ids = array();
foreach ($agency_sb_projects as $agency_sb_list) {
    foreach ($agency_sb_list as $agency_sb_p) {
        $agency_sb_project_ids[] = intval($agency_sb_p->id);
    }
}
$agency_sb_blocks = Agency_Block::get_nav_by_projects($agency_sb_project_ids);

$agency_sb_block_ids = array();
foreach ($agency_sb_blocks as $agency_sb_list) {
    foreach ($agency_sb_list as $agency_sb_b) {
        $agency_sb_block_ids[] = intval($agency_sb_b->id);
    }
}
$agency_sb_comment_counts = Agency_Comment::count_by_blocks($agency_sb_block_ids);

$agency_sb_active_client_id = 0;
foreach ($agency_sb_clients as $agency_sb_c) {
    if (($agency_sb_client_slug !== '' && $agency_sb_c->slug === $agency_sb_client_slug) || ($agency_sb_edit_id && intval($agency_sb_c->id) === $agency_sb_edit_id)) {
        $agency_sb_active_client_id = intval($agency_sb_c->id);
    }
}

// En-tête : logo de l'agence + nom du site
$agency_sb_logo_url  = (string) get_option('agency_brand_logo_url', '');
$agency_sb_site_name = get_bloginfo('name');
$agency_sb_dashboard = home_url('/agency/dashboard/');

// Utilisateur courant
$agency_sb_user     = wp_get_current_user();
$agency_sb_name     = $agency_sb_user->display_name ? $agency_sb_user->display_name : $agency_sb_user->user_login;
$agency_sb_initials = '';
foreach (array_slice(preg_split('/\s+/', trim($agency_sb_name)), 0, 2) as $agency_sb_part) {
    $agency_sb_initials .= function_exists('mb_substr') ? mb_strtoupper(mb_substr($agency_sb_part, 0, 1)) : strtoupper(substr($agency_sb_part, 0, 1));
}
$agency_sb_roles = wp_roles()->get_names();
$agency_sb_role  = !empty($agency_sb_user->roles) && isset($agency_sb_roles[$agency_sb_user->roles[0]]) ? translate_user_role($agency_sb_roles[$agency_sb_user->roles[0]]) : '';

/**
 * Initiale d'un nom (avatar client).
 */
$agency_sb_initial = function ($name) {
    $name = trim((string) $name);
    if ($name === '') {
        return '?';
    }
    return function_exists('mb_substr') ? mb_strtoupper(mb_substr($name, 0, 1)) : strtoupper(substr($name, 0, 1));
};
?>
<?php ob_start(); ?>
(function(){
    try {
        var s = localStorage.getItem('agency_sidebar_state');
        document.body.classList.add(s === 'closed' ? 'agency-sidebar-closed' : 'agency-sidebar-open');
    } catch (e) {
        document.body.classList.add('agency-sidebar-open');
    }
})();
<?php wp_print_inline_script_tag(ob_get_clean()); ?>

<button type="button" class="agency-sidebar-burger agency-btn-icon" id="agency-sidebar-burger" aria-controls="agency-sidebar" aria-expanded="false" aria-label="<?php esc_attr_e('Open menu', 'agency-client-portal'); ?>">
    <?php Agency_Icons::render_ui('menu', 18); ?>
</button>
<div class="agency-sidebar-scrim" id="agency-sidebar-scrim" hidden></div>

<aside id="agency-sidebar" class="agency-sidebar" aria-label="<?php esc_attr_e('Agency navigation', 'agency-client-portal'); ?>">

    <div class="agency-sidebar__head">
        <a class="agency-sidebar__logo" href="<?php echo esc_url($agency_sb_dashboard); ?>" aria-label="<?php esc_attr_e('Dashboard', 'agency-client-portal'); ?>">
            <?php if ($agency_sb_logo_url): ?>
                <img src="<?php echo esc_url($agency_sb_logo_url); ?>" alt="" onerror="this.remove()">
            <?php else: ?>
                <?php Agency_Icons::render_glyph('leaf', 18); ?>
            <?php endif; ?>
        </a>
        <div class="agency-sidebar__ws">
            <div class="agency-sidebar__ws-name"><?php echo esc_html($agency_sb_site_name); ?></div>
            <div class="agency-sidebar__ws-meta">
                <?php
                echo esc_html(
                    /* translators: %d: number of clients */
                    sprintf(_n('%d client', '%d clients', count($agency_sb_clients), 'agency-client-portal'), count($agency_sb_clients))
                    . ' · '
                    /* translators: %d: number of projects */
                    . sprintf(_n('%d project', '%d projects', count($agency_sb_project_ids), 'agency-client-portal'), count($agency_sb_project_ids))
                );
                ?>
            </div>
        </div>
        <button type="button" class="agency-btn-icon agency-sidebar__fold" id="agency-sidebar-toggle" aria-label="<?php esc_attr_e('Collapse menu', 'agency-client-portal'); ?>" data-label-open="<?php esc_attr_e('Collapse menu', 'agency-client-portal'); ?>" data-label-closed="<?php esc_attr_e('Expand menu', 'agency-client-portal'); ?>">
            <?php Agency_Icons::render_ui('sidebar', 16); ?>
        </button>
    </div>

    <div class="agency-sidebar__body">
        <label class="agency-search agency-sidebar__search">
            <?php Agency_Icons::render_ui('search', 16); ?>
            <input type="search" id="agency-sidebar-search" placeholder="<?php esc_attr_e('Search', 'agency-client-portal'); ?>" autocomplete="off" aria-label="<?php esc_attr_e('Search clients, projects and blocks', 'agency-client-portal'); ?>">
            <kbd class="agency-sidebar__kbd">⌘K</kbd>
        </label>
        <button type="button" class="agency-sidebar__rail-btn agency-sidebar__rail-search" id="agency-sidebar-search-btn" data-tip="<?php esc_attr_e('Search', 'agency-client-portal'); ?>" aria-label="<?php esc_attr_e('Search', 'agency-client-portal'); ?>">
            <?php Agency_Icons::render_ui('search', 16); ?>
        </button>

        <nav class="agency-sidebar__nav" aria-label="<?php esc_attr_e('Main', 'agency-client-portal'); ?>">
            <a href="<?php echo esc_url($agency_sb_dashboard); ?>" class="agency-sidebar__row agency-sidebar__row--nav<?php echo $agency_sb_on_dashboard ? ' is-active' : ''; ?>" data-tip="<?php esc_attr_e('Dashboard', 'agency-client-portal'); ?>">
                <?php Agency_Icons::render_glyph('dashboard', 18); ?>
                <span class="agency-sidebar__lbl"><?php esc_html_e('Dashboard', 'agency-client-portal'); ?></span>
            </a>
        </nav>

        <div class="agency-sidebar__section">
            <span class="agency-sidebar__section-lbl"><?php esc_html_e('Clients', 'agency-client-portal'); ?></span>
            <a href="<?php echo esc_url(add_query_arg('action', 'new-client', $agency_sb_dashboard)); ?>" class="agency-btn-icon agency-btn-icon--ghost agency-sidebar__add" id="agency-sidebar-new-client" data-tip="<?php esc_attr_e('New client', 'agency-client-portal'); ?>" aria-label="<?php esc_attr_e('New client', 'agency-client-portal'); ?>">
                <?php Agency_Icons::render_ui('plus-square', 16); ?>
            </a>
        </div>

        <div class="agency-sidebar__clients" id="agency-sidebar-clients">
            <?php foreach ($agency_sb_clients as $agency_sb_c):
                $agency_sb_cid       = intval($agency_sb_c->id);
                $agency_sb_c_proj    = isset($agency_sb_projects[$agency_sb_cid]) ? $agency_sb_projects[$agency_sb_cid] : array();
                $agency_sb_c_active  = $agency_sb_cid === $agency_sb_active_client_id;
                $agency_sb_c_color   = sanitize_hex_color($agency_sb_c->primary_color) ?: '#7C83FD';
                $agency_sb_c_url     = home_url('/client/' . $agency_sb_c->slug . '/');
                $agency_sb_inactive  = $agency_sb_c->status !== 'active';
                $agency_sb_tip       = $agency_sb_c->name . ' · ' . sprintf(
                    /* translators: %d: number of projects */
                    _n('%d project', '%d projects', count($agency_sb_c_proj), 'agency-client-portal'),
                    count($agency_sb_c_proj)
                );
            ?>
            <div class="agency-sidebar__group<?php echo $agency_sb_c_active ? ' is-open is-current' : ''; ?><?php echo $agency_sb_inactive ? ' is-inactive' : ''; ?>"
                 data-sb-key="c<?php echo esc_attr($agency_sb_cid); ?>"
                 data-sb-search="<?php echo esc_attr($agency_sb_c->name); ?>">
                <div class="agency-sidebar__client<?php echo ($agency_sb_c_active && !$agency_sb_project_slug && !$agency_sb_edit_id) ? ' is-active' : ''; ?>">
                    <button type="button" class="agency-sidebar__chev" aria-expanded="<?php echo $agency_sb_c_active ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr(sprintf(/* translators: %s: client name */ __('Show projects of %s', 'agency-client-portal'), $agency_sb_c->name)); ?>">
                        <?php Agency_Icons::render_ui('chevron-right', 12); ?>
                    </button>
                    <a href="<?php echo esc_url($agency_sb_c_url); ?>" class="agency-sidebar__client-link" data-tip="<?php echo esc_attr($agency_sb_tip); ?>">
                        <span class="agency-avatar<?php echo $agency_sb_c_active ? ' is-current' : ''; ?>" style="background:<?php echo esc_attr($agency_sb_c_color); ?>"><?php echo esc_html($agency_sb_initial($agency_sb_c->name)); ?></span>
                        <span class="agency-sidebar__lbl"><?php echo esc_html($agency_sb_c->name); ?></span>
                    </a>
                    <?php if ($agency_sb_inactive): ?>
                        <span class="agency-badge agency-sidebar__meta"><?php esc_html_e('inactive', 'agency-client-portal'); ?></span>
                    <?php else: ?>
                        <span class="agency-count agency-sidebar__meta"><?php echo esc_html(count($agency_sb_c_proj)); ?></span>
                    <?php endif; ?>
                    <a href="<?php echo esc_url(home_url('/agency/edit-client/' . $agency_sb_cid . '/')); ?>" class="agency-btn-icon agency-btn-icon--ghost agency-sidebar__edit" aria-label="<?php echo esc_attr(sprintf(/* translators: %s: client name */ __('Edit %s', 'agency-client-portal'), $agency_sb_c->name)); ?>">
                        <?php Agency_Icons::render_glyph('pen', 15); ?>
                    </a>
                </div>

                <div class="agency-sidebar__tree">
                    <?php foreach ($agency_sb_c_proj as $agency_sb_p):
                        $agency_sb_pid       = intval($agency_sb_p->id);
                        $agency_sb_p_blocks  = isset($agency_sb_blocks[$agency_sb_pid]) ? $agency_sb_blocks[$agency_sb_pid] : array();
                        $agency_sb_p_active  = $agency_sb_c_active && $agency_sb_project_slug !== '' && $agency_sb_p->slug === $agency_sb_project_slug;
                        $agency_sb_p_url     = home_url('/client/' . $agency_sb_c->slug . '/project/' . $agency_sb_p->slug . '/');
                    ?>
                    <div class="agency-sidebar__pgroup<?php echo $agency_sb_p_active ? ' is-open is-current' : ''; ?>"
                         data-sb-key="p<?php echo esc_attr($agency_sb_pid); ?>"
                         data-sb-search="<?php echo esc_attr($agency_sb_p->name); ?>"
                         data-sb-context="<?php echo esc_attr($agency_sb_c->name); ?>">
                        <div class="agency-sidebar__row agency-sidebar__row--project<?php echo ($agency_sb_p_active && !$agency_sb_block_id) ? ' is-active' : ''; ?>">
                            <?php if (!empty($agency_sb_p_blocks)): ?>
                            <button type="button" class="agency-sidebar__chev agency-sidebar__chev--sm" aria-expanded="<?php echo $agency_sb_p_active ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr(sprintf(/* translators: %s: project name */ __('Show blocks of %s', 'agency-client-portal'), $agency_sb_p->name)); ?>">
                                <?php Agency_Icons::render_ui('chevron-right', 10); ?>
                            </button>
                            <?php else: ?>
                            <span class="agency-sidebar__chev agency-sidebar__chev--sm" aria-hidden="true"></span>
                            <?php endif; ?>
                            <a href="<?php echo esc_url($agency_sb_p_url); ?>" class="agency-sidebar__row-link">
                                <?php Agency_Icons::render_glyph('folder', 16); ?>
                                <span class="agency-sidebar__lbl"><?php echo esc_html($agency_sb_p->name); ?></span>
                            </a>
                        </div>
                        <?php if (!empty($agency_sb_p_blocks)): ?>
                        <div class="agency-sidebar__tree agency-sidebar__tree--blocks">
                            <?php foreach ($agency_sb_p_blocks as $agency_sb_b):
                                $agency_sb_bid    = intval($agency_sb_b->id);
                                $agency_sb_b_cnt  = isset($agency_sb_comment_counts[$agency_sb_bid]) ? $agency_sb_comment_counts[$agency_sb_bid] : 0;
                                $agency_sb_b_act  = $agency_sb_p_active && $agency_sb_bid === $agency_sb_block_id;
                            ?>
                            <a href="<?php echo esc_url($agency_sb_p_url . 'block/' . $agency_sb_bid . '/'); ?>"
                               class="agency-sidebar__row agency-sidebar__row--block<?php echo $agency_sb_b_act ? ' is-active' : ''; ?>"
                               data-sb-search="<?php echo esc_attr($agency_sb_b->title); ?>"
                               data-sb-context="<?php echo esc_attr($agency_sb_c->name); ?>">
                                <?php Agency_Icons::render_glyph($agency_sb_b->type, 16); ?>
                                <span class="agency-sidebar__lbl"><?php echo esc_html($agency_sb_b->title); ?></span>
                                <?php if ($agency_sb_b_cnt > 0): ?>
                                    <span class="agency-badge"><?php echo esc_html($agency_sb_b_cnt); ?></span>
                                <?php endif; ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <a href="<?php echo esc_url(add_query_arg('action', 'new-project', $agency_sb_c_url)); ?>" class="agency-sidebar__row agency-sidebar__row--muted">
                        <span class="agency-sidebar__chev agency-sidebar__chev--sm" aria-hidden="true"></span>
                        <?php Agency_Icons::render_ui('plus', 16); ?>
                        <span class="agency-sidebar__lbl"><?php esc_html_e('New project', 'agency-client-portal'); ?></span>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
            <p class="agency-sidebar__empty" id="agency-sidebar-empty" hidden><?php esc_html_e('No results', 'agency-client-portal'); ?></p>
        </div>
    </div>

    <div class="agency-sidebar__foot">
        <div class="agency-dropdown agency-dropdown--up agency-dropdown--left agency-sidebar__user">
            <button type="button" class="agency-sidebar__user-btn" data-agency-dropdown aria-haspopup="true" aria-expanded="false">
                <span class="agency-avatar agency-avatar--32 agency-avatar--round"><?php echo esc_html($agency_sb_initials); ?></span>
                <span class="agency-sidebar__who">
                    <span class="agency-sidebar__name"><?php echo esc_html($agency_sb_name); ?> <?php Agency_Icons::render_ui('chevron-down', 12); ?></span>
                    <?php if ($agency_sb_role): ?><span class="agency-sidebar__role"><?php echo esc_html($agency_sb_role); ?></span><?php endif; ?>
                </span>
                <span class="agency-sidebar__more"><?php Agency_Icons::render_ui('more', 16); ?></span>
            </button>
            <div class="agency-menu" role="menu">
                <?php if (current_user_can('manage_options')): ?>
                <a class="agency-menu__item" role="menuitem" href="<?php echo esc_url(admin_url('admin.php?page=agency-settings')); ?>">
                    <?php Agency_Icons::render_glyph('settings', 16); ?> <?php esc_html_e('Settings', 'agency-client-portal'); ?>
                </a>
                <?php endif; ?>
                <a class="agency-menu__item" role="menuitem" href="<?php echo esc_url(admin_url()); ?>">
                    <?php Agency_Icons::render_glyph('wordpress', 16); ?> <?php esc_html_e('WordPress admin', 'agency-client-portal'); ?>
                </a>
                <hr class="agency-menu__sep">
                <a class="agency-menu__item agency-menu__item--danger" role="menuitem" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">
                    <?php Agency_Icons::render_glyph('logout', 16); ?> <?php esc_html_e('Log out', 'agency-client-portal'); ?>
                </a>
            </div>
        </div>
    </div>
</aside>
