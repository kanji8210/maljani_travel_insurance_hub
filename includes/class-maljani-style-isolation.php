<?php

/**
 * Maljani Style Isolation Manager
 * 
 * Ensures plugin styles are not affected by theme styles
 * Provides wrapper functions for isolated output
 * 
 * @package Maljani_Travel_Insurance_Hub
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Maljani_Style_Isolation {
    
    /**
     * The single instance of the class
     */
    private static $_instance = null;
    
    /**
     * Plugin version for cache busting
     */
    private $version = '1.0.0';
    
    /**
     * Main Instance
     * Ensures only one instance is loaded or can be loaded
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_isolated_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_isolated_styles'));
        add_filter('maljani_output_wrapper', array($this, 'wrap_output'), 10, 2);
    }
    
    /**
     * Enqueue isolated styles for frontend
     */
    public function enqueue_isolated_styles() {
        // Enqueue with high priority to override theme styles
        wp_enqueue_style(
            'maljani-isolated-styles',
            plugin_dir_url(__FILE__) . 'css/maljani-isolated.css',
            array(),
            $this->version . '_' . time(), // Force reload during development
            'all'
        );
        
        // Add inline critical CSS for immediate loading
        $critical_css = $this->get_critical_css();
        wp_add_inline_style('maljani-isolated-styles', $critical_css);
    }
    
    /**
     * Enqueue isolated styles for admin
     */
    public function enqueue_admin_isolated_styles($hook) {
        // Only load on Maljani admin pages
        if (strpos($hook, 'maljani') !== false || get_post_type() === 'maljani_policy') {
            wp_enqueue_style(
                'maljani-admin-isolated',
                plugin_dir_url(__FILE__) . 'css/maljani-isolated.css',
                array(),
                $this->version,
                'all'
            );
        }
    }
    
    /**
     * Get critical CSS for immediate loading
     */
    private function get_critical_css() {
        return '
        .maljani-plugin-container {
            box-sizing: border-box !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
            line-height: 1.5 !important;
            color: #333 !important;
        }
        .maljani-plugin-container * {
            box-sizing: border-box !important;
        }
        ';
    }
    
    /**
     * Wrap output with isolation container
     * 
     * @param string $content The content to wrap
     * @param array $attributes Additional attributes for the wrapper
     * @return string Wrapped content
     */
    public function wrap_output($content, $attributes = array()) {
        
        // Build wrapper attributes
        $wrapper_class = 'maljani-plugin-container';
        if (isset($attributes['class'])) {
            $wrapper_class .= ' ' . esc_attr($attributes['class']);
        }
        
        $wrapper_id = '';
        if (isset($attributes['id'])) {
            $wrapper_id = ' id="' . esc_attr($attributes['id']) . '"';
        }
        
        $wrapper_style = '';
        if (isset($attributes['style'])) {
            $wrapper_style = ' style="' . esc_attr($attributes['style']) . '"';
        }
        
        // Create isolated wrapper
        $output = '<div class="' . esc_attr($wrapper_class) . '"' . $wrapper_id . $wrapper_style . '>';
        $output .= $content;
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Get isolated form HTML
     * 
     * @param string $form_content The form content
     * @param string $form_type Type of form (sales, dashboard, registration)
     * @return string Isolated form HTML
     */
    public function get_isolated_form($form_content, $form_type = 'default') {
        
        $wrapper_class = 'maljani-plugin-container maljani-form-' . esc_attr($form_type);
        
        $output = '<div class="' . $wrapper_class . '">';
        $output .= '<div class="maljani-form-wrapper">';
        $output .= $form_content;
        $output .= '</div>';
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Get isolated button HTML
     * 
     * @param string $text Button text
     * @param string $url Button URL
     * @param string $type Button type (primary, secondary)
     * @param array $attributes Additional attributes
     * @return string Isolated button HTML
     */
    public function get_isolated_button($text, $url = '#', $type = 'primary', $attributes = array()) {
        
        $button_class = 'maljani-btn maljani-btn-' . esc_attr($type);
        if (isset($attributes['class'])) {
            $button_class .= ' ' . esc_attr($attributes['class']);
        }
        
        $button_id = '';
        if (isset($attributes['id'])) {
            $button_id = ' id="' . esc_attr($attributes['id']) . '"';
        }
        
        $button_attrs = '';
        foreach ($attributes as $key => $value) {
            if (!in_array($key, array('class', 'id'))) {
                $button_attrs .= ' ' . esc_attr($key) . '="' . esc_attr($value) . '"';
            }
        }
        
        if ($url === '#' || empty($url)) {
            return '<button type="button" class="' . $button_class . '"' . $button_id . $button_attrs . '>' . esc_html($text) . '</button>';
        } else {
            return '<a href="' . esc_url($url) . '" class="' . $button_class . '"' . $button_id . $button_attrs . '>' . esc_html($text) . '</a>';
        }
    }
    
    /**
     * Get isolated icon HTML
     * 
     * @param string $icon_name Icon name
     * @param string $size Icon size (small, medium, large, xl)
     * @param string $color Icon color
     * @param string $style Icon style (dashicon, fontawesome)
     * @return string Isolated icon HTML
     */
    public function get_isolated_icon($icon_name, $size = 'medium', $color = '', $style = 'dashicon') {
        
        $icon_class = 'maljani-icon maljani-icon-' . esc_attr($style) . ' size-' . esc_attr($size);
        
        if ($style === 'dashicon') {
            $icon_class .= ' dashicons dashicons-' . esc_attr($icon_name);
        } elseif ($style === 'fontawesome') {
            $icon_class .= ' fa fa-' . esc_attr($icon_name);
        }
        
        $icon_style = '';
        if (!empty($color)) {
            $icon_style = ' style="color: ' . esc_attr($color) . ';"';
        }
        
        return '<span class="' . $icon_class . '"' . $icon_style . '></span>';
    }
    
    /**
     * Get isolated notice HTML
     * 
     * @param string $message Notice message
     * @param string $type Notice type (success, error, warning, info)
     * @param bool $dismissible Whether notice is dismissible
     * @return string Isolated notice HTML
     */
    public function get_isolated_notice($message, $type = 'info', $dismissible = false) {
        
        $notice_class = 'maljani-notice maljani-notice-' . esc_attr($type);
        if ($dismissible) {
            $notice_class .= ' is-dismissible';
        }
        
        $output = '<div class="maljani-plugin-container">';
        $output .= '<div class="' . $notice_class . '">';
        $output .= '<p>' . wp_kses_post($message) . '</p>';
        if ($dismissible) {
            $output .= '<button type="button" class="maljani-notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>';
        }
        $output .= '</div>';
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Get isolated table HTML
     * 
     * @param array $headers Table headers
     * @param array $rows Table rows
     * @param array $attributes Table attributes
     * @return string Isolated table HTML
     */
    public function get_isolated_table($headers, $rows, $attributes = array()) {
        
        $table_class = 'maljani-table';
        if (isset($attributes['class'])) {
            $table_class .= ' ' . esc_attr($attributes['class']);
        }
        
        $output = '<div class="maljani-plugin-container">';
        $output .= '<table class="' . $table_class . '">';
        
        // Headers
        if (!empty($headers)) {
            $output .= '<thead><tr>';
            foreach ($headers as $header) {
                $output .= '<th>' . esc_html($header) . '</th>';
            }
            $output .= '</tr></thead>';
        }
        
        // Rows
        if (!empty($rows)) {
            $output .= '<tbody>';
            foreach ($rows as $row) {
                $output .= '<tr>';
                foreach ($row as $cell) {
                    $output .= '<td>' . wp_kses_post($cell) . '</td>';
                }
                $output .= '</tr>';
            }
            $output .= '</tbody>';
        }
        
        $output .= '</table>';
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Check if current theme has known conflicts
     * 
     * @return array List of potential conflicts
     */
    public function check_theme_conflicts() {
        $current_theme = wp_get_theme();
        $theme_name = $current_theme->get('Name');
        $conflicts = array();
        
        // Known problematic themes
        $problematic_themes = array(
            'Twenty Twenty-One' => array('form styles', 'button overrides'),
            'Astra' => array('CSS reset conflicts'),
            'GeneratePress' => array('form field styling'),
            'OceanWP' => array('button styling conflicts'),
        );
        
        if (isset($problematic_themes[$theme_name])) {
            $conflicts = $problematic_themes[$theme_name];
        }
        
        return $conflicts;
    }
    
    /**
     * Generate CSS specificity to override theme styles
     * 
     * @param string $selector CSS selector
     * @param int $specificity_level Level of specificity needed (1-3)
     * @return string Enhanced selector
     */
    public function enhance_specificity($selector, $specificity_level = 2) {
        
        $prefix = '.maljani-plugin-container';
        
        switch ($specificity_level) {
            case 1:
                return $prefix . ' ' . $selector;
            case 2:
                return $prefix . ' .maljani-form-wrapper ' . $selector;
            case 3:
                return 'body ' . $prefix . ' .maljani-form-wrapper ' . $selector;
            default:
                return $prefix . ' ' . $selector;
        }
    }
    
    /**
     * Get inline styles for critical rendering
     * 
     * @return string Critical CSS
     */
    public function get_inline_critical_styles() {
        return '
        <style id="maljani-critical-css">
        .maljani-plugin-container {
            all: initial !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
            line-height: 1.5 !important;
            color: #333 !important;
            display: block !important;
            position: relative !important;
        }
        .maljani-plugin-container * {
            all: unset !important;
            display: revert !important;
            box-sizing: border-box !important;
        }
        </style>
        ';
    }
}

// Initialize the isolation manager
Maljani_Style_Isolation::instance();
