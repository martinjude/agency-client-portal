<?php
/**
 * Agency Modern Fixed Header
 *
 * Variables attendues dans le scope appelant :
 *   - $client (objet client, optionnel — résolu depuis la query var si absent)
 *   - $header_mode (string, optionnel) : 'portal' (par défaut) ou 'edit'
 *
 * Mode 'edit' (utilisé par templates/edit-client.php) :
 *   - Gauche  : retour vers le dashboard admin
 *   - Centre  : nom du client
 *   - Droite  : bouton "Aperçu portail" + bouton "Enregistrer" (#agec-save-btn)
 */
if (!defined('ABSPATH')) { exit; }

// Mode d'affichage du header
$header_mode = isset($header_mode) && in_array($header_mode, array('portal', 'edit', 'dashboard'), true)
    ? $header_mode
    : 'portal';

// Resolve client from query vars if not provided
if (!isset($client) || !$client) {
    $client_slug = get_query_var('agency_client');
    if ($client_slug) {
        $client = Agency_Client::get_by_slug($client_slug);
    }
    // En mode édition le client peut être résolu depuis agency_edit_client
    if (!$client && $header_mode === 'edit') {
        $edit_id = intval(get_query_var('agency_edit_client'));
        if ($edit_id) {
            $client = Agency_Client::get_by_id($edit_id);
        }
    }
}

// Build navigation breadcrumb
$breadcrumb_items = array();

// Determine current page context
$project_slug = get_query_var('agency_project');
$block_id = get_query_var('agency_block');

// Client link (always first)
if ($client && isset($client->slug)) {
    $breadcrumb_items[] = array(
        'label' => $client->name,
        'url' => home_url("/client/{$client->slug}"),
        'active' => !$project_slug && !$block_id
    );
}

// Project link (if applicable)
if ($project_slug && $client) {
    $project = Agency_Project::get_by_slug($client->id, $project_slug);
    if ($project) {
        $project_emoji = !empty($project->emoji) ? $project->emoji : '📋';
        $breadcrumb_items[] = array(
            'label' => $project_emoji . ' ' . $project->name,
            'url' => home_url("/client/{$client->slug}/project/{$project->slug}"),
            'active' => !$block_id
        );
    }
}

// Block link (if applicable)
if ($block_id) {
    $block = Agency_Block::get_by_id($block_id);
    if ($block) {
        $block_icon = isset(Agency_Block::TYPES[$block->type]) ? Agency_Block::get_type_icon($block->type) : '📦';
        $breadcrumb_items[] = array(
            'label' => $block_icon . ' ' . $block->title,
            'url' => '',
            'active' => true
        );
    }
}

// URL de retour pour la navigation mobile (avant-dernier item du fil d'Ariane)
$back_url = '';
$breadcrumb_count = count($breadcrumb_items);
if ($breadcrumb_count >= 2) {
    $back_url = $breadcrumb_items[$breadcrumb_count - 2]['url'];
}

// Charger les projets pour le menu mobile
$mobile_projects = array();
if (isset($client) && !empty($client->id)) {
    // Vérifier que le client connecté a bien accès à ce client
    $authenticated_client = Agency_Security::get_authenticated_client();
    $current_user = wp_get_current_user();
    $is_manager = current_user_can('manage_agency_projects');
    
    if ($is_manager || ($authenticated_client && intval($authenticated_client->id) === intval($client->id))) {
        $all_client_projects = Agency_Project::get_by_client($client->id);
        $mobile_projects = array_slice($all_client_projects, 0, 15);
    }
}

// Déterminer le type d'authentification
$current_user = wp_get_current_user();
$authenticated_client = Agency_Security::get_authenticated_client();
$is_token_auth = $authenticated_client && !is_user_logged_in();

