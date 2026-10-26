<?php
/**
 * Vérification des prérequis pour le système de tokens
 * 
 * @package Agency
 */

if (!defined('ABSPATH')) {
    exit;
}

class Agency_System_Requirements_Check {
    
    private $requirements = array();
    private $warnings = array();
    private $errors = array();
    
    public function __construct() {
        $this->check_all_requirements();
    }
    
    /**
     * Vérifier tous les prérequis
     */
    public function check_all_requirements() {
        $this->check_php_version();
        $this->check_wordpress_version();
        $this->check_php_extensions();
        $this->check_database_access();
        $this->check_file_permissions();
        $this->check_security_functions();
        $this->check_server_config();
    }
    
    /**
     * Vérifier la version PHP
     */
    private function check_php_version() {
        $min_php = '7.4';
        $current_php = PHP_VERSION;
        
        if (version_compare($current_php, $min_php, '>=')) {
            $this->requirements[] = array(
                'type' => 'success',
                'title' => __('PHP version', 'agency-client-portal'),
                'message' => sprintf(/* translators: 1: current PHP version, 2: minimum PHP version */ __('PHP %1$s (✓ >= %2$s required)', 'agency-client-portal'), $current_php, $min_php),
                'details' => __('Optimal support for modern security functions', 'agency-client-portal')
            );
            
            // Vérifications supplémentaires pour versions récentes
            if (version_compare($current_php, '8.0', '>=')) {
                $this->requirements[] = array(
                    'type' => 'info',
                    'title' => 'PHP 8.0+',
                    'message' => __('Optimized performance detected', 'agency-client-portal'),
                    'details' => __('PHP 8+ improves token system performance', 'agency-client-portal')
                );
            }
        } else {
            $this->errors[] = array(
                'type' => 'error',
                'title' => __('Insufficient PHP version', 'agency-client-portal'),
                'message' => sprintf(/* translators: 1: current PHP version, 2: minimum PHP version */ __('PHP %1$s detected (minimum: %2$s)', 'agency-client-portal'), $current_php, $min_php),
                'details' => __('Update PHP for optimal security', 'agency-client-portal')
            );
        }
    }
    
    /**
     * Vérifier la version WordPress
     */
    private function check_wordpress_version() {
        global $wp_version;
        $min_wp = '6.0';
        
        if (version_compare($wp_version, $min_wp, '>=')) {
            $this->requirements[] = array(
                'type' => 'success',
                'title' => __('WordPress version', 'agency-client-portal'),
                'message' => sprintf(/* translators: 1: current WordPress version, 2: minimum WordPress version */ __('WordPress %1$s (✓ >= %2$s required)', 'agency-client-portal'), $wp_version, $min_wp),
                'details' => __('Modern APIs and hooks available', 'agency-client-portal')
            );
        } else {
            $this->errors[] = array(
                'type' => 'error',
                'title' => __('Insufficient WordPress version', 'agency-client-portal'),
                'message' => sprintf(/* translators: 1: current WordPress version, 2: minimum WordPress version */ __('WordPress %1$s detected (minimum: %2$s)', 'agency-client-portal'), $wp_version, $min_wp),
                'details' => __('Update WordPress for full compatibility', 'agency-client-portal')
            );
        }
    }
    
