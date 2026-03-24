<?php
/**
 * Clase para registrar el Custom Post Type
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Auto_Blog_CPT {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'register_post_type'));
    }
    
    /**
     * Registrar Custom Post Type
     */
    public static function register_post_type() {
        $labels = array(
            'name'                  => _x('AI Posts', 'Post type general name', 'ai-auto-blog'),
            'singular_name'         => _x('AI Post', 'Post type singular name', 'ai-auto-blog'),
            'menu_name'             => _x('AI Posts', 'Admin Menu text', 'ai-auto-blog'),
            'name_admin_bar'        => _x('AI Post', 'Add New on Toolbar', 'ai-auto-blog'),
            'add_new'               => __('Añadir nuevo', 'ai-auto-blog'),
            'add_new_item'          => __('Añadir nuevo AI Post', 'ai-auto-blog'),
            'new_item'              => __('Nuevo AI Post', 'ai-auto-blog'),
            'edit_item'             => __('Editar AI Post', 'ai-auto-blog'),
            'view_item'             => __('Ver AI Post', 'ai-auto-blog'),
            'all_items'             => __('Todos los AI Posts', 'ai-auto-blog'),
            'search_items'          => __('Buscar AI Posts', 'ai-auto-blog'),
            'parent_item_colon'     => __('AI Posts padre:', 'ai-auto-blog'),
            'not_found'             => __('No se encontraron AI Posts.', 'ai-auto-blog'),
            'not_found_in_trash'    => __('No se encontraron AI Posts en la papelera.', 'ai-auto-blog'),
            'featured_image'        => _x('Imagen destacada', 'Overrides the "Featured Image" phrase', 'ai-auto-blog'),
            'set_featured_image'    => _x('Establecer imagen destacada', 'Overrides the "Set featured image" phrase', 'ai-auto-blog'),
            'remove_featured_image' => _x('Eliminar imagen destacada', 'Overrides the "Remove featured image" phrase', 'ai-auto-blog'),
            'use_featured_image'    => _x('Usar como imagen destacada', 'Overrides the "Use as featured image" phrase', 'ai-auto-blog'),
            'archives'              => _x('Archivos de AI Posts', 'The post type archive label', 'ai-auto-blog'),
            'insert_into_item'      => _x('Insertar en AI post', 'Overrides the "Insert into post" phrase', 'ai-auto-blog'),
            'uploaded_to_this_item' => _x('Subido a este AI post', 'Overrides the "Uploaded to this post" phrase', 'ai-auto-blog'),
            'filter_items_list'     => _x('Filtrar lista de AI posts', 'Screen reader text for the filter links', 'ai-auto-blog'),
            'items_list_navigation' => _x('Navegación de lista de AI posts', 'Screen reader text for the pagination', 'ai-auto-blog'),
            'items_list'            => _x('Lista de AI posts', 'Screen reader text for the items list', 'ai-auto-blog'),
        );
        
        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'ai-post'),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 5,
            'menu_icon'          => 'dashicons-admin-post',
            'supports'           => array('title', 'editor', 'thumbnail', 'excerpt', 'author', 'revisions'),
            'show_in_rest'       => true, // Habilitar Gutenberg
        );
        
        register_post_type('ai_blog_post', $args);
    }
}
