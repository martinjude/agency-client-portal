<?php
/**
 * Gestion des assets (upload, stockage, sécurité)
 *
 * @package Agency
 */

if (!defined('ABSPATH')) {
    exit;
}

class Agency_Assets_Manager {
    
    /**
     * Types de fichiers autorisés
     */
    private $allowed_types = array(
        'image' => array('jpg', 'jpeg', 'png', 'gif', 'webp'),
        'video' => array('mp4', 'webm', 'ogg'),
        'document' => array('pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'),
        'audio' => array('mp3', 'wav', 'ogg'),
    );
    
    /**
     * Constructeur
     */
    public function __construct() {
        add_action('init', array($this, 'setup_upload_directory'));
        add_action('wp_ajax_agency_serve_asset', array($this, 'serve_protected_asset'));
        add_action('wp_ajax_nopriv_agency_serve_asset', array($this, 'serve_protected_asset'));
        add_action('wp_ajax_agency_serve_thumbnail', array($this, 'serve_thumbnail'));
        add_action('wp_ajax_nopriv_agency_serve_thumbnail', array($this, 'serve_thumbnail'));
    }
    
    /**
     * Configurer le dossier d'upload
     */
    public function setup_upload_directory() {
        if (!file_exists(AGENCY_UPLOAD_DIR)) {
            wp_mkdir_p(AGENCY_UPLOAD_DIR);
            self::protect_upload_directory();
        }
    }
    
