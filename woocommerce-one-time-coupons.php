<?php
/*
Plugin Name: Woocommerce One Time Coupons
Plugin URI: https://github.com/Rgghgh/woocommerce-one-time-coupons
Description: Single Use Coupons for woocommerce
Author: Rgghgh
Version: 1.0
Author URI: https://github.com/Rgghgh/
*/

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

register_activation_hook(__FILE__, function () {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $version = '1.0';
    $table_name = $wpdb->prefix . 'woocommerce_one_time_coupons';

    $sql = "CREATE TABLE $table_name (
			`ID` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT, 
			`coupon_id` BIGINT(20) UNSIGNED NOT NULL,
			`order_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL,
			`code` TEXT NOT NULL,
			PRIMARY KEY (`ID`), 
			UNIQUE (`code`)
	   	) $charset_collate;";

    dbDelta($sql);
    add_option('wotc_version', $version);
});

add_filter('woocommerce_coupon_data_tabs', function ($tabs) {
    $tabs['wc_otc_coupon_data'] = array(
        'label' => __('WebDav Coupon', 'webdav-coupon-plugin'),
        'target' => 'wc_otc_coupon_data',
        'class' => 'wc_otc_coupon_data'
    );
    return $tabs;
}, 10, 1);

add_action('woocommerce_coupon_data_panels', function () {
    ?>
    <div id="wc_otc_coupon_data" class="panel woocommerce_options_panel"><?php

    echo '<div class="options_group">';

    woocommerce_wp_checkbox(array(
        'id' => 'wc_autoship_enabled',
        'label' => __('Enable for Autoship', 'wc-autoship'),
        'description' => __('Enable this coupon when autoship items are added to the cart.', 'wc-autoship'),
    ));

    // Usage limit per coupons
    woocommerce_wp_text_input(array(
        'id' => 'wc_autoship_min_item_quantity',
        'label' => __('Minimum Quantity', 'wc-autoship'),
        'description' => __('The minimum number of autoship items required in the shopping cart.', 'wc-autoship'),
        'type' => 'number',
        'desc_tip' => true,
        'class' => 'short',
        'custom_attributes' => array(
            'step' => '1',
            'min' => '1'
        )
    ));

    woocommerce_wp_checkbox(array(
        'id' => 'wc_autoship_apply_automatically',
        'label' => __('Apply Automatically', 'wc-autoship'),
        'description' => __('Apply this coupon to the cart automatically.', 'wc-autoship'),
    ));

    echo '</div>';

    ?></div><?php
});

/*

function wotc_install_data()
{

    global $wpdb;

    $welcome_name = 'Mr. WordPress';
    $welcome_text = 'Congratulations, you just completed the installation!';

    $table_name = $wpdb->prefix . 'liveshoutbox';

    $wpdb->insert(
        $table_name,
        array(
            'time' => current_time('mysql'),
            'name' => $welcome_name,
            'text' => $welcome_text,
        )
    );
}

 */

?>
