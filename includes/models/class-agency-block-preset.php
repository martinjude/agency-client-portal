<?php
/**
 * Modèle Block Preset
 *
 * @package Agency
 */

if (!defined('ABSPATH')) {
    exit;
}

class Agency_Block_Preset {

    /**
     * Créer un preset
     *
     * @param array $data { name, data (array), created_by, thumbnail_url (optional) }
     * @return int|false ID du preset créé, ou false en cas d'erreur.
     */
    public static function create($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_block_presets';

        $insert = array(
            'name'          => sanitize_text_field($data['name']),
            'data'          => wp_json_encode($data['data']),
            'thumbnail_url' => !empty($data['thumbnail_url']) ? esc_url_raw($data['thumbnail_url']) : null,
            'created_by'    => intval($data['created_by']),
            'created_at'    => current_time('mysql'),
        );

        $wpdb->insert($table, $insert);
        return $wpdb->insert_id ?: false;
    }

    /**
     * Obtenir tous les presets
     *
     * @return array
     */
    public static function get_all() {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_block_presets';
        return $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
    }

    /**
     * Obtenir un preset par ID
     *
     * @param int $id
     * @return object|null
     */
    public static function get_by_id($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_block_presets';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }

    /**
     * Supprimer un preset
     *
     * @param int $id
     * @return bool
     */
    public static function delete($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_block_presets';
        return (bool) $wpdb->delete($table, array('id' => intval($id)));
    }
}
