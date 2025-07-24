<?php
// templates\diagnostic.php
// Script de diagnostic pour vérifier la configuration du formulaire de vente

if (!defined('WPINC')) {
    die('Accès direct interdit');
}

function maljani_sales_diagnostic() {
    echo '<div style="max-width: 800px; margin: 20px auto; font-family: Arial, sans-serif;">';
    echo '<h2>🔧 Diagnostic du Système de Vente Maljani</h2>';
    
    // Vérification de la page configurée
    $sale_page_id = get_option('maljani_policy_sale_page');
    echo '<div style="background: #f9f9f9; padding: 15px; margin: 15px 0; border-radius: 5px;">';
    echo '<h3>📄 Configuration de la Page</h3>';
    
    if ($sale_page_id) {
        $page = get_post($sale_page_id);
        if ($page) {
            echo '<p>✅ <strong>Page configurée :</strong> ' . esc_html($page->post_title) . ' (ID: ' . $sale_page_id . ')</p>';
            echo '<p><strong>URL :</strong> <a href="' . get_permalink($sale_page_id) . '" target="_blank">' . get_permalink($sale_page_id) . '</a></p>';
            
            // Vérifier si le shortcode est présent
            if (has_shortcode($page->post_content, 'maljani_policy_sale')) {
                echo '<p>✅ <strong>Shortcode détecté :</strong> [maljani_policy_sale] présent dans le contenu</p>';
            } else {
                echo '<p>⚠️ <strong>Shortcode manquant :</strong> Ajoutez [maljani_policy_sale] au contenu de la page</p>';
            }
        } else {
            echo '<p>❌ <strong>Erreur :</strong> Page configurée mais introuvable (ID: ' . $sale_page_id . ')</p>';
        }
    } else {
        echo '<p>❌ <strong>Aucune page configurée :</strong> Allez dans Maljani Travel > Settings</p>';
    }
    echo '</div>';
    
    // Vérification des shortcodes
    echo '<div style="background: #f0f8ff; padding: 15px; margin: 15px 0; border-radius: 5px;">';
    echo '<h3>🎯 Shortcodes Disponibles</h3>';
    echo '<ul>';
    echo '<li><code>[maljani_policy_sale]</code> - Formulaire principal (4 étapes)</li>';
    echo '<li><code>[maljani_sales_form]</code> - Alias pour compatibilité</li>';
    echo '</ul>';
    echo '</div>';
    
    // Vérification des doublons de formulaires
    echo '<div style="background: #fff3cd; padding: 15px; margin: 15px 0; border-radius: 5px;">';
    echo '<h3>🔍 Détection de Doublons</h3>';
    
    // Vérifier les conditions de doublement
    $current_page_content = '';
    if (is_singular()) {
        $current_page_content = get_post()->post_content ?? '';
    }
    
    $has_shortcode = has_shortcode($current_page_content, 'maljani_policy_sale') || has_shortcode($current_page_content, 'maljani_sales_form');
    $has_sales_params = isset($_GET['maljani_sales']) || isset($_GET['policy_id']) || isset($_GET['region_id']) ||
                       (isset($_GET['departure']) && isset($_GET['return']));
    
    if ($has_shortcode && $has_sales_params) {
        echo '<p>⚠️ <strong>Risque de doublon détecté :</strong></p>';
        echo '<ul>';
        echo '<li>✅ Shortcode présent dans le contenu</li>';
        echo '<li>⚠️ Paramètres de vente dans l\'URL</li>';
        echo '</ul>';
        echo '<p><em>La fonction maybe_inject_sales_form() a été optimisée pour éviter ce problème.</em></p>';
    } else {
        echo '<p>✅ <strong>Aucun risque de doublon :</strong> Configuration normale</p>';
    }
    echo '</div>';
    
    // Vérification des assets
    echo '<div style="background: #f0f0f0; padding: 15px; margin: 15px 0; border-radius: 5px;">';
    echo '<h3>📦 Système d\'Assets</h3>';
    
    // Vérifier si les anciens fichiers existent encore
    $old_css = plugin_dir_path(__DIR__) . 'templates/sales-form.css';
    $old_js = plugin_dir_path(__DIR__) . 'templates/sales-form.js';
    
    if (file_exists($old_css) || file_exists($old_js)) {
        echo '<p>⚠️ <strong>Anciens fichiers détectés :</strong></p>';
        echo '<ul>';
        if (file_exists($old_css)) echo '<li>❌ sales-form.css (peut causer des conflits)</li>';
        if (file_exists($old_js)) echo '<li>❌ sales-form.js (peut causer des conflits)</li>';
        echo '</ul>';
        echo '<p><em>Recommandation : Supprimez ces fichiers obsolètes.</em></p>';
    } else {
        echo '<p>✅ <strong>Système optimisé :</strong> Assets intégrés dans le code PHP</p>';
        echo '<ul>';
        echo '<li>✅ Styles intégrés via get_inline_sales_styles()</li>';
        echo '<li>✅ Scripts intégrés via get_inline_sales_scripts()</li>';
        echo '<li>✅ Aucune dépendance externe</li>';
        echo '</ul>';
    }
    echo '</div>';
    
    // Vérification des régions
    echo '<div style="background: #f0fff0; padding: 15px; margin: 15px 0; border-radius: 5px;">';
    echo '<h3>🌍 Régions Disponibles</h3>';
    $regions = get_terms(['taxonomy' => 'policy_region', 'hide_empty' => false]);
    if ($regions && !is_wp_error($regions)) {
        echo '<p>✅ <strong>' . count($regions) . ' région(s) configurée(s) :</strong></p>';
        echo '<ul>';
        foreach ($regions as $region) {
            $policy_count = get_posts([
                'post_type' => 'policy',
                'posts_per_page' => -1,
                'tax_query' => [[
                    'taxonomy' => 'policy_region',
                    'field' => 'term_id',
                    'terms' => $region->term_id,
                ]],
                'fields' => 'ids'
            ]);
            echo '<li>' . esc_html($region->name) . ' (' . count($policy_count) . ' police(s))</li>';
        }
        echo '</ul>';
    } else {
        echo '<p>⚠️ <strong>Aucune région configurée :</strong> Créez des régions dans la taxonomie policy_region</p>';
    }
    echo '</div>';
    
    // Vérification des polices
    echo '<div style="background: #fff8f0; padding: 15px; margin: 15px 0; border-radius: 5px;">';
    echo '<h3>📋 Polices Disponibles</h3>';
    $policies = get_posts(['post_type' => 'policy', 'posts_per_page' => -1]);
    if ($policies) {
        echo '<p>✅ <strong>' . count($policies) . ' police(s) disponible(s)</strong></p>';
        foreach ($policies as $policy) {
            $regions = get_the_terms($policy->ID, 'policy_region');
            $region_names = $regions && !is_wp_error($regions) ? implode(', ', wp_list_pluck($regions, 'name')) : 'Aucune région';
            echo '<li>' . esc_html($policy->post_title) . ' - ' . $region_names . '</li>';
        }
    } else {
        echo '<p>❌ <strong>Aucune police disponible :</strong> Créez des polices d\'assurance</p>';
    }
    echo '</div>';
    
    // Test de l'URL
    if ($sale_page_id) {
        echo '<div style="background: #f5f5f5; padding: 15px; margin: 15px 0; border-radius: 5px;">';
        echo '<h3>🔗 Test d\'URL</h3>';
        $test_url = get_permalink($sale_page_id) . '?departure=2024-12-01&return=2024-12-10';
        echo '<p><strong>URL de test :</strong> <a href="' . esc_url($test_url) . '" target="_blank">' . esc_html($test_url) . '</a></p>';
        echo '<p><em>Cette URL devrait afficher directement l\'étape de sélection de région.</em></p>';
        echo '</div>';
    }
    
    echo '</div>';
}

// Ajouter au menu admin pour diagnostic
add_action('admin_menu', function() {
    add_submenu_page(
        'maljani_travel',
        'Diagnostic Vente',
        'Diagnostic',
        'manage_options',
        'maljani_diagnostic',
        'maljani_sales_diagnostic'
    );
});
?>
