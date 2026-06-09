<?php
/**
 * HubSkills LMS – Payment Gateway Framework
 *
 * Supports: Stripe, PayPal, Mercado Pago, PagSeguro, PIX (manual).
 * Orders are stored as the CPT `hs_order` with post-meta fields.
 *
 * @package HubSkills_LMS
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── CPT: hs_order ─────────────────────────────────────────────────────────────

add_action( 'init', 'hs_register_order_cpt' );
function hs_register_order_cpt(): void {
    register_post_type( 'hs_order', [
        'labels' => [
            'name'          => __( 'Pedidos', 'hubskills-lms' ),
            'singular_name' => __( 'Pedido', 'hubskills-lms' ),
        ],
        'public'            => false,
        'show_ui'           => true,
        'show_in_rest'      => false,
        'supports'          => [ 'title', 'custom-fields' ],
        'capability_type'   => 'post',
        'map_meta_cap'      => true,
        'show_in_menu'      => 'hubskills-lms',
    ] );
}

// ── Gateway config helpers ─────────────────────────────────────────────────────

/**
 * Return the slug of the currently active payment gateway.
 *
 * @return string  e.g. 'stripe', 'paypal', 'mercadopago', 'pagseguro', 'pix'
 */
function hs_get_active_gateway(): string {
    return (string) ( get_option( 'hs_active_payment_gateway', 'pix' ) );
}

/**
 * Convert price strings such as "R$ 97,00" to a decimal amount.
 */
function hs_parse_course_price( $price ): float {
    if ( is_numeric( $price ) ) {
        return max( 0, (float) $price );
    }

    $normalized = preg_replace( '/[^0-9,.]/', '', (string) $price );
    if ( $normalized === '' ) {
        return 0.0;
    }

    $last_comma = strrpos( $normalized, ',' );
    $last_dot   = strrpos( $normalized, '.' );

    if ( $last_comma !== false && ( $last_dot === false || $last_comma > $last_dot ) ) {
        $normalized = str_replace( '.', '', $normalized );
        $normalized = str_replace( ',', '.', $normalized );
    } else {
        $normalized = str_replace( ',', '', $normalized );
    }

    return max( 0, (float) $normalized );
}

function hs_gateway_secret_fields( string $gateway ): array {
    $fields = [
        'stripe'      => [ 'secret_key', 'webhook_secret' ],
        'paypal'      => [ 'client_secret' ],
        'mercadopago' => [ 'access_token' ],
        'pagseguro'   => [ 'token' ],
        'pix'         => [ 'pix_key' ],
    ];

    return $fields[ sanitize_key( $gateway ) ] ?? [];
}

function hs_prepare_gateway_config_for_storage( string $gateway, array $config ): array {
    foreach ( hs_gateway_secret_fields( $gateway ) as $field ) {
        if ( ! empty( $config[ $field ] ) && function_exists( 'hs_encrypt' ) ) {
            $config[ $field ] = hs_encrypt( (string) $config[ $field ] );
        }
    }

    return $config;
}

/**
 * Return the configuration array for a specific gateway.
 *
 * @param string $gateway
 * @return array<string, mixed>
 */
function hs_get_gateway_config( string $gateway ): array {
    $config = get_option( 'hs_payment_gateway_' . sanitize_key( $gateway ), [] );
    if ( ! is_array( $config ) ) {
        return [];
    }

    foreach ( hs_gateway_secret_fields( $gateway ) as $field ) {
        if ( isset( $config[ $field ] ) && function_exists( 'hs_decrypt' ) ) {
            $config[ $field ] = hs_decrypt( (string) $config[ $field ] );
        }
    }

    return $config;
}

// ── Course / access helpers ────────────────────────────────────────────────────

/**
 * Check whether a course has a paid price set.
 *
 * @param int $course_id
 * @return bool
 */
function hs_is_course_paid( int $course_id ): bool {
    $price = get_post_meta( $course_id, '_hs_price', true );

    if ( empty( $price ) ) {
        return false;
    }

    if ( strtolower( trim( $price ) ) === 'grátis' || strtolower( trim( $price ) ) === 'gratis' ) {
        return false;
    }

    return hs_parse_course_price( $price ) > 0;
}

/**
 * Check whether a user has access to a course (enrolled or free).
 *
 * @param int $user_id
 * @param int $course_id
 * @return bool
 */
function hs_user_has_access( int $user_id, int $course_id ): bool {
    if ( ! hs_is_course_paid( $course_id ) ) {
        return true;
    }

    if ( function_exists( 'hs_is_enrolled' ) && hs_is_enrolled( $user_id, $course_id ) ) {
        return true;
    }

    // Check completed orders as a fallback.
    global $wpdb;
    $exists = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT pm.post_id FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->postmeta} pm2 ON pm.post_id = pm2.post_id
             INNER JOIN {$wpdb->postmeta} pm3 ON pm.post_id = pm3.post_id
             WHERE pm.meta_key  = '_hs_order_user_id'   AND pm.meta_value  = %d
               AND pm2.meta_key = '_hs_order_course_id' AND pm2.meta_value = %d
               AND pm3.meta_key = '_hs_order_status'    AND pm3.meta_value = 'paid'
             LIMIT 1",
            $user_id,
            $course_id
        )
    );

    return ! empty( $exists );
}

// ── Order CRUD ────────────────────────────────────────────────────────────────

/**
 * Create a new LMS order post.
 *
 * @param int $user_id
 * @param int $course_id
 * @return int|WP_Error  Order post ID on success.
 */
