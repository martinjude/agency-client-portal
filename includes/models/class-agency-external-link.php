<?php
/**
 * Modèle External Link
 *
 * @package Agency
 */

if (!defined('ABSPATH')) {
    exit;
}

class Agency_External_Link {
    
    /**
     * Obtenir tous les liens d'un bloc
     */
    public static function get_by_block($block_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_external_links';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE block_id = %d ORDER BY order_position ASC, created_at DESC",
            $block_id
        ));
    }
    
    /**
     * Obtenir tous les liens d'un projet
     */
    public static function get_by_project($project_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_external_links';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE project_id = %d AND block_id IS NULL ORDER BY order_position ASC, created_at DESC",
            $project_id
        ));
    }
    
    /**
     * Obtenir un lien par ID
     */
    public static function get_by_id($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_external_links';
        
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }
    
    /**
     * Créer un nouveau lien
     */
    public static function create($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_external_links';
        
        $defaults = array(
            'block_id' => null,
            'project_id' => null,
            'url' => '',
            'text' => '',
            'emoji' => '🔗',
            'order_position' => 0,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Validation: doit avoir soit block_id soit project_id
        if (empty($data['block_id']) && empty($data['project_id'])) {
            return new WP_Error('missing_reference', __('A link must be attached to a block or a project', 'agency-client-portal'));
        }
        
        // Validation: ne peut pas avoir les deux
        if (!empty($data['block_id']) && !empty($data['project_id'])) {
            return new WP_Error('invalid_reference', __('A link cannot be attached to both a block and a project', 'agency-client-portal'));
        }
        
        $result = $wpdb->insert($table, $data);
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return new WP_Error('db_error', __('Error while creating the link', 'agency-client-portal'));
    }
    
    /**
     * Mettre à jour un lien
     */
    public static function update($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_external_links';
        
        $allowed_fields = array('url', 'text', 'emoji', 'order_position');
        $update_data = array();
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $update_data[$field] = $data[$field];
            }
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $wpdb->update(
            $table,
            $update_data,
            array('id' => $id)
        );
        
        return $result !== false;
    }
    
    /**
     * Supprimer un lien
     */
    public static function delete($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_external_links';

        return $wpdb->delete($table, array('id' => $id));
    }

    /**
     * Supprime en lot tous les liens rattachés à un projet (au niveau projet
     * ou rattachés à un bloc du projet).
     *
     * @param int        $project_id
     * @param array<int> $block_ids Optionnel : liste de blocs si déjà chargée.
     * @return int|false Nombre de lignes supprimées.
     */
    public static function delete_by_project($project_id, array $block_ids = array()) {
        $project_id = intval($project_id);
        if (!$project_id) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'agency_external_links';

        $block_ids = array_values(array_filter(array_map('intval', $block_ids)));
        if (!empty($block_ids)) {
            $placeholders = implode(',', array_fill(0, count($block_ids), '%d'));
            $args = array_merge(array($project_id), $block_ids);
            return $wpdb->query($wpdb->prepare(
                "DELETE FROM {$table} WHERE project_id = %d OR block_id IN ({$placeholders})",
                $args
            ));
        }

        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE project_id = %d",
            $project_id
        ));
    }
    
    /**
     * Compter les liens d'un bloc
     */
    public static function count_by_block($block_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_external_links';
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE block_id = %d",
            $block_id
        ));
    }
    
    /**
     * Compter les liens d'un projet
     */
    public static function count_by_project($project_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_external_links';
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE project_id = %d AND block_id IS NULL",
            $project_id
        ));
    }
    
    /**
     * Mettre à jour l'ordre des liens (une seule requête CASE WHEN).
     */
    public static function update_order($link_ids) {
        $link_ids = array_values(array_filter(array_map('intval', (array) $link_ids)));
        if (empty($link_ids)) {
            return true;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'agency_external_links';

        $cases = '';
        $case_args = array();
        foreach ($link_ids as $position => $link_id) {
            $cases .= ' WHEN %d THEN %d';
            $case_args[] = $link_id;
            $case_args[] = $position;
        }
        $placeholders = implode(',', array_fill(0, count($link_ids), '%d'));
        $sql = "UPDATE {$table} SET order_position = CASE id{$cases} END WHERE id IN ({$placeholders})";
        $args = array_merge($case_args, $link_ids);

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql ne contient que des placeholders %d construits ci-dessus.
        return $wpdb->query($wpdb->prepare($sql, $args)) !== false;
    }
}
