<?php
/**
 * Helper para compatibilidad de modelos de Gemini
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Auto_Blog_Model_Helper {
    
    /**
     * Obtener lista de modelos disponibles
     */
    public static function get_available_models($api_key) {
        if (empty($api_key)) {
            return array();
        }
        
        $endpoint = 'https://generativelanguage.googleapis.com/v1/models?key=' . $api_key;
        
        $response = wp_remote_get($endpoint, array(
            'timeout' => 15,
        ));
        
        if (is_wp_error($response)) {
            return array();
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['models']) || !is_array($body['models'])) {
            return array();
        }
        
        $available = array();
        foreach ($body['models'] as $model) {
            if (isset($model['name']) && isset($model['supportedGenerationMethods'])) {
                // Extraer nombre del modelo
                $model_name = str_replace('models/', '', $model['name']);
                
                // Verificar si soporta generateContent
                if (in_array('generateContent', $model['supportedGenerationMethods'])) {
                    $available[] = $model_name;
                }
            }
        }
        
        return $available;
    }
    
    /**
     * Obtener modelo recomendado según preferencia
     */
    public static function get_recommended_model($api_key, $preference = 'flash') {
        $available = self::get_available_models($api_key);
        
        if (empty($available)) {
            // Fallback a modelos conocidos
            return $preference === 'pro' ? 'gemini-2.5-pro' : 'gemini-2.5-flash';
        }
        
        // Prioridad para Flash
        $flash_priority = array(
            'gemini-2.5-flash',
            'gemini-2.0-flash',
            'gemini-2.0-flash-001',
            'gemini-2.5-flash-lite',
            'gemini-2.0-flash-lite',
            'gemini-2.0-flash-lite-001',
            'gemini-1.5-flash-latest',
            'gemini-1.5-flash',
            'gemini-1.5-flash-001',
            'gemini-1.5-flash-002',
            'gemini-flash-1.5',
        );
        
        // Prioridad para Pro
        $pro_priority = array(
            'gemini-2.5-pro',
            'gemini-2.0-pro',
            'gemini-1.5-pro-latest',
            'gemini-1.5-pro',
            'gemini-1.5-pro-001',
            'gemini-1.5-pro-002',
            'gemini-pro',
        );
        
        $priority_list = $preference === 'pro' ? $pro_priority : $flash_priority;
        
        // Buscar primer modelo disponible según prioridad
        foreach ($priority_list as $model) {
            if (in_array($model, $available)) {
                return $model;
            }
        }
        
        // Buscar cualquier modelo flash/pro disponible
        foreach ($available as $model) {
            $model_lower = strtolower($model);
            if ($preference === 'flash' && strpos($model_lower, 'flash') !== false) {
                return $model;
            }
            if ($preference === 'pro' && strpos($model_lower, 'pro') !== false) {
                return $model;
            }
        }
        
        // Último recurso: primer modelo disponible
        return isset($available[0]) ? $available[0] : 'gemini-2.5-flash';
    }
    
    /**
     * Validar que un modelo existe
     */
    public static function validate_model($api_key, $model) {
        $available = self::get_available_models($api_key);
        
        if (empty($available)) {
            // Si no podemos verificar, asumimos que es válido
            return true;
        }
        
        return in_array($model, $available);
    }
    
    /**
     * Obtener mapeo de modelos amigables
     */
    public static function get_model_options() {
        return array(
            // Gemini 2.x
            'gemini-2.5-flash' => array(
                'label' => 'Gemini 2.5 Flash (Recomendado)',
                'description' => 'Última generación, rápido y eficiente',
                'preference' => 'flash'
            ),
            'gemini-2.5-pro' => array(
                'label' => 'Gemini 2.5 Pro',
                'description' => 'Más potente de la serie 2.5',
                'preference' => 'pro'
            ),
            'gemini-2.0-flash' => array(
                'label' => 'Gemini 2.0 Flash',
                'description' => 'Serie 2.0, rápido',
                'preference' => 'flash'
            ),
            'gemini-2.5-flash-lite' => array(
                'label' => 'Gemini 2.5 Flash Lite',
                'description' => 'Versión ligera y rápida',
                'preference' => 'flash'
            ),
            'gemini-2.0-flash-lite' => array(
                'label' => 'Gemini 2.0 Flash Lite',
                'description' => 'Versión ligera 2.0',
                'preference' => 'flash'
            ),
            // Gemini 1.5.x (Legacy)
            'gemini-1.5-flash-latest' => array(
                'label' => 'Gemini 1.5 Flash - Latest',
                'description' => 'Última versión 1.5 Flash',
                'preference' => 'flash'
            ),
            'gemini-1.5-flash' => array(
                'label' => 'Gemini 1.5 Flash',
                'description' => 'Versión estable 1.5 Flash',
                'preference' => 'flash'
            ),
            'gemini-1.5-pro-latest' => array(
                'label' => 'Gemini 1.5 Pro - Latest',
                'description' => 'Última versión 1.5 Pro',
                'preference' => 'pro'
            ),
            'gemini-1.5-pro' => array(
                'label' => 'Gemini 1.5 Pro',
                'description' => 'Versión estable 1.5 Pro',
                'preference' => 'pro'
            ),
            'gemini-pro' => array(
                'label' => 'Gemini Pro (Fallback)',
                'description' => 'Compatible con API v1',
                'preference' => 'pro'
            ),
        );
    }
}