// Toujours défini (utilisé plus bas pour la bannière manager, même sans client)
$is_manager = current_user_can('manage_agency_projects');
?>
<?php ob_start(); ?>
(function(){
    var s = localStorage.getItem('agency_sidebar_state');
    document.body.classList.add(s === 'closed' ? 'agency-sidebar-closed' : 'agency-sidebar-open');
})();
<?php wp_print_inline_script_tag(ob_get_clean()); ?>
<header class="agency-header-fixed agency-header-mode-<?php echo esc_attr($header_mode); ?>">
    <div class="agency-header-container">
        <?php if ($header_mode === 'edit'): ?>
        <!-- ===== MODE ÉDITION CLIENT ===== -->
        <div class="agency-header-left">
            <a href="<?php echo esc_url(home_url('/agency/dashboard/')); ?>"
               class="agency-back-arrow"
               aria-label="<?php esc_attr_e('Dashboard', 'agency-client-portal'); ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </a>
            <span class="agency-header-edit-title">
                <?php echo $client ? esc_html($client->name) : esc_html__('Edit client', 'agency-client-portal'); ?>
            </span>
        </div>
        <?php
        $brand_logo_url  = get_option('agency_brand_logo_url', '');
        $header_logo_url = $brand_logo_url ? $brand_logo_url : ($client && !empty($client->logo_url) ? $client->logo_url : '');
        ?>
        <?php if (!empty($header_logo_url)): ?>
        <div class="agency-header-center">
            <img src="<?php echo esc_url($header_logo_url); ?>"
                 alt="<?php echo esc_attr($client ? $client->name : get_bloginfo('name')); ?>"
                 class="agency-header-logo">
        </div>
        <?php endif; ?>
        <div class="agency-header-right">
            <div class="agency-header-actions">
                <?php if ($client && !empty($client->slug)): ?>
                <a href="<?php echo esc_url(home_url('/client/' . $client->slug . '/')); ?>"
                   class="agency-btn agency-btn-secondary">
                    <?php esc_html_e('Portal preview', 'agency-client-portal'); ?>
                </a>
                <?php endif; ?>
                <button type="button" id="agec-save-btn" class="agency-btn agency-btn-primary">
                    <?php esc_html_e('Save', 'agency-client-portal'); ?>
                </button>
            </div>
        </div>
        <?php elseif ($header_mode === 'dashboard'): ?>
        <!-- ===== MODE TABLEAU DE BORD ===== -->
        <div class="agency-header-left">
            <?php $brand_logo_url = get_option('agency_brand_logo_url', ''); ?>
            <?php if (!empty($brand_logo_url)): ?>
            <img src="<?php echo esc_url($brand_logo_url); ?>"
                 alt="<?php echo esc_attr(get_bloginfo('name')); ?>"
                 class="agency-header-logo">
            <?php else: ?>
            <span class="agency-header-site-name"><?php echo esc_html(get_bloginfo('name')); ?></span>
            <?php endif; ?>
        </div>
        <div class="agency-header-right">
            <a href="<?php echo esc_url(admin_url()); ?>" class="agency-header-logout agd-admin-link">
                <?php esc_html_e('WP Admin', 'agency-client-portal'); ?>
            </a>
        </div>
        <?php else: ?>
        <!-- ===== MODE PORTAIL (par défaut) ===== -->
        <!-- Left: Back arrow + Edit CTA (managers only) -->
        <div class="agency-header-left">
            <?php if (!empty($back_url)): ?>
            <a href="<?php echo esc_url($back_url); ?>"
               class="agency-back-arrow"
               aria-label="<?php esc_attr_e('Previous page', 'agency-client-portal'); ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </a>
            <?php endif; ?>
        </div>

        <!-- Center: Logo -->
        <?php
        $brand_logo_url = get_option('agency_brand_logo_url', '');
        $header_logo_url = $brand_logo_url ? $brand_logo_url : ($client && !empty($client->logo_url) ? $client->logo_url : '');
        ?>
        <?php if (!empty($header_logo_url)): ?>
        <div class="agency-header-center">
            <img src="<?php echo esc_url($header_logo_url); ?>" 
                    alt="<?php echo esc_attr($client ? $client->name : get_bloginfo('name')); ?>" 
                    class="agency-header-logo">
        </div>
        <?php endif; ?>

        <!-- Right: Breadcrumb Navigation + User Info -->
        <div class="agency-header-right">
            <?php if (!empty($breadcrumb_items)): ?>
                <nav class="agency-header-breadcrumb" aria-label="<?php esc_attr_e('Navigation', 'agency-client-portal'); ?>">
                    <?php foreach ($breadcrumb_items as $index => $item): ?>
                        <?php if ($index > 0): ?>
                            <span class="breadcrumb-separator">/</span>
                        <?php endif; ?>
                        
                        <?php if ($item['active'] || empty($item['url'])): ?>
                            <span class="breadcrumb-item breadcrumb-current">
                                <?php echo esc_html($item['label']); ?>
                            </span>
                        <?php else: ?>
                            <a href="<?php echo esc_url($item['url']); ?>" 
                               class="breadcrumb-item breadcrumb-link">
                                <?php echo esc_html($item['label']); ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>
            
            <?php if (!empty($mobile_projects)): ?>
            <nav class="agency-mobile-projects" aria-label="<?php esc_attr_e('Projects', 'agency-client-portal'); ?>">
                <p class="agency-mobile-projects-label"><?php esc_html_e('Projects', 'agency-client-portal'); ?></p>
                <ul>
                <?php foreach ($mobile_projects as $mp):
                    $mp_emoji = !empty($mp->emoji) ? $mp->emoji . ' ' : '';
                    $mp_url   = home_url("/client/{$client->slug}/project/{$mp->slug}");
                ?>
                    <li>
                        <a href="<?php echo esc_url($mp_url); ?>">
                            <?php echo esc_html($mp_emoji . $mp->name); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
                </ul>
            </nav>
            <?php endif; ?>
            
            <div class="agency-header-user">
                <?php if ($is_token_auth): ?>
                    <!-- Mode authentification token -->
                    <a href="#"
                       class="agency-header-logout agency-token-logout"
                       title="<?php esc_attr_e('Close session', 'agency-client-portal'); ?>">
                        <?php esc_html_e('Close', 'agency-client-portal'); ?>
                    </a>
                <?php endif; ?>
            </div>

        </div>

        <!-- Hamburger (mobile only) -->
        <button class="agency-hamburger" id="agency-hamburger" aria-label="Menu" type="button">
            <span></span><span></span><span></span>
        </button>
        <?php endif; /* /portal mode */ ?>
    </div>
