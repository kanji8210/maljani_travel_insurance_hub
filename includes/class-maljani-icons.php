<?php
/**
 * Maljani Icons Shortcode
 * Provides icon display functionality for the frontend
 */

class Maljani_Icons_Shortcode {
    
    public function __construct() {
        add_shortcode('maljani_icon', [$this, 'render_icon']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);
    }

    /**
     * Enqueue styles for icons
     */
    public function enqueue_styles() {
        // Enqueue Dashicons for frontend if not already loaded
        wp_enqueue_style('dashicons');
        
        // Styles gérés par le système d'isolation - plus besoin d'inline styles
        // Les styles d'icônes sont maintenant dans maljani-isolated.css
    }

    /**
     * Render icon shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string Generated HTML
     */
    public function render_icon($atts) {
        // Get isolation manager
        $isolation = Maljani_Style_Isolation::instance();
        
        $atts = shortcode_atts([
            'name' => 'star-filled',
            'size' => 'medium',
            'color' => '',
            'text' => '',
            'link' => '',
            'class' => '',
            'style' => 'dashicons' // dashicons, fontawesome, custom
        ], $atts);

        $classes = ['maljani-icon'];
        $classes[] = 'size-' . sanitize_html_class($atts['size']);
        
        if (!empty($atts['class'])) {
            $classes[] = sanitize_html_class($atts['class']);
        }

        $style_attr = '';
        if (!empty($atts['color'])) {
            $style_attr = 'style="color: ' . esc_attr($atts['color']) . ';"';
        }

        $icon_html = '';
        
        switch ($atts['style']) {
            case 'dashicons':
                $icon_classes = array_merge($classes, ['dashicons', 'dashicons-' . sanitize_html_class($atts['name'])]);
                $icon_html = '<span class="' . esc_attr(implode(' ', $icon_classes)) . '" ' . $style_attr . '></span>';
                break;
                
            case 'fontawesome':
                $icon_classes = array_merge($classes, ['fas', 'fa-' . sanitize_html_class($atts['name'])]);
                $icon_html = '<i class="' . esc_attr(implode(' ', $icon_classes)) . '" ' . $style_attr . '></i>';
                break;
                
            case 'custom':
                $icon_html = '<span class="' . esc_attr(implode(' ', $classes)) . ' custom-icon-' . sanitize_html_class($atts['name']) . '" ' . $style_attr . '></span>';
                break;
                
            default:
                $icon_classes = array_merge($classes, ['dashicons', 'dashicons-' . sanitize_html_class($atts['name'])]);
                $icon_html = '<span class="' . esc_attr(implode(' ', $icon_classes)) . '" ' . $style_attr . '></span>';
        }

        // Wrap with text if provided
        if (!empty($atts['text'])) {
            $icon_html = '<span class="maljani-icon-wrapper">' . $icon_html . '<span class="icon-text">' . esc_html($atts['text']) . '</span></span>';
        }

        // Wrap with link if provided
        if (!empty($atts['link'])) {
            $icon_html = '<a href="' . esc_url($atts['link']) . '" class="maljani-icon-link">' . $icon_html . '</a>';
        }

        // Wrap with isolation container and return
        return $isolation->wrap_output($icon_html, ['class' => 'maljani-icon-container']);
    }

    /**
     * Get available Dashicons list
     * 
     * @return array List of available dashicons
     */
    public static function get_available_dashicons() {
        return [
            // Admin Menu
            'menu', 'admin-site', 'dashboard', 'admin-post', 'admin-media', 'admin-links',
            'admin-page', 'admin-comments', 'admin-appearance', 'admin-plugins', 'admin-users',
            'admin-tools', 'admin-settings', 'admin-network', 'admin-home', 'admin-generic',
            
            // Post Formats
            'format-standard', 'format-aside', 'format-image', 'format-gallery', 'format-video',
            'format-status', 'format-quote', 'format-chat', 'format-audio',
            
            // Media
            'camera', 'images-alt', 'images-alt2', 'video-alt', 'video-alt2', 'video-alt3',
            
            // Image Editing
            'image-crop', 'image-rotate', 'image-rotate-left', 'image-rotate-right',
            'image-flip-vertical', 'image-flip-horizontal', 'image-filter',
            
            // TinyMCE
            'editor-bold', 'editor-italic', 'editor-ul', 'editor-ol', 'editor-quote',
            'editor-alignleft', 'editor-aligncenter', 'editor-alignright', 'editor-insertmore',
            
            // Posts
            'edit', 'post-status', 'edit-page', 'edit-large', 'post-trash',
            
            // Sorting
            'sort', 'randomize', 'list-view', 'excerpt-view', 'grid-view',
            
            // Social
            'share', 'share-alt', 'share-alt2', 'twitter', 'rss', 'email', 'email-alt',
            'facebook', 'facebook-alt', 'googleplus', 'networking',
            
            // WordPress
            'wordpress', 'wordpress-alt', 'pressthis',
            
            // Internal/Products
            'update', 'screenoptions', 'cart', 'feedback', 'cloud', 'translation',
            
            // Taxonomies
            'tag', 'category',
            
            // Alerts/Notifications
            'yes', 'no', 'no-alt', 'plus', 'plus-alt', 'plus-alt2', 'minus',
            'dismiss', 'marker', 'star-filled', 'star-half', 'star-empty',
            'flag', 'warning', 'location', 'location-alt', 'vault', 'shield',
            'shield-alt', 'sos', 'search', 'slides', 'analytics', 'chart-pie',
            'chart-bar', 'chart-line', 'chart-area',
            
            // Media Player
            'controls-play', 'controls-pause', 'controls-forward', 'controls-skipforward',
            'controls-back', 'controls-skipback', 'controls-repeat', 'controls-volumeon',
            'controls-volumeoff',
            
            // Misc/Post
            'clock', 'lightbulb', 'microphone', 'desktop', 'laptop', 'tablet', 'smartphone',
            'phone', 'index-card', 'carrot', 'building', 'store', 'album', 'palmtree',
            'tickets-alt', 'money', 'smiley', 'thumbs-up', 'thumbs-down', 'layout',
            
            // Arrows
            'arrow-up', 'arrow-down', 'arrow-right', 'arrow-left',
            'arrow-up-alt', 'arrow-down-alt', 'arrow-right-alt', 'arrow-left-alt',
            'arrow-up-alt2', 'arrow-down-alt2', 'arrow-right-alt2', 'arrow-left-alt2',
            
            // Generic
            'backup', 'portfolio', 'category', 'archive', 'tagcloud', 'text'
        ];
    }
}

// Initialize the shortcode
new Maljani_Icons_Shortcode();
