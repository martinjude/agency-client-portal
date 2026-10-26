<?php
/**
 * Theme Presets
 * Définit les préréglages de thème pour le portail client.
 *
 * @package Agency
 */

if (!defined('ABSPATH')) {
    exit;
}

class Agency_Themes {

    /**
     * Retourne tous les préréglages de thème.
     */
    public static function get_presets() {
        return array(
            'clair' => array(
                'label'       => __('Light', 'agency-client-portal'),
                'description' => __('Clean, professional, timeless.', 'agency-client-portal'),
                'accent'      => '#ff2020',
                'bg'          => '#FAFAF9',
                'card_bg'     => '#FFFFFF',
                'border'      => '#E8E5E2',
                'radius'      => 18,
                'dark'        => false,
                'options'     => array(
                    'agency_ui_font'            => 'inter',
                    'agency_card_border_radius' => 18,
                    'agency_card_border_width'  => 1,
                    'agency_card_border_color'  => '#E8E5E2',
                    'agency_bg_color_light'     => '#FAFAF9',
                    'agency_bg_color_dark'      => '#1C1917',
                    'agency_theme_mode'         => 'light',
                ),
            ),
            'sombre' => array(
                'label'       => __('Dark', 'agency-client-portal'),
                'description' => __('Modern, futuristic, vibrant.', 'agency-client-portal'),
                'accent'      => '#7C3AED',
                'bg'          => '#14141f',
                'card_bg'     => '#12111d',
                'border'      => '#2A2035',
                'radius'      => 18,
                'dark'        => true,
                'options'     => array(
                    'agency_ui_font'            => 'poppins',
                    'agency_card_border_radius' => 18,
                    'agency_card_border_width'  => 1,
                    'agency_card_border_color'  => '#2A2035',
                    'agency_bg_color_light'     => '#F5F5F5',
                    'agency_bg_color_dark'      => '#14141f',
                    'agency_theme_mode'         => 'dark',
                ),
            ),
            'neutre' => array(
                'label'       => __('Neutral light', 'agency-client-portal'),
                'description' => __('SaaS, indigo, versatile.', 'agency-client-portal'),
                'accent'      => '#6366F1',
                'bg'          => '#F4F6FB',
                'card_bg'     => '#FFFFFF',
                'border'      => '#E2E8F0',
                'radius'      => 20,
                'dark'        => false,
                'options'     => array(
                    'agency_ui_font'            => 'inter',
                    'agency_card_border_radius' => 20,
                    'agency_card_border_width'  => 1,
                    'agency_card_border_color'  => '#E2E8F0',
                    'agency_bg_color_light'     => '#F4F6FB',
                    'agency_bg_color_dark'      => '#0F1117',
                    'agency_theme_mode'         => 'light',
                ),
            ),
            'aurora' => array(
                'label'       => __('Aurora', 'agency-client-portal'),
                'description' => __('Deep blue with violet accents, night gradients.', 'agency-client-portal'),
                'accent'      => '#5B6BFF',
                'bg'          => '#0E1330',
                'card_bg'     => '#141A3D',
                'border'      => '#222A56',
                'radius'      => 22,
                'dark'        => true,
                'options'     => array(
                    'agency_ui_font'            => 'inter',
                    'agency_card_border_radius' => 22,
                    'agency_card_border_width'  => 1,
                    'agency_card_border_color'  => '#222A56',
                    'agency_bg_color_light'     => '#EEF1FF',
                    'agency_bg_color_dark'      => '#0E1330',
                    'agency_theme_mode'         => 'dark',
                ),
            ),
            'sunset' => array(
                'label'       => __('Sunset', 'agency-client-portal'),
                'description' => __('Warm coral and orange, creative energy.', 'agency-client-portal'),
                'accent'      => '#FF6B4A',
                'bg'          => '#FFF5EE',
                'card_bg'     => '#FFFFFF',
                'border'      => '#F5DCC9',
                'radius'      => 16,
                'dark'        => false,
                'options'     => array(
                    'agency_ui_font'            => 'poppins',
                    'agency_card_border_radius' => 16,
                    'agency_card_border_width'  => 1,
                    'agency_card_border_color'  => '#F5DCC9',
                    'agency_bg_color_light'     => '#FFF5EE',
                    'agency_bg_color_dark'      => '#2A1812',
                    'agency_theme_mode'         => 'light',
                ),
            ),
            'minuit' => array(
                'label'       => __('Midnight', 'agency-client-portal'),
                'description' => __('Deep night, indigo gradients, high contrast.', 'agency-client-portal'),
                'accent'      => '#5B6BFF',
                'bg'          => '#0E1330',
                'card_bg'     => '#141A3D',
                'border'      => '#222A56',
                'radius'      => 22,
                'dark'        => true,
                'options'     => array(
                    'agency_ui_font'            => 'inter',
                    'agency_card_border_radius' => 22,
                    'agency_card_border_width'  => 1,
                    'agency_card_border_color'  => '#222A56',
                    'agency_bg_color_light'     => '#EEF1FF',
                    'agency_bg_color_dark'      => '#0E1330',
                    'agency_theme_mode'         => 'dark',
                ),
                // Dégradés "full gradient UI" (fond + cartes + header + sidebar)
                'gradients'   => array(
                    'bg'      => 'linear-gradient(160deg, #202a57 100%, #040613 100%)',
                    'card'    => 'linear-gradient(155deg, #1e275a 100%, #191f46 100%)',
                    'header'  => 'linear-gradient(90deg, #111736 100%, #161D42 100%)',
                    'sidebar' => 'linear-gradient(195deg, #1d244b 100%, #0A0E22 100%)',
                ),
            ),
            'charbon' => array(
                'label'       => __('Charcoal', 'agency-client-portal'),
                'description' => __('Deep charcoal, violet highlights, muted mood.', 'agency-client-portal'),
                'accent'      => '#8B7CF6',
                'bg'          => '#14121C',
                'card_bg'     => '#1C1926',
                'border'      => '#2A2335',
                'radius'      => 16,
                'dark'        => true,
                'options'     => array(
                    'agency_ui_font'            => 'poppins',
                    'agency_card_border_radius' => 16,
                    'agency_card_border_width'  => 1,
                    'agency_card_border_color'  => '#2A2335',
                    'agency_bg_color_light'     => '#F4F2F8',
                    'agency_bg_color_dark'      => '#14121C',
                    'agency_theme_mode'         => 'dark',
                ),
                // Dégradés "full gradient UI" (fond + cartes + header + sidebar)
                'gradients'   => array(
                    'bg'      => 'linear-gradient(160deg, #2e2257 100%, #1a1341 100%)',
                    'card'    => 'linear-gradient(155deg, #362a5a 100%, #14111C 100%)',
                    'header'  => 'linear-gradient(90deg, #141019 100%, #211B2C 100%)',
                    'sidebar' => 'linear-gradient(195deg, #1C1827 100%, #0B0911 100%)',
                ),
            ),
        );
    }

