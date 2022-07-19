<?php // @codingStandardsIgnoreLine

if ( ( ! defined( 'AWS_SES_WP_MAIL_KEY' ) || ! defined( 'AWS_SES_WP_MAIL_SECRET' ) || ! defined( 'AWS_SES_WP_MAIL_REGION' ) ) ) {
	return;
}

require_once WP_AMAZON_SES_BASE_DIR . 'includes/aws-ses/vendor/autoload.php';
require_once WP_AMAZON_SES_BASE_DIR . 'includes/aws-ses/inc/class-raw-ses.php';

/**
 * Override WordPress default wp_mail function with one that sends email using the AWS SDK.
 *
 * @param string|string[] $to          Array or comma-separated list of email addresses to send message.
 * @param string          $subject     Email subject.
 * @param string          $message     Message contents.
 * @param string|string[] $headers     Optional. Additional headers.
 * @param string|string[] $attachments Optional. Paths to files to attach.
 * 
 * @return bool Whether the email was sent successfully.
 */
if ( ! function_exists( 'wp_mail' ) ) {
	function wp_mail( $to, $subject, $message, $headers = [], $attachments = [] ) { // @codingStandardsIgnoreLine

		$ses = new AWS_SES_WP_Mail\Raw_SES();
	
		$result = $ses->send_mail( $to, $subject, $message, $headers, $attachments );
		
		// If there is an error throw error and return false
		if ( is_wp_error( $result ) ) {
			trigger_error(
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				sprintf( 'Sendmail SES Email failed: %d %s', $result->get_error_code(), $result->get_error_message() ),
				E_USER_WARNING
			);
			return false;
		}
		
		/**
         * Amazon has a cap on the number of emails that can be sent per second
         * We need to delay send rate to prevent sending error when sending bulk emails
		*/
		if( defined( 'AWS_SES_WP_SEND_BULK_EMAIL' ) && AWS_SES_WP_SEND_BULK_EMAIL ) {
			$max_send_rate = $ses->get_send_quota('send_rate');
            $milliseconds = 1000 / $max_send_rate;
            usleep( $milliseconds * 1000 ); // Multiply by 1000 to get nanoseconds
        }
        
		return $result;
	}
}
