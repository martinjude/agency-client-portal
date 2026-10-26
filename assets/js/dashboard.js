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
        $('#agd-primary-color-picker').val('#000000');
        $('#agd-primary-color-text').val('#000000');
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

    /* ---- Sync couleur picker ↔ texte hex ---- */
    $(document).on('input', '#agd-primary-color-picker', function () {
        $('#agd-primary-color-text').val($(this).val());
    });
    $(document).on('input', '#agd-primary-color-text', function () {
        var val = $(this).val();
        if (/^#[0-9a-fA-F]{6}$/.test(val)) {
            $('#agd-primary-color-picker').val(val);
        }
    });

    /* ---- Media uploader logo ---- */
    $(document).on('click', '#agd-logo-btn', function (e) {
        e.preventDefault();
        if (!logoFrame) {
            logoFrame = wp.media({
                title: 'Choisir un logo',
                button: { text: 'Utiliser ce logo' },
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
            $('#agd-modal-error').text('Le nom du client est obligatoire.').show();
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text(__('Creating…', 'agency-client-portal'));
        $('#agd-modal-error').hide();

        var payload = {
            name:          name,
            logo_url:      $('#agd-logo-url').val() || '',
            primary_color: $('#agd-primary-color-text').val() || '#000000'
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

    /* ---- Copier le lien d'accès client ---- */
    $(document).on('click', '.agd-copy-link', function () {
        var clientId = $(this).data('client-id');
        var $btn     = $(this);
        $btn.prop('disabled', true).text('…');

        $.post(ajaxUrl, {
            action:    'agency_get_access_url',
            client_id: clientId,
            nonce:     ajaxNonce
        }, function (resp) {
            if (resp.success && resp.data && resp.data.access_url) {
                var url = resp.data.access_url;
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(url).then(function () {
                        toast(__('Link copied to clipboard!', 'agency-client-portal'));
                    }).catch(function () {
                        fallbackCopy(url);
                    });
                } else {
                    fallbackCopy(url);
                }
            } else if (resp.data && resp.data.code === 'token_plaintext_expired') {
                toast(__('Legacy token — go to Edit client to regenerate it.', 'agency-client-portal'), true);
            } else {
                toast(__('Unable to retrieve the access link.', 'agency-client-portal'), true);
            }
        }).fail(function () {
            toast(__('Server error while retrieving the link.', 'agency-client-portal'), true);
        }).always(function () {
            $btn.prop('disabled', false).text('Copier le lien');
        });
    });

    function fallbackCopy(text) {
        var $tmp = $('<input>').val(text).appendTo('body');
        $tmp[0].select();
        try {
            document.execCommand('copy');
            toast(__('Link copied to clipboard!', 'agency-client-portal'));
        } catch (e) {
            toast('Copiez manuellement : ' + text, true);
        }
        $tmp.remove();
    }

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
