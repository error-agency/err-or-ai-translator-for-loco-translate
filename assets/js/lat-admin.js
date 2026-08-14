/* global errorLaitAdmin, jQuery */
(function ($) {
    'use strict';

    // ─── Provider Tabs ──────────────────────────────────────────────────────
    $('.lat-provider-tab input[type=radio]').on('change', function () {
        $('.lat-provider-tab').removeClass('active');
        $(this).closest('.lat-provider-tab').addClass('active');

        const provider = $(this).val();

        if (provider === 'ollama') {
            $('.lat-row-apikey').hide();
        } else {
            $('.lat-row-apikey').show();
        }
    });

    // ─── Endpoint Presets ───────────────────────────────────────────────────
    $('.lat-preset').on('click', function () {
        $('#lat-api-endpoint').val($(this).data('value'));
    });

    // ─── Default Prompt Preview ─────────────────────────────────────────────
    $('#lat-show-default-prompt').on('click', function () {
        const $btn = $(this);
        const $pre = $('#lat-default-prompt-preview');
        $pre.slideToggle(200, function () {
            $btn.text($pre.is(':visible') ? 'Hide default prompt' : 'View default prompt');
        });
    });

    // ─── Load Models ────────────────────────────────────────────────────────
    $('#lat-fetch-models').on('click', function () {
        const $btn = $(this);
        const $select = $('#lat-model-select');
        const $input = $('#lat-model-input');

        const provider    = $('.lat-provider-tab input[type=radio]:checked').val() || 'openrouter';
        const apiEndpoint = String($('#lat-api-endpoint').val() || '').trim();
        const apiKey      = String($('input[name="error_lait_settings[api_key]"]').val() || '').trim();

        $btn.text('Loading…').prop('disabled', true);

        $.post(errorLaitAdmin.ajaxUrl, {
            action: 'error_lait_fetch_models',
            nonce: errorLaitAdmin.nonce,
            provider: provider,
            api_endpoint: apiEndpoint,
            api_key: apiKey,
        }, function (res) {
            $btn.text('↻ Load Models').prop('disabled', false);

            if (!res.success) {
                alert('Error: ' + (res.data?.message || 'Unknown error'));
                return;
            }

            const models = res.data.models;
            $select.empty().append('<option value="">— choose a model —</option>');

            models.forEach(function (m) {
                let label = m.id;
                if (m.name && m.name !== m.id) label += ' — ' + m.name;
                if (m.context) label += ' (' + Math.round(m.context / 1000) + 'k ctx)';
                $select.append($('<option>').val(m.id).text(label));
            });

            $select.val($input.val());
            $select.show();

            $select.off('change').on('change', function () {
                $input.val($(this).val());
            });
        }).fail(function () {
            $btn.text('↻ Load Models').prop('disabled', false);
            alert('Network error while loading models.');
        });
    });

    // ─── Test Connection ────────────────────────────────────────────────────
    $('#lat-test-connection').on('click', function () {
        const $btn = $(this);
        const $result = $('#lat-test-result');

        const provider    = $('.lat-provider-tab input[type=radio]:checked').val() || 'openrouter';
        const apiEndpoint = String($('#lat-api-endpoint').val() || '').trim();
        const apiKey      = String($('input[name="error_lait_settings[api_key]"]').val() || '').trim();
        const model       = String($('#lat-model-input').val() || '').trim();

        $btn.text('Testing…').prop('disabled', true);
        $result.removeClass('lat-test-ok lat-test-err').text('');

        $.post(errorLaitAdmin.ajaxUrl, {
            action: 'error_lait_test_connection',
            nonce: errorLaitAdmin.nonce,
            provider: provider,
            api_endpoint: apiEndpoint,
            api_key: apiKey,
            model: model,
        }, function (res) {
            $btn.text('🔌 Test Connection').prop('disabled', false);

            if (res.success) {
                $result.addClass('lat-test-ok')
                    .text('✓ ' + res.data.message + '  "Hello" → "' + res.data.test_output + '"');
            } else {
                $result.addClass('lat-test-err')
                    .text('✗ ' + (res.data?.message || 'Unknown error'));
            }
        }).fail(function () {
            $btn.text('🔌 Test Connection').prop('disabled', false);
            $result.addClass('lat-test-err').text('✗ Network error');
        });
    });

})(jQuery);
