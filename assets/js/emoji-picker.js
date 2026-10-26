/**
 * Agency Emoji Picker Component
 * JavaScript pour le sélecteur d'emoji
 */

(function() {
    'use strict';

    var __ = wp.i18n.__;
    
    // Mapping mots-clés français → emojis pour la recherche
    const EMOJI_MAP = {
        // Cœurs / Hearts
        'coeur': ['❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💔', '❣️', '💕', '💞', '💓', '💗', '💖', '💘', '💝'],
        'heart': ['❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💔', '❣️', '💕', '💞', '💓', '💗', '💖', '💘', '💝'],
        'amour': ['🥰', '😍', '😘', '😗', '😚', '😙', '❤️', '💕', '💞', '💓', '💗', '💖', '💘', '💝'],
        'love': ['🥰', '😍', '😘', '😗', '😚', '😙', '❤️', '💕', '💞', '💓', '💗', '💖', '💘', '💝'],

        // Visages / Faces
        'sourire': ['😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂', '🙂', '🙃', '😉', '😊'],
        'smile': ['😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂', '🙂', '🙃', '😉', '😊'],
        'rire': ['😄', '😆', '😅', '🤣', '😂'],
        'heureux': ['😀', '😃', '😄', '😁', '😆', '😊', '🥰', '😍', '🤩', '🥳'],
        'happy': ['😀', '😃', '😄', '😁', '😆', '😊', '🥰', '😍', '🤩', '🥳'],
        'triste': ['😢', '😭', '😔', '😞', '😟', '🙁', '☹️', '😖', '😣', '😩', '😫', '🥺'],
        'sad': ['😢', '😭', '😔', '😞', '😟', '🙁', '☹️', '😖', '😣', '😩', '😫', '🥺'],
        'colere': ['😡', '😠', '🤬', '😤'],
        'peur': ['😨', '😰', '😱', '😥', '😧'],
        'cool': ['😎', '🤩', '🥳', '🤠'],
        'malade': ['😷', '🤒', '🤕', '🤢', '🤮', '🤧', '🥵', '🥶'],
        'mort': ['💀', '☠️', '👻'],
        'robot': ['🤖'],
        'alien': ['👽', '👾'],
        'clown': ['🤡'],
        'diable': ['😈', '👿'],

        // Éléments / Elements
        'feu': ['🔥', '💥', '⚡', '✨'],
        'fire': ['🔥', '💥', '⚡', '✨'],
        'etoile': ['⭐', '🌟', '✨', '💫'],
        'star': ['⭐', '🌟', '✨', '💫'],
        'eclair': ['⚡', '🌩️'],
        'eau': ['💧', '💦', '🌊', '🚿'],
        'arc-en-ciel': ['🌈'],

        // Validation
        'check': ['✅', '✔️', '☑️'],
        'valider': ['✅', '✔️', '☑️'],
        'ok': ['✅', '✔️', '☑️', '👌', '👍'],
        'erreur': ['❌', '❎', '⛔', '🚫'],
        'attention': ['⚠️', '❗', '❕', '🚸'],
        'question': ['❓', '❔', '⁉️'],
        'interdit': ['🚫', '⛔', '🛑', '🔞'],
        'stop': ['🛑', '⛔', '🚫'],

        // Musique / Music
        'musique': ['🎵', '🎶', '🎤', '🎧', '🎼', '🎹', '🥁', '🎷', '🎺', '🎸', '🎻'],
        'music': ['🎵', '🎶', '🎤', '🎧', '🎼', '🎹', '🥁', '🎷', '🎺', '🎸', '🎻'],
        'guitare': ['🎸'],
        'piano': ['🎹'],
        'micro': ['🎤', '🎙️'],

        // Nourriture / Food
        'nourriture': ['🍏', '🍎', '🍐', '🍊', '🍋', '🍌', '🍉', '🍇', '🍓', '🍔', '🍕', '🍟', '🌭', '🍿'],
        'food': ['🍏', '🍎', '🍐', '🍊', '🍋', '🍌', '🍉', '🍇', '🍓', '🍔', '🍕', '🍟', '🌭', '🍿'],
        'fruit': ['🍏', '🍎', '🍐', '🍊', '🍋', '🍌', '🍉', '🍇', '🍓', '🍑', '🥭', '🍍', '🥥', '🥝'],
        'legume': ['🥦', '🥬', '🥒', '🌶️', '🌽', '🥕', '🥔', '🍆', '🍅', '🥑'],
        'pomme': ['🍏', '🍎'],
        'pizza': ['🍕'],
        'burger': ['🍔'],
        'cafe': ['☕', '🍵'],
        'the': ['🍵', '🫖'],
        'biere': ['🍺', '🍻'],
        'vin': ['🍷', '🥂', '🍾'],
        'cocktail': ['🍸', '🍹'],
        'gateau': ['🎂', '🧁', '🍰'],
        'chocolat': ['🍫', '🍩'],
        'bonbon': ['🍬', '🍭'],
        'glace': ['🍦', '🍧', '🍨'],
        'sushi': ['🍣', '🍱'],
        'pain': ['🍞', '🥖', '🥐', '🥯'],
        'fromage': ['🧀'],
        'oeuf': ['🥚', '🍳'],

        // Animaux / Animals
        'animaux': ['🐶', '🐱', '🐭', '🐹', '🐰', '🦊', '🐻', '🐼', '🐨', '🐯', '🦁', '🐮', '🐷', '🐸'],
        'animal': ['🐶', '🐱', '🐭', '🐹', '🐰', '🦊', '🐻', '🐼', '🐨', '🐯', '🦁', '🐮', '🐷', '🐸'],
        'chien': ['🐶', '🐕', '🦮', '🐕‍🦺', '🐩'],
        'chat': ['🐱', '🐈', '🐈‍⬛'],
        'oiseau': ['🐦', '🐧', '🦅', '🦉', '🦆', '🦚', '🦜', '🦢', '🦩', '🕊️'],
        'poisson': ['🐟', '🐠', '🐡', '🦈'],
        'papillon': ['🦋'],
        'serpent': ['🐍'],
        'tortue': ['🐢'],
        'singe': ['🐵', '🙈', '🙉', '🙊', '🐒'],
        'lion': ['🦁'],
        'ours': ['🐻', '🐼', '🐨'],
        'cheval': ['🐴', '🐎', '🦄', '🏇'],
        'licorne': ['🦄'],
        'dragon': ['🐉', '🐲'],
        'dinosaure': ['🦖', '🦕'],
        'baleine': ['🐳', '🐋'],
        'dauphin': ['🐬'],
        'requin': ['🦈'],
        'elephant': ['🐘'],
        'girafe': ['🦒'],
        'cochon': ['🐷', '🐖', '🐽'],
        'vache': ['🐮', '🐄', '🐂'],
        'lapin': ['🐰', '🐇'],
        'renard': ['🦊'],
        'loup': ['🐺'],
        'souris': ['🐭', '🐁'],
        'grenouille': ['🐸'],
        'abeille': ['🐝'],
        'fourmi': ['🐜'],

        // Sport
        'sport': ['⚽', '🏀', '🏈', '⚾', '🎾', '🏐', '🏉', '🎱', '🏓', '🏸', '🏒', '🏑', '🥅'],
        'football': ['⚽'],
        'basket': ['🏀'],
        'tennis': ['🎾'],
        'golf': ['⛳', '🏌️'],
        'ski': ['🎿', '⛷️', '🏂'],
        'natation': ['🏊', '🤽'],
        'velo': ['🚴', '🚵', '🚲'],
        'boxe': ['🥊', '🥋'],
        'yoga': ['🧘'],
        'surf': ['🏄'],
        'trophee': ['🏆', '🥇', '🥈', '🥉', '🏅'],
        'medaille': ['🥇', '🥈', '🥉', '🏅'],

        // Voyage / Travel
        'voyage': ['🚗', '🚕', '🚙', '🚌', '🚎', '🏎️', '🚓', '🚑', '🚒', '✈️', '🚀', '🚁', '🛸'],
        'travel': ['🚗', '🚕', '🚙', '🚌', '🚎', '🏎️', '🚓', '🚑', '🚒', '✈️', '🚀', '🚁', '🛸'],
        'voiture': ['🚗', '🚕', '🚙', '🏎️'],
        'avion': ['✈️', '🛫', '🛬', '🛩️'],
        'fusee': ['🚀'],
        'bateau': ['🛥️', '⛵', '🚤', '🛳️', '🚢'],
        'train': ['🚂', '🚄', '🚅', '🚆', '🚇', '🚈', '🚃', '🚋'],
        'bus': ['🚌', '🚎', '🚍'],
        'taxi': ['🚕', '🚖'],
        'camion': ['🚚', '🚛', '🛻'],
        'moto': ['🏍️', '🛵'],
        'helicoptere': ['🚁'],
        'maison': ['🏠', '🏡', '🏘️'],
        'immeuble': ['🏢', '🏬', '🏗️'],
        'hopital': ['🏥'],
        'ecole': ['🏫'],
        'eglise': ['⛪'],
        'chateau': ['🏰', '🏯'],
        'camping': ['🏕️', '⛺'],
        'plage': ['🏖️', '⛱️'],
        'montagne': ['⛰️', '🏔️', '🗻'],
        'ile': ['🏝️'],
        'volcan': ['🌋'],

        // Travail / Work
        'travail': ['💼', '💻', '⌨️', '🖥️', '🖨️', '📱', '📞', '☎️', '📧', '📨', '✉️'],
        'work': ['💼', '💻', '⌨️', '🖥️', '🖨️', '📱', '📞', '☎️', '📧', '📨', '✉️'],
        'bureau': ['💼', '🖥️', '🏢', '📋', '📁'],
        'ordinateur': ['💻', '🖥️', '⌨️'],
        'telephone': ['📱', '📞', '☎️'],
        'mail': ['📧', '📨', '✉️', '📬', '📭', '📮'],
        'dossier': ['📁', '📂', '🗂️'],
        'document': ['📄', '📃', '📑', '📜'],
        'livre': ['📖', '📚', '📕', '📗', '📘', '📙'],
        'crayon': ['✏️', '🖊️', '🖋️', '✒️'],
        'stylo': ['🖊️', '🖋️', '✒️'],
        'peinture': ['🎨', '🖌️', '🖍️'],
        'ciseaux': ['✂️'],
        'trombone': ['📎', '🖇️'],
        'punaise': ['📌', '📍'],
        'loupe': ['🔍', '🔎'],
        'calendrier': ['📅', '📆', '🗓️'],
        'horloge': ['⏰', '🕰️', '⏱️', '⏲️', '⌛', '⏳'],

        // Tech
        'camera': ['📷', '📸', '📹', '🎥'],
        'photo': ['📷', '📸', '🖼️'],
        'video': ['📹', '🎥', '📽️', '🎬'],
        'cinema': ['🎬', '🎥', '📽️', '🎞️'],
        'television': ['📺'],
        'radio': ['📻'],
        'batterie': ['🔋'],
        'ampoule': ['💡'],
        'lampe': ['💡', '🔦'],
        'cle': ['🔑', '🗝️'],
        'serrure': ['🔒', '🔓', '🔐'],
        'engrenage': ['⚙️'],
        'outil': ['🔧', '🔨', '🛠️', '🧰', '🪛'],
        'marteau': ['🔨', '⚒️'],
        'bouclier': ['🛡️'],

        // Médical / Medical
        'medicament': ['💊', '💉', '🩹', '🩺'],
        'medecin': ['👨‍⚕️', '👩‍⚕️', '🩺'],
        'sante': ['💊', '💉', '🩹', '🩺', '🏥'],
        'science': ['🔬', '🔭', '🧬', '🧪', '🧫', '⚗️'],

        // Argent / Money
        'argent': ['💰', '💵', '💶', '💷', '💴', '💸', '💳', '🪙'],
        'money': ['💰', '💵', '💶', '💷', '💴', '💸', '💳', '🪙'],
        'euro': ['💶'],
        'dollar': ['💵', '💲'],
        'diamant': ['💎'],

        // Célébration / Celebration
        'fete': ['🎉', '🎊', '🎈', '🎁', '🎂', '🎀', '🪅'],
        'celebration': ['🎉', '🎊', '🎈', '🎁', '🎂', '🎀', '🪅'],
        'cadeau': ['🎁'],
        'anniversaire': ['🎂', '🎉', '🎈', '🎁'],
        'noel': ['🎄', '🎅', '🤶', '🎁', '❄️'],
        'halloween': ['🎃', '👻', '💀', '🧛', '🧟', '🧙'],

        // Météo / Weather
        'meteo': ['☀️', '🌤️', '⛅', '🌥️', '☁️', '🌧️', '⛈️', '🌩️', '🌨️', '❄️', '⛄', '🌈'],
        'weather': ['☀️', '🌤️', '⛅', '🌥️', '☁️', '🌧️', '⛈️', '🌩️', '🌨️', '❄️', '⛄', '🌈'],
        'soleil': ['☀️', '🌤️', '🌞'],
        'pluie': ['🌧️', '⛈️', '🌦️', '☔'],
        'neige': ['❄️', '⛄', '☃️', '🌨️'],
        'nuage': ['☁️', '⛅', '🌥️'],
        'lune': ['🌙', '🌕', '🌖', '🌗', '🌘', '🌑', '🌒', '🌓', '🌔', '🌚', '🌛', '🌜', '🌝'],
        'terre': ['🌎', '🌍', '🌏', '🌐'],
        'planete': ['🪐', '🌎', '🌍', '🌏'],

        // Nature
        'nature': ['🌲', '🌳', '🌴', '🌱', '🌿', '☘️', '🍀', '🌷', '🌹', '🌺', '🌸', '🌼', '🌻'],
        'arbre': ['🌲', '🌳', '🌴', '🌱'],
        'tree': ['🌲', '🌳', '🌴'],
        'fleur': ['🌷', '🌹', '🌺', '🌸', '🌼', '🌻', '💐', '🥀'],
        'flower': ['🌷', '🌹', '🌺', '🌸', '🌼', '🌻', '💐'],
        'rose': ['🌹', '🥀'],
        'feuille': ['🍃', '🍂', '🍁', '🌿'],
        'champignon': ['🍄'],
        'cactus': ['🌵'],

        // Mains / Hands
        'main': ['👋', '🤚', '🖐️', '✋', '👌', '✌️', '🤞', '🤟', '🤘', '🤙', '👈', '👉', '👆', '👇', '☝️', '👍', '👎', '✊', '👊', '🙏'],
        'hand': ['👋', '🤚', '🖐️', '✋', '👌', '✌️', '🤞', '🤟', '🤘', '🤙', '👈', '👉', '👆', '👇', '☝️', '👍', '👎', '✊', '👊', '🙏'],
        'pouce': ['👍', '👎'],
        'bravo': ['👏', '👍', '🙌'],
        'prier': ['🙏'],
        'muscle': ['💪', '🦾'],
        'doigt': ['👈', '👉', '👆', '👇', '☝️', '🖕'],
        'applaudir': ['👏'],

        // Drapeaux / Flags
        'drapeau': ['🏳️', '🏴', '🏁', '🚩', '🇫🇷'],
        'france': ['🇫🇷'],

        // Jeux / Games
        'jeu': ['🎮', '🎰', '🎲', '♟️', '🧩', '🎯', '🎳'],
        'jeux': ['🎮', '🎰', '🎲', '♟️', '🧩', '🎯', '🎳'],
        'game': ['🎮', '🎰', '🎲', '♟️', '🧩', '🎯', '🎳'],
        'puzzle': ['🧩'],
        'echecs': ['♟️'],
        'des': ['🎲'],

        // Art / Créativité
        'art': ['🎨', '🖌️', '🖍️', '✏️', '📝'],
        'film': ['🎬', '📽️', '🎞️', '🎥'],
        'theatre': ['🎭'],
        'cirque': ['🎪', '🤹'],
        'magie': ['🪄', '✨', '🧙'],

        // Objet du quotidien
        'porte': ['🚪'],
        'chaise': ['🪑'],
        'lit': ['🛏️', '🛋️'],
        'miroir': ['🪞'],
        'fenetre': ['🪟'],
        'parapluie': ['☔', '☂️'],
        'lunettes': ['👓', '🕶️'],
        'chapeau': ['🎩', '🧢', '👒'],
        'couronne': ['👑'],
        'bague': ['💍'],
        'sac': ['👜', '👝', '🎒', '🛍️'],
        'poubelle': ['🗑️'],
        'boite': ['📦', '📫', '📪'],

        // Symboles
        'fleche': ['➡️', '⬅️', '⬆️', '⬇️', '↗️', '↘️', '↙️', '↖️', '↕️', '↔️'],
        'cercle': ['🔴', '🟠', '🟡', '🟢', '🔵', '🟣', '⚫', '⚪', '🟤'],
        'carre': ['🟥', '🟧', '🟨', '🟩', '🟦', '🟪', '⬛', '⬜', '🟫'],
        'numero': ['0️⃣', '1️⃣', '2️⃣', '3️⃣', '4️⃣', '5️⃣', '6️⃣', '7️⃣', '8️⃣', '9️⃣', '🔟'],
        'infini': ['♾️'],
        'recyclage': ['♻️'],
        'paix': ['☮️', '✌️', '🕊️'],
        'danger': ['☢️', '☣️', '⚠️', '💀'],
    };

    // Nombre maximum de résultats affichés
    const MAX_RESULTS = 24;

    /**
     * Créer un composant emoji picker
     */
    window.AgencyEmojiPicker = class {
        constructor(container, options = {}) {
            this.container = container;
            this.selectedEmoji = options.defaultEmoji || '📋';
            this.onChange = options.onChange || (() => {});
            this.inputField = options.inputField || null;
            this._debounceTimer = null;

            this.renderShell();
            this.attachShellEvents();
        }

        renderShell() {
            const displayContent = (window.AgencyEmoji && AgencyEmoji.html)
                ? AgencyEmoji.html(this.selectedEmoji)
                : this.selectedEmoji;
            const html = `
                <div class="agency-emoji-picker">
                    <label class="agency-emoji-label">${this.container.dataset.label || __('Project icon', 'agency-client-portal')}</label>
                    <div class="agency-emoji-display" data-emoji="${this.selectedEmoji}">
                        ${displayContent}
                    </div>
                    <div class="agency-emoji-popup">
                        <input type="text"
                               class="agency-emoji-search"
                               placeholder="Tapez pour rechercher un emoji..."
                               autocomplete="off">
                        <div class="agency-emoji-grid-container">
                            <div class="agency-emoji-grid">
                                <div class="agency-emoji-placeholder">Tapez un mot-clé pour trouver un emoji</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            this.container.innerHTML = html;
            this.display = this.container.querySelector('.agency-emoji-display');
            this.popup = this.container.querySelector('.agency-emoji-popup');
            this.searchInput = this.container.querySelector('.agency-emoji-search');
            this.grid = this.container.querySelector('.agency-emoji-grid');
        }

        attachShellEvents() {
            // Toggle popup
            this.display.addEventListener('click', (e) => {
                e.stopPropagation();
                this.popup.classList.toggle('active');

                if (this.popup.classList.contains('active') && this.searchInput) {
                    this.searchInput.value = '';
                    this.grid.innerHTML = '<div class="agency-emoji-placeholder">' + __('Type a keyword to find an emoji', 'agency-client-portal') + '</div>';
                    setTimeout(() => this.searchInput.focus(), 100);
                }
            });

            // Debounced search
            if (this.searchInput) {
                this.searchInput.addEventListener('input', (e) => {
                    e.stopPropagation();
                    clearTimeout(this._debounceTimer);
                    const value = e.target.value;
                    this._debounceTimer = setTimeout(() => {
                        this.searchEmojis(value);
                    }, 300);
                });

                this.searchInput.addEventListener('click', (e) => {
                    e.stopPropagation();
                });
            }

            // Close popup when clicking outside
            document.addEventListener('click', (e) => {
                if (!this.container.contains(e.target)) {
                    this.popup.classList.remove('active');
                }
            });
        }

        searchEmojis(searchTerm) {
            const term = searchTerm.toLowerCase().trim();

            if (!term) {
                this.grid.innerHTML = '<div class="agency-emoji-placeholder">' + __('Type a keyword to find an emoji', 'agency-client-portal') + '</div>';
                return;
            }

            // Collect matching emojis (deduplicated, max MAX_RESULTS)
            const seen = new Set();
            const results = [];

            for (const [keyword, emojis] of Object.entries(EMOJI_MAP)) {
                if (keyword.includes(term)) {
                    for (const emoji of emojis) {
                        if (!seen.has(emoji)) {
                            seen.add(emoji);
                            results.push(emoji);
                            if (results.length >= MAX_RESULTS) break;
                        }
                    }
                }
                if (results.length >= MAX_RESULTS) break;
            }

            if (results.length === 0) {
                this.grid.innerHTML = '<div class="agency-emoji-placeholder">' + __('No emoji found', 'agency-client-portal') + '</div>';
                return;
            }

            // Render only matching results (avec rendu Twemoji si disponible)
            const renderEmoji = (window.AgencyEmoji && AgencyEmoji.html)
                ? (e) => AgencyEmoji.html(e)
                : (e) => e;
            this.grid.innerHTML = results.map(emoji => `
                <div class="agency-emoji-option${emoji === this.selectedEmoji ? ' selected' : ''}"
                     data-emoji="${emoji}">
                    ${renderEmoji(emoji)}
                </div>
            `).join('');

            // Attach click events
            this.grid.querySelectorAll('.agency-emoji-option').forEach(option => {
                option.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.selectEmoji(option.dataset.emoji);
                });
            });
        }

        selectEmoji(emoji) {
            this.selectedEmoji = emoji;
            this.display.dataset.emoji = emoji;
            // Affichage SVG si AgencyEmoji est disponible, sinon Unicode natif
            if (window.AgencyEmoji && AgencyEmoji.html) {
                this.display.innerHTML = AgencyEmoji.html(emoji);
            } else {
                this.display.textContent = emoji;
            }

            if (this.inputField) {
                this.inputField.value = emoji;
            }

            this.popup.classList.remove('active');
            this.onChange(emoji);
        }

        getValue() {
            return this.selectedEmoji;
        }
    };
    
    /**
     * Créer et gérer les modals
     */
    window.AgencyModal = class {
        constructor(modalId) {
            this.modalId = modalId;
            this.modal = document.getElementById(modalId);
            
            if (this.modal) {
                this.attachEvents();
            }
        }
        
        attachEvents() {
            // Close button
            const closeBtn = this.modal.querySelector('.agency-modal-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => this.close());
            }
            
            // Click outside
            this.modal.addEventListener('click', (e) => {
                if (e.target === this.modal) {
                    this.close();
                }
            });
            
            // Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.modal.classList.contains('active')) {
                    this.close();
                }
            });
        }
        
        open() {
            if (this.modal) {
                this.modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }
        
        close() {
            if (this.modal) {
                this.modal.classList.remove('active');
                document.body.style.overflow = '';
            }
        }
    };
    
    /**
     * Auto-initialisation des emoji pickers au chargement
     */
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[data-agency-emoji-picker]').forEach(container => {
            const inputField = container.dataset.inputField ? 
                document.getElementById(container.dataset.inputField) : null;
            
            new AgencyEmojiPicker(container, {
                defaultEmoji: container.dataset.defaultEmoji || '📋',
                inputField: inputField,
                onChange: (emoji) => {
                }
            });
        });
    });
    
})();
