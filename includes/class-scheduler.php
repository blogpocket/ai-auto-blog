<?php
/**
 * Clase para programar la generación automática de posts
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Auto_Blog_Scheduler {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Hook para el cron
        add_action('ai_auto_blog_generate_post', array($this, 'execute_generation'));
        
        // Añadir intervalos personalizados
        add_filter('cron_schedules', array($this, 'add_custom_intervals'));
        
        // Actualizar programación cuando cambian los settings
        add_action('update_option_ai_auto_blog_settings', array($this, 'update_schedule'), 10, 2);
    }
    
    /**
     * Añadir intervalos personalizados al cron
     */
    public function add_custom_intervals($schedules) {
        $schedules['ai_auto_blog_weekly'] = array(
            'interval' => WEEK_IN_SECONDS,
            'display'  => __('Una vez a la semana', 'ai-auto-blog')
        );
        
        $schedules['ai_auto_blog_monthly'] = array(
            'interval' => MONTH_IN_SECONDS,
            'display'  => __('Una vez al mes', 'ai-auto-blog')
        );
        
        return $schedules;
    }
    
    /**
     * Ejecutar generación de post
     */
    public function execute_generation() {
        $generator = new AI_Auto_Blog_Post_Generator();
        $result = $generator->generate_post();
        
        // Opcional: Enviar email de notificación
        if ($result['success']) {
            $this->send_notification($result);
        }
        
        return $result;
    }
    
    /**
     * Actualizar programación cuando cambian los settings
     */
    public function update_schedule($old_value, $new_value) {
        // Limpiar eventos programados anteriores
        $this->clear_schedule();
        
        // Programar nuevo evento según frecuencia
        if (isset($new_value['frequency']) && $new_value['frequency'] !== 'manual') {
            $this->schedule_event($new_value['frequency']);
        }
    }
    
    /**
     * Programar evento
     */
    public function schedule_event($frequency) {
        $schedules = array(
            'daily' => 'daily',
            'weekly' => 'ai_auto_blog_weekly',
            'monthly' => 'ai_auto_blog_monthly'
        );
        
        if (!isset($schedules[$frequency])) {
            return false;
        }
        
        // Programar para mañana a las 9:00 AM
        $tomorrow = strtotime('tomorrow 9:00');
        
        wp_schedule_event($tomorrow, $schedules[$frequency], 'ai_auto_blog_generate_post');
        
        return true;
    }
    
    /**
     * Limpiar eventos programados
     */
    public function clear_schedule() {
        $timestamp = wp_next_scheduled('ai_auto_blog_generate_post');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'ai_auto_blog_generate_post');
        }
        
        // Limpiar todos los eventos por si acaso
        wp_clear_scheduled_hook('ai_auto_blog_generate_post');
    }
    
    /**
     * Obtener información de próxima ejecución
     */
    public static function get_next_scheduled() {
        $timestamp = wp_next_scheduled('ai_auto_blog_generate_post');
        
        if (!$timestamp) {
            return array(
                'scheduled' => false,
                'message' => __('No hay generación programada', 'ai-auto-blog')
            );
        }
        
        $datetime = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
        
        return array(
            'scheduled' => true,
            'timestamp' => $timestamp,
            'datetime' => $datetime,
            /* translators: %s: Formatted date and time for next post generation */
            'message' => sprintf(__('Próxima generación: %s', 'ai-auto-blog'), $datetime)
        );
    }
    
    /**
     * Enviar notificación por email (opcional)
     */
    private function send_notification($result) {
        // Verificar si las notificaciones están habilitadas
        $settings = get_option('ai_auto_blog_settings', array());
        
        if (!isset($settings['email_notifications']) || $settings['email_notifications'] !== 'yes') {
            return;
        }
        
        $admin_email = get_option('admin_email');
        $subject = __('[AI Auto Blog] Nuevo post generado', 'ai-auto-blog');
        
        $message = sprintf(
            /* translators: 1: Post title 2: Post status 3: Post URL 4: Edit URL */
            __('Se ha generado un nuevo post automáticamente:\n\nTítulo: %1$s\nEstado: %2$s\nVer post: %3$s\nEditar post: %4$s\n\n---\nEste es un mensaje automático de AI Auto Blog.', 'ai-auto-blog'),
            $result['post_title'],
            isset($settings['post_status']) && $settings['post_status'] === 'publish' ? 'Publicado' : 'Borrador',
            $result['post_url'],
            $result['edit_url']
        );
        
        wp_mail($admin_email, $subject, $message);
    }
}
