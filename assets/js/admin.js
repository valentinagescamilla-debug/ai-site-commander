jQuery(document).ready(function($) {

    // ── TABS ─────────────────────────────────────────────────────────────────
    $('.aisc-tab').on('click', function() {
        var tab = $(this).data('tab');

        $('.aisc-tab').removeClass('active');
        $(this).addClass('active');

        $('.aisc-tab-content').removeClass('active');
        $('#tab-' + tab).addClass('active');
    });

    // ── LIMPIAR HISTORIAL ────────────────────────────────────────────────────
    $('#aisc-clear-history').on('click', function() {
        if ( ! confirm('Seguro que quieres borrar todo el historial del chat?') ) {
            return;
        }
        var $btn = $(this);
        $btn.text('Limpiando...').prop('disabled', true);

        $.ajax({
            url:    ajaxurl,
            method: 'POST',
            data: {
                action: 'aisc_clear_history',
                nonce:  $('#_wpnonce').val(),
            },
            success: function() {
                $btn.text('Historial limpiado!');
                setTimeout(function() {
                    $btn.text('Limpiar historial del chat').prop('disabled', false);
                }, 2000);
            },
            error: function() {
                $btn.text('Error al limpiar').prop('disabled', false);
            },
        });
    });

    // ── RESTABLECER AJUSTES ──────────────────────────────────────────────────
    $('#aisc-reset-settings').on('click', function() {
        if ( ! confirm('CUIDADO: Esto borrara todas tus configuraciones incluyendo tu API Key. Continuar?') ) {
            return;
        }
        var $btn = $(this);
        $btn.text('Restableciendo...').prop('disabled', true);

        $.ajax({
            url:    ajaxurl,
            method: 'POST',
            data: {
                action: 'aisc_reset_settings',
                nonce:  $('#_wpnonce').val(),
            },
            success: function() {
                $btn.text('Listo! Recargando...');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            },
            error: function() {
                $btn.text('Error').prop('disabled', false);
            },
        });
    });

    // ── PREVIEW COLOR EN TIEMPO REAL ─────────────────────────────────────────
    $('#widget_color').on('input', function() {
        var color = $(this).val();
        $('.aisc-header').css('background', 'linear-gradient(135deg, ' + color + ' 0%, ' + color + ' 100%)');
    });

    // ── MOSTRAR/OCULTAR API KEYS SEGUN PROVEEDOR ─────────────────────────────
    function toggleApiFields() {
        var provider = $('input[name="aisc_settings[ai_provider]"]:checked').val();
        var $rows    = $('.form-table tr');

        $rows.each(function() {
            var $row = $(this);
            var $input = $row.find('input[id], select[id], textarea[id]');
            var id   = $input.attr('id') || '';

            if ( id === 'api_key' ) {
                $row.toggle( provider === 'anthropic' );
            } else if ( id === 'google_key' ) {
                $row.toggle( provider === 'google' );
            } else if ( id === 'openrouter_key' ) {
                $row.toggle( provider === 'openrouter' );
            }
        });
    }

    toggleApiFields();

    $('input[name="aisc_settings[ai_provider]"]').on('change', function() {
        toggleApiFields();
    });

});
