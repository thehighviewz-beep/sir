<?php
/**
 * Plugin Name: Caricove — Vacation Enforcer (Woo) v1.1.2 (MU)
 * Description: Enforces vendor Vacation Mode everywhere (catalog visibility, add-to-cart validation, cart/checkout hard blocks) using Store Settings metas.
 *
 * Install:
 *  - Put this file at: /wp-content/mu-plugins/caricove-vacation-enforcer.php
 *  - Ensure your Store Settings saves these user_meta keys:
 *      _cc_store_vacation_enabled = 'yes'|'no'
 *      _cc_store_vacation_message = string (optional)
 *      _cc_store_availability_notice = string (optional)
 *
 * This plugin is theme-agnostic and blocks purchases even via direct URLs and existing carts.
 */

if ( ! defined('ABSPATH') ) { exit; }

/* ============================================================
 * Helpers
 * ============================================================ */

if ( ! function_exists('cc_vac_vendor_id_for_product_id') ) {
    function cc_vac_vendor_id_for_product_id( int $product_id ): int {
        if ( $product_id <= 0 ) return 0;

        // Variations: use parent product author
        $parent_id = (int) wp_get_post_parent_id( $product_id );
        if ( $parent_id > 0 ) $product_id = $parent_id;

        $author = (int) get_post_field( 'post_author', $product_id );
        return $author > 0 ? $author : 0;
    }
}

if ( ! function_exists('cc_vac_is_vendor_on_vacation') ) {
    function cc_vac_is_vendor_on_vacation( int $vendor_id ): bool {
        if ( $vendor_id <= 0 ) return false;
        $v = (string) get_user_meta( $vendor_id, '_cc_store_vacation_enabled', true );
        return ( strtolower(trim($v)) === 'yes' );
    }
}

if ( ! function_exists('cc_vac_vendor_message') ) {
    function cc_vac_vendor_message( int $vendor_id ): string {
        $msg = (string) get_user_meta( $vendor_id, '_cc_store_vacation_message', true );
        $msg = trim($msg);
        return $msg !== '' ? $msg : __( 'This seller is temporarily unavailable.', 'caricove' );
    }
}

if ( ! function_exists('cc_vac_vendor_availability_notice') ) {
    function cc_vac_vendor_availability_notice( int $vendor_id ): string {
        $msg = (string) get_user_meta( $vendor_id, '_cc_store_availability_notice', true );
        return trim($msg);
    }
}

/**
 * Public helper you can call from custom templates / department pages:
 * if ( cc_vac_product_blocked($product_id) ) { skip or render offline badge; }
 */
if ( ! function_exists('cc_vac_product_blocked') ) {
    function cc_vac_product_blocked( int $product_id ): bool {
        $vendor_id = cc_vac_vendor_id_for_product_id( $product_id );
        return $vendor_id > 0 && cc_vac_is_vendor_on_vacation( $vendor_id );
    }
}

/* ============================================================
 * 1) Catalog visibility — hide vacation vendor products
 * (Woo template loops respect this; custom queries may not)
 * ============================================================ */
add_filter('woocommerce_product_is_visible', function( $visible, $product_id ) {
    if ( is_admin() ) return $visible;
    return cc_vac_product_blocked( (int)$product_id ) ? false : $visible;
}, 20, 2);

/* ============================================================
 * 2) Purchasable — block purchases at product level
 * ============================================================ */
add_filter('woocommerce_is_purchasable', function( $purchasable, $product ) {
    if ( is_admin() ) return $purchasable;
    $pid = ( is_object($product) && method_exists($product,'get_id') ) ? (int)$product->get_id() : 0;
    return ( $pid > 0 && cc_vac_product_blocked($pid) ) ? false : $purchasable;
}, 20, 2);

add_filter('woocommerce_variation_is_purchasable', function( $purchasable, $variation ) {
    if ( is_admin() ) return $purchasable;
    $pid = ( is_object($variation) && method_exists($variation,'get_id') ) ? (int)$variation->get_id() : 0;
    return ( $pid > 0 && cc_vac_product_blocked($pid) ) ? false : $purchasable;
}, 20, 2);

/* ============================================================
 * 3) Add-to-cart hard stop (covers direct URLs + AJAX + Buy Now)
 * ============================================================ */