</header>

<!-- Spacer pour compenser le header fixed -->
<div class="agency-header-spacer"></div>

<?php if ($is_token_auth): ?>
<?php ob_start(); ?>
/* Déconnexion client (token) — autonome, indépendant de frontend.js,
   pour fonctionner sur toutes les pages du portail (y compris les blocs vidéo). */
(function(){
    var link = document.querySelector('.agency-token-logout');
    if (!link) { return; }
    link.addEventListener('click', function(e){
        e.preventDefault();
        if (!confirm(<?php echo wp_json_encode(__('Are you sure you want to close your session?', 'agency-client-portal')); ?>)) { return; }
        var ajaxUrl  = (window.agencyData && agencyData.ajax_url)     ? agencyData.ajax_url     : '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
        var nonce    = (window.agencyData && agencyData.logout_nonce) ? agencyData.logout_nonce : '<?php echo esc_js(wp_create_nonce('agency_token_logout')); ?>';
        var home     = '<?php echo esc_url(home_url()); ?>';
        var xhr = new XMLHttpRequest();
        xhr.open('POST', ajaxUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function(){
            var redirect = home;
            try { var r = JSON.parse(xhr.responseText); if (r && r.data && r.data.redirect) { redirect = r.data.redirect; } } catch(err){}
            window.location.href = redirect;
        };
        xhr.onerror = function(){ window.location.href = home; };
        xhr.send('action=agency_token_logout&nonce=' + encodeURIComponent(nonce));
    });
})();
<?php wp_print_inline_script_tag(ob_get_clean()); ?>
<?php endif; ?>
