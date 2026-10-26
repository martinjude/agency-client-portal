(function ($) {
    'use strict';

    var __ = wp.i18n.__;

    if (typeof agencyEditClient === 'undefined') { return; }

    var clientId    = agencyEditClient.client_id;
    var restUrl     = agencyData.rest_url;
    var restNonce   = agencyData.nonce;
    var ajaxUrl     = agencyAdmin.ajax_url;
    var ajaxNonce   = agencyAdmin.nonce;

    /* ---- Toast : délégué au composant unifié AgencyUI.toast (ui.js) ---- */
    function toast(msg, isError) {
        AgencyUI.toast(msg, isError ? { type: 'error' } : {});
    }

    /* ---- Slug helpers ---- */
    function slugify(str) {
        return str.toLowerCase()
            .normalize('NFD').replace(/[̀-ͯ]/g, '')
            .replace(/[^a-z0-9\s-]/g, '')
            .trim().replace(/[\s_]+/g, '-')
            .replace(/-+/g, '-');
    }

    /* ---- Live slug + URL preview ---- */
    $('#agec-client-name').on('input', function () {
        var slug = slugify($(this).val());
        $('#agec-slug-display').val(slug);
        var portalUrl = agencyData.rest_url.replace('/wp-json/agency/v1/', '') + '/client/' + slug + '/';
        $('#agec-portal-url').val(portalUrl);
    });

    /* ---- Copy portal URL ---- */
    $(document).on('click', '.agec-copy-portal-url', function () {
        var $input = $('#agec-portal-url');
        $input[0].select();
        document.execCommand('copy');
        toast(__('URL copied!', 'agency-client-portal'));
    });

    /* ---- Copy access link ---- */
    $(document).on('click', '#agec-copy-access-link', function () {
        var $input = $('#agec-access-link-input');
        $input[0].select();
        document.execCommand('copy');
        toast(__('Link copied!', 'agency-client-portal'));
    });

    /* ---- Color inputs sync (hex text ↔ color picker) ---- */
    function bindColorSync(colorInputId, textInputId) {
        var $color = $('#' + colorInputId);
        var $text  = $('#' + textInputId);
        $color.on('input', function () { $text.val($color.val()); });
        $text.on('input', function () {
            var val = $text.val();
            if (/^#[0-9a-fA-F]{6}$/.test(val)) { $color.val(val); }
        });
    }
    bindColorSync('agec-primary-color-picker', 'agec-primary-color-text');

    /* ---- Media uploader ---- */
    var logoFrame, bgFrame;

    $('#agec-logo-btn').on('click', function (e) {
        e.preventDefault();
        if (!logoFrame) {
            logoFrame = wp.media({ title: 'Choisir un logo', button: { text: 'Utiliser ce logo' }, multiple: false });
            logoFrame.on('select', function () {
                var url = logoFrame.state().get('selection').first().toJSON().url;
                $('#agec-logo-url').val(url);
                $('#agec-logo-preview').attr('src', url);
            });
        }
        logoFrame.open();
    });

    $('#agec-bg-btn').on('click', function (e) {
        e.preventDefault();
        if (!bgFrame) {
            bgFrame = wp.media({ title: 'Choisir une image de fond', button: { text: 'Utiliser cette image' }, multiple: false });
            bgFrame.on('select', function () {
                var url = bgFrame.state().get('selection').first().toJSON().url;
                $('#agec-bg-url').val(url);
                $('#agec-bg-preview').attr('src', url);
            });
        }
        bgFrame.open();
    });

    /* ---- Save client ---- */
    $('#agec-save-btn').on('click', function () {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Enregistrement…');

        var payload = {
            name:                 $('#agec-client-name').val(),
            logo_url:             $('#agec-logo-url').val(),
            primary_color:        $('#agec-primary-color-text').val(),
            background_image_url: $('#agec-bg-url').val(),
            status:               $('#agec-client-status').val(),
        };

        $.ajax({
            url: restUrl + 'clients/' + clientId,
            method: 'PUT',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', restNonce);
            },
            success: function (resp) {
                if (resp.success && resp.client) {
                    var c = resp.client;
                    $('#agec-slug-display').val(c.slug || '');
                    var portalUrl = window.location.origin + '/client/' + (c.slug || '') + '/';
                    $('#agec-portal-url').val(portalUrl);
                    toast(__('Changes saved!', 'agency-client-portal'));
                } else {
                    toast('Erreur lors de la sauvegarde.', true);
                }
            },
            error: function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Erreur serveur.';
                toast(msg, true);
            },
            complete: function () {
                $btn.prop('disabled', false).text('Enregistrer');
            }
        });
    });

    /* ---- Token: load access link on page load ---- */
    if (agencyEditClient.has_token) {
        loadAccessLink();
    }

    function loadAccessLink() {
        $.post(ajaxUrl, {
            action:    'agency_get_access_url',
            client_id: clientId,
            nonce:     ajaxNonce,
        }, function (resp) {
            if (resp.success && resp.data.access_url) {
                $('#agec-access-link-input').val(resp.data.access_url);
                $('#agec-access-link-wrap').show();
            } else {
                // Transient expiré : le plaintext n'est plus disponible
                $('#agec-access-link-input').val('').attr('placeholder', __('Link expired — click "Regenerate"', 'agency-client-portal'));
                $('#agec-copy-access-link').prop('disabled', true);
            }
        });
    }

    /* ---- Token: generate ---- */
    $('#agec-generate-token').on('click', function () {
        var $btn = $(this);
        $btn.prop('disabled', true);
        $.post(ajaxUrl, {
            action:    'agency_get_access_url',
            client_id: clientId,
            nonce:     ajaxNonce,
        }, function (resp) {
            if (resp.success && resp.data.access_url) {
                $('#agec-access-link-input').val(resp.data.access_url);
                $('#agec-access-link-wrap').show();
                $('#agec-token-status').removeClass('inactive').addClass('active')
                    .find('.agec-token-label').text(__('Access token active', 'agency-client-portal'));
                $('#agec-generate-token').hide();
                $('#agec-regenerate-token, #agec-revoke-token').show();
                toast(__('Access link generated!', 'agency-client-portal'));
            } else {
                toast(__('Error while generating.', 'agency-client-portal'), true);
            }
        }).always(function () { $btn.prop('disabled', false); });
    });

    /* ---- Token: regenerate ---- */
    $('#agec-regenerate-token').on('click', function () {
        if (!confirm(__('Regenerate the token? The old access link will become invalid.', 'agency-client-portal'))) { return; }
        var $btn = $(this);
        $btn.prop('disabled', true);
        $.post(ajaxUrl, {
            action:    'agency_regenerate_token',
            client_id: clientId,
            nonce:     ajaxNonce,
        }, function (resp) {
            if (resp.success) {
                loadAccessLink();
                toast(__('Token regenerated!', 'agency-client-portal'));
            } else {
                toast(__('Error: ', 'agency-client-portal') + (resp.data || __('Failed', 'agency-client-portal')), true);
            }
        }).always(function () { $btn.prop('disabled', false); });
    });

    /* ---- Token: revoke ---- */
    $('#agec-revoke-token').on('click', function () {
        if (!confirm(__('Revoke access? The client will no longer be able to access their space.', 'agency-client-portal'))) { return; }
        var $btn = $(this);
        $btn.prop('disabled', true);
        $.post(ajaxUrl, {
            action:    'agency_revoke_token',
            client_id: clientId,
            nonce:     ajaxNonce,
        }, function (resp) {
            if (resp.success) {
                $('#agec-token-status').removeClass('active').addClass('inactive')
                    .find('.agec-token-label').text(__('No active access token', 'agency-client-portal'));
                $('#agec-access-link-wrap').hide();
                $('#agec-access-link-input').val('');
                $('#agec-generate-token').show();
                $('#agec-regenerate-token, #agec-revoke-token').hide();
                toast(__('Access revoked.', 'agency-client-portal'));
            } else {
                toast(__('Error: ', 'agency-client-portal') + (resp.data || __('Failed', 'agency-client-portal')), true);
            }
        }).always(function () { $btn.prop('disabled', false); });
    });

    /* ---- Hard delete ---- */
    $('#agec-delete-btn').on('click', function () {
        if (!confirm(__('Permanently delete this client? This action cannot be undone and will delete all of its projects, blocks and files.', 'agency-client-portal'))) {
            return;
        }
        var $btn = $(this);
        $btn.prop('disabled', true).text('Suppression…');
        $.post(ajaxUrl, {
            action:    'agency_hard_delete_client',
            client_id: clientId,
            nonce:     ajaxNonce,
        }, function (resp) {
            if (resp.success) {
                toast(__('Client deleted. Redirecting…', 'agency-client-portal'));
                setTimeout(function () {
                    window.location.href = resp.data.redirect || agencyData.dashboard_url;
                }, 1200);
            } else {
                toast(__('Error: ', 'agency-client-portal') + (resp.data || __('Failed', 'agency-client-portal')), true);
                $btn.prop('disabled', false).text(__('Permanently delete the client', 'agency-client-portal'));
            }
        }).fail(function () {
            toast('Erreur serveur.', true);
            $btn.prop('disabled', false).text(__('Permanently delete the client', 'agency-client-portal'));
        });
    });

})(jQuery);