add_filter('woocommerce_add_to_cart_validation', function( $passed, $product_id, $quantity, $variation_id = 0, $variations = [] ) {
    if ( is_admin() ) return $passed;

    $target_id = $variation_id ? (int)$variation_id : (int)$product_id;
    $vendor_id = cc_vac_vendor_id_for_product_id( $target_id );

    if ( $vendor_id > 0 && cc_vac_is_vendor_on_vacation($vendor_id) ) {
        wc_add_notice( cc_vac_vendor_message($vendor_id), 'error' );
        // Persist message across pages (cart banner)
        if (function_exists('WC') && WC()->session) { WC()->session->set('cc_vac_blocked_msg', cc_vac_vendor_message($vendor_id)); }
        // Failsafe cookie (survives even if WC session is not initialized)
        @setcookie('cc_vac_blocked_msg', rawurlencode(cc_vac_vendor_message($vendor_id)), time()+600, (defined('COOKIEPATH') ? COOKIEPATH : '/'), (defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : ''), is_ssl(), true);
        if (defined('SITECOOKIEPATH') && SITECOOKIEPATH !== COOKIEPATH) {
            @setcookie('cc_vac_blocked_msg', rawurlencode(cc_vac_vendor_message($vendor_id)), time()+600, SITECOOKIEPATH, (defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : ''), is_ssl(), true);
        }

        // WC logger debug (WooCommerce > Status > Logs, source: caricove_vac_debug)
        if (function_exists('wc_get_logger')) {
            $lg = wc_get_logger();
            $lg->info('VACATION BLOCK add_to_cart', [
                'source' => 'caricove_vac_debug',
                'product_id' => (int)$product_id,
                'variation_id' => (int)$variation_id,
                'vendor_id' => (int)$vendor_id,
                'vac_meta' => (string)get_user_meta($vendor_id, '_cc_store_vacation_enabled', true),
                'user_id' => (int)get_current_user_id(),
            ]);
        }
        return false;
    }

    return $passed;
}, 20, 5);

/* ============================================================
 * 4) Cart-level enforcement — item not purchasable + notices
 * ============================================================ */
add_filter('woocommerce_cart_item_is_purchasable', function( $purchasable, $cart_item, $cart_item_key ) {
    if ( is_admin() ) return $purchasable;
    $pid = (int) ( $cart_item['product_id'] ?? 0 );
    return ( $pid > 0 && cc_vac_product_blocked($pid) ) ? false : $purchasable;
}, 20, 3);

add_action('woocommerce_before_cart', function() {
    if ( is_admin() ) return;
    if ( ! function_exists('WC') || ! WC()->cart ) return;

    $seen = [];
    foreach ( WC()->cart->get_cart() as $item ) {
        $pid = (int) ( $item['product_id'] ?? 0 );
        if ( $pid <= 0 ) continue;

        $vendor_id = cc_vac_vendor_id_for_product_id( $pid );
        if ( $vendor_id <= 0 || isset($seen[$vendor_id]) ) continue;
        $seen[$vendor_id] = true;

        if ( cc_vac_is_vendor_on_vacation($vendor_id) ) {
            wc_print_notice( cc_vac_vendor_message($vendor_id), 'notice' );
        } else {
            $avail = cc_vac_vendor_availability_notice($vendor_id);
            if ( $avail !== '' ) wc_print_notice( $avail, 'notice' );
        }
    }
}, 5);

/* ============================================================
 * 5) Checkout hard stop (cannot be bypassed)
 * ============================================================ */
add_action('woocommerce_checkout_process', function() {
    if ( is_admin() ) return;
    if ( ! function_exists('WC') || ! WC()->cart ) return;

    foreach ( WC()->cart->get_cart() as $item ) {
        $pid = (int) ( $item['product_id'] ?? 0 );
        if ( $pid <= 0 ) continue;

        $vendor_id = cc_vac_vendor_id_for_product_id( $pid );
        if ( $vendor_id > 0 && cc_vac_is_vendor_on_vacation($vendor_id) ) {
            wc_add_notice( cc_vac_vendor_message($vendor_id), 'error' );
            // One error is enough
            break;
        }
    }
}, 5);

/* ============================================================
 * 6) UX: Replace loop add-to-cart link for vacation products
 * ============================================================ */
add_filter('woocommerce_loop_add_to_cart_link', function( $html, $product, $args ) {
    if ( is_admin() ) return $html;
    $pid = ( is_object($product) && method_exists($product,'get_id') ) ? (int)$product->get_id() : 0;
    if ( $pid > 0 && cc_vac_product_blocked($pid) ) {
        $msg = esc_html__( 'Temporarily unavailable', 'caricove' );
        return '<span class="cc-vac-pill" style="display:inline-block;padding:8px 12px;border-radius:999px;background:rgba(24,165,255,.12);border:1px solid rgba(24,165,255,.25);font-weight:800;">'.$msg.'</span>';
    }
    return $html;
}, 20, 3);

