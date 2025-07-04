<?php
get_header();
?>
<link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__); ?>templates.css?v=1">

<div class="maljani-profile">
    <h1> test <?php the_title(); ?></h1>
    <div class="maljani-profile-header">
        <div class="maljani-profile-image">
            <?php
            $feature_img_id = get_post_meta(get_the_ID(), '_insurer_feature_img', true);
            if ($feature_img_id) {
                $img_url = wp_get_attachment_url($feature_img_id);
            } else {
                $img_url = plugins_url('images/default-policy-image.jpg', __FILE__);
            }
            ?>
            <img src="<?php echo esc_url($img_url); ?>" alt="Insurer Portrait" class="maljani-insurer-feature-img" style="width:180px;height:120px;object-fit:cover;border-radius:12px;display:block;margin-bottom:15px;">
        </div>
        <div class="maljani-profile-info">

            <?php
            // Logo : champ personnalisé ou image à la une ou image par défaut
            $logo_url = get_post_meta(get_the_ID(), '_insurer_logo', true);
            if (!$logo_url) {
                if (has_post_thumbnail()) {
                    $logo_url = get_the_post_thumbnail_url(get_the_ID(), 'full');
                } else {
                    $logo_url = plugins_url('images/default-policy-image.jpg', __FILE__);
                }
            }

            // Nom : champ personnalisé ou titre du post
            $insurer_name = get_post_meta(get_the_ID(), '_insurer_name', true);
            if (empty($insurer_name)) {
                $insurer_name = get_the_title();
            }
            ?>
            <div class="maljani-insurer" style="display:flex;align-items:center;margin-bottom:12px;">
                <img src="<?php echo esc_url($logo_url); ?>" alt="Insurer Logo" class="maljani-insurer-logo" style="width:48px;height:48px;border-radius:50%;object-fit:cover;margin-right:10px;">
                <span><strong>Official name:</strong> <?php echo esc_html($insurer_name); ?></span>
            </div>
           
            <div>
            <?php
    $website = get_post_meta(get_the_ID(), '_insurer_website', true);
    $linkedin = get_post_meta(get_the_ID(), '_insurer_linkedin', true);
    ?>
    <div class="maljani-links" style="margin-bottom:12px;">
        <?php if ($website): ?>
            <a href="<?php echo esc_url($website); ?>" target="_blank" rel="noopener" style="margin-right:10px;">
                🌐 Website
            </a>
        <?php endif; ?>
        <?php if ($linkedin): ?>
            <a href="<?php echo esc_url($linkedin); ?>" target="_blank" rel="noopener">
                <img src="https://cdn.jsdelivr.net/npm/simple-icons@v11/icons/linkedin.svg" alt="LinkedIn" style="width:18px;height:18px;vertical-align:middle;margin-right:4px;">LinkedIn
            </a>
        <?php endif; ?>
    </div>
            </div>

            <div class = "description">
        <h3>Profile</h3>
        <?php
        $description = get_post_meta(get_the_ID(), '_insurer_profile', true);
        if ($description) {
            echo '<p>' . esc_html($description) . '</p>';
        } else {
            echo '<p>No description available.</p>';
        }
        ?>

    </div>
            
        </div>
    </div>
 
</div>
<?php get_footer(); ?>