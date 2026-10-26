<?php
/**
 * Gestion de la confidentialité RGPD / GDPR
 *
 * Ajoute les hooks WordPress pour la politique de confidentialité,
 * l'export et l'effacement des données personnelles.
 *
 * @package Agency
 */

if (!defined('ABSPATH')) {
    exit;
}

class Agency_Privacy {

    /**
     * Constructeur : enregistre les hooks GDPR
     */
    public function __construct() {
        // Suggestion de politique de confidentialité
        add_action('admin_init', array($this, 'add_privacy_policy_content'));

        // Exporteur de données personnelles
        add_filter('wp_privacy_personal_data_exporters', array($this, 'register_exporter'));

        // Effaceur de données personnelles
        add_filter('wp_privacy_personal_data_erasers', array($this, 'register_eraser'));
    }

    /**
     * Ajouter le contenu suggéré pour la politique de confidentialité
     */
    public function add_privacy_policy_content() {
        if (!function_exists('wp_add_privacy_policy_content')) {
            return;
        }

        $content = '<h2>' . esc_html__('Agency Client Portal', 'agency-client-portal') . '</h2>';

        $content .= '<p>' . esc_html__('This plugin stores the following data in the WordPress database:', 'agency-client-portal') . '</p>';
        $content .= '<ul>';
        $content .= '<li>' . esc_html__('Client names and associated identifiers', 'agency-client-portal') . '</li>';
        $content .= '<li>' . esc_html__('Access tokens (stored hashed) for passwordless authentication', 'agency-client-portal') . '</li>';
        $content .= '<li>' . esc_html__('Files uploaded by clients and the team (stored in the wp-content/agency-uploads/ directory)', 'agency-client-portal') . '</li>';
        $content .= '<li>' . esc_html__('Comments left by clients and the team on project blocks', 'agency-client-portal') . '</li>';
        $content .= '</ul>';

        $content .= '<p>' . esc_html__('Google Fonts may be loaded, which sends the visitor\'s IP address and user agent to Google\'s servers.', 'agency-client-portal') . '</p>';

        wp_add_privacy_policy_content(
            __('Agency Client Portal', 'agency-client-portal'),
            wp_kses_post($content)
        );
    }

    /**
     * Enregistrer l'exporteur de données personnelles
     *
     * @param array $exporters Liste des exporteurs existants
     * @return array
     */
    public function register_exporter($exporters) {
        $exporters['agency-client-portal'] = array(
            'exporter_friendly_name' => __('Agency Client Portal', 'agency-client-portal'),
            'callback'               => array($this, 'export_personal_data'),
        );
        return $exporters;
    }

