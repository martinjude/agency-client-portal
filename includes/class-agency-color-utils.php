<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Utilitaires de manipulation de couleurs hexadécimales.
 * Fournit des méthodes de conversion HEX ↔ HSL / RGB et de dérivation de palette.
 *
 * @since 0.9.11
 */
class Agency_Color_Utils {

    /**
     * Convertit un code hex (#rrggbb ou #rgb) en tableau [h, s, l] (0-360, 0-100, 0-100).
     */
    public static function hex_to_hsl(string $hex): array {
        list($r, $g, $b) = self::hex_to_rgb($hex);
        $r /= 255;
        $g /= 255;
        $b /= 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $h = 0;
        $s = 0;

        if ($max !== $min) {
            $d = $max - $min;
            $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

            switch ($max) {
                case $r:
                    $h = (($g - $b) / $d + ($g < $b ? 6 : 0)) / 6;
                    break;
                case $g:
                    $h = (($b - $r) / $d + 2) / 6;
                    break;
                case $b:
                    $h = (($r - $g) / $d + 4) / 6;
                    break;
            }
        }

        return [round($h * 360, 1), round($s * 100, 1), round($l * 100, 1)];
    }

    /**
     * Convertit HSL (0-360, 0-100, 0-100) en hex #rrggbb.
     */
    public static function hsl_to_hex(float $h, float $s, float $l): string {
        $h /= 360;
        $s /= 100;
        $l /= 100;

        if ($s == 0) {
            $r = $g = $b = $l;
        } else {
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $r = self::hue_to_rgb($p, $q, $h + 1/3);
            $g = self::hue_to_rgb($p, $q, $h);
            $b = self::hue_to_rgb($p, $q, $h - 1/3);
        }

        return sprintf('#%02x%02x%02x',
            (int) round($r * 255),
            (int) round($g * 255),
            (int) round($b * 255)
        );
    }

    /**
     * Helper pour hsl_to_hex.
     */
    private static function hue_to_rgb(float $p, float $q, float $t): float {
        if ($t < 0) $t += 1;
        if ($t > 1) $t -= 1;
        if ($t < 1/6) return $p + ($q - $p) * 6 * $t;
        if ($t < 1/2) return $q;
        if ($t < 2/3) return $p + ($q - $p) * (2/3 - $t) * 6;
        return $p;
    }

    /**
     * Convertit hex en tableau [r, g, b] (0-255).
     */
    public static function hex_to_rgb(string $hex): array {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return [0, 0, 0];
        }
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Retourne true si la couleur est perçue comme sombre (luminance relative WCAG < 0.4).
     */
    public static function is_dark(string $hex): bool {
        list($r, $g, $b) = self::hex_to_rgb($hex);
        // Linéariser les composantes sRGB
        $r = $r / 255;
        $g = $g / 255;
        $b = $b / 255;
        $r = $r <= 0.03928 ? $r / 12.92 : pow(($r + 0.055) / 1.055, 2.4);
        $g = $g <= 0.03928 ? $g / 12.92 : pow(($g + 0.055) / 1.055, 2.4);
        $b = $b <= 0.03928 ? $b / 12.92 : pow(($b + 0.055) / 1.055, 2.4);
        $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
        return $luminance < 0.4;
    }

    /**
     * Éclaircit une couleur hex d'un pourcentage de lightness HSL.
     */
    public static function lighten(string $hex, float $amount): string {
        list($h, $s, $l) = self::hex_to_hsl($hex);
        $l = self::clamp_l($l + $amount);
        return self::hsl_to_hex($h, $s, $l);
    }

    /**
     * Assombrit une couleur hex d'un pourcentage de lightness HSL.
     */
    public static function darken(string $hex, float $amount): string {
        list($h, $s, $l) = self::hex_to_hsl($hex);
        $l = self::clamp_l($l - $amount);
        return self::hsl_to_hex($h, $s, $l);
    }

    /**
     * Clamp lightness entre 0 et 100.
     */
    private static function clamp_l(float $l): float {
        return max(0, min(100, $l));
    }

    /**
     * Retourne un tableau de variables CSS dérivées depuis une couleur de fond.
     *
     * @param string $bg_hex Couleur de fond hex (#rrggbb)
     * @return array<string,string>
     */
    public static function derive_palette(string $bg_hex): array {
        $dark = self::is_dark($bg_hex);
        list($h, $s, $l) = self::hex_to_hsl($bg_hex);
        list($r, $g, $b) = self::hex_to_rgb($bg_hex);

        if ($dark) {
            $panel_l      = min(100, $l + 5);
            $list_l       = $l;
            $border_l     = min(100, $l + 8);
            $hover_l      = min(100, $l + 10);
            $input_l      = $l;
            return array(
                'text_color'           => '#ffffff',
                'text_muted'           => '#9ca3af',
                'border_color'         => self::hsl_to_hex($h, $s, $border_l),
                'shadow'               => '0 2px 8px rgba(0,0,0,0.3)',
                'shadow_hover'         => '0 4px 16px rgba(0,0,0,0.4)',
                'shadow_strong'        => '0 4px 20px rgba(0,0,0,0.35)',
                'header_bg'            => "rgba({$r},{$g},{$b},0.85)",
                'panel_bg'             => self::hsl_to_hex($h, $s, $panel_l),
                'list_bg'              => self::hsl_to_hex($h, $s, $list_l),
                'input_bg'             => self::hsl_to_hex($h, $s, $input_l),
                'btn_secondary_bg'     => $bg_hex,
                'btn_secondary_border' => self::hsl_to_hex($h, $s, $border_l),
                'btn_secondary_hover'  => self::hsl_to_hex($h, $s, $hover_l),
                'title_border'         => 'rgba(255,255,255,0.00)',
                'title_bg'             => 'rgba(255,255,255,0.00)',
            );
        } else {
            $panel_l      = 100;
            $border_l     = max(0, $l - 12);
            $hover_l      = max(0, $l - 6);
            return array(
                'text_color'           => '#333333',
                'text_muted'           => '#6b7280',
                'border_color'         => self::hsl_to_hex($h, max(0, $s - 15), $border_l),
                'shadow'               => '0 2px 8px rgba(0,0,0,0.08)',
                'shadow_hover'         => '0 4px 16px rgba(0,0,0,0.12)',
                'shadow_strong'        => '0 4px 20px rgba(0,0,0,0.10)',
                'header_bg'            => 'rgba(255,255,255,0.85)',
                'panel_bg'             => '#ffffff',
                'list_bg'              => $bg_hex,
                'input_bg'             => $bg_hex,
                'btn_secondary_bg'     => '#ffffff',
                'btn_secondary_border' => self::hsl_to_hex($h, max(0, $s - 15), $border_l),
                'btn_secondary_hover'  => self::hsl_to_hex($h, max(0, $s - 10), $hover_l),
                'title_border'         => 'rgba(0,0,0,0.12)',
                'title_bg'             => 'rgba(0,0,0,0.04)',
            );
        }
    }
}