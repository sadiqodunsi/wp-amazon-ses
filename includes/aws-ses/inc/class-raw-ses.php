<?php

namespace AWS_SES_WP_Mail;

use Aws\Ses\SesClient;
use Exception;
use WP_Error;
use Amazon_SES_Email_Log;

class Raw_SES {

    /**
     * AWS access key
     * 
     * @var string
     */
	private $key;

    /**
     * AWS secret key
     * 
     * @var string
     */
	private $secret;

    /**
     * AWS SES configuration set (for tracking)
     * 
     * @var string
     */
	private $config;

    /**
     * AWS account region
     * 
     * @var string
     */
	private $region;

    /**
     * Class constructor
     */
	public function __construct() {
        $this->setup();
	}

	/**
	 * Setup
	 */
	public function setup() {
		$this->key    = defined( 'AWS_SES_WP_MAIL_KEY' ) ? AWS_SES_WP_MAIL_KEY : null;
		$this->secret = defined( 'AWS_SES_WP_MAIL_SECRET' ) ? AWS_SES_WP_MAIL_SECRET : null;
		$this->config = defined( 'AWS_SES_WP_MAIL_CONFIG' ) ? AWS_SES_WP_MAIL_CONFIG : null;
		$this->region = defined( 'AWS_SES_WP_MAIL_REGION' ) ? AWS_SES_WP_MAIL_REGION : null;
	}

