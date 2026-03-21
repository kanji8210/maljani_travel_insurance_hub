<?php
/**
 * Register custom WPGraphQL fields for the Policy CPT.
 *
 * Hooked on graphql_register_types so the headless React/Next.js
 * frontend can query policy meta fields directly.
 *
 * Fields registered:
 *   policyDescription, policyCoverDetails, policyBenefits,
 *   policyNotCovered, policyCurrency, policyPaymentDetails,
 *   policyDayPremiums, policyInsurerName, policyInsurerLogo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'graphql_register_types', 'maljani_register_policy_graphql_fields' );

function maljani_register_policy_graphql_fields() {
    if ( ! function_exists( 'register_graphql_field' ) ) {
        return; // WPGraphQL not active — skip silently.
    }

    // ── Simple string fields ────────────────────────────────────────────────
    $simple_fields = [
        'policyDescription'    => '_policy_description',
        'policyCoverDetails'   => '_policy_cover_details',
        'policyBenefits'       => '_policy_benefits',
        'policyNotCovered'     => '_policy_not_covered',
        'policyCurrency'       => '_policy_currency',
        'policyPaymentDetails' => '_policy_payment_details',
    ];

    foreach ( $simple_fields as $gql_key => $meta_key ) {
        register_graphql_field( 'Policy', $gql_key, [
            'type'        => 'String',
            'description' => 'Policy meta: ' . $meta_key,
            'resolve'     => function ( $post ) use ( $meta_key ) {
                return get_post_meta( $post->databaseId, $meta_key, true ) ?: '';
            },
        ] );
    }

    // ── Premium brackets — list of { from, to, premium } objects ───────────
    register_graphql_object_type( 'PolicyPremiumBracket', [
        'description' => 'A day-range premium bracket for a travel insurance policy.',
        'fields'      => [
            'from'    => [ 'type' => 'Int',   'description' => 'Start day (inclusive).' ],
            'to'      => [ 'type' => 'Int',   'description' => 'End day (inclusive).' ],
            'premium' => [ 'type' => 'Float', 'description' => 'Gross premium amount.' ],
        ],
    ] );

    register_graphql_field( 'Policy', 'policyDayPremiums', [
        'type'        => [ 'list_of' => 'PolicyPremiumBracket' ],
        'description' => 'Premium schedule by trip duration in days.',
        'resolve'     => function ( $post ) {
            $brackets = get_post_meta( $post->databaseId, '_policy_day_premiums', true );
            if ( ! is_array( $brackets ) ) {
                return [];
            }
            return array_map( function ( $b ) {
                return [
                    'from'    => intval( $b['from']    ?? 0 ),
                    'to'      => intval( $b['to']      ?? 0 ),
                    'premium' => floatval( $b['premium'] ?? 0 ),
                ];
            }, array_values( $brackets ) );
        },
    ] );

    // ── Countries covered — stored as serialised array ──────────────────────
    register_graphql_field( 'Policy', 'policyCountries', [
        'type'        => [ 'list_of' => 'String' ],
        'description' => 'List of countries covered by this policy.',
        'resolve'     => function ( $post ) {
            $countries = get_post_meta( $post->databaseId, '_policy_countries', true );
            return is_array( $countries ) ? $countries : [];
        },
    ] );

    // ── Insurer name — resolved from linked insurer_profile post ───────────
    register_graphql_field( 'Policy', 'policyInsurerName', [
        'type'        => 'String',
        'description' => 'Display name of the underwriting insurer.',
        'resolve'     => function ( $post ) {
            $insurer_id = get_post_meta( $post->databaseId, '_policy_insurer', true );
            if ( ! $insurer_id ) {
                return '';
            }
            $name = get_post_meta( $insurer_id, '_insurer_name', true );
            return $name ?: get_the_title( $insurer_id );
        },
    ] );

    // ── Insurer logo URL — resolved from linked insurer_profile post ────────
    register_graphql_field( 'Policy', 'policyInsurerLogo', [
        'type'        => 'String',
        'description' => 'Logo URL of the underwriting insurer.',
        'resolve'     => function ( $post ) {
            $insurer_id = get_post_meta( $post->databaseId, '_policy_insurer', true );
            if ( ! $insurer_id ) {
                return '';
            }
            $logo_id = get_post_meta( $insurer_id, '_insurer_logo_id', true );
            if ( $logo_id ) {
                return wp_get_attachment_url( $logo_id );
            }
            return get_post_meta( $insurer_id, '_insurer_logo', true ) ?: '';
        },
    ] );
}
