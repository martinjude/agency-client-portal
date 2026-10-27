<?php
/**
 * En-tête du portail (côté client uniquement).
 *
 * Les managers naviguent avec la sidebar agence : aucun en-tête n'est affiché.
 * Les clients ont une barre fine : logo à gauche, fermeture de session à droite.
 * Le fil d'Ariane est rendu dans l'en-tête de chaque page.
 *
 * Variables optionnelles du scope appelant : $client.
 */
if (!defined('ABSPATH')) { exit; }

if (current_user_can('manage_agency_projects')) {
    return;
}

if (!isset($agency_client) || !$agency_client) {
    $agency_client_slug = get_query_var('agency_client');
    $agency_client      = $agency_client_slug ? Agency_Client::get_by_slug($agency_client_slug) : null;
}

$agency_authenticated_client = Agency_Security::get_authenticated_client();
$agency_is_token_auth        = $agency_authenticated_client && !is_user_logged_in();
$agency_brand_logo_url       = (string) get_option('agency_brand_logo_url', '');
$agency_header_logo_url      = $agency_client && !empty($agency_client->logo_url) ? $agency_client->logo_url : $agency_brand_logo_url;
$agency_portal_url           = $agency_client ? home_url('/client/' . $agency_client->slug . '/') : home_url('/');
?>
<header class="agency-topbar">
    <div class="agency-topbar__inner">
        <a class="agency-topbar__brand" href="<?php echo esc_url($agency_portal_url); ?>">
            <?php if ($agency_header_logo_url): ?>
                <img src="<?php echo esc_url($agency_header_logo_url); ?>" alt="<?php echo esc_attr($agency_client ? $agency_client->name : get_bloginfo('name')); ?>" class="agency-topbar__logo" onerror="this.style.display='none'">
            <?php elseif ($agency_client): ?>
                <span class="agency-avatar agency-avatar--28"><?php echo esc_html(function_exists('mb_substr') ? mb_strtoupper(mb_substr($agency_client->name, 0, 1)) : strtoupper(substr($agency_client->name, 0, 1))); ?></span>
                <span class="agency-topbar__name"><?php echo esc_html($agency_client->name); ?></span>
            <?php else: ?>
                <span class="agency-topbar__name"><?php echo esc_html(get_bloginfo('name')); ?></span>
            <?php endif; ?>
        </a>
        <?php if ($agency_is_token_auth): ?>
            <a href="#" class="agency-btn agency-btn-sm agency-btn-ghost agency-token-logout">
                <?php Agency_Icons::render_glyph('logout', 16); ?>
                <?php esc_html_e('Close session', 'agency-client-portal'); ?>
            </a>
        <?php endif; ?>
    </div>
</header>

<?php if ($agency_is_token_auth): ?>
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
