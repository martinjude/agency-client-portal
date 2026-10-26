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
})();
