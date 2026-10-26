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
            this.initSidebarProjects();
        },
        
        initSortable: function() {
            // Drag & Drop pour les blocs
            const blocksList = document.getElementById('blocks-list');
            if (blocksList && typeof Sortable !== 'undefined') {
                Sortable.create(blocksList, {
                    animation: 150,
                    delay: 100,
                    delayOnTouchOnly: true,
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

            // Note: Upload asset is handled in frontend.js to avoid duplicate event binding
        },

        initSidebar: function() {
            var $body   = $('body');
            var $toggle = $('#agency-sidebar-toggle');

            if (!$toggle.length) return;

            // Restaurer l'état depuis localStorage (défaut : ouvert)
            var isOpen = localStorage.getItem('agency_sidebar_state') !== 'closed';
            $body.addClass(isOpen ? 'agency-sidebar-open' : 'agency-sidebar-closed');

            $toggle.on('click', function() {
                var open = $body.hasClass('agency-sidebar-open');
                $body
                    .toggleClass('agency-sidebar-open', !open)
                    .toggleClass('agency-sidebar-closed', open);
                localStorage.setItem('agency_sidebar_state', open ? 'closed' : 'open');
                $toggle.attr('aria-label', open ? __('Expand menu', 'agency-client-portal') : __('Collapse menu', 'agency-client-portal'));
            });
        },

        initSidebarProjects: function() {
            var STORAGE_KEY = 'agency_sidebar_projects_open';

            function getOpenIds() {
                try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]'); } catch(e) { return []; }
            }

            function saveOpenIds(ids) {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(ids));
            }

            // Restaurer l'état des projets ouverts depuis localStorage
            var openIds = getOpenIds();
            openIds.forEach(function(id) {
                var $group = $('.agency-sidebar-project-group[data-project-id="' + id + '"]');
                if ($group.length) $group.addClass('is-open');
            });

            // Gérer le clic sur les boutons toggle
            $(document).on('click', '.agency-sidebar-project-toggle', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var id = $(this).data('project-id');
                var $group = $('.agency-sidebar-project-group[data-project-id="' + id + '"]');
                $group.toggleClass('is-open');

                var currentOpen = getOpenIds();
                var idx = currentOpen.indexOf(id);
                if ($group.hasClass('is-open')) {
                    if (idx === -1) currentOpen.push(id);
                } else {
                    if (idx !== -1) currentOpen.splice(idx, 1);
                }
                saveOpenIds(currentOpen);
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
            
            $.ajax({
                url: agencyData.rest_url + 'blocks/' + blockId,
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
