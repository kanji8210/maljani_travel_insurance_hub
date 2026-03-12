<?php
/**
 * Template Name: Maljani Single Policy
 * Description: A premium, modern glassmorphism template for displaying a single Travel Insurance Policy.
 */

get_header();

// 1. Core Post Data
$policy_id = get_the_ID();
$title = get_the_title();
$content = get_the_content();
$excerpt = get_the_excerpt();

// 2. Policy Meta
$premium = get_post_meta($policy_id, 'maljani_premium', true);
$age_limit = get_post_meta($policy_id, 'maljani_age_limit', true);
$duration = get_post_meta($policy_id, 'maljani_duration', true);
$medical = get_post_meta($policy_id, 'maljani_medical_expenses', true);
$baggage = get_post_meta($policy_id, 'maljani_baggage', true);

// Get sales page URL for CTA
$sales_page_id = get_option('maljani_policy_sale_page');
$cta_url = $sales_page_id ? add_query_arg('policy_id', $policy_id, get_permalink($sales_page_id)) : '#';

// 3. Optional Coverages / Highlights
$highlights = [
    'Emergency Medical' => $medical ? '$' . number_format((float)$medical) : 'Covered',
    'Baggage Loss' => $baggage ? '$' . number_format((float)$baggage) : 'Covered',
    'Max Age' => $age_limit ? $age_limit . ' Years' : 'Up to 90',
    'Max Duration' => $duration ? $duration . ' Days' : 'Up to 90 Days',
    '24/7 Support' => 'Included',
    'COVID-19' => 'Covered',
];
?>

<style>
/* Maljani Single Policy - Inter & Glassmorphism Theme */
:root {
    --mj-pri: #4f46e5;
    --mj-pri-hov: #4338ca;
    --mj-bg: #f8fafc;
    --mj-text: #1e293b;
    --mj-text-light: #64748b;
    --mj-glass-bg: rgba(255, 255, 255, 0.85);
    --mj-glass-border: rgba(255, 255, 255, 0.6);
}

.mj-sp-container {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    color: var(--mj-text);
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    padding: 60px 20px;
    min-height: calc(100vh - 200px);
}

.mj-sp-wrapper {
    max-width: 1000px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
}

/* Glassmorphism Cards */
.mj-sp-card {
    background: var(--mj-glass-bg);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid var(--mj-glass-border);
    border-radius: 20px;
    padding: 40px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.05);
}

.mj-sp-main-col {
    display: flex;
    flex-direction: column;
    gap: 30px;
}

.mj-sp-header h1 {
    font-size: 36px;
    font-weight: 800;
    margin: 0 0 16px 0;
    color: #0f172a;
    line-height: 1.2;
    letter-spacing: -0.5px;
}

.mj-badge {
    display: inline-block;
    background: #e0e7ff;
    color: var(--mj-pri);
    font-size: 13px;
    font-weight: 700;
    padding: 6px 14px;
    border-radius: 20px;
    margin-bottom: 20px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.mj-sp-excerpt {
    font-size: 18px;
    color: var(--mj-text-light);
    line-height: 1.6;
    margin-bottom: 0;
}

/* Document & Terms Section */
.mj-sp-content {
    font-size: 16px;
    line-height: 1.7;
    color: #334155;
    background: #ffffff;
    border-radius: 16px;
    padding: 30px;
    border: 1px solid #e2e8f0;
}

.mj-sp-content h2, .mj-sp-content h3 {
    color: #0f172a;
    margin-top: 30px;
    font-weight: 700;
}

.mj-sp-content ul {
    padding-left: 20px;
    margin-bottom: 20px;
}

.mj-sp-content li {
    margin-bottom: 10px;
}

/* Sidebar Pricing Card */
.mj-sp-pricing {
    position: sticky;
    top: 40px;
    text-align: center;
    padding: 40px 30px;
    background: linear-gradient(180deg, rgba(255,255,255,0.95) 0%, rgba(255,255,255,0.85) 100%);
}

.mj-sp-price-label {
    text-transform: uppercase;
    letter-spacing: 1px;
    font-size: 13px;
    font-weight: 700;
    color: var(--mj-text-light);
    margin-bottom: 10px;
}

.mj-sp-price-amount {
    font-size: 48px;
    font-weight: 800;
    color: var(--mj-pri);
    line-height: 1;
    margin-bottom: 8px;
}

.mj-sp-price-sub {
    font-size: 14px;
    color: #94a3b8;
    margin-bottom: 30px;
}

.mj-sp-btn {
    display: block;
    width: 100%;
    padding: 18px;
    background: var(--mj-pri);
    color: #ffffff;
    font-size: 16px;
    font-weight: 700;
    text-align: center;
    text-decoration: none;
    border-radius: 12px;
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.4);
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
}

.mj-sp-btn:hover {
    background: var(--mj-pri-hov);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(79, 70, 229, 0.5);
    color: #ffffff;
}

/* Highlights Grid */
.mj-h-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
    margin-top: 30px;
    text-align: left;
    border-top: 1px solid #e2e8f0;
    padding-top: 30px;
}

.mj-h-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px dashed #e2e8f0;
}

.mj-h-item:last-child {
    border-bottom: none;
}

.mj-h-label {
    font-size: 14px;
    color: var(--mj-text-light);
    font-weight: 500;
}

.mj-h-val {
    font-size: 14px;
    color: #0f172a;
    font-weight: 700;
}

@media (max-width: 900px) {
    .mj-sp-wrapper {
        grid-template-columns: 1fr;
    }
    .mj-sp-pricing {
        position: relative;
        top: 0;
        order: -1; /* Move pricing above content on mobile */
    }
}
</style>

<div class="mj-sp-container">
    <div class="mj-sp-wrapper">
        
        <!-- Main Info -->
        <div class="mj-sp-main-col">
            <div class="mj-sp-card mj-sp-header">
                <span class="mj-badge">Travel Insurance</span>
                <h1><?php echo esc_html($title); ?></h1>
                <?php if ($excerpt): ?>
                    <p class="mj-sp-excerpt"><?php echo wp_kses_post($excerpt); ?></p>
                <?php endif; ?>
            </div>

            <?php if ($content): ?>
                <div class="mj-sp-card mj-sp-content">
                    <?php echo apply_filters('the_content', $content); ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar / Action -->
        <div class="mj-sp-sidebar">
            <div class="mj-sp-card mj-sp-pricing">
                <div class="mj-sp-price-label">Base Premium</div>
                <div class="mj-sp-price-amount">$<?php echo esc_html($premium ?: '0.00'); ?></div>
                <div class="mj-sp-price-sub">per traveler</div>
                
                <a href="<?php echo esc_url($cta_url); ?>" class="mj-sp-btn">Get Covered Now</a>

                <div class="mj-h-grid">
                    <?php foreach ($highlights as $label => $val): ?>
                    <div class="mj-h-item">
                        <span class="mj-h-label"><?php echo esc_html($label); ?></span>
                        <span class="mj-h-val"><?php echo esc_html($val); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<?php 
get_footer(); 
?>
