 <?php
/**
 * Register SUPPLEMENTAL WPGraphQL fields for the Policy CPT.
 *
 * NOTE: Simple string fields and policyDayPremiums / policyInsurerName /
 * policyInsurerLogo are already registered by Policy_CPT::register_graphql_fields().
 * This file registers ONLY the fields that are unique to this module to avoid
 * WPGraphQL FieldAlreadyExists errors that would abort the entire function.
 *
 * Fields added here:
 *   policyCountries  — list of covered countries, falls back to region defaults
 *   maljaniFeatureTags — alias for _policy_feature_tags used by the React catalog
 *   User.phone        — exposes the 'phone' user-meta via GraphQL
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'graphql_register_types', 'maljani_register_policy_graphql_fields' );

/**
 * Returns a default country list for a given region slug/name.
 * Used as a fallback when no countries are manually saved on the policy.
 */
function maljani_countries_for_region( string $region ): array {
    $region = strtolower( trim( $region ) );

    $map = [
        'schengen' => [
            'Austria', 'Belgium', 'Croatia', 'Czech Republic', 'Denmark',
            'Estonia', 'Finland', 'France', 'Germany', 'Greece', 'Hungary',
            'Iceland', 'Italy', 'Latvia', 'Liechtenstein', 'Lithuania',
            'Luxembourg', 'Malta', 'Netherlands', 'Norway', 'Poland',
            'Portugal', 'Slovakia', 'Slovenia', 'Spain', 'Sweden', 'Switzerland',
        ],
        'europe' => [
            'Austria', 'Belgium', 'Croatia', 'Czech Republic', 'Denmark',
            'Estonia', 'Finland', 'France', 'Germany', 'Greece', 'Hungary',
            'Iceland', 'Ireland', 'Italy', 'Latvia', 'Liechtenstein',
            'Lithuania', 'Luxembourg', 'Malta', 'Netherlands', 'Norway',
            'Poland', 'Portugal', 'Romania', 'Slovakia', 'Slovenia',
            'Spain', 'Sweden', 'Switzerland', 'United Kingdom',
        ],
        'africa' => [
            'Kenya', 'Uganda', 'Tanzania', 'Rwanda', 'Ethiopia', 'Ghana',
            'Nigeria', 'South Africa', 'Egypt', 'Morocco', 'Senegal',
            'Côte d\'Ivoire', 'Cameroon', 'Zimbabwe', 'Zambia', 'Mozambique',
            'Angola', 'Namibia', 'Botswana', 'Malawi', 'Madagascar',
        ],
        'east africa' => [
            'Kenya', 'Uganda', 'Tanzania', 'Rwanda', 'Burundi',
            'South Sudan', 'Ethiopia', 'Somalia', 'Eritrea',
        ],
        'worldwide' => [ 'Worldwide' ],
        'global'    => [ 'Worldwide' ],
        'asia'      => [
            'China', 'Japan', 'India', 'South Korea', 'Thailand',
            'Vietnam', 'Indonesia', 'Malaysia', 'Philippines', 'Singapore',
            'UAE', 'Saudi Arabia', 'Qatar', 'Turkey', 'Israel',
        ],
        'americas'  => [
            'United States', 'Canada', 'Mexico', 'Brazil', 'Argentina',
            'Colombia', 'Chile', 'Peru',
        ],
    ];

    // Partial match — e.g. "east-africa" matches "east africa"
    $region_normalised = str_replace( '-', ' ', $region );
    foreach ( $map as $key => $countries ) {
        if ( strpos( $region_normalised, $key ) !== false || strpos( $key, $region_normalised ) !== false ) {
            return $countries;
        }
    }
    return [];
}

function maljani_register_policy_graphql_fields() {
    if ( ! function_exists( 'register_graphql_field' ) ) {
        return;
    }

    // ── Countries covered — stored as serialised array; falls back to region ─
    register_graphql_field( 'Policy', 'policyCountries', [
        'type'        => [ 'list_of' => 'String' ],
        'description' => 'Countries covered by this policy. Falls back to region default when empty.',
        'resolve'     => function ( $post ) {
            $countries = get_post_meta( $post->databaseId, '_policy_countries', true );
            if ( is_array( $countries ) && ! empty( $countries ) ) {
                return $countries;
            }

            // Fallback: derive country list from the policy_region taxonomy terms
            $terms = wp_get_post_terms( $post->databaseId, 'policy_region', [ 'fields' => 'names' ] );
            if ( is_wp_error( $terms ) || empty( $terms ) ) {
                return [];
            }

            $result = [];
            foreach ( $terms as $region_name ) {
                $result = array_merge( $result, maljani_countries_for_region( $region_name ) );
            }
            return array_values( array_unique( $result ) );
        },
    ] );

    // ── maljaniFeatureTags — alias used by the React catalog thumbnail ──────
    register_graphql_field( 'Policy', 'maljaniFeatureTags', [
        'type'        => 'String',
        'description' => 'Feature tags for the policy thumbnail (e.g. "Popular, New").',
        'resolve'     => function ( $post ) {
            return get_post_meta( $post->databaseId, '_policy_feature_tags', true ) ?: '';
        },
    ] );

    // ── User.phone — exposes the phone user-meta via GraphQL ────────────────
    register_graphql_field( 'User', 'phone', [
        'type'        => 'String',
        'description' => 'User phone number stored in user meta.',
        'resolve'     => function ( $user ) {
            return get_user_meta( $user->databaseId, 'phone', true ) ?: '';
        },
    ] );
}
