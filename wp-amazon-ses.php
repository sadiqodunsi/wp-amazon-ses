<?php

/**
 * Plugin Name: WP Amazon SES and SNS
 * Description: Send and track all WordPress emails with Amazon SES and SNS
 * Author: Sadiq Odunsi
 * Author URI: https://sadiqodunsi.com
 * Version: 1.0.0
 * Requires at least: 4.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! defined( 'WP_AMAZON_SES_VERSION' ) ) {
	define( 'WP_AMAZON_SES_VERSION', '1.0.0' );
}

if ( ! defined( 'WP_AMAZON_SES_PLUGIN_FILE' ) ) {
	define( 'WP_AMAZON_SES_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'WP_AMAZON_SES_BASE_DIR' ) ) {
	define( 'WP_AMAZON_SES_BASE_DIR', plugin_dir_path( WP_AMAZON_SES_PLUGIN_FILE ) );
}

if ( ! defined( 'WP_AMAZON_SES_BASE_URL' ) ) {
	define( 'WP_AMAZON_SES_BASE_URL', plugin_dir_url( WP_AMAZON_SES_PLUGIN_FILE ) );
}

if ( ! defined( 'WP_AMAZON_SES_ADMIN_PAGE' ) ) {
	define( 'WP_AMAZON_SES_ADMIN_PAGE', 'amazon_ses_email_log' );
}

// Require the main class.
require_once WP_AMAZON_SES_BASE_DIR . 'includes/class-wp-amazon-ses.php';

WP_AMAZON_SES::get_instance();

