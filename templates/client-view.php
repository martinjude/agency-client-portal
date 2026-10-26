<?php if (!defined('ABSPATH')) { exit; } ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <?php wp_head(); ?>
</head>
<?php
$client_slug = get_query_var('agency_client');
$client = Agency_Client::get_by_slug($client_slug);

if (!$client) {
    wp_die(esc_html__('Client not found', 'agency-client-portal'), 404);
}

// Préparer les classes CSS du body
$body_classes = array();
if (Agency_Security::get_authenticated_client() && !is_user_logged_in()) {
    $body_classes[] = 'agency-client-mode';
}
?>
<body <?php body_class($body_classes); ?> data-client-id="<?php echo esc_attr($client->id); ?>">

<?php
// Vérifier les permissions (hybride : token ou utilisateur WordPress)
$security = agency()->security;
$token_authenticated = Agency_Security::can_access_client_by_token($client->id);
$wp_authenticated = is_user_logged_in() && $security->user_can_access_client($client->id);

if (!$token_authenticated && !$wp_authenticated) {
    wp_die(esc_html__('Access denied', 'agency-client-portal'), 403);
}

$projects = Agency_Client::get_projects($client->id);
$is_manager = current_user_can('manage_agency_projects');
$is_client_mode = $token_authenticated && !is_user_logged_in(); // Mode client pur (sans compte WP)
$client_view_title = !empty($client->client_view_title) ? $client->client_view_title : __('Your Projects', 'agency-client-portal');

// Blocs de navigation par projet (compteurs des cartes) en une requête
$nav_blocks   = Agency_Block::get_nav_by_projects(wp_list_pluck($projects, 'id'));
$total_blocks = 0;
foreach ($nav_blocks as $list) {
    $total_blocks += count($list);
}
$archived_count = $is_manager ? count(Agency_Project::get_archived_by_client($client->id)) : 0;
$has_token      = !empty($client->token_hash) && !empty($client->token_active);
$is_active      = $client->status === 'active';

// Libellés disponibles : uniquement ceux liés aux projets du client
$available_labels_global = (array) get_option('agency_project_labels', array());
$used_labels = array();
foreach ($projects as $p) {
    if (!empty($p->labels)) {
        foreach (explode(',', $p->labels) as $lbl) {
            $lbl = trim($lbl);
            if ($lbl !== '' && in_array($lbl, $available_labels_global, true)) {
                $used_labels[$lbl] = true;
            }
        }
    }
}
$available_labels = array_keys($used_labels);
?>

