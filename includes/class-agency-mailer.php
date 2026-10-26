<?php
/**
 * Agency_Mailer — Infrastructure d'envoi d'emails (couche bas niveau).
 *
 * Cette classe ne connaît rien aux commentaires ni aux projets : elle se contente
 * de fournir un modèle HTML configurable, de remplacer les jetons {{...}} et
 * d'envoyer via wp_mail() en HTML. La logique « quoi envoyer / quand » vit dans
 * Agency_Notifications.
 *
 * @package Agency
 */

if (!defined('ABSPATH')) {
    exit;
}

class Agency_Mailer {

    /**
     * Clé d'option stockant le modèle HTML personnalisé.
     */
    const OPTION_TEMPLATE = 'agency_email_template_html';

    /**
     * Modèle HTML par défaut (table-based, styles inline pour compat clients mail).
     * Jetons disponibles : voir get_available_tokens().
     */
    const DEFAULT_TEMPLATE = <<<'HTML'
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{subject}}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f5f7; font-family:Arial, Helvetica, sans-serif; color:#1a1a1a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f5f7; padding:32px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e0e0e0;">
                    <tr>
                        <td style="background-color:#111111; padding:24px 32px;">
                            <span style="color:#ffffff; font-size:20px; font-weight:bold; letter-spacing:0.5px;">{{site_name}}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 16px; font-size:16px;">{{greeting}}</p>
                            <div style="margin:0 0 24px; font-size:15px; line-height:1.6; color:#333333;">{{message}}</div>
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="border-radius:8px; background-color:#111111;">
                                        <a href="{{button_url}}" target="_blank" style="display:inline-block; padding:12px 28px; font-size:15px; font-weight:bold; color:#ffffff; text-decoration:none; border-radius:8px;">{{button_label}}</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px; background-color:#fafafa; border-top:1px solid #eeeeee;">
                            <p style="margin:0; font-size:12px; color:#999999;">{{site_name}} — <a href="{{site_url}}" style="color:#999999;">{{site_url}}</a></p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

    /**
     * Retourne le modèle HTML actif (personnalisé ou par défaut).
     *
     * @return string
     */
    public static function get_template() {
        $stored = get_option(self::OPTION_TEMPLATE, '');
        $stored = is_string($stored) ? trim($stored) : '';
        return $stored !== '' ? $stored : self::DEFAULT_TEMPLATE;
    }

    /**
     * Liste documentée des jetons disponibles (affichée dans l'admin).
     *
     * @return array<string,string> token => description
     */
    public static function get_available_tokens() {
        return array(
            'subject'         => __('Email subject', 'agency-client-portal'),
            'greeting'        => __('Greeting (e.g. "Hello John,")', 'agency-client-portal'),
            'message'         => __('Main message content (HTML allowed)', 'agency-client-portal'),
            'button_url'      => __('Action button URL', 'agency-client-portal'),
            'button_label'    => __('Action button label', 'agency-client-portal'),
            'recipient_name'  => __('Recipient name', 'agency-client-portal'),
            'commenter_name'  => __('Comment author name', 'agency-client-portal'),
            'comment_content' => __('Comment content', 'agency-client-portal'),
            'project_name'    => __('Project name', 'agency-client-portal'),
            'client_name'     => __('Client name', 'agency-client-portal'),
            'site_name'       => __('Site name', 'agency-client-portal'),
            'logo_url'        => __('Brand logo URL', 'agency-client-portal'),
            'site_url'        => __('Site URL', 'agency-client-portal'),
            'year'            => __('Current year', 'agency-client-portal'),
        );
    }

    /**
     * Variables globales injectées dans chaque email (échappées).
     *
     * @return array<string,string>
     */
    private static function global_vars() {
        return array(
            'site_name' => esc_html(get_bloginfo('name')),
            'logo_url'  => esc_url((string) get_option('agency_brand_logo_url', '')),
            'site_url'  => esc_url(home_url('/')),
            'year'      => esc_html(wp_date('Y')),
        );
    }

    /**
     * Remplace les jetons {{cle}} d'un modèle par les valeurs fournies.
     * Les jetons inconnus sont supprimés (rendus vides).
     *
     * Les valeurs sont supposées déjà échappées par l'appelant ; les champs
     * riches (message, comment_content) contiennent du HTML déjà assaini.
     *
     * @param string               $template
     * @param array<string,string> $vars
     * @return string
     */
    public static function render($template, array $vars) {
        return preg_replace_callback(
            '/\{\{\s*([a-z0-9_]+)\s*\}\}/i',
            function ($matches) use ($vars) {
                $key = strtolower($matches[1]);
                return isset($vars[$key]) ? (string) $vars[$key] : '';
            },
            (string) $template
        );
    }

    /**
     * Rend le modèle actif et envoie l'email en HTML.
     *
     * @param string|array         $to      Destinataire(s).
     * @param string               $subject Sujet.
     * @param array<string,string> $vars    Variables de gabarit (déjà échappées).
     * @return bool Résultat de wp_mail().
     */
    public static function send($to, $subject, array $vars) {
        $vars = array_merge(self::global_vars(), $vars);

        if (!isset($vars['subject'])) {
            $vars['subject'] = esc_html($subject);
        }

        $body    = self::render(self::get_template(), $vars);
        $headers = array('Content-Type: text/html; charset=UTF-8');

        /**
         * Permet de filtrer destinataire, sujet, corps et en-têtes avant envoi.
         */
        $mail = apply_filters('agency_mail_args', array(
            'to'      => $to,
            'subject' => $subject,
            'body'    => $body,
            'headers' => $headers,
        ), $vars);

        return wp_mail($mail['to'], $mail['subject'], $mail['body'], $mail['headers']);
    }

    /**
     * Construit l'URL directe d'un bloc (destinataires disposant déjà d'un accès,
     * ex. staff agence). Cf. templates/project-view.php.
     *
     * @param object $client
     * @param object $project
     * @param int    $block_id
     * @return string
     */
    public static function build_block_url($client, $project, $block_id) {
        return home_url("/client/{$client->slug}/project/{$project->slug}/block/" . intval($block_id));
    }

    /**
     * Construit l'URL d'accès par jeton vers un bloc (clients sans cookie).
     * Réutilise le token permanent (token_secret) sans en régénérer un nouveau,
     * pour ne pas invalider une session cliente en cours.
     *
     * @param object $client
     * @param object $project
     * @param int    $block_id
     * @return string|false URL ou false si aucun lien à jeton n'est récupérable.
     */
    public static function build_token_block_url($client, $project, $block_id) {
        $access_url = Agency_Client::get_access_url($client->id);
        if (!$access_url) {
            return false;
        }

        $block_path = "/client/{$client->slug}/project/{$project->slug}/block/" . intval($block_id);
        return add_query_arg('redirect', $block_path, $access_url);
    }

    /**
     * Liste d'éléments/attributs HTML autorisés pour un modèle d'email
     * (wp_kses_post retire styles inline et tables). Utilisé côté admin pour
     * assainir le modèle enregistré.
     *
     * @return array
     */
    public static function get_allowed_html() {
        $common_attrs = array(
            'style' => true,
            'class' => true,
            'id'    => true,
            'align' => true,
            'width' => true,
            'height'=> true,
            'bgcolor' => true,
            'valign'  => true,
            'border'  => true,
            'cellpadding' => true,
            'cellspacing' => true,
            'role'    => true,
            'dir'     => true,
            'lang'    => true,
        );

        $tags = array(
            'html', 'head', 'body', 'meta', 'title',
            'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th',
            'div', 'span', 'p', 'a', 'img', 'br', 'hr',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'strong', 'b', 'em', 'i', 'u', 'small',
            'ul', 'ol', 'li', 'center', 'font',
        );

        $allowed = array();
        foreach ($tags as $tag) {
            $allowed[$tag] = $common_attrs;
        }

        // Attributs spécifiques.
        $allowed['a']['href']    = true;
        $allowed['a']['target']  = true;
        $allowed['a']['rel']     = true;
        $allowed['img']['src']   = true;
        $allowed['img']['alt']   = true;
        $allowed['meta']['charset'] = true;
        $allowed['meta']['name']    = true;
        $allowed['meta']['content'] = true;
        $allowed['font']['color']   = true;
        $allowed['font']['face']    = true;
        $allowed['font']['size']    = true;

        return $allowed;
    }
}
