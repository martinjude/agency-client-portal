<?php
if (!defined('ABSPATH')) { exit; }

if (empty($is_manager)) {
    return;
}

$sidebar_context = isset($sidebar_context) ? $sidebar_context : 'client';

// Charger les blocs si nécessaire (contextes project et block)
if (in_array($sidebar_context, array('project', 'block'), true) && empty($blocks) && isset($project)) {
    $blocks = Agency_Block::get_by_project($project->id);
}

// Charger la liste des projets si non fournie (project-view.php ne la définit pas)
if (empty($projects) && isset($client)) {
    $projects = Agency_Project::get_by_client($client->id);
}

$current_block_id   = intval(get_query_var('agency_block'));
$back_to_client_url = (isset($client->slug)) ? home_url('/client/' . $client->slug . '/') : '';
$on_edit_client     = !empty(get_query_var('agency_edit_client'));

// Charger les blocs de chaque projet pour les toggles
$projects_blocks = array();
if (!empty($projects)) {
    foreach ($projects as $sp) {
        $all_sp_blocks = Agency_Block::get_by_project($sp->id);
        $projects_blocks[$sp->id] = array_values(array_filter($all_sp_blocks, function($b) {
            return $b->type !== 'separator' && $b->type !== 'progress_bar';
        }));
    }
}