function hs_create_order( int $user_id, int $course_id ) {
    if ( ! get_userdata( $user_id ) ) {
        return new WP_Error( 'invalid_user', __( 'Usuário inválido.', 'hubskills-lms' ) );
    }

    if ( get_post_type( $course_id ) !== 'hubskills_course' ) {
        return new WP_Error( 'invalid_course', __( 'Curso inválido.', 'hubskills-lms' ) );
    }

    $price   = hs_parse_course_price( get_post_meta( $course_id, '_hs_price', true ) );
    $gateway = hs_get_active_gateway();

    $order_id = wp_insert_post( [
        'post_type'   => 'hs_order',
        'post_status' => 'publish',
        'post_title'  => sprintf(
            /* translators: 1: user ID, 2: course ID */
            __( 'Pedido – Usuário %1$d – Curso %2$d', 'hubskills-lms' ),
            $user_id,
            $course_id
        ),
    ], true );

    if ( is_wp_error( $order_id ) ) {
        return $order_id;
    }

    $meta = [
        '_hs_order_user_id'        => $user_id,
        '_hs_order_course_id'      => $course_id,
        '_hs_order_gateway'        => $gateway,
        '_hs_order_amount'         => $price,
        '_hs_order_status'         => 'pending',
        '_hs_order_transaction_id' => '',
        '_hs_order_created_at'     => current_time( 'mysql' ),
    ];

    foreach ( $meta as $key => $value ) {
        update_post_meta( $order_id, $key, $value );
    }

    do_action( 'hs_order_created', $order_id, $user_id, $course_id );

    return $order_id;
}

/**
 * Retrieve full order data as an associative array.
 *
 * @param int $order_id
 * @return array<string, mixed>|null  Null if post does not exist.
 */
function hs_get_order( int $order_id ): ?array {
    $post = get_post( $order_id );
    if ( ! $post || $post->post_type !== 'hs_order' ) {
        return null;
    }

    return [
        'id'             => $order_id,
        'user_id'        => (int)    get_post_meta( $order_id, '_hs_order_user_id',        true ),
        'course_id'      => (int)    get_post_meta( $order_id, '_hs_order_course_id',      true ),
        'gateway'        => (string) get_post_meta( $order_id, '_hs_order_gateway',        true ),
        'amount'         => (float)  get_post_meta( $order_id, '_hs_order_amount',         true ),
        'status'         => (string) get_post_meta( $order_id, '_hs_order_status',         true ),
        'transaction_id' => (string) get_post_meta( $order_id, '_hs_order_transaction_id', true ),
        'created_at'     => (string) get_post_meta( $order_id, '_hs_order_created_at',     true ),
    ];
}

/**
 * Mark an order as paid and enroll the user in the course.
 *
 * @param int $order_id
 * @return bool
 */
function hs_complete_order( int $order_id ): bool {
    $order = hs_get_order( $order_id );
    if ( ! $order ) {
        return false;
    }

    update_post_meta( $order_id, '_hs_order_status', 'paid' );
    update_post_meta( $order_id, '_hs_order_paid_at', current_time( 'mysql' ) );

    // Enroll the user in the course.
    if ( function_exists( 'hs_enroll_user' ) ) {
        hs_enroll_user( $order['user_id'], $order['course_id'], 'payment' );
    }

    do_action( 'hs_order_completed', $order_id, $order['user_id'], $order['course_id'] );

    return true;
}

/**
 * Mark an order as refunded and unenroll the user.
 *
 * @param int $order_id
 * @return bool
 */
function hs_refund_order( int $order_id ): bool {
    $order = hs_get_order( $order_id );
    if ( ! $order ) {
        return false;
    }

    update_post_meta( $order_id, '_hs_order_status', 'refunded' );
    update_post_meta( $order_id, '_hs_order_refunded_at', current_time( 'mysql' ) );

    if ( function_exists( 'hs_unenroll_user' ) ) {
        hs_unenroll_user( $order['user_id'], $order['course_id'] );
    }

    do_action( 'hs_order_refunded', $order_id, $order['user_id'], $order['course_id'] );

    return true;
}

// ── Redirect URL helpers ──────────────────────────────────────────────────────

/**
 * Return the URL to redirect to after a successful payment.
 *
 * @param int $order_id
 * @return string
 */
function hs_payment_success_url( int $order_id ): string {
    $order = hs_get_order( $order_id );
    $base  = get_option( 'hs_payment_success_url' );

    if ( ! $base ) {
        $base = home_url( '/dashboard/' );
    }

    return add_query_arg(
        [ 'hs_order' => $order_id, 'status' => 'success' ],
        $base
    );
}

/**
 * Return the URL to redirect to after a cancelled payment.
 *
 * @param int $order_id
 * @return string
 */
function hs_payment_cancel_url( int $order_id ): string {
    $order = hs_get_order( $order_id );
    $base  = get_option( 'hs_payment_cancel_url' );

    if ( ! $base && $order ) {
        $base = get_permalink( $order['course_id'] ) ?: home_url( '/cursos/' );
    }

    if ( ! $base ) {
        $base = home_url( '/cursos/' );
    }

    return add_query_arg(
        [ 'hs_order' => $order_id, 'status' => 'cancelled' ],
        $base
    );
}

// ── Stripe ────────────────────────────────────────────────────────────────────

/**
 * Create a Stripe Checkout Session for an order.
 *
 * @param int $order_id
 * @return array<string, mixed>|WP_Error  Array with 'session_id' and 'checkout_url', or WP_Error.
 */