	/**
	 * Send email via AWS SDK
	 *
	 * @access public
     * 
	 * @param  string $to
	 * @param  string $subject
	 * @param  string $message
	 * @param  mixed $headers
	 * @param  array $attachments
     * 
	 * @return bool true if mail has been sent, false if it failed
	 */
	public function send_mail( $to, $subject, $message, $headers = [], $attachments = [] ) {

		// Compact the input, apply the filters, and extract them back out
		extract( $atts = apply_filters( 'wp_mail', compact( 'to', 'subject', 'message', 'headers', 'attachments' ) ) ); // @codingStandardsIgnoreLine
		
        $pre_wp_mail = apply_filters( 'pre_wp_mail', null, $atts );
        
        if ( null !== $pre_wp_mail ) {
            return $pre_wp_mail;
        }
        
		// Get headers as array
		if ( empty( $headers ) ) {
			$headers = [];
		}
        
		if ( ! is_array( $headers ) ) {
			// Explode the headers out, so this function can take both
			// string headers and an array of headers.
			$headers = array_filter( explode( "\n", str_replace( "\r\n", "\n", $headers ) ) );
		}
        
        // Headers.
        $cc       = [];
        $bcc      = [];
        $reply_to = [];
        $custom_headers = [];

		// Normalize header names to Camel-Case and get from name/email
		foreach ( $headers as $header ) {
			if ( strpos( $header, ':' ) === false ) {
                continue;
			}
            list( $name, $content ) = array_map( 'trim', explode( ':', $header ) );
			$name = ucwords( strtolower( $name ), '-' );
            if ( $name === 'From' ) {
                // Mainly for legacy -- process a "From:" header if it's there.
                $bracket_pos = strpos( $content, '<' );
                if ( false !== $bracket_pos ) {
                    // Text before the bracketed email is the "From" name.
                    if ( $bracket_pos > 0 ) {
                        $from_name = substr( $content, 0, $bracket_pos - 1 );
                        $from_name = str_replace( '"', '', $from_name );
                        $from_name = trim( $from_name );
                    }
 
                    $from_email = substr( $content, $bracket_pos + 1 );
                    $from_email = str_replace( '>', '', $from_email );
                    $from_email = trim( $from_email );
 
                // Avoid setting an empty $from_email.
                } elseif ( '' !== trim( $content ) ) {
                    $from_email = trim( $content );
                }
                
            } else if ( $name === 'Content-Type' ) {
                if ( strpos( $content, ';' ) !== false ) {
                    list( $content_type, $charset ) = array_map( 'trim', explode( ';', $content ) );
                    if ( false !== stripos( $charset, 'charset=' ) ) {
                        $charset = trim( str_replace( array( 'charset=', '"' ), '', $charset ) );
                    } elseif ( false !== stripos( $charset, 'boundary=' ) ) {
                        $boundary = trim( str_replace( array( 'BOUNDARY=', 'boundary=', '"' ), '', $charset ) );
                        $charset  = '';
                    }
                    
                // Avoid setting an empty $content_type.
                } elseif ( '' !== trim( $content ) ) {
                    $content_type = trim( $content );
                }

            } else if ( $name === 'Cc' ) {
                $cc = explode( ',', $content );

            } else if ( $name === 'Bcc' ) {
                $bcc = explode( ',', $content );

            } else if ( $name === 'Reply-To' ) {
                $reply_to = explode( ',', $content );

            } else {
                // Custom headers.
                $custom_headers[ trim( $name ) ] = trim( $content );
            }
		}
        
        // If we don't have a content-type from the input headers.
        if ( ! isset( $content_type ) ) {
            $content_type = 'text/plain';
        }
        
        // If we don't have a charset from the input headers.
        if ( ! isset( $charset ) ) {
            $charset = get_bloginfo( 'charset' );
        }
		
        // If we don't have a name from the input headers.
        if ( ! isset( $from_name ) ) {
            $from_name = get_bloginfo( 'name' );
        }
        
		// If we don't have an email from the input headers
		if ( ! isset( $from_email ) ) {
		    $sitename = strtolower( wp_parse_url( site_url(), PHP_URL_HOST ) );
		    if ( 'www.' === substr( $sitename, 0, 4 ) ) {
			    $sitename = substr( $sitename, 4 );
		    }
		    $from_email = get_bloginfo('admin_email');
		    $from_email = $from_email ? $from_email : 'no-reply@' . $sitename;
		}
        
        /**
        * Filters the default wp_mail() charset.
        *
        * @param string $charset Default email charset.
        */
        $charset = apply_filters( 'wp_mail_charset', $charset );
		
        /**
        * Filters the wp_mail() content type.
        *
        * @param string $content_type Default wp_mail() content type.
        */
        $content_type = apply_filters( 'wp_mail_content_type', $content_type );
		
		/**
		 * Filters the address email is sent from.
		 *
		 * @param string $from_email The email address to send from.
		 */
		$from_email = apply_filters( 'wp_mail_from', $from_email );
        
		/**
		 * Filters the name for the email sender.
		 *
		 * @param string $from_name The name to send email from.
		 */
		$from_name = apply_filters( 'wp_mail_from_name', $from_name );
        
        // Create an SesClient.
		$ses = $this->get_client();
		if ( is_wp_error( $ses ) ) {
            $error_data = compact( 'to', 'subject', 'message', 'headers', 'attachments' );
		    $error = new WP_Error( 'wp_mail_failed', $ses->getMessage(), $error_data );
		    do_action( 'wp_mail_failed', $error );
			return false;
		}

        // Create a new PHPMailer object.
        $mailer = $this->get_PHPMailer();

        // Set whether it's plaintext, depending on $content_type.
        if ( 'text/html' === $content_type ) {
            $mailer->isHTML( true );
        }

        /**
         * Add components to the email.
         * 
         * XMailer = ' ' must be used to turn off "Using PHPMailer..." in header.
         */
        $mailer->setFrom( $from_email, $from_name );
        $mailer->Subject = $subject;
        $mailer->Body = $message;
        $mailer->CharSet = $charset;
        $mailer->XMailer = ' ';
        $mailer->addCustomHeader( 'X-SES-CONFIGURATION-SET', $this->config );

        // Set destination addresses, using appropriate methods for handling addresses.
        $address_headers = compact( 'to', 'cc', 'bcc', 'reply_to' );
        foreach ( $address_headers as $address_header => $addresses ) {
            if ( empty( $addresses ) ) {
                continue;
            }
            foreach ( (array) $addresses as $address ) {
                try {
                    // Break $recipient into name and address parts if in the format "Foo <bar@baz.com>".
                    $recipient_name = '';
     
                    if ( preg_match( '/(.*)<(.+)>/', $address, $matches ) ) {
                        if ( count( $matches ) == 3 ) {
                            $recipient_name = $matches[1];
                            $address        = $matches[2];
                        }
                    }
                    switch ( $address_header ) {
                        case 'to':
                            $mailer->addAddress( $address, $recipient_name );
                            break;
                        case 'cc':
                            $mailer->addCc( $address, $recipient_name );
                            break;
                        case 'bcc':
                            $mailer->addBcc( $address, $recipient_name );
                            break;
                        case 'reply_to':
                            $mailer->addReplyTo( $address, $recipient_name );
                            break;
                    }
                } catch ( \PHPMailer\PHPMailer\Exception $e ) {
                    continue;
                }
            }
        }
 
        // Set custom headers.
        if ( ! empty( $custom_headers ) ) {
            foreach ( (array) $custom_headers as $name => $content ) {
                // Only add custom headers not added automatically by PHPMailer.
                if ( ! in_array( $name, array( 'MIME-Version', 'X-Mailer' ), true ) ) {
                    try {
                        $mailer->addCustomHeader( sprintf( '%1$s: %2$s', $name, $content ) );
                    } catch ( \PHPMailer\PHPMailer\Exception $e ) {
                        continue;
                    }
                }
            }
            if ( false !== stripos( $content_type, 'multipart' ) && ! empty( $boundary ) ) {
                $mailer->addCustomHeader( sprintf( 'Content-Type: %s; boundary="%s"', $content_type, $boundary ) );
            }
        }

        // Add attachments
        if ( ! empty( $attachments ) ) {
            foreach ( $attachments as $attachment ) {
                try {
                    $mailer->addAttachment( $attachment );
                } catch ( \PHPMailer\PHPMailer\Exception $e ) {
                    continue;
                }
            }
        }

        // Attempt to assemble the above components into a MIME message.
        if ( ! $mailer->preSend() ) {
            return $mailer->ErrorInfo;
        } else {
            // Create a new variable that contains the MIME message.
            $message = $mailer->getSentMIMEMessage();
        }
        
		try {

            // Send the message via aws ses
            $result = $ses->sendRawEmail([
                'RawMessage' => [
                    'Data' => $message
                ]
            ]);
			
            /**
             * Record event id for tracking
             * $sdq_current_email_log_id is defined in WP_AMAZON_SES class in method log_wp_mail
             */
            global $sdq_current_email_log_id;
            if ( isset( $sdq_current_email_log_id ) ) {
                $mailer = new Amazon_SES_Email_Log( $sdq_current_email_log_id );
                $mailer->update( [ 'event_id' => $result->get('MessageId') ] );
		    }
		    
		} catch ( Exception $e ) {
		    
		    $error_data = compact( 'to', 'subject', 'message', 'headers', 'attachments' );
		    $error = new WP_Error( 'wp_mail_failed', $e->getMessage(), $error_data );
		    do_action( 'wp_mail_failed', $error );
			return $error;
			
		}
		
		return true;
	}

