#!/usr/bin/env php
<?php
/**
 * Script de Generación Automática para AI Auto Blog
 * 
 * Este script se ejecuta directamente desde el cron del sistema
 * sin depender del sistema de cron de WordPress
 * 
 * Uso desde cron:
 * 0 9 * * * /usr/bin/php /ruta/a/wordpress/wp-content/plugins/ai-auto-blog/cron-generate.php >> /tmp/ai-auto-blog-cron.log 2>&1
 * 
 * @package AI_Auto_Blog
 */

// Protección requerida por Plugin Check - se cumplirá después de cargar WordPress
if (!defined('ABSPATH')) {
    // Permitir ejecución CLI antes de cargar WordPress
    if (php_sapi_name() !== 'cli') {
        exit;
    }
}

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI script, output escaping not needed

// Este es un script CLI - verificar que se ejecuta desde línea de comandos
if (php_sapi_name() !== 'cli') {
    exit('Este script solo puede ejecutarse desde línea de comandos.');
}

// Configuración
define('DOING_CRON', true);
define('AI_AUTO_BLOG_CRON_SCRIPT', true);

// Detectar la ruta de WordPress
$wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';

// Verificar que wp-load.php existe
if (!file_exists($wp_load_path)) {
    echo "[ERROR] " . gmdate('Y-m-d H:i:s') . " - No se encontró wp-load.php en: $wp_load_path\n";
    exit(1);
}

// Cargar WordPress
require_once($wp_load_path);

// Verificar que WordPress se cargó correctamente
if (!function_exists('get_option')) {
    echo "[ERROR] " . gmdate('Y-m-d H:i:s') . " - WordPress no se cargó correctamente\n";
    exit(1);
}

// Verificar que ABSPATH está definido (WordPress cargado correctamente)
if (!defined('ABSPATH')) {
    echo "[ERROR] " . gmdate('Y-m-d H:i:s') . " - ABSPATH no definido - WordPress no se inicializó\n";
    exit(1);
}

echo "[INFO] " . gmdate('Y-m-d H:i:s') . " - Iniciando generación automática de AI Auto Blog\n";

// Verificar que el plugin está activo
if (!class_exists('AI_Auto_Blog_Post_Generator')) {
    echo "[ERROR] " . gmdate('Y-m-d H:i:s') . " - El plugin AI Auto Blog no está activo\n";
    exit(1);
}

// Obtener configuración
$settings = get_option('ai_auto_blog_settings', array());

if (empty($settings)) {
    echo "[ERROR] " . gmdate('Y-m-d H:i:s') . " - No hay configuración del plugin\n";
    exit(1);
}

// Verificar que la frecuencia no es manual
$frequency = isset($settings['frequency']) ? $settings['frequency'] : 'manual';

if ($frequency === 'manual') {
    echo "[INFO] " . gmdate('Y-m-d H:i:s') . " - Modo manual activado, no se genera post automáticamente\n";
    exit(0);
}

echo "[INFO] " . gmdate('Y-m-d H:i:s') . " - Frecuencia configurada: $frequency\n";

// Verificar si ya se generó hoy (solo para daily)
if ($frequency === 'daily') {
    $last_posts = get_posts(array(
        'post_type' => 'ai_blog_post',
        'posts_per_page' => 1,
        'orderby' => 'date',
        'order' => 'DESC'
    ));
    
    if (!empty($last_posts)) {
        $last_post_date = strtotime($last_posts[0]->post_date);
        $today_start = strtotime('today midnight');
        
        if ($last_post_date >= $today_start) {
            echo "[INFO] " . gmdate('Y-m-d H:i:s') . " - Ya se generó un post hoy, saltando ejecución\n";
            exit(0);
        }
    }
}

// Verificar si es el momento de generar (para weekly y monthly)
$last_execution = get_option('ai_auto_blog_last_cron_execution', 0);
$now = time();

$should_generate = false;

switch ($frequency) {
    case 'daily':
        $should_generate = true;
        break;
        
    case 'weekly':
        // Generar si han pasado 7 días
        if (($now - $last_execution) >= WEEK_IN_SECONDS) {
            $should_generate = true;
        }
        break;
        
    case 'monthly':
        // Generar si han pasado 30 días
        if (($now - $last_execution) >= (30 * DAY_IN_SECONDS)) {
            $should_generate = true;
        }
        break;
}

if (!$should_generate) {
    $next_run = gmdate('Y-m-d H:i:s', $last_execution + ($frequency === 'weekly' ? WEEK_IN_SECONDS : 30 * DAY_IN_SECONDS));
    echo "[INFO] " . gmdate('Y-m-d H:i:s') . " - Aún no es momento de generar. Próxima ejecución: $next_run\n";
    exit(0);
}

echo "[INFO] " . gmdate('Y-m-d H:i:s') . " - Iniciando generación de post...\n";

// Iniciar generación
try {
    $generator = new AI_Auto_Blog_Post_Generator();
    $result = $generator->generate_post();
    
    if ($result['success']) {
        echo "[SUCCESS] " . gmdate('Y-m-d H:i:s') . " - Post generado exitosamente\n";
        echo "[INFO] Post ID: " . $result['post_id'] . "\n";
        echo "[INFO] Título: " . $result['post_title'] . "\n";
        echo "[INFO] URL: " . $result['post_url'] . "\n";
        
        if (isset($result['topic_used']) && !empty($result['topic_used'])) {
            echo "[INFO] Tema: " . $result['topic_used'] . "\n";
        }
        
        if (isset($result['image_generated']) && $result['image_generated']) {
            echo "[INFO] Imagen destacada: Generada\n";
        }
        
        // Actualizar última ejecución
        update_option('ai_auto_blog_last_cron_execution', $now);
        
        // Enviar email si está configurado
        if (isset($settings['email_notifications']) && $settings['email_notifications'] === 'yes') {
            $admin_email = get_option('admin_email');
            $subject = '[AI Auto Blog] Nuevo post generado: ' . $result['post_title'];
            $message = "Se ha generado un nuevo post automáticamente:\n\n";
            $message .= "Título: " . $result['post_title'] . "\n";
            $message .= "URL: " . $result['post_url'] . "\n";
            $message .= "Editar: " . $result['edit_url'] . "\n";
            $message .= "Fecha: " . gmdate('Y-m-d H:i:s') . "\n";
            
            if (isset($result['topic_used']) && !empty($result['topic_used'])) {
                $message .= "Tema: " . $result['topic_used'] . "\n";
            }
            
            wp_mail($admin_email, $subject, $message);
            echo "[INFO] Email de notificación enviado a: $admin_email\n";
        }
        
        exit(0);
        
    } else {
        echo "[ERROR] " . gmdate('Y-m-d H:i:s') . " - Error al generar post: " . $result['message'] . "\n";
        
        // Enviar email de error si está configurado
        if (isset($settings['email_notifications']) && $settings['email_notifications'] === 'yes') {
            $admin_email = get_option('admin_email');
            $subject = '[AI Auto Blog] Error en generación automática';
            $message = "Hubo un error al generar el post automáticamente:\n\n";
            $message .= "Error: " . $result['message'] . "\n";
            $message .= "Fecha: " . gmdate('Y-m-d H:i:s') . "\n";
            
            wp_mail($admin_email, $subject, $message);
        }
        
        exit(1);
    }
    
} catch (Exception $e) {
    echo "[EXCEPTION] " . gmdate('Y-m-d H:i:s') . " - Excepción capturada: " . $e->getMessage() . "\n";
    echo "[TRACE] " . $e->getTraceAsString() . "\n";
    exit(1);
}
