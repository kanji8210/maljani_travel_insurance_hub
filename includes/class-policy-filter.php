<?php
// Define the class
class Maljani_Policy_Filter {

    public function __construct() {
        add_action('init', array($this, 'register_shortcode'));
    }

    public function register_shortcode() {
        add_shortcode('policy_filter', array($this, 'output_filter'));
    }

    public function output_filter($atts = [], $content = null) {
        // Attributs par défaut
        $atts = shortcode_atts(array(
            'type' => 'basic'
        ), $atts, 'policy_filter');

        // Exemple de formulaire de filtre
        $output = '<form class="maljani-policy-filter" method="get" action="">';
        $output .= '<label for="region">Région :</label> ';
        $output .= '<select name="region" id="region">';
        $output .= '<option value="">Toutes</option>';
        $regions = get_terms(array('taxonomy' => 'policy_region', 'hide_empty' => false));
        foreach ($regions as $region) {
            $selected = (isset($_GET['region']) && $_GET['region'] == $region->slug) ? 'selected' : '';
            $output .= '<option value="' . esc_attr($region->slug) . '" ' . $selected . '>' . esc_html($region->name) . '</option>';
        }
        $output .= '</select> ';

        $output .= '<label for="insurer">Assureur :</label> ';
        $output .= '<select name="insurer" id="insurer">';
        $output .= '<option value="">Tous</option>';
        $insurers = get_posts(array('post_type' => 'insurer_profile', 'numberposts' => -1));
        foreach ($insurers as $insurer) {
            $selected = (isset($_GET['insurer']) && $_GET['insurer'] == $insurer->ID) ? 'selected' : '';
            $output .= '<option value="' . esc_attr($insurer->ID) . '" ' . $selected . '>' . esc_html($insurer->post_title) . '</option>';
        }
        $output .= '</select> ';

        $output .= '<button type="submit">Filtrer</button>';
        $output .= '</form>';

        // Ici tu peux ajouter l'affichage des résultats filtrés si besoin

        return $output;
    }
}

// Initialize the class
new Maljani_Policy_Filter();
