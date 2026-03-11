<?php
/**
 * Insurer Profile Template
 * Premium Glassmorphism Design
 */
get_header();
?>
<link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__); ?>templates.css?v=1.2">
<style>
    :root {
        --mj-primary: #4f46e5;
        --mj-primary-hover: #4338ca;
        --mj-glass: rgba(255, 255, 255, 0.7);
        --mj-glass-border: rgba(255, 255, 255, 0.3);
        --mj-text: #1e293b;
        --mj-text-muted: #64748b;
    }

    .maljani-profile-wrapper {
        max-width: 1000px;
        margin: 60px auto;
        padding: 0 20px;
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        color: var(--mj-text);
    }

    .premium-profile-card {
        background: var(--mj-glass);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid var(--mj-glass-border);
        border-radius: 24px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.06);
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    @media (min-width: 768px) {
        .premium-profile-card {
            flex-direction: row;
        }
    }

    .profile-sidebar {
        background: rgba(255, 255, 255, 0.5);
        padding: 40px;
        display: flex;
        flex-direction: column;
        align-items: center;
        border-bottom: 1px solid var(--mj-glass-border);
        min-width: 300px;
    }

    @media (min-width: 768px) {
        .profile-sidebar {
            border-bottom: none;
            border-right: 1px solid var(--mj-glass-border);
        }
    }

    .insurer-feature-frame {
        width: 200px;
        height: 200px;
        border-radius: 20px;
        overflow: hidden;
        margin-bottom: 24px;
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        border: 4px solid white;
    }

    .insurer-feature-frame img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .insurer-logo-pills {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 24px;
        background: white;
        padding: 8px 16px;
        border-radius: 100px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.02);
    }

    .insurer-logo-pills img {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
    }

    .insurer-logo-pills span {
        font-weight: 600;
        font-size: 14px;
        color: var(--mj-text);
    }

    .profile-main {
        padding: 40px;
        flex: 1;
        background: white;
    }

    .profile-main h1 {
        margin: 0 0 12px 0;
        font-size: 32px;
        font-weight: 800;
        color: #0f172a;
        letter-spacing: -0.02em;
    }

    .profile-badge {
        display: inline-block;
        padding: 6px 14px;
        background: #e0e7ff;
        color: #4338ca;
        border-radius: 100px;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        margin-bottom: 24px;
        letter-spacing: 0.05em;
    }

    .profile-description {
        font-size: 16px;
        line-height: 1.7;
        color: var(--mj-text-muted);
        margin-bottom: 32px;
    }

    .profile-links {
        display: flex;
        gap: 16px;
        flex-wrap: wrap;
    }

    .mj-link-btn {
        display: inline-flex;
        align-items: center;
        padding: 12px 24px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 14px;
        text-decoration: none;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .mj-link-web {
        background: var(--mj-primary);
        color: white !important;
    }

    .mj-link-web:hover {
        background: var(--mj-primary-hover);
        transform: translateY(-2px);
        box-shadow: 0 10px 15px rgba(79, 70, 229, 0.2);
    }

    .mj-link-linkedin {
        background: #f1f5f9;
        color: #475569 !important;
    }

    .mj-link-linkedin:hover {
        background: #e2e8f0;
        transform: translateY(-2px);
    }

    .mj-link-linkedin img {
        width: 18px;
        height: 18px;
        margin-right: 8px;
    }

</style>

<div class="maljani-profile-wrapper">
    <div class="premium-profile-card">
        <aside class="profile-sidebar">
            <div class="insurer-feature-frame">
                <?php
                $feature_img_id = get_post_meta(get_the_ID(), '_insurer_feature_img', true);
                $img_url = $feature_img_id ? wp_get_attachment_url($feature_img_id) : plugins_url('images/default-policy-image.jpg', __FILE__);
                ?>
                <img src="<?php echo esc_url($img_url); ?>" alt="<?php the_title_attribute(); ?>">
            </div>

            <?php
            $logo_id = get_post_meta(get_the_ID(), '_insurer_logo_id', true);
            $logo_url = $logo_id ? wp_get_attachment_url($logo_id) : get_post_meta(get_the_ID(), '_insurer_logo', true);
            if (!$logo_url) {
                $logo_url = has_post_thumbnail() ? get_the_post_thumbnail_url(get_the_ID(), 'thumbnail') : plugins_url('images/default-policy-image.jpg', __FILE__);
            }
            ?>
            <div class="insurer-logo-pills">
                <img src="<?php echo esc_url($logo_url); ?>" alt="Logo">
                <span>Verified Partner</span>
            </div>
        </aside>

        <main class="profile-main">
            <span class="profile-badge">Official Partner Profile</span>
            <h1><?php the_title(); ?></h1>
            
            <div class="profile-description">
                <?php
                $description = get_post_meta(get_the_ID(), '_insurer_profile', true);
                if ($description) {
                    echo wpautop(esc_html($description));
                } else {
                    echo '<p>No official profile description provided by the insurer.</p>';
                }
                ?>
            </div>

            <?php
            $website = get_post_meta(get_the_ID(), '_insurer_website', true);
            $linkedin = get_post_meta(get_the_ID(), '_insurer_linkedin', true);
            ?>
            <div class="profile-links">
                <?php if ($website): ?>
                    <a href="<?php echo esc_url($website); ?>" target="_blank" rel="noopener" class="mj-link-btn mj-link-web">
                        Visit Official Website →
                    </a>
                <?php endif; ?>
                <?php if ($linkedin): ?>
                    <a href="<?php echo esc_url($linkedin); ?>" target="_blank" rel="noopener" class="mj-link-btn mj-link-linkedin">
                        <img src="https://cdn.jsdelivr.net/npm/simple-icons@v11/icons/linkedin.svg" alt="LinkedIn">
                        LinkedIn Profile
                    </a>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<?php get_footer(); ?>