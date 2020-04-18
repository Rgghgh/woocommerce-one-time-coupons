<?php
/*
Plugin Name: Woocommerce One Time Coupons
Plugin URI: https://github.com/Rgghgh/woocommerce-one-time-coupons
Description: Single Use Coupons for woocommerce
Author: Rgghgh
Version: 1.0
Author URI: https://github.com/Rgghgh/
Text Domain: wc-one-time-coupons
Domain Path: /languages
*/

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
define("WC_OTC_VERSION", '1.0');
define("WC_OTC_TEXT_DOMAIN", 'wc-one-time-coupons');
define("WC_OTC_TABLE", "woocommerce_one_time_coupons");
define("WC_OTC_COUPON_LENGTH", 8);

register_activation_hook(__FILE__, function () {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . WC_OTC_TABLE;

    $sql = "CREATE TABLE $table_name (
			`ID` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT, 
			`coupon_id` BIGINT(20) UNSIGNED NOT NULL,
			`order_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL,
			`code` TEXT NOT NULL,
			PRIMARY KEY (`ID`), 
			UNIQUE (`code`)
	   	) $charset_collate;";

    dbDelta($sql);
    add_option('wc_otc_version', WC_OTC_VERSION);
});

add_filter('woocommerce_coupon_data_tabs', function ($tabs) {
    $tabs['wc_otc_coupon_data'] = array(
        'label' => __('Coupon Codes', WC_OTC_TEXT_DOMAIN),
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
        'label' => __('Enable one time use codes', WC_OTC_TEXT_DOMAIN),
        'description' => __('Allow this coupon to have multiple coupon codes', WC_OTC_TEXT_DOMAIN),
    ));

    woocommerce_wp_text_input(array(
        'id' => 'otc_coupon_prefix',
        'label' => __('Coupon Prefixes', WC_OTC_TEXT_DOMAIN),
        'description' => __('Will be prepended to generated coupons', WC_OTC_TEXT_DOMAIN),
        'type' => 'text',
        'desc_tip' => true,
        'class' => 'short'
    ));

    woocommerce_wp_text_input(array(
        'id' => 'codes_to_generate',
        'label' => __('Codes amount', WC_OTC_TEXT_DOMAIN),
        'description' => __('The amount of one-time-use codes to generate', WC_OTC_TEXT_DOMAIN),
        'type' => 'number',
        'desc_tip' => true,
        'class' => 'short'
    ));

    echo '</div>';
    echo '<div class="options_group">';
    echo "<legend>Test</legend>";

    woocommerce_wp_textarea_input(array(
        'id' => 'all_codes',
        'label' => __('All Codes', WC_OTC_TEXT_DOMAIN),
        'value' => get_codes($coupon_id),
        'disabled' => true
    ));

    woocommerce_wp_textarea_input(array(
        'id' => 'free_codes',
        'label' => __('Free Codes', WC_OTC_TEXT_DOMAIN),
        'value' => get_codes($coupon_id, WC_OTC_FREE),
    ));

    woocommerce_wp_textarea_input(array(
        'id' => 'used_codes',
        'label' => __('Used Codes', WC_OTC_TEXT_DOMAIN),
        'value' => get_codes($coupon_id, WC_OTC_USED),
    ));

    echo '</div></div>';
}, 10, 2);

define('WC_OTC_ALL', 0);
define('WC_OTC_FREE', 1);
define('WC_OTC_USED', 2);

function get_codes($coupon_id, $state = WC_OTC_ALL)
{
    global $wpdb;
    $table_name = $wpdb->prefix . WC_OTC_TABLE;
    $sql = "SELECT code FROM $table_name WHERE coupon_id=%d";
    if ($state == WC_OTC_FREE) $sql .= " AND order_id IS NULL";
    if ($state == WC_OTC_USED) $sql .= " AND order_id IS NOT NULL";

    $result = $wpdb->get_results($wpdb->prepare($sql, $coupon_id));

    $codes = [];
    foreach ($result as $row)
        $codes[] = $row->code;

    return implode("\r\n", $codes);
}

add_action('woocommerce_process_shop_coupon_meta', function ($post_id) {
    update_post_meta($post_id, 'is_otc_coupon', $_POST['is_otc_coupon']);
    update_post_meta($post_id, 'otc_coupon_prefix', $_POST['otc_coupon_prefix']);
    if ($_POST['codes_to_generate'])
        do_action('wc_otc_generate_codes', $post_id, (int)$_POST['codes_to_generate']);
}, 10, 1);

add_action('wc_otc_generate_codes', function ($post_id, $count) {
    global $wpdb;
    $table_name = $wpdb->prefix . WC_OTC_TABLE;
    $prefix = get_post_meta($post_id, 'otc_coupon_prefix', true);

    $values = [];
    $place_holders = [];
    $query = "INSERT INTO $table_name (coupon_id, code) VALUES ";

    for ($i = 0; $i < $count; $i++) {
        $code = $prefix . substr(wp_generate_uuid4(), 0, WC_OTC_COUPON_LENGTH);
        array_push($values, $post_id, $code);
        $place_holders[] = "('%d', '%s')";
    }

    $query .= implode(', ', $place_holders);
    $wpdb->query($wpdb->prepare("$query ", $values));
}, 1, 2);

add_filter('woocommerce_coupon_code', function ($value) {
    global $wpdb;
    $table_name = $wpdb->prefix . WC_OTC_TABLE;

    $sql = "SELECT coupon_id FROM $table_name WHERE code='%s'";
    $coupon_id = $wpdb->get_var($wpdb->prepare($sql, $value));
    if ($coupon_id) {
        if (!WC()->session->has_session())
            throw new Exception("NO SESSION");
        WC()->session->set('wc_otc_coupon', $value);
        return get_the_title($coupon_id);
    }

    return $value;
});

add_filter('woocommerce_coupon_is_valid', function ($value, WC_Coupon $coupon) {
    if (WC()->session->get("wc_otc_coupon"))


    if ($coupon->get_meta('is_otc_coupon') && !WC()->session->get("wc_otc_coupon"))
        throw new Exception("is otc");
    return $value;
}, 1, 2);

add_action('woocommerce_new_order', function ($order_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . WC_OTC_TABLE;
    $otc_coupon = WC()->session->get("wc_otc_coupon");
    $wpdb->update($table_name, ["order_id" => $order_id], ["code" => $otc_coupon]);
}, 10);
