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
        
        // CSS — tokens du design system, primitives UI, puis partials dans l'ordre
        // du cascade (_app.css = coque, en-têtes de page et cartes). Le dernier
        // handle garde le nom 'agency-frontend' : le CSS dynamique (couleur client)
        // et les dépendances existantes s'y greffent.
        wp_enqueue_style('agency-tokens', AGENCY_PLUGIN_URL . 'assets/css/tokens.css', array(), AGENCY_VERSION);
        wp_enqueue_style('agency-ui-css', AGENCY_PLUGIN_URL . 'assets/css/ui.css', array('agency-tokens'), AGENCY_VERSION);

        $css_dir = AGENCY_PLUGIN_URL . 'assets/css/frontend/';
        wp_enqueue_style('agency-frontend-base',        $css_dir . '_base.css',        array('agency-ui-css'), AGENCY_VERSION);
        wp_enqueue_style('agency-frontend-blocks',      $css_dir . '_blocks.css',      array('agency-frontend-base'), AGENCY_VERSION);
        wp_enqueue_style('agency-frontend-block-views', $css_dir . '_block-views.css', array('agency-frontend-blocks'), AGENCY_VERSION);
        wp_enqueue_style('agency-frontend-components',  $css_dir . '_components.css',  array('agency-frontend-block-views'), AGENCY_VERSION);
        wp_enqueue_style('agency-frontend-app',         $css_dir . '_app.css',         array('agency-frontend-components'), AGENCY_VERSION);
        wp_enqueue_style('agency-frontend-block-pages', $css_dir . '_block-pages.css', array('agency-frontend-app'), AGENCY_VERSION);
        wp_enqueue_style('agency-frontend',             $css_dir . '_sidebar.css',     array('agency-frontend-block-pages'), AGENCY_VERSION);

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
        wp_enqueue_script('agency-ui', AGENCY_PLUGIN_URL . 'assets/js/ui.js', array('wp-i18n'), AGENCY_VERSION, true);
        wp_set_script_translations('agency-ui', 'agency-client-portal', AGENCY_PLUGIN_DIR . 'languages');

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
        
        // Couleur d'accent unique du client (seule personnalisation conservée) :
        // alimente --ag-client et ses variantes survol / fond atténué.
        $accent = sanitize_hex_color($client->primary_color);
        if (!$accent) {
            return;
        }
        $hover = self::mix_hex($accent, '#ffffff', 0.15);
        $soft  = self::mix_hex($accent, '#18181b', 0.82);
        $custom_css = "body.agency-portal { --ag-client: {$accent}; --ag-client-hover: {$hover}; --ag-client-soft: {$soft}; }";

        wp_add_inline_style('agency-frontend', $custom_css);
    }
    
    /**
     * Mélange deux couleurs hex : $ratio = part de $with (0 → $hex, 1 → $with).
     */
    private static function mix_hex($hex, $with, $ratio) {
        $a = sscanf(ltrim($hex, '#'), '%02x%02x%02x');
        $b = sscanf(ltrim($with, '#'), '%02x%02x%02x');
        $out = '#';
        for ($i = 0; $i < 3; $i++) {
            $out .= sprintf('%02x', (int) round($a[$i] * (1 - $ratio) + $b[$i] * $ratio));
        }
        return $out;
    }

    /**
     * Ajouter des classes au body
     */
    public function add_body_classes($classes) {
        if ($this->is_agency_page()) {
            $classes[] = 'agency-portal';

            $is_manager = current_user_can('manage_agency_projects');
            if ($is_manager) {
                // La sidebar agence est présente sur toutes les pages manager
                $classes[] = 'agency-manager';
                $classes[] = 'agency-has-sidebar';
            } else {
                $classes[] = 'agency-client';
            }

            if (!empty(get_query_var('agency_edit_client'))) {
                $classes[] = 'agency-edit-client-page';
            } elseif (!empty(get_query_var('agency_dashboard'))) {
                $classes[] = 'agency-dashboard-page';
            } elseif (get_query_var('agency_block')) {
                $classes[] = 'agency-block-view';
            } elseif (get_query_var('agency_project')) {
                $classes[] = 'agency-project-view';
            } else {
                $classes[] = 'agency-client-view';
            }
        }

        return $classes;
    }
    
    /**
     * Masquer la barre d'admin pour les clients
     */
    public function hide_admin_bar_for_clients($show) {
        // Pages Agency en pleine page : l'accès à l'admin WP passe par la sidebar
        if ($this->is_agency_page()) {
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
