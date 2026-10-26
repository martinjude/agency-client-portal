/**
 * Agency Emoji Renderer
 *
 * Remplace les emojis Unicode natifs par des SVG modernes servis depuis un CDN.
 *
 * Style par défaut : Twemoji (SVG, MIT, jsdelivr). Twemoji a un mapping
 * codepoint → URL trivial, ce qui le rend fiable côté navigateur. Pour
 * basculer vers une autre source (Microsoft Fluent, Apple-like, etc.),
 * remplacer URL_BASE ci-dessous — la convention de codepoint reste
 * compatible Twemoji (séparateur "-", suppression du sélecteur de
 * variation U+FE0F).
 *
 * Repli : si l'image SVG échoue à charger, on rétablit l'emoji Unicode
 * d'origine via l'attribut `data-emoji-fallback`. Aucune dégradation
 * pour les navigateurs sans JS — le contenu reste lisible en natif.
 */
(function() {
    'use strict';

    var URL_BASE = 'https://cdn.jsdelivr.net/gh/jdecked/twemoji@17.0.2/assets/svg/';

    // Sélecteurs ciblant les éléments porteurs d'un emoji isolé.
    // On évite les contenus mixtes (texte + emoji) pour ne pas casser la mise en page.
    var TARGET_SELECTORS = [
        '.agency-emoji-display',
        '.agency-emoji-option',
        '.link-emoji',
        '.emoji-display',
        '[data-agency-emoji]'
    ].join(',');

    /**
     * Extrait les codepoints d'un emoji Unicode au format Twemoji.
     * Ignore U+FE0F (variation selector), conserve les ZWJ sequences.
     */
    function toCodepoint(unicode) {
        var points = [];
        var i = 0;
        while (i < unicode.length) {
            var cp = unicode.codePointAt(i);
            if (cp === undefined) break;
            // U+FE0F est ignoré par convention Twemoji (sauf en cas d'emoji combiné)
            if (cp !== 0xFE0F) {
                points.push(cp.toString(16));
            }
            i += cp > 0xFFFF ? 2 : 1;
        }
        return points.join('-');
    }

    /**
     * Construit l'URL du SVG pour un emoji donné.
     */
    function urlFor(unicode) {
        var cp = toCodepoint(unicode);
        if (!cp) return null;
        return URL_BASE + cp + '.svg';
    }

    function escapeAttr(s) {
        return String(s).replace(/[&<>"']/g, function(c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    /**
     * Retourne un fragment HTML <img> représentant l'emoji.
     * Si le rendu échoue (réseau, codepoint inconnu), l'attribut onerror
     * restitue l'emoji Unicode natif.
     */
    function html(unicode) {
        var url = urlFor(unicode);
        if (!url) return escapeAttr(unicode);
        var alt = escapeAttr(unicode);
        return '<img class="agency-emoji-img" src="' + url + '" alt="' + alt + '" draggable="false" '
            + 'onerror="this.outerHTML=this.getAttribute(\'data-emoji-fallback\')" '
            + 'data-emoji-fallback="' + alt + '">';
    }

    /**
     * Remplace le contenu textuel des éléments cibles par un <img> emoji.
     * Ignore les éléments déjà traités.
     */
    function replaceAll(scope) {
        scope = scope || document;
        var els = scope.querySelectorAll(TARGET_SELECTORS);
        for (var i = 0; i < els.length; i++) {
            var el = els[i];
            // Éviter le double-traitement
            if (el.querySelector('img.agency-emoji-img')) continue;
            // Prendre l'emoji depuis data-emoji si présent, sinon textContent
            var emoji = el.dataset.emoji || (el.textContent || '').trim();
            if (!emoji) continue;
            // N'agir que si le contenu commence par un emoji valide
            if (!urlFor(emoji)) continue;
            el.innerHTML = html(emoji);
        }
    }

    /**
     * Observe les ajouts ultérieurs dans le DOM (modals, ajax) et applique
     * le rendu aux nouveaux éléments.
     */
    function observe() {
        if (typeof MutationObserver === 'undefined') return;
        var obs = new MutationObserver(function(mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var added = mutations[i].addedNodes;
                for (var j = 0; j < added.length; j++) {
                    var node = added[j];
                    if (node.nodeType !== 1) continue;
                    replaceAll(node);
                    // Sous-arbre potentiellement porteur
                    if (node.querySelectorAll) replaceAll(node);
                }
            }
        });
        obs.observe(document.body, { childList: true, subtree: true });
    }

    window.AgencyEmoji = {
        html: html,
        url: urlFor,
        codepoint: toCodepoint,
        replaceAll: replaceAll
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            replaceAll(document);
            observe();
        });
    } else {
        replaceAll(document);
        observe();
    }
})();
