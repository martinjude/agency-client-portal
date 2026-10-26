<?php
/**
 * Icônes SVG inline pour l'interface Agency
 *
 * Remplace les emojis d'UI par des icônes SVG légères (style Lucide).
 * Les icônes utilisent stroke="currentColor" pour hériter la couleur CSS.
 *
 * @package Agency
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Agency_Icons {

    /**
     * Retourne le markup SVG d'une icône.
     *
     * @param string $name  Nom de l'icône.
     * @param int    $size  Taille en pixels (défaut 18).
     * @param string $class Classe CSS additionnelle.
     * @return string       Markup SVG ou chaîne vide si l'icône n'existe pas.
     */
    public static function get( $name, $size = 18, $class = '' ) {
        $icons = self::icons();
        if ( ! isset( $icons[ $name ] ) ) {
            return '';
        }
        $css_class = 'agency-icon agency-icon--' . esc_attr( $name );
        if ( $class ) {
            $css_class .= ' ' . esc_attr( $class );
        }
        return sprintf(
            '<svg class="%s" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>',
            $css_class,
            $size,
            $size,
            $icons[ $name ]
        );
    }

    /**
     * Dictionnaire des icônes (paths SVG Lucide).
     *
     * @return array<string, string>
     */
    /**
     * Balises et attributs SVG autorisés pour wp_kses() lors de l'affichage des icônes.
     *
     * @return array
     */
    public static function allowed_html() {
        $shape = array(
            'd' => true, 'points' => true, 'cx' => true, 'cy' => true, 'r' => true,
            'x' => true, 'y' => true, 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true,
            'rx' => true, 'ry' => true, 'width' => true, 'height' => true,
            'fill' => true, 'stroke' => true, 'stroke-width' => true,
            'stroke-linecap' => true, 'stroke-linejoin' => true, 'opacity' => true, 'fill-rule' => true,
        );
        return array(
            'svg'      => array(
                'class' => true, 'width' => true, 'height' => true, 'viewbox' => true,
                'fill' => true, 'stroke' => true, 'stroke-width' => true,
                'stroke-linecap' => true, 'stroke-linejoin' => true, 'aria-hidden' => true,
                'xmlns' => true,
            ),
            'path'     => $shape,
            'circle'   => $shape,
            'line'     => $shape,
            'polyline' => $shape,
            'polygon'  => $shape,
            'rect'     => $shape,
        );
    }

    /**
     * Affiche une icône SVG échappée.
     *
     * @param string $name  Nom de l'icône.
     * @param int    $size  Taille en pixels (défaut 18).
     * @param string $class Classe CSS additionnelle.
     */
    public static function render( $name, $size = 18, $class = '' ) {
        echo wp_kses( self::get( $name, $size, $class ), self::allowed_html() );
    }

    private static function icons() {
        return array(
            'image'      => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>',
            'x'          => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
            'minus'      => '<line x1="5" y1="12" x2="19" y2="12"/>',
            'plus'       => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
            'save'       => '<path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>',
            'download'   => '<path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
            'upload'     => '<path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
            'link'       => '<path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/>',
            'trash'      => '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>',
            'edit'       => '<path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>',
            'paperclip'  => '<path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/>',
            'lock'       => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>',
            'arrow-down' => '<line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/>',
            'file'       => '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>',
            'video'      => '<polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>',
            'mail'       => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 6L2 7"/>',
            'grip'       => '<circle cx="9" cy="5" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="9" cy="19" r="1"/><circle cx="15" cy="5" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="19" r="1"/>',
            'users'      => '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>',
            'key'        => '<path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 11-7.778 7.778 5.5 5.5 0 017.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>',
            'folder'     => '<path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/>',
            'film'       => '<rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"/><line x1="7" y1="2" x2="7" y2="22"/><line x1="17" y1="2" x2="17" y2="22"/><line x1="2" y1="12" x2="22" y2="12"/><line x1="2" y1="7" x2="7" y2="7"/><line x1="2" y1="17" x2="7" y2="17"/><line x1="17" y1="17" x2="22" y2="17"/><line x1="17" y1="7" x2="22" y2="7"/>',
            'alert'      => '<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
            'check'      => '<polyline points="20 6 9 17 4 12"/>',
            'comment'    => '<path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/>',
            'info'       => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
            'sparkles'   => '<path d="M12 3l1.9 5.8a2 2 0 001.3 1.3L21 12l-5.8 1.9a2 2 0 00-1.3 1.3L12 21l-1.9-5.8a2 2 0 00-1.3-1.3L3 12l5.8-1.9a2 2 0 001.3-1.3z"/>',
            'bar-chart'  => '<line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/>',
            'copy'       => '<rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/>',
            'clock'      => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
            'shield'     => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        );
    }

    /**
     * Icône utilitaire du design system (traits 1,6 px, viewBox 20).
     *
     * @param string $name
     * @param int    $size
     * @param string $class
     * @return string
     */
    public static function get_ui( $name, $size = 16, $class = '' ) {
        $icons = self::ui_icons();
        if ( ! isset( $icons[ $name ] ) ) {
            return '';
        }
        $css_class = 'agency-icon agency-icon--' . esc_attr( $name ) . ( $class ? ' ' . esc_attr( $class ) : '' );
        return sprintf(
            '<svg class="%s" width="%d" height="%d" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>',
            $css_class,
            $size,
            $size,
            $icons[ $name ]
        );
    }

    /**
     * Affiche une icône utilitaire échappée.
     */
    public static function render_ui( $name, $size = 16, $class = '' ) {
        echo wp_kses( self::get_ui( $name, $size, $class ), self::allowed_html() );
    }

    /**
     * Glyphe bicolore du design system (navigation, types de blocs), viewBox 20.
     * Les deux tons viennent des tokens --ag-icon-a / --ag-icon-b.
     *
     * @param string $name Nom du glyphe ou type de bloc.
     * @param int    $size
     * @param string $class
     * @return string
     */
    public static function get_glyph( $name, $size = 18, $class = '' ) {
        $glyphs  = self::glyphs();
        $aliases = self::GLYPH_ALIASES;
        if ( ! isset( $glyphs[ $name ] ) && isset( $aliases[ $name ] ) ) {
            $name = $aliases[ $name ];
        }
        if ( ! isset( $glyphs[ $name ] ) ) {
            $name = 'block';
        }
        $css_class = 'agency-glyph agency-glyph--' . esc_attr( $name ) . ( $class ? ' ' . esc_attr( $class ) : '' );
        return sprintf(
            '<svg class="%s" width="%d" height="%d" viewBox="0 0 20 20" fill="none" aria-hidden="true">%s</svg>',
            $css_class,
            $size,
            $size,
            $glyphs[ $name ]
        );
    }

    /**
     * Affiche un glyphe bicolore échappé.
     */
    public static function render_glyph( $name, $size = 18, $class = '' ) {
        echo wp_kses( self::get_glyph( $name, $size, $class ), self::allowed_html() );
    }

    /**
     * Anciens types de blocs → glyphe le plus proche.
     */
    const GLYPH_ALIASES = array( 'video_youtube' => 'video', 'video_dropbox' => 'video', 'voiceover' => 'video', 'pdf' => 'script', 'text' => 'script', 'image_gallery' => 'versioned_media', 'embed' => 'code' );

    private static function ui_icons() {
        return array(
            'search'        => '<circle cx="9" cy="9" r="5.5"/><path d="M13.2 13.2L17 17"/>',
            'plus'          => '<path d="M10 4.5v11M4.5 10h11"/>',
            'x'             => '<path d="M5.5 5.5l9 9M14.5 5.5l-9 9"/>',
            'check'         => '<path d="M4.5 10.5l3.5 3.5 7.5-8"/>',
            'chevron-down'  => '<path d="M6 8l4 4 4-4"/>',
            'chevron-right' => '<path d="M8 6l4 4-4 4"/>',
            'chevron-left'  => '<path d="M12 6l-4 4 4 4"/>',
            'plus-square'   => '<rect x="2.75" y="2.75" width="14.5" height="14.5" rx="3.5"/><path d="M10 6.5v7M6.5 10h7"/>',
            'sidebar'       => '<rect x="2.75" y="3.75" width="14.5" height="12.5" rx="3"/><path d="M8 4v12"/>',
            'more'          => '<circle cx="4.5" cy="10" r="1" /><circle cx="10" cy="10" r="1"/><circle cx="15.5" cy="10" r="1"/>',
            'more-v'        => '<circle cx="10" cy="4.5" r="1"/><circle cx="10" cy="10" r="1"/><circle cx="10" cy="15.5" r="1"/>',
            'filter'        => '<path d="M3 4.5h14l-5.5 6.5v5l-3-1.5V11z"/>',
            'grid'          => '<rect x="3" y="3" width="6" height="6" rx="1.5"/><rect x="11" y="3" width="6" height="6" rx="1.5"/><rect x="3" y="11" width="6" height="6" rx="1.5"/><rect x="11" y="11" width="6" height="6" rx="1.5"/>',
            'list'          => '<path d="M7 5h10M7 10h10M7 15h10M3.5 5h.01M3.5 10h.01M3.5 15h.01"/>',
            'external'      => '<path d="M11 3h6v6M17 3l-8 8"/><path d="M15 12v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h3"/>',
            'menu'          => '<path d="M3 5h14M3 10h14M3 15h14"/>',
            'bubble'        => '<path d="M10 3c4.1 0 7 2.6 7 6s-2.9 6-7 6c-.9 0-1.8-.1-2.6-.4L4 16l.9-3C3.7 11.9 3 10.5 3 9c0-3.4 2.9-6 7-6z"/>',
            'play'          => '<path d="M7 5.5v9l7-4.5z" fill="currentColor"/>',
            'pencil'        => '<path d="M12.5 4.5l3 3L7 16H4v-3z"/>',
            'reply'         => '<path d="M8 6L4 10l4 4"/><path d="M4 10h8a4 4 0 0 1 4 4v1"/>',
        );
    }

    private static function glyphs() {
        return array(
            'dashboard'       => '<rect x="2" y="2" width="7" height="7" rx="2" fill="var(--ag-icon-b)"/><rect x="11" y="2" width="7" height="7" rx="3.5" fill="var(--ag-icon-a)"/><rect x="2" y="11" width="7" height="7" rx="2" fill="var(--ag-icon-a)"/><rect x="11" y="11" width="7" height="7" rx="2" fill="var(--ag-icon-a)"/>',
            'comments'        => '<path d="M10 2.5c4.4 0 8 3 8 6.8s-3.6 6.8-8 6.8c-1 0-2-.2-2.9-.5L3 17l1-3.6C2.8 12.2 2 10.8 2 9.3 2 5.5 5.6 2.5 10 2.5z" fill="var(--ag-icon-a)"/><circle cx="6.8" cy="9.4" r="1.2" fill="var(--ag-icon-b)"/><circle cx="10" cy="9.4" r="1.2" fill="var(--ag-icon-b)"/><circle cx="13.2" cy="9.4" r="1.2" fill="var(--ag-icon-b)"/>',
            'inbox'           => '<path d="M5 3h10l2 8h-4l-1.5 2h-3L7 11H3z" fill="var(--ag-icon-a)"/><path d="M3 11h4l1.5 2h3L13 11h4v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" fill="var(--ag-icon-b)"/>',
            'folder'          => '<path d="M2 5a2 2 0 0 1 2-2h3.6l2 2H16a2 2 0 0 1 2 2v1H2z" fill="var(--ag-icon-b)"/><rect x="2" y="7" width="16" height="10" rx="2" fill="var(--ag-icon-a)"/>',
            'video'           => '<rect x="2" y="4" width="12" height="12" rx="2.5" fill="var(--ag-icon-a)"/><path d="M14 8.5l4-2.5v8l-4-2.5z" fill="var(--ag-icon-b)"/><path d="M6.5 7.5v5l4-2.5z" fill="var(--ag-icon-b)"/>',
            'storyboard'      => '<rect x="2" y="4" width="16" height="12" rx="2" fill="var(--ag-icon-a)"/><rect x="4.5" y="7" width="5" height="6" rx="1" fill="var(--ag-icon-b)"/><rect x="10.5" y="7" width="5" height="6" rx="1" fill="var(--ag-icon-b)"/>',
            'versioned_media' => '<path d="M10 2l8 4-8 4-8-4z" fill="var(--ag-icon-b)"/><path d="M2 10l8 4 8-4v2.5l-8 4-8-4z" fill="var(--ag-icon-a)"/>',
            'script'          => '<path d="M5 2h7l4 4v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z" fill="var(--ag-icon-a)"/><path d="M12 2l4 4h-3a1 1 0 0 1-1-1z" fill="var(--ag-icon-b)"/><rect x="6" y="9" width="7" height="1.6" rx=".8" fill="var(--ag-icon-b)"/><rect x="6" y="12.5" width="5" height="1.6" rx=".8" fill="var(--ag-icon-b)"/>',
            'link'            => '<path d="M8.5 11.5l3-3" stroke="var(--ag-icon-b)" stroke-width="1.8" stroke-linecap="round"/><path d="M9 5.5l1.5-1.5a3.5 3.5 0 0 1 5 5L14 10.5M11 14.5L9.5 16a3.5 3.5 0 0 1-5-5L6 9.5" stroke="var(--ag-icon-a)" stroke-width="2.2" fill="none" stroke-linecap="round"/>',
            'file_deposit'    => '<path d="M3 12h4l1.5 2h3L13 12h4v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" fill="var(--ag-icon-a)"/><path d="M10 2l4 4h-2.5v5h-3V6H6z" fill="var(--ag-icon-b)"/>',
            'code'            => '<rect x="2" y="3" width="16" height="14" rx="2.5" fill="var(--ag-icon-a)"/><path d="M8 7.5L5.5 10 8 12.5M12 7.5l2.5 2.5-2.5 2.5" stroke="var(--ag-icon-b)" stroke-width="1.6" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
            'progress_bar'    => '<rect x="2" y="8" width="16" height="4" rx="2" fill="var(--ag-icon-a)"/><rect x="2" y="8" width="9" height="4" rx="2" fill="var(--ag-icon-b)"/>',
            'separator'       => '<rect x="2" y="9.2" width="16" height="1.6" rx=".8" fill="var(--ag-icon-a)"/><rect x="2" y="4" width="10" height="2" rx="1" fill="var(--ag-icon-b)"/><rect x="2" y="14" width="10" height="2" rx="1" fill="var(--ag-icon-b)"/>',
            'settings'        => '<circle cx="10" cy="10" r="7.5" fill="var(--ag-icon-a)"/><circle cx="10" cy="10" r="3" fill="var(--ag-icon-b)"/>',
            'wordpress'       => '<rect x="3" y="3" width="14" height="14" rx="3" fill="var(--ag-icon-a)"/><path d="M7 10h6M10 7v6" stroke="var(--ag-icon-b)" stroke-width="1.6" stroke-linecap="round"/>',
            'logout'          => '<path d="M3 4a2 2 0 0 1 2-2h5v16H5a2 2 0 0 1-2-2z" fill="var(--ag-icon-a)"/><path d="M12.5 6.5L16 10l-3.5 3.5M16 10H8" stroke="var(--ag-danger)" stroke-width="1.6" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
            'lock'            => '<rect x="3.5" y="8.5" width="13" height="9.5" rx="2.5" fill="var(--ag-icon-a)"/><path d="M6.5 8.5V6.5a3.5 3.5 0 0 1 7 0v2" stroke="var(--ag-icon-b)" stroke-width="1.8" fill="none"/><circle cx="10" cy="13" r="1.5" fill="var(--ag-icon-b)"/>',
            'pen'             => '<path d="M12.5 3.5l4 4L8 16H4v-4z" fill="var(--ag-icon-a)"/><path d="M12.5 3.5l1.6-1.6a1.4 1.4 0 0 1 2 0l2 2a1.4 1.4 0 0 1 0 2L16.5 7.5z" fill="var(--ag-icon-b)"/>',
            'leaf'            => '<path d="M15.5 2.5c-5.5 1-9 5-10.5 12l-1.5 3 3-1.5c6.5-1.5 10.5-5 9-13.5z" fill="var(--ag-panel)"/><path d="M5 14.5l5.5-6" stroke="var(--ag-text-1)" stroke-width="1.4" stroke-linecap="round"/>',
            'block'           => '<rect x="2" y="2" width="16" height="16" rx="3" fill="var(--ag-icon-a)"/><rect x="5" y="5" width="10" height="4" rx="1" fill="var(--ag-icon-b)"/>',
        );
    }
}
