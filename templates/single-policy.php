<?php
get_header(); ?>

<link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__); ?>templates.css?v=1">
<link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__); ?>../includes/css/sales_form.css?v=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<div class="maljani-container">
    <div class="maljani-row">
        <!-- Colonne gauche : Feature image -->
        <div class="maljani-col" style="align-items:center;">
            <?php
            $feature_img_id = get_post_meta(get_the_ID(), '_policy_feature_img', true);
            if ($feature_img_id) {
                $img_url = wp_get_attachment_url($feature_img_id);
            } else {
                $img_url = plugins_url('images/default-policy-image.jpg', __FILE__);
            }
            ?>
            <img src="<?php echo esc_url($img_url); ?>" alt="Feature Image" class="maljani-policy-feature-img">
        </div>

        <!-- Colonne droite : Infos principales -->
        <div class="maljani-col">
            <?php the_title('<div class="maljani-title">', '</div>'); ?>

            <?php
            // Insurer logo
            $insurer_id = get_post_meta(get_the_ID(), '_policy_insurer', true);
            $insurer_logo = '';
            $insurer_name = '';
            if ($insurer_id) {
                $insurer = get_post($insurer_id);
                $insurer_name = $insurer ? $insurer->post_title : '';
                
                // Logo : champ personnalisé ou image à la une ou image par défaut
                $insurer_logo = get_post_meta($insurer_id, '_insurer_logo', true);
                if (!$insurer_logo) {
                    if (has_post_thumbnail($insurer_id)) {
                        $insurer_logo = get_the_post_thumbnail_url($insurer_id, 'full');
                    }
                }
            }
            if (!$insurer_logo) {
                $insurer_logo = get_site_icon_url(64);
            }
            ?>
            <div class="maljani-insurer">
                <img src="<?php echo esc_url($insurer_logo); ?>" alt="Insurer Logo" class="maljani-insurer-logo">
                <span>
                    <?php
                    if ($insurer_id && $insurer_name) {
                        $insurer_link = get_permalink($insurer_id);
                        echo '<a href="' . esc_url($insurer_link) . '" class="maljani-insurer-link">' . esc_html($insurer_name) . '</a>';
                    } else {
                        echo esc_html($insurer_name ? $insurer_name : get_bloginfo('name'));
                    }
                    ?>
                </span>
            </div>

            <?php
            // Regions
            $regions = get_the_terms(get_the_ID(), 'policy_region');
            if ($regions && !is_wp_error($regions)) {
                $region_names = array();
                foreach ($regions as $region) {
                    $region_names[] = esc_html($region->name);
                }
                echo '<div class="maljani-region"> Region(s): ' . implode(', ', $region_names) . '</div>';
            }
            ?>

            <?php
            // Description
            $description = get_post_meta(get_the_ID(), '_policy_description', true);
            if ($description) {
                echo '<div class="maljani-description">' . esc_html($description) . '</div>';
            }

            // Variables nécessaires pour le CTA
            $sale_page_id = get_option('maljani_policy_sale_page');
            $sale_page_url = $sale_page_id ? get_permalink($sale_page_id) : home_url();
            
            // Debug: vérifier si la page de vente est configurée
            if (!$sale_page_id) {
                echo '<div style="background: #ffebee; padding: 15px; border-left: 4px solid #f44336; margin: 15px 0; border-radius: 4px;">';
                echo '<strong>⚠️ Configuration requise:</strong><br>';
                echo 'La page de vente n\'est pas configurée. Allez dans <strong>Plugins > Maljani Travel Insurance > Settings</strong> et sélectionnez une page pour "Policy Sale Page".';
                echo '</div>';
            }
            ?>

            <!-- Bloc Calculateur + CTA juste sous la description -->
            <div class="maljani-section">
                <div><p>Calculate Premium</p></div>
                <form id="maljani-premium-calc" class="maljani-premium-calc-form" autocomplete="off">
                    <input type="date" name="departure" required placeholder="Departure">
                    <span class="maljani-premium-sep">→</span>
                    <input type="date" name="return" required placeholder="Return">
                    <button type="submit" class="maljani-premium-btn">Check</button>
                </form>
                <div id="maljani-premium-result" class="maljani-premium-result"></div>
                <!-- CTA intégré -->
                <form id="maljani-cta-form" action="<?php echo esc_url($sale_page_url); ?>" method="get" style="margin-top:16px;">
                    <input type="hidden" name="policy_id" value="<?php echo esc_attr(get_the_ID()); ?>">
                    <input type="hidden" name="departure" id="cta-departure">
                    <input type="hidden" name="return" id="cta-return">
                    <button type="submit" class="maljani-cta-btn" disabled>
                        <span class="dashicons dashicons-star-filled"></span>
                        Get a Quote / Buy Now
                    </button>
                </form>
            </div>
            <!-- Fin bloc calculateur + CTA -->

        </div>
    </div>

    <!-- Sections supplémentaires -->
    <div class="maljani-section">
        <?php
        $cover_details = get_post_meta(get_the_ID(), '_policy_cover_details', true);
        if ($cover_details) {
            echo '<h3>Cover Details</h3>' . wpautop($cover_details);
        }
        ?>
    </div>
    <div class="maljani-section">
        <?php
        $benefits = get_post_meta(get_the_ID(), '_policy_benefits', true);
        if ($benefits) {
            echo '<h3>Benefits</h3>' . wpautop($benefits);
        }
        ?>
    </div>
    <div class="maljani-section">
        <?php
        $not_covered = get_post_meta(get_the_ID(), '_policy_not_covered', true);
        if ($not_covered) {
            echo '<h3>What is not covered</h3>' . wpautop($not_covered);
        }
        ?>
    </div>
    <div class="maljani-section">
        <?php
        $premiums = get_post_meta(get_the_ID(), '_policy_day_premiums', true);
        if ($premiums && is_array($premiums)) {
            echo '<table class="maljani-premium-table"><thead><tr><th>From</th><th>To</th><th>Premium</th></tr></thead><tbody>';
            foreach ($premiums as $row) {
                echo '<tr><td>' . esc_html($row['from']) . '</td><td>' . esc_html($row['to']) . '</td><td>' . esc_html($row['premium']) . '</td></tr>';
            }
            echo '</tbody></table>';
            echo '<script>window.maljaniPremiums = ' . json_encode($premiums) . ';</script>';
        }
        ?>
    </div>
