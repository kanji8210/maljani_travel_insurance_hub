<?php
/**
 * Maljani Travel Insurance Hub - IDE Stubs
 * 
 * This file is intended for IDE/Linter indexing purposes only.
 * It provides signatures for WordPress functions to resolve "unknown function" warnings.
 * DO NOT include this file in the production plugin logic.
 */

if ( ! function_exists( 'add_action' ) ) {
    function add_action( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {}
}
if ( ! function_exists( 'register_setting' ) ) {
    function register_setting( $option_group, $option_name, $args = array() ) {}
}
if ( ! function_exists( 'get_option' ) ) {
    function get_option( $option, $default = false ) { return $default; }
}
if ( ! function_exists( 'checked' ) ) {
    function checked( $checked, $current = true, $echo = true ) { return ''; }
}
if ( ! function_exists( 'selected' ) ) {
    function selected( $selected, $current = true, $echo = true ) { return ''; }
}
if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( $text ) { return $text; }
}
if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( $text ) { return $text; }
}
if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( $url, $protocols = null, $_context = 'display' ) { return $url; }
}
if ( ! function_exists( 'esc_textarea' ) ) {
    function esc_textarea( $text ) { return $text; }
}
if ( ! function_exists( 'admin_url' ) ) {
    function admin_url( $path = '', $scheme = 'admin' ) { return $path; }
}
if ( ! function_exists( 'get_bloginfo' ) ) {
    function get_bloginfo( $show = '', $filter = 'raw' ) { return ''; }
}
if ( ! function_exists( 'get_post_type' ) ) {
    function get_post_type( $post = null ) { return ''; }
}
if ( ! function_exists( 'get_post_field' ) ) {
    function get_post_field( $field, $post = null, $context = 'display' ) { return ''; }
}
if ( ! function_exists( 'wp_update_post' ) ) {
    function wp_update_post( $postarr = array(), $wp_error = false, $fire_after_hooks = true ) { return 0; }
}
if ( ! class_exists( 'WP_Post' ) ) {
    class WP_Post {
        /** @var int */
        public $ID = 0;
        /** @var string */
        public $post_title = '';
        /** @var string */
        public $post_content = '';
    }
}
/** @return WP_Post[] */
if ( ! function_exists( 'get_posts' ) ) {
    function get_posts( $args = null ) { 
        return array( new WP_Post() ); 
    }
}
if ( ! function_exists( 'settings_fields' ) ) {
    function settings_fields( $option_group ) {}
}
if ( ! function_exists( 'submit_button' ) ) {
    function submit_button( $text = null, $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = null ) {}
}
if ( ! function_exists( 'rest_sanitize_boolean' ) ) {
    function rest_sanitize_boolean( $value ) { return false; }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $str ) { return $str; }
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
    function sanitize_textarea_field( $str ) { return $str; }
}

if ( ! function_exists( 'add_shortcode' ) ) {
    function add_shortcode( $tag, $callback ) {}
}
if ( ! function_exists( 'has_shortcode' ) ) {
    function has_shortcode( $content, $tag ) { return false; }
}
if ( ! function_exists( 'wp_enqueue_script' ) ) {
    function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {}
}
if ( ! function_exists( 'plugin_dir_path' ) ) {
    function plugin_dir_path( $file ) { return ''; }
}
if ( ! function_exists( 'plugin_dir_url' ) ) {
    function plugin_dir_url( $file ) { return ''; }
}