    /**
     * Vérifier les extensions PHP requises
     */
    private function check_php_extensions() {
        $required_extensions = array(
            'openssl' => __('Secure token generation', 'agency-client-portal'),
            'json' => __('Cookie and data handling', 'agency-client-portal'),
            'hash' => __('SHA-256 token hashing', 'agency-client-portal'),
            'mbstring' => __('UTF-8 character support', 'agency-client-portal'),
        );
        
        foreach ($required_extensions as $ext => $purpose) {
            if (extension_loaded($ext)) {
                $this->requirements[] = array(
                    'type' => 'success',
                    'title' => sprintf(/* translators: %s: PHP extension name */ __('%s extension', 'agency-client-portal'), $ext),
                    'message' => __('✓ Available', 'agency-client-portal'),
                    'details' => $purpose
                );
            } else {
                $this->errors[] = array(
                    'type' => 'error',
                    'title' => sprintf(/* translators: %s: PHP extension name */ __('%s extension missing', 'agency-client-portal'), $ext),
                    'message' => sprintf(__('Required for: %s', 'agency-client-portal'), $purpose),
                    'details' => __('Contact your host to enable this extension', 'agency-client-portal')
                );
            }
        }
        
        // Vérifications optionnelles
        $optional_extensions = array(
            'sodium' => __('Advanced encryption (PHP 7.2+)', 'agency-client-portal'),
            'gd' => __('Image processing for client logos', 'agency-client-portal'),
            'curl' => __('External HTTP requests', 'agency-client-portal')
        );
        
        foreach ($optional_extensions as $ext => $purpose) {
            if (extension_loaded($ext)) {
                $this->requirements[] = array(
                    'type' => 'info',
                    'title' => sprintf(/* translators: %s: PHP extension name */ __('%s extension', 'agency-client-portal'), $ext),
                    'message' => __('✓ Available (optional)', 'agency-client-portal'),
                    'details' => $purpose
                );
            } else {
                $this->warnings[] = array(
                    'type' => 'warning',
                    'title' => sprintf(/* translators: %s: PHP extension name */ __('%s extension', 'agency-client-portal'), $ext),
                    'message' => __('Not available (optional)', 'agency-client-portal'),
                    'details' => sprintf(/* translators: %s: feature improved by the extension */ __('Would improve: %s', 'agency-client-portal'), $purpose)
                );
            }
        }
    }
    
    /**
     * Vérifier l'accès à la base de données
     */
    private function check_database_access() {
        global $wpdb;
        
        // Test de connexion
        $connection_test = $wpdb->check_connection();
        if ($connection_test) {
            $this->requirements[] = array(
                'type' => 'success',
                'title' => __('Database Connection', 'agency-client-portal'),
                'message' => __('✓ Operational', 'agency-client-portal'),
                'details' => __('MySQL/MariaDB connection active', 'agency-client-portal')
            );
        } else {
            $this->errors[] = array(
                'type' => 'error',
                'title' => __('Database Connection', 'agency-client-portal'),
                'message' => __('Connection failed', 'agency-client-portal'),
                'details' => __('Check the database configuration', 'agency-client-portal')
            );
            return; // Arrêter les autres tests DB
        }
        
        // Test des permissions
        $table_test = $wpdb->prefix . 'agency_test_' . time();
        $create_result = $wpdb->query("CREATE TABLE $table_test (id INT PRIMARY KEY) ENGINE=InnoDB");
        
        if ($create_result !== false) {
            $wpdb->query("DROP TABLE $table_test");
            $this->requirements[] = array(
                'type' => 'success',
                'title' => __('Database Permissions', 'agency-client-portal'),
                'message' => __('✓ CREATE/DROP allowed', 'agency-client-portal'),
                'details' => __('Can create Agency tables', 'agency-client-portal')
            );
        } else {
            $this->errors[] = array(
                'type' => 'error',
                'title' => __('Database Permissions', 'agency-client-portal'),
                'message' => __('Insufficient permissions', 'agency-client-portal'),
                'details' => __('CREATE TABLE required for installation', 'agency-client-portal')
            );
        }
        
        // Test du charset UTF-8
        $charset = $wpdb->get_charset_collate();
        if (strpos($charset, 'utf8') !== false) {
            $this->requirements[] = array(
                'type' => 'success',
                'title' => __('Database Charset', 'agency-client-portal'),
                'message' => __('✓ UTF-8 detected', 'agency-client-portal'),
                'details' => __('International character support', 'agency-client-portal')
            );
        } else {
            $this->warnings[] = array(
                'type' => 'warning',
                'title' => __('Database Charset', 'agency-client-portal'),
                'message' => __('UTF-8 not detected', 'agency-client-portal'),
                'details' => __('May cause issues with emojis and special characters', 'agency-client-portal')
            );
        }
    }
    
