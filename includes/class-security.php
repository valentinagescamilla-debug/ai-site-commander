<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AISC_Security {

    public static function is_authorized() {
        return is_user_logged_in() && current_user_can( 'manage_options' );
    }

    public static function verify_nonce( $nonce ) {
        return wp_verify_nonce( $nonce, 'aisc_chat_nonce' );
    }

    public static function get_settings() {
        $settings = get_option( 'aisc_settings', array() );
        return wp_parse_args( $settings, array(
            'ai_provider'       => 'anthropic',
            'ai_model'          => 'claude-sonnet-4-5',
            'api_key'           => '',
            'openrouter_key'    => '',
            'google_key'        => '',
            'widget_position'   => 'bottom-right',
            'widget_size'       => 'medium',
            'widget_color'      => '#6366f1',
            'widget_title'      => 'AI Commander',
            'welcome_message'   => 'Hola! Soy tu asistente IA.',
            'system_prompt'     => '',
            'chat_height'       => '450',
            'chat_width'        => '380',
            'max_tokens'        => '2048',
            'temperature'       => '0.3',
            'whatsapp_enabled'  => '0',
            'whatsapp_token'    => '',
            'whatsapp_phone_id' => '',
            'whatsapp_verify'   => 'aisc_verify_2024',
            'show_on_mobile'    => '1',
        ) );
    }

    public static function sanitize_message( $message ) {
        return sanitize_textarea_field( wp_unslash( $message ) );
    }
}
