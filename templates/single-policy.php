<?php
get_header(); ?>

<link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__); ?>templates.css?v=1">
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
                $logo_id = get_post_thumbnail_id($insurer_id);
                if ($logo_id) {
                    $insurer_logo = wp_get_attachment_url($logo_id);
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
            $description = get_post_meta(get_the_ID(), '_policy_description', true);
            if ($description) {
                echo '<div class="maljani-description">' . esc_html($description) . '</div>';
            }
            ?>

            <!-- Calculateur de prime + CTA minimaliste -->
            <div class="maljani-section">
                <div><p>Calculate Premium</p></div>
                <form id="maljani-premium-calc" class="maljani-premium-calc-form" autocomplete="off">
                    <input type="date" name="departure" required placeholder="Departure">
                    <span class="maljani-premium-sep">→</span>
                    <input type="date" name="return" required placeholder="Return">
                    <button type="submit" class="maljani-premium-btn">Check</button>
                </form>
                <div id="maljani-premium-result" class="maljani-premium-result"></div>
            </div>
            <a href="<?php echo esc_url( home_url('/sales-form/?policy_id=' . get_the_ID() . '&premium=' . $premium . '&days=' . $days) ); ?>" class="maljani-cta-btn">
                <span class="dashicons dashicons-yes"></span>
                Get this cover
            </a>
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
    <!-- CTA principal tout en bas de la page -->
<div class="section" style="text-align:center; margin: 48px 0 0 0;">
    <a href="<?php echo esc_url( home_url('/sales-form/?policy_id=' . get_the_ID()) ); ?>"
       class="maljani-cta-btn maljani-cta-bottom-btn">
        <span class="dashicons dashicons-star-filled"></span>
        Ready to protect your trip? <strong>Get your insurance now!</strong>
    </a>
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



<?php
get_footer();