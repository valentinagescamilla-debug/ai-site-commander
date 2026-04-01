<?php
/**
 * Plugin Name: AI Site Commander
 * Description: Controla tu sitio WordPress completo desde un chat de IA flotante.
 * Version: 1.0.0
 * Author: AI Site Commander
 * License: GPL v2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'AISC_VERSION',     '1.0.0' );
define( 'AISC_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'AISC_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'AISC_PLUGIN_FILE', __FILE__ );

require_once AISC_PLUGIN_DIR . 'includes/class-security.php';
require_once AISC_PLUGIN_DIR . 'includes/class-admin-panel.php';
require_once AISC_PLUGIN_DIR . 'includes/class-chat-widget.php';
require_once AISC_PLUGIN_DIR . 'includes/class-ai-engine.php';

function aisc_init() {
    new AISC_Admin_Panel();
    new AISC_Chat_Widget();
    new AISC_AI_Engine();
}
add_action( 'plugins_loaded', 'aisc_init' );

register_activation_hook( __FILE__, 'aisc_activate' );
function aisc_activate() {
    $defaults = array(
        'ai_provider'       => 'anthropic',
        'ai_model'          => 'claude-sonnet-4-5',
        'api_key'           => '',
        'openrouter_key'    => '',
        'google_key'        => '',
        'widget_position'   => 'bottom-right',
        'widget_size'       => 'medium',
        'widget_color'      => '#6366f1',
        'widget_title'      => 'AI Commander',
        'chat_height'       => '450',
        'chat_width'        => '380',
        'welcome_message'   => 'Hola! Soy tu asistente IA. Que cambios quieres hacer en tu sitio hoy?',
        'system_prompt'     => 'Eres un asistente experto en WordPress que ayuda a la administradora del sitio a controlar y modificar su web mediante comandos en lenguaje natural. Siempre confirma cada accion realizada.',
        'whatsapp_enabled'  => '0',
        'whatsapp_token'    => '',
        'whatsapp_phone_id' => '',
        'whatsapp_verify'   => 'aisc_verify_2024',
        'show_on_mobile'    => '1',
        'max_tokens'        => '2048',
        'temperature'       => '0.3',
    );
    add_option( 'aisc_settings', $defaults );
}

register_deactivation_hook( __FILE__, 'aisc_deactivate' );
function aisc_deactivate() {
    // No borrar datos al desactivar
}