    /**
     * Exporter les données personnelles d'un utilisateur
     *
     * @param string $email_address Adresse email de l'utilisateur
     * @param int    $page          Numéro de page (non utilisé, tout est exporté en une fois)
     * @return array Données exportées au format WordPress standard
     */
    public function export_personal_data($email_address, $page = 1) {
        global $wpdb;

        $export_items = array();

        $user = get_user_by('email', $email_address);
        if (!$user) {
            return array(
                'data' => $export_items,
                'done' => true,
            );
        }

        // Exporter les données clients liées à cet utilisateur
        $clients_table = $wpdb->prefix . 'agency_clients';
        $clients = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, slug, status FROM {$clients_table} WHERE user_id = %d",
            $user->ID
        ));

        if ($clients) {
            foreach ($clients as $client) {
                $export_items[] = array(
                    'group_id'          => 'agency-clients',
                    'group_label'       => __('Agency Clients', 'agency-client-portal'),
                    'group_description' => __('Client data managed through Agency Client Portal.', 'agency-client-portal'),
                    'item_id'           => 'agency-client-' . intval($client->id),
                    'data'              => array(
                        array(
                            'name'  => __('Client name', 'agency-client-portal'),
                            'value' => $client->name,
                        ),
                        array(
                            'name'  => __('Slug', 'agency-client-portal'),
                            'value' => $client->slug,
                        ),
                        array(
                            'name'  => __('Status', 'agency-client-portal'),
                            'value' => $client->status,
                        ),
                    ),
                );
            }
        }

        // Exporter les commentaires de cet utilisateur
        $comments_table = $wpdb->prefix . 'agency_comments';
        $comments = $wpdb->get_results($wpdb->prepare(
            "SELECT id, content, type, status, created_at FROM {$comments_table} WHERE user_id = %d",
            $user->ID
        ));

        if ($comments) {
            foreach ($comments as $comment) {
                $export_items[] = array(
                    'group_id'          => 'agency-comments',
                    'group_label'       => __('Agency Comments', 'agency-client-portal'),
                    'group_description' => __('Comments left through Agency Client Portal.', 'agency-client-portal'),
                    'item_id'           => 'agency-comment-' . intval($comment->id),
                    'data'              => array(
                        array(
                            'name'  => __('Content', 'agency-client-portal'),
                            'value' => $comment->content,
                        ),
                        array(
                            'name'  => __('Type', 'agency-client-portal'),
                            'value' => $comment->type,
                        ),
                        array(
                            'name'  => __('Status', 'agency-client-portal'),
                            'value' => $comment->status,
                        ),
                        array(
                            'name'  => __('Creation date', 'agency-client-portal'),
                            'value' => $comment->created_at,
                        ),
                    ),
                );
            }
        }

        return array(
            'data' => $export_items,
            'done' => true,
        );
    }

    /**
     * Enregistrer l'effaceur de données personnelles
     *
     * @param array $erasers Liste des effaceurs existants
     * @return array
     */
    public function register_eraser($erasers) {
        $erasers['agency-client-portal'] = array(
            'eraser_friendly_name' => __('Agency Client Portal', 'agency-client-portal'),
            'callback'             => array($this, 'erase_personal_data'),
        );
        return $erasers;
    }

    /**
     * Effacer / anonymiser les données personnelles d'un utilisateur
     *
     * Les commentaires sont anonymisés (contenu vidé).
     * Les fiches clients sont conservées (rétention métier) mais l'effacement est consigné.
     *
     * @param string $email_address Adresse email de l'utilisateur
     * @param int    $page          Numéro de page (non utilisé)
     * @return array Résultat au format WordPress standard
     */
    public function erase_personal_data($email_address, $page = 1) {
        global $wpdb;

        $items_removed  = 0;
        $items_retained = 0;
        $messages       = array();

        $user = get_user_by('email', $email_address);
        if (!$user) {
            return array(
                'items_removed'  => $items_removed,
                'items_retained' => $items_retained,
                'messages'       => $messages,
                'done'           => true,
            );
        }

        // Anonymiser les commentaires de cet utilisateur
        $comments_table = $wpdb->prefix . 'agency_comments';
        $comments_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$comments_table} WHERE user_id = %d",
            $user->ID
        ));

        if ($comments_count > 0) {
            $wpdb->update(
                $comments_table,
                array('content' => ''),
                array('user_id' => $user->ID),
                array('%s'),
                array('%d')
            );
            $items_removed += $comments_count;
        }

        // Conserver les fiches clients (rétention métier) et consigner la demande
        $clients_table = $wpdb->prefix . 'agency_clients';
        $clients_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$clients_table} WHERE user_id = %d",
            $user->ID
        ));

        if ($clients_count > 0) {
            $items_retained += $clients_count;
            $messages[] = sprintf(
                /* translators: %d: nombre de fiches clients conservées */
                __('%d client record(s) retained for business retention reasons.', 'agency-client-portal'),
                $clients_count
            );
            error_log(sprintf(
                '[Agency] GDPR erasure request for user #%d — %d client record(s) retained.',
                $user->ID,
                $clients_count
            ));
        }

        return array(
            'items_removed'  => $items_removed,
            'items_retained' => $items_retained,
            'messages'       => $messages,
            'done'           => true,
        );
    }
}
