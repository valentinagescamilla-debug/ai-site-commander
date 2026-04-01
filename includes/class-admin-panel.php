<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AISC_Admin_Panel {

    private $settings;

    public function __construct() {
        $this->settings = AISC_Security::get_settings();
        add_action( 'admin_menu',            array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init',            array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function add_admin_menu() {
        add_menu_page(
            'AI Site Commander',
            'AI Commander',
            'manage_options',
            'ai-site-commander',
            array( $this, 'render_page' ),
            'dashicons-superhero',
            3
        );
    }

    public function register_settings() {
        register_setting(
            'aisc_settings_group',
            'aisc_settings',
            array( 'sanitize_callback' => array( $this, 'sanitize_settings' ) )
        );
    }

    public function sanitize_settings( $input ) {
        return array(
            'ai_provider'       => sanitize_text_field( $input['ai_provider'] ?? 'anthropic' ),
            'ai_model'          => sanitize_text_field( $input['ai_model'] ?? 'claude-sonnet-4-5' ),
            'api_key'           => sanitize_text_field( $input['api_key'] ?? '' ),
            'openrouter_key'    => sanitize_text_field( $input['openrouter_key'] ?? '' ),
            'google_key'        => sanitize_text_field( $input['google_key'] ?? '' ),
            'widget_position'   => sanitize_text_field( $input['widget_position'] ?? 'bottom-right' ),
            'widget_size'       => sanitize_text_field( $input['widget_size'] ?? 'medium' ),
            'widget_color'      => sanitize_hex_color( $input['widget_color'] ?? '#6366f1' ),
            'widget_title'      => sanitize_text_field( $input['widget_title'] ?? 'AI Commander' ),
            'chat_height'       => absint( $input['chat_height'] ?? 450 ),
            'chat_width'        => absint( $input['chat_width'] ?? 380 ),
            'welcome_message'   => sanitize_textarea_field( $input['welcome_message'] ?? '' ),
            'system_prompt'     => sanitize_textarea_field( $input['system_prompt'] ?? '' ),
            'whatsapp_enabled'  => isset( $input['whatsapp_enabled'] ) ? '1' : '0',
            'whatsapp_token'    => sanitize_text_field( $input['whatsapp_token'] ?? '' ),
            'whatsapp_phone_id' => sanitize_text_field( $input['whatsapp_phone_id'] ?? '' ),
            'whatsapp_verify'   => sanitize_text_field( $input['whatsapp_verify'] ?? 'aisc_verify_2024' ),
            'show_on_mobile'    => isset( $input['show_on_mobile'] ) ? '1' : '0',
            'max_tokens'        => absint( $input['max_tokens'] ?? 2048 ),
            'temperature'       => floatval( $input['temperature'] ?? 0.3 ),
        );
    }

    public function enqueue_assets( $hook ) {
        if ( 'toplevel_page_ai-site-commander' !== $hook ) {
            return;
        }
        wp_enqueue_style( 'aisc-admin', AISC_PLUGIN_URL . 'assets/css/admin.css', array(), AISC_VERSION );
        wp_enqueue_script( 'aisc-admin', AISC_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), AISC_VERSION, true );
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Sin permisos.' );
        }
        $s = $this->settings;
        $has_key = ! empty( $s['api_key'] ) || ! empty( $s['google_key'] ) || ! empty( $s['openrouter_key'] );
        ?>
        <div class="wrap aisc-wrap">
            <div class="aisc-header">
                <h1>AI Site Commander</h1>
                <p>Controla tu sitio WordPress completo desde un chat inteligente</p>
                <span class="aisc-status <?php echo $has_key ? 'ok' : 'warn'; ?>">
                    <?php echo $has_key ? 'Activo' : 'Configura tu API Key para comenzar'; ?>
                </span>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields( 'aisc_settings_group' ); ?>

                <div class="aisc-tabs">
                    <button type="button" class="aisc-tab active" data-tab="ia">IA</button>
                    <button type="button" class="aisc-tab" data-tab="widget">Widget</button>
                    <button type="button" class="aisc-tab" data-tab="whatsapp">WhatsApp</button>
                    <button type="button" class="aisc-tab" data-tab="avanzado">Avanzado</button>
                </div>

                <div class="aisc-tab-content active" id="tab-ia">
                    <div class="aisc-card">
                        <h2>Proveedor de IA y API Key</h2>
                        <table class="form-table">
                            <tr>
                                <th>Proveedor</th>
                                <td>
                                    <label><input type="radio" name="aisc_settings[ai_provider]" value="anthropic" <?php checked( $s['ai_provider'], 'anthropic' ); ?>> Anthropic Claude (Recomendado)</label><br>
                                    <label><input type="radio" name="aisc_settings[ai_provider]" value="google" <?php checked( $s['ai_provider'], 'google' ); ?>> Google Gemini</label><br>
                                    <label><input type="radio" name="aisc_settings[ai_provider]" value="openrouter" <?php checked( $s['ai_provider'], 'openrouter' ); ?>> OpenRouter</label>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="api_key">API Key Anthropic</label></th>
                                <td>
                                    <input type="password" id="api_key" name="aisc_settings[api_key]" value="<?php echo esc_attr( $s['api_key'] ); ?>" class="regular-text" placeholder="sk-ant-api03-...">
                                    <p class="description"><a href="https://console.anthropic.com" target="_blank">Obtener key en console.anthropic.com</a></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="google_key">API Key Google</label></th>
                                <td>
                                    <input type="password" id="google_key" name="aisc_settings[google_key]" value="<?php echo esc_attr( $s['google_key'] ); ?>" class="regular-text" placeholder="AIza...">
                                    <p class="description"><a href="https://aistudio.google.com" target="_blank">Obtener key en aistudio.google.com</a></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="openrouter_key">API Key OpenRouter</label></th>
                                <td>
                                    <input type="password" id="openrouter_key" name="aisc_settings[openrouter_key]" value="<?php echo esc_attr( $s['openrouter_key'] ); ?>" class="regular-text" placeholder="sk-or-v1-...">
                                    <p class="description"><a href="https://openrouter.ai/keys" target="_blank">Obtener key en openrouter.ai</a></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="ai_model">Modelo</label></th>
                                <td>
                                    <select name="aisc_settings[ai_model]" id="ai_model" class="regular-text">
                                        <option value="claude-sonnet-4-5" <?php selected( $s['ai_model'], 'claude-sonnet-4-5' ); ?>>Claude Sonnet 4.5 (Recomendado)</option>
                                        <option value="claude-opus-4-5" <?php selected( $s['ai_model'], 'claude-opus-4-5' ); ?>>Claude Opus 4.5 (Maximo)</option>
                                        <option value="claude-haiku-3-5" <?php selected( $s['ai_model'], 'claude-haiku-3-5' ); ?>>Claude Haiku 3.5 (Rapido)</option>
                                        <option value="gemini-2.5-pro" <?php selected( $s['ai_model'], 'gemini-2.5-pro' ); ?>>Gemini 2.5 Pro</option>
                                        <option value="gemini-2.0-flash" <?php selected( $s['ai_model'], 'gemini-2.0-flash' ); ?>>Gemini 2.0 Flash</option>
                                        <option value="meta-llama/llama-3.3-70b-instruct" <?php selected( $s['ai_model'], 'meta-llama/llama-3.3-70b-instruct' ); ?>>Llama 3.3 70B (OpenRouter)</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="max_tokens">Tokens maximos</label></th>
                                <td>
                                    <input type="number" id="max_tokens" name="aisc_settings[max_tokens]" value="<?php echo esc_attr( $s['max_tokens'] ); ?>" min="256" max="8192" class="small-text">
                                    <p class="description">Recomendado: 2048</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="temperature">Temperatura</label></th>
                                <td>
                                    <input type="number" id="temperature" name="aisc_settings[temperature]" value="<?php echo esc_attr( $s['temperature'] ); ?>" min="0" max="1" step="0.1" class="small-text">
                                    <p class="description">0 = preciso, 1 = creativo. Recomendado: 0.3</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="system_prompt">Prompt del sistema</label></th>
                                <td>
                                    <textarea id="system_prompt" name="aisc_settings[system_prompt]" rows="5" class="large-text"><?php echo esc_textarea( $s['system_prompt'] ); ?></textarea>
                                    <p class="description">Define la personalidad de tu asistente IA</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="aisc-tab-content" id="tab-widget">
                    <div class="aisc-card">
                        <h2>Apariencia del Chat Flotante</h2>
                        <table class="form-table">
                            <tr>
                                <th><label for="widget_title">Titulo del chat</label></th>
                                <td>
                                    <input type="text" id="widget_title" name="aisc_settings[widget_title]" value="<?php echo esc_attr( $s['widget_title'] ); ?>" class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th><label for="welcome_message">Mensaje de bienvenida</label></th>
                                <td>
                                    <textarea id="welcome_message" name="aisc_settings[welcome_message]" rows="3" class="large-text"><?php echo esc_textarea( $s['welcome_message'] ); ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="widget_color">Color principal</label></th>
                                <td>
                                    <input type="color" id="widget_color" name="aisc_settings[widget_color]" value="<?php echo esc_attr( $s['widget_color'] ); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th>Posicion en pantalla</th>
                                <td>
                                    <label><input type="radio" name="aisc_settings[widget_position]" value="bottom-right" <?php checked( $s['widget_position'], 'bottom-right' ); ?>> Abajo Derecha</label><br>
                                    <label><input type="radio" name="aisc_settings[widget_position]" value="bottom-left" <?php checked( $s['widget_position'], 'bottom-left' ); ?>> Abajo Izquierda</label><br>
                                    <label><input type="radio" name="aisc_settings[widget_position]" value="top-right" <?php checked( $s['widget_position'], 'top-right' ); ?>> Arriba Derecha</label><br>
                                    <label><input type="radio" name="aisc_settings[widget_position]" value="top-left" <?php checked( $s['widget_position'], 'top-left' ); ?>> Arriba Izquierda</label>
                                </td>
                            </tr>
                            <tr>
                                <th>Tamano del chat</th>
                                <td>
                                    <label><input type="radio" name="aisc_settings[widget_size]" value="small" <?php checked( $s['widget_size'], 'small' ); ?>> Pequeno (320x380px)</label><br>
                                    <label><input type="radio" name="aisc_settings[widget_size]" value="medium" <?php checked( $s['widget_size'], 'medium' ); ?>> Mediano (380x450px)</label><br>
                                    <label><input type="radio" name="aisc_settings[widget_size]" value="large" <?php checked( $s['widget_size'], 'large' ); ?>> Grande (460x580px)</label>
                                </td>
                            </tr>
                            <tr>
                                <th>Mostrar en movil</th>
                                <td>
                                    <label><input type="checkbox" name="aisc_settings[show_on_mobile]" value="1" <?php checked( $s['show_on_mobile'], '1' ); ?>> Mostrar el chat en dispositivos moviles</label>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="aisc-tab-content" id="tab-whatsapp">
                    <div class="aisc-card">
                        <h2>Integracion WhatsApp Business</h2>
                        <p class="description">Controla tu sitio web desde WhatsApp. Requiere cuenta de Meta for Developers.</p>
                        <table class="form-table">
                            <tr>
                                <th>Activar WhatsApp</th>
                                <td>
                                    <label><input type="checkbox" name="aisc_settings[whatsapp_enabled]" value="1" <?php checked( $s['whatsapp_enabled'], '1' ); ?>> Activar control via WhatsApp Business</label>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="whatsapp_token">Token de acceso</label></th>
                                <td>
                                    <input type="password" id="whatsapp_token" name="aisc_settings[whatsapp_token]" value="<?php echo esc_attr( $s['whatsapp_token'] ); ?>" class="regular-text" placeholder="EAAx...">
                                    <p class="description"><a href="https://developers.facebook.com" target="_blank">Obtener en developers.facebook.com</a></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="whatsapp_phone_id">Phone ID</label></th>
                                <td>
                                    <input type="text" id="whatsapp_phone_id" name="aisc_settings[whatsapp_phone_id]" value="<?php echo esc_attr( $s['whatsapp_phone_id'] ); ?>" class="regular-text" placeholder="123456789">
                                </td>
                            </tr>
                            <tr>
                                <th><label for="whatsapp_verify">Token de verificacion</label></th>
                                <td>
                                    <input type="text" id="whatsapp_verify" name="aisc_settings[whatsapp_verify]" value="<?php echo esc_attr( $s['whatsapp_verify'] ); ?>" class="regular-text">
                                    <p class="description">Tu URL de webhook es: <code><?php echo esc_url( rest_url( 'aisc/v1/whatsapp' ) ); ?></code></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="aisc-tab-content" id="tab-avanzado">
                    <div class="aisc-card">
                        <h2>Opciones Avanzadas</h2>
                        <table class="form-table">
                            <tr>
                                <th>Informacion del sistema</th>
                                <td>
                                    <p>Version del plugin: <strong><?php echo AISC_VERSION; ?></strong></p>
                                    <p>WordPress version: <strong><?php echo get_bloginfo( 'version' ); ?></strong></p>
                                    <p>PHP version: <strong><?php echo PHP_VERSION; ?></strong></p>
                                    <p>URL del sitio: <strong><?php echo get_site_url(); ?></strong></p>
                                </td>
                            </tr>
                            <tr>
                                <th>Limpiar historial</th>
                                <td>
                                    <button type="button" id="aisc-clear-history" class="button button-secondary">Limpiar historial del chat</button>
                                    <p class="description">Borra todo el historial de conversaciones guardado</p>
                                </td>
                            </tr>
                            <tr>
                                <th>Restablecer ajustes</th>
                                <td>
                                    <button type="button" id="aisc-reset-settings" class="button button-secondary">Restablecer valores por defecto</button>
                                    <p class="description">Cuidado: esto borrara todas tus configuraciones</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="aisc-save-bar">
                    <?php submit_button( 'Guardar cambios', 'primary large', 'submit', false ); ?>
                </div>

            </form>
        </div>
        <?php
    }
}
