<?php
/**
 * Clase para manejar la API de Google Gemini
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Auto_Blog_Gemini_API {
    
    private $api_key;
    private $model;
    private $base_url = 'https://generativelanguage.googleapis.com/v1/models/';
    
    /**
     * Constructor
     */
    public function __construct($api_key = null, $model = 'gemini-2.5-flash') {
        $this->api_key = $api_key;
        $this->model = $model;
    }
    
    /**
     * Establecer API key
     */
    public function set_api_key($api_key) {
        $this->api_key = $api_key;
    }
    
    /**
     * Establecer modelo
     */
    public function set_model($model) {
        $this->model = $model;
    }
    
    /**
     * Probar conexión con la API
     */
    public function test_connection() {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => __('API key no configurada', 'ai-auto-blog')
            );
        }
        
        $endpoint = $this->base_url . $this->model . ':generateContent?key=' . $this->api_key;
        
        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => 'Hello, this is a test.')
                    )
                )
            )
        );
        
        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($body),
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code !== 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $error_message = isset($body['error']['message']) ? $body['error']['message'] : __('Error desconocido', 'ai-auto-blog');
            
            return array(
                'success' => false,
                'message' => $error_message
            );
        }
        
        return array(
            'success' => true,
            'message' => __('Conexión exitosa con Gemini API', 'ai-auto-blog')
        );
    }
    
    /**
     * Generar contenido de texto
     */
    public function generate_content($prompt, $max_tokens = 2048) {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => __('API key no configurada', 'ai-auto-blog')
            );
        }
        
        $endpoint = $this->base_url . $this->model . ':generateContent?key=' . $this->api_key;
        
        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $prompt)
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => $max_tokens,
            )
        );
        
        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($body),
            'timeout' => 60,
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($status_code !== 200) {
            $error_message = isset($response_body['error']['message']) ? $response_body['error']['message'] : __('Error desconocido', 'ai-auto-blog');
            
            return array(
                'success' => false,
                'message' => $error_message
            );
        }
        
        // Extraer el texto generado
        if (isset($response_body['candidates'][0]['content']['parts'][0]['text'])) {
            $generated_text = $response_body['candidates'][0]['content']['parts'][0]['text'];
            
            return array(
                'success' => true,
                'content' => $generated_text,
                'raw_response' => $response_body
            );
        }
        
        return array(
            'success' => false,
            'message' => __('No se pudo extraer el contenido generado', 'ai-auto-blog')
        );
    }
    
    /**
     * Generar contenido estructurado (con título y contenido separados)
     */
    public function generate_blog_post($base_prompt, $length = 'medium') {
        // Determinar tokens según longitud - TRIPLICADOS para asegurar respuestas completas
        // Nota: Gemini a veces ignora maxOutputTokens, por eso necesitamos ser muy generosos
        $token_limits = array(
            'brief' => 4096,   // ~500 palabras (mucho margen)
            'medium' => 8192,  // ~1000 palabras (mucho margen)
            'long' => 16384    // ~2000 palabras (mucho margen)
        );
        
        $max_tokens = isset($token_limits[$length]) ? $token_limits[$length] : $token_limits['medium'];
        
        // NUEVA ESTRATEGIA: Usar delimitadores XML simples en lugar de JSON
        $full_prompt = "Genera un artículo de blog COMPLETO en español siguiendo estas instrucciones:

{$base_prompt}

Requisitos:
- Longitud aproximada: " . $this->get_length_description($length) . "
- Usa este formato EXACTO (copia la estructura):

<TITLE>Tu título aquí</TITLE>

<CONTENT>
<p>Primer párrafo del artículo...</p>

<h2>Primer subtítulo</h2>
<p>Contenido de esta sección...</p>

<h2>Segundo subtítulo</h2>
<p>Más contenido...</p>

<p>Conclusión...</p>
</CONTENT>

CRÍTICO E IMPORTANTE:
- El artículo debe estar COMPLETO de principio a fin
- NO cortes el artículo a la mitad
- Asegúrate de cerrar TODAS las etiquetas </CONTENT>
- Incluye introducción, desarrollo Y conclusión
- El título debe ir entre <TITLE> y </TITLE>
- Todo el contenido HTML debe ir entre <CONTENT> y </CONTENT>
- Usa HTML válido: <p>, <h2>, <h3>, <ul>, <ol>, <li>, <strong>, <em>
- NO uses markdown
- NO agregues nada fuera de estos delimitadores
- COMPLETA EL ARTÍCULO ENTERO antes de terminar

Escribe el artículo COMPLETO ahora:";
        
        $result = $this->generate_content($full_prompt, $max_tokens);
        
        if (!$result['success']) {
            return $result;
        }
        
        $raw_content = $result['content'];
        
        // Extraer título
        $title = '';
        if (preg_match('/<TITLE>(.*?)<\/TITLE>/is', $raw_content, $title_match)) {
            $title = trim($title_match[1]);
        }
        
        // Extraer contenido
        $content = '';
        if (preg_match('/<CONTENT>(.*?)<\/CONTENT>/is', $raw_content, $content_match)) {
            $content = trim($content_match[1]);
        }
        
        // Si encontramos ambos, sanitizar y retornar
        if (!empty($title) && !empty($content)) {
            // Verificar si el contenido parece estar cortado
            $is_truncated = false;
            $truncation_indicators = array(
                // No hay etiqueta de cierre
                !preg_match('/<\/CONTENT>/i', $raw_content),
                // Termina abruptamente (última etiqueta no cerrada)
                preg_match('/<[^>]+$/i', $content),
                // Muy corto para la longitud pedida
                (strlen($content) < 500 && $length !== 'brief'),
                (strlen($content) < 200 && $length === 'brief')
            );
            
            $is_truncated = in_array(true, $truncation_indicators, true);
            
            if ($is_truncated) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Logging for debugging truncation issues
                error_log('AI Auto Blog: Contenido posiblemente truncado. Longitud: ' . strlen($content) . ' caracteres. Length config: ' . $length);
            }
            
            // Sanitizar título
            $title = sanitize_text_field(wp_strip_all_tags($title));
            
            // Sanitizar contenido con todas las etiquetas HTML permitidas
            $allowed_tags = array(
                'p' => array(),
                'br' => array(),
                'strong' => array(),
                'em' => array(),
                'b' => array(),
                'i' => array(),
                'u' => array(),
                'h1' => array(),
                'h2' => array(),
                'h3' => array(),
                'h4' => array(),
                'h5' => array(),
                'h6' => array(),
                'ul' => array(),
                'ol' => array(),
                'li' => array(),
                'a' => array('href' => array(), 'title' => array(), 'target' => array()),
                'img' => array('src' => array(), 'alt' => array(), 'title' => array()),
                'blockquote' => array(),
                'code' => array(),
                'pre' => array(),
                'div' => array('class' => array(), 'id' => array()),
                'span' => array('class' => array(), 'id' => array()),
                'table' => array(),
                'thead' => array(),
                'tbody' => array(),
                'tr' => array(),
                'th' => array(),
                'td' => array(),
            );
            
            $allowed_tags = apply_filters('ai_auto_blog_allowed_html_tags', $allowed_tags);
            $content = wp_kses($content, $allowed_tags);
            
            return array(
                'success' => true,
                'title' => $title,
                'content' => $content,
                'method' => 'xml_delimiters',
                'is_truncated' => $is_truncated,
                'content_length' => strlen($content)
            );
        }
        
        // Si no funcionó, intentar con el método simple como fallback
        return $this->generate_blog_post_simple($base_prompt, $length);
    }
    
    /**
     * Limpiar string de JSON escapado
     */
    private function clean_json_string($string) {
        // Primero decodificar como JSON string
        $decoded = json_decode('"' . $string . '"');
        
        if ($decoded !== null) {
            return $decoded;
        }
        
        // Si falla, limpieza manual
        $string = stripcslashes($string);
        $string = str_replace('\\\\', '\\', $string);
        $string = str_replace('\"', '"', $string);
        $string = str_replace("\'", "'", $string);
        $string = str_replace('\\n', "\n", $string);
        $string = str_replace('\n', "\n", $string);
        
        return $string;
    }
    
    /**
     * Obtener descripción de longitud
     */
    private function get_length_description($length) {
        $descriptions = array(
            'brief' => '500 palabras',
            'medium' => '1000 palabras',
            'long' => '2000 palabras'
        );
        
        return isset($descriptions[$length]) ? $descriptions[$length] : $descriptions['medium'];
    }
    
    /**
     * Método alternativo: Generar con prompt simple (sin JSON)
     */
    public function generate_blog_post_simple($base_prompt, $length = 'medium') {
        $token_limits = array(
            'brief' => 4096,
            'medium' => 8192,
            'long' => 16384
        );
        
        $max_tokens = isset($token_limits[$length]) ? $token_limits[$length] : $token_limits['medium'];
        
        // Prompt más simple, sin pedir JSON
        $full_prompt = "Escribe un artículo de blog completo en español.

Instrucciones:
{$base_prompt}

Requisitos:
- Longitud: aproximadamente " . $this->get_length_description($length) . "
- Incluye un título atractivo en la primera línea
- Usa HTML para el formato: <h2>, <h3>, <p>, <ul>, <ol>, <strong>, <em>
- El contenido debe ser informativo, bien estructurado y de alta calidad
- Separa el título del contenido con una línea en blanco

Escribe el artículo ahora:";
        
        $result = $this->generate_content($full_prompt, $max_tokens);
        
        if (!$result['success']) {
            return $result;
        }
        
        $content = trim($result['content']);
        
        // Extraer título (primera línea no vacía)
        $lines = explode("\n", $content);
        $title = '';
        $body_lines = array();
        $title_found = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (!$title_found && !empty($line)) {
                // Limpiar el título
                $title = $line;
                $title = preg_replace('/^#+\s*/', '', $title); // Remover markdown headers
                $title = preg_replace('/^Título:\s*/i', '', $title);
                $title = trim($title, '"\'#-=* ');
                $title_found = true;
            } elseif ($title_found && !empty($line)) {
                $body_lines[] = $line;
            }
        }
        
        if (empty($title)) {
            $title = 'Artículo Generado por IA';
        }
        
        $html_content = implode("\n", $body_lines);
        
        // Si no tiene HTML, convertir a párrafos
        if (strpos($html_content, '<p>') === false && strpos($html_content, '<h2>') === false) {
            $paragraphs = explode("\n\n", $html_content);
            $formatted = '';
            foreach ($paragraphs as $p) {
                $p = trim($p);
                if (!empty($p)) {
                    // Verificar si es un título (línea corta sin punto final)
                    if (strlen($p) < 100 && substr($p, -1) !== '.') {
                        $formatted .= '<h2>' . $p . '</h2>';
                    } else {
                        $formatted .= '<p>' . nl2br($p) . '</p>';
                    }
                }
            }
            $html_content = $formatted;
        }
        
        return array(
            'success' => true,
            'title' => sanitize_text_field($title),
            'content' => wp_kses_post($html_content),
            'method' => 'simple'
        );
    }
}
