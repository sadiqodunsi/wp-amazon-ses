<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Amazon_SES_Email_Log_Query extends Amazon_SES_Email_Log {
    
    /**
     * Holds the args.
	 * 
     * @var array
     */
    private $args = [];
    
    /**
    * Get things started
    *
    * @access  public
    */
    public function __construct( $args = [] ) {
        $this->set_props();
        $this->args = $args;
    }

    /** 
	 * Query emails
	 * 
	 * @access  public
	 * @param   bool  $count  Return only the total number of results found (optional)
	 * 
	 * @return array|int Array of found results or total number of results found
	 */
    public function get_emails( $count = false ) {

	    global $wpdb;

	    $defaults = array(
		    'number'        => -1,
		    'offset'        => 0,
		    'orderby'       => $this->primary_key,
		    'order'         => 'DESC'
	    );

	    $args = wp_parse_args( $this->args, $defaults );

	    if( $args['number'] < 1 ) {
		    $args['number'] = PHP_INT_MAX;
	    }

	    $where = '';
	    
	    if( isset( $args['status'] ) ) {

		    if( is_array( $args['status'] ) ) {
				$statuses = esc_sql( implode( ',', $args['status'] ) );
			    $where .= "WHERE `status` IN(" . $statuses . ") ";
		    } else {
				$status = esc_sql( $args['status'] );
			    $where .= "WHERE `status` = '{$status}' ";
		    }
            
	    }
	    
	    if( isset( $args['subject'] ) ) {
            
		    if( empty( $where ) ) {
			    $where .= "WHERE";
		    } else {
			    $where .= "AND";
		    }
            $value = esc_sql( '%' . $wpdb->esc_like( $args['subject'] ) . '%' );
		    $where .= " `subject` LIKE '{$value}' ";

	    }
	    
	    if( isset( $args['email'] ) ) {

		    if( empty( $where ) ) {
			    $where .= "WHERE";
		    } else {
			    $where .= "AND";
		    }

		    if( is_array( $args['email'] ) ) {
				$emails = esc_sql( implode( ',', $args['email'] ) );
			    $where .= " `email` IN(" . $emails . ") ";
		    } else {
				$email = esc_sql( $args['email'] );
			    $where .= " `email` = '{$email}' ";
		    }

	    }

	    if( isset( $args['search'] ) ) {

		    if( empty( $where ) ) {
			    $where .= "WHERE";
		    } else {
			    $where .= "AND";
		    }
		    $search = esc_sql( '%' . $wpdb->esc_like( $args['search'] ) . '%' );
		    $where .= " `subject` LIKE '{$search}' ";

	    }

	    $args['orderby'] = ! array_key_exists( $args['orderby'], $this->get_columns() ) ? $this->primary_key : $args['orderby'];
		$args['order'] = in_array( $args['order'], [ 'DESC', 'ASC' ] ) ? $args['order'] : 'DESC';

		if ( true === $count ) {
			
			$results = absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} {$where};" ) );
			
		} else {

			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$this->table_name} {$where} ORDER BY {$args['orderby']} {$args['order']} LIMIT %d, %d;",
					absint( $args['offset'] ),
					absint( $args['number'] )
				)
			);

		}
	
	    return $results;

    }

	/**
	 * Return the number of results found for a given query
	 * 
	 * @return int
	 */
	public function count() {
		return $this->get_emails( true );
	}
}