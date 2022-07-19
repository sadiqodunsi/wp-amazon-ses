<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WP_AMAZON_SES {

	/**
     * The single instance of the class.
     *
     * @var WP_AMAZON_SES
     */
	static $instance;

    /**
     * Amazon_SES_List_Table instance.
     * 
     * @var Amazon_SES_List_Table
     */
	private $ses_list_table;
	
	/** 
     * Singleton instance
     */
    public static function get_instance() {
	    if ( ! isset( self::$instance ) ){
		    self::$instance = new self();
	    }
	    return self::$instance;
    }
	
    /**
     * Class constructor
     */
    public function __construct() {
        $this->includes();
        $this->hook();
    }
	
    /**
     * Includes
     */
    private function includes() {
		require_once WP_AMAZON_SES_BASE_DIR . 'includes/class-abstract-db.php';
        require_once WP_AMAZON_SES_BASE_DIR . 'includes/class-email-log.php';
        require_once WP_AMAZON_SES_BASE_DIR . 'includes/class-email-query.php';
		require_once WP_AMAZON_SES_BASE_DIR . 'includes/class-email-extractor.php';
        require_once WP_AMAZON_SES_BASE_DIR . 'includes/aws-ses/aws-ses.php';
        require_once WP_AMAZON_SES_BASE_DIR . 'includes/aws-sns/aws-sns.php';
    }
	
    /**
     * Hooks
     */
    private function hook() {
        register_activation_hook( WP_AMAZON_SES_PLUGIN_FILE, [ $this, 'activate' ] );
        add_action( 'admin_notices', [ $this, 'notice' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_filter( 'set-screen-option', [ __CLASS__, 'set_screen' ], 10, 3 );
        add_action( 'wp_ajax_v_email_list', [ $this, 'list_action' ] );
        add_filter( 'wp_mail', [ $this, 'log_wp_mail' ], PHP_INT_MAX );
        add_action( 'wp_mail_failed', [ $this, 'log_failed_mail' ] );
        add_filter( 'wp_mail_from_name', [ $this, 'from_name' ], PHP_INT_MAX );
        add_filter( 'wp_mail_from', [ $this, 'from_email' ], PHP_INT_MAX );
    }
    
    /**
     * Create email log table
     */
    public function activate() {
		Amazon_SES_Email_Log::create_table();
    }
    
    /**
     * Show notice if neccessary constants are not set
     */
    public function notice() {
        $class  = 'error';
        $notice = "";
        if ( ! defined( 'AWS_SES_WP_MAIL_KEY' ) ) {
            $notice .= "AWS_SES_WP_MAIL_KEY - ";
        }
        if ( ! defined( 'AWS_SES_WP_MAIL_SECRET' ) ) {
            $notice .= "AWS_SES_WP_MAIL_SECRET - ";
        }
        if ( ! defined( 'AWS_SES_WP_MAIL_REGION' ) ) {
            $notice .= "AWS_SES_WP_MAIL_REGION - ";
        }
        if( $notice ){
            $notice .= "must be <a href='https://github.com/sadiqodunsi/wp-amazon-ses.git' target='_blank'>defined</a> in wp-config file before you can send email via Amazon SES.";
            printf( '<div class="%s"><p>%s</p></div>', $class, $notice );
        }
    }
    
	/**
	 * Admin scripts and styles.
	 */
	public function enqueue() {
		if ( ! isset( $_GET['page'] ) || WP_AMAZON_SES_ADMIN_PAGE !== $_GET['page'] ) {
		    return;
		}
        $suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
        wp_enqueue_script( 'email-scripts', WP_AMAZON_SES_BASE_URL . "assets/scripts$suffix.js", ['jquery'], WP_AMAZON_SES_VERSION, true );
        wp_enqueue_style( 'email-styles', WP_AMAZON_SES_BASE_URL . "assets/styles$suffix.css", [], WP_AMAZON_SES_VERSION );
	}

    /**
    * Add menu to WordPress admin
    */
    public function admin_menu() {
        $hook = add_menu_page(
            'Amazon SES Log',          // Page title
            'Amazon SES Log',          // Menu title
            'edit_pages',              // Capability
            WP_AMAZON_SES_ADMIN_PAGE,  // Menu slug
            [$this, 'email_log'],      // Callback function
            'dashicons-email',         // Icon
            apply_filters('aws_ses_wp_admin_menu_position', 7)  // Position
        );
        add_action( "load-$hook", [$this, 'screen_option'] );
    }

    /**
    * Screen options
    */
    public function screen_option() {
	    $option = 'per_page';
	    $args   = [
		    'label'   => 'Email log',
		    'default' => 20,
		    'option'  => 'emails_per_page'
	    ];
	    add_screen_option( $option, $args );
        if ( ! class_exists( 'Amazon_SES_List_Table' ) ) {
            require_once WP_AMAZON_SES_BASE_DIR . 'includes/class-amazon-ses-list-table.php';
        }
	    $this->ses_list_table = new Amazon_SES_List_Table();
    }
    
    /**
    * Saves screen option value
    */
    public static function set_screen( $status, $option, $value ) {
	    return $value;
    }

    /*
    * Email log content
    */
    public function email_log() {
        $list = $this->ses_list_table;
        $list->prepare_items();
        $max = 0;
        $sent = 0;
        $available = 0;
        $send_rate = 0;
        // If neccessary constants are not defined, AWS SES will not be loaded
        if( class_exists('AWS_SES_WP_Mail\Raw_SES') ){
            $ses = new AWS_SES_WP_Mail\Raw_SES();
            $send_quota = $ses->get_send_quota();
            $max = $send_quota['max'];
            $sent = $send_quota['sent'];
            $available = round( ( $sent / $max ) * 100, 2 );
            $send_rate = $send_quota['send_rate'];
        }
        $args = [];
        if( isset( $_GET['subject'] ) ) {
            $args['subject'] = $_GET['subject'];
        }
        $query = new Amazon_SES_Email_Log_Query( $args );
        $db_total = $query->count();
        $short_total = $this->shorten_number( $db_total );
	    $statuses = ['hard_bounce','failed','complaint','clicked','opened','delivered'];
        $statuses = apply_filters( 'aws_ses_wp_admin_list_statistics', $statuses );
        ?>
            <div class="wrap email-log">
                <h2>Email log</h2>
                <p>Max send rate: <?php echo esc_html( $send_rate ); ?> emails per second</p>
                <div class="flex center">
                    <span>
                        <div><?php echo esc_html( "$sent / " . $this->shorten_number( $max ) . " used (24h)" ); ?></div>
                        <div class="bold"><?php echo esc_html( $available ); ?>%</div>
                    </span>
                    <?php
                    foreach ( $statuses as $key => $status ) {
                        $args['status'] = $status;
                        $query = new Amazon_SES_Email_Log_Query( $args );
                        $count = $query->count();
                        if( $status === 'hard_bounce' ){
                            $status = str_replace( '_', ' ', $status );
                        } else if( $status === 'clicked' ){
                            $clicked = $count;
                        } else if( $status === 'opened' ){
                            $opened = $count + $clicked;
                            $count = $opened;
                        } else if( $status === 'delivered' ){
                            $count = $count + $opened + $clicked;
                        }
                        $percentage = $db_total ? round( ( $count / $db_total ) * 100, 2 ) : 0;
                        ?>
                        <span>
                            <div><?php echo esc_html( $this->shorten_number( $count ) . " / $short_total $status" ); ?></div>
                            <div class="bold"><?php echo esc_html( $percentage ); ?>%</div>
                        </span>
                        <?php
                    }
                    ?>
                </div>
                <form method="post" action="" id="amazon-ses-log-form">
                <?php
                    $list->views();
                    $list->search_box('Search', 'search');
                    $list->display(); 
                ?>
                </form>
                <div class="email-details popup-overlay popup-close">
		            <div class="popup-body">
		                <div class="close popup-close">&times;</div>
		            </div>
                </div>
            </div>
        <?php
    }
    
	/**
	 * Email list action
	 */    
    public function list_action() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'v_email_list' ) )
        wp_send_json_error( 'Invalid request.' );
        
        if ( ! current_user_can( 'edit_pages' ) )
        wp_send_json_error( 'You do not have the permission to do this.' );
        
        if ( ! isset( $_POST['id'] ) || empty( $id = absint( $_POST['id'] ) ) )
        wp_send_json_error( 'Unknown request.' );
        
        if ( ! isset( $_POST['todo'] ) || empty( $_POST['todo'] ) )
        wp_send_json_error( 'Unknown request.' );

        $email_logger = new Amazon_SES_Email_Log( $id );
        
        if ( $_POST['todo'] === 'view' ) {
            wp_send_json_success( [ 'view' => $email_logger->get_content() ] );
        }
        
        if ( $_POST['todo'] === 'delete' ) {
            $email_logger->delete();
            wp_send_json_success( [ 'delete' => 1 ] );
        }

        wp_send_json_error( 'An error occurred.' );
    }

    /**
     * Filter to log email to database.
     *
     * @param array $mail_array
     * @global $sdq_current_email_log_id
     * @return array $mail_array
     */
    public function log_wp_mail( $mail_array ) {
        $mail = new Amazon_SES_Email_Extractor();
        // Save the log ID to global variable for later use
        global $sdq_current_email_log_id;
        $sdq_current_email_log_id = $mail->log( $mail_array );
        return $mail_array;
    }

    /**
     * Filter to update status of failed emails
     *
     * @param WP_Error $wperror
     * @global $sdq_current_email_log_id
     */
    public function log_failed_mail( $wperror ) {
        global $sdq_current_email_log_id;
        if ( ! isset( $sdq_current_email_log_id ) )
        return;
        $email_logger = new Amazon_SES_Email_Log( $sdq_current_email_log_id );
        $email_logger->failed( $wperror->get_error_message() );
    }
	
    /**
     * Filter to use site name as default from name
     * 
     * @param string $wp_name From name
     * @return string From name
     */
    public function from_name( $wp_name ) {
        if ( $wp_name !== 'WordPress' ) {
            return $wp_name;
        }
		$from_name = get_bloginfo('name');
		return ! empty( $from_name ) ? $from_name : $wp_name;
    }
	
    /**
     * Filter to use site email as default from email
     * 
     * @param string $wp_email From email
     * @return string From email
     */
    public function from_email( $wp_email ) {
        if ( strpos( $wp_email, 'wordpress' ) === false ) {
            return $wp_email;
        }
		$from_email = get_bloginfo('admin_email');
		return ! empty( $from_email ) ? $from_email : $wp_email;
    }

    /**
     * Shorten number to e.g. 12.2K instead of 12200
     * 
     * @param int|string $num The number to shorten
     * @return string The shortened number
     */
    private function shorten_number( $num ) {
        $units = ['', 'K', 'M', 'B', 'T'];
        for ($i = 0; $num >= 1000; $i++) {
            $num /= 1000;
        }
        return round($num, 1) . $units[$i];
    }

}