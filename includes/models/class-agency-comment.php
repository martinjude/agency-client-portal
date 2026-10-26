<?php
/**
 * Modèle Comment
 *
 * @package Agency
 */

if (!defined('ABSPATH')) {
    exit;
}

class Agency_Comment {
    
    /**
     * Obtenir tous les commentaires d'un bloc
     */
    public static function get_by_block($block_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_comments';
        
        $comments = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE block_id = %d AND (asset_id IS NULL OR asset_id = 0) AND status = 'approved' ORDER BY created_at ASC",
            $block_id
        ));
        
        // Enrichir avec les données utilisateur
        foreach ($comments as $comment) {
            $comment->user = get_userdata($comment->user_id);
            if (!$comment->user) {
                // Token-authenticated clients have no WP user; fallback label
                $comment->user = (object) array('display_name' => __('Client', 'agency-client-portal'));
            }
        }
        
        return $comments;
    }
    
    /**
     * Obtenir tous les commentaires d'un asset
     */
    public static function get_by_asset($asset_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_comments';
        
        $comments = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE asset_id = %d AND status = 'approved' ORDER BY created_at ASC",
            $asset_id
        ));
        
        // Enrichir avec les données utilisateur
        foreach ($comments as $comment) {
            $comment->user = get_userdata($comment->user_id);
            if (!$comment->user) {
                $comment->user = (object) array('display_name' => __('Client', 'agency-client-portal'));
            }
        }
        
        return $comments;
    }
    
    /**
     * Obtenir un commentaire par ID
     */
    public static function get_by_id($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_comments';
        
        $comment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
        
        if ($comment) {
            $comment->user = get_userdata($comment->user_id);
        }
        
        return $comment;
    }
    
    /**
     * Créer un nouveau commentaire
     */
    public static function create($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_comments';
        
        $insert_data = array(
            'block_id' => intval($data['block_id']),
            'user_id' => get_current_user_id(),
            'parent_id' => isset($data['parent_id']) ? intval($data['parent_id']) : 0,
            'content' => wp_kses_post($data['content']),
            'status' => 'approved',
            'created_at' => current_time('mysql'),
        );
        
        // Add asset_id if provided (for storyboard comments)
        if (isset($data['asset_id']) && !empty($data['asset_id'])) {
            $insert_data['asset_id'] = intval($data['asset_id']);
        }
        
        $wpdb->insert($table, $insert_data);
        
        $comment_id = $wpdb->insert_id;
        
        do_action('agency_comment_created', $comment_id, $data);
        
        // Envoyer une notification (peut être étendu)
        self::send_notification($comment_id);
        
        return $comment_id;
    }
    
    /**
     * Mettre à jour un commentaire
     */
    public static function update($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_comments';
        
        $update_data = array();
        
        if (isset($data['content'])) {
            $update_data['content'] = wp_kses_post($data['content']);
        }
        
        if (isset($data['status'])) {
            $update_data['status'] = sanitize_text_field($data['status']);
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $wpdb->update($table, $update_data, array('id' => $id));
        
        do_action('agency_comment_updated', $id, $data);
        
        return $result !== false;
    }
    
    /**
     * Supprimer un commentaire
     */
    public static function delete($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_comments';
        
        // Hard delete pour les commentaires
        $result = $wpdb->delete($table, array('id' => $id));
        
        do_action('agency_comment_deleted', $id);
        
        return $result !== false;
    }
    
    /**
     * Supprimer tous les commentaires d'un bloc
     */
    public static function delete_by_block($block_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_comments';

        $wpdb->delete($table, array('block_id' => $block_id));
    }

    /**
     * Supprimer en lot les commentaires d'une liste de blocs.
     *
     * @param array<int> $block_ids
     * @return int|false Nombre de lignes supprimées ou false si la liste est vide.
     */
    public static function delete_by_block_ids(array $block_ids) {
        $block_ids = array_values(array_filter(array_map('intval', $block_ids)));
        if (empty($block_ids)) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'agency_comments';
        $placeholders = implode(',', array_fill(0, count($block_ids), '%d'));

        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE block_id IN ({$placeholders})",
            $block_ids
        ));
    }
    
    /**
     * Obtenir le nombre de commentaires d'un bloc
     */
    public static function count_by_block($block_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_comments';
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE block_id = %d AND status = 'approved'",
            $block_id
        ));
    }
    
    /**
     * Nombre de commentaires approuvés de plusieurs blocs, indexé par block_id.
     *
     * @param int[] $block_ids
     * @return array<int, int>
     */
    public static function count_by_blocks(array $block_ids) {
        global $wpdb;
        $block_ids = array_values(array_filter(array_map('intval', $block_ids)));
        if (empty($block_ids)) {
            return array();
        }
        $table        = $wpdb->prefix . 'agency_comments';
        $placeholders = implode(',', array_fill(0, count($block_ids), '%d'));
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders ne contient que des %d.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT block_id, COUNT(*) AS cnt FROM $table WHERE block_id IN ($placeholders) AND status = 'approved' GROUP BY block_id",
            $block_ids
        ));
        $counts = array();
        foreach ($rows as $row) {
            $counts[intval($row->block_id)] = intval($row->cnt);
        }
        return $counts;
    }

    /**
     * Envoyer une notification pour un nouveau commentaire
     */
    private static function send_notification($comment_id) {
        $comment = self::get_by_id($comment_id);
        if (!$comment) {
            return;
        }
        
        $block = Agency_Block::get_by_id($comment->block_id);
        if (!$block) {
            return;
        }
        
        $project = Agency_Project::get_by_id($block->project_id);
        if (!$project) {
            return;
        }
        
        $client = Agency_Client::get_by_id($project->client_id);
        if (!$client) {
            return;
        }
        
        // Hook pour permettre l'extension des notifications
        do_action('agency_comment_notification', $comment, $block, $project, $client);
    }
}