function hs_stripe_create_checkout( int $order_id ) {
    $order  = hs_get_order( $order_id );
    if ( ! $order ) {
        return new WP_Error( 'invalid_order', __( 'Pedido inválido.', 'hubskills-lms' ) );
    }

    $config = hs_get_gateway_config( 'stripe' );
    $secret = $config['secret_key'] ?? '';

    if ( empty( $secret ) ) {
        return new WP_Error( 'stripe_config', __( 'Stripe não configurado.', 'hubskills-lms' ) );
    }

    $course_title = get_the_title( $order['course_id'] );
    $amount_cents = (int) round( $order['amount'] * 100 );

    $payload = [
        'payment_method_types' => [ 'card' ],
        'line_items'           => [
            [
                'price_data' => [
                    'currency'     => strtolower( get_option( 'hs_currency', 'brl' ) ),
                    'product_data' => [ 'name' => $course_title ],
                    'unit_amount'  => $amount_cents,
                ],
                'quantity' => 1,
            ],
        ],
        'mode'                 => 'payment',
        'success_url'          => hs_payment_success_url( $order_id ),
        'cancel_url'           => hs_payment_cancel_url( $order_id ),
        'metadata'             => [ 'hs_order_id' => $order_id ],
    ];

    $response = wp_remote_post( 'https://api.stripe.com/v1/checkout/sessions', [
        'headers' => [
            'Authorization' => 'Bearer ' . $secret,
            'Content-Type'  => 'application/x-www-form-urlencoded',
        ],
        'body'    => hs_encode_stripe_params( $payload ),
        'timeout' => 30,
    ] );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( ! empty( $body['error'] ) ) {
        return new WP_Error( 'stripe_api', $body['error']['message'] ?? __( 'Erro Stripe.', 'hubskills-lms' ) );
    }

    update_post_meta( $order_id, '_hs_order_transaction_id', $body['id'] ?? '' );

    return [
        'session_id'   => $body['id'] ?? '',
        'checkout_url' => $body['url'] ?? '',
    ];
}

/**
 * Recursively URL-encode a nested array for the Stripe API.
 *
 * @param array<mixed, mixed> $params
 * @param string              $prefix
 * @return string
 */
function hs_encode_stripe_params( array $params, string $prefix = '' ): string {
    $parts = [];
    foreach ( $params as $key => $value ) {
        $full_key = $prefix ? $prefix . '[' . $key . ']' : $key;
        if ( is_array( $value ) ) {
            $parts[] = hs_encode_stripe_params( $value, $full_key );
        } else {
            $parts[] = urlencode( (string) $full_key ) . '=' . urlencode( (string) $value );
        }
    }
    return implode( '&', $parts );
}

/**
 * Handle incoming Stripe webhook events.
 * Hooked to the REST API endpoint POST /hubskills/v1/webhooks/stripe.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function hs_stripe_webhook_handler( WP_REST_Request $request ): WP_REST_Response {
    $config  = hs_get_gateway_config( 'stripe' );
    $secret  = $config['webhook_secret'] ?? '';
    $payload = $request->get_body();
    $sig     = $request->get_header( 'stripe-signature' );

    // Verify webhook signature.
    if ( $secret && ! hs_stripe_verify_signature( $payload, $sig, $secret ) ) {
        return new WP_REST_Response( [ 'error' => 'Invalid signature' ], 400 );
    }

    $event = json_decode( $payload, true );
    if ( ! is_array( $event ) ) {
        return new WP_REST_Response( [ 'error' => 'Invalid payload' ], 400 );
    }

    $type = $event['type'] ?? '';

    if ( $type === 'checkout.session.completed' ) {
        $session_id = $event['data']['object']['id'] ?? '';
        $order_id   = (int) ( $event['data']['object']['metadata']['hs_order_id'] ?? 0 );

        if ( $order_id ) {
            update_post_meta( $order_id, '_hs_order_transaction_id', $session_id );
            hs_complete_order( $order_id );
        }
    } elseif ( $type === 'charge.refunded' ) {
        $session_id = $event['data']['object']['payment_intent'] ?? '';
        // Look up the order by transaction_id.
        $order_id = hs_find_order_by_transaction( $session_id );
        if ( $order_id ) {
            hs_refund_order( $order_id );
        }
    }

    return new WP_REST_Response( [ 'received' => true ], 200 );
}

/**
 * Verify a Stripe webhook signature using the timing-safe comparison.
 *
 * @param string $payload
 * @param string $sig_header
 * @param string $secret
 * @return bool
 */
function hs_stripe_verify_signature( string $payload, string $sig_header, string $secret ): bool {
    // Parse timestamp and signature from header.
    $parts     = explode( ',', $sig_header );
    $timestamp = '';
    $v1        = '';

    foreach ( $parts as $part ) {
        $kv = explode( '=', $part, 2 );
        if ( $kv[0] === 't' ) {
            $timestamp = $kv[1];
        }
        if ( $kv[0] === 'v1' ) {
            $v1 = $kv[1];
        }
    }

    if ( ! $timestamp || ! $v1 ) {
        return false;
    }

    $expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );

    return hash_equals( $expected, $v1 );
}

// ── PayPal ────────────────────────────────────────────────────────────────────

/**
 * Return a PayPal access token.
 *
 * @param array<string, mixed> $config
 * @return string|WP_Error
 */
