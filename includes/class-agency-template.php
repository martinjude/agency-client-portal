<?php
/**
 * Petites aides d'affichage partagées par les templates (design system).
 *
 * @package Agency
 */

if (!defined('ABSPATH')) {
    exit;
}

class Agency_Template {

    /**
     * Durée relative (« il y a 2 h ») depuis une date MySQL.
     * Les dates sont stockées à l'heure du site (NOW() / current_time('mysql')).
     *
     * @param string $datetime
     * @return string
     */
    public static function time_ago($datetime) {
        $ts = $datetime ? strtotime($datetime) : false;
        if (!$ts) {
            return '';
        }
        /* translators: %s: human-readable time difference, e.g. "2 hours" */
        return sprintf(__('%s ago', 'agency-client-portal'), human_time_diff($ts, strtotime(current_time('mysql'))));
    }

    /**
     * Initiale d'un nom (avatars).
     *
     * @param string $name
     * @return string
     */
    public static function initial($name) {
        $name = trim((string) $name);
        if ($name === '') {
            return '?';
        }
        return function_exists('mb_substr') ? mb_strtoupper(mb_substr($name, 0, 1)) : strtoupper(substr($name, 0, 1));
    }

    /**
     * Couleur client sûre pour un attribut style.
     *
     * @param object|null $client
     * @return string
     */
    public static function client_color($client) {
        $color = $client && !empty($client->primary_color) ? sanitize_hex_color($client->primary_color) : '';
        return $color ? $color : '#7C83FD';
    }

    /**
     * Fil d'Ariane de page : liste de [libellé, url] (url vide = élément courant).
     *
     * @param array $items
     */
    public static function crumbs(array $items) {
        if (empty($items)) {
            return;
        }
        echo '<nav class="agency-crumbs" aria-label="' . esc_attr__('Breadcrumb', 'agency-client-portal') . '">';
        $last = count($items) - 1;
        foreach ($items as $i => $item) {
            if ($i > 0) {
                Agency_Icons::render_ui('chevron-right', 14);
            }
            if ($i === $last || empty($item[1])) {
                echo '<span class="agency-crumbs__cur">' . esc_html($item[0]) . '</span>';
            } else {
                echo '<a href="' . esc_url($item[1]) . '">' . esc_html($item[0]) . '</a>';
            }
        }
        echo '</nav>';
    }
}
