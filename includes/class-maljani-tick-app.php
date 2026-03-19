<?php
/**
 * Maljani_Tick_App Class
 * Integrates the React 'Tick' application into WordPress via shortcode.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maljani_Tick_App {
    public function __construct() {
        add_shortcode('maljani_tick_app', [$this, 'render_app']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Enqueue the React application assets.
     * In development, it points to the Vite dev server.
     * In production, it would point to the /tick/dist/ folder.
     */
    public function enqueue_assets() {
        global $post;
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'maljani_tick_app')) {
            return;
        }

        // Check if dev server is running (simplified check)
        // In a real production setup, we'd check a constant or setting.
        $is_dev = true; 

        if ($is_dev) {
            wp_enqueue_script('maljani-tick-vite', 'http://localhost:5174/@vite/client', [], null, true);
            wp_enqueue_script('maljani-tick-app', 'http://localhost:5174/src/main.jsx', ['maljani-tick-vite'], null, true);
            // Vite doesn't need explicit CSS enqueue in dev as it injects it.
        } else {
            // Production paths would go here
            // wp_enqueue_script('maljani-tick-app', ...);
            // wp_enqueue_style('maljani-tick-style', ...);
        }
    }

    /**
     * Render the mount point for the React app.
     */
    public function render_app($atts) {
        return '<div id="root"></div>';
    }
}

new Maljani_Tick_App();