function hs_paypal_get_token( array $config ) {
    $client_id     = $config['client_id'] ?? '';
    $client_secret = $config['client_secret'] ?? '';
    $sandbox       = ! empty( $config['sandbox'] );

    $base = $sandbox
        ? 'https://api-m.sandbox.paypal.com'
        : 'https://api-m.paypal.com';

    $response = wp_remote_post( $base . '/v1/oauth2/token', [
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
            'Content-Type'  => 'application/x-www-form-urlencoded',
        ],
        'body'    => 'grant_type=client_credentials',
        'timeout' => 30,
    ] );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( empty( $body['access_token'] ) ) {
        return new WP_Error( 'paypal_auth', __( 'Falha na autenticação PayPal.', 'hubskills-lms' ) );
    }

    return $body['access_token'];
}

/**
 * Create a PayPal order (payment intent).
 *
 * @param int $order_id
 * @return array<string, mixed>|WP_Error
 */
function hs_paypal_create_order( int $order_id ) {
    $order = hs_get_order( $order_id );
    if ( ! $order ) {
        return new WP_Error( 'invalid_order', __( 'Pedido inválido.', 'hubskills-lms' ) );
    }

    $config   = hs_get_gateway_config( 'paypal' );
    $token    = hs_paypal_get_token( $config );
    $sandbox  = ! empty( $config['sandbox'] );

    if ( is_wp_error( $token ) ) {
        return $token;
    }

    $base = $sandbox
        ? 'https://api-m.sandbox.paypal.com'
        : 'https://api-m.paypal.com';

    $currency = strtoupper( get_option( 'hs_currency', 'BRL' ) );

    $payload = [
        'intent'         => 'CAPTURE',
        'purchase_units' => [
            [
                'reference_id' => 'hs_order_' . $order_id,
                'description'  => get_the_title( $order['course_id'] ),
                'amount'       => [
                    'currency_code' => $currency,
                    'value'         => number_format( $order['amount'], 2, '.', '' ),
                ],
            ],
        ],
        'application_context' => [
            'return_url' => hs_payment_success_url( $order_id ),
            'cancel_url' => hs_payment_cancel_url( $order_id ),
        ],
    ];

    $response = wp_remote_post( $base . '/v2/checkout/orders', [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        ],
        'body'    => wp_json_encode( $payload ),
        'timeout' => 30,
    ] );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( empty( $body['id'] ) ) {
        return new WP_Error( 'paypal_create', __( 'Erro ao criar pedido PayPal.', 'hubskills-lms' ) );
    }

    update_post_meta( $order_id, '_hs_order_transaction_id', $body['id'] );

    // Find the approve link.
    $approve_url = '';
    foreach ( $body['links'] ?? [] as $link ) {
        if ( $link['rel'] === 'approve' ) {
            $approve_url = $link['href'];
            break;
        }
    }

    return [
        'paypal_order_id' => $body['id'],
        'approve_url'     => $approve_url,
    ];
}

/**
 * Capture an approved PayPal order.
 *
 * @param string $paypal_order_id
 * @return array<string, mixed>|WP_Error
 */
function hs_paypal_capture_order( string $paypal_order_id ) {
    $config  = hs_get_gateway_config( 'paypal' );
    $token   = hs_paypal_get_token( $config );
    $sandbox = ! empty( $config['sandbox'] );

    if ( is_wp_error( $token ) ) {
        return $token;
    }

    $base = $sandbox
        ? 'https://api-m.sandbox.paypal.com'
        : 'https://api-m.paypal.com';

    $response = wp_remote_post(
        $base . '/v2/checkout/orders/' . rawurlencode( $paypal_order_id ) . '/capture',
        [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => '{}',
            'timeout' => 30,
        ]
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $body   = json_decode( wp_remote_retrieve_body( $response ), true );
    $status = $body['status'] ?? '';

    if ( $status !== 'COMPLETED' ) {
        return new WP_Error( 'paypal_capture', __( 'Pagamento PayPal não confirmado.', 'hubskills-lms' ) );
    }

    // Find the matching HS order and complete it.
    $order_id = hs_find_order_by_transaction( $paypal_order_id );
    if ( $order_id ) {
        hs_complete_order( $order_id );
    }

    return $body;
}

// ── Mercado Pago ──────────────────────────────────────────────────────────────

/**
 * Create a Mercado Pago preference (checkout link).
 *
 * @param int $order_id
 * @return array<string, mixed>|WP_Error
 */
function hs_mp_create_preference( int $order_id ) {
    $order = hs_get_order( $order_id );
    if ( ! $order ) {
        return new WP_Error( 'invalid_order', __( 'Pedido inválido.', 'hubskills-lms' ) );
    }

    $config      = hs_get_gateway_config( 'mercadopago' );
    $access_token = $config['access_token'] ?? '';

    if ( empty( $access_token ) ) {
        return new WP_Error( 'mp_config', __( 'Mercado Pago não configurado.', 'hubskills-lms' ) );
    }

    $payload = [
        'items' => [
            [
                'title'     => get_the_title( $order['course_id'] ),
                'quantity'  => 1,
                'unit_price'=> (float) $order['amount'],
                'currency_id' => strtoupper( get_option( 'hs_currency', 'BRL' ) ),
            ],
        ],
        'external_reference' => 'hs_order_' . $order_id,
        'back_urls' => [
            'success' => hs_payment_success_url( $order_id ),
            'failure' => hs_payment_cancel_url( $order_id ),
            'pending' => hs_payment_cancel_url( $order_id ),
        ],
        'auto_return'        => 'approved',
        'notification_url'   => rest_url( 'hubskills/v1/webhooks/mercadopago' ),
    ];

    $response = wp_remote_post( 'https://api.mercadopago.com/checkout/preferences', [
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type'  => 'application/json',
        ],
        'body'    => wp_json_encode( $payload ),
        'timeout' => 30,
    ] );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( empty( $body['id'] ) ) {
        return new WP_Error( 'mp_create', __( 'Erro ao criar preferência Mercado Pago.', 'hubskills-lms' ) );
    }

    update_post_meta( $order_id, '_hs_order_transaction_id', $body['id'] );

    $sandbox = ! empty( $config['sandbox'] );
    $checkout_url = $sandbox
        ? ( $body['sandbox_init_point'] ?? '' )
        : ( $body['init_point'] ?? '' );

    return [
        'preference_id' => $body['id'],
        'checkout_url'  => $checkout_url,
    ];
}

