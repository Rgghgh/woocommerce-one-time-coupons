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

class WoocommerceOneTimeCouponsPlugin
{
    private $version;
    private $table_name;

    public function __construct()
    {
        global $wpdb;
        $this->version = '1.0';
        $this->table_name = $wpdb->prefix . 'woocommerce_one_time_coupons';

        register_activation_hook(__FILE__, [$this, 'install']);
        register_deactivation_hook(__FILE__, [$this, 'uninstall']);
        add_action('plugins_loaded', [$this, 'update']);
    }

    public function install()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $this->table_name (
			`ID` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT, 
			`coupon_id` BIGINT(20) UNSIGNED NOT NULL,
			`order_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL,
			`code` TEXT NOT NULL,
			PRIMARY KEY (`ID`), 
			UNIQUE (`code`)
	   	) $charset_collate;";

        dbDelta($sql);
        add_option('wotc_version', $this->version);
    }

    public function uninstall()
    {
        global $wpdb;
        $sql = "DROP TABLE $this->table_name;";
        dbDelta($sql);
        delete_option('wotc_version');
    }

    public function update()
    {
        if (get_site_option('wotc_version') != $this->version) {
            $this->uninstall();
            $this->install();
        }
    }
}

// Run Plugin
new WoocommerceOneTimeCouponsPlugin();


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
