<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Amazon_SES_Email_Log extends WP_AMAZON_SES_ABSTRACT_DB {
    
    /**
     * Found email.
	 * 
     * @var object
     */
    private $email = null;

    /**
    * Get things started
    *
    * @access  public
    */
    public function __construct( $id = 0 ) {
        $this->set_props();
        $this->set_id( $id );
    }
    
	/**
	 * Set class properties
	*/
	protected function set_props() {
	    global $wpdb;
	    $this->table_name   = "{$wpdb->prefix}amazon_ses_email_log";
	    $this->primary_key  = 'ID';
	    $this->version      = WP_AMAZON_SES_VERSION;
	}

	/**
	 * Retrieve email property
	 * 
	 * @param string $column_name The column to retrieve
	 *
	 * @return null|var
	 */
	private function get_email_prop( $column_name ) {
		if ( ! array_key_exists( $column_name, $this->get_columns() ) ) {
			return false;
		}
        if ( null === $this->email ) {
            $this->email = $this->get_email();
            if ( null === $this->email ) {
                return $this->email;
            }
        }
	    return $this->email->{$column_name};
	}
	
    /**
     * Set ID.
     * 
     * @param int ID.
     */
    protected function set_id( $id ) {
        $this->id = absint( $id );
    }
	
    /**
     * Set found email data.
     * 
     * @param object $email Email object from DB
     */
    protected function set_email( $email ) {
        $this->email = $email;
    }

	/**
	 * Retrieve row with id
	 *
	 * @return null|object
	 */
	public function get_email() {
        if ( null === $this->email ) {
	        global $wpdb;
	        $this->email = $wpdb->get_row(
			    $wpdb->prepare(
				    "SELECT * FROM $this->table_name WHERE $this->primary_key = %s",
				    $this->id
			    )
		    );
        }
        return $this->email;
	}

	/**
	 * Retrieve row with event id
	 * 
	 * @param string $event_id The Event ID
	 *
	 * @return null|object
	 */
	public function get_email_by_event_id( $event_id ) {
	    global $wpdb;
	    $this->email = $wpdb->get_row(
	        $wpdb->prepare(
	            "SELECT * FROM $this->table_name WHERE event_id = %s",
	            $event_id
		    )
	    );
	    if( $this->email )
	    $this->set_id( $this->email->{$this->primary_key} );
        return $this->email;
	}
	
	/**
	 * Retrieve email ID
	 *
	 * @return null|int
	 */
	public function get_id() {
	    return $this->id;
	}
	
	/**
	 * Retrieve email event id
	 *
	 * @return null|string
	 */
	public function get_event_id() {
	    return $this->get_email_prop( 'event_id' );
	}
	
	/**
	 * Retrieve email events
	 *
	 * @return null|array
	 */
	public function get_events() {
	    return (array) maybe_unserialize( $this->get_email_prop( 'events' ) );
	}
	
	/**
	 * Retrieve email content
	 *
	 * @return null|string
	 */
	public function get_content() {
	    return $this->get_email_prop( 'content' );
	}
	
	/**
	 * Retrieve email date
	 *
	 * @return null|string
	 */
	public function get_date() {
	    return $this->get_email_prop( 'date' );
	}
	
	/**
	 * Retrieve email address
	 *
	 * @return null|string
	 */
	public function get_email_address() {
	    return $this->get_email_prop( 'email' );
	}
	
	/**
	 * Retrieve email subject
	 *
	 * @return null|string
	 */
	public function get_subject() {
	    return $this->get_email_prop( 'subject' );
	}
	
	/**
	 * Retrieve email headers
	 *
	 * @return array
	 */
	public function get_headers() {
	    return explode( ', ', $this->get_email_prop( 'headers' ) );
	}
	
	/**
	 * Retrieve email status
	 *
	 * @return null|string
	 */
	public function get_status() {
	    return $this->get_email_prop( 'status' );
	}
	
	/**
	 * Retrieve open count
	 *
	 * @return int
	 */
	public function get_open_count() {
	    return absint( $this->get_email_prop( 'open_count' ) );
	}
	
	/**
	 * Retrieve click count
	 *
	 * @return int
	 */
	public function get_click_count() {
	    return absint( $this->get_email_prop( 'click_count' ) );
	}
	
	/**
	 * Retrieve email created by
	 *
	 * @return null|string
	 */
	public function get_created_by() {
	    return $this->get_email_prop( 'created_by' );
	}
	
	/** 
	 * Update sent email
	 *
	 * @return true|false
	*/
	public function sent() {
        $data = [
            'status' => 'sent',
            'date'   => date('Y-m-d H:i:s')
        ];
	    return $this->update( $data );
	}
	
	/** 
	 * Update failed email
	 * 
	 * @param string $error Error message.
	 * @param string $status Failed status key.
	 *
	 * @return true|false
	*/
	public function failed( $error = '', $status = 'failed' ) {
	    $title = ( $status === 'failed' ) ? 'Fail' : 'Bounce';
        $failure = [ 
            $title => [ 
                'Timestamp' =>  date('Y-m-d H:i:s'),
                'ErrorMessage' => $error
            ] 
        ];
        $event = $this->get_events();
        $event[] = $failure;
        $data['events'] = maybe_serialize( $event );
        $data['status'] = $status;
        return $this->update( $data );
	}

    /**
    *  Associative array of columns
    *
    * @return array
    */
    public function email_status() {
		return array(
			'pending'   => 'Pending',
			'sent'      => 'Sent',
			'delivered' => 'Delivered',
			'failed'    => 'Failed',
			'opened'    => 'Opened',
			'clicked'   => 'Clicked',
			'hard_bounce' => 'Hard Bounce',
			'soft_bounce' => 'Soft Bounce',
			'complaint' => 'Complaint',
			'rejected'  => 'Rejected',
			'unsubscribed' => 'Unsubscribed'
		);
    }
	
	/**
	 * Get columns and formats
	 * 
	 * @return array
	*/
	public function get_columns() {
		return array(
			'ID'         => '%d',
			'email'      => '%s',
			'subject'    => '%s',
			'content'    => '%s',
			'status'     => '%s',
			'created_by' => '%s',
			'date'       => '%s',
			'headers'    => '%s',
			'open_count' => '%d',
			'click_count' => '%d',
			'events'     => '%s',
			'event_id'   => '%s',
			'attachments'=> '%s'
		);
	}

	/**
	 * Get default column values
	 *
	 * @access  public
	 * @return array
	*/
	public function get_column_defaults() {
		return array(
			'email'      => null,
			'subject'    => null,
			'content'    => null,
			'status'     => 'pending',
			'created_by' => get_current_user_id(),
			'date'       => date('Y-m-d H:i:s'),
			'headers'    => null,
			'open_count' => 0,
			'click_count' => 0,
			'events'     => null,
			'event_id'   => null,
			'attachments'=> null
		);
	}

	/**
	 * Create the table
	 *
	 * @access  public
	*/
	public static function create_table() {

		global $wpdb;

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$charset_collate = '';

		if ( ! empty( $wpdb->charset ) ) {
			$charset_collate .= "DEFAULT CHARACTER SET {$wpdb->charset}";
		}
		if ( ! empty( $wpdb->collate ) ) {
			$charset_collate .= " COLLATE {$wpdb->collate}";
		}
		
		$sql = "CREATE TABLE {$wpdb->prefix}amazon_ses_email_log (
		    ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		    email varchar(255) NULL,
		    subject varchar(255) NULL,
		    content mediumtext NULL,
		    status varchar(50) NOT NULL,
		    created_by varchar(50) NOT NULL,
		    date datetime NOT NULL,
			headers text NULL,
			open_count smallint(20) unsigned DEFAULT 0,
			click_count smallint(20) unsigned DEFAULT 0,
		    events text NULL,
		    event_id varchar(512) NULL,
			attachments text NULL,
		    PRIMARY KEY  (ID),
		    KEY status (status),
		    KEY date (date)
		) {$charset_collate};";

		dbDelta( $sql );
	}

}