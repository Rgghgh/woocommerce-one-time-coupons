<?php
/**
 * @package Creating Tables Boilerplate WordPress Plugin
 * @version 1.0
 */
/*
Plugin Name: Creating Tables Boilerplate WordPress Plugin
Plugin URI: https://praison.com/
Description: Creating Tables Boilerplate WordPress Plugin
Author: Mervin Praison 
Version: 1.0
Author URI: https://praison.com/
*/

global $jal_db_version;
$jal_db_version = '1.0';

/*Creating or Updating the Table*/

function jal_install() {
	global $wpdb;
	global $jal_db_version;

	$table_name = $wpdb->prefix . 'liveshoutbox';
	
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		name tinytext NOT NULL,
		text text NOT NULL,
		url varchar(55) DEFAULT '' NOT NULL,
		PRIMARY KEY  (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

	add_option( 'jal_db_version', $jal_db_version );

	/*Adding an Upgrade Function*/

	global $wpdb;
	$installed_ver = get_option( "jal_db_version" );

	if ( $installed_ver != $jal_db_version ) {

		$table_name = $wpdb->prefix . 'liveshoutbox';

		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			name tinytext NOT NULL,
			text text NOT NULL,
			url varchar(100) DEFAULT '' NOT NULL,
			PRIMARY KEY  (id)
		);";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		update_option( "jal_db_version", $jal_db_version );
	}

}

/*Adding Initial Data*/

function jal_install_data() {

	global $wpdb;
	
	$welcome_name = 'Mr. WordPress';
	$welcome_text = 'Congratulations, you just completed the installation!';
	
	$table_name = $wpdb->prefix . 'liveshoutbox';
	
	$wpdb->insert( 
		$table_name, 
		array( 
			'time' => current_time( 'mysql' ), 
			'name' => $welcome_name, 
			'text' => $welcome_text, 
		) 
	);
}

/*Calling the functions*/

register_activation_hook( __FILE__, 'jal_install' );
register_activation_hook( __FILE__, 'jal_install_data' );


function myplugin_update_db_check() {
    global $jal_db_version;
    if ( get_site_option( 'jal_db_version' ) != $jal_db_version ) {
        jal_install();
    }
}
add_action( 'plugins_loaded', 'myplugin_update_db_check' );



?>
