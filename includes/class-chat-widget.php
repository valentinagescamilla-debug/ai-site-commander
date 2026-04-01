<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AISC_Chat_Widget {

    private $settings;

    public function __construct() {
        $this->settings = AISC_Security::get_settings();
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_footer',          array( $this, 'render_widget' ) );
    }

    public function enqueue_assets() {
        if ( ! AISC_Security::is_authorized() ) {
            return;
        }
        if ( $this->settings['show_on_mobile'] !== '1' && wp_is_mobile() ) {
            return;
        }
        wp_enqueue_style(
            'aisc-widget',
            AISC_PLUGIN_URL . 'assets/css/widget.css',
            array(),
            AISC_VERSION
        );
        wp_enqueue_script(
            'aisc-widget',
            AISC_PLUGIN_URL . 'assets/js/widget.js',
            array( 'jquery' ),
            AISC_VERSION,
            true
        );
        wp_localize_script( 'aisc-widget', 'aisc_data', array(
            'ajax_url'        => admin_url( 'admin-ajax.php' ),
            'nonce'           => wp_create_nonce( 'aisc_chat_nonce' ),
            'welcome_message' => $this->settings['welcome_message'],
            'widget_color'    => $this->settings['widget_color'],
            'widget_title'    => $this->settings['widget_title'],
            'position'        => $this->settings['widget_position'],
            'size'            => $this->settings['widget_size'],
        ) );
    }

    public function render_widget() {
        if ( ! AISC_Security::is_authorized() ) {
            return;
        }
        if ( $this->settings['show_on_mobile'] !== '1' && wp_is_mobile() ) {
            return;
        }
        $position = $this->settings['widget_position'];
        $color    = $this->settings['widget_color'];
        $title    = $this->settings['widget_title'];
        $size     = $this->settings['widget_size'];

        $sizes = array(
            'small'  => array( 'width' => '320px', 'height' => '380px' ),
            'medium' => array( 'width' => '380px', 'height' => '450px' ),
            'large'  => array( 'width' => '460px', 'height' => '580px' ),
        );
        $w = $sizes[ $size ] ?? $sizes['medium'];

        $pos_css = '';
        switch ( $position ) {
            case 'bottom-right': $pos_css = 'bottom:24px;right:24px;'; break;
            case 'bottom-left':  $pos_css = 'bottom:24px;left:24px;'; break;
            case 'top-right':    $pos_css = 'top:24px;right:24px;'; break;
            case 'top-left':     $pos_css = 'top:24px;left:24px;'; break;
            default:             $pos_css = 'bottom:24px;right:24px;';
        }
        ?>

        <div id="aisc-widget-wrap" style="position:fixed;<?php echo esc_attr( $pos_css ); ?>z-index:999999;">

            <!-- BOTON FLOTANTE -->
            <button id="aisc-toggle-btn"
                style="width:60px;height:60px;border-radius:50%;background:<?php echo esc_attr( $color ); ?>;border:none;cursor:pointer;box-shadow:0 4px 20px rgba(0,0,0,0.3);display:flex;align-items:center;justify-content:center;transition:all 0.3s ease;"
                title="AI Commander">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="white">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z"/>
                </svg>
            </button>

            <!-- VENTANA DEL CHAT -->
            <div id="aisc-chat-window"
                style="display:none;width:<?php echo esc_attr( $w['width'] ); ?>;height:<?php echo esc_attr( $w['height'] ); ?>;background:#fff;border-radius:16px;box-shadow:0 8px 40px rgba(0,0,0,0.2);flex-direction:column;overflow:hidden;margin-bottom:12px;position:absolute;bottom:72px;right:0;">

                <!-- CABECERA DEL CHAT -->
                <div id="aisc-chat-header"
                    style="background:<?php echo esc_attr( $color ); ?>;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:36px;height:36px;background:rgba(255,255,255,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="white">
                                <path d="M12 2a2 2 0 0 1 2 2c0 .74-.4 1.39-1 1.73V7h1a7 7 0 0 1 7 7h1a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1H2a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h1a7 7 0 0 1 7-7h1V5.73c-.6-.34-1-.99-1-1.73a2 2 0 0 1 2-2M9 9a1 1 0 0 0-1 1 1 1 0 0 0 1 1 1 1 0 0 0 1-1 1 1 0 0 0-1-1m6 0a1 1 0 0 0-1 1 1 1 0 0 0 1 1 1 1 0 0 0 1-1 1 1 0 0 0-1-1z"/>
                            </svg>
                        </div>
                        <div>
                            <div style="color:white;font-weight:700;font-size:15px;font-family:sans-serif;"><?php echo esc_html( $title ); ?></div>
                            <div style="color:rgba(255,255,255,0.8);font-size:12px;font-family:sans-serif;">Solo visible para administradores</div>
                        </div>
                    </div>
                    <button id="aisc-close-btn"
                        style="background:rgba(255,255,255,0.2);border:none;color:white;width:30px;height:30px;border-radius:50%;cursor:pointer;font-size:18px;display:flex;align-items:center;justify-content:center;">
                        &times;
                    </button>
                </div>

                <!-- AREA DE MENSAJES -->
                <div id="aisc-messages"
                    style="flex:1;overflow-y:auto;padding:16px;background:#f8f9fa;display:flex;flex-direction:column;gap:12px;">
                </div>

                <!-- AREA DE ESCRITURA -->
                <div id="aisc-input-area"
                    style="padding:12px 16px;background:#fff;border-top:1px solid #e9ecef;display:flex;gap:8px;align-items:flex-end;">
                    <textarea id="aisc-user-input"
                        placeholder="Escribe un comando... ej: Cambia el color del header a azul"
                        style="flex:1;border:1px solid #dee2e6;border-radius:10px;padding:10px 14px;font-size:14px;font-family:sans-serif;resize:none;outline:none;max-height:100px;line-height:1.5;"
                        rows="1"></textarea>
                    <button id="aisc-send-btn"
                        style="background:<?php echo esc_attr( $color ); ?>;border:none;color:white;width:42px;height:42px;border-radius:10px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="white">
                            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                        </svg>
                    </button>
                </div>

            </div>
        </div>
        <?php
    }
}