    /**
     * Vérifier les permissions de fichiers
     */
    private function check_file_permissions() {
        $upload_dir = AGENCY_UPLOAD_DIR;
        
        // Vérifier que le dossier d'upload peut être créé
        if (is_dir($upload_dir) || wp_mkdir_p($upload_dir)) {
            $this->requirements[] = array(
                'type' => 'success',
                'title' => __('Agency Upload Folder', 'agency-client-portal'),
                'message' => sprintf(/* translators: %s: upload folder path */ __('✓ Accessible (%s)', 'agency-client-portal'), $upload_dir),
                'details' => __('Secure storage of client files', 'agency-client-portal')
            );
            
            // Test d'écriture
            $test_file = $upload_dir . '/test_' . time() . '.txt';
            if (Agency_Assets_Manager::filesystem()->put_contents($test_file, 'test')) {
                wp_delete_file($test_file);
                $this->requirements[] = array(
                    'type' => 'success',
                    'title' => __('File Writing', 'agency-client-portal'),
                    'message' => __('✓ Write permissions OK', 'agency-client-portal'),
                    'details' => __('Can save client assets', 'agency-client-portal')
                );
            } else {
                $this->errors[] = array(
                    'type' => 'error',
                    'title' => __('File Writing', 'agency-client-portal'),
                    'message' => __('Insufficient permissions', 'agency-client-portal'),
                    'details' => __('CHMOD 755 or 777 required on the upload folder', 'agency-client-portal')
                );
            }
        } else {
            $this->errors[] = array(
                'type' => 'error',
                'title' => __('Agency Upload Folder', 'agency-client-portal'),
                'message' => sprintf(/* translators: %s: folder path */ __('Unable to create %s', 'agency-client-portal'), $upload_dir),
                'details' => __('Check the wp-content folder permissions', 'agency-client-portal')
            );
        }
    }
    
    /**
     * Vérifier les fonctions de sécurité
     */
    private function check_security_functions() {
        // random_bytes (recommandé)
        if (function_exists('random_bytes')) {
            $this->requirements[] = array(
                'type' => 'success',
                'title' => __('Random Generator', 'agency-client-portal'),
                'message' => __('✓ random_bytes() available', 'agency-client-portal'),
                'details' => __('Cryptographically secure generation', 'agency-client-portal')
            );
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            $this->warnings[] = array(
                'type' => 'warning',
                'title' => __('Random Generator', 'agency-client-portal'),
                'message' => __('Fallback: openssl_random_pseudo_bytes()', 'agency-client-portal'),
                'details' => __('Works but random_bytes() is recommended', 'agency-client-portal')
            );
        } else {
            $this->errors[] = array(
                'type' => 'error',
                'title' => __('Random Generator', 'agency-client-portal'),
                'message' => __('No secure function available', 'agency-client-portal'),
                'details' => __('PHP 7.0+ or the OpenSSL extension is required', 'agency-client-portal')
            );
        }
        
        // hash() function
        if (function_exists('hash') && in_array('sha256', hash_algos())) {
            $this->requirements[] = array(
                'type' => 'success',
                'title' => __('SHA-256 Hashing', 'agency-client-portal'),
                'message' => __('✓ Available', 'agency-client-portal'),
                'details' => __('Secure token hashing', 'agency-client-portal')
            );
        } else {
            $this->errors[] = array(
                'type' => 'error',
                'title' => __('SHA-256 Hashing', 'agency-client-portal'),
                'message' => __('Not available', 'agency-client-portal'),
                'details' => __('The hash extension is required for PHP', 'agency-client-portal')
            );
        }
        
        // hash_equals (protection timing attacks)
        if (function_exists('hash_equals')) {
            $this->requirements[] = array(
                'type' => 'success',
                'title' => __('Secure Comparison', 'agency-client-portal'),
                'message' => __('✓ hash_equals() available', 'agency-client-portal'),
                'details' => __('Protection against timing attacks', 'agency-client-portal')
            );
        } else {
            $this->warnings[] = array(
                'type' => 'warning',
                'title' => __('Secure Comparison', 'agency-client-portal'),
                'message' => __('hash_equals() not available', 'agency-client-portal'),
                'details' => __('PHP 5.6+ recommended for optimal security', 'agency-client-portal')
            );
        }
    }
    
