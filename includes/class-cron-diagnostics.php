<?php
/**
 * Herramientas de diagnóstico de Cron para AI Auto Blog
 * 
 * @package AI_Auto_Blog
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Auto_Blog_Cron_Diagnostics {
    
    /**
     * Obtener información completa del cron
     */
    public static function get_cron_info() {
        $settings = get_option('ai_auto_blog_settings', array());
        $frequency = isset($settings['frequency']) ? $settings['frequency'] : 'manual';
        
        $info = array(
            'cron_enabled' => !defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON,
            'cron_constant' => defined('DISABLE_WP_CRON') ? DISABLE_WP_CRON : false,
            'plugin_frequency' => $frequency,
            'server_time' => current_time('mysql'),
            'utc_time' => gmdate('Y-m-d H:i:s'),
            'timezone' => wp_timezone_string(),
            'next_scheduled' => false,
            'is_scheduled' => false,
            'hook_exists' => false,
            'all_cron_events' => array(),
        );
        
        // Verificar si el hook está registrado
        $timestamp = wp_next_scheduled('ai_auto_blog_generate_post');
        $info['is_scheduled'] = $timestamp !== false;
        $info['next_scheduled'] = $timestamp ? gmdate('Y-m-d H:i:s', $timestamp) : false;
        
        // Obtener todos los eventos de cron del plugin
        $cron_array = _get_cron_array();
        if ($cron_array) {
            foreach ($cron_array as $timestamp => $cron) {
                if (isset($cron['ai_auto_blog_generate_post'])) {
                    $info['all_cron_events'][] = array(
                        'timestamp' => $timestamp,
                        'formatted_time' => gmdate('Y-m-d H:i:s', $timestamp),
                        'time_until' => human_time_diff($timestamp, time()),
                        'args' => $cron['ai_auto_blog_generate_post']
                    );
                }
            }
        }
        
        // Verificar si hay otros eventos de cron problemáticos
        $info['total_cron_events'] = $cron_array ? count($cron_array, COUNT_RECURSIVE) : 0;
        
        // Verificar últimos posts generados
        $last_posts = get_posts(array(
            'post_type' => 'ai_blog_post',
            'posts_per_page' => 5,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        $info['last_generated_posts'] = array();
        foreach ($last_posts as $post) {
            $info['last_generated_posts'][] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'date' => $post->post_date,
                'status' => $post->post_status
            );
        }
        
        // Logs recientes
        $logs = get_option('ai_auto_blog_logs', array());
        $info['recent_logs'] = array_slice(array_reverse($logs), 0, 10);
        
        return $info;
    }
    
    /**
     * Verificar salud del cron
     */
    public static function check_cron_health() {
        $issues = array();
        $warnings = array();
        $info = array();
        
        // 1. Verificar si WP_CRON está deshabilitado
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            $warnings[] = 'DISABLE_WP_CRON está activado. Debes configurar un cron real del servidor.';
        } else {
            $info[] = 'WP_CRON está habilitado correctamente.';
        }
        
        // 2. Verificar configuración del plugin
        $settings = get_option('ai_auto_blog_settings', array());
        $frequency = isset($settings['frequency']) ? $settings['frequency'] : 'manual';
        
        if ($frequency === 'manual') {
            $info[] = 'Modo manual activado. No se programarán posts automáticos.';
        } else {
            $info[] = "Frecuencia configurada: $frequency";
            
            // 3. Verificar si hay evento programado
            $next_run = wp_next_scheduled('ai_auto_blog_generate_post');
            
            if ($next_run === false) {
                $issues[] = 'NO hay eventos programados aunque la frecuencia está en: ' . $frequency;
                $issues[] = 'El cron debería haberse registrado pero no está.';
            } else {
                $time_until = human_time_diff(time(), $next_run);
                $formatted_time = gmdate('Y-m-d H:i:s', $next_run);
                
                if ($next_run < time()) {
                    $warnings[] = "El evento programado está en el PASADO: $formatted_time";
                    $warnings[] = "Esto significa que WordPress no está ejecutando los crons.";
                } else {
                    $info[] = "Próxima ejecución: $formatted_time (en $time_until)";
                }
            }
        }
        
        // 4. Verificar última generación
        $last_posts = get_posts(array(
            'post_type' => 'ai_blog_post',
            'posts_per_page' => 1,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        if (!empty($last_posts)) {
            $last_post_date = $last_posts[0]->post_date;
            $time_since = human_time_diff(strtotime($last_post_date), time());
            $info[] = "Último post generado hace: $time_since ($last_post_date)";
            
            // Verificar si debería haber generado más posts
            $expected_interval = self::get_expected_interval($frequency);
            $time_since_seconds = time() - strtotime($last_post_date);
            
            if ($expected_interval && $time_since_seconds > ($expected_interval * 2)) {
                $issues[] = "Han pasado $time_since desde el último post, pero debería generar cada " . 
                           human_time_diff(0, $expected_interval);
            }
        } else {
            $warnings[] = 'No se han generado posts todavía.';
        }
        
        // 5. Verificar logs de errores
        $logs = get_option('ai_auto_blog_logs', array());
        $error_logs = array_filter($logs, function($log) {
            return isset($log['type']) && $log['type'] === 'error';
        });
        
        if (!empty($error_logs)) {
            $recent_errors = array_slice(array_reverse($error_logs), 0, 3);
            $warnings[] = 'Hay errores en el log. Últimos 3 errores:';
            foreach ($recent_errors as $error) {
                $warnings[] = "  - [{$error['timestamp']}] {$error['message']}";
            }
        }
        
        // 6. Verificar problemas comunes de hosting
        if (!function_exists('wp_next_scheduled')) {
            $issues[] = 'Función wp_next_scheduled no disponible. Problema crítico de WordPress.';
        }
        
        return array(
            'status' => empty($issues) ? (empty($warnings) ? 'healthy' : 'warning') : 'error',
            'issues' => $issues,
            'warnings' => $warnings,
            'info' => $info
        );
    }
    
    /**
     * Obtener intervalo esperado en segundos
     */
    private static function get_expected_interval($frequency) {
        $intervals = array(
            'daily' => DAY_IN_SECONDS,
            'weekly' => WEEK_IN_SECONDS,
            'monthly' => MONTH_IN_SECONDS
        );
        
        return isset($intervals[$frequency]) ? $intervals[$frequency] : false;
    }
    
    /**
     * Forzar reprogramación del cron
     */
    public static function force_reschedule() {
        // Limpiar todos los eventos programados
        wp_clear_scheduled_hook('ai_auto_blog_generate_post');
        
        // Obtener configuración
        $settings = get_option('ai_auto_blog_settings', array());
        $frequency = isset($settings['frequency']) ? $settings['frequency'] : 'manual';
        
        if ($frequency !== 'manual') {
            // Calcular próxima ejecución
            $next_run = strtotime('tomorrow 9:00 AM');
            
            // Programar nuevo evento
            $scheduled = wp_schedule_event($next_run, $frequency, 'ai_auto_blog_generate_post');
            
            if ($scheduled === false) {
                return array(
                    'success' => false,
                    'message' => 'Error al reprogramar el evento'
                );
            }
            
            return array(
                'success' => true,
                'message' => 'Evento reprogramado correctamente',
                'next_run' => gmdate('Y-m-d H:i:s', $next_run),
                'frequency' => $frequency
            );
        }
        
        return array(
            'success' => true,
            'message' => 'Modo manual - no se programa ningún evento'
        );
    }
    
    /**
     * Ejecutar generación manual (test)
     */
    public static function test_generation() {
        $generator = new AI_Auto_Blog_Post_Generator();
        $result = $generator->generate_post();
        
        return array(
            'success' => $result['success'],
            'message' => $result['message'],
            'details' => $result
        );
    }
    
    /**
     * Listar todos los cron jobs de WordPress
     */
    public static function list_all_cron_jobs() {
        $cron_array = _get_cron_array();
        $all_events = array();
        
        if ($cron_array) {
            foreach ($cron_array as $timestamp => $cron) {
                foreach ($cron as $hook => $details) {
                    $all_events[] = array(
                        'hook' => $hook,
                        'timestamp' => $timestamp,
                        'formatted_time' => gmdate('Y-m-d H:i:s', $timestamp),
                        'time_until' => human_time_diff(time(), $timestamp),
                        'is_past' => $timestamp < time(),
                        'schedule' => isset($details[0]['schedule']) ? $details[0]['schedule'] : 'single',
                    );
                }
            }
            
            // Ordenar por timestamp
            usort($all_events, function($a, $b) {
                return $a['timestamp'] - $b['timestamp'];
            });
        }
        
        return $all_events;
    }
    
    /**
     * Limpiar eventos pasados del cron
     */
    public static function clean_past_events() {
        $cron_array = _get_cron_array();
        $cleaned = 0;
        
        if ($cron_array) {
            $current_time = time();
            
            foreach ($cron_array as $timestamp => $cron) {
                if ($timestamp < $current_time) {
                    foreach ($cron as $hook => $details) {
                        wp_unschedule_event($timestamp, $hook);
                        $cleaned++;
                    }
                }
            }
        }
        
        return array(
            'success' => true,
            'cleaned' => $cleaned,
            'message' => "$cleaned eventos pasados eliminados"
        );
    }
}