</div>

<?php
// Enqueue le JS pour ce template
add_action('wp_footer', function() {
    wp_enqueue_script(
        'maljani-template-js',
        plugin_dir_url(__FILE__) . 'template.js',
        array('jquery'),
        null,
        true
    );
});
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const depInput = document.querySelector('#maljani-premium-calc input[name="departure"]');
    const retInput = document.querySelector('#maljani-premium-calc input[name="return"]');
    const ctaDep = document.getElementById('cta-departure');
    const ctaRet = document.getElementById('cta-return');
    const ctaBtn = document.querySelector('#maljani-cta-form button[type="submit"]');
    const ctaForm = document.getElementById('maljani-cta-form');

    function syncCTA() {
        if (depInput && retInput && ctaDep && ctaRet && ctaBtn) {
            ctaDep.value = depInput.value;
            ctaRet.value = retInput.value;
            // Active le bouton CTA seulement si les deux dates sont remplies
            ctaBtn.disabled = !(depInput.value && retInput.value);
        }
    }

    // Event listeners
    if (depInput) depInput.addEventListener('change', syncCTA);
    if (retInput) retInput.addEventListener('change', syncCTA);
    
            // Debug form submission
            if (ctaForm) {
                ctaForm.addEventListener('submit', function(e) {
                    e.preventDefault(); // Empêche la soumission par défaut du formulaire
                    
                    const policyIdInput = ctaForm.querySelector('input[name="policy_id"]');
                    const policyId = policyIdInput ? policyIdInput.value : '';
                    const departure = ctaDep ? ctaDep.value : '';
                    const returnDate = ctaRet ? ctaRet.value : '';
                    
                    // Vérifications avant soumission
                    if (!policyId) {
                        alert('Erreur: ID de la politique manquant. Rechargez la page et réessayez.');
                        return false;
                    }
                    
                    if (!departure || !returnDate) {
                        alert('Erreur: Veuillez sélectionner les dates de départ et de retour.');
                        return false;
                    }
                    
                    // Vérifier que l'URL d'action n'est pas vide ou '#'
                    if (!ctaForm.action || ctaForm.action.endsWith('#') || ctaForm.action.endsWith('/wordpress/')) {
                        alert('Erreur: Page de vente non configurée. Veuillez contacter l\'administrateur.');
                        return false;
                    }
                    
                    // Construire l'URL avec les paramètres de manière sécurisée
                    const url = new URL(ctaForm.action);
                    url.searchParams.set('policy_id', policyId);
                    url.searchParams.set('departure', departure);
                    url.searchParams.set('return', returnDate);
                    
                    // Rediriger vers la nouvelle URL
                    window.location.href = url.toString();
                    
                    return false;
                });
            }    // Initialisation au chargement
    syncCTA();
});
</script>

<?php
get_footer();