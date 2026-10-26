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
     * Initiales d'un nom (2 lettres max, avatars des commentaires).
     *
     * @param string $name
     * @return string
     */
    public static function initials($name) {
        $parts = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);
        if (empty($parts)) {
            return '?';
        }
        $out = self::initial($parts[0]);
        if (count($parts) > 1) {
            $out .= self::initial(end($parts));
        }
        return $out;
    }

    /**
     * Tuile de type de fichier (extension en mono, teintée par famille).
     *
     * @param string $filename Nom de fichier.
     * @return string HTML échappé.
     */
    public static function file_tile($filename) {
        $ext = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));
        $groups = array(
            'zip'   => array('zip', 'rar', '7z', 'tar', 'gz'),
            'pdf'   => array('pdf'),
            'img'   => array('png', 'jpg', 'jpeg', 'gif', 'webp'),
            'vec'   => array('svg', 'ai', 'eps'),
            'video' => array('mp4', 'mov', 'avi', 'mkv', 'webm'),
            'audio' => array('mp3', 'wav', 'aac', 'flac'),
        );
        $family = 'other';
        foreach ($groups as $name => $exts) {
            if (in_array($ext, $exts, true)) {
                $family = $name;
                break;
            }
        }
        $label = $ext !== '' ? strtoupper(substr($ext, 0, 4)) : '—';
        return '<span class="agency-ftile agency-ftile--' . esc_attr($family) . '">' . esc_html($label) . '</span>';
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
