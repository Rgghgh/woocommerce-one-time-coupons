<?php
/*
Plugin Name: One Time Coupons for WooCommerce
Plugin URI: https://github.com/Rgghgh/woocommerce-one-time-coupons
Description: Single Use Coupons for woocommerce
Author: Rgghgh
Version: 1.0.2
Author URI: https://github.com/Rgghgh/
Text Domain: woo-one-time-coupons
Domain Path: /languages
*/

define("WCOTC_VERSION", '1.0.1');
define("WCOTC_TEXT_DOMAIN", 'woo-one-time-coupons');
define("WCOTC_TABLE", "wc_one_time_coupons");
define("WCOTC_COUPON_LENGTH", 8);

/**
 * Setup
 */

register_activation_hook(__FILE__, function () {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . WCOTC_TABLE;

    $sql = "CREATE TABLE $table_name (
			ID BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT, 
			coupon_id BIGINT(20) UNSIGNED NOT NULL,
			order_id BIGINT(20) UNSIGNED NULL DEFAULT NULL,
			code VARCHAR(512) NOT NULL,
			PRIMARY KEY (ID), 
			UNIQUE (code)
	   	) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    update_option('wcotc_version', WCOTC_VERSION);
});

/**
 * Admin Panel
 */

add_filter('woocommerce_coupon_data_tabs', function ($tabs) {
    $tabs['wc_otc_coupon_data'] = array(
        'label' => __('Coupon Codes', WCOTC_TEXT_DOMAIN),
        'target' => 'wc_otc_coupon_data',
        'class' => 'wc_otc_coupon_data'
    );
    return $tabs;
}, 10, 1);

add_action('woocommerce_coupon_data_panels', function ($coupon_id, $coupon) {
    echo '<div id="wc_otc_coupon_data" class="panel woocommerce_options_panel">';
    echo '<div class="options_group">';

    woocommerce_wp_checkbox(array(
        'id' => 'is_otc_coupon',
        'label' => __('Enable one time use codes', WCOTC_TEXT_DOMAIN),
        'description' => __('Allow this coupon to have multiple coupon codes', WCOTC_TEXT_DOMAIN),
    ));

    woocommerce_wp_text_input(array(
        'id' => 'otc_coupon_prefix',
        'label' => __('Coupon Prefixes', WCOTC_TEXT_DOMAIN),
        'description' => __('Will be prepended to generated coupons', WCOTC_TEXT_DOMAIN),
        'type' => 'text',
        'desc_tip' => true,
        'class' => 'short'
    ));

    woocommerce_wp_text_input(array(
        'id' => 'codes_to_generate',
        'label' => __('Codes amount', WCOTC_TEXT_DOMAIN),
        'description' => __('The amount of one-time-use codes to generate', WCOTC_TEXT_DOMAIN),
        'type' => 'number',
        'desc_tip' => true,
        'class' => 'short'
    ));

    echo '</div>';
    echo '<div class="options_group">';

    woocommerce_wp_textarea_input(array(
        'id' => 'free_codes',
        'label' => __('Free Codes', WCOTC_TEXT_DOMAIN),
        'value' => wcotc_get_codes($coupon_id, WCOTC_FREE),
    ));

    woocommerce_wp_textarea_input(array(
        'id' => 'used_codes',
        'label' => __('Used Codes', WCOTC_TEXT_DOMAIN),
        'value' => wcotc_get_codes($coupon_id, WCOTC_USED),
    ));

    echo '</div></div>';
}, 10, 2);

define('WCOTC_ALL', 0);
define('WCOTC_FREE', 1);
define('WCOTC_USED', 2);

function wcotc_get_codes($coupon_id, $state = WCOTC_ALL)
{
    global $wpdb;
    $table_name = $wpdb->prefix . WCOTC_TABLE;
    $sql = "SELECT code FROM $table_name WHERE coupon_id=%d";
    if ($state == WCOTC_FREE) $sql .= " AND order_id IS NULL";
    if ($state == WCOTC_USED) $sql .= " AND order_id IS NOT NULL";

    $result = $wpdb->get_results($wpdb->prepare($sql, $coupon_id));

    $codes = [];
    foreach ($result as $row)
        $codes[] = $row->code;

    return implode("\n", $codes);
}

