<?php

class Maljani_Policy_Single {

    public static function init() {
        return new self();
    }

    public function __construct() {
        // Hook into the single_template filter to override the template for our CPT
        add_filter('single_template', [$this, 'override_single_template']);
    }

    /**
     * Override standard single.php with our custom template.
     */
    public function override_single_template($single_template) {
        global $post;

        if ($post->post_type === 'policy') {
            $custom_template = plugin_dir_path(dirname(__FILE__)) . 'templates/single-maljani_policy.php';
            
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        return $single_template;
    }
}
