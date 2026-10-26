<?php
/**
 * Autoloader pour charger automatiquement les classes du plugin
 *
 * @package Agency
 */

if (!defined('ABSPATH')) {
    exit;
}

class Agency_Autoloader {
    
    /**
     * Enregistrer l'autoloader
     */
    public static function register() {
        spl_autoload_register(array(__CLASS__, 'autoload'));
    }
    
    /**
     * Autoload des classes
     */
    public static function autoload($class) {
        // Vérifier si la classe commence par "Agency_"
        if (strpos($class, 'Agency_') !== 0) {
            return;
        }
        
        // Convertir le nom de classe en nom de fichier
        $class_file = strtolower(str_replace('_', '-', $class));
        $class_file = 'class-' . $class_file . '.php';
        
        // Chemins possibles
        $paths = array(
            AGENCY_PLUGIN_DIR . 'includes/',
            AGENCY_PLUGIN_DIR . 'includes/admin/',
            AGENCY_PLUGIN_DIR . 'includes/frontend/',
            AGENCY_PLUGIN_DIR . 'includes/api/',
            AGENCY_PLUGIN_DIR . 'includes/models/',
            AGENCY_PLUGIN_DIR . 'includes/controllers/',
        );
        
        // Chercher le fichier dans les différents chemins
        foreach ($paths as $path) {
            $file = $path . $class_file;
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
}