add_action('woocommerce_process_shop_coupon_meta', function ($post_id) {
    update_post_meta($post_id, 'is_otc_coupon', boolval($_POST['is_otc_coupon']));
    update_post_meta($post_id, 'otc_coupon_prefix', wc_format_coupon_code(trim(wc_sanitize_coupon_code($_POST['otc_coupon_prefix']))));
    if ($_POST['codes_to_generate'] && is_numeric($_POST['codes_to_generate']))
        do_action('wcotc_generate_codes', $post_id, (int)$_POST['codes_to_generate']);
}, 10, 1);

add_action('wcotc_generate_codes', function ($post_id, $count) {
    global $wpdb;
    $table_name = $wpdb->prefix . WCOTC_TABLE;
    $prefix = get_post_meta($post_id, 'otc_coupon_prefix', true);

    $values = [];
    $place_holders = [];
    $query = "INSERT INTO $table_name (coupon_id, code) VALUES ";

    for ($i = 0; $i < $count; $i++) {
        $code = $prefix . substr(wp_generate_uuid4(), 0, WCOTC_COUPON_LENGTH);
        array_push($values, $post_id, $code);
        $place_holders[] = "('%d', '%s')";
    }

    $query .= implode(', ', $place_holders);
    $wpdb->query($wpdb->prepare("$query ", $values));
}, 1, 2);

/**
 * Coupon Logic
 */

add_filter('woocommerce_get_shop_coupon_data', function ($false, $code, WC_Coupon $wc_coupon) {
    global $wpdb;
    $table_name = $wpdb->prefix . WCOTC_TABLE;

    $sql = "SELECT * FROM $table_name WHERE code='%s'";
    $otc_coupon = $wpdb->get_row($wpdb->prepare($sql, $code), ARRAY_A);
    if (!$otc_coupon)
        return false;

    $original = new WC_Coupon($otc_coupon['coupon_id']);
    if (!$original)
        return false;

    if (!$original->get_meta("is_otc_coupon"))
        return false;

    $data = $original->get_data();
    $data['usage_count'] = $otc_coupon['order_id'] ? 1 : 0;
    $data['usage_limit'] = 1;
    return $data;
}, 10, 3);

add_filter('woocommerce_coupon_is_valid', function ($value, WC_Coupon $coupon) {
    if ($coupon->get_meta('is_otc_coupon'))
        return false;
    return $value;
}, 1, 2);

add_action('woocommerce_order_status_pending', 'wcotc_update_otc_coupon_usage');
add_action('woocommerce_order_status_completed', 'wcotc_update_otc_coupon_usage');
add_action('woocommerce_order_status_processing', 'wcotc_update_otc_coupon_usage');
add_action('woocommerce_order_status_on-hold', 'wcotc_update_otc_coupon_usage');
add_action('woocommerce_order_status_cancelled', 'wcotc_update_otc_coupon_usage');

function wcotc_update_otc_coupon_usage($order_id)
{
    global $wpdb;
    $table_name = $wpdb->prefix . WCOTC_TABLE;
    $sql = "SELECT * FROM $table_name WHERE code='%s'";

    $order = wc_get_order($order_id);
    if (!$order)
        return;

    foreach ($order->get_coupon_codes() as $code) {
        $coupon = new WC_Coupon($code);
        if (!$coupon->get_virtual())
            continue;

        $otc_coupon = $wpdb->get_row($wpdb->prepare($sql, $code), ARRAY_A);
        if (!$otc_coupon)
            continue;

        $new_order_id = $order->has_status('cancelled') ? null : $order_id;
        $wpdb->update($table_name, ["order_id" => $new_order_id], ["code" => $code]);
    }
}
