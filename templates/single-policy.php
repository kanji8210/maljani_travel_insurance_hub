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
        </div>
    </div>

    <!-- Le reste du contenu dans des div séparés -->
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
            echo '<h3>Day Premiums</h3>';
            echo '<table class="maljani-table"><tr><th>From</th><th>To</th><th>Premium</th></tr>';
            foreach ($premiums as $row) {
                echo '<tr>';
                echo '<td>' . esc_html($row['from']) . '</td>';
                echo '<td>' . esc_html($row['to']) . '</td>';
                echo '<td>' . esc_html($row['premium']) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
        ?>
    </div>

    <div class="description">
        <h3>Profile</h3>
        <?php
        $description = get_post_meta(get_the_ID(), '_insurer_profile', true);
        if ($description) {
            echo wpautop($description);
        } else {
            echo '<p>No description available.</p>';
        }
        ?>
    </div>
</div>

<?php get_footer(); ?>