/* ============================================================
 * 7) UX: Product page notice (vacation OR availability notice)
 * ============================================================ */
add_action('woocommerce_before_add_to_cart_form', function() {
    if ( is_admin() ) return;
    global $product;
    if ( ! $product || ! is_object($product) || ! method_exists($product,'get_id') ) return;

    $pid = (int) $product->get_id();
    $vendor_id = cc_vac_vendor_id_for_product_id( $pid );
    if ( $vendor_id <= 0 ) return;

    if ( cc_vac_is_vendor_on_vacation($vendor_id) ) {
        wc_print_notice( cc_vac_vendor_message($vendor_id), 'notice' );
    } else {
        $avail = cc_vac_vendor_availability_notice($vendor_id);
        if ( $avail !== '' ) wc_print_notice( $avail, 'notice' );
    }
}, 5);


add_action('wp_loaded', function () {
    if (is_admin() || wp_doing_ajax()) return;

    // Detect an add-to-cart attempt (GET or POST)
    $add_id = 0;

    if (!empty($_REQUEST['add-to-cart'])) {
        $add_id = (int) $_REQUEST['add-to-cart'];
    } elseif (!empty($_POST['add-to-cart'])) {
        $add_id = (int) $_POST['add-to-cart'];
    }

    if ($add_id <= 0) return;

    // Resolve vendor + vacation state
    $vendor_id = cc_vac_vendor_id_for_product_id($add_id);
    if ($vendor_id <= 0) return;

    if (!cc_vac_is_vendor_on_vacation($vendor_id)) return;

    // Set the same message (session + cookie) for continuity
    $msg = cc_vac_vendor_message($vendor_id);

    if (function_exists('WC') && WC()->session) {
        WC()->session->set('cc_vac_blocked_msg', $msg);
    }

    @setcookie('cc_vac_blocked_msg', rawurlencode($msg), time()+600, (defined('COOKIEPATH') ? COOKIEPATH : '/'), (defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : ''), is_ssl(), true);
    if (defined('SITECOOKIEPATH') && SITECOOKIEPATH !== COOKIEPATH) {
        @setcookie('cc_vac_blocked_msg', rawurlencode($msg), time()+600, SITECOOKIEPATH, (defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : ''), is_ssl(), true);
    }

    // Redirect to Seller Away (no cart detour)
    $away_slug = 'seller-away';
    wp_safe_redirect(home_url('/' . trim($away_slug, '/') . '/'));
    exit;
}, 0);





/* ============================================================
 * 8) HARD UX: Redirect vacation vendor product clicks to Seller Away page
 *    - Ensures "click product → seller away message" without relying on cart state
 *    - Change the slug below if your page slug differs
 * ============================================================ */
add_action('template_redirect', function () {
    if ( is_admin() || wp_doing_ajax() ) return;

    // Avoid redirect loops if you're already on the Seller Away page.
    // (Adjust slug if you change it.)
    $away_slug = 'seller-away';
    if ( function_exists('is_page') && is_page($away_slug) ) return;

    if ( ! function_exists('is_product') || ! is_product() ) return;

    global $post;
    $product_id = $post ? (int) $post->ID : 0;
    if ( $product_id <= 0 ) return;

    $vendor_id = cc_vac_vendor_id_for_product_id( $product_id );
    if ( $vendor_id <= 0 ) return;

    if ( ! cc_vac_is_vendor_on_vacation($vendor_id) ) return;

    $msg = cc_vac_vendor_message($vendor_id);

    // Persist message (optional — useful if you later want to display it on the away page)
    if ( function_exists('WC') && WC()->session ) {
        WC()->session->set('cc_vac_blocked_msg', $msg );
    }

    // Cookie for non-WC contexts / redundancy
    @setcookie('cc_vac_blocked_msg', rawurlencode($msg), time()+600, (defined('COOKIEPATH') ? COOKIEPATH : '/'), (defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : ''), is_ssl(), true);
    if ( defined('SITECOOKIEPATH') && SITECOOKIEPATH !== COOKIEPATH ) {
        @setcookie('cc_vac_blocked_msg', rawurlencode($msg), time()+600, SITECOOKIEPATH, (defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : ''), is_ssl(), true);
    }

    $to = home_url('/' . trim($away_slug, '/') . '/');
    wp_safe_redirect( $to );
    exit;
}, 1);

