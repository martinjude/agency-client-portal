/**
 * Agency Admin JavaScript
 */

(function($) {
    'use strict';

    var __ = wp.i18n.__;
    
    const AgencyAdmin = {
        
        init: function() {
            this.bindEvents();
            this.initColorPickers();
        },
        
        bindEvents: function() {
            // Copier l'URL d'accès
            $('.agency-copy-btn').on('click', this.copyAccessUrl.bind(this));

            // Preview couleurs
            $('input[type="color"]').on('change', this.updateColorPreview.bind(this));

            // Hard delete client
            $('#agency-hard-delete-client').on('click', this.hardDeleteClient.bind(this));
        },
        
        initColorPickers: function() {
            // Initialiser les color pickers
            $('input[type="color"]').each(function() {
                const $input = $(this);
                const $preview = $input.siblings('.agency-color-preview');
                
                if ($preview.length) {
                    $preview.css('background-color', $input.val());
                }
            });
        },
        
        copyAccessUrl: function(e) {
            e.preventDefault();
            
            const $btn = $(e.currentTarget);
            const url = $btn.data('url');
            
            if (!url) return;
            
            // Créer un élément temporaire
            const $temp = $('<input>');
            $('body').append($temp);
            $temp.val(url).select();
            document.execCommand('copy');
            $temp.remove();
            
            // Feedback visuel
            const originalText = $btn.text();
            $btn.text(__('✓ Copied!', 'agency-client-portal'));
            
            setTimeout(function() {
                $btn.text(originalText);
            }, 2000);
        },
        
        updateColorPreview: function(e) {
            const $input = $(e.currentTarget);
            const color = $input.val();
            const $preview = $input.siblings('.agency-color-preview');

            if ($preview.length) {
                $preview.css('background-color', color);
            }
        },

        hardDeleteClient: function(e) {
            e.preventDefault();
            var clientId = $(e.currentTarget).data('client-id');
            if (!clientId) return;

            if (!confirm(__('WARNING: This action cannot be undone.\n\nIt permanently deletes:\n- The client\n- All of its projects\n- All blocks and comments\n- All uploaded files\n\nContinue?', 'agency-client-portal'))) {
                return;
            }

            var $btn = $(e.currentTarget);
            $btn.prop('disabled', true).text('Suppression...');

            $.post(agencyAdmin.ajax_url, {
                action: 'agency_hard_delete_client',
                client_id: clientId,
                nonce: agencyAdmin.nonce
            }, function(response) {
                if (response.success) {
                    window.location.href = response.data.redirect;
                } else {
                    alert(response.data || 'Erreur lors de la suppression.');
                    $btn.prop('disabled', false).text('Supprimer definitivement le client');
                }
            }).fail(function() {
                alert('Erreur de communication avec le serveur.');
                $btn.prop('disabled', false).text('Supprimer definitivement le client');
            });
        }
    };
    
    $(document).ready(function() {
        AgencyAdmin.init();
    });

    // ===== Aperçu live du modèle d'email (onglet Emails) =====
    $(document).ready(function() {
        var $textarea = $('#email_template_html');
        var $preview = $('#agency-email-preview');
        if (!$textarea.length || !$preview.length) {
            return;
        }

        // Données d'exemple pour le rendu de l'aperçu.
        var sample = {
            subject: __('New comment on your project', 'agency-client-portal'),
            greeting: __('Hello Marie,', 'agency-client-portal'),
            message: '<p>' + __('Here is a preview of your email\'s content.', 'agency-client-portal') + '</p>',
            button_url: '#',
            button_label: __('View project', 'agency-client-portal'),
            recipient_name: 'Marie',
            commenter_name: 'Jean Dupont',
            comment_content: __('Great work, I love the new version!', 'agency-client-portal'),
            project_name: __('Summer 2026 Campaign', 'agency-client-portal'),
            client_name: 'Acme Studio',
            site_name: __('My Agency', 'agency-client-portal'),
            logo_url: '',
            site_url: '#',
            year: new Date().getFullYear()
        };

        function renderEmailPreview() {
            var html = $textarea.val().replace(/\{\{\s*([a-z0-9_]+)\s*\}\}/gi, function(match, key) {
                key = key.toLowerCase();
                return (sample[key] !== undefined) ? sample[key] : '';
            });
            $preview.get(0).srcdoc = html;
        }

        $textarea.on('input', renderEmailPreview);

        $('#agency-email-reset').on('click', function(e) {
            e.preventDefault();
            var def = $('#agency-email-default-template').val();
            if (def) {
                $textarea.val(def);
                renderEmailPreview();
            }
        });

        renderEmailPreview();
    });

})(jQuery);
