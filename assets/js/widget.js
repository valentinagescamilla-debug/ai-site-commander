jQuery(document).ready(function($) {

    var isOpen    = false;
    var isLoading = false;
    var color     = aisc_data.widget_color || '#6366f1';

    // ── APLICAR COLOR DINAMICO ──────────────────────────────────────────────
    $('.aisc-message.user').css('background', color);
    $('#aisc-send-btn').css('background', color);

    // ── MOSTRAR MENSAJE DE BIENVENIDA ───────────────────────────────────────
    function showWelcome() {
        var msg = aisc_data.welcome_message || 'Hola! Como puedo ayudarte?';
        appendMessage('assistant', msg);
    }

    // ── ABRIR / CERRAR CHAT ─────────────────────────────────────────────────
    $('#aisc-toggle-btn').on('click', function() {
        if ( isOpen ) {
            closeChat();
        } else {
            openChat();
        }
    });

    $('#aisc-close-btn').on('click', function() {
        closeChat();
    });

    function openChat() {
        isOpen = true;
        var $window = $('#aisc-chat-window');
        $window.css('display', 'flex');
        setTimeout(function() {
            $window.css('opacity', '1');
        }, 10);
        if ( $('#aisc-messages').children().length === 0 ) {
            showWelcome();
        }
        setTimeout(function() {
            $('#aisc-user-input').focus();
        }, 300);
        $('#aisc-toggle-btn').html(
            '<svg width="24" height="24" viewBox="0 0 24 24" fill="white"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>'
        );
    }

    function closeChat() {
        isOpen = false;
        $('#aisc-chat-window').css('display', 'none');
        $('#aisc-toggle-btn').html(
            '<svg width="28" height="28" viewBox="0 0 24 24" fill="white"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z"/></svg>'
        );
    }

    // ── ENVIAR MENSAJE CON ENTER ────────────────────────────────────────────
    $('#aisc-user-input').on('keydown', function(e) {
        if ( e.key === 'Enter' && ! e.shiftKey ) {
            e.preventDefault();
            sendMessage();
        }
    });

    // ── ENVIAR MENSAJE CON BOTON ────────────────────────────────────────────
    $('#aisc-send-btn').on('click', function() {
        sendMessage();
    });

    // ── AUTO RESIZE TEXTAREA ────────────────────────────────────────────────
    $('#aisc-user-input').on('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 100) + 'px';
    });

    // ── FUNCION PRINCIPAL: ENVIAR MENSAJE ───────────────────────────────────
    function sendMessage() {
        if ( isLoading ) return;

        var message = $('#aisc-user-input').val().trim();
        if ( ! message ) return;

        appendMessage('user', message);
        $('#aisc-user-input').val('');
        $('#aisc-user-input').css('height', 'auto');

        showTyping();
        setLoading(true);

        $.ajax({
            url:    aisc_data.ajax_url,
            method: 'POST',
            data: {
                action:  'aisc_send_message',
                nonce:   aisc_data.nonce,
                message: message,
            },
            timeout: 60000,
            success: function(response) {
                removeTyping();
                setLoading(false);
                if ( response.success ) {
                    appendMessage('assistant', response.data.message);
                } else {
                    appendMessage('error', 'Error: ' + (response.data.message || 'Error desconocido'));
                }
            },
            error: function(xhr, status) {
                removeTyping();
                setLoading(false);
                if ( status === 'timeout' ) {
                    appendMessage('error', 'La solicitud tardo demasiado. Intenta de nuevo.');
                } else {
                    appendMessage('error', 'Error de conexion. Verifica tu conexion a internet.');
                }
            },
        });
    }

    // ── AGREGAR MENSAJE AL CHAT ─────────────────────────────────────────────
    function appendMessage(type, text) {
        var $messages = $('#aisc-messages');
        var time      = getCurrentTime();
        var bgColor   = type === 'user' ? color : '';
        var styleAttr = type === 'user' ? ' style="background:' + color + ';"' : '';

        var html = '<div class="aisc-message ' + type + '"' + styleAttr + '>'
                 + formatMessage(text)
                 + '<div class="aisc-message-time">' + time + '</div>'
                 + '</div>';

        $messages.append(html);
        scrollToBottom();
    }
    // ── MOSTRAR INDICADOR DE ESCRITURA ──────────────────────────────────────
    function showTyping() {
        var $messages = $('#aisc-messages');
        var html = '<div class="aisc-typing" id="aisc-typing-indicator">'
                 + '<span></span><span></span><span></span>'
                 + '</div>';
        $messages.append(html);
        scrollToBottom();
    }

    function removeTyping() {
        $('#aisc-typing-indicator').remove();
    }

    // ── BLOQUEAR / DESBLOQUEAR INPUT ────────────────────────────────────────
    function setLoading(state) {
        isLoading = state;
        $('#aisc-send-btn').prop('disabled', state);
        $('#aisc-user-input').prop('disabled', state);
        if ( state ) {
            $('#aisc-user-input').attr('placeholder', 'Esperando respuesta...');
        } else {
            $('#aisc-user-input').attr('placeholder', 'Escribe un comando...');
            $('#aisc-user-input').focus();
        }
    }

    // ── SCROLL AL FINAL ─────────────────────────────────────────────────────
    function scrollToBottom() {
        var $messages = $('#aisc-messages');
        $messages.scrollTop($messages[0].scrollHeight);
    }

    // ── HORA ACTUAL ─────────────────────────────────────────────────────────
    function getCurrentTime() {
        var now  = new Date();
        var h    = now.getHours().toString().padStart(2, '0');
        var m    = now.getMinutes().toString().padStart(2, '0');
        return h + ':' + m;
    }

    // ── FORMATEAR MENSAJE (markdown basico) ─────────────────────────────────
    function formatMessage(text) {
        if ( ! text ) return '';
        text = $('<div>').text(text).html();
        text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        text = text.replace(/\*(.*?)\*/g, '<em>$1</em>');
        text = text.replace(/`(.*?)`/g, '<code style="background:#f3f4f6;padding:2px 6px;border-radius:4px;font-size:13px;">$1</code>');
        text = text.replace(/\n/g, '<br>');
        return text;
    }

    // ── CERRAR CHAT AL HACER CLICK FUERA ────────────────────────────────────
    $(document).on('click', function(e) {
        if ( isOpen ) {
            if ( ! $(e.target).closest('#aisc-widget-wrap').length ) {
                closeChat();
            }
        }
    });

    // ── APLICAR POSICION SEGUN CONFIGURACION ────────────────────────────────
    var position = aisc_data.position || 'bottom-right';
    var $wrap    = $('#aisc-widget-wrap');
    var $window  = $('#aisc-chat-window');

    if ( position === 'bottom-left' ) {
        $window.css({ 'right': 'auto', 'left': '0' });
    } else if ( position === 'top-right' ) {
        $window.css({ 'bottom': 'auto', 'top': '72px' });
    } else if ( position === 'top-left' ) {
        $window.css({ 'bottom': 'auto', 'top': '72px', 'right': 'auto', 'left': '0' });
    }

});
