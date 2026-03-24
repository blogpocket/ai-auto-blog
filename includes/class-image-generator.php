<?php
/**
 * Clase para generar imágenes con Google Imagen 3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Auto_Blog_Image_Generator {
    
    private $api_key;
    private $base_url = 'https://generativelanguage.googleapis.com/v1/models/';
    private $model = 'imagen-3.0-generate-001';
    
    /**
     * Constructor
     */
    public function __construct($api_key = null) {
        $this->api_key = $api_key;
    }
    
    /**
     * Establecer API key
     */
    public function set_api_key($api_key) {
        $this->api_key = $api_key;
    }
    
    /**
     * Generar imagen basada en título y contenido
     */
    public function generate_image($title, $content_excerpt = '') {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => __('API key no configurada', 'ai-auto-blog')
            );
        }
        
        // Crear prompt descriptivo para la imagen
        $image_prompt = $this->create_image_prompt($title, $content_excerpt);
        
        // Nota: Imagen 3.0 usa un endpoint y estructura diferente
        // Por ahora usaremos Gemini para generar una descripción y luego habría que usar
        // un servicio de generación de imágenes compatible
        
        // TEMPORAL: Como Imagen 3.0 no está disponible en la API pública aún,
        // retornamos un error informativo
        return array(
            'success' => false,
            'message' => __('La generación de imágenes con Imagen 3.0 aún no está disponible en la API pública de Google. Por favor, desactiva la opción "Generar Imagen Destacada" o añade imágenes manualmente.', 'ai-auto-blog'),
            'note' => 'imagen_api_not_available'
        );
        
        /* 
        // Este código quedará para cuando Google habilite Imagen 3.0 en la API pública
        $endpoint = $this->base_url . $this->model . ':predict?key=' . $this->api_key;
        
        $body = array(
            'instances' => array(
                array(
                    'prompt' => $image_prompt
                )
            ),
            'parameters' => array(
                'sampleCount' => 1,
                'aspectRatio' => '16:9',
                'negativePrompt' => 'text, words, letters, watermark, logo, signature',
                'safetySetting' => 'block_some'
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
            $error_message = isset($response_body['error']['message']) ? $response_body['error']['message'] : __('Error al generar imagen', 'ai-auto-blog');
            
            return array(
                'success' => false,
                'message' => $error_message
            );
        }
        
        // Extraer imagen
        if (isset($response_body['predictions'][0]['bytesBase64Encoded'])) {
            return array(
                'success' => true,
                'image_data' => $response_body['predictions'][0]['bytesBase64Encoded'],
                'mime_type' => 'image/png'
            );
        }
        
        return array(
            'success' => false,
            'message' => __('No se pudo extraer la imagen generada', 'ai-auto-blog')
        );
        */
    }
    
    /**
     * Crear prompt para la imagen
     */
    private function create_image_prompt($title, $content_excerpt = '') {
        $excerpt_text = !empty($content_excerpt) ? ' ' . wp_strip_all_tags(substr($content_excerpt, 0, 200)) : '';
        
        $prompt = "Create a professional, high-quality featured image for a blog article titled: '{$title}'.{$excerpt_text}

Style requirements:
- Modern and clean design
- Professional quality suitable for a blog
- Relevant to the article topic
- Visually appealing and engaging
- No text, words, or letters in the image
- Photorealistic or modern digital art style
- Well-composed and balanced
- Suitable for a 16:9 aspect ratio

Create a compelling visual that captures the essence of this article.";
        
        return $prompt;
    }
    
    /**
     * Subir imagen a la biblioteca de medios de WordPress
     */
    public function upload_to_media_library($image_base64, $mime_type, $post_title) {
        // Decodificar base64
        $image_data = base64_decode($image_base64);
        
        if (!$image_data) {
            return array(
                'success' => false,
                'message' => __('Error al decodificar imagen', 'ai-auto-blog')
            );
        }
        
        // Determinar extensión
        $extension = 'png';
        if (strpos($mime_type, 'jpeg') !== false || strpos($mime_type, 'jpg') !== false) {
            $extension = 'jpg';
        }
        
        // Generar nombre de archivo único
        $filename = 'ai-post-' . sanitize_title($post_title) . '-' . time() . '.' . $extension;
        
        // Obtener directorio de uploads
        $upload_dir = wp_upload_dir();
        
        // Crear subdirectorio si no existe
        $subdir = '/ai-auto-blog';
        if (!file_exists($upload_dir['basedir'] . $subdir)) {
            wp_mkdir_p($upload_dir['basedir'] . $subdir);
        }
        
        $filepath = $upload_dir['basedir'] . $subdir . '/' . $filename;
        $fileurl = $upload_dir['baseurl'] . $subdir . '/' . $filename;
        
        // Guardar archivo
        $saved = file_put_contents($filepath, $image_data);
        
        if (!$saved) {
            return array(
                'success' => false,
                'message' => __('Error al guardar imagen', 'ai-auto-blog')
            );
        }
        
        // Preparar attachment
        $wp_filetype = wp_check_filetype($filename, null);
        
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => sanitize_file_name($post_title),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        // Insertar attachment
        $attach_id = wp_insert_attachment($attachment, $filepath);
        
        if (is_wp_error($attach_id)) {
            return array(
                'success' => false,
                'message' => $attach_id->get_error_message()
            );
        }
        
        // Generar metadata
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $filepath);
        wp_update_attachment_metadata($attach_id, $attach_data);
        
        return array(
            'success' => true,
            'attachment_id' => $attach_id,
            'url' => $fileurl
        );
    }
    
    /**
     * Generar y asignar imagen destacada a un post
     */
    public function generate_and_attach($post_id, $title, $content_excerpt = '') {
        // Generar imagen
        $result = $this->generate_image($title, $content_excerpt);
        
        if (!$result['success']) {
            return $result;
        }
        
        // Subir a biblioteca de medios
        $upload_result = $this->upload_to_media_library(
            $result['image_data'],
            $result['mime_type'],
            $title
        );
        
        if (!$upload_result['success']) {
            return $upload_result;
        }
        
        // Asignar como imagen destacada
        $set_thumbnail = set_post_thumbnail($post_id, $upload_result['attachment_id']);
        
        if (!$set_thumbnail) {
            return array(
                'success' => false,
                'message' => __('Error al asignar imagen destacada', 'ai-auto-blog')
            );
        }
        
        return array(
            'success' => true,
            'attachment_id' => $upload_result['attachment_id'],
            'url' => $upload_result['url'],
            'message' => __('Imagen generada y asignada correctamente', 'ai-auto-blog')
        );
    }
}
