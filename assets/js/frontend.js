/**
 * Agency Frontend JavaScript
 */

(function($) {
    'use strict';

    var __ = wp.i18n.__;
    
    const Agency = {
        
        blockResizing: false, // Flag to disable dragging while resizing
        
        init: function() {
            this.bindEvents();
            this.initComments();
            this.initStoryboardSortable();
            this.initBlocksGrid();
            this.initClientTitleEditor();
            this.initLazyBackgrounds();
            this.initCodeCopy();
            this.initMobileHeader();
        },

        initMobileHeader: function() {
            const hamburger = document.getElementById('agency-hamburger');
            if (!hamburger) return;

            const drawer = document.querySelector('.agency-header-right');
            if (!drawer) return;

            const close = () => {
                drawer.classList.remove('is-open');
                hamburger.classList.remove('is-active');
                hamburger.setAttribute('aria-expanded', 'false');
                document.body.classList.remove('agency-header-drawer-open');
            };
            const open = () => {
                drawer.classList.add('is-open');
                hamburger.classList.add('is-active');
                hamburger.setAttribute('aria-expanded', 'true');
                document.body.classList.add('agency-header-drawer-open');
            };
            const toggle = () => {
                drawer.classList.contains('is-open') ? close() : open();
            };

            hamburger.setAttribute('aria-expanded', 'false');
            hamburger.setAttribute('aria-controls', 'agency-header-drawer');
            drawer.id = drawer.id || 'agency-header-drawer';

            hamburger.addEventListener('click', (e) => {
                e.stopPropagation();
                toggle();
            });

            // Fermer sur Escape
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && drawer.classList.contains('is-open')) {
                    close();
                    hamburger.focus();
                }
            });

            // Fermer au clic hors drawer
            document.addEventListener('click', (e) => {
                if (!drawer.classList.contains('is-open')) return;
                if (drawer.contains(e.target) || hamburger.contains(e.target)) return;
                close();
            });

            // Fermer au clic sur un lien interne au drawer
            drawer.addEventListener('click', (e) => {
                const link = e.target.closest('a');
                if (link && drawer.classList.contains('is-open')) {
                    close();
                }
            });

            // Si le viewport repasse en desktop, réinitialiser
            const mql = window.matchMedia('(min-width: 769px)');
            const handleResize = (m) => { if (m.matches) close(); };
            if (mql.addEventListener) mql.addEventListener('change', handleResize);
            else mql.addListener(handleResize);
        },

        initCodeCopy: function() {
            const buttons = document.querySelectorAll('.agency-code-copy-btn[data-code-b64]');
            if (!buttons.length) return;

            const decode = (b64) => {
                try {
                    return decodeURIComponent(escape(window.atob(b64)));
                } catch (e) {
                    try { return window.atob(b64); } catch (_) { return ''; }
                }
            };

            const fallbackCopy = (text) => {
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.setAttribute('readonly', '');
                ta.style.position = 'fixed';
                ta.style.top = '-1000px';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                let ok = false;
                try { ok = document.execCommand('copy'); } catch (_) {}
                document.body.removeChild(ta);
                return ok;
            };

            buttons.forEach((btn) => {
                if (btn.dataset.copyBound === '1') return;
                btn.dataset.copyBound = '1';

                btn.addEventListener('click', async function() {
                    const label = btn.querySelector('.copy-btn-label');
                    const code = decode(btn.getAttribute('data-code-b64') || '');
                    const labelCopied = btn.getAttribute('data-label-copied') || 'Copied!';
                    const labelCopy = btn.getAttribute('data-label-copy') || 'Copy';
                    const labelError = btn.getAttribute('data-label-error') || 'Error';

                    btn.classList.remove('copied', 'copy-error');

                    let success = false;
                    if (navigator.clipboard && window.isSecureContext) {
                        try {
                            await navigator.clipboard.writeText(code);
                            success = true;
                        } catch (_) {
                            success = fallbackCopy(code);
                        }
                    } else {
                        success = fallbackCopy(code);
                    }

                    if (success) {
                        btn.classList.add('copied');
                        if (label) label.textContent = labelCopied;
                    } else {
                        btn.classList.add('copy-error');
                        if (label) label.textContent = labelError;
                    }

                    clearTimeout(btn._copyResetTimer);
                    btn._copyResetTimer = setTimeout(() => {
                        btn.classList.remove('copied', 'copy-error');
                        if (label) label.textContent = labelCopy;
                    }, 2000);
                });
            });
        },
        
        initBlocksGrid: function() {
            // Only initialize if user can manage and Sortable is available
            if (!agencyData.can_manage || typeof Sortable === 'undefined') {
                return;
            }
            
            const blocksList = document.getElementById('blocks-list');
            if (blocksList && blocksList.classList.contains('agency-blocks-grid')) {
                // Initialize drag-and-drop with animation
                Sortable.create(blocksList, {
                    animation: 150,
                    delay: 100,
                    delayOnTouchOnly: true,
                    onMove: (evt) => {
                        // Only cancel drag if we're actively resizing
                        if (this.blockResizing) {
                            // Cancel the drag operation to prevent conflicts
                            if (evt.dragged) {
                                evt.cancel();
                            }
                            return false;
                        }
                        return true;
                    },
                    onEnd: this.handleBlockReorder.bind(this)
                });
                
                // Initialize resize functionality
                this.initBlockResize();
            }
        },
        
        initStoryboardSortable: function() {
            // Only initialize if user can manage and Sortable is available
            if (!agencyData.can_manage || typeof Sortable === 'undefined') {
                return;
            }
            
            const self = this;
            const storyboardItems = document.getElementById('storyboard-items');
            if (storyboardItems && storyboardItems.classList.contains('sortable')) {
                Sortable.create(storyboardItems, {
                    animation: 150,
                    // Poignée dédiée : la slide entière contenait la textarea voix-off,
                    // et chaque ré-insertion du nœud pendant le drag déclenchait un blur
                    // → rafale de PUT identiques (ban WAF). Le drag ne démarre plus que
                    // depuis la poignée.
                    handle: '.storyboard-drag-handle',
                    onStart: function() { self.storyboardDragging = true; },
                    onEnd: this.handleAssetReorder.bind(this)
                });
            }
        },
        
        initBlockResize: function() {
            const self = this;
            const blocksList = document.getElementById('blocks-list');

            if (!blocksList) return;

            blocksList.querySelectorAll('.agency-block').forEach(block => {
                const resizeIcon = block.querySelector('.block-resize-icon');

                if (resizeIcon) {
                    resizeIcon.addEventListener('mousedown', (e) => {
                        self.startResize(e, block);
                    });
                }
            });
        },

        startResize: function(e, block) {
            e.preventDefault();
            e.stopPropagation();

            this.blockResizing = true;
            block.classList.add('is-resizing');

            const gridContainer = block.parentElement;

            // Get current grid dimensions
            const currentWidth = parseInt(block.getAttribute('data-grid-width')) || 1;
            const currentHeight = parseInt(block.getAttribute('data-grid-height')) || 1;

            // Get grid cell size
            const computedStyle = window.getComputedStyle(gridContainer);
            const gridTemplate = computedStyle.gridTemplateColumns;
            const cells = gridTemplate.split(' ').length;
            const gap = parseInt(computedStyle.gap) || 20;
            const cellWidth = (gridContainer.offsetWidth - gap * (cells - 1)) / cells;
            
            // Fixed reference height for predictable resizing (300px min-height + gap)
            const BASE_ROW_HEIGHT = 300;
            const cellHeight = BASE_ROW_HEIGHT + gap;

            const startX = e.clientX;
            const startY = e.clientY;

            // Create resize tooltip
            let tooltip = document.createElement('div');
            tooltip.className = 'agency-resize-tooltip';
            tooltip.textContent = `${currentWidth} × ${currentHeight}`;
            document.body.appendChild(tooltip);

            const updateTooltipPosition = (x, y) => {
                tooltip.style.left = (x + 15) + 'px';
                tooltip.style.top = (y + 15) + 'px';
            };
            updateTooltipPosition(startX, startY);

            const onMouseMove = (moveEvent) => {
                const deltaX = moveEvent.clientX - startX;
                const deltaY = moveEvent.clientY - startY;

                // Calculate new width (expanding to the right)
                const cellsToAdd = Math.round(deltaX / cellWidth);
                const newWidth = Math.max(1, Math.min(3, currentWidth + cellsToAdd));

                // Calculate new height (expanding downward)
                const rowsToAdd = Math.round(deltaY / cellHeight);
                const newHeight = Math.max(1, Math.min(2, currentHeight + rowsToAdd));

                // Update block styles
                block.style.gridColumn = `span ${newWidth}`;
                block.style.gridRow = `span ${newHeight}`;
                block.style.setProperty('--agency-grid-height', newHeight);
                block.setAttribute('data-grid-width', newWidth);
                block.setAttribute('data-grid-height', newHeight);

                // Update tooltip
                tooltip.textContent = `${newWidth} × ${newHeight}`;
                updateTooltipPosition(moveEvent.clientX, moveEvent.clientY);
            };

            const onMouseUp = () => {
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);

                block.classList.remove('is-resizing');
                this.blockResizing = false;

                // Remove tooltip
                if (tooltip && tooltip.parentNode) {
                    tooltip.parentNode.removeChild(tooltip);
                }

                // Save new dimensions
                const blockId = block.getAttribute('data-block-id');
                const newWidth = parseInt(block.getAttribute('data-grid-width'));
                const newHeight = parseInt(block.getAttribute('data-grid-height'));

                this.saveBlockResize(blockId, newWidth, newHeight);
            };

            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        },
        
        saveBlockResize: function(blockId, gridWidth, gridHeight) {
            $.ajax({
                url: agencyData.rest_url + 'blocks/' + blockId,
                method: 'PUT',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', agencyData.nonce);
                },
                data: JSON.stringify({
                    grid_width: gridWidth,
                    grid_height: gridHeight
                }),
                contentType: 'application/json',
                success: function(response) {
                    if (response.success) {
                        console.log('Block resized successfully');
                    }
                },
                error: function(xhr) {
                    console.error('Failed to save block resize:', xhr);
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
        
        bindEvents: function() {
            // Supprimer commentaire
            $(document).on('click', '.delete-comment', this.deleteComment.bind(this));

            // Block-view pages (storyboard, video, script, code, link) ont leurs propres
            // handlers inline — on n'attache ces events que sur les pages hors block-view
            // pour éviter le double déclenchement.
            const isBlockView = !!document.querySelector('.agency-block-view');
            if (!isBlockView) {
                // Nouveau commentaire
                $('#new-comment-form').on('submit', this.submitComment.bind(this));

                // Supprimer asset
                $(document).on('click', '.delete-asset', this.deleteAsset.bind(this));

                // Script editor - save button
                $('#save-script-btn').on('click', this.saveScript.bind(this));

                // Asset comment forms
                $(document).on('submit', '.new-asset-comment-form', this.submitAssetComment.bind(this));

                // Upload asset button
                $('#upload-asset-btn').on('click', this.uploadAsset.bind(this));
            }

            // Sauvegarde voix-off (clic bouton) — nécessaire sur toutes les pages
            $(document).on('click', '.save-voiceover-btn', this.saveVoiceover.bind(this));

            // Mémorise la valeur au focus : permet d'ignorer les blur sans modification
            $(document).on('focusin', '.asset-voiceover-input', function() {
                const $t = $(this);
                $t.data('agencyOriginal', $t.val());
            });

            // Auto-save au blur : garde anti-drag + dirty check + debounce.
            // Sans ces gardes, SortableJS ré-insère le nœud déplacé dans le DOM et
            // déclenche des blur en série → rafale de requêtes identiques.
            const self = this;
            $(document).on('blur', '.asset-voiceover-input', function() {
                if (!agencyData.can_manage || self.storyboardDragging) {
                    return;
                }
                const $t = $(this);
                if ($t.val() === $t.data('agencyOriginal')) {
                    return; // Rien n'a changé, pas de requête
                }
                const assetId = $t.data('asset-id');
                if (!assetId) {
                    return;
                }
                let debounced = $t.data('agencySaveDebounced');
                if (!debounced) {
                    debounced = AgencyUI.debounce(function() {
                        self.doSaveVoiceover(assetId, null);
                    }, 800);
                    $t.data('agencySaveDebounced', debounced);
                }
                debounced();
            });
        },
        
        submitComment: function(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const content = $('#comment-content').val();
            const blockId = this.getCurrentBlockId();
            
            if (!content || !blockId) {
                return;
            }
            
            $.ajax({
                url: agencyData.rest_url + 'comments',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', agencyData.nonce);
                },
                data: JSON.stringify({
                    block_id: blockId,
                    content: content
                }),
                contentType: 'application/json',
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

        saveVoiceover: function(e) {
            // Clic sur le bouton "Enregistrer la voix-off" : sauvegarde explicite
            const $btn = $(e.currentTarget);
            const assetId = $btn.data('asset-id');
            if (!assetId || !agencyData.can_manage) return;
            this.doSaveVoiceover(assetId, $btn);
        },

        doSaveVoiceover: function(assetId, $btn) {
            const self = this;
            const $textarea = $('#voiceover-' + assetId);
            if (!$textarea.length || !agencyData.can_manage) return;

            // Une seule requête en vol par asset : ignore les déclenchements en rafale
            this.voiceoverSaving = this.voiceoverSaving || {};
            if (this.voiceoverSaving[assetId]) return;
            this.voiceoverSaving[assetId] = true;

            const content = $textarea.val();
            const isButton = !!($btn && $btn.length);
            const btnLabel = isButton ? $btn.text() : '';
            const $status = $('.voiceover-save-status[data-asset-id="' + assetId + '"]');
            if (isButton) {
                $btn.prop('disabled', true).text('Enregistrement...');
            }
            if ($status.length) { $status.text(''); $status.removeClass('success error'); }
            $.ajax({
                url: agencyData.rest_url + 'assets/' + assetId,
                method: 'PUT',
                beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', agencyData.nonce); },
                data: JSON.stringify({ voiceover: content }),
                contentType: 'application/json',
                success: function(response) {
                    // Nouvelle valeur de référence pour le dirty check au blur
                    $textarea.data('agencyOriginal', content);
                    if ($status.length) { $status.text('✓'); $status.addClass('success'); setTimeout(function(){ $status.text(''); $status.removeClass('success'); }, 2000); }
                },
                error: function() {
                    if ($status.length) { $status.text('✗'); $status.addClass('error'); }
                    AgencyUI.toast(agencyData.strings.error, { type: 'error' });
                },
                complete: function() {
                    self.voiceoverSaving[assetId] = false;
                    if (isButton) { $btn.prop('disabled', false).text(btnLabel); }
                }
            });
        },
        
        deleteComment: function(e) {
            e.preventDefault();
            
            if (!confirm(agencyData.strings.confirm_delete)) {
                return;
            }
            
            const commentId = $(e.currentTarget).data('comment-id');
            
            $.ajax({
                url: agencyData.rest_url + 'comments/' + commentId,
                method: 'DELETE',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', agencyData.nonce);
                },
                success: function(response) {
                    if (response.success) {
                        $('[data-comment-id="' + commentId + '"]').fadeOut(function() {
                            $(this).remove();
                        });
                    }
                },
                error: function() {
                    AgencyUI.toast(agencyData.strings.error, { type: 'error' });
                }
            });
        },
        
        deleteAsset: function(e) {
            e.preventDefault();
            
            if (!confirm(agencyData.strings.confirm_delete)) {
                return;
            }
            
            const assetId = $(e.currentTarget).data('asset-id');
            
            $.ajax({
                url: agencyData.rest_url + 'assets/' + assetId,
                method: 'DELETE',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', agencyData.nonce);
                },
                success: function(response) {
                    if (response.success) {
                        $('[data-asset-id="' + assetId + '"]').fadeOut(function() {
                            $(this).remove();
                        });
                    }
                },
                error: function() {
                    AgencyUI.toast(agencyData.strings.error, { type: 'error' });
                }
            });
        },
        
        getCurrentBlockId: function() {
            const url = window.location.pathname;
            const match = url.match(/\/block\/(\d+)/);
            return match ? match[1] : null;
        },
        
        initComments: function() {
            // Auto-expand textarea
            $('textarea').on('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
        },

        initClientTitleEditor: function() {
            const $title = $('#agency-client-title');
            const $status = $('#client-title-status');

            if (!$title.length) {
                return;
            }

            const clientId = $title.data('client-id');
            let original = $title.text().trim();

            // Prevent new lines in contenteditable
            $title.on('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                }
            });

            const saveTitle = () => {
                const value = $title.text().trim();
                if (!value || value === original) {
                    return;
                }

                $title.addClass('is-saving');
                $status.removeClass('success error').text('');

                $.ajax({
                    url: agencyData.rest_url + 'clients/' + clientId,
                    method: 'PUT',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', agencyData.nonce);
                    },
                    data: JSON.stringify({
                        client_view_title: value
                    }),
                    contentType: 'application/json',
                    success: function(response) {
                        if (response.success) {
                            original = value;
                            $status.text(__('✓ Saved', 'agency-client-portal')).addClass('success');
                        } else {
                            $status.text(__('✗ Error', 'agency-client-portal')).addClass('error');
                        }
                    },
                    error: function() {
                        $status.text(__('✗ Error', 'agency-client-portal')).addClass('error');
                    },
                    complete: function() {
                        $title.removeClass('is-saving');
                        setTimeout(function() {
                            $status.fadeOut(function() {
                                $(this).text('').removeClass('success error').show();
                            });
                        }, 2000);
                    }
                });
            };

            $title.on('blur', function() {
                saveTitle();
            });
        },
        
        // Script Editor Functions
        saveScript: function(e) {
            e.preventDefault();
            
            const $btn = $(e.currentTarget);
            const $status = $('.script-save-status');
            const content = $('#script-content').val();
            const blockId = $btn.closest('.agency-block-view').find('#edit-block-btn').data('block-id');
            
            if (!blockId) {
                console.error('Block ID not found');
                return;
            }
            
            $btn.prop('disabled', true).text('Enregistrement...');
            $status.text('').removeClass('success error');
            
            $.ajax({
                url: agencyData.rest_url + 'blocks/' + blockId,
                method: 'PUT',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', agencyData.nonce);
                },
                data: JSON.stringify({
                    content: content
                }),
                contentType: 'application/json',
                success: function(response) {
                    if (response.success) {
                        $status.text(__('✓ Saved', 'agency-client-portal')).addClass('success');
                        $btn.text('💾 ' + __('Save script', 'agency-client-portal'));
                        setTimeout(function() {
                            $status.fadeOut(function() {
                                $(this).text('').show();
                            });
                        }, 3000);
                    }
                    $btn.prop('disabled', false);
                },
                error: function() {
                    $status.text(__('✗ Error while saving', 'agency-client-portal')).addClass('error');
                    $btn.prop('disabled', false).text('💾 ' + __('Save script', 'agency-client-portal'));
                }
            });
        },
        
        // Storyboard Functions
        submitAssetComment: function(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const $textarea = $form.find('.asset-comment-input');
            const content = $textarea.val().trim();
            const assetId = $form.data('asset-id');
            const blockId = this.getCurrentBlockId();
            
            if (!content || !assetId || !blockId) {
                return;
            }
            
            $.ajax({
                url: agencyData.rest_url + 'comments',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', agencyData.nonce);
                },
                data: JSON.stringify({
                    block_id: blockId,
                    asset_id: assetId,
                    content: content
                }),
                contentType: 'application/json',
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
        
        uploadAsset: function(e) {
            e.preventDefault();
            
            const blockId = this.getCurrentBlockId();
            if (!blockId) {
                AgencyUI.toast('Block ID not found', { type: 'error' });
                return;
            }
            
            // Create file input
            const $fileInput = $('<input type="file" multiple accept="image/*,video/*">');
            
            $fileInput.on('change', function(e) {
                const files = e.target.files;
                if (files.length === 0) return;
                
                const formData = new FormData();
                for (let i = 0; i < files.length; i++) {
                    formData.append('files[]', files[i]);
                }
                formData.append('block_id', blockId);
                
                // Show loading indicator
                $('<div class="upload-progress">' + __('Uploading...', 'agency-client-portal') + '</div>').appendTo('body');
                
                $.ajax({
                    url: agencyData.rest_url + 'assets/upload',
                    method: 'POST',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', agencyData.nonce);
                    },
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        $('.upload-progress').remove();
                        if (response.success) {
                            location.reload();
                        } else {
                            AgencyUI.toast(response.message || agencyData.strings.error, { type: 'error' });
                        }
                    },
                    error: function(xhr) {
                        $('.upload-progress').remove();
                        let errorMsg = agencyData.strings.error;
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        }
                        AgencyUI.toast(errorMsg, { type: 'error' });
                    }
                });
            });
            
            // Trigger file selection
            $fileInput.click();
        },
        
        /**
         * Lazy load background images using IntersectionObserver.
         * Elements with [data-bg-lazy] get their background-image set
         * only when they enter (or are about to enter) the viewport.
         * Can be re-called after dynamic content insertion.
         */
        initLazyBackgrounds: function() {
            var lazyBgElements = document.querySelectorAll('[data-bg-lazy]:not(.bg-loaded)');

            if (!lazyBgElements.length) return;

            if ('IntersectionObserver' in window) {
                var bgObserver = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            var el = entry.target;
                            var bgUrl = el.getAttribute('data-bg-lazy');
                            if (bgUrl) {
                                el.style.backgroundImage = "url('" + bgUrl + "')";
                                el.style.backgroundSize = 'cover';
                                el.style.backgroundPosition = 'center';
                                el.style.backgroundRepeat = 'no-repeat';
                                el.classList.add('bg-loaded');
                            }
                            bgObserver.unobserve(el);
                        }
                    });
                }, {
                    rootMargin: '200px 0px'
                });

                lazyBgElements.forEach(function(el) {
                    bgObserver.observe(el);
                });
            } else {
                // Fallback: load all immediately
                lazyBgElements.forEach(function(el) {
                    var bgUrl = el.getAttribute('data-bg-lazy');
                    if (bgUrl) {
                        el.style.backgroundImage = "url('" + bgUrl + "')";
                        el.style.backgroundSize = 'cover';
                        el.style.backgroundPosition = 'center';
                        el.style.backgroundRepeat = 'no-repeat';
                        el.classList.add('bg-loaded');
                    }
                });
            }
        },

        handleAssetReorder: function(evt) {
            this.storyboardDragging = false;
            const assetIds = [];
            $('#storyboard-items .storyboard-item').each(function() {
                assetIds.push($(this).data('asset-id'));
            });
            
            $.ajax({
                url: agencyData.rest_url + 'assets/reorder',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', agencyData.nonce);
                },
                data: JSON.stringify({
                    asset_ids: assetIds
                }),
                contentType: 'application/json',
                success: function(response) {
                    if (response.success) {
                        // Order saved successfully
                        console.log('Asset order saved');
                    }
                },
                error: function() {
                    console.error('Failed to save asset order');
                }
            });
        }
    };
    
    // Handle copy block link functionality
    $(document).on('click', '.copy-block-link-btn', function(e) {
        e.preventDefault();

        var button = $(this);
        var blockId = button.data('block-id');
        var clientId = button.data('client-id');
        var clientSlug = button.data('client-slug');
        var projectSlug = button.data('project-slug');
        
        // Build the internal path to redirect after auth
        var blockPath = '/client/' + clientSlug + '/project/' + projectSlug + '/block/' + blockId;
        
        // Helper to copy text and show feedback
        function copyLink(text) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(function() {
                    showCopyFeedback(button, true);
                }).catch(function() {
                    fallbackCopyTextToClipboard(text, button);
                });
            } else {
                fallbackCopyTextToClipboard(text, button);
            }
        }
        
        // Managers get a direct link (they already have access)
        if (agencyData && agencyData.can_manage) {
            // For managers, get token URL via AJAX
            $.post(agencyData.ajax_url, {
                action: 'agency_get_access_url',
                client_id: clientId,
                nonce: agencyData.admin_nonce
            }).done(function(response) {
                // Check if response is valid JSON and contains expected data
                if (response && typeof response === 'object' && response.success && response.data && response.data.access_url) {
                    // Append redirect query param (URL-encoded)
                    var tokenUrl = response.data.access_url;
                    var link = tokenUrl + '&redirect=' + encodeURIComponent(blockPath);
                    copyLink(link);
                } else {
                    // Fallback to clean URL if AJAX fails or returns unexpected data
                    console.warn('Unexpected response from AJAX call:', response);
                    copyLink(window.location.origin + blockPath);
                }
            }).fail(function(xhr, status, error) {
                // Fallback to clean URL on AJAX error
                console.error('AJAX error:', status, error);
                copyLink(window.location.origin + blockPath);
            });
            return;
        }
        
        // For clients (non-managers), copy clean URL
        var link = window.location.origin + blockPath;
        copyLink(link);
    });

    // ===== Notifier le client (popin manuelle) =====
    var $notifyModal = null;

    function closeNotifyModal() {
        if ($notifyModal) {
            $notifyModal.removeClass('active');
        }
    }

    $(document).on('click', '.notify-client-btn', function(e) {
        e.preventDefault();

        var button = $(this);
        $notifyModal = $('#notify-client-modal');
        if (!$notifyModal.length) {
            return;
        }

        // Pré-remplir.
        $('#notify-client-block-id').val(button.data('block-id'));
        $('#notify-client-email').val(button.data('project-email') || '');
        $('#notify-client-subject').val('');
        $('#notify-client-name').val('');
        $('#notify-client-message').val('');
        $notifyModal.find('.notify-client-feedback').hide().empty().removeClass('is-error is-success');

        $notifyModal.addClass('active');
    });

    $(document).on('click', '#notify-client-modal .modal-cancel, #notify-client-modal .agency-modal-close', function(e) {
        e.preventDefault();
        closeNotifyModal();
    });

    $(document).on('click', '#notify-client-modal', function(e) {
        if (e.target === this) {
            closeNotifyModal();
        }
    });

    $(document).on('click', '#notify-client-send', function(e) {
        e.preventDefault();

        var $btn = $(this);
        var $modal = $('#notify-client-modal');
        var $feedback = $modal.find('.notify-client-feedback');

        var blockId = $('#notify-client-block-id').val();
        var email = $('#notify-client-email').val().trim();
        var name = $('#notify-client-name').val().trim();
        var subject = $('#notify-client-subject').val().trim();
        var message = $('#notify-client-message').val().trim();

        if (!email) {
            $feedback.removeClass('is-success').addClass('is-error')
                .text(__('Please enter the client\'s email address.', 'agency-client-portal')).show();
            return;
        }
        if (!message) {
            $feedback.removeClass('is-success').addClass('is-error')
                .text('Veuillez saisir un message.').show();
            return;
        }

        $btn.prop('disabled', true).text('Envoi...');
        $feedback.hide();

        $.ajax({
            url: agencyData.rest_url + 'blocks/' + blockId + '/notify',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', agencyData.nonce);
            },
            data: JSON.stringify({
                email: email,
                name: name,
                subject: subject,
                message: message
            }),
            contentType: 'application/json'
        }).done(function(response) {
            if (response && response.success) {
                $feedback.removeClass('is-error').addClass('is-success')
                    .text(response.message || __('Email sent to the client.', 'agency-client-portal')).show();
                setTimeout(closeNotifyModal, 1500);
            } else {
                $feedback.removeClass('is-success').addClass('is-error')
                    .text((response && response.message) ? response.message : __('Sending failed.', 'agency-client-portal')).show();
            }
        }).fail(function(xhr) {
            var msg = __('Sending failed.', 'agency-client-portal');
            if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            }
            $feedback.removeClass('is-success').addClass('is-error').text(msg).show();
        }).always(function() {
            $btn.prop('disabled', false).text('Envoyer');
        });
    });

    $(document).ready(function() {
        Agency.init();
    });

    // Expose Agency for dynamic content re-initialization
    window.Agency = Agency;
    
    // Global token logout function
    window.agency_token_logout = function() {
        if (!confirm(__('Are you sure you want to close this session?', 'agency-client-portal'))) {
            return false;
        }
        
        $.ajax({
            url: agencyData.ajax_url,
            method: 'POST',
            data: {
                action: 'agency_token_logout',
                nonce: agencyData.logout_nonce || ''
            },
            success: function(response) {
                if (response.success) {
                    // Redirect to home or a thank you page
                    window.location.href = '/';
                } else {
                    AgencyUI.toast(__('Error while logging out', 'agency-client-portal'), { type: 'error' });
                }
            },
            error: function() {
                // Even if error, redirect to home
                window.location.href = '/';
            }
        });
        
        return false;
    };
    

    // Fallback function for older browsers or HTTP contexts
    function fallbackCopyTextToClipboard(text, button) {
        var textArea = document.createElement("textarea");
        textArea.value = text;
        
        // Avoid scrolling to bottom
        textArea.style.top = "0";
        textArea.style.left = "0";
        textArea.style.position = "fixed";
        textArea.style.opacity = "0";
        
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            var successful = document.execCommand('copy');
            if (successful) {
                console.log('Fallback copy successful');
                showCopyFeedback(button, true);
            } else {
                console.error('Fallback copy failed');
                showCopyFeedback(button, false);
            }
        } catch (err) {
            console.error('Fallback copy failed: ', err);
            showCopyFeedback(button, false);
        }
        
        document.body.removeChild(textArea);
    }
    
    // Show copy feedback
    function showCopyFeedback(button, success) {
        var originalText = button.html();
        if (success) {
            button.html('<span style="color:green;">' + __('✓ Copied!', 'agency-client-portal') + '</span>');
        } else {
            button.html('<span style="color:red;">' + __('Error', 'agency-client-portal') + '</span>');
        }
        
        // Reset button after 2 seconds
        setTimeout(function() {
            button.html(originalText);
        }, 2000);
    }
    
})(jQuery);
