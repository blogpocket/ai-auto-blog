/**
 * JavaScript para la administración de AI Auto Blog
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        /**
         * Probar conexión con API
         */
        $('#test-connection').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $result = $('#test-result');
            var apiKey = $('#api_key').val();
            var model = $('input[name="ai_auto_blog_settings[model]"]:checked').val();
            
            if (!apiKey) {
                $result.removeClass('success error testing')
                       .addClass('error')
                       .html('⚠️ ' + aiAutoBlogAjax.strings.test_error + ': API key vacía');
                return;
            }
            
            // Mostrar estado de carga
            $button.prop('disabled', true);
            $result.removeClass('success error')
                   .addClass('testing')
                   .html('<span class="ai-auto-blog-spinner"></span> ' + aiAutoBlogAjax.strings.testing);
            
            // Hacer petición AJAX
            $.ajax({
                url: aiAutoBlogAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'ai_auto_blog_test_connection',
                    nonce: aiAutoBlogAjax.nonce,
                    api_key: apiKey,
                    model: model
                },
                success: function(response) {
                    $button.prop('disabled', false);
                    
                    if (response.success) {
                        $result.removeClass('testing error')
                               .addClass('success')
                               .html('✅ ' + response.data.message);
                    } else {
                        $result.removeClass('testing success')
                               .addClass('error')
                               .html('❌ ' + response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    $button.prop('disabled', false);
                    $result.removeClass('testing success')
                           .addClass('error')
                           .html('❌ Error de conexión: ' + error);
                }
            });
        });
        
        /**
         * Detectar modelos disponibles
         */
        $('#detect-models').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $result = $('#available-models');
            var apiKey = $('#api_key').val();
            
            if (!apiKey) {
                $result.html('<p style="color: #d63638;">⚠️ Por favor, introduce tu API key primero</p>');
                return;
            }
            
            // Mostrar estado de carga
            $button.prop('disabled', true);
            $result.html('<p><span class="ai-auto-blog-spinner"></span> Detectando modelos disponibles...</p>');
            
            // Hacer petición AJAX
            $.ajax({
                url: aiAutoBlogAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'ai_auto_blog_detect_models',
                    nonce: aiAutoBlogAjax.nonce,
                    api_key: apiKey
                },
                success: function(response) {
                    $button.prop('disabled', false);
                    
                    if (response.success && response.data.models) {
                        var html = '<div style="background: #f0f0f1; padding: 10px; border-radius: 4px; margin-top: 10px;">';
                        html += '<strong>✅ Modelos disponibles:</strong><br>';
                        html += '<ul style="margin: 10px 0; padding-left: 20px;">';
                        
                        response.data.models.forEach(function(model) {
                            html += '<li><code>' + escapeHtml(model) + '</code></li>';
                        });
                        
                        html += '</ul>';
                        html += '<p style="margin: 5px 0 0 0;"><em>Usa cualquiera de estos modelos en las opciones de arriba</em></p>';
                        html += '</div>';
                        
                        $result.html(html);
                    } else {
                        $result.html('<p style="color: #d63638;">❌ ' + (response.data.message || 'Error al detectar modelos') + '</p>');
                    }
                },
                error: function(xhr, status, error) {
                    $button.prop('disabled', false);
                    $result.html('<p style="color: #d63638;">❌ Error de conexión: ' + escapeHtml(error) + '</p>');
                }
            });
        });
        
        /**
         * Generar post ahora
         */
        $('#generate-now').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $result = $('#generate-result');
            var $output = $('#generation-output');
            
            // Confirmar acción
            if (!confirm('¿Estás seguro de que quieres generar un nuevo post ahora?')) {
                return;
            }
            
            // Mostrar estado de carga
            $button.prop('disabled', true);
            $result.removeClass('success error')
                   .addClass('generating')
                   .html('<span class="ai-auto-blog-spinner"></span> ' + aiAutoBlogAjax.strings.generating);
            $output.removeClass('visible').html('');
            
            // Hacer petición AJAX
            $.ajax({
                url: aiAutoBlogAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'ai_auto_blog_generate_now',
                    nonce: aiAutoBlogAjax.nonce
                },
                timeout: 120000, // 2 minutos timeout
                success: function(response) {
                    $button.prop('disabled', false);
                    
                    if (response.success) {
                        $result.removeClass('generating error')
                               .addClass('success')
                               .html('✅ ' + response.data.message);
                        
                        // Mostrar información del post generado
                        var outputHtml = '<h3>✨ Post Generado Exitosamente</h3>';
                        outputHtml += '<p><strong>Título:</strong> ' + escapeHtml(response.data.post_title) + '</p>';
                        
                        if (response.data.topic_used) {
                            outputHtml += '<p><strong>Tema:</strong> 🎲 ' + escapeHtml(response.data.topic_used) + '</p>';
                        }
                        
                        if (response.data.image_generated) {
                            outputHtml += '<p><strong>Imagen destacada:</strong> ✅ Generada</p>';
                        } else if (response.data.image_note === 'api_not_available') {
                            outputHtml += '<p><strong>Imagen destacada:</strong> ⚠️ No disponible</p>';
                            outputHtml += '<p style="background: #fff3cd; padding: 10px; border-left: 4px solid #ffc107; margin: 10px 0;">';
                            outputHtml += '<strong>Nota:</strong> La API de generación de imágenes de Google aún no está disponible públicamente. ';
                            outputHtml += 'Puedes añadir una imagen destacada manualmente editando el post, o desactivar esta opción en la configuración.';
                            outputHtml += '</p>';
                        }
                        
                        outputHtml += '<p>';
                        outputHtml += '<a href="' + response.data.edit_url + '" class="button button-primary" target="_blank">Editar Post</a> ';
                        outputHtml += '<a href="' + response.data.post_url + '" class="button button-secondary" target="_blank">Ver Post</a>';
                        outputHtml += '</p>';
                        
                        $output.html(outputHtml).addClass('visible');
                        
                        // Scroll suave hacia el resultado
                        $('html, body').animate({
                            scrollTop: $output.offset().top - 100
                        }, 500);
                        
                    } else {
                        $result.removeClass('generating success')
                               .addClass('error')
                               .html('❌ ' + response.data.message);
                        
                        var errorHtml = '<h3>❌ Error en la Generación</h3>';
                        errorHtml += '<p>' + escapeHtml(response.data.message) + '</p>';
                        
                        if (response.data.raw_content) {
                            errorHtml += '<details>';
                            errorHtml += '<summary>Ver respuesta sin procesar</summary>';
                            errorHtml += '<pre style="background: #fff; padding: 10px; overflow-x: auto;">' + escapeHtml(response.data.raw_content) + '</pre>';
                            errorHtml += '</details>';
                        }
                        
                        $output.html(errorHtml).addClass('visible');
                    }
                },
                error: function(xhr, status, error) {
                    $button.prop('disabled', false);
                    $result.removeClass('generating success')
                           .addClass('error')
                           .html('❌ Error de conexión');
                    
                    var errorHtml = '<h3>❌ Error en la Generación</h3>';
                    
                    if (status === 'timeout') {
                        errorHtml += '<p>La generación ha tardado demasiado tiempo. Esto puede deberse a:</p>';
                        errorHtml += '<ul>';
                        errorHtml += '<li>Longitud del post muy grande</li>';
                        errorHtml += '<li>Conexión lenta con la API</li>';
                        errorHtml += '<li>Servidor sobrecargado</li>';
                        errorHtml += '</ul>';
                        errorHtml += '<p>Intenta con una longitud de post más corta o espera unos minutos.</p>';
                    } else {
                        errorHtml += '<p>Error: ' + escapeHtml(error) + '</p>';
                    }
                    
                    $output.html(errorHtml).addClass('visible');
                }
            });
        });
        
        /**
         * Mostrar/ocultar botón de generar según periodicidad
         */
        $('input[name="ai_auto_blog_settings[frequency]"]').on('change', function() {
            var frequency = $(this).val();
            var $manualSection = $('#generate-now').closest('hr').nextAll();
            
            if (frequency === 'manual') {
                $manualSection.show();
            } else {
                $manualSection.hide();
            }
        });
        
        /**
         * Helper: Escapar HTML
         */
        function escapeHtml(text) {
            if (!text) return '';
            
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
        
        /**
         * Confirmación antes de salir si hay cambios sin guardar
         */
        var formChanged = false;
        
        $('#ai-auto-blog-form :input').on('change', function() {
            formChanged = true;
        });
        
        $('#ai-auto-blog-form').on('submit', function() {
            formChanged = false;
        });
        
        $(window).on('beforeunload', function() {
            if (formChanged) {
                return '¿Estás seguro de que quieres salir? Hay cambios sin guardar.';
            }
        });
    });
    
})(jQuery);