    /**
     * Applique un préréglage de thème : écrit les options WP correspondantes.
     * Note : ne modifie pas les couleurs client (primary_color / secondary_color).
     */
    public static function apply_preset($preset_id) {
        $presets = self::get_presets();
        if (!isset($presets[$preset_id])) {
            return false;
        }
        $preset = $presets[$preset_id];

        foreach ($preset['options'] as $option_key => $value) {
            if ($option_key === 'agency_theme_mode') {
                if (in_array($value, array('light', 'dark'), true)) {
                    update_option($option_key, $value);
                }
            } elseif (in_array($option_key, array('agency_bg_color_light', 'agency_bg_color_dark', 'agency_card_border_color'), true)) {
                update_option($option_key, sanitize_hex_color($value) ?: $value);
            } elseif (in_array($option_key, array('agency_card_border_radius', 'agency_card_border_width'), true)) {
                update_option($option_key, floatval($value));
            } else {
                update_option($option_key, sanitize_text_field($value));
            }
        }

        // Dégradés (thèmes "full gradient") : stockés à part car la dérivation
        // de palette (texte/bordures) exige une couleur de fond solide.
        if (!empty($preset['gradients']) && is_array($preset['gradients'])) {
            update_option('agency_theme_gradients', wp_json_encode($preset['gradients']));
            update_option('agency_sidebar_bg_color', isset($preset['gradients']['sidebar']) ? $preset['gradients']['sidebar'] : '');
        } else {
            // Thème non dégradé : revenir à une sidebar dérivée du fond sombre.
            delete_option('agency_theme_gradients');
            update_option('agency_sidebar_bg_color', '');
        }

        // Les thèmes ne pilotent PAS l'accent — c'est le client qui choisit
        // sa couleur via primary_color. Le champ "accent" dans get_presets()
        // est conservé uniquement comme swatch d'aperçu dans la galerie admin.
        if (isset($preset['dark'])) {
            update_option('agency_theme_is_dark', $preset['dark'] ? 1 : 0);
        }
        update_option('agency_theme_preset', sanitize_key($preset_id));
        // Nettoyer l'ancienne option (anciens cycles d'install)
        delete_option('agency_theme_accent');
        return true;
    }

    /**
     * Retourne l'identifiant du préréglage actif ('custom' si modifié manuellement).
     */
    public static function get_active_preset() {
        return get_option('agency_theme_preset', 'custom');
    }
}