    /**
     * Instance WP_Filesystem en accès direct pour les opérations sur wp-content/agency-uploads/.
     *
     * @return WP_Filesystem_Direct
     */
    public static function filesystem() {
        static $fs = null;
        if (null === $fs) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
            if (!defined('FS_CHMOD_DIR')) {
                define('FS_CHMOD_DIR', (fileperms(ABSPATH) & 0777 | 0755));
            }
            if (!defined('FS_CHMOD_FILE')) {
                define('FS_CHMOD_FILE', (fileperms(ABSPATH . 'index.php') & 0777 | 0644));
            }
            $fs = new WP_Filesystem_Direct(null);
        }
        return $fs;
    }

    /**
     * Protéger le dossier d'upload (.htaccess + index.php).
     */
    public static function protect_upload_directory() {
        $fs = self::filesystem();

        $htaccess_content = "# Agency Plugin - Protected Directory\n";
        $htaccess_content .= "Order Deny,Allow\n";
        $htaccess_content .= "Deny from all\n";
        $fs->put_contents(AGENCY_UPLOAD_DIR . '/.htaccess', $htaccess_content, FS_CHMOD_FILE);

        $fs->put_contents(AGENCY_UPLOAD_DIR . '/index.php', '<?php // Silence is golden', FS_CHMOD_FILE);
    }

    /**
     * Déplace un fichier uploadé vers le dossier Agency via wp_handle_upload().
     *
     * Le type de fichier a déjà été validé par validate_file() (liste blanche Agency),
     * d'où test_type => false.
     *
     * @param array  $file      Entrée de $_FILES.
     * @param string $asset_dir Dossier de destination.
     * @param string $filename  Nom de fichier final.
     * @return string|WP_Error  Chemin du fichier déplacé.
     */
    private function handle_upload($file, $asset_dir, $filename) {
        require_once ABSPATH . 'wp-admin/includes/file.php';

        $dir_filter = function ($dirs) use ($asset_dir) {
            $dirs['path']    = $asset_dir;
            $dirs['url']     = AGENCY_UPLOAD_URL;
            $dirs['subdir']  = '';
            $dirs['basedir'] = $asset_dir;
            $dirs['baseurl'] = AGENCY_UPLOAD_URL;
            $dirs['error']   = false;
            return $dirs;
        };

        add_filter('upload_dir', $dir_filter, PHP_INT_MAX);
        $result = wp_handle_upload($file, array(
            'test_form'                => false,
            'test_type'                => false,
            'unique_filename_callback' => function () use ($filename) {
                return $filename;
            },
        ));
        remove_filter('upload_dir', $dir_filter, PHP_INT_MAX);

        if (!is_array($result) || isset($result['error']) || empty($result['file'])) {
            return new WP_Error('upload_failed', __('Upload failed', 'agency-client-portal'));
        }

        return $result['file'];
    }
    
    /**
     * Uploader un fichier
     */
    public function upload_file($file, $block_id, $meta = array(), $skip_permission_check = false) {
        // Vérifier les permissions
        if (!$skip_permission_check && !current_user_can('upload_agency_assets')) {
            return new WP_Error('permission_denied', __('Permission denied', 'agency-client-portal'));
        }

        // Récupérer le bloc pour appliquer des règles spécifiques
        $block = Agency_Block::get_by_id($block_id);
        if (!$block) {
            return new WP_Error('block_not_found', __('Block not found', 'agency-client-portal'));
        }
        
        // Restreindre les formats pour le bloc média versionné
        $allowed_ext = null;
        if ($block->type === 'versioned_media') {
            $allowed_ext = array('jpg', 'jpeg', 'png', 'pdf');
        }

        // Appliquer les restrictions configurées pour le bloc dépôt de fichiers
        if ($block->type === 'file_deposit') {
            $block_settings = !empty($block->settings) ? json_decode($block->settings, true) : array();
            $allowed_ext = !empty($block_settings['allowed_extensions'])
                ? array_map('trim', (array) $block_settings['allowed_extensions'])
                : array('zip');
            $custom_max_mb = !empty($block_settings['max_size_mb']) ? intval($block_settings['max_size_mb']) : 100;
            if ($file['size'] > $custom_max_mb * 1024 * 1024) {
                return new WP_Error('file_too_large', sprintf(
                    /* translators: %d: maximum file size in MB */ __('The file exceeds the configured limit (%d MB)', 'agency-client-portal'),
                    $custom_max_mb
                ));
            }
        }
        
        // Valider le fichier
        $validation = $this->validate_file($file, $allowed_ext);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Créer un nom de fichier unique
        $extension = $validation['extension'];
        $detected_mime_type = $validation['mime_type'];
        $filename = uniqid('agency_') . '_' . time() . '.' . $extension;
        
        // Obtenir le chemin dynamique basé sur client/project/block
        $asset_dir = $this->get_asset_directory($block_id);
        if (is_wp_error($asset_dir)) {
            return $asset_dir;
        }
        
        // Créer le dossier s'il n'existe pas
        if (!file_exists($asset_dir)) {
            wp_mkdir_p($asset_dir);
        }
        
        $file_path = $asset_dir . '/' . $filename;
        
        // Déplacer le fichier
        $moved = $this->handle_upload($file, $asset_dir, $filename);
        if (is_wp_error($moved)) {
            return $moved;
        }
        $file_path = $moved;
        
        // Préparer les métadonnées de version
        $version_number = isset($meta['version_number']) ? intval($meta['version_number']) : Agency_Asset::get_next_version_number($block_id);
        $version_label = isset($meta['version_label']) && $meta['version_label'] !== '' ? sanitize_text_field($meta['version_label']) : sprintf(/* translators: %d: version number */ __('Version %d', 'agency-client-portal'), $version_number);
        $version_status = isset($meta['version_status']) && $meta['version_status'] !== '' ? sanitize_text_field($meta['version_status']) : 'draft';
        $version_notes = isset($meta['version_notes']) ? wp_kses_post($meta['version_notes']) : null;
        $order_position = isset($meta['order_position']) ? intval($meta['order_position']) : $version_number;

        // Si after_asset_id est fourni, calculer la position d'insertion et décaler les suivants
        if (array_key_exists('after_asset_id', $meta)) {
            global $wpdb;
            $at = $wpdb->prefix . 'agency_assets';
            $after_id = $meta['after_asset_id'] ? intval($meta['after_asset_id']) : 0;

            if (!$after_id) {
                // Insérer avant toutes les slides : décaler tout
                $wpdb->query($wpdb->prepare(
                    "UPDATE $at SET order_position = order_position + 1 WHERE block_id = %d AND (parent_asset_id IS NULL OR parent_asset_id = 0)",
                    $block_id
                ));
                $order_position = 0;
            } else {
                $target_pos = $wpdb->get_var($wpdb->prepare("SELECT order_position FROM $at WHERE id = %d", $after_id));
                if ($target_pos !== null) {
                    $new_pos = intval($target_pos) + 1;
                    $wpdb->query($wpdb->prepare(
                        "UPDATE $at SET order_position = order_position + 1 WHERE block_id = %d AND (parent_asset_id IS NULL OR parent_asset_id = 0) AND order_position >= %d",
                        $block_id,
                        $new_pos
                    ));
                    $order_position = $new_pos;
                }
            }
        }
        
        // Enregistrer dans la base de données
        global $wpdb;
        $table = $wpdb->prefix . 'agency_assets';
        
        $wpdb->insert($table, array(
            'block_id' => $block_id,
            'filename' => $filename,
            'original_filename' => sanitize_file_name($file['name']),
            'file_path' => $file_path,
            'file_type' => $this->get_file_type($extension),
            'file_size' => $file['size'],
            'mime_type' => $detected_mime_type,
            'order_position' => $order_position,
            'voiceover' => isset($meta['voiceover']) ? wp_kses_post($meta['voiceover']) : null,
            'version_number' => $version_number,
            'version_label' => $version_label,
            'version_status' => $version_status,
            'version_notes' => $version_notes,
            'parent_asset_id' => isset($meta['parent_asset_id']) ? intval($meta['parent_asset_id']) : null,
            'suggested_by_client' => !empty($meta['suggested_by_client']) ? 1 : 0,
            'suggestion_text' => isset($meta['suggestion_text']) ? sanitize_textarea_field($meta['suggestion_text']) : null,
            'uploaded_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
        ));
        
        $asset_id = $wpdb->insert_id;

        // Générer un thumbnail pour les images
        $file_type = $this->get_file_type($extension);
        if ($file_type === 'image' && !in_array($extension, array('svg', 'gif'))) {
            $this->generate_thumbnail($asset_id, $file_path);
        }

        return array(
            'success' => true,
            'asset_id' => $asset_id,
            'filename' => $filename,
            'url' => $this->get_asset_url($asset_id),
            'version_number' => $version_number,
        );
    }
    
    /**
     * Taille maximale d'upload pour les fichiers Agency (512 Mo par défaut).
     *
     * Ne remplace pas les limites PHP du serveur (upload_max_filesize, post_max_size).
     * Filtrable via `agency_upload_size_limit`.
     *
     * @return int Taille en octets.
     */
    public static function get_max_upload_size() {
        return (int) apply_filters('agency_upload_size_limit', 512 * MB_IN_BYTES);
    }

    /**
     * Valider un fichier
     */
    private function validate_file($file, $allowed_extensions = null) {
        // Vérifier les erreurs d'upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', __('Error during upload', 'agency-client-portal'));
        }

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return new WP_Error('invalid_upload', __('Invalid uploaded file', 'agency-client-portal'));
        }
        
        // Vérifier la taille (limite propre à Agency, sans modifier la limite globale du site)
        $max_size = self::get_max_upload_size();
        if ($file['size'] > $max_size) {
            return new WP_Error('file_too_large', sprintf(/* translators: %s: maximum file size */ __('The file is too large (max: %s)', 'agency-client-portal'), size_format($max_size)));
        }

        $wp_filetype = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
        $detected_extension = !empty($wp_filetype['ext']) ? strtolower($wp_filetype['ext']) : '';
        $detected_mime_type = !empty($wp_filetype['type']) ? $wp_filetype['type'] : '';
        $filename_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (empty($detected_extension) || empty($detected_mime_type)) {
            return new WP_Error('invalid_file_type', __('File type not allowed', 'agency-client-portal'));
        }

        if ($filename_extension && $detected_extension !== $filename_extension) {
            return new WP_Error('invalid_file_type', __('Invalid file extension', 'agency-client-portal'));
        }

        $all_extensions = $allowed_extensions ? $allowed_extensions : array_merge(...array_values($this->allowed_types));
        
        if (!in_array($detected_extension, $all_extensions, true)) {
            return new WP_Error('invalid_file_type', __('File type not allowed', 'agency-client-portal'));
        }

        return array(
            'extension' => $detected_extension,
            'mime_type' => $detected_mime_type,
        );
    }
    
    /**
     * Obtenir le répertoire de l'asset basé sur client/project/block
     */
    private function get_asset_directory($block_id) {
        // Obtenir le bloc
        $block = Agency_Block::get_by_id($block_id);
        if (!$block) {
            return new WP_Error('block_not_found', __('Block not found', 'agency-client-portal'));
        }
        
        // Obtenir le projet
        $project = Agency_Project::get_by_id($block->project_id);
        if (!$project) {
            return new WP_Error('project_not_found', __('Project not found', 'agency-client-portal'));
        }
        
        // Obtenir le client
        $client = Agency_Client::get_by_id($project->client_id);
        if (!$client) {
            return new WP_Error('client_not_found', __('Client not found', 'agency-client-portal'));
        }
        
        // Construire le chemin: /client-slug/project-slug/block-id-block-title/
        $block_folder = $block->id . '-' . sanitize_file_name($block->title);
        $asset_dir = AGENCY_UPLOAD_DIR . '/' . $client->slug . '/' . $project->slug . '/' . $block_folder;
        
        return $asset_dir;
    }
    
    /**
     * Obtenir le type de fichier
     */
    private function get_file_type($extension) {
        foreach ($this->allowed_types as $type => $extensions) {
            if (in_array($extension, $extensions)) {
                return $type;
            }
        }
        return 'other';
    }
    
    /**
     * Obtenir l'URL de l'asset (protégée)
     */
    public function get_asset_url($asset_id, $download = false) {
        $url = admin_url('admin-ajax.php?action=agency_serve_asset&id=' . intval($asset_id));
        if ($download) {
            $url = add_query_arg('download', '1', $url);
        }

        return $url;
    }

    /**
     * Obtenir l'URL d'aperçu d'un asset (mode preview-only).
     */
    public function get_asset_preview_url($asset_id) {
        return add_query_arg('preview', '1', $this->get_asset_url($asset_id));
    }
    
    /**
     * Servir un asset protégé
     */
    public function serve_protected_asset() {
        $asset_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $is_download_request = isset($_GET['download']) && sanitize_text_field(wp_unslash($_GET['download'])) === '1';
        $is_preview_request = isset($_GET['preview']) && sanitize_text_field(wp_unslash($_GET['preview'])) === '1';
        
        if (!$asset_id) {
            wp_die(esc_html__('Asset not found', 'agency-client-portal'), 404);
        }
        
        // Récupérer l'asset
        global $wpdb;
        $table = $wpdb->prefix . 'agency_assets';
        $asset = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $asset_id));
        
        if (!$asset) {
            wp_die(esc_html__('Asset not found', 'agency-client-portal'), 404);
        }
        
        // Vérifier les permissions
        $block = Agency_Block::get_by_id($asset->block_id);
        if (!$block) {
            wp_die(esc_html__('Access denied', 'agency-client-portal'), 403);
        }
        
        $project = Agency_Project::get_by_id($block->project_id);
        if (!$project || !apply_filters('agency_can_access_project', false, $project->id)) {
            wp_die(esc_html__('Access denied', 'agency-client-portal'), 403);
        }

        $is_manager_user = current_user_can('manage_agency_projects');
        $is_token_client = Agency_Security::can_access_client_by_token($project->client_id);
        $is_preview_only_mode = (
            !$is_manager_user &&
            $is_token_client &&
            Agency_Block::supports_media_download_setting($block->type) &&
            !Agency_Block::is_media_download_allowed($block)
        );

        // Bloquer le téléchargement explicite pour les clients token si le bloc l'interdit.
        if ($is_download_request && $is_preview_only_mode) {
            wp_die(esc_html__('Media download is disabled for this block.', 'agency-client-portal'), 403);
        }

        // En mode preview-only, bloquer l'URL originale et n'autoriser que l'URL preview.
        if ($is_preview_only_mode && !$is_preview_request) {
            wp_die(esc_html__('This media is only available in preview mode.', 'agency-client-portal'), 403);
        }

        // Servir un aperçu (thumbnail pour images, fallback inline pour les autres médias).
        if ($is_preview_request) {
            $preview_payload = $this->get_preview_payload($asset_id, $asset);
            if (!$preview_payload || !file_exists($preview_payload['file_path'])) {
                wp_die(esc_html__('Preview not available for this media.', 'agency-client-portal'), 404);
            }

            $this->stream_file(
                $preview_payload['file_path'],
                $preview_payload['mime_type'],
                sanitize_file_name($asset->original_filename),
                'inline',
                'private, no-store, no-cache, must-revalidate'
            );
            // stream_file appelle exit
        }

        // Servir le fichier
        if (!file_exists($asset->file_path)) {
            wp_die(esc_html__('File not found', 'agency-client-portal'), 404);
        }

        $disposition = 'attachment';
        if (
            strpos($asset->mime_type, 'image/') === 0 ||
            strpos($asset->mime_type, 'video/') === 0 ||
            strpos($asset->mime_type, 'audio/') === 0 ||
            $asset->mime_type === 'application/pdf'
        ) {
            $disposition = 'inline';
        }

        $this->stream_file(
            $asset->file_path,
            $asset->mime_type,
            sanitize_file_name($asset->original_filename),
            $disposition,
            'private, max-age=3600'
        );
        // stream_file appelle exit
    }

    /**
     * Stream un fichier au client avec :
     *  - lecture chunked (pas de readfile() → pas d'OOM sur fichiers volumineux) ;
     *  - support HTTP Range / 206 Partial Content (vidéo seekable) ;
     *  - ETag + If-None-Match (304 si cache client à jour) ;
     *  - Last-Modified ;
     *  - protection realpath contre la traversée hors AGENCY_UPLOAD_DIR.
     *
     * Cette méthode termine la requête (exit).
     */
    private function stream_file($file_path, $mime_type, $download_filename, $disposition = 'inline', $cache_control = 'private, max-age=3600') {
        $real_path       = realpath($file_path);
        $real_upload_dir = realpath(AGENCY_UPLOAD_DIR);
        if (!$real_path || !$real_upload_dir || strpos($real_path, $real_upload_dir) !== 0) {
            wp_die(esc_html__('Access denied', 'agency-client-portal'), 403);
        }

        if (!file_exists($real_path)) {
            wp_die(esc_html__('File not found', 'agency-client-portal'), 404);
        }

        // Vider tout buffer ouvert pour éviter d'agréger en mémoire.
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        $size  = filesize($real_path);
        $mtime = filemtime($real_path);
        $etag  = '"' . md5($real_path . '|' . $mtime . '|' . $size) . '"';

        $client_etag = isset($_SERVER['HTTP_IF_NONE_MATCH'])
            ? trim(sanitize_text_field(wp_unslash($_SERVER['HTTP_IF_NONE_MATCH'])))
            : '';
        if ($client_etag !== '' && $client_etag === $etag) {
            status_header(304);
            header('ETag: ' . $etag);
            header('Cache-Control: ' . $cache_control);
            exit;
        }

        $start = 0;
        $end   = $size > 0 ? $size - 1 : 0;
        $is_range_request = false;

        if (!empty($_SERVER['HTTP_RANGE'])) {
            $raw_range = sanitize_text_field(wp_unslash($_SERVER['HTTP_RANGE']));
            if (preg_match('/^bytes=(\d+)-(\d*)$/', $raw_range, $m)) {
                $start = (int) $m[1];
                $end   = ($m[2] === '') ? $end : min((int) $m[2], $end);
                if ($start > $end || $start >= $size) {
                    status_header(416);
                    header('Content-Range: bytes */' . $size);
                    exit;
                }
                $is_range_request = true;
            }
        }

        $length = $end - $start + 1;

        header('Content-Type: ' . $mime_type);
        header('X-Content-Type-Options: nosniff');
        header('Accept-Ranges: bytes');
        header('ETag: ' . $etag);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        header('Cache-Control: ' . $cache_control);
        header('Content-Disposition: ' . $disposition . '; filename="' . $download_filename . '"');
        header('Content-Length: ' . $length);

        if ($is_range_request) {
            status_header(206);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
        }

        // Lecture en flux (support HTTP Range) : WP_Filesystem ne permet pas la lecture par morceaux.
        $fp = fopen($real_path, 'rb'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        if ($fp === false) {
            wp_die(esc_html__('File not readable', 'agency-client-portal'), 500);
        }

        if ($start > 0) {
            fseek($fp, $start);
        }

        $remaining = $length;
        while ($remaining > 0 && !feof($fp)) {
            $chunk = fread($fp, min(8192, $remaining)); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
            if ($chunk === false) {
                break;
            }
            // Flux binaire du fichier protégé : ne doit pas être échappé.
            echo $chunk; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            flush();
            $remaining -= strlen($chunk);
        }
        fclose($fp); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        exit;
    }

    /**
     * Préparer le payload d'aperçu d'un asset.
     */
    private function get_preview_payload($asset_id, $asset) {
        if (
            strpos($asset->mime_type, 'image/') === 0 &&
            !in_array(strtolower(pathinfo($asset->original_filename, PATHINFO_EXTENSION)), array('svg', 'gif'), true)
        ) {
            $thumb_path = $this->get_thumbnail_path($asset->file_path);

            if (!$thumb_path || !file_exists($thumb_path)) {
                $thumb_path = $this->generate_thumbnail($asset_id, $asset->file_path);
            }

            if ($thumb_path && file_exists($thumb_path)) {
                $thumb_ext = strtolower(pathinfo($thumb_path, PATHINFO_EXTENSION));
                $mime_map = array(
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'webp' => 'image/webp',
                );

                return array(
                    'file_path' => $thumb_path,
                    'mime_type' => isset($mime_map[$thumb_ext]) ? $mime_map[$thumb_ext] : 'image/jpeg',
                    'file_size' => filesize($thumb_path),
                );
            }
        }

        if (!file_exists($asset->file_path)) {
            return false;
        }

        $file_size = filesize($asset->file_path);
        if ($file_size === false) {
            $file_size = intval($asset->file_size);
        }

        return array(
            'file_path' => $asset->file_path,
            'mime_type' => $asset->mime_type,
            'file_size' => $file_size,
        );
    }
    
    /**
     * Supprimer un asset
     */
    public function delete_asset($asset_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_assets';

        $asset = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $asset_id));

        if (!$asset) {
            return false;
        }

        $this->cleanup_asset_files($asset);

        // Supprimer de la base de données
        $wpdb->delete($table, array('id' => $asset_id));

        return true;
    }

    /**
     * Supprimer en lot les fichiers ET les lignes de tous les assets rattachés
     * à une liste de blocs. Utilisé pour le cascade delete projet/bloc.
     *
     * @param array<int> $block_ids
     * @return int|false Nombre de lignes assets supprimées.
     */
    public function delete_assets_for_block_ids(array $block_ids) {
        $block_ids = array_values(array_filter(array_map('intval', $block_ids)));
        if (empty($block_ids)) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'agency_assets';
        $placeholders = implode(',', array_fill(0, count($block_ids), '%d'));

        // 1) Récupérer tous les chemins pour nettoyer les fichiers physiques.
        $assets = $wpdb->get_results($wpdb->prepare(
            "SELECT id, file_path, original_filename, mime_type FROM {$table} WHERE block_id IN ({$placeholders})",
            $block_ids
        ));

        if (!empty($assets)) {
            foreach ($assets as $asset) {
                $this->cleanup_asset_files($asset);
            }
        }

        // 2) Supprimer toutes les lignes en une seule requête.
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE block_id IN ({$placeholders})",
            $block_ids
        ));
    }

    /**
     * Supprime le fichier d'un asset + son thumbnail si présent.
     */
    private function cleanup_asset_files($asset) {
        if (empty($asset) || empty($asset->file_path)) {
            return;
        }

        if (file_exists($asset->file_path)) {
            wp_delete_file($asset->file_path);
        }

        $thumb_path = $this->get_thumbnail_path($asset->file_path);
        if ($thumb_path && file_exists($thumb_path)) {
            wp_delete_file($thumb_path);
        }
    }
    
    /**
     * Migrer les assets d'un bloc vers un nouveau chemin (appelé lors du renommage)
     */
    public function migrate_block_assets($block_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'agency_assets';

        // Obtenir tous les assets du bloc
        $assets = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE block_id = %d",
            $block_id
        ));

        if (empty($assets)) {
            return true;
        }

        // Capturer l'ancien répertoire avant migration
        $old_dir = null;
        if (!empty($assets[0]->file_path)) {
            $old_dir = dirname($assets[0]->file_path);
        }

        // Obtenir le nouveau répertoire
        $new_dir = $this->get_asset_directory($block_id);
        if (is_wp_error($new_dir)) {
            return false;
        }

        // Créer le nouveau dossier
        if (!file_exists($new_dir)) {
            wp_mkdir_p($new_dir);
        }

		// Migrer chaque asset
		foreach ($assets as $asset) {
			$old_path = $asset->file_path;
			$new_path = $new_dir . '/' . $asset->filename;

            // Déplacer le fichier physique
            if (file_exists($old_path) && $old_path !== $new_path) {
                if (self::filesystem()->move($old_path, $new_path)) {
                    // Mettre à jour le chemin dans la base de données
                    $wpdb->update(
                        $table,
                        array('file_path' => $new_path),
                        array('id' => $asset->id),
                        array('%s'),
                        array('%d')
                    );
				}
			}
		}

		// Déplacer le dossier des miniatures s'il existe
		$old_thumbs_dir = $old_dir . DIRECTORY_SEPARATOR . 'thumbnails';
		$new_thumbs_dir = $new_dir . DIRECTORY_SEPARATOR . 'thumbnails';
		if (is_dir($old_thumbs_dir) && $old_dir !== $new_dir) {
			if (!file_exists($new_thumbs_dir)) {
				self::filesystem()->move($old_thumbs_dir, $new_thumbs_dir);
			} else {
				// Si le dossier de destination existe déjà, déplacer les fichiers un par un
				$thumb_files = @scandir($old_thumbs_dir);
				if ($thumb_files) {
					foreach (array_diff($thumb_files, array('.', '..')) as $tf) {
						self::filesystem()->move($old_thumbs_dir . DIRECTORY_SEPARATOR . $tf, $new_thumbs_dir . DIRECTORY_SEPARATOR . $tf);
					}
					self::filesystem()->rmdir($old_thumbs_dir);
				}
			}
		}

		// Nettoyer l'ancien répertoire s'il est maintenant vide
		if ($old_dir && is_dir($old_dir)) {
			$this->cleanup_empty_directories($old_dir);
		}

        return true;
    }
    
    /**
     * Migrer les assets d'un projet vers un nouveau chemin (appelé lors du renommage du projet)
     */
    public function migrate_project_assets($project_id) {
        global $wpdb;

        // Obtenir tous les blocs du projet
        $blocks = Agency_Block::get_by_project($project_id);

        foreach ($blocks as $block) {
            $this->migrate_block_assets($block->id);
        }

        // Nettoyer les dossiers vides au niveau client
        $project = Agency_Project::get_by_id($project_id);
        if ($project) {
            $client = Agency_Client::get_by_id($project->client_id);
            if ($client) {
                $client_dir = AGENCY_UPLOAD_DIR . '/' . $client->slug;
                if (is_dir($client_dir)) {
                    $this->cleanup_empty_directories($client_dir);
                }
            }
        }

        return true;
    }
    
    /**
     * Migrer les assets d'un client vers un nouveau chemin (appelé lors du renommage du client)
     */
    public function migrate_client_assets($client_id) {
        global $wpdb;

        // Obtenir tous les projets du client
        $projects = Agency_Project::get_by_client($client_id);

        foreach ($projects as $project) {
            $this->migrate_project_assets($project->id);
        }

        // Nettoyer les dossiers vides dans le répertoire uploads
        $this->cleanup_empty_directories(AGENCY_UPLOAD_DIR);

        return true;
    }

	/**
	 * Nettoyer un répertoire s'il est vide (en ignorant les fichiers de garde).
	 * Utilise le nettoyage récursif interne pour supprimer aussi les sous-dossiers
	 * vides éventuels avant de tenter de supprimer le dossier cible.
	 *
	 * @param string $dir Répertoire à nettoyer.
	 * @return bool True si le dossier n'existe plus après nettoyage, sinon false.
	 */
	public function cleanup_directory_if_empty($dir) {
		if (!is_string($dir) || !is_dir($dir)) {
			return false;
		}

		$this->cleanup_empty_directories($dir);

		return !is_dir($dir);
	}

    /**
     * Générer un thumbnail pour un asset image.
     *
     * @param int    $asset_id    ID de l'asset.
     * @param string $source_path Chemin complet du fichier source.
     * @param int    $max_width   Largeur max du thumbnail.
     * @param int    $max_height  Hauteur max du thumbnail.
     * @return string|false       Chemin du thumbnail ou false en cas d'erreur.
     */
    public function generate_thumbnail($asset_id, $source_path, $max_width = 800, $max_height = 600) {
        if (!file_exists($source_path)) {
            return false;
        }

        $thumb_path = $this->get_thumbnail_path($source_path);
        if (!$thumb_path) {
            return false;
        }

        // Si le thumbnail existe déjà, le retourner
        if (file_exists($thumb_path)) {
            return $thumb_path;
        }

        // Créer le dossier thumbnails
        $thumb_dir = dirname($thumb_path);
        if (!file_exists($thumb_dir)) {
            wp_mkdir_p($thumb_dir);
        }

        // Utiliser l'éditeur d'image WordPress
        $editor = wp_get_image_editor($source_path);
        if (is_wp_error($editor)) {
            return false;
        }

        $editor->resize($max_width, $max_height, false);
        $editor->set_quality(92);

        $saved = $editor->save($thumb_path);
        if (is_wp_error($saved)) {
            return false;
        }

        return $thumb_path;
    }

    /**
     * Obtenir le chemin du thumbnail à partir du chemin source.
     *
     * @param string $source_path Chemin du fichier original.
     * @return string|false       Chemin du thumbnail ou false.
     */
    private function get_thumbnail_path($source_path) {
        if (!$source_path) {
            return false;
        }

        $dir = dirname($source_path);
        $info = pathinfo($source_path);
        $ext = isset($info['extension']) ? strtolower($info['extension']) : '';

        // Seulement pour les formats supportés par wp_get_image_editor
        if (!in_array($ext, array('jpg', 'jpeg', 'png', 'webp'))) {
            return false;
        }

        return $dir . '/thumbnails/' . $info['filename'] . '_thumb.' . $ext;
    }

    /**
     * Obtenir l'URL du thumbnail d'un asset.
     *
     * @param int $asset_id ID de l'asset.
     * @return string        URL AJAX du thumbnail.
     */
    public function get_thumbnail_url($asset_id) {
        return admin_url('admin-ajax.php?action=agency_serve_thumbnail&id=' . $asset_id);
    }

    /**
     * Servir un thumbnail protégé via AJAX.
     */
    public function serve_thumbnail() {
        $asset_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        if (!$asset_id) {
            wp_die(esc_html__('Asset not found', 'agency-client-portal'), 404);
        }

        // Récupérer l'asset
        global $wpdb;
        $table = $wpdb->prefix . 'agency_assets';
        $asset = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $asset_id));

        if (!$asset) {
            wp_die(esc_html__('Asset not found', 'agency-client-portal'), 404);
        }

        // Vérifier les permissions
        $block = Agency_Block::get_by_id($asset->block_id);
        if (!$block) {
            wp_die(esc_html__('Access denied', 'agency-client-portal'), 403);
        }

        $project = Agency_Project::get_by_id($block->project_id);
        if (!$project || !apply_filters('agency_can_access_project', false, $project->id)) {
            wp_die(esc_html__('Access denied', 'agency-client-portal'), 403);
        }

        // Chercher le thumbnail
        $thumb_path = $this->get_thumbnail_path($asset->file_path);

        // Si le thumb n'existe pas, générer à la volée
        if (!$thumb_path || !file_exists($thumb_path)) {
            $thumb_path = $this->generate_thumbnail($asset_id, $asset->file_path);
        }

        // Si le thumbnail ne peut pas être généré, retourner 404
        // Le JS utilisera asset.url comme fallback si thumbnail_url retourne 404
        if (!$thumb_path || !file_exists($thumb_path)) {
            wp_die(esc_html__('Thumbnail not available', 'agency-client-portal'), 404);
        }

        // Déterminer le MIME type correct selon l'extension du thumbnail
        $thumb_ext = strtolower(pathinfo($thumb_path, PATHINFO_EXTENSION));
        $mime_map = array(
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'webp' => 'image/webp',
        );
        $thumb_mime = isset($mime_map[$thumb_ext]) ? $mime_map[$thumb_ext] : 'image/jpeg';

        $this->stream_file(
            $thumb_path,
            $thumb_mime,
            sanitize_file_name(pathinfo($asset->original_filename, PATHINFO_FILENAME) . '_thumb.' . $thumb_ext),
            'inline',
            'public, max-age=31536000'
        );
    }

    /**
     * Supprimer récursivement les répertoires vides sous un chemin donné.
     * Ne supprime jamais AGENCY_UPLOAD_DIR lui-même.
     *
     * @param string $dir Répertoire de départ.
     * @return void
     */
    private function cleanup_empty_directories($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $root = wp_normalize_path(AGENCY_UPLOAD_DIR);
        $target = wp_normalize_path($dir);

        // S'assurer que le répertoire est bien dans l'arborescence uploads
        if (strpos($target, $root) !== 0) {
            return;
        }

        $items = @scandir($dir);
        if ($items === false) {
            return;
        }

        $items = array_diff($items, array('.', '..'));

        // Récurser dans les sous-dossiers d'abord
        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->cleanup_empty_directories($path);
            }
        }

        // Ne pas supprimer le répertoire racine
        if ($target === $root) {
            return;
        }

        // Re-scanner après nettoyage récursif des sous-dossiers
        $items = @scandir($dir);
        if ($items === false) {
            return;
        }
        $items = array_diff($items, array('.', '..'));

        // Ignorer les fichiers de protection (.htaccess, index.php)
        $guard_files = array('.htaccess', 'index.php');
        $remaining = array_diff($items, $guard_files);

        if (empty($remaining)) {
            // Supprimer les fichiers de protection avant rmdir
            foreach ($guard_files as $guard) {
                $guard_path = $dir . DIRECTORY_SEPARATOR . $guard;
                if (file_exists($guard_path)) {
                    wp_delete_file($guard_path);
                }
            }
            self::filesystem()->rmdir($dir);
        }
    }
}
