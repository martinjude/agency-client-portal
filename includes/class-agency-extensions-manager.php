<?php
/**
 * Point d'extension pour les add-ons Agency.
 *
 * Les extensions sont des plugins WordPress standards (installés via l'écran
 * Extensions de WordPress) qui se branchent sur le hook `agency_load_extensions`
 * et sur les autres actions/filtres exposés par le plugin.
 *
 * @package Agency
 */

if (!defined('ABSPATH')) {
    exit;
}

class Agency_Extensions_Manager {

    /**
     * Constructeur
     */
    public function __construct() {
        add_action('plugins_loaded', array($this, 'load_extensions'), 20);
    }

    /**
     * Signale aux add-ons que le cœur Agency est prêt.
     *
     * @return void
     */
    public function load_extensions() {
        /**
         * Hook déclenché une fois le cœur Agency chargé.
         * Les add-ons (plugins séparés) s'y branchent pour s'initialiser.
         */
        do_action('agency_load_extensions');
    }
}