/**
 * Handle incoming Mercado Pago webhook notifications.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function hs_mp_webhook_handler( WP_REST_Request $request ): WP_REST_Response {
    $topic = $request->get_param( 'topic' );
    $id    = $request->get_param( 'id' );

    if ( $topic !== 'payment' || ! $id ) {
        return new WP_REST_Response( [ 'skipped' => true ], 200 );
    }

    $config       = hs_get_gateway_config( 'mercadopago' );
    $access_token = $config['access_token'] ?? '';

    $response = wp_remote_get(
        'https://api.mercadopago.com/v1/payments/' . rawurlencode( (string) $id ),
        [
            'headers' => [ 'Authorization' => 'Bearer ' . $access_token ],
            'timeout' => 30,
        ]
    );

    if ( is_wp_error( $response ) ) {
        return new WP_REST_Response( [ 'error' => 'API error' ], 500 );
    }

    $payment    = json_decode( wp_remote_retrieve_body( $response ), true );
    $ext_ref    = $payment['external_reference'] ?? '';
    $status     = $payment['status'] ?? '';
    $order_id   = (int) str_replace( 'hs_order_', '', $ext_ref );

    if ( $order_id && $status === 'approved' ) {
        update_post_meta( $order_id, '_hs_order_transaction_id', (string) $id );
        hs_complete_order( $order_id );
    } elseif ( $order_id && $status === 'refunded' ) {
        hs_refund_order( $order_id );
    }

    return new WP_REST_Response( [ 'received' => true ], 200 );
}

// ── PagSeguro ─────────────────────────────────────────────────────────────────

/**
 * Create a PagSeguro session token.
 *
 * @return string|WP_Error
 */
