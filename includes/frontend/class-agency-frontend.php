<?php
/**
 * Frontend - Gestion de l'interface utilisateur côté client
 *
 * @package Agency
 */

if (!defined('ABSPATH')) {
    exit;
}

class Agency_Frontend {
    
    /**
     * Constructeur
     */
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_filter('body_class', array($this, 'add_body_classes'));
        add_filter('show_admin_bar', array($this, 'hide_admin_bar_for_clients'));
        // Déconnexion session token (clients non connectés WP)
        add_action('wp_ajax_agency_token_logout', array($this, 'handle_token_logout'));
        add_action('wp_ajax_nopriv_agency_token_logout', array($this, 'handle_token_logout'));
        // Fermeture bannière plan gratuit (managers uniquement)
        add_action('wp_ajax_agency_dismiss_manager_banner', array($this, 'ajax_dismiss_manager_banner'));
    }
    
    /**
     * Charger les assets frontend
     */
    public function enqueue_frontend_assets() {
        // Vérifier si on est sur une page Agency
        if (!$this->is_agency_page()) {
            return;
        }
        
        // CSS — frontend.css scindé en partials chargés dans l'ordre du cascade.
        // Le dernier handle conserve le nom 'agency-frontend' : le CSS dynamique
        // (wp_add_inline_style) et les dépendances existantes s'y greffent et se
        // chargent donc après tous les partials.
        // Tokens (variables CSS) puis primitives UI : source de vérité unique,
        // chargées avant les partials
        wp_enqueue_style('agency-tokens', AGENCY_PLUGIN_URL . 'assets/css/tokens.css', array(), AGENCY_VERSION);
        wp_enqueue_style('agency-ui-css', AGENCY_PLUGIN_URL . 'assets/css/ui.css', array('agency-tokens'), AGENCY_VERSION);

        $css_dir = AGENCY_PLUGIN_URL . 'assets/css/frontend/';
        wp_enqueue_style('agency-frontend-base',        $css_dir . '_base.css',        array('agency-ui-css'), AGENCY_VERSION);
        wp_enqueue_style('agency-frontend-blocks',      $css_dir . '_blocks.css',      array('agency-frontend-base'), AGENCY_VERSION);
        wp_enqueue_style('agency-frontend-block-views', $css_dir . '_block-views.css', array('agency-frontend-blocks'), AGENCY_VERSION);
        wp_enqueue_style('agency-frontend-components',  $css_dir . '_components.css',  array('agency-frontend-block-views'), AGENCY_VERSION);
        wp_enqueue_style('agency-frontend-overrides',   $css_dir . '_overrides.css',   array('agency-frontend-components'), AGENCY_VERSION);
        wp_enqueue_style('agency-frontend',             $css_dir . '_sidebar.css',     array('agency-frontend-overrides'), AGENCY_VERSION);

        // Code block assets — chargés uniquement sur les pages de type "code"
        $block_id = get_query_var('agency_block');
        if ($block_id) {
            $block = Agency_Block::get_by_id(intval($block_id));
            if ($block && $block->type === 'code') {
                wp_enqueue_style('agency-prism', AGENCY_PLUGIN_URL . 'assets/css/prism-dark.css', array('agency-frontend'), AGENCY_VERSION);
                wp_enqueue_style('agency-code-block', AGENCY_PLUGIN_URL . 'assets/css/code-block.css', array('agency-frontend'), AGENCY_VERSION);
                wp_enqueue_script('agency-prism', AGENCY_PLUGIN_URL . 'assets/js/prism.js', array(), AGENCY_VERSION, false);
            }
        }

        // Utilitaires UI partagés (debounce, etc.) — chargés avant frontend.js
        wp_enqueue_script('agency-ui', AGENCY_PLUGIN_URL . 'assets/js/ui.js', array(), AGENCY_VERSION, true);

        // JavaScript frontend (no emoji-picker dependency)
        wp_enqueue_script('agency-frontend', AGENCY_PLUGIN_URL . 'assets/js/frontend.js', array('jquery', 'agency-ui', 'wp-i18n'), AGENCY_VERSION, true);
        wp_set_script_translations('agency-frontend', 'agency-client-portal', AGENCY_PLUGIN_DIR . 'languages');

        // Rendu emoji moderne (Twemoji SVG via CDN). Chargé avant l'emoji-picker
        // pour que celui-ci puisse appeler AgencyEmoji.html() lors du rendu.
        wp_enqueue_script('agency-emoji-render', AGENCY_PLUGIN_URL . 'assets/js/emoji-render.js', array(), AGENCY_VERSION, true);

        // Drag & Drop + Emoji picker (managers only)
        if (current_user_can('manage_agency_projects')) {
            wp_enqueue_style('agency-emoji-picker', AGENCY_PLUGIN_URL . 'assets/css/emoji-picker.css', array('agency-frontend'), AGENCY_VERSION);
            wp_enqueue_script('agency-emoji-picker', AGENCY_PLUGIN_URL . 'assets/js/emoji-picker.js', array('jquery', 'wp-i18n'), AGENCY_VERSION, true); // true = footer
            wp_set_script_translations('agency-emoji-picker', 'agency-client-portal', AGENCY_PLUGIN_DIR . 'languages');
            wp_enqueue_script('sortablejs', AGENCY_PLUGIN_URL . 'assets/js/sortable.min.js', array(), '1.15.0', true);
            wp_enqueue_script('agency-manager', AGENCY_PLUGIN_URL . 'assets/js/manager.js', array('jquery', 'sortablejs', 'wp-i18n'), AGENCY_VERSION, true);
            wp_set_script_translations('agency-manager', 'agency-client-portal', AGENCY_PLUGIN_DIR . 'languages');
            wp_enqueue_media();
        }
        
        // Localisation
        wp_localize_script('agency-frontend', 'agencyData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => rest_url('agency/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'admin_nonce' => wp_create_nonce('agency_admin_nonce'),
            'logout_nonce' => wp_create_nonce('agency_token_logout'),
            'current_user_id' => get_current_user_id(),
            'can_manage' => current_user_can('manage_agency_projects'),
            'client_slug' => sanitize_title(get_query_var('agency_client', '')),
            'icons' => array(
                'edit'     => Agency_Icons::get('edit', 16),
                'trash'    => Agency_Icons::get('trash', 16),
                'save'     => Agency_Icons::get('save', 16),
                'download' => Agency_Icons::get('download', 16),
                'link'     => Agency_Icons::get('link', 16),
            ),
            'strings' => array(
                'loading' => __('Loading...', 'agency-client-portal'),
                'error' => __('An error occurred', 'agency-client-portal'),
                'success' => __('Operation successful', 'agency-client-portal'),
                'confirm_delete' => __('Are you sure you want to delete this item?', 'agency-client-portal'),
            ),
        ));

        // Charger la police UI si configurée
        $ui_font = get_option('agency_ui_font', 'default');
        $font_family = '';
        $font_weights = ':wght@300;400;500;600;700';

        switch ($ui_font) {
            case 'questrial':
                wp_enqueue_style('agency-font-questrial', 'https://fonts.googleapis.com/css2?family=Questrial&display=block', array(), null);
                $font_family = "'Questrial', 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji', sans-serif";
                break;
            case 'poppins':
                wp_enqueue_style('agency-font-poppins', 'https://fonts.googleapis.com/css2?family=Poppins' . $font_weights . '&display=block', array(), null);
                $font_family = "'Poppins', 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji', sans-serif";
                break;
            case 'inter':
                wp_enqueue_style('agency-font-inter', 'https://fonts.googleapis.com/css2?family=Inter' . $font_weights . '&display=block', array(), null);
                $font_family = "'Inter', 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji', sans-serif";
                break;
            case 'roboto':
                wp_enqueue_style('agency-font-roboto', 'https://fonts.googleapis.com/css2?family=Roboto' . $font_weights . '&display=block', array(), null);
                $font_family = "'Roboto', 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji', sans-serif";
                break;
            case 'montserrat':
                wp_enqueue_style('agency-font-montserrat', 'https://fonts.googleapis.com/css2?family=Montserrat' . $font_weights . '&display=block', array(), null);
                $font_family = "'Montserrat', 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji', sans-serif";
                break;
            case 'lato':
                wp_enqueue_style('agency-font-lato', 'https://fonts.googleapis.com/css2?family=Lato' . $font_weights . '&display=block', array(), null);
                $font_family = "'Lato', 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji', sans-serif";
                break;
            case 'open-sans':
                wp_enqueue_style('agency-font-opensans', 'https://fonts.googleapis.com/css2?family=Open+Sans' . $font_weights . '&display=block', array(), null);
                $font_family = "'Open Sans', 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji', sans-serif";
                break;
            case 'nunito':
                wp_enqueue_style('agency-font-nunito', 'https://fonts.googleapis.com/css2?family=Nunito' . $font_weights . '&display=block', array(), null);
                $font_family = "'Nunito', 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji', sans-serif";
                break;
            case 'playfair':
                wp_enqueue_style('agency-font-playfair', 'https://fonts.googleapis.com/css2?family=Playfair+Display' . $font_weights . '&display=block', array(), null);
                $font_family = "'Playfair Display', 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji', serif";
                break;
            case 'raleway':
                wp_enqueue_style('agency-font-raleway', 'https://fonts.googleapis.com/css2?family=Raleway' . $font_weights . '&display=block', array(), null);
                $font_family = "'Raleway', 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji', sans-serif";
                break;
            case 'jetbrains-mono':
                wp_enqueue_style('agency-font-jetbrains', 'https://fonts.googleapis.com/css2?family=JetBrains+Mono' . $font_weights . '&display=block', array(), null);
                $font_family = "'JetBrains Mono', 'Fira Code', 'Consolas', monospace";
                break;
        }

        if (!empty($font_family)) {
            wp_add_inline_style('agency-frontend', "body.agency-portal, .agency-wrapper, .agency-wrapper * { font-family: {$font_family}; }");
        }

        // Charger les styles de branding globaux
        $this->load_global_branding();

        // Charger les couleurs du client
        $this->load_client_branding();
    }
    
    /**
     * Vérifier si on est sur une page Agency
     */
    private function is_agency_page() {
        return !empty(get_query_var('agency_client'))
            || !empty(get_query_var('agency_edit_client'))
            || !empty(get_query_var('agency_dashboard'));
    }

    /**
     * Charger le branding global (bordures, couleurs de fond, etc.)
     */
    private function load_global_branding() {
        // Guard: s'assurer que Agency_Color_Utils est disponible
        if (!class_exists('Agency_Color_Utils')) {
            return;
        }
        
        $bg_light        = sanitize_hex_color(get_option('agency_bg_color_light', '#f8f9fa')) ?: '#f8f9fa';
        $bg_dark         = sanitize_hex_color(get_option('agency_bg_color_dark',  '#0b0f14')) ?: '#0b0f14';
        $border_color    = sanitize_hex_color(get_option('agency_card_border_color', '#e0e0e0')) ?: '#e0e0e0';
        $border_radius   = max(0, intval(get_option('agency_card_border_radius', 25)));
        $border_width    = max(0, floatval(get_option('agency_card_border_width', 0)));

        $palette_light = Agency_Color_Utils::derive_palette($bg_light);
        $palette_dark  = Agency_Color_Utils::derive_palette($bg_dark);

        $radius_small  = max(4, $border_radius - 12);
        $radius_button = max(4, min($border_radius, 14));

        // Note : les thèmes ne définissent JAMAIS la couleur d'accent.
        // L'accent unique vient du client (--agency-primary-color) via
        // load_client_branding(). Les thèmes pilotent uniquement le chrome
        // (fond, cartes, bordures, rayon, police).

        $css = "
        :root {
            --agency-light-bg:          {$bg_light};
            --agency-text-color:        {$palette_light['text_color']};
            --agency-text-muted:        {$palette_light['text_muted']};
            --agency-border-color:      {$palette_light['border_color']};
            --agency-shadow:            {$palette_light['shadow']};
            --agency-shadow-hover:      {$palette_light['shadow_hover']};
            --agency-shadow-strong:     {$palette_light['shadow_strong']};
            --agency-header-bg:         {$palette_light['header_bg']};
            --agency-panel-bg:          {$palette_light['panel_bg']};
            --agency-card-border-color: {$border_color};
            --agency-card-border-width: {$border_width}px;
            --agency-radius:            {$border_radius}px;
            --agency-radius-small:      {$radius_small}px;
            --agency-radius-button:     {$radius_button}px;
        }
        .agency-project-card,
        .agency-block {
            border: var(--agency-card-border-width) solid var(--agency-card-border-color);
            /* Fallback for subpixel borders */
            box-shadow: 0 0 0 var(--agency-card-border-width) var(--agency-card-border-color);
        }

        /* === MODE SOMBRE — dérivé dynamiquement de agency_bg_color_dark === */
        .agency-theme-dark {
            --agency-light-bg:          {$bg_dark};
            --agency-secondary-color:   {$palette_dark['text_color']};
            --agency-text-color:        {$palette_dark['text_color']};
            --agency-text-muted:        {$palette_dark['text_muted']};
            --agency-border-color:      {$palette_dark['border_color']};
            --agency-shadow:            {$palette_dark['shadow']};
            --agency-shadow-hover:      {$palette_dark['shadow_hover']};
            --agency-shadow-strong:     {$palette_dark['shadow_strong']};
            --agency-header-bg:         {$palette_dark['header_bg']};
            --agency-panel-bg:          {$palette_dark['panel_bg']};
        }
        .agency-theme-dark .agency-header { color: {$palette_dark['text_color']}; }
        .agency-theme-dark .agency-footer {
            background: {$bg_dark};
            color: {$palette_dark['text_muted']};
        }
        .agency-theme-dark .agency-project-card,
        .agency-theme-dark .block-header-full,
        .agency-theme-dark .agency-block,
        .agency-theme-dark .block-content-full,
        .agency-theme-dark .block-column-left,
        .agency-theme-dark .block-column-right,
        .agency-theme-dark .block-comments,
        .agency-theme-dark .storyboard-item,
        .agency-theme-dark .storyboard-description {
            background: {$palette_dark['panel_bg']};
            color: var(--agency-text-color);
        }
        .agency-theme-dark .agency-btn-secondary {
            background:    {$palette_dark['btn_secondary_bg']};
            border-color:  {$palette_dark['btn_secondary_border']};
            color: white;
        }
        .agency-theme-dark .agency-btn-secondary:hover {
            background:   {$palette_dark['btn_secondary_hover']};
            border-color: {$palette_dark['btn_secondary_border']};
        }
        .agency-theme-dark .project-blocks-list,
        .agency-theme-dark .asset-comments-list .comment-item {
            background: {$palette_dark['list_bg']};
        }
        .agency-theme-dark .script-editor,
        .agency-theme-dark .script-content-display,
        .agency-theme-dark .asset-comment-input {
            background:   {$palette_dark['input_bg']};
            color:        var(--agency-text-color);
            border-color: var(--agency-border-color);
        }
        .agency-theme-dark .agency-title {
            border-color: {$palette_dark['title_border']};
            background:   {$palette_dark['title_bg']};
        }
        ";

        // Fond de la sidebar : surcharge dédiée (agency_sidebar_bg_color, qui peut
        // être une couleur solide OU un dégradé via un preset), sinon le fond du thème actif.
        $sidebar_bg_override = (string) get_option('agency_sidebar_bg_color', '');
        $theme_mode          = (string) get_option('agency_theme_mode', 'dark');
        $sidebar_bg_default  = ($theme_mode === 'light') ? $bg_light : $bg_dark;
        $sidebar_bg          = $sidebar_bg_override !== '' ? $sidebar_bg_override : $sidebar_bg_default;

        // Contraste : calculé sur la couleur solide effective (les dégradés reviennent sur le défaut).
        $sidebar_solid_for_contrast = sanitize_hex_color($sidebar_bg) ?: $sidebar_bg_default;
        $sidebar_is_dark     = Agency_Color_Utils::is_dark($sidebar_solid_for_contrast);
        $sidebar_text        = $sidebar_is_dark ? 'rgba(255,255,255,0.75)' : 'rgba(0,0,0,0.65)';
        $sidebar_text_hover  = $sidebar_is_dark ? '#ffffff' : '#000000';
        $sidebar_accent      = $sidebar_is_dark ? 'rgba(255,255,255,0.10)' : 'rgba(0,0,0,0.07)';
        $sidebar_active      = $sidebar_is_dark ? 'rgba(255,255,255,0.18)' : 'rgba(0,0,0,0.12)';
        $sidebar_border      = $sidebar_is_dark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)';
        $sidebar_muted       = $sidebar_is_dark ? 'rgba(255,255,255,0.35)' : 'rgba(0,0,0,0.35)';

        $css .= "
        :root {
            --agency-sidebar-bg:          {$sidebar_bg};
            --agency-sidebar-text:        {$sidebar_text};
            --agency-sidebar-text-hover:  {$sidebar_text_hover};
            --agency-sidebar-accent:      {$sidebar_accent};
            --agency-sidebar-active-bg:   {$sidebar_active};
            --agency-sidebar-border:      {$sidebar_border};
            --agency-sidebar-muted:       {$sidebar_muted};
        }
        ";

        // Thèmes "full gradient" : dégradés sur le fond, les cartes et le header
        // (appliqués après les règles solides pour les surcharger).
        $gradients = json_decode((string) get_option('agency_theme_gradients', ''), true);
        if (is_array($gradients) && !empty($gradients)) {
            if (!empty($gradients['bg'])) {
                $css .= "
        .agency-theme-dark .agency-wrapper { background: {$gradients['bg']}; min-height: 100vh; }
        .agency-theme-dark .agency-main { background: transparent; }
        .agency-theme-dark .agency-client-cover::after,
        .agency-theme-dark .agency-project-cover::after {
            background: {$gradients['bg']};
            mask-image: linear-gradient(to bottom, transparent 0%, black 80%);
            -webkit-mask-image: linear-gradient(to bottom, transparent 0%, black 80%);
        }
        ";
            }
            if (!empty($gradients['card'])) {
                $css .= "
        .agency-theme-dark .agency-project-card,
        .agency-theme-dark .agency-block { background: {$gradients['card']}; }
        ";
            }
            if (!empty($gradients['header'])) {
                $css .= "
        .agency-theme-dark .agency-header-fixed { background: {$gradients['header']}; }
        ";
            }
        }

        wp_add_inline_style('agency-frontend', $css);
    }
    
    /**
     * Charger le branding du client (couleurs personnalisées)
     */
    private function load_client_branding() {
        // Le client est résolu depuis le slug (vue portail) ou depuis l'ID
        // (page d'édition frontend via agency_edit_client) pour que l'accent
        // soit aussi injecté sur la page d'édition.
        $client_slug = get_query_var('agency_client');
        if (!empty($client_slug)) {
            $client = Agency_Client::get_by_slug($client_slug);
        } elseif (!empty(get_query_var('agency_edit_client'))) {
            $client = Agency_Client::get_by_id(intval(get_query_var('agency_edit_client')));
        } else {
            return;
        }
        if (!$client) {
            return;
        }
        
        // Injecter la couleur d'accent unique du client.
        // Note : --agency-secondary-color est conservé en CSS avec un défaut
        // (blanc) pour la compatibilité ascendante. Le client ne le configure
        // plus depuis l'UI — un seul accent.
        $custom_css = "
            :root {
                --agency-primary-color: {$client->primary_color};
            }
            .agency-header {
                background-color: {$client->primary_color};
            }
            .agency-btn-primary {
                background-color: {$client->primary_color};
            }
            .agency-btn-primary:hover {
                opacity: 0.9;
            }
        ";
        
        wp_add_inline_style('agency-frontend', $custom_css);
    }
    
    /**
     * Ajouter des classes au body
     */
    public function add_body_classes($classes) {
        if ($this->is_agency_page()) {
            $classes[] = 'agency-portal';

            // Page d'édition frontend
            if (!empty(get_query_var('agency_edit_client'))) {
                $classes[] = 'agency-edit-client-page';
                $classes[] = 'agency-manager';
                $classes[] = 'agency-has-sidebar';
            } elseif (!empty(get_query_var('agency_dashboard'))) {
                $classes[] = 'agency-dashboard-page';
                $classes[] = 'agency-manager';
                // Pas de agency-has-sidebar — layout pleine largeur
            } else {
                if (current_user_can('manage_agency_projects')) {
                    $classes[] = 'agency-manager';
                    $classes[] = 'agency-has-sidebar';
                } else {
                    $classes[] = 'agency-client';
                }

                // Ajouter la classe de la vue actuelle
                if (get_query_var('agency_block')) {
                    $classes[] = 'agency-block-view';
                } elseif (get_query_var('agency_project')) {
                    $classes[] = 'agency-project-view';
                } else {
                    $classes[] = 'agency-client-view';
                }
            }

            // Thème clair/sombre depuis les paramètres
            $theme_mode = get_option('agency_theme_mode', 'light');
            if ($theme_mode === 'dark') {
                $classes[] = 'agency-theme-dark';
            } else {
                $classes[] = 'agency-theme-light';
            }
        }

        return $classes;
    }
    
    /**
     * Masquer la barre d'admin pour les clients
     */
    public function hide_admin_bar_for_clients($show) {
        // Toujours masquer sur le dashboard frontend (page pleine largeur sans barre WP)
        if (!empty(get_query_var('agency_dashboard'))) {
            return false;
        }
        if ($this->is_agency_page() && !current_user_can('manage_agency_projects')) {
            return false;
        }

        return $show;
    }

    /**
     * Handler AJAX : fermeture de la bannière plan gratuit (manager frontend)
     */
    public function ajax_dismiss_manager_banner() {
        check_ajax_referer( 'agency_dismiss_manager_banner', 'nonce' );
        update_option( 'agency_frontend_manager_banner_dismissed_at', time(), false );
        wp_send_json_success();
    }

    /**
     * Handler AJAX : déconnexion session token client
     */
    public function handle_token_logout() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'agency_token_logout')) {
            wp_send_json_error(__('Security error', 'agency-client-portal'));
        }

        Agency_Security::clear_auth_cookie();
        wp_send_json_success();
    }

}
