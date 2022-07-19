<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Amazon_SES_List_Table extends WP_List_Table {

    /**
     * Total emails found
     * 
     * @var int
     */
    private $total_items;

    /**
     * Amazon_SES_Email_Log instance.
     * 
     * @var Amazon_SES_List_Table
     */
    public $email_logger;
    
    /**
     * Class constructor
     */
    public function __construct() {
        
        parent::__construct( [
			'singular' => 'Email log', //singular name of the listed records
			'plural'   => 'Email logs', //plural name of the listed records
			'ajax'     => false //should this table support ajax?

		] );

    }

    /**
    *  Get Amazon_SES_Email_Log instance.
    *
    * @return Amazon_SES_Email_Log
    */
    public function email_logger() {
        if ( $this->email_logger ) {
            return $this->email_logger;
        }
        return $this->email_logger = new Amazon_SES_Email_Log();
    }

    /**
    *  Associative array of columns to show
    *
    * @return array
    */
    public function get_columns() {
		$columns = array(
			'action'     => 'Actions',
			'date'       => 'Date',
			'email'      => 'Email Address',
			'subject'    => 'Subject',
			'status'     => 'Status',
			'headers'    => 'Headers',
			'created_by' => 'Created By',
			'attachments'=> 'Attachments',
			'open_count' => 'Open count',
			'click_count'=> 'Click count',
			'events'     => 'Events'
		);
        return $columns;
    }

    /**
    * Columns to make sortable.
    *
    * @return array
    */
    public function get_sortable_columns() {
        return [
            'date'        => [ 'date', 'asc' ],
            'open_count'  => [ 'open_count', true ],
            'click_count' => [ 'click_count', true ]
        ];
    }
    
    /**
    * Retrieve email data from the database
    *
    * @param int $per_page
    * @param int $current_page
    *
    * @return array Array of emails
    */
    public function get_emails( $per_page = 20, $current_page = 1 ) {
        
        $offset     = ( $current_page - 1 ) * $per_page;
        $order_by   = ! empty($_GET['orderby']) ? $_GET['orderby'] : 'ID';
        $order      = ! empty($_GET['order']) ? $_GET['order'] : 'DESC';
        
        $args = array(
		    'number'  => $per_page,
		    'offset'  => $offset,
		    'orderby' => $order_by,
		    'order'   => $order,
	    );
	    
        if ( isset( $_GET['subject'] ) ) {
            $args['subject'] = urldecode( $_GET['subject'] );
        }
	    
        if ( isset( $_GET['status'] ) ) {
            $args['status'] = $_GET['status'];
        }
        
        if ( ! empty( $_POST['s'] ) ) {
            $args['search'] = wp_unslash( trim( $_POST['s'] ) );
            if ( strpos( $args['search'], '@' ) !== false ) {
                $args['email'] = $args['search'];
                unset( $args['search'] );
            }
        }

	    $query = new Amazon_SES_Email_Log_Query( $args );
	    $this->total_items = $query->count();
		return $query->get_emails();
    }
    
    /**
    * Handles data query, filter, sorting, and pagination.
    */
    public function prepare_items() {
        
        $this->process_bulk_action();
        
        $this->_column_headers = $this->get_column_info();

        $per_page     = $this->get_items_per_page( 'emails_per_page', 20 );
        $current_page = $this->get_pagenum();
        $this->items  = $this->get_emails( $per_page, $current_page );

        $this->set_pagination_args( [
            'total_items' => $this->total_items,
            'per_page'    => $per_page
        ] );
        
    }
    
    /**
     * Provides a list of statuses and count for easy filtering
     *
     * @return array An array of HTML links, one for each view.
     */
    protected function get_views() {
        
        $url = 'admin.php?page=' . WP_AMAZON_SES_ADMIN_PAGE;
        $current = ( ! empty( $_GET['status'] ) ? $_GET['status'] : 'all' );
        
	    $query = new Amazon_SES_Email_Log_Query();
        $current_attr = ( $current === 'all' ) ? ' class="current" aria-current="page"' : '';
        $links['all'] = '<a href="' .$url. '"' .$current_attr. '>All <span class="count">(' . $query->count() . ')</span></a>';
        
	    $statuses   = $this->email_logger()->email_status();
        foreach ( $statuses as $key => $status ) {

            $query = new Amazon_SES_Email_Log_Query( [ 'status' => $key ] );        
            if ( 0 === $count = $query->count() ){
                continue;
            }
            
            $current_attr = ( $current == $key ) ? ' class="current" aria-current="page"' : '';
            
            $links[$key] = '<a href="' .esc_url( add_query_arg( 'status', $key, $url ) ). '"' .$current_attr. '>' .$status. ' <span class="count">(' .$count. ')</span></a>';
        }

        return $links;
    }
    
    /** 
     * Text displayed when no order is available
    */
    public function no_items() {
        echo 'No email avaliable.';
    }
    
    /** 
     * Failed statuses
     * 
     * @return array
     */
    private function failed_statuses() {
        return ['hard_bounce', 'failed'];
    }

	/**
	 * Extra controls to be displayed between bulk actions and pagination.
	 *
	 * @param string $which Either top or bottom of the page.
	 */
	protected function extra_tablenav( $which ) {
		if ( $which === 'bottom' ) {
			return;
		}
		?>
		<div class="alignleft actions">
			<input type="text" name="filter_input"  id="filter-input" placeholder="Enter subject" value="<?php echo esc_html( isset( $_GET['subject'] ) ? $_GET['subject'] : '' ); ?>">
			<button type="submit" name="filter_button" id="filter-button" class="button">Filter</button>
		</div>
		<?php
		if ( isset( $_GET['status'] ) && in_array( $_GET['status'], $this->failed_statuses() ) ) {
		?>
		<?php wp_nonce_field( 'empty_delete_nonce', '_empty_delete' ); ?>
		<div class="alignleft actions">
			<button type="submit" name="empty_delete" class="button confirm-action" value="empty_only">Empty Only</button>
		</div>
		<div class="alignleft actions">
			<button type="submit" name="empty_delete" class="button confirm-action" value="delete_users">Empty & Delete Users</button>
		</div>
		<?php
		}
	}
    
	/**
	 * Process the bulk actions
	 */
	public function process_bulk_action() {
		if ( ! isset( $_POST['empty_delete'] ) || ! isset( $_POST['_empty_delete'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_empty_delete'] ), 'empty_delete_nonce' ) ) {
			return;
		}
		
		if ( ! isset( $_GET['status'] ) || ! in_array( $_GET['status'], $this->failed_statuses() ) ) {
			return;
		}
		
	    $query = new Amazon_SES_Email_Log_Query( [ 'status' => sanitize_text_field( $_GET['status'] ) ] );
		$users_deleted = 0;
		if( $count = $query->count() ) {
		    foreach( $query->get_emails() as $email ){
		        
		        $email_logger = new Amazon_SES_Email_Log( absint( $email->ID ) );
                $email_logger->delete();
                
                if( $_POST['empty_delete'] === 'delete_users' ){
		            if( $user = get_user_by( 'email', sanitize_text_field( $email->email ) ) ){
	                    require_once( ABSPATH.'wp-admin/includes/user.php' );
                        wp_delete_user( $user->ID, get_current_user_id() );
                        ++$users_deleted;
		            }
                }
                
		    }
		}
		
        $class  = 'notice notice-success is-dismissible';
        $notice = $this->maybe_plural( $count, 'email and ', 'emails and ' ) . $this->maybe_plural( $users_deleted, 'user', 'users' ) . " permanently deleted.";
		printf( '<div class="%s"><p>%s</p></div>', $class, $notice );
	}
    
    /**
    * Method for name column
    *
    * @param array $item An array of DB data
    *
    * @return string
    */
    function column_action( $item ) {
        $nonce = wp_create_nonce( 'v_email_list' );
        $link = "<a href='javascript:void(0)' class='list-action' data-action='view'>View</a>";
        $link .= " | <a href='javascript:void(0)' class='list-action' data-action='delete'>Delete</a>";
        return "<div data-id='{$item->ID}' data-nonce='{$nonce}'>$link</div>";
    }
    
    /**
    * Render a column when no column specific method exists.
    *
    * @param array $item
    * @param string $column_name
    *
    * @return mixed
    */
    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
        case 'date':
            return "<span class='local-time'>{$item->$column_name}</span>";
            
        case 'created_by':
            if ( ! $user = get_userdata( absint( $item->$column_name ) ) ){
                return $item->$column_name;
            }
            return esc_html( $user->display_name );
            
        case 'status':
            $value = $item->$column_name;
            $status = "{$this->email_logger()->email_status()[ $value ]}";
            if ( $value === 'clicked' ) {
                $status .= " ({$item->click_count})";
            } else if ( $value === 'opened' ) {
                $status .= " ({$item->open_count})";
            }
            return esc_html( $status );
            
        case 'events':
            $events = maybe_unserialize( $item->$column_name );
            if ( empty( $events ) )
            return $item->$column_name;
            $text = '';
            foreach( $events as $event ) {
                $text .= "<p>";
                foreach( $event as $key => $e ) {
                    $text .= "<strong>Event: $key</strong></br>";
                    foreach( $e as $key => $v ) {
                        $text .= "$key: $v </br>";
                    }
                }
                $text .= "</p>";
            }
            return $text;
            
        default:
            return $item->$column_name;
        }
    }

    /**
    * Maybe pluralize word
    *
    * @param string|int $amount
    * @param string $singular Singular form of the word
    * @param string $plural Plural form of the word
    * @param string $custom Character to add in front if there is no amount
    *
    * @return string
    */
    function maybe_plural( $amount, $singular, $plural, $custom = 0 ) {
        if ( $amount == 1 ) {
            return "$amount $singular";
        } else if ( ! $amount ) {
            return "$custom $plural";
        }
        return "$amount $plural";
    }

}