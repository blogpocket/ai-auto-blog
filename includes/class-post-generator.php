<?php
/**
 * Clase para generar posts automáticamente
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Auto_Blog_Post_Generator {
    
    private $settings;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = get_option('ai_auto_blog_settings', array());
    }
    
    /**
     * Generar un nuevo post
     */
    public function generate_post() {
        // Validar configuración
        if (empty($this->settings['api_key'])) {
            return array(
                'success' => false,
                'message' => __('API key no configurada. Por favor, configura el plugin primero.', 'ai-auto-blog')
            );
        }
        
        if (empty($this->settings['prompt'])) {
            return array(
                'success' => false,
                'message' => __('Prompt no configurado. Por favor, configura el plugin primero.', 'ai-auto-blog')
            );
        }
        
        // Inicializar API de Gemini
        $gemini = new AI_Auto_Blog_Gemini_API(
            $this->settings['api_key'],
            $this->settings['model']
        );
        
        // Generar contenido - Intentar primero con JSON
        $length = isset($this->settings['length']) ? $this->settings['length'] : 'medium';
        
        // Obtener prompt con tema aleatorio si está activado
        $final_prompt = AI_Auto_Blog_Topic_Randomizer::generate_prompt_with_topic($this->settings['prompt']);
        
        // Registrar tema usado si está activado
        $use_random_topics = isset($this->settings['use_random_topics']) && $this->settings['use_random_topics'] === 'yes';
        $topic_used = null;
        
        if ($use_random_topics) {
            // Obtener tema para este post (usando rotación)
            $use_rotation = isset($this->settings['use_topic_rotation']) && $this->settings['use_topic_rotation'] === 'yes';
            
            if ($use_rotation) {
                $topic_used = AI_Auto_Blog_Topic_Randomizer::get_next_topic_in_rotation();
            } else {
                $topic_used = AI_Auto_Blog_Topic_Randomizer::get_random_topic();
            }
            
            // Generar prompt con este tema específico
            $final_prompt = AI_Auto_Blog_Topic_Randomizer::generate_prompt_with_topic($this->settings['prompt'], $topic_used);
        }
        
        $result = $gemini->generate_blog_post($final_prompt, $length);
        
        // Si falla el método JSON, intentar con método simple
        if (!$result['success']) {
            $this->log_generation('warning', 'Método JSON falló, intentando método simple...');
            $result = $gemini->generate_blog_post_simple($this->settings['prompt'], $length);
        }
        
        if (!$result['success']) {
            $this->log_generation('error', 'Error al generar contenido: ' . $result['message']);
            return $result;
        }
        
        // Crear el post
        $post_status = isset($this->settings['post_status']) ? $this->settings['post_status'] : 'draft';
        
        $post_data = array(
            'post_title'   => $result['title'],
            'post_content' => $result['content'],
            'post_status'  => $post_status,
            'post_type'    => 'ai_blog_post',
            'post_author'  => get_current_user_id() > 0 ? get_current_user_id() : 1,
        );
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            $this->log_generation('error', 'Error al crear post: ' . $post_id->get_error_message());
            return array(
                'success' => false,
                'message' => $post_id->get_error_message()
            );
        }
        
        // Agregar metadata
        add_post_meta($post_id, '_ai_generated', true);
        add_post_meta($post_id, '_ai_generation_date', current_time('mysql'));
        add_post_meta($post_id, '_ai_model_used', $this->settings['model']);
        
        // Agregar método usado (JSON o simple)
        if (isset($result['method'])) {
            add_post_meta($post_id, '_ai_generation_method', $result['method']);
        }
        
        // Guardar tema usado si está activado
        if ($topic_used) {
            add_post_meta($post_id, '_ai_topic_used', $topic_used);
            
            // Registrar en estadísticas
            AI_Auto_Blog_Topic_Randomizer::record_topic_usage($topic_used);
        }
        
        // Generar imagen destacada si está habilitado
        $image_generated = false;
        $image_note = '';
        if (isset($this->settings['generate_image']) && $this->settings['generate_image'] === 'yes') {
            $image_generator = new AI_Auto_Blog_Image_Generator($this->settings['api_key']);
            
            // Crear excerpt del contenido para mejor contexto
            $content_text = wp_strip_all_tags($result['content']);
            $excerpt = substr($content_text, 0, 300);
            
            $image_result = $image_generator->generate_and_attach($post_id, $result['title'], $excerpt);
            
            if ($image_result['success']) {
                $image_generated = true;
                add_post_meta($post_id, '_ai_image_generated', true);
            } else {
                // Si es el error conocido de API no disponible, guardar nota
                if (isset($image_result['note']) && $image_result['note'] === 'imagen_api_not_available') {
                    $image_note = 'api_not_available';
                    $this->log_generation('info', 'Post creado. Imagen destacada no generada: API de Imagen 3.0 aún no disponible públicamente.');
                } else {
                    // Otro tipo de error
                    $this->log_generation('warning', 'Post creado pero error al generar imagen: ' . $image_result['message']);
                }
            }
        }
        
        // Log exitoso
        $method_used = isset($result['method']) ? ' (método: ' . $result['method'] . ')' : '';
        $this->log_generation('success', 'Post generado exitosamente: ' . $result['title'] . $method_used, $post_id);
        
        // Retornar resultado
        return array(
            'success' => true,
            'message' => __('Post generado exitosamente', 'ai-auto-blog'),
            'post_id' => $post_id,
            'post_title' => $result['title'],
            'post_url' => get_permalink($post_id),
            'edit_url' => get_edit_post_link($post_id, 'raw'),
            'image_generated' => $image_generated,
            'image_note' => $image_note,
            'generation_method' => isset($result['method']) ? $result['method'] : 'json',
            'topic_used' => $topic_used
        );
    }
    
    /**
     * Registrar actividad de generación
     */
    private function log_generation($type, $message, $post_id = null) {
        $logs = get_option('ai_auto_blog_logs', array());
        
        // Mantener solo los últimos 50 logs
        if (count($logs) >= 50) {
            array_shift($logs);
        }
        
        $logs[] = array(
            'type' => $type,
            'message' => $message,
            'post_id' => $post_id,
            'timestamp' => current_time('mysql'),
            'date' => current_time('timestamp')
        );
        
        update_option('ai_auto_blog_logs', $logs);
        
        // Actualizar contadores
        if ($type === 'success') {
            $total_generated = get_option('ai_auto_blog_total_generated', 0);
            update_option('ai_auto_blog_total_generated', $total_generated + 1);
        }
    }
    
    /**
     * Obtener logs de generación
     */
    public static function get_logs($limit = 10) {
        $logs = get_option('ai_auto_blog_logs', array());
        
        // Ordenar por fecha descendente
        usort($logs, function($a, $b) {
            return $b['date'] - $a['date'];
        });
        
        return array_slice($logs, 0, $limit);
    }
    
    /**
     * Obtener estadísticas
     */
    public static function get_stats() {
        $total_generated = get_option('ai_auto_blog_total_generated', 0);
        
        // Contar posts por estado
        $count_published = wp_count_posts('ai_blog_post');
        
        return array(
            'total_generated' => $total_generated,
            'published' => isset($count_published->publish) ? $count_published->publish : 0,
            'draft' => isset($count_published->draft) ? $count_published->draft : 0,
            'total_posts' => isset($count_published->publish) && isset($count_published->draft) 
                ? $count_published->publish + $count_published->draft 
                : 0
        );
    }
}