    /**
     * Vérifier la configuration serveur
     */
    private function check_server_config() {
        // HTTPS
        if (is_ssl()) {
            $this->requirements[] = array(
                'type' => 'success',
                'title' => __('HTTPS Connection', 'agency-client-portal'),
                'message' => __('✓ SSL/TLS enabled', 'agency-client-portal'),
                'details' => __('Secure cookies and data encryption', 'agency-client-portal')
            );
        } else {
            $this->warnings[] = array(
                'type' => 'warning',
                'title' => __('HTTPS Connection', 'agency-client-portal'),
                'message' => __('HTTP detected (not secure)', 'agency-client-portal'),
                'details' => __('HTTPS strongly recommended for production', 'agency-client-portal')
            );
        }
        
        // Memory limit
        $memory_limit = ini_get('memory_limit');
        $memory_bytes = $this->parse_size($memory_limit);
        $min_memory = 64 * 1024 * 1024; // 64MB
        
        if ($memory_bytes >= $min_memory) {
            $this->requirements[] = array(
                'type' => 'success',
                'title' => __('PHP Memory', 'agency-client-portal'),
                'message' => sprintf(/* translators: %s: PHP memory limit */ __('✓ %s available', 'agency-client-portal'), $memory_limit),
                'details' => __('Sufficient to process tokens and data', 'agency-client-portal')
            );
        } else {
            $this->warnings[] = array(
                'type' => 'warning',
                'title' => __('PHP Memory', 'agency-client-portal'),
                'message' => sprintf(/* translators: %s: PHP memory limit */ __('%s (64M+ recommended)', 'agency-client-portal'), $memory_limit),
                'details' => __('May limit performance with many clients', 'agency-client-portal')
            );
        }
        
        // Max execution time
        $max_exec = ini_get('max_execution_time');
        if ($max_exec == 0 || $max_exec >= 30) {
            $this->requirements[] = array(
                'type' => 'success',
                'title' => __('Execution Time', 'agency-client-portal'),
                'message' => $max_exec == 0 ? __('✓ Unlimited', 'agency-client-portal') : sprintf(/* translators: %d: max execution time in seconds */ __('✓ %ds', 'agency-client-portal'), $max_exec),
                'details' => __('Sufficient for migration operations', 'agency-client-portal')
            );
        } else {
            $this->warnings[] = array(
                'type' => 'warning',
                'title' => __('Execution Time', 'agency-client-portal'),
                'message' => sprintf(/* translators: %d: max execution time in seconds */ __('%ds (30s+ recommended)', 'agency-client-portal'), $max_exec),
                'details' => __('May interrupt large migrations', 'agency-client-portal')
            );
        }
    }
    
    /**
     * Convertir une taille en bytes
     */
    private function parse_size($size) {
        $unit = strtolower(substr($size, -1));
        $value = (int)$size;
        
        switch ($unit) {
            case 'g': $value *= 1024;
            case 'm': $value *= 1024;
            case 'k': $value *= 1024;
        }
        
        return $value;
    }
    
