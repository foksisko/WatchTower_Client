<?php
/*
Plugin Name: Whatarmy Watchtower
Plugin URI: https://github.com/c2pdev/WatchTower_Client
Description: WP website monitoring API
Author: Code2prog
Version: 1.1.7
Author URI: http://whatarmy.com
*/

/**
 * Composer dependencies autoload
 */
require 'vendor/autoload.php';

/**
 * UPDATE Plugin
 */
require 'plugin-update-checker/plugin-update-checker.php';
$className       = PucFactory::getLatestClassVersion( 'PucGitHubChecker' );
$myUpdateChecker = new $className(
	'https://github.com/c2pdev/WatchTower_Client',
	__FILE__,
	'master'
);

/**
 * Register autoload SRC
 */
register_wp_autoload( 'Whatarmy_Watchtower\\', __DIR__ . '/src' );

/**
 * Include Plugin Class
 */
if ( ! function_exists( 'get_plugins' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
/**
 * Activation Hook
 */
register_activation_hook( __FILE__, array( '\Whatarmy_Watchtower\Install_Uninstall', 'install' ) );

/**
 * RUN API ENDPOINT
 */
new \Whatarmy_Watchtower\Watchtower_API_Endpoint();

/**
 * Run Watchtower main class
 */
new \Whatarmy_Watchtower\Watchtower();
