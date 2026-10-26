<?php
/**
 * Gestion du dossier d'upload pour les images de couverture des projets.
 *
 * Redirige les uploads WP Media vers /uploads/agency/[client-slug]/[project-slug]/
 * en s'appuyant sur un contexte temporaire (transient) par utilisateur.
 *
 * @package Agency
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Agency_Cover_Upload
 */
class Agency_Cover_Upload {

	/**
	 * Préfixe de la clé de transient.
	 *
	 * @var string
	 */
	const TRANSIENT_PREFIX = 'agency_upload_ctx_';

	/**
	 * Durée de vie du contexte (secondes).
	 *
	 * @var int
	 */
	const CONTEXT_TTL = 300; // 5 minutes

	/**
	 * Constructeur — enregistre les hooks.
	 */
	public function __construct() {
		add_action( 'wp_ajax_agency_set_upload_context', array( $this, 'set_upload_context' ) );
		add_filter( 'upload_dir', array( $this, 'custom_upload_dir' ) );
	}

	/**
	 * Endpoint AJAX : enregistre le contexte d'upload (client + projet) dans un transient.
	 *
	 * POST agency_set_upload_context
	 *   nonce        string  Nonce wp_rest
	 *   client_slug  string  Slug du client
	 *   project_slug string  Slug du projet (optionnel pour les nouveaux projets)
	 */
	public function set_upload_context() {
		check_ajax_referer( 'wp_rest', 'nonce' );

		if ( ! current_user_can( 'manage_agency_projects' ) ) {
			wp_send_json_error( array( 'message' => __('Unauthorized', 'agency-client-portal') ), 403 );
			return;
		}

		$client_slug  = sanitize_title( sanitize_text_field( wp_unslash( $_POST['client_slug'] ?? '' ) ) );
		$project_slug = sanitize_title( sanitize_text_field( wp_unslash( $_POST['project_slug'] ?? '' ) ) );

		if ( empty( $client_slug ) ) {
			wp_send_json_error( array( 'message' => __('client_slug is required', 'agency-client-portal') ) );
			return;
		}

		set_transient(
			self::TRANSIENT_PREFIX . get_current_user_id(),
			array(
				'client_slug'  => $client_slug,
				'project_slug' => $project_slug,
			),
			self::CONTEXT_TTL
		);

		wp_send_json_success();
	}

	/**
	 * Filtre upload_dir : redirige vers /uploads/agency/[client]/[projet]/ si un contexte est actif.
	 *
	 * @param  array $uploads Tableau de chemins/URLs WordPress.
	 * @return array          Tableau modifié.
	 */
	public function custom_upload_dir( $uploads ) {
		if ( ! function_exists( 'is_user_logged_in' ) || ! is_user_logged_in() ) {
			return $uploads;
		}

		$context = get_transient( self::TRANSIENT_PREFIX . get_current_user_id() );

		if ( ! $context || empty( $context['client_slug'] ) ) {
			return $uploads;
		}

		$subdir = '/agency/' . $context['client_slug'];

		if ( ! empty( $context['project_slug'] ) ) {
			$subdir .= '/' . $context['project_slug'];
		}

		// Créer le dossier si nécessaire
		$path = $uploads['basedir'] . $subdir;
		if ( ! file_exists( $path ) ) {
			wp_mkdir_p( $path );
		}

		$uploads['path']   = $path;
		$uploads['url']    = $uploads['baseurl'] . $subdir;
		$uploads['subdir'] = $subdir;

		return $uploads;
	}
}