function hs_pagseguro_create_session() {
    $config  = hs_get_gateway_config( 'pagseguro' );
    $email   = $config['email'] ?? '';
    $token   = $config['token'] ?? '';
    $sandbox = ! empty( $config['sandbox'] );

    if ( empty( $email ) || empty( $token ) ) {
        return new WP_Error( 'pagseguro_config', __( 'PagSeguro não configurado.', 'hubskills-lms' ) );
    }

    $base = $sandbox
        ? 'https://ws.sandbox.pagseguro.uol.com.br'
        : 'https://ws.pagseguro.uol.com.br';

    $response = wp_remote_post(
        $base . '/v2/sessions?email=' . rawurlencode( $email ) . '&token=' . rawurlencode( $token ),
        [
            'headers' => [ 'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8' ],
            'timeout' => 30,
        ]
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $xml = simplexml_load_string( wp_remote_retrieve_body( $response ) );
    if ( ! $xml || empty( $xml->id ) ) {
        return new WP_Error( 'pagseguro_session', __( 'Erro ao criar sessão PagSeguro.', 'hubskills-lms' ) );
    }

    return (string) $xml->id;
}

/**
 * Initiate a PagSeguro checkout and return the redirect URL.
 *
 * @param int $order_id
 * @return array<string, mixed>|WP_Error
 */
function hs_pagseguro_checkout( int $order_id ) {
    $order = hs_get_order( $order_id );
    if ( ! $order ) {
        return new WP_Error( 'invalid_order', __( 'Pedido inválido.', 'hubskills-lms' ) );
    }

    $config  = hs_get_gateway_config( 'pagseguro' );
    $email   = $config['email'] ?? '';
    $token   = $config['token'] ?? '';
    $sandbox = ! empty( $config['sandbox'] );

    $base = $sandbox
        ? 'https://ws.sandbox.pagseguro.uol.com.br'
        : 'https://ws.pagseguro.uol.com.br';

    $redirect_base = $sandbox
        ? 'https://sandbox.pagseguro.uol.com.br'
        : 'https://pagseguro.uol.com.br';

    $buyer = get_userdata( $order['user_id'] );
    $name  = $buyer ? trim( $buyer->display_name ) : 'Aluno';

    $body = http_build_query( [
        'email'                    => $email,
        'token'                    => $token,
        'currency'                 => 'BRL',
        'itemId1'                  => $order['course_id'],
        'itemDescription1'         => get_the_title( $order['course_id'] ),
        'itemAmount1'              => number_format( $order['amount'], 2, '.', '' ),
        'itemQuantity1'            => 1,
        'reference'                => 'hs_order_' . $order_id,
        'senderEmail'              => $buyer ? $buyer->user_email : '',
        'senderName'               => $name,
        'redirectURL'              => hs_payment_success_url( $order_id ),
        'notificationURL'          => rest_url( 'hubskills/v1/webhooks/pagseguro' ),
    ] );

    $response = wp_remote_post( $base . '/v2/checkout', [
        'headers' => [ 'Content-Type' => 'application/x-www-form-urlencoded; charset=ISO-8859-1' ],
        'body'    => $body,
        'timeout' => 30,
    ] );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $xml = simplexml_load_string( wp_remote_retrieve_body( $response ) );
    if ( ! $xml || empty( $xml->code ) ) {
        return new WP_Error( 'pagseguro_checkout', __( 'Erro ao iniciar checkout PagSeguro.', 'hubskills-lms' ) );
    }

    $checkout_code = (string) $xml->code;
    update_post_meta( $order_id, '_hs_order_transaction_id', $checkout_code );

    return [
        'checkout_code' => $checkout_code,
        'checkout_url'  => $redirect_base . '/v2/checkout/payment.html?code=' . $checkout_code,
    ];
}

// ── PIX (manual) ──────────────────────────────────────────────────────────────

/**
 * Return the configured PIX key for the store.
 *
 * @return string
 */
function hs_pix_get_key(): string {
    $config = hs_get_gateway_config( 'pix' );
    return (string) ( $config['pix_key'] ?? '' );
}

/**
 * Generate a PIX EMV/copy-paste payload (BR Code – Payload Format Indicator).
 *
 * This implements the simplified static PIX payload per BACEN spec.
 *
 * @param int $order_id
 * @return array<string, mixed>|WP_Error
 */
function hs_pix_generate_payload( int $order_id ) {
    $order = hs_get_order( $order_id );
    if ( ! $order ) {
        return new WP_Error( 'invalid_order', __( 'Pedido inválido.', 'hubskills-lms' ) );
    }

    $pix_key   = hs_pix_get_key();
    if ( empty( $pix_key ) ) {
        return new WP_Error( 'pix_config', __( 'Chave PIX não configurada.', 'hubskills-lms' ) );
    }

    $config        = hs_get_gateway_config( 'pix' );
    $merchant_name = substr( sanitize_text_field( $config['merchant_name'] ?? get_bloginfo( 'name' ) ), 0, 25 );
    $merchant_city = substr( sanitize_text_field( $config['city'] ?? get_option( 'hs_city', 'Brasil' ) ), 0, 15 );
    $amount        = number_format( $order['amount'], 2, '.', '' );
    $txid          = 'HSLMS' . $order_id;

    // EMV QRCPS-MPM payload builder.
    $build = function ( string $id, string $value ): string {
        return $id . str_pad( strlen( $value ), 2, '0', STR_PAD_LEFT ) . $value;
    };

    $merchant_account = $build( '00', 'br.gov.bcb.pix' )
                      . $build( '01', $pix_key );

    $payload_without_crc =
          $build( '00', '01' )
        . $build( '26', $merchant_account )
        . $build( '52', '0000' )
        . $build( '53', '986' )
        . $build( '54', $amount )
        . $build( '58', 'BR' )
        . $build( '59', $merchant_name )
        . $build( '60', $merchant_city )
        . $build( '62', $build( '05', $txid ) )
        . '6304'; // CRC placeholder.

    // CRC-16/CCITT-FALSE.
    $crc = hs_pix_crc16( $payload_without_crc );

    $payload = $payload_without_crc . strtoupper( str_pad( dechex( $crc ), 4, '0', STR_PAD_LEFT ) );

    return [
        'payload'      => $payload,
        'pix_key'      => $pix_key,
        'amount'       => $amount,
        'merchant'     => $merchant_name,
        'city'         => $merchant_city,
        'txid'         => $txid,
        'order_id'     => $order_id,
    ];
}

/**
 * Calculate CRC-16/CCITT-FALSE checksum for PIX payload.
 *
 * @param string $payload
 * @return int
 */
function hs_pix_crc16( string $payload ): int {
    $crc = 0xFFFF;
    for ( $i = 0; $i < strlen( $payload ); $i++ ) {
        $crc ^= ord( $payload[ $i ] ) << 8;
        for ( $j = 0; $j < 8; $j++ ) {
            if ( $crc & 0x8000 ) {
                $crc = ( $crc << 1 ) ^ 0x1021;
            } else {
                $crc <<= 1;
            }
            $crc &= 0xFFFF;
        }
    }
    return $crc;
}

// ── Utility ───────────────────────────────────────────────────────────────────

/**
 * Find an HS order post ID by payment gateway transaction ID.
 *
 * @param string $transaction_id
 * @return int  Order post ID, or 0 if not found.
 */
function hs_find_order_by_transaction( string $transaction_id ): int {
    global $wpdb;

    if ( empty( $transaction_id ) ) {
        return 0;
    }

    $result = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = '_hs_order_transaction_id'
               AND meta_value = %s
             LIMIT 1",
            $transaction_id
        )
    );

    return (int) $result;
}

// ── REST API – Webhook routes ──────────────────────────────────────────────────

add_action( 'rest_api_init', 'hs_register_payment_rest_routes' );
function hs_register_payment_rest_routes(): void {
    // Stripe webhook.
    register_rest_route( 'hubskills/v1', '/webhooks/stripe', [
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'hs_stripe_webhook_handler',
        'permission_callback' => '__return_true', // Validated inside via signature.
    ] );

    // Mercado Pago webhook.
    register_rest_route( 'hubskills/v1', '/webhooks/mercadopago', [
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'hs_mp_webhook_handler',
        'permission_callback' => '__return_true',
    ] );

    // PayPal IPN / webhook (capture confirmation comes from JS on the front end,
    // but we register a route for server-side IPN notifications as well).
    register_rest_route( 'hubskills/v1', '/webhooks/paypal', [
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'hs_paypal_webhook_handler',
        'permission_callback' => '__return_true',
    ] );

    // PagSeguro notification.
    register_rest_route( 'hubskills/v1', '/webhooks/pagseguro', [
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'hs_pagseguro_webhook_handler',
        'permission_callback' => '__return_true',
    ] );
}