<div class="agency-wrapper">

    <?php include AGENCY_PLUGIN_DIR . 'templates/partials/header.php'; ?>
    <?php include AGENCY_PLUGIN_DIR . 'templates/partials/sidebar.php'; ?>

    <main class="agency-main">
        <div class="agency-container">
            <div class="agency-client-view">

                <div class="agency-page-head">
                    <div class="agency-page-head__main">
                        <span class="agency-avatar agency-avatar--48" style="background:<?php echo esc_attr(Agency_Template::client_color($client)); ?>">
                            <?php if (!empty($client->logo_url)): ?>
                                <img src="<?php echo esc_url($client->logo_url); ?>" alt="" data-initial="<?php echo esc_attr(Agency_Template::initial($client->name)); ?>" onerror="this.parentNode.textContent=this.dataset.initial">
                            <?php else: ?>
                                <?php echo esc_html(Agency_Template::initial($client->name)); ?>
                            <?php endif; ?>
                        </span>
                        <div class="agency-page-head__text">
                            <div class="agency-page-head__title-row">
                                <?php if ($is_manager): ?>
                                    <h1 class="agency-page-title"><?php echo esc_html($client->name); ?></h1>
                                    <span class="agency-pill <?php echo $is_active ? 'agency-pill--ok' : 'agency-pill--off'; ?>"><?php echo $is_active ? esc_html__('Active', 'agency-client-portal') : esc_html__('Inactive', 'agency-client-portal'); ?></span>
                                <?php else: ?>
                                    <h1 class="agency-page-title"><?php echo esc_html($client_view_title); ?></h1>
                                <?php endif; ?>
                            </div>
                            <div class="agency-page-meta">
                                <?php
                                $meta_parts = array(
                                    /* translators: %d: number of projects */
                                    sprintf(_n('%d project', '%d projects', count($projects), 'agency-client-portal'), count($projects)),
                                    /* translators: %d: number of blocks */
                                    sprintf(_n('%d block', '%d blocks', $total_blocks, 'agency-client-portal'), $total_blocks),
                                );
                                if ($is_manager) {
                                    $meta_parts[] = $has_token ? __('portal active', 'agency-client-portal') : __('no access link', 'agency-client-portal');
                                }
                                echo esc_html(implode(' · ', $meta_parts));
                                ?>
                            </div>
                            <?php if ($is_manager): ?>
                                <p class="agency-page-sub">
                                    <span class="agency-count"><?php esc_html_e('Title shown to the client:', 'agency-client-portal'); ?></span>
                                    <span class="agency-inline-edit" id="agency-client-title" contenteditable="true" data-client-id="<?php echo esc_attr($client->id); ?>" aria-label="<?php esc_attr_e('Client page title', 'agency-client-portal'); ?>"><?php echo esc_html($client_view_title); ?></span>
                                    <span class="agency-title-status" id="client-title-status" aria-live="polite"></span>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($is_manager): ?>
                    <div class="agency-page-head__actions">
                        <?php if ($has_token): ?>
                        <button type="button" class="agency-btn agency-copy-access" data-client-id="<?php echo esc_attr($client->id); ?>">
                            <?php Agency_Icons::render('copy', 16); ?> <?php esc_html_e('Copy portal link', 'agency-client-portal'); ?>
                        </button>
                        <?php endif; ?>
                        <div class="agency-dropdown">
                            <button type="button" class="agency-btn-icon" data-agency-dropdown aria-haspopup="true" aria-expanded="false" aria-label="<?php esc_attr_e('More actions', 'agency-client-portal'); ?>">
                                <?php Agency_Icons::render_ui('more', 16); ?>
                            </button>
                            <div class="agency-menu" role="menu">
                                <a class="agency-menu__item" href="<?php echo esc_url(home_url('/agency/edit-client/' . intval($client->id) . '/')); ?>"><?php Agency_Icons::render('edit', 16); ?> <?php esc_html_e('Edit client', 'agency-client-portal'); ?></a>
                                <a class="agency-menu__item" href="<?php echo esc_url(home_url('/agency/edit-client/' . intval($client->id) . '/#agec-access')); ?>"><?php Agency_Icons::render('key', 16); ?> <?php esc_html_e('Access & security', 'agency-client-portal'); ?></a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="agency-toolbar">
                    <label class="agency-search">
                        <?php Agency_Icons::render_ui('search', 16); ?>
                        <input type="search" id="project-search" placeholder="<?php esc_attr_e('Search a project', 'agency-client-portal'); ?>" autocomplete="off">
                    </label>
                    <?php if ($is_manager): ?>
                    <button type="button" class="agency-btn" id="show-archived-btn" aria-pressed="false">
                        <?php Agency_Icons::render_glyph('folder', 16); ?> <?php esc_html_e('Archived', 'agency-client-portal'); ?>
                        <span class="agency-badge"><?php echo esc_html($archived_count); ?></span>
                    </button>
                    <?php endif; ?>
                    <?php if (!empty($available_labels)): ?>
                    <div class="agency-dropdown">
                        <button type="button" class="agency-btn" data-agency-dropdown aria-haspopup="true" aria-expanded="false">
                            <?php Agency_Icons::render_ui('filter', 16); ?> <?php esc_html_e('Filter', 'agency-client-portal'); ?>
                        </button>
                        <div class="agency-menu" id="label-filters">
                            <div class="agency-menu__label"><?php esc_html_e('Labels', 'agency-client-portal'); ?></div>
                            <button type="button" class="agency-label-filter active" data-label="all"><?php esc_html_e('All', 'agency-client-portal'); ?></button>
                            <?php foreach ($available_labels as $label): ?>
                                <button type="button" class="agency-label-filter" data-label="<?php echo esc_attr($label); ?>"><?php echo esc_html($label); ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="agency-seg" data-agency-view-toggle="projects-grid" role="group" aria-label="<?php esc_attr_e('Display', 'agency-client-portal'); ?>">
                        <button type="button" data-view="grid" class="is-on" aria-label="<?php esc_attr_e('Grid', 'agency-client-portal'); ?>"><?php Agency_Icons::render_ui('grid', 16); ?></button>
                        <button type="button" data-view="list" aria-label="<?php esc_attr_e('List', 'agency-client-portal'); ?>"><?php Agency_Icons::render_ui('list', 16); ?></button>
                    </div>
                    <?php if ($is_manager): ?>
                    <button class="agency-btn agency-btn-primary" id="add-project-btn" type="button">
                        <?php Agency_Icons::render_ui('plus', 16); ?> <?php esc_html_e('Add a project', 'agency-client-portal'); ?>
                    </button>
                    <?php endif; ?>
                </div>

                <?php if ($is_manager): ?>
                    <!-- Modal de création de projet -->
                    <div class="agency-modal" id="new-project-modal">
                        <div class="agency-modal-content">
                            <div class="agency-modal-header">
                                <h3 class="agency-modal-title"><?php esc_html_e('New Project', 'agency-client-portal'); ?></h3>
                                <button class="agency-modal-close" type="button">&times;</button>
                            </div>
                            <div class="agency-modal-body">
                                <div class="agency-form-group">
                                    <label class="agency-form-label"><?php esc_html_e('Project icon', 'agency-client-portal'); ?></label>
                                    <div data-agency-emoji-picker
                                         data-default-emoji="📋"
                                         data-input-field="new-project-emoji"></div>
                                    <input type="hidden" id="new-project-emoji" value="📋">
                                </div>
                                <div class="agency-form-group">
                                    <label class="agency-form-label" for="new-project-name">
                                        <?php esc_html_e('Project name', 'agency-client-portal'); ?>
                                    </label>
                                    <input type="text"
                                           id="new-project-name"
                                           class="agency-form-input"
                                           placeholder="<?php esc_attr_e('e.g. Marketing Campaign 2024', 'agency-client-portal'); ?>"
                                           required>
                                </div>
                                <div class="agency-form-group">
                                    <label class="agency-form-label" for="new-project-image-url">
                                        <?php esc_html_e('Cover image (optional)', 'agency-client-portal'); ?>
                                    </label>
                                    <div class="agency-form-inline">
                                        <input type="url"
                                               id="new-project-image-url"
                                               class="agency-form-input"
                                               placeholder="<?php esc_attr_e('Image URL', 'agency-client-portal'); ?>">
                                        <input type="hidden" id="new-project-image-asset-id" value="">
                                        <input type="hidden" id="new-project-image-thumbnail-url" value="">
                                        <button type="button" class="agency-btn agency-btn-secondary" id="new-project-image-btn">
                                            <?php esc_html_e('Choose', 'agency-client-portal'); ?>
                                        </button>
                                    </div>
                                    <div class="agency-image-preview" id="new-project-image-preview" style="display:none;"></div>
                                </div>
                                <?php if (!empty($available_labels_global)): ?>
                                <div class="agency-form-group">
                                    <label class="agency-form-label"><?php esc_html_e('Labels', 'agency-client-portal'); ?></label>
                                    <div class="agency-labels-select" id="new-project-labels">
                                        <?php foreach ($available_labels_global as $label): ?>
                                            <button type="button" class="agency-label-toggle" data-value="<?php echo esc_attr($label); ?>">
                                                <?php echo esc_html($label); ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="hidden" id="new-project-labels-value" value="">
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="agency-modal-footer">
                                <button class="agency-btn agency-btn-secondary modal-cancel" type="button">
                                    <?php esc_html_e('Cancel', 'agency-client-portal'); ?>
                                </button>
                                <button class="agency-btn agency-btn-primary" id="create-project-btn" type="button">
                                    <?php esc_html_e('Create project', 'agency-client-portal'); ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Project Modal -->
                    <div class="agency-modal" id="edit-project-modal">
                        <div class="agency-modal-content">
                            <div class="agency-modal-header">
                                <h3 class="agency-modal-title"><?php esc_html_e('Edit Project', 'agency-client-portal'); ?></h3>
                                <button class="agency-modal-close" type="button">&times;</button>
                            </div>
                            <div class="agency-modal-body">
                                <div class="agency-form-group">
                                    <label class="agency-form-label"><?php esc_html_e('Project icon', 'agency-client-portal'); ?></label>
                                    <div data-agency-emoji-picker
                                         data-default-emoji="📋"
                                         data-input-field="edit-project-emoji"></div>
                                    <input type="hidden" id="edit-project-emoji" value="📋">
                                    <input type="hidden" id="edit-project-id">
                                    <input type="hidden" id="edit-project-slug">
                                </div>
                                <div class="agency-form-group">
                                    <label class="agency-form-label" for="edit-project-name">
                                        <?php esc_html_e('Project name', 'agency-client-portal'); ?>
                                    </label>
                                    <input type="text"
                                           id="edit-project-name"
                                           class="agency-form-input"
                                           required>
                                </div>
                                <div class="agency-form-group">
                                    <label class="agency-form-label" for="edit-project-email">
                                        <?php esc_html_e('Client email (optional)', 'agency-client-portal'); ?>
                                    </label>
                                    <input type="email"
                                           id="edit-project-email"
                                           class="agency-form-input"
                                           placeholder="<?php esc_attr_e('client@example.com', 'agency-client-portal'); ?>">
                                    <p class="agency-form-hint"><?php esc_html_e('Used for the "Notify client" button.', 'agency-client-portal'); ?></p>
                                </div>
                                <div class="agency-form-group">
                                    <label class="agency-form-label" for="edit-project-image-url">
                                        <?php esc_html_e('Cover image (optional)', 'agency-client-portal'); ?>
                                    </label>
                                    <div class="agency-form-inline">
                                        <input type="url"
                                               id="edit-project-image-url"
                                               class="agency-form-input"
                                               placeholder="<?php esc_attr_e('Image URL', 'agency-client-portal'); ?>">
                                        <input type="hidden" id="edit-project-image-asset-id" value="">
                                        <input type="hidden" id="edit-project-image-thumbnail-url" value="">
                                        <button type="button" class="agency-btn agency-btn-secondary" id="edit-project-image-btn">
                                            <?php esc_html_e('Choose', 'agency-client-portal'); ?>
                                        </button>

                                        <button type="button" class="agency-btn agency-btn-danger" id="edit-project-image-clear" title="<?php esc_attr_e('Remove image', 'agency-client-portal'); ?>" style="display:none;">✕</button>
                                    </div>
                                    <div class="agency-image-preview" id="edit-project-image-preview" style="display:none;"></div>
                                </div>
                                <?php if (!empty($available_labels_global)): ?>
                                <div class="agency-form-group">
                                    <label class="agency-form-label"><?php esc_html_e('Labels', 'agency-client-portal'); ?></label>
                                    <div class="agency-labels-select" id="edit-project-labels">
                                        <?php foreach ($available_labels_global as $label): ?>
                                            <button type="button" class="agency-label-toggle" data-value="<?php echo esc_attr($label); ?>">
                                                <?php echo esc_html($label); ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="hidden" id="edit-project-labels-value" value="">
                                </div>
                                <?php endif; ?>
                                <div class="agency-form-group">
                                    <label class="agency-form-label agency-form-label--checkbox">
                                        <input type="checkbox" id="edit-project-archived" value="1">
                                        <?php esc_html_e('Archive this project', 'agency-client-portal'); ?>
                                    </label>
                                    <p class="agency-form-hint"><?php esc_html_e('An archived project is hidden from the main view but remains accessible.', 'agency-client-portal'); ?></p>
                                </div>
                            </div>
                            <div class="agency-modal-footer">
                                <button class="agency-btn agency-btn-secondary modal-cancel" type="button">
                                    <?php esc_html_e('Cancel', 'agency-client-portal'); ?>
                                </button>
                                <button class="agency-btn agency-btn-primary" id="update-project-btn" type="button">
                                    <?php esc_html_e('Update', 'agency-client-portal'); ?>
                                </button>
                            </div>
                        </div>
                    </div>

                <?php endif; ?>

                <!-- Grille des projets actifs -->
                <div id="active-projects-view">
                    <h2 class="agency-sec-h"><?php esc_html_e('Projects', 'agency-client-portal'); ?> <span class="agency-badge"><?php echo esc_html(count($projects)); ?></span></h2>
                    <?php if (empty($projects) && !$is_manager): ?>
                        <div class="agency-empty">
                            <p class="agency-empty__title"><?php esc_html_e('No projects yet.', 'agency-client-portal'); ?></p>
                            <p class="agency-empty__text"><?php esc_html_e('Your projects will appear here as soon as they are shared with you.', 'agency-client-portal'); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="agency-card-grid agency-projects-grid <?php echo $is_manager ? 'sortable' : ''; ?>" id="projects-grid" data-client-id="<?php echo esc_attr($client->id); ?>">
                            <?php foreach ($projects as $project): ?>
                                <?php
                                    $project_name = isset($project->name) && $project->name !== '' ? $project->name : sprintf(/* translators: %d: project ID */ __('Project #%d', 'agency-client-portal'), intval($project->id));
                                    $project_emoji = !empty($project->emoji) ? $project->emoji : '📋';
                                    $project_url  = home_url("/client/{$client->slug}/project/{$project->slug}/");
                                    $blocks_count = isset($nav_blocks[intval($project->id)]) ? count($nav_blocks[intval($project->id)]) : 0;
                                    $cover_url = Agency_Project::get_cover_thumbnail_url($project);
                                    $project_labels_raw = !empty($project->labels) ? $project->labels : '';
                                ?>
                                <div class="agency-card agency-project-card"
                                     data-project-id="<?php echo esc_attr($project->id); ?>"
                                     data-project-labels="<?php echo esc_attr($project_labels_raw); ?>"
                                     data-project-name="<?php echo esc_attr($project_name); ?>">
                                    <a href="<?php echo esc_url($project_url); ?>" class="agency-card__link" aria-label="<?php echo esc_attr($project_name); ?>"></a>
                                    <div class="agency-card__cover<?php echo $cover_url ? '' : ' is-empty'; ?>"<?php if ($cover_url): ?> data-bg-lazy="<?php echo esc_url($cover_url); ?>"<?php endif; ?>></div>
                                    <div class="agency-card__body">
                                        <div class="agency-card__tags">
                                            <?php if (!empty($project_labels_raw)): ?>
                                                <?php foreach (array_slice(explode(',', $project_labels_raw), 0, 3) as $lbl): ?>
                                                    <span class="agency-tag agency-tag--outline"><?php echo esc_html(trim($lbl)); ?></span>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                            <?php if ($is_manager): ?>
                                            <div class="agency-dropdown agency-card__menu">
                                                <button type="button" class="agency-btn-icon agency-btn-icon--ghost" data-agency-dropdown aria-haspopup="true" aria-expanded="false" aria-label="<?php esc_attr_e('Project actions', 'agency-client-portal'); ?>">
                                                    <?php Agency_Icons::render_ui('more-v', 16); ?>
                                                </button>
                                                <div class="agency-menu" role="menu">
                                                    <a class="agency-menu__item" href="<?php echo esc_url($project_url); ?>"><?php Agency_Icons::render_ui('external', 16); ?> <?php esc_html_e('Open', 'agency-client-portal'); ?></a>
                                                    <button type="button" class="agency-menu__item edit-project"
                                                        data-project-id="<?php echo esc_attr($project->id); ?>"
                                                        data-project-name="<?php echo esc_attr($project->name); ?>"
                                                        data-project-email="<?php echo esc_attr($project->email ?? ''); ?>"
                                                        data-project-slug="<?php echo esc_attr($project->slug); ?>"
                                                        data-project-emoji="<?php echo esc_attr($project->emoji); ?>"
                                                        data-project-cover="<?php echo esc_attr($cover_url); ?>"
                                                        data-project-cover-asset-id="<?php echo esc_attr($project->cover_image_asset_id ?? ''); ?>"
                                                        data-project-cover-thumbnail-url="<?php echo esc_attr($project->cover_image_thumbnail_url ?? ''); ?>"
                                                        data-project-labels="<?php echo esc_attr($project_labels_raw); ?>"
                                                        data-project-archived="0"><?php Agency_Icons::render('edit', 16); ?> <?php esc_html_e('Edit', 'agency-client-portal'); ?></button>
                                                    <hr class="agency-menu__sep">
                                                    <button type="button" class="agency-menu__item agency-menu__item--danger delete-project" data-project-id="<?php echo esc_attr($project->id); ?>"><?php Agency_Icons::render('trash', 16); ?> <?php esc_html_e('Delete', 'agency-client-portal'); ?></button>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <h3 class="agency-card__title">
                                            <span class="agency-card__emoji" data-agency-emoji><?php echo esc_html($project_emoji); ?></span>
                                            <span class="agency-card__title-text"><?php echo esc_html($project_name); ?></span>
                                        </h3>
                                        <div class="agency-card__meta">
                                            <?php Agency_Icons::render_glyph('versioned_media', 12); ?>
                                            <?php
                                            echo esc_html(
                                                /* translators: %d: number of blocks */
                                                sprintf(_n('%d block', '%d blocks', $blocks_count, 'agency-client-portal'), $blocks_count)
                                                . ' · ' . Agency_Template::time_ago(!empty($project->updated_at) ? $project->updated_at : $project->created_at)
                                            );
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($is_manager): ?>
                                <button type="button" class="agency-card agency-card--add" id="add-project-card">
                                    <span class="agency-card__plus"><?php Agency_Icons::render_ui('plus', 18); ?></span>
                                    <?php esc_html_e('Add a project', 'agency-client-portal'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                        <p class="agency-empty-state" id="projects-no-results" hidden><?php esc_html_e('No project matches your search.', 'agency-client-portal'); ?></p>
                    <?php endif; ?>
                </div>

                <!-- Vue projets archivés (chargée dynamiquement) -->
                <div id="archived-projects-view" style="display:none;">
                    <h2 class="agency-sec-h"><?php esc_html_e('Archived projects', 'agency-client-portal'); ?></h2>
                    <div class="agency-card-grid agency-projects-grid" id="archived-projects-grid"></div>
                    <p class="agency-empty-state" id="archived-empty" style="display:none;"><?php esc_html_e('No archived projects.', 'agency-client-portal'); ?></p>
                </div>

            </div>
        </div>
    </main>

    <?php include AGENCY_PLUGIN_DIR . 'templates/partials/footer.php'; ?>
</div>

<?php ob_start(); ?>
jQuery(document).ready(function($) {

    // ─── Filtres libellés, recherche, archives ───────────────────────────────
    var activeFilter = 'all';
    var showingArchived = false;

    function applyFilters() {
        var term = ($('#project-search').val() || '').toLowerCase().trim();
        var shown = 0;
        $('#projects-grid .agency-project-card').each(function() {
            var labels = String($(this).data('project-labels') || '').split(',').map(function(l){ return l.trim(); });
            var name = String($(this).data('project-name') || '').toLowerCase();
            var ok = (activeFilter === 'all' || labels.indexOf(activeFilter) !== -1)
                && (term === '' || name.indexOf(term) !== -1);
            $(this).toggle(ok);
            if (ok) shown++;
        });
        $('#add-project-card').toggle(term === '' && activeFilter === 'all');
        $('#projects-no-results').prop('hidden', shown > 0 || (term === '' && activeFilter === 'all'));
    }

    $('#label-filters').on('click', '.agency-label-filter', function() {
        activeFilter = $(this).data('label');
        $('#label-filters .agency-label-filter').removeClass('active');
        $(this).addClass('active');
        applyFilters();
    });

    $('#project-search').on('input', AgencyUI.debounce(applyFilters, 150));

    $('#show-archived-btn').on('click', function() {
        showingArchived = !showingArchived;
        $(this).toggleClass('is-on', showingArchived).attr('aria-pressed', showingArchived ? 'true' : 'false');
        $('#active-projects-view').toggle(!showingArchived);
        $('#archived-projects-view').toggle(showingArchived);
        if (showingArchived) loadArchivedProjects();
    });

    $('#add-project-card').on('click', function() {
        $('#add-project-btn').trigger('click');
    });

    // Échappement HTML (texte et attributs)
    function esc(text) {
        return $('<div>').text(text == null ? '' : String(text)).html().replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function loadArchivedProjects() {
        var clientId = <?php echo intval($client->id); ?>;
        $.ajax({
            url: agencyData.rest_url + 'projects/archived?client_id=' + clientId,
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', agencyData.nonce);
            },
            success: function(projects) {
                var $grid = $('#archived-projects-grid');
                $grid.empty();
                if (!projects || projects.length === 0) {
                    $('#archived-empty').show();
                    return;
                }
                $('#archived-empty').hide();
                $.each(projects, function(i, project) {
                    var name = project.name || '<?php echo esc_js(__('Project', 'agency-client-portal')); ?>';
                    var emoji = project.emoji || '📋';
                    var url = '<?php echo esc_js(home_url("/client/{$client->slug}/project/")); ?>' + encodeURIComponent(project.slug) + '/';
                    var cover = project.cover_image_thumbnail_url || project.cover_image_url || '';
                    var tags = '';
                    String(project.labels || '').split(',').forEach(function(lbl) {
                        lbl = lbl.trim();
                        if (lbl) tags += '<span class="agency-tag agency-tag--outline">' + esc(lbl) + '</span>';
                    });
                    var menu = '';
                    <?php if ($is_manager): ?>
                    menu = '<div class="agency-dropdown agency-card__menu">' +
                        '<button type="button" class="agency-btn-icon agency-btn-icon--ghost" data-agency-dropdown aria-haspopup="true" aria-expanded="false">⋮</button>' +
                        '<div class="agency-menu" role="menu">' +
                        '<button type="button" class="agency-menu__item edit-project" data-project-id="' + esc(project.id) + '" data-project-name="' + esc(name) + '" data-project-email="' + esc(project.email) + '" data-project-slug="' + esc(project.slug) + '" data-project-emoji="' + esc(emoji) + '" data-project-cover="' + esc(cover) + '" data-project-cover-asset-id="' + esc(project.cover_image_asset_id) + '" data-project-cover-thumbnail-url="' + esc(project.cover_image_thumbnail_url) + '" data-project-labels="' + esc(project.labels) + '" data-project-archived="1">' + (agencyData.icons ? agencyData.icons.edit : '') + ' <?php echo esc_js(__('Edit', 'agency-client-portal')); ?></button>' +
                        '<hr class="agency-menu__sep">' +
                        '<button type="button" class="agency-menu__item agency-menu__item--danger delete-project" data-project-id="' + esc(project.id) + '">' + (agencyData.icons ? agencyData.icons.trash : '') + ' <?php echo esc_js(__('Delete', 'agency-client-portal')); ?></button>' +
                        '</div></div>';
                    <?php endif; ?>
                    var html = '<div class="agency-card agency-project-card" data-project-id="' + esc(project.id) + '">' +
                        '<a href="' + esc(url) + '" class="agency-card__link" aria-label="' + esc(name) + '"></a>' +
                        '<div class="agency-card__cover' + (cover ? '' : ' is-empty') + '"' + (cover ? ' data-bg-lazy="' + esc(cover) + '"' : '') + '></div>' +
                        '<div class="agency-card__body"><div class="agency-card__tags">' + tags + menu + '</div>' +
                        '<h3 class="agency-card__title"><span class="agency-card__emoji" data-agency-emoji>' + esc(emoji) + '</span><span class="agency-card__title-text">' + esc(name) + '</span></h3>' +
                        '<div class="agency-card__meta"><span class="agency-pill agency-pill--arch"><?php echo esc_js(__('Archived', 'agency-client-portal')); ?></span></div>' +
                        '</div></div>';
                    $grid.append(html);
                });
                // Re-init lazy loading for dynamically added cards
                if (typeof Agency !== 'undefined' && Agency.initLazyBackgrounds) {
                    Agency.initLazyBackgrounds();
                }
            }
        });
    }

    // ─── Labels toggle (create modal) ────────────────────────────────────────
    function initLabelToggles(containerId, hiddenInputId) {
        $('#' + containerId).on('click', '.agency-label-toggle', function() {
            $(this).toggleClass('selected');
            updateLabelValue(containerId, hiddenInputId);
        });
    }

    function updateLabelValue(containerId, hiddenInputId) {
        var selected = [];
        $('#' + containerId + ' .agency-label-toggle.selected').each(function() {
            selected.push($(this).data('value'));
        });
        $('#' + hiddenInputId).val(selected.join(','));
    }

    function setLabelToggles(containerId, hiddenInputId, value) {
        var labels = value ? value.split(',').map(function(l){ return l.trim(); }) : [];
        $('#' + containerId + ' .agency-label-toggle').each(function() {
            var v = $(this).data('value');
            if (labels.indexOf(v) !== -1) {
                $(this).addClass('selected');
            } else {
                $(this).removeClass('selected');
            }
        });
        $('#' + hiddenInputId).val(value || '');
    }

    initLabelToggles('new-project-labels', 'new-project-labels-value');
    initLabelToggles('edit-project-labels', 'edit-project-labels-value');

    // ─── Edit modal: clear image button ──────────────────────────────────────
    $('#edit-project-image-url').on('input', function() {
        if ($(this).val()) {
            $('#edit-project-image-clear').show();
        } else {
            $('#edit-project-image-clear').hide();
            $('#edit-project-image-preview').hide().empty();
        }
    });

    $('#edit-project-image-clear').on('click', function() {
        $('#edit-project-image-url').val('');
        $('#edit-project-image-asset-id').val('');  // Clear asset ID as well
        $('#edit-project-image-thumbnail-url').val('');
        $(this).hide();
        $('#edit-project-image-preview').hide().empty();
    });



    // ─── Edit project modal ───────────────────────────────────────────────────
    var editModal = typeof AgencyModal !== 'undefined' ? new AgencyModal('edit-project-modal') : null;

    $(document).on('click', '.edit-project', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var projectId           = $(this).data('project-id');
        var projectName         = $(this).data('project-name');
        var projectSlug         = $(this).data('project-slug') || '';
        var projectEmoji        = $(this).data('project-emoji') || '📋';
        var projectCover        = $(this).data('project-cover') || '';
        var projectCoverAssetId = $(this).data('project-cover-asset-id') || '';
        var projectCoverThumbnailUrl = $(this).data('project-cover-thumbnail-url') || '';
        var projectLabels       = $(this).data('project-labels') || '';
        var projectEmail        = $(this).data('project-email') || '';
        var isArchived          = $(this).data('project-archived') == '1';

        $('#edit-project-id').val(projectId);
        $('#edit-project-slug').val(projectSlug);
        $('#edit-project-name').val(projectName);
        $('#edit-project-email').val(projectEmail);
        $('#edit-project-image-url').val(projectCover);
        $('#edit-project-image-asset-id').val(projectCoverAssetId);
        $('#edit-project-image-thumbnail-url').val(projectCoverThumbnailUrl);

        // Afficher/masquer le bouton de suppression image
        if (projectCover) {
            $('#edit-project-image-clear').show();
            $('#edit-project-image-preview').show().html('<img src="' + projectCover + '" style="max-width:100%;max-height:150px;border-radius:4px;">');
        } else {
            $('#edit-project-image-clear').hide();
            $('#edit-project-image-preview').hide().empty();
        }

        // Précharger les libellés
        setLabelToggles('edit-project-labels', 'edit-project-labels-value', projectLabels);

        // Case archive
        $('#edit-project-archived').prop('checked', isArchived);

        // Mettre à jour l'emoji picker
        var emojiDisplay = document.querySelector('[data-input-field="edit-project-emoji"]');
        if (emojiDisplay) {
            var emojiSpan = emojiDisplay.querySelector('.emoji-display');
            if (emojiSpan) emojiSpan.textContent = projectEmoji;
        }
        $('#edit-project-emoji').val(projectEmoji);

        editModal.open();
    });

    // Close modal on cancel
    $('#edit-project-modal .modal-cancel').on('click', function() {
        editModal.close();
    });

    // Handle update project button
    $('#update-project-btn').on('click', function(e) {
        e.preventDefault();

        const projectId      = $('#edit-project-id').val();
        const name           = $('#edit-project-name').val().trim();
        const email          = $('#edit-project-email').val().trim();
        const emoji          = $('#edit-project-emoji').val();
        const coverImageUrl  = $('#edit-project-image-url').val().trim();
        const coverImageAssetId = $('#edit-project-image-asset-id').val().trim();
        const coverImageThumbnailUrl = $('#edit-project-image-thumbnail-url').val().trim();
        const labels         = $('#edit-project-labels-value').val();
        const isArchived     = $('#edit-project-archived').is(':checked') ? 1 : 0;

        if (!name) {
            alert('<?php echo esc_js(__('Please enter a project name', 'agency-client-portal')); ?>');
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true).text('<?php echo esc_js(__('Updating...', 'agency-client-portal')); ?>');

        // Prepare payload
        var payload = {
            name: name,
            email: email,
            emoji: emoji,
            cover_image_url: coverImageUrl,
            cover_image_thumbnail_url: coverImageThumbnailUrl,
            labels: labels,
            is_archived: isArchived
        };
        
        // Add asset ID if available, otherwise explicitly set to null
        if (coverImageAssetId) {
            payload.cover_image_asset_id = parseInt(coverImageAssetId);
        } else {
            payload.cover_image_asset_id = null;
        }

        $.ajax({
            url: agencyData.rest_url + 'projects/' + projectId,
            method: 'PUT',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', agencyData.nonce);
            },
            data: JSON.stringify(payload),
            contentType: 'application/json',
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('<?php echo esc_js(__('Error while updating', 'agency-client-portal')); ?>');
                    $btn.prop('disabled', false).text('<?php echo esc_js(__('Update', 'agency-client-portal')); ?>');
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('Error while updating', 'agency-client-portal')); ?>');
                $btn.prop('disabled', false).text('<?php echo esc_js(__('Update', 'agency-client-portal')); ?>');
            }
        });
    });

    // ─── Create project modal ─────────────────────────────────────────────────
    <?php if ($is_manager): ?>
    var newModal = new AgencyModal('new-project-modal');

    $('#add-project-btn').on('click', function() {
        // Réinitialiser le formulaire
        $('#new-project-name').val('');
        $('#new-project-image-url').val('');
        $('#new-project-image-asset-id').val('');  // Clear asset ID as well
        $('#new-project-image-thumbnail-url').val('');
        $('#new-project-image-preview').hide().empty();
        $('#new-project-labels-value').val('');
        $('#new-project-labels .agency-label-toggle').removeClass('selected');
        newModal.open();
    });

    $('#new-project-modal .modal-cancel').on('click', function() {
        newModal.close();
    });

    // Lien « Nouveau projet » de la sidebar : ?action=new-project
    if (new URLSearchParams(window.location.search).get('action') === 'new-project') {
        $('#add-project-btn').trigger('click');
        if (window.history && window.history.replaceState) {
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    }

    $('#create-project-btn').on('click', function() {
        var name          = $('#new-project-name').val().trim();
        var emoji         = $('#new-project-emoji').val() || '📋';
        var coverImageUrl = $('#new-project-image-url').val().trim();
        var coverImageAssetId = $('#new-project-image-asset-id').val().trim();
        var coverImageThumbnailUrl = $('#new-project-image-thumbnail-url').val().trim();
        var labels        = $('#new-project-labels-value').val();

        if (!name) {
            alert('<?php echo esc_js(__('Please enter a project name', 'agency-client-portal')); ?>');
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text('<?php echo esc_js(__('Creating...', 'agency-client-portal')); ?>');

        // Prepare payload
        var payload = {
            client_id: <?php echo intval($client->id); ?>,
            name: name,
            emoji: emoji,
            cover_image_url: coverImageUrl,
            cover_image_thumbnail_url: coverImageThumbnailUrl,
            labels: labels
        };
        
        // Add asset ID if available, otherwise explicitly set to null
        if (coverImageAssetId) {
            payload.cover_image_asset_id = parseInt(coverImageAssetId);
        } else {
            payload.cover_image_asset_id = null;
        }

        $.ajax({
            url: agencyData.rest_url + 'projects',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', agencyData.nonce);
            },
            data: JSON.stringify(payload),
            contentType: 'application/json',
            success: function(response) {
                if (response.success && response.data && response.data.url) {
                    window.location.href = response.data.url;
                } else if (response.success) {
                    location.reload();
                } else {
                    alert('<?php echo esc_js(__('Error during creation', 'agency-client-portal')); ?>');
                    $btn.prop('disabled', false).text('<?php echo esc_js(__('Create project', 'agency-client-portal')); ?>');
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('Error during creation', 'agency-client-portal')); ?>');
                $btn.prop('disabled', false).text('<?php echo esc_js(__('Create project', 'agency-client-portal')); ?>');
            }
        });
    });

    // Delete project — handled by manager.js (AgencyManager.deleteProject)

    // WP Media uploader for images (cached to avoid double-open)
    var clientSlugForUpload = '<?php echo esc_js($client->slug); ?>';

    function agencySetUploadContext(clientSlug, projectSlug, callback) {
        $.post(agencyData.ajax_url, {
            action: 'agency_set_upload_context',
            nonce: agencyData.nonce,
            client_slug: clientSlug || '',
            project_slug: projectSlug || ''
        }).always(function() {
            callback();
        });
    }

    function initMediaUploader(btnId, inputId, previewId, clearBtnId, getContext) {
        var uploader = null;
        $('#' + btnId).on('click', function(e) {
            e.preventDefault();
            var ctx = (typeof getContext === 'function') ? getContext() : {};
            agencySetUploadContext(ctx.clientSlug || '', ctx.projectSlug || '', function() {
                if (!uploader) {
                    uploader = wp.media({
                        title: '<?php echo esc_js(__('Choose an image', 'agency-client-portal')); ?>',
                        button: { text: '<?php echo esc_js(__('Use this image', 'agency-client-portal')); ?>' },
                        multiple: false,
                        library: { type: 'image' }
                    });
                    uploader.on('select', function() {
                        var attachment = uploader.state().get('selection').first().toJSON();
                        var fullUrl      = attachment.url;
                        var thumbnailUrl = (attachment.sizes && attachment.sizes.large)
                            ? attachment.sizes.large.url
                            : fullUrl;
                        $('#' + inputId).val(fullUrl);
                        // Stocker l'URL thumbnail (large) dans le champ dédié
                        if (inputId.indexOf('edit-project-image-url') !== -1) {
                            $('#edit-project-image-asset-id').val('');
                            $('#edit-project-image-thumbnail-url').val(thumbnailUrl);
                        } else if (inputId.indexOf('new-project-image-url') !== -1) {
                            $('#new-project-image-asset-id').val('');
                            $('#new-project-image-thumbnail-url').val(thumbnailUrl);
                        }
                        $('#' + previewId).show().html('<img src="' + thumbnailUrl + '" style="max-width:100%;max-height:150px;border-radius:4px;">');
                        if (clearBtnId) $('#' + clearBtnId).show();
                    });
                }
                uploader.open();
            });
        });
    }

    initMediaUploader('new-project-image-btn', 'new-project-image-url', 'new-project-image-preview', null, function() {
        return { clientSlug: clientSlugForUpload, projectSlug: $('#new-project-name').val() };
    });
    initMediaUploader('edit-project-image-btn', 'edit-project-image-url', 'edit-project-image-preview', 'edit-project-image-clear', function() {
        return { clientSlug: clientSlugForUpload, projectSlug: $('#edit-project-slug').val() };
    });
    <?php endif; ?>
});
// La déconnexion client (token) est gérée par le script autonome de
// templates/partials/header.php (lié au bouton .agency-token-logout).
<?php wp_print_inline_script_tag(ob_get_clean()); ?>

<?php wp_footer(); ?>
</body>
</html>
