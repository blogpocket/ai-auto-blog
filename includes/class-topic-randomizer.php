<?php
/**
 * Clase para generar temas aleatorios
 * 
 * @package AI_Auto_Blog
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Auto_Blog_Topic_Randomizer {
    
    /**
     * Obtener temas configurados
     */
    private static function get_topics() {
        $settings = get_option('ai_auto_blog_settings', array());
        
        // Obtener temas configurados (separados por comas)
        $topics_string = isset($settings['topics']) ? trim($settings['topics']) : '';
        
        if (empty($topics_string)) {
            // Temas por defecto si no hay configurados
            return array(
                'Tecnología e Innovación',
                'Inteligencia Artificial',
                'Negocios y Emprendimiento',
                'Salud y Bienestar',
                'Ciencia y Educación',
                'Marketing Digital',
                'Desarrollo Personal',
                'Finanzas y Economía'
            );
        }
        
        // Convertir string a array, limpiar espacios
        $topics = array_map('trim', explode(',', $topics_string));
        
        // Filtrar temas vacíos
        $topics = array_filter($topics, function($topic) {
            return !empty($topic);
        });
        
        return array_values($topics); // Reindexar
    }
    
    /**
     * Obtener un tema aleatorio
     * 
     * @return string Tema seleccionado
     */
    public static function get_random_topic() {
        $topics = self::get_topics();
        
        if (empty($topics)) {
            return 'Tema General';
        }
        
        return $topics[array_rand($topics)];
    }
    
    /**
     * Obtener múltiples temas aleatorios únicos
     * 
     * @param int $count Cantidad de temas a obtener
     * @return array Array de temas
     */
    public static function get_multiple_random_topics($count) {
        $topics = self::get_topics();
        
        if (empty($topics)) {
            return array('Tema General');
        }
        
        // No pedir más temas de los disponibles
        $count = min($count, count($topics));
        
        if ($count <= 0) {
            return array();
        }
        
        if ($count == 1) {
            return array(self::get_random_topic());
        }
        
        // Obtener índices aleatorios
        $randomKeys = array_rand($topics, $count);
        
        // Construir array de resultado
        $result = array();
        foreach ((array)$randomKeys as $key) {
            $result[] = $topics[$key];
        }
        
        return $result;
    }
    
    /**
     * Generar prompt base con tema aleatorio
     * 
     * @param string $base_prompt Prompt base del usuario
     * @param string $topic Tema específico (opcional, si no se usa aleatorio)
     * @return string Prompt completo con tema
     */
    public static function generate_prompt_with_topic($base_prompt = '', $topic = null) {
        // Obtener configuración
        $settings = get_option('ai_auto_blog_settings', array());
        $use_random_topics = isset($settings['use_random_topics']) && $settings['use_random_topics'] === 'yes';
        
        // Si no está activado usar temas aleatorios, devolver prompt original
        if (!$use_random_topics) {
            return $base_prompt;
        }
        
        // Obtener tema
        if ($topic === null) {
            $topic = self::get_random_topic();
        }
        
        // Si el prompt base ya contiene {TOPIC} o {topic}, reemplazarlo
        if (stripos($base_prompt, '{topic}') !== false) {
            return str_ireplace('{topic}', $topic, $base_prompt);
        }
        
        // Si no, añadir el tema al inicio
        if (empty($base_prompt)) {
            // Prompt por defecto si no hay ninguno
            $templates = array(
                "Escribe un artículo informativo y bien estructurado sobre {$topic}. Incluye información actualizada, ejemplos prácticos y una perspectiva equilibrada.",
                "Crea un análisis profundo sobre las tendencias actuales en {$topic}. Explica el contexto, los desafíos y las oportunidades.",
                "Escribe una guía completa sobre {$topic}. Usa un lenguaje claro, proporciona ejemplos útiles y estructura el contenido de forma lógica.",
                "Genera contenido educativo sobre {$topic}, explicando conceptos clave, aplicaciones prácticas y tendencias actuales.",
                "Escribe un artículo completo sobre {$topic} que sea informativo, fácil de entender y útil para los lectores.",
            );
            
            return $templates[array_rand($templates)];
        }
        
        // Combinar tema con prompt del usuario
        return "Escribe sobre el tema: {$topic}\n\nInstrucciones adicionales:\n{$base_prompt}";
    }
    
    /**
     * Obtener próximo tema en rotación (evita repeticiones inmediatas)
     * 
     * @return string Tema seleccionado
     */
    public static function get_next_topic_in_rotation() {
        $topics = self::get_topics();
        
        if (empty($topics)) {
            return 'Tema General';
        }
        
        // Obtener historial de temas usados recientemente
        $used_topics = get_option('ai_auto_blog_used_topics', array());
        
        // Si ya usamos todos, reiniciar
        if (count($used_topics) >= count($topics)) {
            $used_topics = array();
        }
        
        // Obtener temas disponibles (no usados recientemente)
        $available = array_diff($topics, $used_topics);
        
        if (empty($available)) {
            // Si no hay disponibles, usar todos
            $available = $topics;
            $used_topics = array();
        }
        
        // Seleccionar uno aleatorio de los disponibles
        $selected = $available[array_rand($available)];
        
        // Añadir a usados
        $used_topics[] = $selected;
        update_option('ai_auto_blog_used_topics', $used_topics);
        
        return $selected;
    }
    
    /**
     * Validar lista de temas
     * 
     * @param string $topics_string String con temas separados por comas
     * @return array Array con 'valid' => bool y 'message' => string
     */
    public static function validate_topics($topics_string) {
        $topics_string = trim($topics_string);
        
        if (empty($topics_string)) {
            return array(
                'valid' => true,
                'message' => __('Se usarán los temas por defecto', 'ai-auto-blog')
            );
        }
        
        $topics = array_map('trim', explode(',', $topics_string));
        $topics = array_filter($topics); // Eliminar vacíos
        
        if (count($topics) < 2) {
            return array(
                'valid' => false,
                'message' => __('Debes proporcionar al menos 2 temas separados por comas', 'ai-auto-blog')
            );
        }
        
        if (count($topics) > 50) {
            return array(
                'valid' => false,
                'message' => __('Máximo 50 temas permitidos', 'ai-auto-blog')
            );
        }
        
        // Verificar longitud de cada tema
        foreach ($topics as $topic) {
            if (strlen($topic) > 100) {
                return array(
                    'valid' => false,
                    /* translators: %s: Topic name (first 50 characters) */
                    'message' => sprintf(__('El tema "%s" es demasiado largo (máx 100 caracteres)', 'ai-auto-blog'), substr($topic, 0, 50) . '...')
                );
            }
        }
        
        return array(
            'valid' => true,
            /* translators: %d: Number of topics configured */
            'message' => sprintf(__('%d temas configurados correctamente', 'ai-auto-blog'), count($topics))
        );
    }
    
    /**
     * Obtener estadísticas de uso de temas
     * 
     * @return array Estadísticas
     */
    public static function get_topic_stats() {
        $stats = get_option('ai_auto_blog_topic_stats', array());
        $topics = self::get_topics();
        
        // Inicializar stats para temas nuevos
        foreach ($topics as $topic) {
            if (!isset($stats[$topic])) {
                $stats[$topic] = 0;
            }
        }
        
        return $stats;
    }
    
    /**
     * Registrar uso de un tema
     * 
     * @param string $topic Tema usado
     */
    public static function record_topic_usage($topic) {
        $stats = get_option('ai_auto_blog_topic_stats', array());
        
        if (!isset($stats[$topic])) {
            $stats[$topic] = 0;
        }
        
        $stats[$topic]++;
        update_option('ai_auto_blog_topic_stats', $stats);
    }
    
    /**
     * Resetear historial de rotación
     */
    public static function reset_rotation() {
        delete_option('ai_auto_blog_used_topics');
    }
    
    /**
     * Resetear estadísticas
     */
    public static function reset_stats() {
        delete_option('ai_auto_blog_topic_stats');
    }
}