/**
 * PayPal server-side webhook handler.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function hs_paypal_webhook_handler( WP_REST_Request $request ): WP_REST_Response {
    $body  = json_decode( $request->get_body(), true );
    $event = $body['event_type'] ?? '';

    if ( $event === 'CHECKOUT.ORDER.APPROVED' ) {
        $paypal_order_id = $body['resource']['id'] ?? '';
        if ( $paypal_order_id ) {
            hs_paypal_capture_order( $paypal_order_id );
        }
    }

    return new WP_REST_Response( [ 'received' => true ], 200 );
}

/**
 * PagSeguro notification handler.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function hs_pagseguro_webhook_handler( WP_REST_Request $request ): WP_REST_Response {
    $notification_code = sanitize_text_field( $request->get_param( 'notificationCode' ) ?? '' );
    $notification_type = sanitize_text_field( $request->get_param( 'notificationType' ) ?? '' );

    if ( $notification_type !== 'transaction' || empty( $notification_code ) ) {
        return new WP_REST_Response( [ 'skipped' => true ], 200 );
    }

    $config  = hs_get_gateway_config( 'pagseguro' );
    $email   = $config['email'] ?? '';
    $token   = $config['token'] ?? '';
    $sandbox = ! empty( $config['sandbox'] );

    $base = $sandbox
        ? 'https://ws.sandbox.pagseguro.uol.com.br'
        : 'https://ws.pagseguro.uol.com.br';

    $url = add_query_arg(
        [ 'email' => $email, 'token' => $token ],
        $base . '/v3/transactions/notifications/' . rawurlencode( $notification_code )
    );

    $response = wp_remote_get( $url, [ 'timeout' => 30 ] );
    if ( is_wp_error( $response ) ) {
        return new WP_REST_Response( [ 'error' => 'API error' ], 500 );
    }

    $xml      = simplexml_load_string( wp_remote_retrieve_body( $response ) );
    $status   = $xml ? (int) $xml->status : 0;
    $ref      = $xml ? (string) $xml->reference : '';
    $order_id = (int) str_replace( 'hs_order_', '', $ref );

    // PagSeguro status 3 = paid, 7 = refunded.
    if ( $order_id ) {
        if ( $status === 3 ) {
            hs_complete_order( $order_id );
        } elseif ( $status === 7 ) {
            hs_refund_order( $order_id );
        }
    }

    return new WP_REST_Response( [ 'received' => true ], 200 );
}

// ── AJAX handlers ─────────────────────────────────────────────────────────────

add_action( 'wp_ajax_hs_initiate_payment',     'hs_ajax_initiate_payment' );
add_action( 'wp_ajax_hs_check_payment_status', 'hs_ajax_check_payment_status' );
add_action( 'wp_ajax_hs_request_refund',       'hs_ajax_request_refund' );
add_action( 'template_redirect', 'hs_handle_payment_return' );

function hs_confirm_mp_payment_for_order( int $order_id, string $payment_id ): bool {
    $order = hs_get_order( $order_id );
    if ( ! $order || ! $payment_id ) {
        return false;
    }

    $config = hs_get_gateway_config( 'mercadopago' );
    $access_token = $config['access_token'] ?? '';
    if ( ! $access_token ) {
        return false;
    }

    $response = wp_remote_get(
        'https://api.mercadopago.com/v1/payments/' . rawurlencode( $payment_id ),
        [
            'headers' => [ 'Authorization' => 'Bearer ' . $access_token ],
            'timeout' => 30,
        ]
    );

    if ( is_wp_error( $response ) ) {
        return false;
    }

    $payment = json_decode( wp_remote_retrieve_body( $response ), true );
    $ext_ref = $payment['external_reference'] ?? '';
    $status  = $payment['status'] ?? '';
    if ( $ext_ref === 'hs_order_' . $order_id && $status === 'approved' ) {
        update_post_meta( $order_id, '_hs_order_transaction_id', $payment_id );
        hs_complete_order( $order_id );
        return true;
    }

    return false;
}

function hs_handle_payment_return(): void {
    if ( is_admin() || ! is_user_logged_in() ) {
        return;
    }

    $order_id = absint( $_GET['hs_order'] ?? 0 );
    $status   = sanitize_key( $_GET['status'] ?? '' );
    if ( ! $order_id || $status !== 'success' ) {
        return;
    }

    $order = hs_get_order( $order_id );
    if ( ! $order ) {
        return;
    }

    $current_user = get_current_user_id();
    if ( (int) $order['user_id'] !== $current_user && ( ! function_exists( 'hs_is_hs_admin' ) || ! hs_is_hs_admin( $current_user ) ) ) {
        return;
    }

    if ( $order['status'] !== 'paid' ) {
        if ( $order['gateway'] === 'paypal' ) {
            $paypal_order_id = sanitize_text_field( wp_unslash( $_GET['token'] ?? $order['transaction_id'] ) );
            if ( $paypal_order_id ) {
                hs_paypal_capture_order( $paypal_order_id );
            }
        } elseif ( $order['gateway'] === 'mercadopago' ) {
            $payment_id = sanitize_text_field( wp_unslash( $_GET['payment_id'] ?? $_GET['collection_id'] ?? '' ) );
            if ( $payment_id ) {
                hs_confirm_mp_payment_for_order( $order_id, $payment_id );
            }
        }
    }

    if ( function_exists( 'hs_sync_user_paid_access_enrollments' ) ) {
        hs_sync_user_paid_access_enrollments( (int) $order['user_id'] );
    }
}

/**
 * AJAX: initiate a payment session for a course.
 */
