(function ($) {
    'use strict';

    var __ = wp.i18n.__;

    // Variables initialisées dans document.ready (garantit le chargement complet des scripts)
    var restUrl, restNonce, ajaxUrl, ajaxNonce, newClientModal, logoFrame;

    /* ---- Toast : délégué au composant unifié AgencyUI.toast (ui.js) ---- */
    function toast(msg, isError) {
        AgencyUI.toast(msg, isError ? { type: 'error' } : {});
    }

    /* ---- Modal nouveau client ---- */
    function openNewClientModal() {
        $('#agd-client-name').val('');
        $('#agd-logo-url').val('');
        $('#agd-logo-preview').hide().attr('src', '');
        AgencyUI.setAccent(document.querySelector('[data-accent-field="agd-primary-color"]'), '#7C83FD');
        $('#agd-modal-error').hide().text('');
        $('#agd-modal-save').prop('disabled', false).text(__('Create client', 'agency-client-portal'));

        if (newClientModal) {
            newClientModal.open();
        } else {
            $('#agd-new-client-modal').addClass('active');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeNewClientModal() {
        if (newClientModal) {
            newClientModal.close();
        } else {
            $('#agd-new-client-modal').removeClass('active');
            document.body.style.overflow = '';
        }
    }

    /* ---- Handlers (event delegation sur document — indépendants du DOM au chargement) ---- */
    $(document).on('click', '#agd-new-client-btn', openNewClientModal);
    $(document).on('click', '#agd-new-client-cta', openNewClientModal);
    $(document).on('click', '#agd-modal-cancel, #agd-modal-close', closeNewClientModal);

    // Fermer en cliquant hors du contenu
    $(document).on('click', '#agd-new-client-modal', function (e) {
        if ($(e.target).is('#agd-new-client-modal')) {
            closeNewClientModal();
        }
    });

    /* ---- Recherche et filtre de statut des clients ---- */
    var statusFilter = 'all';
    function filterClients() {
        var term  = ($('#agd-client-search').val() || '').toLowerCase().trim();
        var shown = 0;
        $('#agd-clients-grid .agency-client-card').each(function () {
            var $card = $(this);
            var ok = (term === '' || String($card.data('client-name')).toLowerCase().indexOf(term) !== -1)
                && (statusFilter === 'all' || $card.data('client-status') === statusFilter);
            $card.toggle(ok);
            if (ok) { shown++; }
        });
        $('#agd-no-results').prop('hidden', shown > 0);
    }
    $(document).on('input', '#agd-client-search', filterClients);
    $(document).on('click', '#agd-status-filter .agency-label-filter', function () {
        statusFilter = $(this).data('status');
        $('#agd-status-filter .agency-label-filter').removeClass('active');
        $(this).addClass('active');
        filterClients();
    });

    /* ---- Media uploader logo ---- */
    $(document).on('click', '#agd-logo-btn', function (e) {
        e.preventDefault();
        if (!logoFrame) {
            logoFrame = wp.media({
                title: __('Choose a logo', 'agency-client-portal'),
                button: { text: __('Use this logo', 'agency-client-portal') },
                multiple: false
            });
            logoFrame.on('select', function () {
                var url = logoFrame.state().get('selection').first().toJSON().url;
                $('#agd-logo-url').val(url);
                $('#agd-logo-preview').attr('src', url).show();
            });
        }
        logoFrame.open();
    });

    /* ---- Créer un client (REST POST) ---- */
    $(document).on('click', '#agd-modal-save', function () {
        var name = $.trim($('#agd-client-name').val());
        if (!name) {
            $('#agd-modal-error').text(__('The client name is required.', 'agency-client-portal')).show();
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text(__('Creating…', 'agency-client-portal'));
        $('#agd-modal-error').hide();

        var payload = {
            name:          name,
            logo_url:      $('#agd-logo-url').val() || '',
            primary_color: $('#agd-primary-color-text').val() || '#7C83FD'
        };

        $.ajax({
            url:         restUrl + 'clients',
            method:      'POST',
            contentType: 'application/json',
            data:        JSON.stringify(payload),
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', restNonce);
            },
            success: function (resp) {
                if (resp && resp.id) {
                    closeNewClientModal();
                    toast(__('Client created successfully!', 'agency-client-portal'));
                    setTimeout(function () {
                        window.location.href = window.location.origin + '/agency/edit-client/' + resp.id + '/';
                    }, 700);
                } else {
                    $('#agd-modal-error').text(__('Error while creating the client.', 'agency-client-portal')).show();
                    $btn.prop('disabled', false).text(__('Create client', 'agency-client-portal'));
                }
            },
            error: function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message)
                    ? xhr.responseJSON.message
                    : __('Server error. Check the logs.', 'agency-client-portal');
                $('#agd-modal-error').text(msg).show();
                $btn.prop('disabled', false).text(__('Create client', 'agency-client-portal'));
            }
        });
    });

    /* Copie du lien d'accès : gérée par manager.js (.agency-copy-access) */

    /* ---- Initialisation au chargement du DOM ---- */
    $(document).ready(function () {
        // Récupérer les variables depuis agencyData (localisé par PHP)
        restUrl   = agencyData.rest_url;
        restNonce = agencyData.nonce;
        ajaxUrl   = agencyData.ajax_url;
        ajaxNonce = agencyData.admin_nonce;

        // Init modal avec AgencyModal si disponible
        if (typeof AgencyModal !== 'undefined') {
            newClientModal = new AgencyModal('agd-new-client-modal');
        }

        // Auto-ouvrir le modal si ?action=new-client (redirect depuis admin)
        var urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('action') === 'new-client') {
            openNewClientModal();
            if (window.history && window.history.replaceState) {
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        }
    });

})(jQuery);
