/**
 * Agency UI — utilitaires partagés (sans dépendance, sans bundler).
 *
 * Exposé en global via window.AgencyUI. Chargé avant frontend.js,
 * manager.js, dashboard.js et edit-client.js qui peuvent s'en servir.
 */
(function() {
    'use strict';

    window.AgencyUI = window.AgencyUI || {};

    /**
     * Retourne une version "debounced" de fn : les appels rapprochés sont
     * regroupés, seul le dernier s'exécute après `delay` ms de silence.
     * Indispensable pour les sauvegardes déclenchées au blur/input afin
     * d'éviter les rafales de requêtes (cf. bug voix-off storyboard).
     *
     * @param {Function} fn    Fonction à exécuter.
     * @param {number}   delay Délai en millisecondes.
     * @returns {Function}
     */
    window.AgencyUI.debounce = function(fn, delay) {
        var timer = null;
        return function() {
            var context = this;
            var args = arguments;
            if (timer) {
                clearTimeout(timer);
            }
            timer = setTimeout(function() {
                timer = null;
                fn.apply(context, args);
            }, delay);
        };
    };

    /**
     * Affiche une notification toast (élément unique réutilisé, stylé par
     * .agency-toast dans ui.css). Remplace les alert() et les toasts
     * dupliqués de dashboard.js / edit-client.js.
     *
     * @param {string} msg  Message à afficher.
     * @param {Object} [opts]
     * @param {string} [opts.type]     'success' | 'error' (défaut : neutre).
     * @param {number} [opts.duration] Durée d'affichage en ms (défaut 3200).
     */
    window.AgencyUI.toast = function(msg, opts) {
        opts = opts || {};
        var el = document.getElementById('agency-toast');
        if (!el) {
            el = document.createElement('div');
            el.id = 'agency-toast';
            el.className = 'agency-toast';
            el.setAttribute('role', 'alert');
            el.setAttribute('aria-live', 'polite');
            document.body.appendChild(el);
        }
        el.textContent = msg;
        el.classList.remove('agency-toast--error', 'agency-toast--success');
        if (opts.type === 'error') {
            el.classList.add('agency-toast--error');
        } else if (opts.type === 'success') {
            el.classList.add('agency-toast--success');
        }
        // Relance le minuteur même si un toast est déjà visible
        if (el._agencyTimer) {
            clearTimeout(el._agencyTimer);
        }
        el.classList.add('show');
        el._agencyTimer = setTimeout(function() {
            el.classList.remove('show');
        }, opts.duration || 3200);
    };

    /**
     * Menus déroulants : <div class="agency-dropdown"><button data-agency-dropdown>…</button>
     * <div class="agency-menu">…</div></div>. Un seul menu ouvert à la fois ;
     * fermeture au clic extérieur, sur un élément du menu ou avec Échap.
     */
    function closeDropdowns(except) {
        document.querySelectorAll('.agency-dropdown.is-open').forEach(function(dd) {
            if (dd !== except) {
                dd.classList.remove('is-open');
                var t = dd.querySelector('[data-agency-dropdown]');
                if (t) { t.setAttribute('aria-expanded', 'false'); }
            }
        });
    }
    window.AgencyUI.closeDropdowns = closeDropdowns;

    document.addEventListener('click', function(e) {
        var trigger = e.target.closest('[data-agency-dropdown]');
        if (trigger) {
            e.preventDefault();
            e.stopPropagation();
            var dd = trigger.closest('.agency-dropdown');
            var open = !dd.classList.contains('is-open');
            closeDropdowns(dd);
            dd.classList.toggle('is-open', open);
            trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
            return;
        }
        if (e.target.closest('.agency-dropdown .agency-menu__item')) {
            closeDropdowns();
            return;
        }
        if (!e.target.closest('.agency-dropdown')) {
            closeDropdowns();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') { closeDropdowns(); }
    });

    /**
     * Champ couleur d'accent (templates/partials/accent-field.php) :
     * synchronise pastille, sélecteur natif, saisie hex et couleurs suggérées.
     * Déclenche 'input' sur le champ texte pour les scripts de page.
     */
    window.AgencyUI.setAccent = function(field, hex) {
        if (!field || !/^#[0-9a-fA-F]{6}$/.test(hex)) { return; }
        var prefix = field.dataset.accentField;
        var picker = document.getElementById(prefix + '-picker');
        var text   = document.getElementById(prefix + '-text');
        var swatch = field.querySelector('.agency-accent-field__swatch');
        if (picker) { picker.value = hex.toLowerCase(); }
        if (text && text.value.toLowerCase() !== hex.toLowerCase()) { text.value = hex.toUpperCase(); }
        if (swatch) { swatch.style.background = hex; }
        field.querySelectorAll('.agency-accent-field__preset').forEach(function(b) {
            b.classList.toggle('is-on', b.dataset.color.toLowerCase() === hex.toLowerCase());
        });
    };

    document.addEventListener('input', function(e) {
        var field = e.target.closest && e.target.closest('[data-accent-field]');
        if (!field) { return; }
        window.AgencyUI.setAccent(field, e.target.value.trim());
    });

    document.addEventListener('click', function(e) {
        var preset = e.target.closest && e.target.closest('.agency-accent-field__preset');
        if (!preset) { return; }
        var field = preset.closest('[data-accent-field]');
        window.AgencyUI.setAccent(field, preset.dataset.color);
        var text = document.getElementById(field.dataset.accentField + '-text');
        if (text) { text.dispatchEvent(new Event('input', { bubbles: true })); }
    });

    /**
     * Copie d'une URL : <button class="agency-copy-url" data-url="…">.
     */
    window.AgencyUI.copyText = function(text, message) {
        var done = function() { window.AgencyUI.toast(message || wp.i18n.__('Link copied to clipboard!', 'agency-client-portal')); };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done).catch(function() { window.prompt('', text); });
        } else {
            var tmp = document.createElement('input');
            tmp.value = text;
            document.body.appendChild(tmp);
            tmp.select();
            try { document.execCommand('copy'); done(); } catch (err) { window.prompt('', text); }
            tmp.remove();
        }
    };

    document.addEventListener('click', function(e) {
        var btn = e.target.closest && e.target.closest('.agency-copy-url[data-url]');
        if (!btn) { return; }
        e.preventDefault();
        window.AgencyUI.copyText(btn.dataset.url);
    });

    /**
     * Bascule grille / liste : <div class="agency-seg" data-agency-view-toggle="id-de-la-grille">.
     * Préférence mémorisée par grille (confort uniquement).
     */
    function applyView(seg, view) {
        var grid = document.getElementById(seg.dataset.agencyViewToggle);
        if (!grid) { return; }
        grid.classList.toggle('is-list', view === 'list');
        seg.querySelectorAll('button[data-view]').forEach(function(b) {
            var on = b.dataset.view === view;
            b.classList.toggle('is-on', on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    document.addEventListener('click', function(e) {
        var btn = e.target.closest && e.target.closest('[data-agency-view-toggle] button[data-view]');
        if (!btn) { return; }
        var seg = btn.closest('[data-agency-view-toggle]');
        applyView(seg, btn.dataset.view);
        try { localStorage.setItem('agency_view_' + seg.dataset.agencyViewToggle, btn.dataset.view); } catch (err) {}
    });

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[data-agency-view-toggle]').forEach(function(seg) {
            var view = null;
            try { view = localStorage.getItem('agency_view_' + seg.dataset.agencyViewToggle); } catch (err) {}
            if (view) { applyView(seg, view); }
        });
    });
})();
