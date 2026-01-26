<?php
/**
 * Maljani Cache Manager
 *
 * Provides caching functionality for expensive operations
 * 
 * @since 1.0.1
 * @package Maljani
 */

class Maljani_Cache {
    
    /**
     * Cache group prefix
     *
     * @var string
     */
    const CACHE_GROUP = 'maljani';
    
    /**
     * Default cache expiration (1 hour)
     *
     * @var int
     */
    const DEFAULT_EXPIRATION = 3600;
    
    /**
     * Get cached premium for a policy and duration
     *
     * @param int $policy_id Policy ID
     * @param int $days Number of days
     * @return string|false Premium or false if not found
     */
    public static function get_premium($policy_id, $days) {
        $cache_key = self::get_premium_cache_key($policy_id, $days);
        $premium = get_transient($cache_key);
        
        if (false === $premium) {
            $premiums = get_post_meta($policy_id, '_policy_day_premiums', true);
            $premium = '';
            
            if (is_array($premiums)) {
                foreach ($premiums as $row) {
                    if ($days >= intval($row['from']) && $days <= intval($row['to'])) {
                        $premium = $row['premium'];
                        break;
                    }
                }
            }
            
            // Cache for 24 hours
            set_transient($cache_key, $premium, DAY_IN_SECONDS);
            
            // Log cache miss
            if (class_exists('Maljani_Logger')) {
                Maljani_Logger::get_instance()->debug('Premium cache miss', [
                    'policy_id' => $policy_id,
                    'days' => $days
                ]);
            }
        }
        
        return $premium;
    }
    
    /**
     * Get cached policies with optional filters
     *
     * @param array $args Query arguments
     * @return array Array of policy posts
     */
    public static function get_policies($args = []) {
        $cache_key = 'policies_' . md5(serialize($args));
        $policies = wp_cache_get($cache_key, self::CACHE_GROUP);
        
        if (false === $policies) {
            $defaults = [
                'post_type' => 'policy',
                'posts_per_page' => 50,
                'post_status' => 'publish',
                'orderby' => 'title',
                'order' => 'ASC'
            ];
            
            $args = wp_parse_args($args, $defaults);
            $query = new WP_Query($args);
            $policies = $query->posts;
            
            // Cache for 1 hour
            wp_cache_set($cache_key, $policies, self::CACHE_GROUP, HOUR_IN_SECONDS);
            
            // Log cache miss
            if (class_exists('Maljani_Logger')) {
                Maljani_Logger::get_instance()->debug('Policies cache miss', [
                    'args' => $args
                ]);
            }
        }
        
        return $policies;
    }
    
    /**
     * Get cached regions
     *
     * @return array Array of region terms
     */
    public static function get_regions() {
        $cache_key = 'regions_all';
        $regions = wp_cache_get($cache_key, self::CACHE_GROUP);
        
        if (false === $regions) {
            $regions = get_terms([
                'taxonomy' => 'policy_region',
                'hide_empty' => true,
                'orderby' => 'name',
                'order' => 'ASC'
            ]);
            
            // Cache for 1 hour
            wp_cache_set($cache_key, $regions, self::CACHE_GROUP, HOUR_IN_SECONDS);
        }
        
        return $regions;
    }
    
    /**
     * Clear all plugin caches
     *
     * @return bool Success status
     */
    public static function clear_all() {
        global $wpdb;
        
        // Clear transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_maljani_%' 
            OR option_name LIKE '_transient_timeout_maljani_%'"
        );
        
        // Clear object cache
        wp_cache_flush();
        
        // Log cache clear
        if (class_exists('Maljani_Logger')) {
            Maljani_Logger::get_instance()->info('All caches cleared');
        }
        
        return true;
    }
    
    /**
     * Clear premium cache for a specific policy
     *
     * @param int $policy_id Policy ID
     * @return bool Success status
     */
    public static function clear_policy_cache($policy_id) {
        global $wpdb;
        
        // Clear all premium transients for this policy
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE %s 
            OR option_name LIKE %s",
            '_transient_maljani_premium_' . $policy_id . '_%',
            '_transient_timeout_maljani_premium_' . $policy_id . '_%'
        ));
        
        // Log cache clear
        if (class_exists('Maljani_Logger')) {
            Maljani_Logger::get_instance()->info('Policy cache cleared', [
                'policy_id' => $policy_id
            ]);
        }
        
        return true;
    }
    
    /**
     * Generate premium cache key
     *
     * @param int $policy_id Policy ID
     * @param int $days Number of days
     * @return string Cache key
     */
    private static function get_premium_cache_key($policy_id, $days) {
        return 'maljani_premium_' . $policy_id . '_' . $days;
    }
    
    /**
     * Schedule automatic cache cleanup
     */
    public static function schedule_cleanup() {
        if (!wp_next_scheduled('maljani_cache_cleanup')) {
            wp_schedule_event(time(), 'daily', 'maljani_cache_cleanup');
        }
    }
    
    /**
     * Unschedule cache cleanup
     */
    public static function unschedule_cleanup() {
        wp_clear_scheduled_hook('maljani_cache_cleanup');
    }
}

// Hook for cache cleanup when policy is updated
add_action('save_post_policy', function($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    Maljani_Cache::clear_policy_cache($post_id);
}, 10, 1);

// Hook for scheduled cleanup
add_action('maljani_cache_cleanup', [Maljani_Cache::class, 'clear_all']);