function hs_ajax_initiate_payment(): void {
    if ( function_exists( 'hs_verify_request' ) ) {
        hs_verify_request( 'hubskills_nonce', true );
    } else {
        check_ajax_referer( 'hubskills_nonce', 'nonce' );
    }

    $user_id   = get_current_user_id();
    $course_id = (int) ( $_POST['course_id'] ?? 0 );

    if ( ! $course_id ) {
        wp_send_json_error( [ 'message' => __( 'Curso inválido.', 'hubskills-lms' ) ] );
    }

    if ( hs_user_has_access( $user_id, $course_id ) ) {
        if ( function_exists( 'hs_is_enrolled' ) && ! hs_is_enrolled( $user_id, $course_id ) && function_exists( 'hs_enroll_user' ) ) {
            hs_enroll_user( $user_id, $course_id, 'payment' );
        }
        wp_send_json_success( [
            'message'          => __( 'Você já tem acesso a este curso.', 'hubskills-lms' ),
            'already_enrolled' => true,
            'course_url'       => get_permalink( $course_id ),
        ] );
    }

    $order_id = hs_create_order( $user_id, $course_id );
    if ( is_wp_error( $order_id ) ) {
        wp_send_json_error( [ 'message' => $order_id->get_error_message() ] );
    }

    $gateway = hs_get_active_gateway();
    $result  = null;

    switch ( $gateway ) {
        case 'stripe':
            $result = hs_stripe_create_checkout( $order_id );
            break;

        case 'paypal':
            $result = hs_paypal_create_order( $order_id );
            break;

        case 'mercadopago':
            $result = hs_mp_create_preference( $order_id );
            break;

        case 'pagseguro':
            $result = hs_pagseguro_checkout( $order_id );
            break;

        case 'pix':
            $result = hs_pix_generate_payload( $order_id );
            break;

        default:
            wp_send_json_error( [ 'message' => __( 'Gateway de pagamento inválido.', 'hubskills-lms' ) ] );
    }

    if ( is_wp_error( $result ) ) {
        update_post_meta( $order_id, '_hs_order_status', 'failed' );
        wp_send_json_error( [ 'message' => $result->get_error_message() ] );
    }

    $response = array_merge(
        [
            'order_id'       => $order_id,
            'gateway'        => $gateway,
            'gateway_label'  => strtoupper( $gateway ),
            'course_id'      => $course_id,
            'course_title'   => get_the_title( $course_id ),
            'course_url'     => get_permalink( $course_id ),
            'amount'         => (float) get_post_meta( $order_id, '_hs_order_amount', true ),
            'amount_display' => 'R$ ' . number_format( (float) get_post_meta( $order_id, '_hs_order_amount', true ), 2, ',', '.' ),
        ],
        (array) $result
    );
    if ( empty( $response['checkout_url'] ) && ! empty( $response['approve_url'] ) ) {
        $response['checkout_url'] = $response['approve_url'];
    }

    wp_send_json_success( $response );
}

/**
 * AJAX: return current status of an order.
 */
function hs_ajax_check_payment_status(): void {
    check_ajax_referer( 'hubskills_nonce', 'nonce' );

    $order_id = (int) ( $_POST['order_id'] ?? 0 );
    $order    = hs_get_order( $order_id );

    if ( ! $order ) {
        wp_send_json_error( [ 'message' => __( 'Pedido não encontrado.', 'hubskills-lms' ) ] );
    }

    // Only allow the order owner or an LMS admin to check.
    $current_user = get_current_user_id();
    if ( $order['user_id'] !== $current_user && ! hs_is_hs_admin( $current_user ) ) {
        wp_send_json_error( [ 'message' => __( 'Acesso negado.', 'hubskills-lms' ) ] );
    }

    wp_send_json_success( [
        'order_id' => $order_id,
        'status'   => $order['status'],
    ] );
}

/**
 * AJAX: request a refund for an order.
 */
function hs_ajax_request_refund(): void {
    check_ajax_referer( 'hubskills_nonce', 'nonce' );

    $order_id    = (int) ( $_POST['order_id'] ?? 0 );
    $current_uid = get_current_user_id();
    $order       = hs_get_order( $order_id );

    if ( ! $order ) {
        wp_send_json_error( [ 'message' => __( 'Pedido não encontrado.', 'hubskills-lms' ) ] );
    }

    if ( $order['user_id'] !== $current_uid && ! hs_is_hs_admin( $current_uid ) ) {
        wp_send_json_error( [ 'message' => __( 'Acesso negado.', 'hubskills-lms' ) ] );
    }

    if ( ! hs_is_hs_admin( $current_uid ) ) {
        wp_send_json_error( [ 'message' => __( 'Reembolsos precisam ser processados pelo administrador.', 'hubskills-lms' ) ], 403 );
    }

    if ( $order['status'] !== 'paid' ) {
        wp_send_json_error( [ 'message' => __( 'Apenas pedidos pagos podem ser reembolsados.', 'hubskills-lms' ) ] );
    }

    $refunded = hs_refund_order( $order_id );
    if ( ! $refunded ) {
        wp_send_json_error( [ 'message' => __( 'Erro ao processar reembolso.', 'hubskills-lms' ) ] );
    }

    wp_send_json_success( [ 'message' => __( 'Reembolso solicitado com sucesso.', 'hubskills-lms' ) ] );
}