	/**
	 * Get the Send Quota for AWS SES.
     * 
     * @param string $data Data to return (max|sent|send_rate|remaining). Optional.
	 *
	 * @return string|array|WP_Error Requested data, Array of Sending quota data or wp error.
	 */
	public function get_send_quota( $data = '' ) {
	    
		$ses = $this->get_client();
        
		if ( is_wp_error( $ses ) ) {
			return $ses;
		}
        
		try {
		    
			$result = $ses->getSendQuota([]);
            $max    = $result["Max24HourSend"];
            $sent   = $result["SentLast24Hours"];
            $remaining = $max - $sent;
            $send_rate = $result["MaxSendRate"];
            
            if ( $data === 'max' ){
                $result = $max;
            } else if ( $data === 'sent' ){
                $result = $sent;
            } else if ( $data === 'remaining' ){
                $result = $remaining;
            } else if ( $data === 'send_rate' ){
                $result = $rate;
            } else {
                $result = compact( 'max', 'sent', 'remaining', 'send_rate' );
            }
			
		} catch ( Exception $e ) {
			return new WP_Error( get_class( $e ), $e->getMessage() );
		}

		return $result;
	}

	/**
	 * Get the Send Statistics for AWS SES.
	 *
	 * @return Array|WP_Error
	 */
	public function get_send_statistics() {
	    
		$ses = $this->get_client();
        
		if ( is_wp_error( $ses ) ) {
			return $ses;
		}
        
		try {
			$result = $ses->getSendStatistics([]);
		} catch ( Exception $e ) {
			return new WP_Error( get_class( $e ), $e->getMessage() );
		}

		return $result;
	}

	/**
	 * Get the client for AWS SES.
	 *
	 * @return SesClient|WP_Error
	 */
	public function get_client() {
		if ( ! empty( $this->client ) ) {
			return $this->client;
		}
        
		$params = [
			'version' => 'latest',
		];
        
		if ( $this->key && $this->secret ) {
			$params['credentials'] = [
				'key' => $this->key,
				'secret' => $this->secret,
			];
		}
        
		if ( $this->region ) {
			$params['signature'] = 'v4';
			$params['region'] = $this->region;
		}
        
		if ( defined( 'WP_PROXY_HOST' ) && defined( 'WP_PROXY_PORT' ) ) {
			$proxy_auth = '';
			$proxy_address = WP_PROXY_HOST . ':' . WP_PROXY_PORT;
            
			if ( defined( 'WP_PROXY_USERNAME' ) && defined( 'WP_PROXY_PASSWORD' ) ) {
				$proxy_auth = WP_PROXY_USERNAME . ':' . WP_PROXY_PASSWORD . '@';
			}
            
			$params['request.options']['proxy'] = $proxy_auth . $proxy_address;
		}
        
		$params = apply_filters( 'aws_ses_wp_mail_ses_client_params', $params );
        
		try {
			$this->client = SesClient::factory( $params );
		} catch ( Exception $e ) {
			return new WP_Error( get_class( $e ), $e->getMessage() );
		}

		return $this->client;
	}

	/**
	 * Gets the PHP Mailer instance.
	 * 
	 * Backwards-compatibility for pre-5.5 versions of WordPress.
	 *
	 * @return PHPMailer
	 */
	public function get_PHPMailer() {
		if ( file_exists( ABSPATH . WPINC . '/PHPMailer/PHPMailer.php' ) ) {
			require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
			require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
			return new \PHPMailer\PHPMailer\PHPMailer();
	    }
    }
}
