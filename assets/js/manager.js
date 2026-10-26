/**
 * Agency Manager JavaScript
 * Fonctionnalités pour les membres de l'agence (drag & drop, édition, etc.)
 */

(function($) {
    'use strict';

    var __ = wp.i18n.__;
    
    const AgencyManager = {
        newProjectModal: null,
        
        init: function() {
            this.initSortable();
            this.bindManagerEvents();
            this.initSidebar();
        },
        
        initSortable: function() {
            // Drag & Drop pour les blocs
            const blocksList = document.getElementById('blocks-list');
            if (blocksList && typeof Sortable !== 'undefined') {
                Sortable.create(blocksList, {
                    animation: 150,
                    delay: 100,
                    delayOnTouchOnly: true,
                    draggable: '.agency-block',
                    onEnd: this.handleBlockReorder.bind(this)
                });
            }
            
            // Drag & Drop pour les projets
            const projectsGrid = document.getElementById('projects-grid');
            if (projectsGrid && typeof Sortable !== 'undefined') {
                Sortable.create(projectsGrid, {
                    animation: 150,
                    delay: 100,
                    delayOnTouchOnly: true,
                    draggable: '.agency-project-card',
                    onEnd: this.handleProjectReorder.bind(this)
                });
            }
        },
        
        bindManagerEvents: function() {
            // Ajouter projet
            $('#add-project-btn').on('click', this.addProject.bind(this));

            // Ajouter bloc: handled by modal UI in project-view.php, so do not bind prompt-based handler here
            // $('#add-block-btn').on('click', this.addBlock.bind(this));

            // Éditer projet
            $(document).on('click', '.edit-project', this.editProject.bind(this));

            // Supprimer projet
            $(document).on('click', '.delete-project', this.deleteProject.bind(this));

            // Éditer bloc
            $(document).on('click', '.edit-block', this.editBlock.bind(this));

            // Supprimer bloc
            $(document).on('click', '.delete-block', this.deleteBlock.bind(this));

            // Sélection image de bloc (médiathèque)
            $(document).on('click', '#edit-block-image-btn', this.selectEditBlockImage.bind(this));

            // Sélection image de bloc depuis les assets du projet
            $(document).on('click', '#edit-block-project-assets-btn', this.openProjectAssetsPicker.bind(this));
            $(document).on('click', '#project-assets-picker-close', this.closeProjectAssetsPicker.bind(this));

            // Update block button
            $(document).on('click', '#update-block-btn', this.updateBlock.bind(this));

            // Presets – Enregistrer une disposition
            $(document).on('click', '#save-preset-btn', this.openSavePresetModal.bind(this));
            $(document).on('click', '#save-preset-cancel, #save-preset-modal-close', this.closeSavePresetModal.bind(this));
            $(document).on('click', '#save-preset-confirm', this.savePreset.bind(this));

            // Presets – Importer une disposition
            $(document).on('click', '#import-preset-btn', this.openImportPresetModal.bind(this));
            $(document).on('click', '#import-preset-cancel, #import-preset-modal-close', this.closeImportPresetModal.bind(this));

            // Copier le lien d'accès d'un client (dashboard, portail)
            $(document).on('click', '.agency-copy-access', this.copyAccessLink.bind(this));

            // Note: Upload asset is handled in frontend.js to avoid duplicate event binding
        },

        /**
         * Récupère le lien d'accès (token) d'un client et le copie.
         */
        copyAccessLink: function(e) {
            e.preventDefault();
            e.stopPropagation();
            const $btn = $(e.currentTarget);
            const clientId = $btn.data('client-id');
            if ($btn.prop('disabled')) return;
            $btn.prop('disabled', true);

            const copy = function(url) {
                const done = function() { AgencyUI.toast(__('Link copied to clipboard!', 'agency-client-portal')); };
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(url).then(done).catch(function() { fallback(url); });
                } else {
                    fallback(url);
                }
                function fallback(text) {
                    const $tmp = $('<input>').val(text).appendTo('body');
                    $tmp[0].select();
                    try { document.execCommand('copy'); done(); } catch (err) { window.prompt(__('Copy the link:', 'agency-client-portal'), text); }
                    $tmp.remove();
                }
            };

            $.post(agencyData.ajax_url, {
                action: 'agency_get_access_url',
                client_id: clientId,
                nonce: agencyData.admin_nonce
            }, function(resp) {
                if (resp.success && resp.data && resp.data.access_url) {
                    copy(resp.data.access_url);
                } else if (resp.data && resp.data.code === 'token_plaintext_expired') {
                    AgencyUI.toast(__('Legacy token — go to Edit client to regenerate it.', 'agency-client-portal'), { type: 'error' });
                } else {
                    AgencyUI.toast(__('Unable to retrieve the access link.', 'agency-client-portal'), { type: 'error' });
                }
            }).fail(function() {
                AgencyUI.toast(__('Server error while retrieving the link.', 'agency-client-portal'), { type: 'error' });
            }).always(function() {
                $btn.prop('disabled', false);
            });
        },

        /**
         * Sidebar agence : repli en rail, groupes ouverts, recherche (⌘K),
         * infobulles du rail et tiroir mobile. Les états sont mémorisés dans
         * localStorage (confort uniquement : le chemin courant est toujours
         * ouvert côté serveur).
         */
        initSidebar: function() {
            var sidebar = document.getElementById('agency-sidebar');
            if (!sidebar) return;

            var body    = document.body;
            var toggle  = document.getElementById('agency-sidebar-toggle');
            var OPEN_KEY = 'agency_sidebar_open';

            function store(key, value) {
                try { localStorage.setItem(key, value); } catch (e) {}
            }
            function read(key) {
                try { return localStorage.getItem(key); } catch (e) { return null; }
            }

            // --- Repli / dépli ---
            function setFolded(folded) {
                body.classList.toggle('agency-sidebar-closed', folded);
                body.classList.toggle('agency-sidebar-open', !folded);
                store('agency_sidebar_state', folded ? 'closed' : 'open');
                if (toggle) {
                    toggle.setAttribute('aria-label', folded ? toggle.dataset.labelClosed : toggle.dataset.labelOpen);
                }
                hideTip();
            }

            if (toggle) {
                toggle.addEventListener('click', function() { setFolded(true); });
                // Bouton « déplier » affiché en bas du rail
                var unfold = toggle.cloneNode(true);
                unfold.removeAttribute('id');
                unfold.className = 'agency-btn-icon agency-sidebar__unfold';
                unfold.setAttribute('aria-label', toggle.dataset.labelClosed);
                unfold.dataset.tip = toggle.dataset.labelClosed;
                unfold.addEventListener('click', function() { setFolded(false); });
                sidebar.querySelector('.agency-sidebar__foot').prepend(unfold);
            }

            // --- Groupes clients / projets ouverts ---
            function openKeys() {
                try { return JSON.parse(read(OPEN_KEY) || '[]'); } catch (e) { return []; }
            }
            openKeys().forEach(function(key) {
                var g = sidebar.querySelector('[data-sb-key="' + key + '"]');
                if (g) g.classList.add('is-open');
            });
            sidebar.querySelectorAll('.agency-sidebar__group.is-open, .agency-sidebar__pgroup.is-open').forEach(syncChevron);

            function syncChevron(group) {
                var chev = group.querySelector(':scope > div > .agency-sidebar__chev');
                if (chev && chev.tagName === 'BUTTON') {
                    chev.setAttribute('aria-expanded', group.classList.contains('is-open') ? 'true' : 'false');
                }
            }

            sidebar.addEventListener('click', function(e) {
                var chev = e.target.closest('button.agency-sidebar__chev');
                if (!chev) return;
                e.preventDefault();
                var group = chev.closest('.agency-sidebar__pgroup, .agency-sidebar__group');
                group.classList.toggle('is-open');
                syncChevron(group);
                var keys = openKeys().filter(function(k) { return k !== group.dataset.sbKey; });
                if (group.classList.contains('is-open')) keys.push(group.dataset.sbKey);
                store(OPEN_KEY, JSON.stringify(keys));
            });

            // --- Recherche (clients, projets, blocs) ---
            var search = document.getElementById('agency-sidebar-search');
            var empty  = document.getElementById('agency-sidebar-empty');

            function filter(term) {
                term = (term || '').trim().toLowerCase();
                sidebar.classList.toggle('is-searching', term !== '');
                var anyVisible = false;
                sidebar.querySelectorAll('.agency-sidebar__group').forEach(function(group) {
                    var groupMatch = term === '' || group.dataset.sbSearch.toLowerCase().indexOf(term) !== -1;
                    var groupHasChild = false;
                    group.querySelectorAll('.agency-sidebar__pgroup').forEach(function(pg) {
                        var projMatch = groupMatch || pg.dataset.sbSearch.toLowerCase().indexOf(term) !== -1;
                        var projHasChild = false;
                        pg.querySelectorAll('.agency-sidebar__row--block').forEach(function(row) {
                            var match = projMatch || row.dataset.sbSearch.toLowerCase().indexOf(term) !== -1;
                            row.toggleAttribute('data-sb-hidden', !match);
                            projHasChild = projHasChild || match;
                        });
                        var showProj = projMatch || projHasChild;
                        pg.toggleAttribute('data-sb-hidden', !showProj);
                        groupHasChild = groupHasChild || showProj;
                    });
                    var show = groupMatch || groupHasChild;
                    group.toggleAttribute('data-sb-hidden', !show);
                    anyVisible = anyVisible || show;
                });
                if (empty) empty.hidden = anyVisible;
            }

            if (search) {
                search.addEventListener('input', function() { filter(search.value); });
                search.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        search.value = '';
                        filter('');
                        search.blur();
                    }
                });
            }

            function focusSearch() {
                if (window.matchMedia('(max-width: 900px)').matches) {
                    body.classList.add('agency-sidebar-mobile-open');
                } else if (body.classList.contains('agency-sidebar-closed')) {
                    setFolded(false);
                }
                if (search) { search.focus(); search.select(); }
            }

            var searchBtn = document.getElementById('agency-sidebar-search-btn');
            if (searchBtn) searchBtn.addEventListener('click', focusSearch);

            document.addEventListener('keydown', function(e) {
                if ((e.metaKey || e.ctrlKey) && (e.key === 'k' || e.key === 'K')) {
                    e.preventDefault();
                    focusSearch();
                }
            });

            // Raccourci affiché selon la plateforme
            var kbd = sidebar.querySelector('.agency-sidebar__kbd');
            if (kbd && !/Mac|iPhone|iPad/.test(navigator.platform || '')) kbd.textContent = 'Ctrl K';

            // --- Infobulles du rail ---
            var tip = null;
            function hideTip() { if (tip) { tip.remove(); tip = null; } }
            sidebar.addEventListener('mouseover', function(e) {
                if (!body.classList.contains('agency-sidebar-closed')) return;
                var el = e.target.closest('[data-tip]');
                if (!el || !sidebar.contains(el)) return;
                hideTip();
                var r = el.getBoundingClientRect();
                tip = document.createElement('div');
                tip.className = 'agency-sidebar-tip';
                tip.textContent = el.dataset.tip;
                tip.style.left = (sidebar.getBoundingClientRect().right + 8) + 'px';
                tip.style.top = (r.top + r.height / 2) + 'px';
                body.appendChild(tip);
            });
            sidebar.addEventListener('mouseout', function(e) {
                var el = e.target.closest('[data-tip]');
                if (el && !el.contains(e.relatedTarget)) hideTip();
            });

            // --- Tiroir mobile ---
            var burger = document.getElementById('agency-sidebar-burger');
            var scrim  = document.getElementById('agency-sidebar-scrim');
            function setMobile(open) {
                body.classList.toggle('agency-sidebar-mobile-open', open);
                if (burger) burger.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (scrim) scrim.hidden = !open;
            }
            if (burger) burger.addEventListener('click', function() { setMobile(true); });
            if (scrim) scrim.addEventListener('click', function() { setMobile(false); });
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && body.classList.contains('agency-sidebar-mobile-open')) setMobile(false);
            });
        },

        addProject: function(e) {
            e.preventDefault();

            const clientId = this.getCurrentClientId();

            if (!clientId) {
                console.error('Client ID not found');
                AgencyUI.toast('Error: Client ID not found', { type: 'error' });
                return;
            }

            if (typeof AgencyModal !== 'undefined') {
                if (!this.newProjectModal) {
                    this.newProjectModal = new AgencyModal('new-project-modal');
                    $('#new-project-modal .modal-cancel').on('click', () => this.newProjectModal.close());
                }
            }

            const modal = $('#new-project-modal');
            $('#new-project-name').val('');
            $('#new-project-emoji').val('📋');
            $('#new-project-image-url').val('');
            this.updateProjectImagePreview('');
            if (this.newProjectModal) {
                this.newProjectModal.open();
            } else {
                modal.addClass('active');
            }
        },

        selectProjectImage: function(e) {
            e.preventDefault();

            if (typeof wp === 'undefined' || !wp.media) {
                AgencyUI.toast('Media uploader not available', { type: 'error' });
                return;
            }

            if (!this._newProjectImageFrame) {
                this._newProjectImageFrame = wp.media({
                    title: 'Choisir une image',
                    button: { text: 'Utiliser cette image' },
                    multiple: false,
                    library: { type: 'image' }
                });

                this._newProjectImageFrame.on('select', () => {
                    const attachment = this._newProjectImageFrame.state().get('selection').first().toJSON();
                    $('#new-project-image-url').val(attachment.url);
                    this.updateProjectImagePreview(attachment.url);
                });
            }

            // Définir le contexte d'upload (nouveau projet : slug dérivé du nom)
            const projectName = $('#new-project-name').val() || '';
            $.post(agencyData.ajax_url, {
                action: 'agency_set_upload_context',
                nonce: agencyData.nonce,
                client_slug: agencyData.client_slug || '',
                project_slug: projectName
            }).always(() => {
                this._newProjectImageFrame.open();
            });
        },

        updateProjectImagePreview: function(url) {
            const $preview = $('#new-project-image-preview');
            if (!$preview.length) return;
            if (!url) {
                $preview.hide().empty();
                return;
            }
            $preview
                .html('<img src="' + url + '" alt="" />')
                .show();
        },

        selectEditProjectImage: function(e) {
            e.preventDefault();

            if (typeof wp === 'undefined' || !wp.media) {
                AgencyUI.toast('Media uploader not available', { type: 'error' });
                return;
            }

            if (!this._editProjectImageFrame) {
                this._editProjectImageFrame = wp.media({
                    title: 'Choisir une image',
                    button: { text: 'Utiliser cette image' },
                    multiple: false,
                    library: { type: 'image' }
                });

                this._editProjectImageFrame.on('select', () => {
                    const attachment = this._editProjectImageFrame.state().get('selection').first().toJSON();
                    // Utiliser la taille large pour la couverture si disponible
                    const coverUrl = (attachment.sizes && attachment.sizes.large)
                        ? attachment.sizes.large.url
                        : attachment.url;
                    $('#edit-project-image-url').val(coverUrl);
                    this.updateEditProjectImagePreview(coverUrl);
                });
            }

            // Définir le contexte d'upload (projet en cours d'édition)
            const projectSlug = $('#edit-project-slug').val() || '';
            $.post(agencyData.ajax_url, {
                action: 'agency_set_upload_context',
                nonce: agencyData.nonce,
                client_slug: agencyData.client_slug || '',
                project_slug: projectSlug
            }).always(() => {
                this._editProjectImageFrame.open();
            });
        },

        updateEditProjectImagePreview: function(url) {
            const $preview = $('#edit-project-image-preview');
            if (!$preview.length) return;
            if (!url) {
                $preview.hide().empty();
                return;
            }
            $preview
                .html('<img src="' + url + '" alt="" />')
                .show();
        },

        getBestCardBackgroundImageUrl: function(attachment) {
            if (!attachment || !attachment.sizes || typeof attachment.sizes !== 'object') {
                return attachment && attachment.url ? attachment.url : '';
            }

            const targetWidth = 800;
            const minUsableWidth = 600;
            const candidates = Object.values(attachment.sizes)
                .filter((size) => size && size.url)
                .map((size) => ({
                    url: size.url,
                    width: parseInt(size.width, 10) || 0
                }));

            if (!candidates.length) {
                return attachment.url || '';
            }

            // Évite les tailles trop petites (souvent floues en fond de carte),
            // puis prend la variante la plus proche d'un thumbnail ~800px.
            const pool = candidates.filter((size) => size.width >= minUsableWidth);
            const usableCandidates = pool.length ? pool : candidates;

            let bestMatch = usableCandidates[0];
            let bestScore = Math.abs(bestMatch.width - targetWidth);

            usableCandidates.forEach((candidate) => {
                const score = Math.abs(candidate.width - targetWidth);
                if (score < bestScore || (score === bestScore && candidate.width > bestMatch.width)) {
                    bestMatch = candidate;
                    bestScore = score;
                }
            });

            return bestMatch.url || attachment.url || '';
        },

        selectEditBlockImage: function(e) {
            e.preventDefault();

            if (typeof wp === 'undefined' || !wp.media) {
                AgencyUI.toast('Media uploader not available', { type: 'error' });
                return;
            }

            const frame = wp.media({
                title: 'Choisir une image',
                button: { text: 'Utiliser cette image' },
                multiple: false,
                library: { type: 'image' }
            });

            frame.on('select', () => {
                const attachment = frame.state().get('selection').first().toJSON();
                const imageUrl = this.getBestCardBackgroundImageUrl(attachment);
                $('#edit-block-image-url').val(imageUrl);
                this.updateEditBlockImagePreview(imageUrl);
            });

            frame.open();
        },

        openProjectAssetsPicker: function(e) {
            e.preventDefault();
            const projectId = $(e.currentTarget).data('project-id');
            if (!projectId) return;

            const $modal   = $('#project-assets-picker-modal');
            const $grid    = $('#project-assets-picker-grid');
            const $empty   = $('#project-assets-picker-empty');

            // Reset state
            $grid.html('<p class="project-assets-picker-loading">' + __('Loading…', 'agency-client-portal') + '</p>').show();
            $empty.hide();
            $modal.addClass('active');

            $.ajax({
                url: agencyData.rest_url + 'projects/' + projectId + '/assets',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', agencyData.nonce);
                },
                success: (assets) => {
                    $grid.empty();
                    if (!assets || assets.length === 0) {
                        $grid.hide();
                        $empty.show();
                        return;
                    }
                    assets.forEach((asset) => {
                        // Afficher uniquement les images (assets avec thumbnail ou extension image)
                        if (!asset.thumbnail_url && !/\.(jpg|jpeg|png|gif|webp|svg)$/i.test(asset.url || '')) {
                            return; // skip non-images
                        }
                        const $item = $('<div class="project-assets-picker-item"></div>');
                        const thumbSrc = asset.thumbnail_url || asset.url;
                        const $img  = $('<img>', { 
                            src: thumbSrc, 
                            alt: asset.filename, 
                            title: asset.filename,
                            loading: 'lazy'
                        });
                        
                        // Si le thumbnail échoue à charger (404), utiliser l'URL complète
                        // mais la CSS du modal limitera déjà la taille d'affichage
                        $img.on('error', function() {
                            if ($(this).attr('src') !== asset.url) {
                                $(this).attr('src', asset.url);
                            }
                        });
                        
                        $item.append($img);
                        $item.on('click', () => {
                            // Utiliser le thumbnail pour le fond de la carte
                            const bgUrl = asset.thumbnail_url || asset.url;
                            $('#edit-block-image-url').val(bgUrl);
                            this.updateEditBlockImagePreview(bgUrl);
                            this.closeProjectAssetsPicker();
                        });
                        $grid.append($item);
                    });
                },
                error: () => {
                    $grid.html('<p>' + __('Error while loading images.', 'agency-client-portal') + '</p>');
                }
            });
        },

        closeProjectAssetsPicker: function() {
            $('#project-assets-picker-modal').removeClass('active');
        },

        // ===== PRESETS =====

        openSavePresetModal: function(e) {
            e.preventDefault();
            $('#preset-name').val('');
            $('#save-preset-modal').addClass('active');
        },

        closeSavePresetModal: function() {
            $('#save-preset-modal').removeClass('active');
        },

        savePreset: function() {
            const name = $('#preset-name').val().trim();
            if (!name) {
                AgencyUI.toast(agencyData.strings && agencyData.strings.fill_required ? agencyData.strings.fill_required : 'Veuillez saisir un nom.');
                return;
            }

            const projectId = parseInt($('#save-preset-btn').data('project-id'), 10);
            if (!projectId) return;

            const payload = {
                name: name,
                project_id: projectId,
                include_blocks:          $('#preset-include-blocks').is(':checked'),
                include_grid:            $('#preset-include-grid').is(':checked'),
                include_descriptions:    $('#preset-include-descriptions').is(':checked'),
                include_links:           $('#preset-include-links').is(':checked'),
                include_separators:      $('#preset-include-separators').is(':checked'),
                include_card_thumbnails: $('#preset-include-card-thumbnails').is(':checked'),
                include_thumbnail:       $('#preset-include-thumbnail').is(':checked'),
                include_settings:        $('#preset-include-settings').is(':checked'),
            };

            const $btn = $('#save-preset-confirm');
            $btn.prop('disabled', true).text('...');

            $.ajax({
                url: agencyData.rest_url + 'block-presets',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(payload),
                beforeSend: xhr => xhr.setRequestHeader('X-WP-Nonce', agencyData.nonce),
                success: (data) => {
                    if (data.success) {
                        this.closeSavePresetModal();
                        AgencyUI.toast(__('Layout saved!', 'agency-client-portal'), { type: 'success' });
                    } else {
                        AgencyUI.toast(__('Error while saving.', 'agency-client-portal'), { type: 'error' });
                    }
                    $btn.prop('disabled', false).html((agencyData.icons ? agencyData.icons.save : '') + ' Enregistrer');
                },
                error: () => {
                    AgencyUI.toast(__('Error while saving.', 'agency-client-portal'), { type: 'error' });
                    $btn.prop('disabled', false).html((agencyData.icons ? agencyData.icons.save : '') + ' Enregistrer');
                }
            });
        },

        openImportPresetModal: function(e) {
            e.preventDefault();
            $('#import-preset-modal').addClass('active');
            this.loadPresets();
        },

        closeImportPresetModal: function() {
            $('#import-preset-modal').removeClass('active');
        },

        loadPresets: function() {
            const $list  = $('#presets-list');
            const $empty = $('#presets-list-empty');
            $list.html('<p class="presets-list-loading">' + __('Loading…', 'agency-client-portal') + '</p>');
            $empty.hide();

            const projectId = parseInt($('#import-preset-btn').data('project-id'), 10);

            $.ajax({
                url: agencyData.rest_url + 'block-presets',
                method: 'GET',
                beforeSend: xhr => xhr.setRequestHeader('X-WP-Nonce', agencyData.nonce),
                success: (presets) => {
                    $list.empty();
                    if (!presets || presets.length === 0) {
                        $empty.show();
                        return;
                    }
                    presets.forEach(preset => {
                        const date = preset.created_at ? new Date(preset.created_at).toLocaleDateString() : '';
                        const thumbHtml = preset.thumbnail_url
                            ? `<img src="${preset.thumbnail_url}" class="preset-card-thumb" alt="" onerror="this.style.display='none'">`
                            : '';
                        const $card = $(`
                            <div class="preset-card">
                                ${thumbHtml}
                                <div class="preset-card-info">
                                    <strong class="preset-card-name">${$('<span>').text(preset.name).html()}</strong>
                                    <span class="preset-card-date">${date}</span>
                                </div>
                                <div class="preset-card-actions">
                                    <button class="agency-btn agency-btn-sm agency-btn-primary preset-import-btn" data-preset-id="${preset.id}">${agencyData.icons ? agencyData.icons.download : ''} Importer</button>
                                    <button class="agency-btn agency-btn-sm agency-btn-danger preset-delete-btn" data-preset-id="${preset.id}">${agencyData.icons ? agencyData.icons.trash : ''}</button>
                                </div>
                            </div>
                        `);

                        $card.find('.preset-import-btn').on('click', () => this.importPreset(preset.id, projectId));
                        $card.find('.preset-delete-btn').on('click', () => this.deletePreset(preset.id, $card));

                        $list.append($card);
                    });
                },
                error: () => {
                    $list.html('<p>' + __('Error while loading.', 'agency-client-portal') + '</p>');
                }
            });
        },

        importPreset: function(presetId, projectId) {
            if (!confirm(__('Import this layout? New blocks will be created in the project.', 'agency-client-portal'))) return;

            $.ajax({
                url: agencyData.rest_url + 'block-presets/' + presetId + '/import',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ project_id: projectId }),
                beforeSend: xhr => xhr.setRequestHeader('X-WP-Nonce', agencyData.nonce),
                success: (data) => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        AgencyUI.toast(__('Error while importing.', 'agency-client-portal'), { type: 'error' });
                    }
                },
                error: () => AgencyUI.toast(__('Error while importing.', 'agency-client-portal'), { type: 'error' })
            });
        },

        deletePreset: function(presetId, $card) {
            if (!confirm(__('Delete this layout?', 'agency-client-portal'))) return;

            $.ajax({
                url: agencyData.rest_url + 'block-presets/' + presetId,
                method: 'DELETE',
                beforeSend: xhr => xhr.setRequestHeader('X-WP-Nonce', agencyData.nonce),
                success: (data) => {
                    if (data.success) {
                        $card.remove();
                        if ($('#presets-list').children().length === 0) {
                            $('#presets-list-empty').show();
                        }
                    }
                }
            });
        },

        updateEditBlockImagePreview: function(url) {
            const $preview = $('#edit-block-image-preview');
            if (!$preview.length) return;
            if (!url) {
                $preview.hide().empty();
                return;
            }
            $preview
                .html('<img src="' + url + '" alt="" />')
                .show();
        },

        supportsMediaDownloadSetting: function(blockType) {
            return ['video', 'versioned_media', 'storyboard', 'script'].indexOf(blockType) !== -1;
        },

        toggleMediaDownloadSetting: function(blockType, allowMediaDownload) {
            const $group = $('#edit-block-download-group');
            const $checkbox = $('#edit-block-allow-media-download');

            if (!this.supportsMediaDownloadSetting(blockType)) {
                $group.hide();
                $checkbox.prop('checked', true);
                return;
            }

            $group.show();
            $checkbox.prop('checked', !!allowMediaDownload);
        },

        updateBlock: function(e) {
            e.preventDefault();

            const blockId = $('#edit-block-id').val();
            const blockType = $('#edit-block-type').val();
            const title = $('#edit-block-title').val().trim();
            const backgroundImageUrl = $('#edit-block-image-url').val().trim();

            if (!title) {
                AgencyUI.toast('Veuillez entrer un titre de bloc');
                return;
            }

            const $btn = $(this);
            $btn.prop('disabled', true).text(__('Updating...', 'agency-client-portal'));

            // Prepare settings object – always include background_image_url (even empty) to allow removal
            const settings = { background_image_url: backgroundImageUrl };

            if (this.supportsMediaDownloadSetting(blockType)) {
                settings.allow_media_download = $('#edit-block-allow-media-download').is(':checked') ? 1 : 0;
            }
            
            // Add client comment setting
            settings.client_can_comment = $('#edit-block-client-comment').is(':checked') ? 1 : 0;

            // Add comment notification setting
            settings.notify_on_comment = $('#edit-block-notify-on-comment').is(':checked') ? 1 : 0;

            $.ajax({
                url: agencyData.rest_url + 'blocks/' + blockId,
                method: 'PUT',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', agencyData.nonce);
                },
                data: JSON.stringify({
                    title: title,
                    settings: settings
                }),
                contentType: 'application/json',
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        AgencyUI.toast(__('Error while updating', 'agency-client-portal'), { type: 'error' });
                        $btn.prop('disabled', false).text(__('Update', 'agency-client-portal'));
                    }
                },
                error: function() {
                    AgencyUI.toast(agencyData.strings.error, { type: 'error' });
                    $btn.prop('disabled', false).text(__('Update', 'agency-client-portal'));
                }
            });
        },
        
        // addBlock: function(e) { /* Disabled: use modal UI only */ },
        
        editProject: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $btn = $(e.currentTarget);
            const projectId = $btn.data('project-id');
            const projectName = $btn.data('project-name');
            const projectEmoji = $btn.data('project-emoji');
            const projectCover = $btn.data('project-cover') || '';
            
            // Open edit modal and populate fields
            const modal = $('#edit-project-modal');
            $('#edit-project-id').val(projectId);
            $('#edit-project-name').val(projectName);
            $('#edit-project-emoji').val(projectEmoji);
            $('#edit-project-image-url').val(projectCover);
            this.updateEditProjectImagePreview(projectCover);
            
            // Update the emoji picker display
            const $emojiDisplay = $('#edit-project-modal').find('[data-agency-emoji-picker]').find('.agency-emoji-display');
            if ($emojiDisplay.length) {
                $emojiDisplay.text(projectEmoji);
                $emojiDisplay[0].dataset.emoji = projectEmoji;
            }
            
            modal.addClass('active');
        },
        
        deleteProject: function(e) {
            e.preventDefault();
            
            if (!confirm(agencyData.strings.confirm_delete)) {
                return;
            }
            
            const projectId = $(e.currentTarget).data('project-id');
            
            $.ajax({
                url: agencyData.rest_url + 'projects/' + projectId,
                method: 'DELETE',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', agencyData.nonce);
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    }
                },
                error: function() {
                    AgencyUI.toast(agencyData.strings.error, { type: 'error' });
                }
            });
        },
        
        editBlock: function(e) {
            e.preventDefault();
            e.stopPropagation();

            const $btn = $(e.currentTarget);
            const blockId = $btn.data('block-id');
            const $blockCard = $btn.closest('.agency-block');
            const blockTitle = $blockCard.find('.block-title').text().trim();
            const blockType = $blockCard.data('block-type');
            const blockImage = $blockCard.data('background-image') || '';
            const allowMediaDownload = parseInt($blockCard.attr('data-allow-media-download'), 10) !== 0;
            const clientCanComment = parseInt($blockCard.attr('data-client-can-comment'), 10) !== 0;
            const notifyOnComment = parseInt($blockCard.attr('data-notify-on-comment'), 10) !== 0;

            // Open edit modal and populate fields
            const modal = $('#edit-block-modal');
            $('#edit-block-id').val(blockId);
            $('#edit-block-type').val(blockType);
            $('#edit-block-title').val(blockTitle);
            $('#edit-block-image-url').val(blockImage);
            this.updateEditBlockImagePreview(blockImage);
            this.toggleMediaDownloadSetting(blockType, allowMediaDownload);
            
            // Set client comment checkbox
            $('#edit-block-client-comment').prop('checked', !!clientCanComment);

            // Set comment notification checkbox (défaut activé)
            $('#edit-block-notify-on-comment').prop('checked', !!notifyOnComment);

            modal.addClass('active');
        },
        
        deleteBlock: function(e) {
            e.preventDefault();
            
            if (!confirm(agencyData.strings.confirm_delete)) {
                return;
            }
            
            const blockId = $(e.currentTarget).data('block-id');
            // Depuis une page de bloc : retour au projet (la page n'existe plus)
            const redirectUrl = $(e.currentTarget).data('redirect');
            
            $.ajax({
                url: agencyData.rest_url + 'blocks/' + blockId,
                method: 'DELETE',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', agencyData.nonce);
                },
                success: function(response) {
                    if (response.success) {
                        if (redirectUrl) {
                            location.href = redirectUrl;
                        } else {
                            location.reload();
                        }
                    }
                },
                error: function() {
                    AgencyUI.toast(agencyData.strings.error, { type: 'error' });
                }
            });
        },
        
        handleBlockReorder: function(evt) {
            const blockIds = [];
            $('#blocks-list .agency-block').each(function() {
                blockIds.push($(this).data('block-id'));
            });
            
            $.ajax({
                url: agencyData.rest_url + 'blocks/reorder',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', agencyData.nonce);
                },
                data: JSON.stringify({
                    block_ids: blockIds
                }),
                contentType: 'application/json',
                success: function(response) {
                    if (response.success) {
                        console.log('Block order saved');
                    }
                },
                error: function() {
                    console.error('Failed to save block order');
                }
            });
        },
        
        handleProjectReorder: function(evt) {
            const projectIds = [];
            const projectsGrid = $('#projects-grid');
            const clientId = projectsGrid.data('client-id');

            projectsGrid.find('.agency-project-card').each(function() {
                projectIds.push($(this).data('project-id'));
            });

            if (!clientId) {
                console.error('Client ID not found');
                return;
            }

            $.ajax({
                url: agencyData.rest_url + 'projects/reorder',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', agencyData.nonce);
                },
                data: JSON.stringify({
                    project_ids: projectIds,
                    client_id: clientId
                }),
                contentType: 'application/json',
                success: function(response) {
                    if (response.success) {
                        console.log('Project order saved');
                    }
                },
                error: function() {
                    console.error('Failed to save project order');
                }
            });
        },
        
        getCurrentClientId: function() {
            // Extract client ID from URL or a data attribute
            const clientIdFromBody = $('body').data('client-id');
            const clientIdFromUrl = window.location.pathname.match(/\/client\/(\d+)/);
            
            let clientId = clientIdFromBody;
            if (!clientId && clientIdFromUrl) {
                clientId = parseInt(clientIdFromUrl[1]);
            }
            
            console.log('Current client ID:', clientId);
            return clientId || null;
        },
        
        getCurrentProjectId: function() {
            const url = window.location.pathname;
            const match = url.match(/\/project\/([^\/]+)/);
            return match ? match[1] : null;
        },
        
        getCurrentBlockId: function() {
            const url = window.location.pathname;
            const match = url.match(/\/block\/(\d+)/);
            return match ? match[1] : null;
        }
    };
    
    $(document).ready(function() {
        if (agencyData.can_manage) {
            AgencyManager.init();
        }
    });
    
})(jQuery);
