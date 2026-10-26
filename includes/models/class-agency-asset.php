<?php
/**
 * Modèle Asset
 *
 * @package Agency
 */

if (!defined('ABSPATH')) {
    exit;
}

class Agency_Asset {
    
    /**
     * Obtenir tous les assets d'un bloc
     */
    public static function get_by_block($block_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_assets';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE block_id = %d ORDER BY order_position ASC, created_at DESC",
            $block_id
        ));
    }
    
    /**
     * Obtenir un asset par ID
     */
    public static function get_by_id($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_assets';

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }

    /**
     * Obtenir tous les assets image d'un projet (toutes blocks confondus)
     */
    public static function get_images_by_project($project_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.id, a.original_filename, a.file_path
             FROM {$wpdb->prefix}agency_assets a
             INNER JOIN {$wpdb->prefix}agency_blocks b ON a.block_id = b.id
             WHERE b.project_id = %d AND a.file_type = 'image'
             ORDER BY a.created_at DESC",
            $project_id
        ));
    }

    /**
     * Obtenir le prochain numéro de version pour un bloc
     */
    public static function get_next_version_number($block_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_assets';
        $max = $wpdb->get_var($wpdb->prepare("SELECT MAX(version_number) FROM $table WHERE block_id = %d", $block_id));
        return $max ? intval($max) + 1 : 1;
    }
    
    /**
     * Uploader un asset
     */
    public static function upload($files, $block_id) {
        $assets_manager = agency()->assets;
        
        if (!isset($files['file'])) {
            return new WP_Error('no_file', __('No file provided', 'agency-client-portal'));
        }
        
        return $assets_manager->upload_file($files['file'], $block_id);
    }
    
    /**
     * Supprimer un asset
     */
    public static function delete($id) {
        $assets_manager = agency()->assets;
        return $assets_manager->delete_asset($id);
    }
    
    /**
     * Obtenir l'URL d'un asset
     */
    public static function get_url($id) {
        $assets_manager = agency()->assets;
        return $assets_manager->get_asset_url($id);
    }

    /**
     * Obtenir l'URL de téléchargement d'un asset.
     */
    public static function get_download_url($id) {
        $assets_manager = agency()->assets;
        return $assets_manager->get_asset_url($id, true);
    }

    /**
     * Obtenir l'URL d'aperçu d'un asset.
     */
    public static function get_preview_url($id) {
        $assets_manager = agency()->assets;
        return $assets_manager->get_asset_preview_url($id);
    }

    /**
     * Obtenir l'URL du thumbnail d'un asset
     */
    public static function get_thumbnail_url($id) {
        $assets_manager = agency()->assets;
        return $assets_manager->get_thumbnail_url($id);
    }
    
    /**
     * Créer ou récupérer un asset virtuel pour un embed vidéo
     */
    public static function get_or_create_embed_asset($block_id, $embed_url, $embed_title = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_assets';

        // Chercher si un asset existe déjà pour cet embed
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE block_id = %d AND file_type = 'video_embed' AND file_path = %s",
            $block_id,
            $embed_url
        ));

        if ($existing) {
            return $existing;
        }

        // Créer un nouvel asset pour cet embed
        $wpdb->insert(
            $table,
            array(
                'block_id' => $block_id,
                'file_type' => 'video_embed',
                'file_path' => $embed_url,
                'original_filename' => $embed_title ?: __('Embedded video', 'agency-client-portal'),
                'file_size' => 0,
                'version_number' => 1,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s', '%d', '%d', '%s')
        );

        return self::get_by_id($wpdb->insert_id);
    }

    /**
     * Obtenir le nombre d'assets par bloc
     */
    public static function count_by_block($block_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_assets';

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE block_id = %d",
            $block_id
        ));
    }
    
    /**
     * Obtenir la taille totale des assets d'un bloc (en octets)
     */
    public static function get_total_size($block_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_assets';
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(file_size) FROM $table WHERE block_id = %d",
            $block_id
        ));
    }
    
    /**
     * Mettre à jour la position d'un asset
     */
    public static function update_position($id, $position) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_assets';
        
        return $wpdb->update(
            $table,
            array('order_position' => intval($position)),
            array('id' => intval($id)),
            array('%d'),
            array('%d')
        );
    }
    
    /**
     * Mettre à jour l'ordre de plusieurs assets (une seule requête CASE WHEN).
     */
    public static function update_order($asset_orders) {
        $asset_orders = array_values(array_filter(array_map('intval', (array) $asset_orders)));
        if (empty($asset_orders)) {
            return true;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'agency_assets';

        $cases = '';
        $case_args = array();
        foreach ($asset_orders as $position => $asset_id) {
            $cases .= ' WHEN %d THEN %d';
            $case_args[] = $asset_id;
            $case_args[] = $position;
        }
        $placeholders = implode(',', array_fill(0, count($asset_orders), '%d'));
        $sql = "UPDATE {$table} SET order_position = CASE id{$cases} END WHERE id IN ({$placeholders})";
        $args = array_merge($case_args, $asset_orders);

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql ne contient que des placeholders %d construits ci-dessus.
        return $wpdb->query($wpdb->prepare($sql, $args)) !== false;
    }

    /**
     * Mettre à jour un asset (ex: voiceover)
     */
    public static function update($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_assets';
        
        $update_data = array();
        $update_formats = array();
        
        if (isset($data['voiceover'])) {
            $update_data['voiceover'] = wp_kses_post($data['voiceover']);
            $update_formats[] = '%s';
        }
        if (isset($data['version_status'])) {
            $update_data['version_status'] = sanitize_text_field($data['version_status']);
            $update_formats[] = '%s';
        }

        if (isset($data['version_label'])) {
            $update_data['version_label'] = sanitize_text_field($data['version_label']);
            $update_formats[] = '%s';
        }

        if (isset($data['version_notes'])) {
            $update_data['version_notes'] = wp_kses_post($data['version_notes']);
            $update_formats[] = '%s';
        }

        if (isset($data['version_number'])) {
            $update_data['version_number'] = intval($data['version_number']);
            $update_formats[] = '%d';
        }        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $wpdb->update($table, $update_data, array('id' => intval($id)), $update_formats, array('%d'));
        return $result !== false;
    }
}
