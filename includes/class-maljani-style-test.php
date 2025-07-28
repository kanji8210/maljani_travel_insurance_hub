<?php
/**
 * Maljani Style Isolation Test
 * 
 * Test file to verify that style isolation is working correctly
 * Access via: /wp-admin/admin.php?page=maljani-style-test
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Maljani_Style_Test {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_test_page']);
    }
    
    /**
     * Add test page to admin menu
     */
    public function add_test_page() {
        add_submenu_page(
            'edit.php?post_type=policy',
            'Style Isolation Test',
            'Style Test',
            'manage_options',
            'maljani-style-test',
            [$this, 'render_test_page']
        );
    }
    
    /**
     * Render the test page
     */
    public function render_test_page() {
        $isolation = Maljani_Style_Isolation::instance();
        ?>
        <div class="wrap">
            <h1>Maljani Style Isolation Test</h1>
            
            <div style="background: #f0f0f1; padding: 20px; margin: 20px 0; border-left: 4px solid #0073aa;">
                <h2>Purpose</h2>
                <p>This page tests that Maljani plugin styles are properly isolated from WordPress admin and theme styles.</p>
                <p><strong>Expected Result:</strong> All plugin elements should maintain consistent styling regardless of active theme.</p>
            </div>
            
            <!-- Test 1: Basic Container -->
            <h2>Test 1: Basic Container Isolation</h2>
            <?php echo $isolation->wrap_output('<p>This text should use plugin font family and styling, not admin styles.</p>', ['class' => 'test-container']); ?>
            
            <!-- Test 2: Form Elements -->
            <h2>Test 2: Form Elements</h2>
            <?php 
            $form_content = '
                <h3 class="maljani-form-title">Test Form</h3>
                <input type="text" placeholder="Test Input" style="margin-bottom: 10px;">
                <select style="margin-bottom: 10px;">
                    <option>Test Option</option>
                </select>
                <textarea placeholder="Test Textarea" rows="3" style="margin-bottom: 10px;"></textarea>
            ';
            echo $isolation->get_isolated_form($form_content, 'test');
            ?>
            
            <!-- Test 3: Buttons -->
            <h2>Test 3: Button Styling</h2>
            <div class="maljani-plugin-container">
                <?php echo $isolation->get_isolated_button('Primary Button', '#', 'primary'); ?>
                <?php echo $isolation->get_isolated_button('Secondary Button', '#', 'secondary'); ?>
            </div>
            
            <!-- Test 4: Notifications -->
            <h2>Test 4: Notification Styles</h2>
            <?php echo $isolation->get_isolated_notice('This is a success message', 'success'); ?>
            <?php echo $isolation->get_isolated_notice('This is an error message', 'error'); ?>
            <?php echo $isolation->get_isolated_notice('This is a warning message', 'warning'); ?>
            <?php echo $isolation->get_isolated_notice('This is an info message', 'info'); ?>
            
            <!-- Test 5: Icons -->
            <h2>Test 5: Icon Display</h2>
            <div class="maljani-plugin-container">
                <p>Small icon: <?php echo $isolation->get_isolated_icon('star-filled', 'small', '#ffd700'); ?></p>
                <p>Medium icon: <?php echo $isolation->get_isolated_icon('shield-alt', 'medium', '#28a745'); ?></p>
                <p>Large icon: <?php echo $isolation->get_isolated_icon('airplane', 'large', '#007cba'); ?></p>
                <p>XL icon: <?php echo $isolation->get_isolated_icon('admin-home', 'xl', '#dc3545'); ?></p>
            </div>
            
            <!-- Test 6: Table -->
            <h2>Test 6: Table Styling</h2>
            <?php 
            $headers = ['Column 1', 'Column 2', 'Column 3'];
            $rows = [
                ['Data 1A', 'Data 1B', 'Data 1C'],
                ['Data 2A', 'Data 2B', 'Data 2C'],
                ['Data 3A', 'Data 3B', 'Data 3C']
            ];
            echo $isolation->get_isolated_table($headers, $rows, ['class' => 'test-table']);
            ?>
            
            <!-- Test 7: Theme Conflict Detection -->
            <h2>Test 7: Theme Conflict Detection</h2>
            <?php 
            $conflicts = $isolation->check_theme_conflicts();
            if (empty($conflicts)) {
                echo $isolation->get_isolated_notice('No known conflicts detected with current theme.', 'success');
            } else {
                echo $isolation->get_isolated_notice('Potential conflicts detected: ' . implode(', ', $conflicts), 'warning');
            }
            ?>
            
            <!-- Test 8: CSS Specificity -->
            <h2>Test 8: CSS Specificity Enhancement</h2>
            <div class="maljani-plugin-container">
                <code>
                    Base selector: .my-element<br>
                    Level 1: <?php echo $isolation->enhance_specificity('.my-element', 1); ?><br>
                    Level 2: <?php echo $isolation->enhance_specificity('.my-element', 2); ?><br>
                    Level 3: <?php echo $isolation->enhance_specificity('.my-element', 3); ?>
                </code>
            </div>
            
            <!-- Test 9: Comparison Without Isolation -->
            <h2>Test 9: Comparison (Without Isolation)</h2>
            <div style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0;">
                <strong>Without Isolation:</strong>
                <p>This paragraph uses default WordPress admin styles and may look different depending on the active theme.</p>
                <button type="button" class="button">WordPress Button</button>
                <input type="text" placeholder="WordPress Input">
            </div>
            
            <!-- Test Results Summary -->
            <div style="background: #e7f3ff; padding: 20px; margin: 20px 0; border-left: 4px solid #0073aa;">
                <h2>Test Results Summary</h2>
                <p><strong>Visual Verification:</strong></p>
                <ul>
                    <li>✅ Plugin elements should have consistent blue/modern styling</li>
                    <li>✅ Fonts should be modern system fonts (not WordPress admin fonts)</li>
                    <li>✅ Buttons should be blue with rounded corners</li>
                    <li>✅ Notifications should have colored left borders</li>
                    <li>✅ Form elements should have subtle borders and padding</li>
                    <li>✅ No style inheritance from WordPress admin interface</li>
                </ul>
                
                <p><strong>Browser DevTools Check:</strong></p>
                <ol>
                    <li>Open browser developer tools (F12)</li>
                    <li>Inspect plugin elements</li>
                    <li>Verify <code>.maljani-plugin-container</code> class is present</li>
                    <li>Check that styles have <code>!important</code> declarations</li>
                    <li>Confirm no conflicting theme styles are overriding plugin styles</li>
                </ol>
            </div>
            
            <!-- JavaScript Test -->
            <script>
            jQuery(document).ready(function($) {
                // Test container detection
                const containers = document.querySelectorAll('.maljani-plugin-container');
                console.log('Maljani isolation containers found:', containers.length);
                
                // Test style application
                containers.forEach((container, index) => {
                    const computedStyles = window.getComputedStyle(container);
                    console.log(`Container ${index} font-family:`, computedStyles.fontFamily);
                    console.log(`Container ${index} box-sizing:`, computedStyles.boxSizing);
                });
                
                // Add success indicator
                if (containers.length > 0) {
                    $('<div class="notice notice-success" style="margin: 20px 0; padding: 10px;"><p><strong>JavaScript Test:</strong> ✅ Isolation containers detected successfully. Check browser console for details.</p></div>').insertAfter('h1');
                } else {
                    $('<div class="notice notice-error" style="margin: 20px 0; padding: 10px;"><p><strong>JavaScript Test:</strong> ❌ No isolation containers found. Style isolation may not be working.</p></div>').insertAfter('h1');
                }
            });
            </script>
        </div>
        <?php
    }
}

// Initialize test if in admin
if (is_admin()) {
    new Maljani_Style_Test();
}
