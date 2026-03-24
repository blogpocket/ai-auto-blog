<?php
/**
 * Clase para la página de administración del plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Auto_Blog_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
        
        // AJAX handlers
        add_action('wp_ajax_ai_auto_blog_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_ai_auto_blog_generate_now', array($this, 'ajax_generate_now'));
        add_action('wp_ajax_ai_auto_blog_detect_models', array($this, 'ajax_detect_models'));
    }
    
    /**
     * Manejar acciones del admin
     */
    public function handle_admin_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Resetear estadísticas de temas
        if (isset($_GET['action']) && sanitize_text_field(wp_unslash($_GET['action'])) === 'reset_topic_stats') {
            if (isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'reset_topic_stats')) {
                AI_Auto_Blog_Topic_Randomizer::reset_stats();
                wp_safe_redirect(admin_url('admin.php?page=ai-auto-blog&tab=stats&reset=stats'));
                exit;
            }
        }
        
        // Resetear rotación de temas
        if (isset($_GET['action']) && sanitize_text_field(wp_unslash($_GET['action'])) === 'reset_topic_rotation') {
            if (isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'reset_topic_rotation')) {
                AI_Auto_Blog_Topic_Randomizer::reset_rotation();
                wp_safe_redirect(admin_url('admin.php?page=ai-auto-blog&tab=stats&reset=rotation'));
                exit;
            }
        }
    }
    
    /**
     * Añadir menú de administración
     */
    public function add_admin_menu() {
        add_options_page(
            __('AI Auto Blog', 'ai-auto-blog'),
            __('AI Auto Blog', 'ai-auto-blog'),
            'manage_options',
            'ai-auto-blog',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Registrar configuraciones
     */
    public function register_settings() {
        register_setting(
            'ai_auto_blog_settings_group',
            'ai_auto_blog_settings',
            array($this, 'sanitize_settings')
        );
    }
    
    /**
     * Sanitizar configuraciones
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        if (isset($input['api_key'])) {
            $sanitized['api_key'] = sanitize_text_field($input['api_key']);
        }
        
        if (isset($input['prompt'])) {
            $sanitized['prompt'] = wp_kses_post($input['prompt']);
        }
        
        if (isset($input['length'])) {
            $allowed_lengths = array('brief', 'medium', 'long');
            $sanitized['length'] = in_array($input['length'], $allowed_lengths) ? $input['length'] : 'medium';
        }
        
        if (isset($input['frequency'])) {
            $allowed_frequencies = array('manual', 'daily', 'weekly', 'monthly');
            $sanitized['frequency'] = in_array($input['frequency'], $allowed_frequencies) ? $input['frequency'] : 'manual';
        }
        
        if (isset($input['post_status'])) {
            $allowed_statuses = array('draft', 'publish');
            $sanitized['post_status'] = in_array($input['post_status'], $allowed_statuses) ? $input['post_status'] : 'draft';
        }
        
        if (isset($input['model'])) {
            $allowed_models = array(
                // Gemini 2.x (más recientes)
                'gemini-2.5-flash',
                'gemini-2.5-pro',
                'gemini-2.0-flash',
                'gemini-2.0-flash-001',
                'gemini-2.0-flash-lite-001',
                'gemini-2.0-flash-lite',
                'gemini-2.5-flash-lite',
                // Gemini 1.5.x (legacy)
                'gemini-1.5-flash-latest',
                'gemini-1.5-pro-latest',
                'gemini-1.5-flash',
                'gemini-1.5-pro',
                'gemini-pro'
            );
            $sanitized['model'] = in_array($input['model'], $allowed_models) ? $input['model'] : 'gemini-2.5-flash';
        }
        
        if (isset($input['generate_image'])) {
            $sanitized['generate_image'] = $input['generate_image'] === 'yes' ? 'yes' : 'no';
        }
        
        if (isset($input['email_notifications'])) {
            $sanitized['email_notifications'] = $input['email_notifications'] === 'yes' ? 'yes' : 'no';
        }
        
        // Sanitizar opciones de temas aleatorios
        if (isset($input['use_random_topics'])) {
            $sanitized['use_random_topics'] = $input['use_random_topics'] === 'yes' ? 'yes' : 'no';
        }
        
        if (isset($input['use_topic_rotation'])) {
            $sanitized['use_topic_rotation'] = $input['use_topic_rotation'] === 'yes' ? 'yes' : 'no';
        }
        
        if (isset($input['topics'])) {
            $topics_string = sanitize_textarea_field($input['topics']);
            
            // Validar temas
            $validation = AI_Auto_Blog_Topic_Randomizer::validate_topics($topics_string);
            
            if ($validation['valid']) {
                $sanitized['topics'] = $topics_string;
                add_settings_error(
                    'ai_auto_blog_messages',
                    'topics_validated',
                    $validation['message'],
                    'success'
                );
            } else {
                // Mantener valor anterior si no es válido
                $old_settings = get_option('ai_auto_blog_settings', array());
                $sanitized['topics'] = isset($old_settings['topics']) ? $old_settings['topics'] : '';
                
                add_settings_error(
                    'ai_auto_blog_messages',
                    'topics_error',
                    $validation['message'],
                    'error'
                );
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Renderizar página de administración
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Obtener configuración actual
        $settings = get_option('ai_auto_blog_settings', array());
        
        // Valores por defecto
        $defaults = array(
            'api_key' => '',
            'prompt' => 'Escribe un artículo informativo y bien estructurado sobre temas de actualidad, tecnología o cultura.',
            'length' => 'medium',
            'frequency' => 'manual',
            'post_status' => 'draft',
            'model' => 'gemini-2.5-flash',
            'generate_image' => 'no',
            'email_notifications' => 'no',
            'use_random_topics' => 'no',
            'topics' => '',
            'use_topic_rotation' => 'yes'
        );
        
        $settings = wp_parse_args($settings, $defaults);
        
        // Obtener estadísticas
        $stats = AI_Auto_Blog_Post_Generator::get_stats();
        $logs = AI_Auto_Blog_Post_Generator::get_logs(5);
        $next_scheduled = AI_Auto_Blog_Scheduler::get_next_scheduled();
        
        // Determinar tab activo
        $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'settings';
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php settings_errors('ai_auto_blog_messages'); ?>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=ai-auto-blog&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Configuración', 'ai-auto-blog'); ?>
                </a>
                <a href="?page=ai-auto-blog&tab=posts" class="nav-tab <?php echo $active_tab === 'posts' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Posts Generados', 'ai-auto-blog'); ?>
                </a>
                <a href="?page=ai-auto-blog&tab=stats" class="nav-tab <?php echo $active_tab === 'stats' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Estadísticas', 'ai-auto-blog'); ?>
                </a>
                <a href="?page=ai-auto-blog&tab=diagnostics" class="nav-tab <?php echo $active_tab === 'diagnostics' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('🔧 Diagnóstico Cron', 'ai-auto-blog'); ?>
                </a>
            </h2>
            
            <?php if ($active_tab === 'settings') : ?>
                <form method="post" action="options.php" id="ai-auto-blog-form">
                    <?php settings_fields('ai_auto_blog_settings_group'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="api_key"><?php esc_html_e('API Key de Gemini', 'ai-auto-blog'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="api_key" 
                                       name="ai_auto_blog_settings[api_key]" 
                                       value="<?php echo esc_attr($settings['api_key']); ?>" 
                                       class="regular-text" 
                                       placeholder="AIza...">
                                <p class="description">
                                    <?php esc_html_e('Obtén tu API key desde ', 'ai-auto-blog'); ?>
                                    <a href="https://makersuite.google.com/app/apikey" target="_blank">Google AI Studio</a>
                                </p>
                                <button type="button" id="test-connection" class="button button-secondary">
                                    <?php esc_html_e('Probar Conexión', 'ai-auto-blog'); ?>
                                </button>
                                <span id="test-result"></span>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="model"><?php esc_html_e('Modelo de IA', 'ai-auto-blog'); ?></label>
                            </th>
                            <td>
                                <fieldset>
                                    <legend class="screen-reader-text"><?php esc_html_e('Seleccionar modelo', 'ai-auto-blog'); ?></legend>
                                    
                                    <strong><?php esc_html_e('Gemini 2.x (Más Recientes)', 'ai-auto-blog'); ?></strong><br>
                                    <label style="margin-left: 20px;">
                                        <input type="radio" 
                                               name="ai_auto_blog_settings[model]" 
                                               value="gemini-2.5-flash" 
                                               <?php checked($settings['model'], 'gemini-2.5-flash'); ?>>
                                        <?php esc_html_e('Gemini 2.5 Flash (RECOMENDADO - rápido)', 'ai-auto-blog'); ?>
                                    </label><br>
                                    <label style="margin-left: 20px;">
                                        <input type="radio" 
                                               name="ai_auto_blog_settings[model]" 
                                               value="gemini-2.5-pro" 
                                               <?php checked($settings['model'], 'gemini-2.5-pro'); ?>>
                                        <?php esc_html_e('Gemini 2.5 Pro (más potente)', 'ai-auto-blog'); ?>
                                    </label><br>
                                    <label style="margin-left: 20px;">
                                        <input type="radio" 
                                               name="ai_auto_blog_settings[model]" 
                                               value="gemini-2.0-flash" 
                                               <?php checked($settings['model'], 'gemini-2.0-flash'); ?>>
                                        <?php esc_html_e('Gemini 2.0 Flash', 'ai-auto-blog'); ?>
                                    </label><br>
                                    <label style="margin-left: 20px;">
                                        <input type="radio" 
                                               name="ai_auto_blog_settings[model]" 
                                               value="gemini-2.5-flash-lite" 
                                               <?php checked($settings['model'], 'gemini-2.5-flash-lite'); ?>>
                                        <?php esc_html_e('Gemini 2.5 Flash Lite (ligero)', 'ai-auto-blog'); ?>
                                    </label><br>
                                    <label style="margin-left: 20px;">
                                        <input type="radio" 
                                               name="ai_auto_blog_settings[model]" 
                                               value="gemini-2.0-flash-lite" 
                                               <?php checked($settings['model'], 'gemini-2.0-flash-lite'); ?>>
                                        <?php esc_html_e('Gemini 2.0 Flash Lite', 'ai-auto-blog'); ?>
                                    </label><br>
                                    
                                    <br><strong><?php esc_html_e('Gemini 1.5.x (Legacy)', 'ai-auto-blog'); ?></strong><br>
                                    <label style="margin-left: 20px;">
                                        <input type="radio" 
                                               name="ai_auto_blog_settings[model]" 
                                               value="gemini-1.5-flash-latest" 
                                               <?php checked($settings['model'], 'gemini-1.5-flash-latest'); ?>>
                                        <?php esc_html_e('Gemini 1.5 Flash Latest', 'ai-auto-blog'); ?>
                                    </label><br>
                                    <label style="margin-left: 20px;">
                                        <input type="radio" 
                                               name="ai_auto_blog_settings[model]" 
                                               value="gemini-1.5-pro-latest" 
                                               <?php checked($settings['model'], 'gemini-1.5-pro-latest'); ?>>
                                        <?php esc_html_e('Gemini 1.5 Pro Latest', 'ai-auto-blog'); ?>
                                    </label><br>
                                    <label style="margin-left: 20px;">
                                        <input type="radio" 
                                               name="ai_auto_blog_settings[model]" 
                                               value="gemini-pro" 
                                               <?php checked($settings['model'], 'gemini-pro'); ?>>
                                        <?php esc_html_e('Gemini Pro (fallback)', 'ai-auto-blog'); ?>
                                    </label>
                                </fieldset>
                                <p class="description">
                                    <?php esc_html_e('Se recomienda usar Gemini 2.5 Flash para mejor rendimiento. Usa el botón de abajo para verificar disponibilidad.', 'ai-auto-blog'); ?>
                                </p>
                                <?php if (!empty($settings['api_key'])) : ?>
                                    <button type="button" id="detect-models" class="button button-secondary" style="margin-top: 10px;">
                                        <?php esc_html_e('🔍 Detectar Modelos Disponibles', 'ai-auto-blog'); ?>
                                    </button>
                                    <div id="available-models" style="margin-top: 10px;"></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="prompt"><?php esc_html_e('Prompt del Sistema', 'ai-auto-blog'); ?></label>
                            </th>
                            <td>
                                <textarea id="prompt" 
                                          name="ai_auto_blog_settings[prompt]" 
                                          rows="5" 
                                          class="large-text"><?php echo esc_textarea($settings['prompt']); ?></textarea>
                                <p class="description">
                                    <?php esc_html_e('Define el criterio, estilo y tema para los posts generados. Usa {TOPIC} para insertar el tema aleatorio.', 'ai-auto-blog'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e('🎲 Temas Aleatorios', 'ai-auto-blog'); ?></label>
                            </th>
                            <td>
                                <label style="margin-bottom: 15px; display: block;">
                                    <input type="checkbox" 
                                           name="ai_auto_blog_settings[use_random_topics]" 
                                           value="yes" 
                                           <?php checked($settings['use_random_topics'], 'yes'); ?>>
                                    <?php esc_html_e('Activar generación con temas aleatorios', 'ai-auto-blog'); ?>
                                </label>
                                
                                <div id="random-topics-config" style="margin-left: 25px; <?php echo $settings['use_random_topics'] !== 'yes' ? 'display:none;' : ''; ?>">
                                    <p>
                                        <label for="topics">
                                            <strong><?php esc_html_e('Lista de Temas (separados por comas):', 'ai-auto-blog'); ?></strong>
                                        </label>
                                    </p>
                                    <textarea id="topics" 
                                              name="ai_auto_blog_settings[topics]" 
                                              rows="4" 
                                              class="large-text" 
                                              placeholder="<?php esc_html_e('Ej: Tecnología, Inteligencia Artificial, Marketing Digital, Salud, Finanzas', 'ai-auto-blog'); ?>"><?php echo esc_textarea($settings['topics']); ?></textarea>
                                    <p class="description">
                                        <?php esc_html_e('Lista de temas sobre los que generar contenido. Si se deja vacío, se usarán temas por defecto.', 'ai-auto-blog'); ?>
                                    </p>
                                    
                                    <p style="margin-top: 15px;">
                                        <label>
                                            <input type="checkbox" 
                                                   name="ai_auto_blog_settings[use_topic_rotation]" 
                                                   value="yes" 
                                                   <?php checked($settings['use_topic_rotation'], 'yes'); ?>>
                                            <?php esc_html_e('Usar rotación de temas (evita repetir el mismo tema consecutivamente)', 'ai-auto-blog'); ?>
                                        </label>
                                    </p>
                                    
                                    <?php
                                    // Mostrar temas actuales
                                    $current_topics = AI_Auto_Blog_Topic_Randomizer::get_random_topic();
                                    $all_topics_preview = array();
                                    for ($i = 0; $i < 5; $i++) {
                                        $all_topics_preview[] = AI_Auto_Blog_Topic_Randomizer::get_random_topic();
                                    }
                                    $all_topics_preview = array_unique($all_topics_preview);
                                    ?>
                                    
                                    <div style="margin-top: 15px; padding: 10px; background: #f0f0f1; border-left: 4px solid #2271b1;">
                                        <p style="margin: 0 0 10px 0;"><strong><?php esc_html_e('Vista previa de temas:', 'ai-auto-blog'); ?></strong></p>
                                        <p style="margin: 0;">
                                            <?php echo esc_html(implode(' • ', $all_topics_preview)); ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <script>
                                jQuery(document).ready(function($) {
                                    $('input[name="ai_auto_blog_settings[use_random_topics]"]').on('change', function() {
                                        if ($(this).is(':checked')) {
                                            $('#random-topics-config').slideDown();
                                        } else {
                                            $('#random-topics-config').slideUp();
                                        }
                                    });
                                });
                                </script>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e('Longitud del Post', 'ai-auto-blog'); ?></label>
                            </th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="radio" 
                                               name="ai_auto_blog_settings[length]" 
                                               value="brief" 
                                               <?php checked($settings['length'], 'brief'); ?>>
                                        <?php esc_html_e('Breve (~500 palabras)', 'ai-auto-blog'); ?>
                                    </label><br>
                                    <label>
                                        <input type="radio" 
                                               name="ai_auto_blog_settings[length]" 
                                               value="medium" 
                                               <?php checked($settings['length'], 'medium'); ?>>
                                        <?php esc_html_e('Medio (~1000 palabras)', 'ai-auto-blog'); ?>
                                    </label><br>
                                    <label>
                                        <input type="radio" 
                                               name="ai_auto_blog_settings[length]" 
                                               value="long" 
                                               <?php checked($settings['length'], 'long'); ?>>
                                        <?php esc_html_e('Largo (~2000 palabras)', 'ai-auto-blog'); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e('Estado de Publicación', 'ai-auto-blog'); ?></label>
                            </th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="radio" 
                                               name="ai_auto_blog_settings[post_status]" 
                                               value="draft" 
                                               <?php checked($settings['post_status'], 'draft'); ?>>
                                        <?php esc_html_e('Borrador (requiere revisión)', 'ai-auto-blog'); ?>
                                    </label><br>
                                    <label>
                                        <input type="radio" 
                                               name="ai_auto_blog_settings[post_status]" 
                                               value="publish" 
                                               <?php checked($settings['post_status'], 'publish'); ?>>
                                        <?php esc_html_e('Publicar automáticamente', 'ai-auto-blog'); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e('Generar Imagen Destacada', 'ai-auto-blog'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           name="ai_auto_blog_settings[generate_image]" 
                                           value="yes" 
                                           <?php checked($settings['generate_image'], 'yes'); ?>>
                                    <?php esc_html_e('Sí, intentar generar con Imagen 3.0', 'ai-auto-blog'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('⚠️ Nota: La API de Google Imagen 3.0 aún no está disponible públicamente. Esta opción está preparada para cuando se habilite. Mientras tanto, puedes añadir imágenes destacadas manualmente editando los posts.', 'ai-auto-blog'); ?>
                                </p>
                                <p class="description">
                                    <?php esc_html_e('Se recomienda desactivar esta opción por ahora para evitar mensajes de advertencia.', 'ai-auto-blog'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e('Periodicidad', 'ai-auto-blog'); ?></label>
                            </th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="radio" 
                                               name="ai_auto_blog_settings[frequency]" 
                                               value="manual" 
                                               <?php checked($settings['frequency'], 'manual'); ?>>
                                        <?php esc_html_e('Manual', 'ai-auto-blog'); ?>
                                    </label><br>
                                    <label>
                                        <input type="radio" 
                                               name="ai_auto_blog_settings[frequency]" 
                                               value="daily" 
                                               <?php checked($settings['frequency'], 'daily'); ?>>
                                        <?php esc_html_e('Diaria', 'ai-auto-blog'); ?>
                                    </label><br>
                                    <label>
                                        <input type="radio" 
                                               name="ai_auto_blog_settings[frequency]" 
                                               value="weekly" 
                                               <?php checked($settings['frequency'], 'weekly'); ?>>
                                        <?php esc_html_e('Semanal', 'ai-auto-blog'); ?>
                                    </label><br>
                                    <label>
                                        <input type="radio" 
                                               name="ai_auto_blog_settings[frequency]" 
                                               value="monthly" 
                                               <?php checked($settings['frequency'], 'monthly'); ?>>
                                        <?php esc_html_e('Mensual', 'ai-auto-blog'); ?>
                                    </label>
                                </fieldset>
                                <?php if ($next_scheduled['scheduled']) : ?>
                                    <p class="description">
                                        <strong><?php echo esc_html($next_scheduled['message']); ?></strong>
                                    </p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e('Notificaciones por Email', 'ai-auto-blog'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           name="ai_auto_blog_settings[email_notifications]" 
                                           value="yes" 
                                           <?php checked($settings['email_notifications'], 'yes'); ?>>
                                    <?php esc_html_e('Enviar email cuando se genera un post', 'ai-auto-blog'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('Guardar Configuración', 'ai-auto-blog')); ?>
                </form>
                
                <?php if ($settings['frequency'] === 'manual') : ?>
                    <hr>
                    <h2><?php esc_html_e('Generar Post Manualmente', 'ai-auto-blog'); ?></h2>
                    <p><?php esc_html_e('Haz clic en el botón para generar un nuevo post ahora mismo.', 'ai-auto-blog'); ?></p>
                    <button type="button" id="generate-now" class="button button-primary button-large">
                        <?php esc_html_e('Generar Post Ahora', 'ai-auto-blog'); ?>
                    </button>
                    <span id="generate-result"></span>
                    <div id="generation-output" style="margin-top: 20px;"></div>
                <?php endif; ?>
                
            <?php elseif ($active_tab === 'posts') : ?>
                <h2><?php esc_html_e('Últimos Posts Generados', 'ai-auto-blog'); ?></h2>
                
                <?php if (empty($logs)) : ?>
                    <p><?php esc_html_e('No se han generado posts todavía.', 'ai-auto-blog'); ?></p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Fecha', 'ai-auto-blog'); ?></th>
                                <th><?php esc_html_e('Tipo', 'ai-auto-blog'); ?></th>
                                <th><?php esc_html_e('Mensaje', 'ai-auto-blog'); ?></th>
                                <th><?php esc_html_e('Post', 'ai-auto-blog'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log) : ?>
                                <tr>
                                    <td><?php echo esc_html($log['timestamp']); ?></td>
                                    <td>
                                        <?php 
                                        $badge_class = 'success' === $log['type'] ? 'success' : ('error' === $log['type'] ? 'error' : 'warning');
                                        ?>
                                        <span class="log-badge log-<?php echo esc_attr($badge_class); ?>">
                                            <?php echo esc_html(ucfirst($log['type'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($log['message']); ?></td>
                                    <td>
                                        <?php if ($log['post_id']) : ?>
                                            <a href="<?php echo esc_url(get_edit_post_link($log['post_id'])); ?>" target="_blank">
                                                <?php esc_html_e('Editar', 'ai-auto-blog'); ?>
                                            </a>
                                        <?php else : ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
            <?php elseif ($active_tab === 'stats') : ?>
                <h2><?php esc_html_e('Estadísticas de Generación', 'ai-auto-blog'); ?></h2>
                
                <div class="ai-auto-blog-stats">
                    <div class="stat-box">
                        <div class="stat-number"><?php echo esc_html($stats['total_generated']); ?></div>
                        <div class="stat-label"><?php esc_html_e('Posts Generados', 'ai-auto-blog'); ?></div>
                    </div>
                    
                    <div class="stat-box">
                        <div class="stat-number"><?php echo esc_html($stats['published']); ?></div>
                        <div class="stat-label"><?php esc_html_e('Publicados', 'ai-auto-blog'); ?></div>
                    </div>
                    
                    <div class="stat-box">
                        <div class="stat-number"><?php echo esc_html($stats['draft']); ?></div>
                        <div class="stat-label"><?php esc_html_e('Borradores', 'ai-auto-blog'); ?></div>
                    </div>
                    
                    <div class="stat-box">
                        <div class="stat-number"><?php echo esc_html($settings['model']); ?></div>
                        <div class="stat-label"><?php esc_html_e('Modelo Actual', 'ai-auto-blog'); ?></div>
                    </div>
                </div>
                
                <h3><?php esc_html_e('Información del Sistema', 'ai-auto-blog'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Versión del Plugin', 'ai-auto-blog'); ?></th>
                        <td><?php echo esc_html(AI_AUTO_BLOG_VERSION); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('WordPress', 'ai-auto-blog'); ?></th>
                        <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('PHP', 'ai-auto-blog'); ?></th>
                        <td><?php echo esc_html(PHP_VERSION); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Estado del Cron', 'ai-auto-blog'); ?></th>
                        <td>
                            <?php 
                            if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
                                echo '<span style="color: red;">' . esc_html__('Deshabilitado', 'ai-auto-blog') . '</span>';
                            } else {
                                echo '<span style="color: green;">' . esc_html__('Activo', 'ai-auto-blog') . '</span>';
                            }
                            ?>
                        </td>
                    </tr>
                </table>
                
                <?php if ($settings['use_random_topics'] === 'yes') : ?>
                    <h3><?php esc_html_e('📊 Estadísticas de Temas', 'ai-auto-blog'); ?></h3>
                    <?php
                    $topic_stats = AI_Auto_Blog_Topic_Randomizer::get_topic_stats();
                    if (!empty($topic_stats)) :
                        arsort($topic_stats); // Ordenar por más usados
                    ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Tema', 'ai-auto-blog'); ?></th>
                                    <th style="width: 100px; text-align: center;"><?php esc_html_e('Posts Generados', 'ai-auto-blog'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topic_stats as $topic => $count) : ?>
                                    <tr>
                                        <td><?php echo esc_html($topic); ?></td>
                                        <td style="text-align: center;">
                                            <strong><?php echo esc_html($count); ?></strong>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <p style="margin-top: 15px;">
                            <button type="button" class="button" onclick="if(confirm('¿Resetear estadísticas de temas?')) { location.href='<?php echo esc_url(admin_url('admin.php?page=ai-auto-blog&action=reset_topic_stats&tab=stats&_wpnonce=' . wp_create_nonce('reset_topic_stats'))); ?>'; }">
                                <?php esc_html_e('🔄 Resetear Estadísticas de Temas', 'ai-auto-blog'); ?>
                            </button>
                            <button type="button" class="button" onclick="if(confirm('¿Resetear rotación de temas?')) { location.href='<?php echo esc_url(admin_url('admin.php?page=ai-auto-blog&action=reset_topic_rotation&tab=stats&_wpnonce=' . wp_create_nonce('reset_topic_rotation'))); ?>'; }">
                                <?php esc_html_e('🔄 Resetear Rotación', 'ai-auto-blog'); ?>
                            </button>
                        </p>
                    <?php else : ?>
                        <p><?php esc_html_e('No hay estadísticas de temas todavía. Genera algunos posts para ver las estadísticas.', 'ai-auto-blog'); ?></p>
                    <?php endif; ?>
                <?php endif; ?>
                
            <?php elseif ($active_tab === 'diagnostics') : ?>
                <h2><?php esc_html_e('🔧 Diagnóstico del Sistema Cron', 'ai-auto-blog'); ?></h2>
                
                <?php
                // Procesar acciones
                if (isset($_GET['action'])) {
                    $action = sanitize_text_field(wp_unslash($_GET['action']));
                    
                    if ($action === 'reschedule' && isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'reschedule_cron')) {
                        $result = AI_Auto_Blog_Cron_Diagnostics::force_reschedule();
                        ?>
                        <div class="notice notice-<?php echo $result['success'] ? 'success' : 'error'; ?> is-dismissible">
                            <p><strong><?php echo esc_html($result['message']); ?></strong></p>
                            <?php if ($result['success'] && isset($result['next_run'])) : ?>
                                <p>Próxima ejecución: <?php echo esc_html($result['next_run']); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php
                    }
                    
                    if ($action === 'test_generation' && isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'test_generation')) {
                        $result = AI_Auto_Blog_Cron_Diagnostics::test_generation();
                        ?>
                        <div class="notice notice-<?php echo $result['success'] ? 'success' : 'error'; ?> is-dismissible">
                            <p><strong><?php echo esc_html($result['message']); ?></strong></p>
                        </div>
                        <?php
                    }
                    
                    if ($action === 'clean_past' && isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'clean_past')) {
                        $result = AI_Auto_Blog_Cron_Diagnostics::clean_past_events();
                        ?>
                        <div class="notice notice-success is-dismissible">
                            <p><strong><?php echo esc_html($result['message']); ?></strong></p>
                        </div>
                        <?php
                    }
                }
                
                // Obtener diagnóstico
                $health = AI_Auto_Blog_Cron_Diagnostics::check_cron_health();
                $info = AI_Auto_Blog_Cron_Diagnostics::get_cron_info();
                ?>
                
                <div class="card">
                    <h3>Estado General</h3>
                    
                    <?php if ($health['status'] === 'healthy') : ?>
                        <p style="color: green; font-size: 16px;">✅ <strong>Todo funciona correctamente</strong></p>
                    <?php elseif ($health['status'] === 'warning') : ?>
                        <p style="color: orange; font-size: 16px;">⚠️ <strong>Hay advertencias que revisar</strong></p>
                    <?php else : ?>
                        <p style="color: red; font-size: 16px;">❌ <strong>Se detectaron problemas</strong></p>
                    <?php endif; ?>
                    
                    <?php if (!empty($health['issues'])) : ?>
                        <h4 style="color: red;">❌ Problemas Críticos:</h4>
                        <ul style="list-style: disc; margin-left: 20px;">
                            <?php foreach ($health['issues'] as $issue) : ?>
                                <li><?php echo esc_html($issue); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    
                    <?php if (!empty($health['warnings'])) : ?>
                        <h4 style="color: orange;">⚠️ Advertencias:</h4>
                        <ul style="list-style: disc; margin-left: 20px;">
                            <?php foreach ($health['warnings'] as $warning) : ?>
                                <li><?php echo esc_html($warning); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    
                    <?php if (!empty($health['info'])) : ?>
                        <h4>ℹ️ Información:</h4>
                        <ul style="list-style: disc; margin-left: 20px;">
                            <?php foreach ($health['info'] as $info_item) : ?>
                                <li><?php echo esc_html($info_item); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                
                <div class="card">
                    <h3>Información del Cron</h3>
                    <table class="form-table">
                        <tr>
                            <th>Estado del WP-Cron:</th>
                            <td><?php echo $info['cron_enabled'] ? '<span style="color:green">✅ Habilitado</span>' : '<span style="color:red">❌ Deshabilitado</span>'; ?></td>
                        </tr>
                        <tr>
                            <th>Frecuencia Configurada:</th>
                            <td><strong><?php echo esc_html(ucfirst($info['plugin_frequency'])); ?></strong></td>
                        </tr>
                        <tr>
                            <th>¿Hay Evento Programado?:</th>
                            <td><?php echo $info['is_scheduled'] ? '<span style="color:green">✅ Sí</span>' : '<span style="color:red">❌ No</span>'; ?></td>
                        </tr>
                        <?php if ($info['next_scheduled']) : ?>
                        <tr>
                            <th>Próxima Ejecución:</th>
                            <td><strong><?php echo esc_html($info['next_scheduled']); ?></strong></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <th>Hora del Servidor:</th>
                            <td><?php echo esc_html($info['server_time']); ?> (<?php echo esc_html($info['timezone']); ?>)</td>
                        </tr>
                        <tr>
                            <th>Total Eventos Cron:</th>
                            <td><?php echo esc_html($info['total_cron_events']); ?></td>
                        </tr>
                    </table>
                </div>
                
                <?php if (!empty($info['last_generated_posts'])) : ?>
                <div class="card">
                    <h3>Últimos Posts Generados</h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Título</th>
                                <th>Fecha</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($info['last_generated_posts'] as $post) : ?>
                                <tr>
                                    <td><?php echo esc_html($post['id']); ?></td>
                                    <td>
                                        <a href="<?php echo esc_url(get_edit_post_link($post['id'])); ?>" target="_blank">
                                            <?php echo esc_html($post['title']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo esc_html($post['date']); ?></td>
                                    <td><?php echo esc_html(ucfirst($post['status'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                
                <div class="card">
                    <h3>Acciones de Diagnóstico</h3>
                    <p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-auto-blog&tab=diagnostics&action=reschedule&_wpnonce=' . wp_create_nonce('reschedule_cron'))); ?>" 
                           class="button button-primary"
                           onclick="return confirm('¿Reprogramar el cron? Esto eliminará el evento actual y creará uno nuevo.');">
                            🔄 Reprogramar Cron
                        </a>
                        
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-auto-blog&tab=diagnostics&action=test_generation&_wpnonce=' . wp_create_nonce('test_generation'))); ?>" 
                           class="button"
                           onclick="return confirm('¿Generar un post de prueba ahora?');">
                            🧪 Probar Generación Manual
                        </a>
                        
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-auto-blog&tab=diagnostics&action=clean_past&_wpnonce=' . wp_create_nonce('clean_past'))); ?>" 
                           class="button"
                           onclick="return confirm('¿Limpiar eventos pasados del cron?');">
                            🧹 Limpiar Eventos Pasados
                        </a>
                    </p>
                </div>
                
                <?php
                $all_crons = AI_Auto_Blog_Cron_Diagnostics::list_all_cron_jobs();
                if (!empty($all_crons)) :
                ?>
                <div class="card">
                    <h3>Todos los Eventos Cron (WordPress)</h3>
                    <p style="color: #666;">Mostrando todos los eventos programados en WordPress</p>
                    <table class="wp-list-table widefat fixed striped" style="font-size: 12px;">
                        <thead>
                            <tr>
                                <th>Hook</th>
                                <th>Fecha Programada</th>
                                <th>Tiempo Restante</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($all_crons, 0, 20) as $event) : ?>
                                <tr <?php echo $event['is_past'] ? 'style="background:#ffebee;"' : ''; ?>>
                                    <td><code><?php echo esc_html($event['hook']); ?></code></td>
                                    <td><?php echo esc_html($event['formatted_time']); ?></td>
                                    <td><?php echo esc_html($event['time_until']); ?></td>
                                    <td>
                                        <?php if ($event['is_past']) : ?>
                                            <span style="color: red;">⚠️ Pasado</span>
                                        <?php else : ?>
                                            <span style="color: green;">✅ Futuro</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (count($all_crons) > 20) : ?>
                        <p><em>Mostrando 20 de <?php echo count($all_crons); ?> eventos</em></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * AJAX: Probar conexión con API
     */
    public function ajax_test_connection() {
        check_ajax_referer('ai_auto_blog_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('No tienes permisos', 'ai-auto-blog')));
        }
        
        $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';
        $model = isset($_POST['model']) ? sanitize_text_field(wp_unslash($_POST['model'])) : 'gemini-1.5-flash';
        
        if (empty($api_key)) {
            wp_send_json_error(array('message' => __('API key vacía', 'ai-auto-blog')));
        }
        
        $gemini = new AI_Auto_Blog_Gemini_API($api_key, $model);
        $result = $gemini->test_connection();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Generar post ahora
     */
    public function ajax_generate_now() {
        check_ajax_referer('ai_auto_blog_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('No tienes permisos', 'ai-auto-blog')));
        }
        
        $generator = new AI_Auto_Blog_Post_Generator();
        $result = $generator->generate_post();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Detectar modelos disponibles
     */
    public function ajax_detect_models() {
        check_ajax_referer('ai_auto_blog_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('No tienes permisos', 'ai-auto-blog')));
        }
        
        $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';
        
        if (empty($api_key)) {
            wp_send_json_error(array('message' => __('API key vacía', 'ai-auto-blog')));
        }
        
        $available_models = AI_Auto_Blog_Model_Helper::get_available_models($api_key);
        
        if (empty($available_models)) {
            wp_send_json_error(array('message' => __('No se pudieron detectar modelos. Verifica tu API key.', 'ai-auto-blog')));
        }
        
        // Filtrar solo modelos relevantes para generación de texto
        $text_models = array_filter($available_models, function($model) {
            $lower = strtolower($model);
            return (strpos($lower, 'gemini') !== false || strpos($lower, 'pro') !== false || strpos($lower, 'flash') !== false) 
                   && strpos($lower, 'vision') === false 
                   && strpos($lower, 'embedding') === false;
        });
        
        wp_send_json_success(array(
            'models' => array_values($text_models),
            /* translators: %d: Number of available models found */
            'message' => sprintf(__('Se encontraron %d modelos disponibles', 'ai-auto-blog'), count($text_models))
        ));
    }
}