    /**
     * Obtenir le résumé des vérifications
     */
    public function get_summary() {
        $total_requirements = count($this->requirements);
        $total_warnings = count($this->warnings);
        $total_errors = count($this->errors);
        
        $status = 'success';
        if ($total_errors > 0) {
            $status = 'error';
        } elseif ($total_warnings > 0) {
            $status = 'warning';
        }
        
        return array(
            'status' => $status,
            'requirements_ok' => $total_requirements,
            'warnings' => $total_warnings,
            'errors' => $total_errors,
            'requirements' => $this->requirements,
            'warnings_list' => $this->warnings,
            'errors_list' => $this->errors
        );
    }
    
    /**
     * Afficher le rapport complet
     */
    public function display_report() {
        $summary = $this->get_summary();
        
        echo "<div class='agency-requirements-report'>";
        
        // En-tête du rapport
        $status_colors = array(
            'success' => '#28a745',
            'warning' => '#ffc107', 
            'error' => '#dc3545'
        );
        
        $status_messages = array(
            'success' => __('✅ System ready for production', 'agency-client-portal'),
            'warning' => __('⚠️ Working, with a few recommended improvements', 'agency-client-portal'),
            'error' => __('❌ Critical issues to fix', 'agency-client-portal')
        );
        
        $color = $status_colors[$summary['status']];
        $message = $status_messages[$summary['status']];
        
        echo "<div style='background: " . esc_attr($color) . "; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; text-align: center;'>";
        echo "<h2 style='margin: 0; color: white;'>" . esc_html($message) . "</h2>";
        echo "<p style='margin: 10px 0 0 0; opacity: 0.9;'>";
        /* translators: 1: requirements met, 2: warnings, 3: errors */
        echo esc_html(sprintf(__('Requirements OK: %1$d | Warnings: %2$d | Errors: %3$d', 'agency-client-portal'), $summary['requirements_ok'], $summary['warnings'], $summary['errors']));
        echo "</p>";
        echo "</div>";
        
        // Prérequis satisfaits
        if (!empty($this->requirements)) {
            echo "<div style='background: white; padding: 15px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 15px;'>";
            echo "<h3 style='color: #28a745; margin-top: 0;'>✅ " . esc_html__('Requirements Met', 'agency-client-portal') . "</h3>";
            foreach ($this->requirements as $req) {
                echo "<div style='margin: 10px 0; padding: 10px; background: #f8f9fa; border-left: 4px solid #28a745;'>";
                echo '<strong>' . esc_html($req['title']) . '</strong>: ' . esc_html($req['message']) . '<br>';
                echo "<small style='color: #666;'>" . esc_html($req['details']) . '</small>';
                echo "</div>";
            }
            echo "</div>";
        }
        
        // Avertissements
        if (!empty($this->warnings)) {
            echo "<div style='background: white; padding: 15px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 15px;'>";
            echo "<h3 style='color: #ffc107; margin-top: 0;'>⚠️ " . esc_html__('Recommended Improvements', 'agency-client-portal') . "</h3>";
            foreach ($this->warnings as $warning) {
                echo "<div style='margin: 10px 0; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107;'>";
                echo '<strong>' . esc_html($warning['title']) . '</strong>: ' . esc_html($warning['message']) . '<br>';
                echo "<small style='color: #856404;'>" . esc_html($warning['details']) . '</small>';
                echo "</div>";
            }
            echo "</div>";
        }
        
        // Erreurs
        if (!empty($this->errors)) {
            echo "<div style='background: white; padding: 15px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 15px;'>";
            echo "<h3 style='color: #dc3545; margin-top: 0;'>❌ " . esc_html__('Critical Issues', 'agency-client-portal') . "</h3>";
            foreach ($this->errors as $error) {
                echo "<div style='margin: 10px 0; padding: 10px; background: #f8d7da; border-left: 4px solid #dc3545;'>";
                echo '<strong>' . esc_html($error['title']) . '</strong>: ' . esc_html($error['message']) . '<br>';
                echo "<small style='color: #721c24;'>" . esc_html($error['details']) . '</small>';
                echo "</div>";
            }
            echo "</div>";
        }
        
        echo "</div>";
    }
}