$active_project_id = (in_array($sidebar_context, array('project', 'block'), true) && isset($project)) ? intval($project->id) : 0;
?>
<nav id="agency-manager-sidebar" class="agency-manager-sidebar" aria-label="<?php esc_attr_e('Manager navigation', 'agency-client-portal'); ?>">

    <button type="button"
            id="agency-sidebar-toggle"
            class="agency-sidebar-toggle"
            aria-label="<?php esc_attr_e('Collapse menu', 'agency-client-portal'); ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2.5"
             stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
    </button>

    <div class="agency-sidebar-inner">

        <div class="agency-sidebar-top">
            <?php if (isset($client)): ?>
            <span class="agency-sidebar-portal-badge">
                <?php echo esc_html('Portail ' . $client->name); ?>
            </span>
            <?php endif; ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=agency-portal')); ?>"
               class="agency-sidebar-item agency-sidebar-item--home"
               title="<?php esc_attr_e('Dashboard', 'agency-client-portal'); ?>">
                <span class="agency-sidebar-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                        <polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                </span>
                <span class="agency-sidebar-label"><?php esc_html_e('Dashboard', 'agency-client-portal'); ?></span>
            </a>
        </div>

        <div class="agency-sidebar-middle">

            <?php if (current_user_can('manage_agency_clients') && isset($client) && !$on_edit_client): ?>
            <a href="<?php echo esc_url(home_url('/agency/edit-client/' . intval($client->id) . '/')); ?>"
               class="agency-sidebar-item agency-sidebar-item--edit-client"
               title="<?php echo esc_attr(sprintf(/* translators: %s: client name */ __('Edit %s', 'agency-client-portal'), $client->name)); ?>">
                <span class="agency-sidebar-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                </span>
                <span class="agency-sidebar-label"><?php echo esc_html(sprintf(/* translators: %s: client name */ __('Edit %s', 'agency-client-portal'), $client->name)); ?></span>
            </a>
            <?php endif; ?>

            <?php
            // Lien de retour contextuel — toujours présent hors de la liste des projets,
            // avec un libellé adapté (édition client / projet / bloc).
            $back_link = null;
            if ($on_edit_client && $back_to_client_url) {
                $back_link = array(
                    'url'   => $back_to_client_url,
                    'label' => isset($client->name)
                        ? sprintf(/* translators: %s: client name */ __('Back to %s portal', 'agency-client-portal'), $client->name)
                        : __('Back to portal', 'agency-client-portal'),
                );
            } elseif ($sidebar_context === 'block' && isset($project) && isset($client->slug)) {
                $back_link = array(
                    'url'   => home_url('/client/' . $client->slug . '/project/' . $project->slug . '/'),
                    'label' => __('Back to project', 'agency-client-portal'),
                );
            } elseif ($sidebar_context === 'project' && $back_to_client_url) {
                $back_link = array(
                    'url'   => $back_to_client_url,
                    'label' => __('Back to project list', 'agency-client-portal'),
                );
            }
            ?>
            <?php if ($back_link): ?>
                <a href="<?php echo esc_url($back_link['url']); ?>"
                   class="agency-sidebar-item agency-sidebar-item--back"
                   title="<?php echo esc_attr($back_link['label']); ?>">
                    <span class="agency-sidebar-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2.5"
                             stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="15 18 9 12 15 6"/>
                        </svg>
                    </span>
                    <span class="agency-sidebar-label"><?php echo esc_html($back_link['label']); ?></span>
                </a>
            <?php endif; ?>

            <?php if (!empty($projects)): ?>
            <div class="agency-sidebar-section">
                <span class="agency-sidebar-section-label"><?php esc_html_e('Projects', 'agency-client-portal'); ?></span>
                <?php foreach ($projects as $sp):
                    $sp_name    = !empty($sp->name) ? $sp->name : sprintf(/* translators: %d: project ID */ __('Project #%d', 'agency-client-portal'), intval($sp->id));
                    $sp_emoji   = !empty($sp->emoji) ? $sp->emoji : '📋';
                    $sp_url     = home_url('/client/' . $client->slug . '/project/' . $sp->slug . '/');
                    $sp_id      = intval($sp->id);
                    $sp_blocks  = isset($projects_blocks[$sp_id]) ? $projects_blocks[$sp_id] : array();
                    $sp_active  = ($active_project_id && $active_project_id === $sp_id);
                ?>
                <div class="agency-sidebar-project-group<?php echo $sp_active ? ' is-open' : ''; ?>"
                     data-project-id="<?php echo esc_attr($sp_id); ?>">
                    <div class="agency-sidebar-project-row">
                        <a href="<?php echo esc_url($sp_url); ?>"
                           class="agency-sidebar-item agency-sidebar-item--project"
                           title="<?php echo esc_attr($sp_name); ?>">
                            <span class="agency-sidebar-icon agency-sidebar-icon--emoji"><?php echo esc_html($sp_emoji); ?></span>
                            <span class="agency-sidebar-label"><?php echo esc_html($sp_name); ?></span>
                        </a>
                        <?php if (!empty($sp_blocks)): ?>
                        <button type="button"
                                class="agency-sidebar-project-toggle"
                                data-project-id="<?php echo esc_attr($sp_id); ?>"
                                aria-label="<?php esc_attr_e('Show blocks', 'agency-client-portal'); ?>">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2.5"
                                 stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="9 18 15 12 9 6"/>
                            </svg>
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($sp_blocks)): ?>
                    <div class="agency-sidebar-blocks">
                        <?php foreach ($sp_blocks as $sb):
                            $sb_url    = home_url('/client/' . $client->slug . '/project/' . $sp->slug . '/block/' . $sb->id . '/');
                            $sb_icon   = Agency_Block::get_type_icon($sb->type);
                            $sb_title  = !empty($sb->title) ? $sb->title : sprintf(/* translators: %d: block ID */ __('Block #%d', 'agency-client-portal'), intval($sb->id));
                            $sb_active = ($current_block_id && $current_block_id === intval($sb->id));
                        ?>
                            <a href="<?php echo esc_url($sb_url); ?>"
                               class="agency-sidebar-item agency-sidebar-item--block<?php echo $sb_active ? ' agency-sidebar-item--active' : ''; ?>"
                               title="<?php echo esc_attr($sb_title); ?>">
                                <span class="agency-sidebar-icon agency-sidebar-icon--emoji"><?php echo esc_html($sb_icon); ?></span>
                                <span class="agency-sidebar-label"><?php echo esc_html($sb_title); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="agency-sidebar-bottom">
            <a href="<?php echo esc_url(admin_url('admin.php?page=agency-settings')); ?>"
               class="agency-sidebar-item agency-sidebar-item--settings"
               title="<?php esc_attr_e('Settings', 'agency-client-portal'); ?>">
                <span class="agency-sidebar-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/>
                    </svg>
                </span>
                <span class="agency-sidebar-label"><?php esc_html_e('Settings', 'agency-client-portal'); ?></span>
            </a>


            <a href="<?php echo esc_url(wp_logout_url(admin_url())); ?>"
               class="agency-sidebar-item agency-sidebar-item--logout"
               title="<?php esc_attr_e('Log out', 'agency-client-portal'); ?>">
                <span class="agency-sidebar-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                </span>
                <span class="agency-sidebar-label"><?php esc_html_e('Log out', 'agency-client-portal'); ?></span>
            </a>
        </div>

    </div>
</nav>
<?php ob_start(); ?>
(function(){
    var open = JSON.parse(localStorage.getItem('agency_sidebar_projects_open') || '[]');
    if (open.length) {
        open.forEach(function(id) {
            var el = document.querySelector('.agency-sidebar-project-group[data-project-id="' + id + '"]');
            if (el) el.classList.add('is-open');
        });
    }
})();
<?php wp_print_inline_script_tag(ob_get_clean()); ?>
