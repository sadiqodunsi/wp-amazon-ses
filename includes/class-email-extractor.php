<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Amazon_SES_Email_Extractor extends Amazon_SES_Email_Log {

    /**
     * Class constructor
     */
    public function __construct() {
        $this->set_props();
    }
    
    /**
     * Log sent email to database
     * 
     * @param array $mail Array of email data
     * 
     * @return int Database insert ID
     */
    public function log( $mail ) {
	    $data = [
			'email'         => $this->extract_receiver_email( $mail['to'] ),
			'subject'       => $mail['subject'],
			'status'        => 'sent',
			'content'       => $this->extract_message( $mail ),
            'headers'       => $this->extract_header( $mail )
		];
		
        if ( isset( $mail['attachments'] ) )
        $data['attachments'] = $this->extract_attachments( $mail );
        
        return $this->insert( $data );
    }

    /**
     * Extract receiver email
     * 
     * @param string $receiver Receiver email
     * 
     * @return string Receiver email
     */
    private function extract_receiver_email( $receiver ) {
        return $this->convert_multiparts_to_string( $receiver );
    }

    /**
     * Convert array to string
     * 
     * @param array $multiparts
     * 
     * @return string
     */
    private function convert_multiparts_to_string( $multiparts ) {

        if( is_array( $multiparts ) ) {
            $multiPartArray = $multiparts;
        } else {
            $multiPartArray = $this->split_comma( $multiparts );
        }

        $string = $this->join_array( $multiPartArray );

        return $string;
    }
    
    /**
     * Split string
     * 
     * @param string $string
     * 
     * @return array
     */
    private function split_comma( $string ) {
        $parts = preg_split( "/(,|,\s)/", $string );
        return $parts;
    }
    
    /**
     * Join array with comma
     * 
     * @param array $array
     * 
     * @return string
     */
    private function join_array( array $array ) {
        return implode(', ', $array);
    }
    
    /**
     * Extract message from email array
     * 
     * @param array $mail
     * 
     * @return string
     */
    private function extract_message( $mail ) {
        if ( isset( $mail['message'] ) ) {
            // Usually the message is stored in the message field
            return $mail['message'];
        } elseif ( isset( $mail['html'] ) ) {
            // For example Mandrill stores the message in the 'html' field
            return $mail['html'];
        } else {
            // Well we cannot find the message
            return '';
        }
    }
    
    /**
     * Extract header from email array
     * 
     * @param array $mail
     * 
     * @return string
     */
    private function extract_header( $mail ) {
        $headers = isset( $mail['headers'] ) ? $mail['headers'] : [];
        return $this->join_multiparts( $headers );
    }
    
    /**
     * Maybe convert array to string
     * 
     * @param array|string $multipart
     * 
     * @return string
     */
    private function join_multiparts( $multipart ) {
        return is_array( $multipart ) ? $this->join_array( $multipart ) : $multipart;
    }

    /**
     * Extract attachments from email array
     * 
     * @param array $mail
     * 
     * @return string
     */
    private function extract_attachments( $mail ) {
        $attachmentAbsPaths = isset( $mail['attachments'] ) ? $mail['attachments'] : [];

        if( ! is_array( $attachmentAbsPaths ) ) {
            $attachmentAbsPaths = $this->split_comma( $attachmentAbsPaths );
        }

        $attachment_urls = [];
        foreach ( $attachmentAbsPaths as $attachmentAbsPath ) {
            $attachment_urls[] = $attachmentAbsPath;
        }

        $string = $this->join_array( $attachment_urls );

        return $string;
    }
}