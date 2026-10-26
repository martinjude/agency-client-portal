<?php
/**
 * Gestion des rôles et capabilities
 *
 * @package Agency
 */

if (!defined('ABSPATH')) {
    exit;
}

class Agency_Roles {
    
    /**
     * Créer les rôles personnalisés
     */
    public function create_roles() {
        // Rôle : Agency Manager (membre de l'agence)
        add_role('agency_manager', __('Agency Manager', 'agency-client-portal'), array(
            'read' => true,
            'edit_posts' => true,
            'upload_files' => true,
            // Capabilities Agency
            'manage_agency_clients' => true,
            'manage_agency_projects' => true,
            'manage_agency_blocks' => true,
            'view_agency_comments' => true,
            'delete_agency_content' => true,
            'upload_agency_assets' => true,
        ));
        
        // Rôle : Agency Client (client avec accès restreint)
        add_role('agency_client', __('Agency Client', 'agency-client-portal'), array(
            'read' => true,
            // Capabilities Agency
            'view_agency_projects' => true,
            'view_agency_blocks' => true,
            'add_agency_comments' => true,
        ));
        
        // Ajouter les capabilities aux administrateurs
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('manage_agency_clients');
            $admin_role->add_cap('manage_agency_projects');
            $admin_role->add_cap('manage_agency_blocks');
            $admin_role->add_cap('view_agency_comments');
            $admin_role->add_cap('delete_agency_content');
            $admin_role->add_cap('upload_agency_assets');
        }
    }
    
    /**
     * Supprimer les rôles personnalisés
     */
    public function remove_roles() {
        remove_role('agency_manager');
        remove_role('agency_client');
        
        // Retirer les capabilities des administrateurs
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->remove_cap('manage_agency_clients');
            $admin_role->remove_cap('manage_agency_projects');
            $admin_role->remove_cap('manage_agency_blocks');
            $admin_role->remove_cap('view_agency_comments');
            $admin_role->remove_cap('delete_agency_content');
            $admin_role->remove_cap('upload_agency_assets');
        }
    }
}
