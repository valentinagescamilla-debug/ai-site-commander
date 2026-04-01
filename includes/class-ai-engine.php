<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AISC_AI_Engine {

    private $settings;

    public function __construct() {
        $this->settings = AISC_Security::get_settings();
        add_action( 'wp_ajax_aisc_send_message', array( $this, 'handle_message' ) );
        add_action( 'rest_api_init',             array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        register_rest_route( 'aisc/v1', '/whatsapp', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'whatsapp_verify' ),
                'permission_callback' => '__return_true',
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'whatsapp_message' ),
                'permission_callback' => '__return_true',
            ),
        ) );
    }

    public function whatsapp_verify( $request ) {
        $mode      = $request->get_param( 'hub_mode' );
        $token     = $request->get_param( 'hub_verify_token' );
        $challenge = $request->get_param( 'hub_challenge' );
        if ( $mode === 'subscribe' && $token === $this->settings['whatsapp_verify'] ) {
            return new WP_REST_Response( intval( $challenge ), 200 );
        }
        return new WP_REST_Response( 'Forbidden', 403 );
    }

    public function whatsapp_message( $request ) {
        if ( $this->settings['whatsapp_enabled'] !== '1' ) {
            return new WP_REST_Response( 'Disabled', 200 );
        }
        $body  = $request->get_json_params();
        $entry = $body['entry'][0]['changes'][0]['value'] ?? null;
        if ( ! $entry ) {
            return new WP_REST_Response( 'OK', 200 );
        }
        $message = $entry['messages'][0] ?? null;
        if ( ! $message || $message['type'] !== 'text' ) {
            return new WP_REST_Response( 'OK', 200 );
        }
        $text    = $message['text']['body'] ?? '';
        $phone   = $message['from'] ?? '';
        $response_text = $this->process_message( $text );
        $this->send_whatsapp_reply( $phone, $response_text );
        return new WP_REST_Response( 'OK', 200 );
    }

    private function send_whatsapp_reply( $phone, $text ) {
        $phone_id = $this->settings['whatsapp_phone_id'];
        $token    = $this->settings['whatsapp_token'];
        if ( empty( $phone_id ) || empty( $token ) ) {
            return;
        }
        wp_remote_post(
            "https://graph.facebook.com/v18.0/{$phone_id}/messages",
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ),
                'body' => wp_json_encode( array(
                    'messaging_product' => 'whatsapp',
                    'to'                => $phone,
                    'type'              => 'text',
                    'text'              => array( 'body' => $text ),
                ) ),
                'timeout' => 15,
            )
        );
    }

    public function handle_message() {
        if ( ! AISC_Security::is_authorized() ) {
            wp_send_json_error( array( 'message' => 'Sin permisos.' ), 403 );
        }
        $nonce = sanitize_text_field( $_POST['nonce'] ?? '' );
        if ( ! AISC_Security::verify_nonce( $nonce ) ) {
            wp_send_json_error( array( 'message' => 'Nonce invalido.' ), 403 );
        }
        $message = AISC_Security::sanitize_message( $_POST['message'] ?? '' );
        if ( empty( $message ) ) {
            wp_send_json_error( array( 'message' => 'Mensaje vacio.' ) );
        }
        $response = $this->process_message( $message );
        wp_send_json_success( array( 'message' => $response ) );
    }

    private function process_message( $user_message ) {
        $provider = $this->settings['ai_provider'];
        switch ( $provider ) {
            case 'anthropic':
                return $this->call_anthropic( $user_message );
            case 'google':
                return $this->call_google( $user_message );
            case 'openrouter':
                return $this->call_openrouter( $user_message );
            default:
                return $this->call_anthropic( $user_message );
        }
    }

    private function get_system_prompt() {
        $custom = $this->settings['system_prompt'];
        $site_url  = get_site_url();
        $site_name = get_bloginfo( 'name' );
        $base = "Eres un asistente experto en WordPress para el sitio '{$site_name}' ({$site_url}). 
Puedes controlar completamente el sitio web mediante las siguientes acciones disponibles.
Cuando el usuario te pida hacer algo, ejecuta la accion correspondiente usando las funciones disponibles.
Siempre responde en espanol y confirma cada accion realizada.
Sitio: {$site_name} | URL: {$site_url}";
        return ! empty( $custom ) ? $custom . "\n\n" . $base : $base;
    }

    private function get_tools() {
        return array(
            array(
                'name'        => 'execute_wordpress_action',
                'description' => 'Ejecuta una accion en WordPress como cambiar CSS, crear paginas, editar contenido, gestionar productos WooCommerce, etc.',
                'input_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'action' => array(
                            'type'        => 'string',
                            'description' => 'La accion a ejecutar',
                            'enum'        => array(
                                'change_css',
                                'get_pages',
                                'create_page',
                                'edit_page',
                                'delete_page',
                                'get_posts',
                                'create_post',
                                'edit_post',
                                'get_site_info',
                                'change_site_option',
                                'get_products',
                                'edit_product',
                                'inject_html',
                            ),
                        ),
                        'params' => array(
                            'type'        => 'object',
                            'description' => 'Parametros especificos de la accion',
                        ),
                    ),
                    'required' => array( 'action', 'params' ),
                ),
            ),
        );
    }

    private function call_anthropic( $user_message ) {
        $api_key = $this->settings['api_key'];
        if ( empty( $api_key ) ) {
            return 'Error: No hay API Key de Anthropic configurada. Ve a AI Commander > IA para configurarla.';
        }
        $history   = get_option( 'aisc_chat_history', array() );
        $history[] = array( 'role' => 'user', 'content' => $user_message );
        if ( count( $history ) > 20 ) {
            $history = array_slice( $history, -20 );
        }
        $response = wp_remote_post(
            'https://api.anthropic.com/v1/messages',
            array(
                'headers' => array(
                    'x-api-key'         => $api_key,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ),
                'body' => wp_json_encode( array(
                    'model'      => $this->settings['ai_model'],
                    'max_tokens' => intval( $this->settings['max_tokens'] ),
                    'system'     => $this->get_system_prompt(),
                    'tools'      => $this->get_tools(),
                    'messages'   => $history,
                ) ),
                'timeout' => 60,
            )
        );
        if ( is_wp_error( $response ) ) {
            return 'Error de conexion: ' . $response->get_error_message();
        }
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( isset( $body['error'] ) ) {
            return 'Error de API: ' . ( $body['error']['message'] ?? 'Error desconocido' );
        }
        $assistant_message = '';
        $tool_results      = array();
        foreach ( $body['content'] as $block ) {
            if ( $block['type'] === 'text' ) {
                $assistant_message .= $block['text'];
            } elseif ( $block['type'] === 'tool_use' ) {
                $tool_result  = $this->execute_action( $block['input']['action'], $block['input']['params'] ?? array() );
                $tool_results[] = array(
                    'type'       => 'tool_result',
                    'tool_use_id'=> $block['id'],
                    'content'    => $tool_result,
                );
            }
        }
        if ( ! empty( $tool_results ) ) {
            $history[] = array( 'role' => 'assistant', 'content' => $body['content'] );
            $history[] = array( 'role' => 'user',      'content' => $tool_results );
            $response2 = wp_remote_post(
                'https://api.anthropic.com/v1/messages',
                array(
                    'headers' => array(
                        'x-api-key'         => $api_key,
                        'anthropic-version' => '2023-06-01',
                        'content-type'      => 'application/json',
                    ),
                    'body' => wp_json_encode( array(
                        'model'      => $this->settings['ai_model'],
                        'max_tokens' => intval( $this->settings['max_tokens'] ),
                        'system'     => $this->get_system_prompt(),
                        'tools'      => $this->get_tools(),
                        'messages'   => $history,
                    ) ),
                    'timeout' => 60,
                )
            );
            $body2 = json_decode( wp_remote_retrieve_body( $response2 ), true );
            foreach ( $body2['content'] as $block ) {
                if ( $block['type'] === 'text' ) {
                    $assistant_message = $block['text'];
                }
            }
            $history[] = array( 'role' => 'assistant', 'content' => $body2['content'] );
        } else {
            $history[] = array( 'role' => 'assistant', 'content' => $assistant_message );
        }
        update_option( 'aisc_chat_history', $history );
        return $assistant_message;
    }
    private function call_google( $user_message ) {
        $api_key = $this->settings['google_key'];
        if ( empty( $api_key ) ) {
            return 'Error: No hay API Key de Google configurada. Ve a AI Commander > IA para configurarla.';
        }
        $model    = $this->settings['ai_model'];
        $history  = get_option( 'aisc_chat_history', array() );
        $contents = array();
        foreach ( $history as $msg ) {
            $role = $msg['role'] === 'assistant' ? 'model' : 'user';
            if ( is_string( $msg['content'] ) ) {
                $contents[] = array(
                    'role'  => $role,
                    'parts' => array( array( 'text' => $msg['content'] ) ),
                );
            }
        }
        $contents[] = array(
            'role'  => 'user',
            'parts' => array( array( 'text' => $user_message ) ),
        );
        $response = wp_remote_post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}",
            array(
                'headers' => array( 'Content-Type' => 'application/json' ),
                'body'    => wp_json_encode( array(
                    'system_instruction' => array(
                        'parts' => array( array( 'text' => $this->get_system_prompt() ) ),
                    ),
                    'contents'           => $contents,
                    'generationConfig'   => array(
                        'maxOutputTokens' => intval( $this->settings['max_tokens'] ),
                        'temperature'     => floatval( $this->settings['temperature'] ),
                    ),
                ) ),
                'timeout' => 60,
            )
        );
        if ( is_wp_error( $response ) ) {
            return 'Error de conexion: ' . $response->get_error_message();
        }
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( isset( $body['error'] ) ) {
            return 'Error de API: ' . ( $body['error']['message'] ?? 'Error desconocido' );
        }
        $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? 'Sin respuesta';
        $history[] = array( 'role' => 'user',      'content' => $user_message );
        $history[] = array( 'role' => 'assistant',  'content' => $text );
        if ( count( $history ) > 20 ) {
            $history = array_slice( $history, -20 );
        }
        update_option( 'aisc_chat_history', $history );
        return $text;
    }

    private function call_openrouter( $user_message ) {
        $api_key = $this->settings['openrouter_key'];
        if ( empty( $api_key ) ) {
            return 'Error: No hay API Key de OpenRouter configurada. Ve a AI Commander > IA para configurarla.';
        }
        $history   = get_option( 'aisc_chat_history', array() );
        $messages  = array(
            array( 'role' => 'system', 'content' => $this->get_system_prompt() ),
        );
        foreach ( $history as $msg ) {
            if ( is_string( $msg['content'] ) ) {
                $messages[] = array(
                    'role'    => $msg['role'],
                    'content' => $msg['content'],
                );
            }
        }
        $messages[] = array( 'role' => 'user', 'content' => $user_message );
        $response = wp_remote_post(
            'https://openrouter.ai/api/v1/chat/completions',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                    'HTTP-Referer'  => get_site_url(),
                    'X-Title'       => get_bloginfo( 'name' ),
                ),
                'body' => wp_json_encode( array(
                    'model'      => $this->settings['ai_model'],
                    'messages'   => $messages,
                    'max_tokens' => intval( $this->settings['max_tokens'] ),
                    'temperature'=> floatval( $this->settings['temperature'] ),
                ) ),
                'timeout' => 60,
            )
        );
        if ( is_wp_error( $response ) ) {
            return 'Error de conexion: ' . $response->get_error_message();
        }
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( isset( $body['error'] ) ) {
            return 'Error de API: ' . ( $body['error']['message'] ?? 'Error desconocido' );
        }
        $text = $body['choices'][0]['message']['content'] ?? 'Sin respuesta';
        $history[] = array( 'role' => 'user',      'content' => $user_message );
        $history[] = array( 'role' => 'assistant',  'content' => $text );
        if ( count( $history ) > 20 ) {
            $history = array_slice( $history, -20 );
        }
        update_option( 'aisc_chat_history', $history );
        return $text;
    }

    public function execute_action( $action, $params ) {
        switch ( $action ) {
            case 'change_css':
                return $this->action_change_css( $params );
            case 'get_pages':
                return $this->action_get_pages();
            case 'create_page':
                return $this->action_create_page( $params );
            case 'edit_page':
                return $this->action_edit_page( $params );
            case 'delete_page':
                return $this->action_delete_page( $params );
            case 'get_posts':
                return $this->action_get_posts();
            case 'create_post':
                return $this->action_create_post( $params );
            case 'edit_post':
                return $this->action_edit_post( $params );
            case 'get_site_info':
                return $this->action_get_site_info();
            case 'change_site_option':
                return $this->action_change_site_option( $params );
            case 'get_products':
                return $this->action_get_products();
            case 'edit_product':
                return $this->action_edit_product( $params );
            case 'inject_html':
                return $this->action_inject_html( $params );
            default:
                return 'Accion no reconocida: ' . $action;
        }
    }

    private function action_change_css( $params ) {
        $css      = sanitize_textarea_field( $params['css'] ?? '' );
        $existing = get_option( 'aisc_custom_css', '' );
        $marker   = '/* AISC-BLOCK-START */';
        $marker_end = '/* AISC-BLOCK-END */';
        if ( strpos( $existing, $marker ) !== false ) {
            $new_css = preg_replace(
                '/' . preg_quote( $marker, '/' ) . '.*?' . preg_quote( $marker_end, '/' ) . '/s',
                $marker . "\n" . $css . "\n" . $marker_end,
                $existing
            );
        } else {
            $new_css = $existing . "\n" . $marker . "\n" . $css . "\n" . $marker_end;
        }
        update_option( 'aisc_custom_css', $new_css );
        return 'CSS actualizado correctamente. Los cambios se aplicaran en el sitio inmediatamente.';
    }

    private function action_get_pages() {
        $pages = get_pages( array( 'post_status' => 'publish,draft' ) );
        $list  = array();
        foreach ( $pages as $page ) {
            $list[] = array(
                'id'     => $page->ID,
                'title'  => $page->post_title,
                'status' => $page->post_status,
                'url'    => get_permalink( $page->ID ),
            );
        }
        return wp_json_encode( $list );
    }

    private function action_create_page( $params ) {
        $title   = sanitize_text_field( $params['title'] ?? 'Nueva Pagina' );
        $content = wp_kses_post( $params['content'] ?? '' );
        $status  = sanitize_text_field( $params['status'] ?? 'draft' );
        $page_id = wp_insert_post( array(
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => $status,
            'post_type'    => 'page',
        ) );
        if ( is_wp_error( $page_id ) ) {
            return 'Error al crear la pagina: ' . $page_id->get_error_message();
        }
        return "Pagina '{$title}' creada correctamente con ID {$page_id}. URL: " . get_permalink( $page_id );
    }

    private function action_edit_page( $params ) {
        $id      = intval( $params['id'] ?? 0 );
        $title   = sanitize_text_field( $params['title'] ?? '' );
        $content = wp_kses_post( $params['content'] ?? '' );
        if ( ! $id ) {
            return 'Error: ID de pagina no proporcionado.';
        }
        $data = array( 'ID' => $id );
        if ( ! empty( $title ) )   $data['post_title']   = $title;
        if ( ! empty( $content ) ) $data['post_content'] = $content;
        $result = wp_update_post( $data );
        if ( is_wp_error( $result ) ) {
            return 'Error al editar la pagina: ' . $result->get_error_message();
        }
        return "Pagina ID {$id} actualizada correctamente.";
    }

    private function action_delete_page( $params ) {
        $id = intval( $params['id'] ?? 0 );
        if ( ! $id ) {
            return 'Error: ID de pagina no proporcionado.';
        }
        $result = wp_trash_post( $id );
        if ( ! $result ) {
            return 'Error al eliminar la pagina.';
        }
        return "Pagina ID {$id} movida a la papelera correctamente.";
    }

    private function action_get_posts() {
        $posts = get_posts( array( 'numberposts' => 20, 'post_status' => 'any' ) );
        $list  = array();
        foreach ( $posts as $post ) {
            $list[] = array(
                'id'     => $post->ID,
                'title'  => $post->post_title,
                'status' => $post->post_status,
                'url'    => get_permalink( $post->ID ),
            );
        }
        return wp_json_encode( $list );
    }

    private function action_create_post( $params ) {
        $title   = sanitize_text_field( $params['title'] ?? 'Nuevo Post' );
        $content = wp_kses_post( $params['content'] ?? '' );
        $status  = sanitize_text_field( $params['status'] ?? 'draft' );
        $post_id = wp_insert_post( array(
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => $status,
            'post_type'    => 'post',
        ) );
        if ( is_wp_error( $post_id ) ) {
            return 'Error al crear el post: ' . $post_id->get_error_message();
        }
        return "Post '{$title}' creado correctamente con ID {$post_id}.";
    }

    private function action_edit_post( $params ) {
        $id      = intval( $params['id'] ?? 0 );
        $title   = sanitize_text_field( $params['title'] ?? '' );
        $content = wp_kses_post( $params['content'] ?? '' );
        if ( ! $id ) {
            return 'Error: ID de post no proporcionado.';
        }
        $data = array( 'ID' => $id );
        if ( ! empty( $title ) )   $data['post_title']   = $title;
        if ( ! empty( $content ) ) $data['post_content'] = $content;
        $result = wp_update_post( $data );
        if ( is_wp_error( $result ) ) {
            return 'Error al editar el post: ' . $result->get_error_message();
        }
        return "Post ID {$id} actualizado correctamente.";
    }

    private function action_get_site_info() {
        $info = array(
            'name'        => get_bloginfo( 'name' ),
            'description' => get_bloginfo( 'description' ),
            'url'         => get_site_url(),
            'admin_email' => get_option( 'admin_email' ),
            'wp_version'  => get_bloginfo( 'version' ),
            'theme'       => wp_get_theme()->get( 'Name' ),
            'language'    => get_bloginfo( 'language' ),
        );
        return wp_json_encode( $info );
    }

    private function action_change_site_option( $params ) {
        $option = sanitize_text_field( $params['option'] ?? '' );
        $value  = sanitize_text_field( $params['value'] ?? '' );
        $allowed = array( 'blogname', 'blogdescription', 'admin_email' );
        if ( ! in_array( $option, $allowed, true ) ) {
            return 'Opcion no permitida. Opciones disponibles: ' . implode( ', ', $allowed );
        }
        update_option( $option, $value );
        return "Opcion '{$option}' actualizada a '{$value}' correctamente.";
    }

    private function action_get_products() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return 'WooCommerce no esta instalado o activo.';
        }
        $products = wc_get_products( array( 'limit' => 20, 'status' => 'publish' ) );
        $list     = array();
        foreach ( $products as $product ) {
            $list[] = array(
                'id'    => $product->get_id(),
                'name'  => $product->get_name(),
                'price' => $product->get_price(),
                'stock' => $product->get_stock_quantity(),
            );
        }
        return wp_json_encode( $list );
    }

        private function action_edit_product( $params ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return 'WooCommerce no esta instalado o activo.';
        }
        $id    = intval( $params['id'] ?? 0 );
        $price = $params['price'] ?? null;
        $name  = sanitize_text_field( $params['name'] ?? '' );
        $stock = $params['stock'] ?? null;
        if ( ! $id ) {
            return 'Error: ID de producto no proporcionado.';
        }
        $product = wc_get_product( $id );
        if ( ! $product ) {
            return 'Producto no encontrado.';
        }
        if ( ! is_null( $price ) )  $product->set_price( floatval( $price ) );
        if ( ! is_null( $price ) )  $product->set_regular_price( floatval( $price ) );
        if ( ! empty( $name ) )     $product->set_name( $name );
        if ( ! is_null( $stock ) )  $product->set_stock_quantity( intval( $stock ) );
        $product->save();
        return "Producto ID {$id} actualizado correctamente.";
    }

    private function action_inject_html( $params ) {
        $page_id = intval( $params['page_id'] ?? 0 );
        $html    = wp_kses_post( $params['html'] ?? '' );
        if ( ! $page_id || empty( $html ) ) {
            return 'Error: Se necesita page_id y html.';
        }
        $page = get_post( $page_id );
        if ( ! $page ) {
            return 'Pagina no encontrada.';
        }
        $new_content = $page->post_content . "\n" . $html;
        wp_update_post( array(
            'ID'           => $page_id,
            'post_content' => $new_content,
        ) );
        return "HTML inyectado correctamente en la pagina ID {$page_id}.";
    }
}
