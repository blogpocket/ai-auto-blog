<?php
/**
 * Plugin Name: AI Auto Blog
 * Plugin URI: https://tusitio.com/ai-auto-blog
 * Description: Plugin para generar contenido automático de blog usando Google Gemini AI con temas aleatorios
 * Version: 1.4.2
 * Author: Tu Nombre
 * Author URI: https://tusitio.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-auto-blog
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
define('AI_AUTO_BLOG_VERSION', '1.4.2');
define('AI_AUTO_BLOG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AI_AUTO_BLOG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AI_AUTO_BLOG_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Clase principal del plugin
 */
class AI_Auto_Blog {
    
    /**
     * Instancia única del plugin
     */
    private static $instance = null;
    
    /**
     * Obtener instancia única
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Cargar dependencias
     */
    private function load_dependencies() {
        require_once AI_AUTO_BLOG_PLUGIN_DIR . 'includes/class-cpt.php';
        require_once AI_AUTO_BLOG_PLUGIN_DIR . 'includes/class-model-helper.php';
        require_once AI_AUTO_BLOG_PLUGIN_DIR . 'includes/class-topic-randomizer.php';
        require_once AI_AUTO_BLOG_PLUGIN_DIR . 'includes/class-cron-diagnostics.php';
        require_once AI_AUTO_BLOG_PLUGIN_DIR . 'includes/class-gemini-api.php';
        require_once AI_AUTO_BLOG_PLUGIN_DIR . 'includes/class-image-generator.php';
        require_once AI_AUTO_BLOG_PLUGIN_DIR . 'includes/class-post-generator.php';
        require_once AI_AUTO_BLOG_PLUGIN_DIR . 'includes/class-scheduler.php';
        require_once AI_AUTO_BLOG_PLUGIN_DIR . 'includes/class-admin.php';
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // Activación y desactivación
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Inicializar componentes
        add_action('plugins_loaded', array($this, 'init_components'));
        
        // Cargar scripts y estilos admin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Inicializar componentes del plugin
     */
    public function init_components() {
        AI_Auto_Blog_CPT::get_instance();
        AI_Auto_Blog_Admin::get_instance();
        AI_Auto_Blog_Scheduler::get_instance();
    }
    
    /**
     * Cargar assets del admin
     */
    public function enqueue_admin_assets($hook) {
        // Solo cargar en nuestra página de configuración
        if ('settings_page_ai-auto-blog' !== $hook) {
            return;
        }
        
        wp_enqueue_style(
            'ai-auto-blog-admin',
            AI_AUTO_BLOG_PLUGIN_URL . 'admin/css/admin-styles.css',
            array(),
            AI_AUTO_BLOG_VERSION
        );
        
        wp_enqueue_script(
            'ai-auto-blog-admin',
            AI_AUTO_BLOG_PLUGIN_URL . 'admin/js/admin-scripts.js',
            array('jquery'),
            AI_AUTO_BLOG_VERSION,
            true
        );
        
        wp_localize_script('ai-auto-blog-admin', 'aiAutoBlogAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_auto_blog_nonce'),
            'strings' => array(
                'generating' => __('Generando post...', 'ai-auto-blog'),
                'success' => __('Post generado exitosamente', 'ai-auto-blog'),
                'error' => __('Error al generar post', 'ai-auto-blog'),
                'testing' => __('Probando conexión...', 'ai-auto-blog'),
                'test_success' => __('Conexión exitosa', 'ai-auto-blog'),
                'test_error' => __('Error de conexión', 'ai-auto-blog'),
            )
        ));
    }
    
    /**
     * Activación del plugin
     */
    public function activate() {
        // Registrar CPT
        AI_Auto_Blog_CPT::register_post_type();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Crear opciones por defecto
        $defaults = array(
            'api_key' => '',
            'prompt' => 'Escribe un artículo informativo y bien estructurado sobre temas de actualidad, tecnología o cultura.',
            'length' => 'medium',
            'frequency' => 'manual',
            'post_status' => 'draft',
            'model' => 'gemini-2.5-flash',
            'generate_image' => 'no',
            'use_random_topics' => 'no',
            'topics' => '',
            'use_topic_rotation' => 'yes'
        );
        
        add_option('ai_auto_blog_settings', $defaults);
    }
    
    /**
     * Desactivación del plugin
     */
    public function deactivate() {
        // Limpiar cron jobs programados
        $timestamp = wp_next_scheduled('ai_auto_blog_generate_post');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'ai_auto_blog_generate_post');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

/**
 * Iniciar el plugin
 */
function ai_auto_blog_init() {
    return AI_Auto_Blog::get_instance();
}

// Iniciar
ai_auto_blog_init();
