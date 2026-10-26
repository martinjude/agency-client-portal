<?php
/**
 * Shortcodes
 *
 * @package Agency
 */

if (!defined('ABSPATH')) {
    exit;
}

class Agency_Shortcodes {
    
    /**
     * Constructeur
     */
    public function __construct() {
        add_shortcode('agency_client_portal', array($this, 'client_portal'));
        add_shortcode('agency_projects_list', array($this, 'projects_list'));
        add_shortcode('agency_project_view', array($this, 'project_view'));
        add_shortcode('agency_storyboard', array($this, 'storyboard'));
    }
    
    /**
     * Shortcode : Portail client
     * Usage: [agency_client_portal]
     * @deprecated 1.3.0 Utiliser le système de liens directs avec tokens à la place
     */
    public function client_portal($atts) {
        // NOUVEAU : Vérifier d'abord l'authentification par token
        $authenticated_client = Agency_Security::get_authenticated_client();
        if ($authenticated_client) {
            // Client authentifié par token : rediriger vers son espace
            $redirect_url = home_url('/client/' . $authenticated_client->slug . '/');
            return wp_get_inline_script_tag('window.location.href="' . esc_js($redirect_url) . '";');
        }
        
        // ANCIEN : Fallback pour les utilisateurs WordPress
        if (!is_user_logged_in()) {
            return '<div class="agency-notice agency-notice-info">' . 
                   '<p>' . __('⚠️ This shortcode is deprecated. Use the direct access links generated in the admin.', 'agency-client-portal') . '</p>' .
                   '<p>' . sprintf(/* translators: %s: login URL */ __('For the team: <a href="%s">Log in here</a>', 'agency-client-portal'), esc_url(wp_login_url())) . '</p>' .
                   '</div>';
        }
        
        ob_start();
        include AGENCY_PLUGIN_DIR . 'templates/shortcodes/client-portal.php';
        return ob_get_clean();
    }
    
    /**
     * Shortcode : Liste des projets
     * Usage: [agency_projects_list client_id="123"]
     */
    public function projects_list($atts) {
        $atts = shortcode_atts(
            array(
                'client_id' => 0,
            ), 
            $atts
        );
        
        if (!$atts['client_id']) {
            return '<p>' . __('Client ID required', 'agency-client-portal') . '</p>';
        }
        
        $projects = Agency_Project::get_by_client($atts['client_id']);
        
        ob_start();
        include AGENCY_PLUGIN_DIR . 'templates/shortcodes/projects-list.php';
        return ob_get_clean();
    }
    
    /**
     * Shortcode : Vue projet
     * Usage: [agency_project_view project_id="456"]
     */
    public function project_view($atts) {
        $atts = shortcode_atts(
            array(
                'project_id' => 0,
            ), 
            $atts
        );
        
        if (!$atts['project_id']) {
            return '<p>' . __('Project ID required', 'agency-client-portal') . '</p>';
        }
        
        $project = Agency_Project::get_by_id($atts['project_id']);
        
        if (!$project) {
            return '<p>' . __('Project not found', 'agency-client-portal') . '</p>';
        }
        
        $blocks = Agency_Block::get_by_project($atts['project_id']);
        
        ob_start();
        include AGENCY_PLUGIN_DIR . 'templates/shortcodes/project-view.php';
        return ob_get_clean();
    }
    
    /**
     * Shortcode : Storyboard
     * Usage: [agency_storyboard block_id="789"]
     */
    public function storyboard($atts) {
        $atts = shortcode_atts(
            array(
                'block_id' => 0,
            ), 
            $atts
        );
        
        if (!$atts['block_id']) {
            return '<p>' . __('Block ID required', 'agency-client-portal') . '</p>';
        }
        
        $block = Agency_Block::get_by_id($atts['block_id']);
        
        if (!$block || $block->type !== 'storyboard') {
            return '<p>' . __('Storyboard not found', 'agency-client-portal') . '</p>';
        }
        
        $assets = Agency_Asset::get_by_block($atts['block_id']);
        $comments = Agency_Comment::get_by_block($atts['block_id']);
        
        ob_start();
        include AGENCY_PLUGIN_DIR . 'templates/shortcodes/storyboard.php';
        return ob_get_clean();
    }
}
