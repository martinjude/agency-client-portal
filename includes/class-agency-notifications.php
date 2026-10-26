<?php
/**
 * Agency_Notifications — Couche « quoi envoyer / quand » (politique de notification).
 *
 * S'appuie sur Agency_Mailer pour le rendu et l'envoi. Pour ajouter un nouveau
 * type d'email, ajouter une méthode ici — aucune nouvelle plomberie nécessaire.
 *
 * @package Agency
 */

if (!defined('ABSPATH')) {
    exit;
}

class Agency_Notifications {

    /**
     * Enregistre les hooks. Appelé au bootstrap.
     */
    public static function init() {
        add_action('agency_comment_notification', array(__CLASS__, 'on_comment_notification'), 10, 4);
    }

    /**
     * Notifie le créateur du projet lorsqu'un commentaire est posté sur un bloc.
     *
     * Déclenché par do_action('agency_comment_notification', ...) dans
     * Agency_Comment::create().
     *
     * @param object $comment
     * @param object $block
     * @param object $project
     * @param object $client
     */
    public static function on_comment_notification($comment, $block, $project, $client) {
        // 1) Bascule par bloc : activée par défaut (clé absente => activée).
        if (!Agency_Block::notify_on_comment_enabled($block)) {
            return;
        }

        // 2) Destinataire = créateur du projet (utilisateur WP).
        $creator_id = isset($project->created_by) ? (int) $project->created_by : 0;
        $creator    = $creator_id ? get_userdata($creator_id) : null;
        $to         = $creator ? $creator->user_email : '';

        /**
         * Filtre le destinataire de la notification de commentaire.
         */
        $to = apply_filters('agency_comment_notification_recipient', $to, $project, $block, $comment, $client);

        if (empty($to) || !is_email($to)) {
            return;
        }

        // 3) Ne pas se notifier soi-même.
        if ($creator_id && (int) $comment->user_id === $creator_id) {
            return;
        }

        // 4) Nom de l'auteur du commentaire.
        if ((int) $comment->user_id > 0) {
            $author = get_userdata((int) $comment->user_id);
            $commenter_name = $author ? $author->display_name : __('User', 'agency-client-portal');
        } else {
            // Commentaire client (auth par jeton, pas d'utilisateur WP).
            $commenter_name = !empty($client->name) ? $client->name : __('The client', 'agency-client-portal');
        }

        // 5) Construire les variables et envoyer.
        $recipient_name = $creator->display_name;
        $block_title    = !empty($block->title) ? $block->title : __('a block', 'agency-client-portal');
        $project_name   = !empty($project->name) ? $project->name : '';
        $comment_html   = wpautop(wp_kses_post($comment->content));

        $message  = '<p>' . sprintf(
            /* translators: 1: commenter name, 2: block title, 3: project name */
            esc_html__('%1$s posted a comment on "%2$s" in the project "%3$s":', 'agency-client-portal'),
            '<strong>' . esc_html($commenter_name) . '</strong>',
            esc_html($block_title),
            esc_html($project_name)
        ) . '</p>';
        $message .= '<blockquote style="margin:12px 0; padding:12px 16px; border-left:3px solid #111111; background:#f7f7f7; color:#333333;">' . $comment_html . '</blockquote>';

        $subject = sprintf(
            /* translators: 1: commenter name, 2: project name */
            __('New comment from %1$s on %2$s', 'agency-client-portal'),
            $commenter_name,
            $project_name
        );

        Agency_Mailer::send($to, $subject, array(
            'subject'         => esc_html($subject),
            'greeting'        => sprintf(/* translators: %s: recipient name */ esc_html__('Hello %s,', 'agency-client-portal'), esc_html($recipient_name)),
            'message'         => $message,
            'button_url'      => esc_url(Agency_Mailer::build_block_url($client, $project, $block->id)),
            'button_label'    => esc_html__('View comment', 'agency-client-portal'),
            'recipient_name'  => esc_html($recipient_name),
            'commenter_name'  => esc_html($commenter_name),
            'comment_content' => $comment_html,
            'project_name'    => esc_html($project_name),
            'client_name'     => esc_html(isset($client->name) ? $client->name : ''),
        ));
    }

    /**
     * Envoi manuel d'une notification au client (bouton « Notifier le client »).
     *
     * @param int   $block_id
     * @param array $args { subject, name, message, email }
     * @return true|WP_Error
     */
    public static function send_manual_notification($block_id, array $args) {
        $block = Agency_Block::get_by_id(intval($block_id));
        if (!$block) {
            return new WP_Error('block_not_found', __('Block not found.', 'agency-client-portal'), array('status' => 404));
        }

        $project = Agency_Project::get_by_id($block->project_id);
        if (!$project) {
            return new WP_Error('project_not_found', __('Project not found.', 'agency-client-portal'), array('status' => 404));
        }

        $client = Agency_Client::get_by_id($project->client_id);
        if (!$client) {
            return new WP_Error('client_not_found', __('Client not found.', 'agency-client-portal'), array('status' => 404));
        }

        $to = !empty($args['email']) ? sanitize_email($args['email']) : '';
        if (empty($to) || !is_email($to)) {
            return new WP_Error('invalid_recipient', __('Recipient email address is invalid or missing.', 'agency-client-portal'), array('status' => 400));
        }

        // Lien à jeton vers le bloc (le client n'a pas forcément de cookie).
        $button_url = Agency_Mailer::build_token_block_url($client, $project, $block->id);
        if (!$button_url) {
            return new WP_Error(
                'no_access_link',
                __('The client\'s access link cannot be retrieved. Regenerate it once from the client record.', 'agency-client-portal'),
                array('status' => 409)
            );
        }

        $recipient_name = isset($args['name']) ? sanitize_text_field($args['name']) : '';
        $subject        = isset($args['subject']) && $args['subject'] !== ''
            ? sanitize_text_field($args['subject'])
            : sprintf(/* translators: %s: project name */ __('Update on your project %s', 'agency-client-portal'), $project->name);
        $message_html   = isset($args['message']) ? wpautop(wp_kses_post($args['message'])) : '';

        $greeting = $recipient_name !== ''
            ? sprintf(/* translators: %s: recipient name */ esc_html__('Hello %s,', 'agency-client-portal'), esc_html($recipient_name))
            : esc_html__('Hello,', 'agency-client-portal');

        $sent = Agency_Mailer::send($to, $subject, array(
            'subject'        => esc_html($subject),
            'greeting'       => $greeting,
            'message'        => $message_html,
            'button_url'     => esc_url($button_url),
            'button_label'   => esc_html__('View project', 'agency-client-portal'),
            'recipient_name' => esc_html($recipient_name),
            'project_name'   => esc_html($project->name),
            'client_name'    => esc_html(isset($client->name) ? $client->name : ''),
        ));

        if (!$sent) {
            return new WP_Error('send_failed', __('Sending the email failed.', 'agency-client-portal'), array('status' => 500));
        }

        return true;
    }
}
