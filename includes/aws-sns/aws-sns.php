<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

require_once WP_AMAZON_SES_BASE_DIR . 'includes/aws-sns/vendor/autoload.php';
require_once WP_AMAZON_SES_BASE_DIR . 'includes/aws-sns/inc/class-sns.php';

add_action( 'rest_api_init', function() {
  register_rest_route( 'amazon-sns/v1', '/email-tracking', [
    'methods' => 'POST',
    'callback' => 'sdq_amazon_sns_email_tracking',
    'permission_callback' => '__return_true',
  ] );
} );

/**
 * The api callback funtion
 * 
 * @param WP_REST_Request $request The http request
 */
function sdq_amazon_sns_email_tracking( $request ) {
    AWS_SNS_EMAIL_TRACKING\SNS::get_instance()->process();
    http_response_code( 200 );
}