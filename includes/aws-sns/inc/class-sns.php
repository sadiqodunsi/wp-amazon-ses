<?php

namespace AWS_SNS_EMAIL_TRACKING;

use Aws\Sns\Message;
use Aws\Sns\MessageValidator;
use Aws\Sns\Exception\InvalidSnsMessageException;
use Amazon_SES_Email_Log;

class SNS {

    /**
     * The single instance of the class.
     *
     * @var SNS
     */
	private static $instance;

    /**
     * Message from POST
     * 
     * @var array
     */
	private $message;

    /**
     * The event object
     * 
     * @var object
     */
	private $event;

    /**
     * The event ID
     * 
     * @var string
     */
	private $event_id;

    /**
     * The event type
     * 
     * @var string
     */
	private $event_type;

    /**
     * Event log
     * 
     * @var string
     */
	private $event_log = [];

    /**
     * Amazon_SES_Email_Log object
     * 
     * @var object
     */
	private $email;

	/** 
     * Singleton instance
     */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new static();
		}
		return self::$instance;
	}

	/**
	 * Process the event
	 */
	public function process() {
        $this->message = Message::fromRawPostData();
        $this->validate();
        $this->handler();
	}

	/**
	 * Validate the message and log errors if invalid.
	 */
	private function validate() {
	    
	    $validator = new MessageValidator();
	    
        try {
            
            $validator->validate( $this->message );
            
        } catch ( InvalidSnsMessageException $e) {
            
            // Pretend we're not here if the message is invalid.
            http_response_code( 404 );
            error_log( 'SNS Message Validation Error: ' . $e->getMessage() );
            die();
            
        }

	}

	/**
	 * Handle the message - Confirm new subscription or update event.
	 */
	private function handler() {
	    
	    // Types of subscriptions
	    $subscriptions = ['SubscriptionConfirmation', 'UnsubscribeConfirmation'];
	    
        if ( in_array( $this->message['Type'], $subscriptions ) ) {
            
            // Confirm the subscription by sending a GET request to SubscribeURL
            file_get_contents( $this->message['SubscribeURL'] );
            
        } else if ( $this->message['Type'] === 'Notification' ) {
            
            $this->event = json_decode( $this->message['Message'] );
            
            $this->event_id = $this->event->mail->messageId;
            
            $this->event_type = $this->event->eventType;
            
            $this->get_email();
            
            // Check if we have already logged the event, if so, die.
            $this->prevent_double_entry();
            
            if( $this->event_type === 'Delivery' ){
                
                $this->DeliveryEvent();
                
            } else if( $this->event_type === 'Open' ){
                
                $this->OpenEvent();
                
            } else if( $this->event_type === 'Click' ){
                
                $this->ClickEvent();
                
            } else if( $this->event_type === 'Bounce' ){
                
                $this->BounceEvent();
                
            } else if( $this->event_type === 'Complaint' ){
                
                $this->ComplaintEvent();
                
            } else if( $this->event_type === 'Reject' ){
                
                $this->RejectEvent();
                
            } else if( $this->event_type === 'Rendering Failure' ){
                
                $this->FailEvent();
                
            }
            
            $this->LogEvent();
            
        }
        
	}

	/**
	 * Get the email from log by event ID
	 */
	private function get_email() {
	    
        $this->email = new Amazon_SES_Email_Log();
        $this->email->get_email_by_event_id( $this->event_id );

	}
	
	/**
	 * Prevent double entry
	 */
	private function prevent_double_entry() {
	    
	    $eventType = $this->event_type;
	    
	    if( $eventType === 'Reject' || $eventType === 'Rendering Failure' )
	    return;
	    
	    $events = $this->email->get_events();
	    
	    $event_timestamps = [];
	    
	    foreach( $events as $event ) {
            if( isset( $event[ $eventType ][ 'Timestamp' ] ) ) {
                $event_timestamps[] = $event[ $eventType ][ 'Timestamp' ];
            }
	    }
        
        $eventType = strtolower( $eventType );
        $timestamp = $this->event->{$eventType}->timestamp;
                
        if( in_array( $timestamp, $event_timestamps ) ) {
            http_response_code( 200 );
            die();
        }
        
	}

	/**
	 * Log delivered event
	 */
	private function DeliveryEvent() {
	    
        $this->email->update( [ 'status' => 'delivered' ] );
        $this->event_log = [ 
            'Delivery' => [ 
                'Timestamp' =>  $this->event->delivery->timestamp,
                'Recipients' => implode( ', ', $this->event->delivery->recipients )
            ] 
        ];

	}

	/**
	 * Log open event
	 */
	private function OpenEvent() {
	    
        $count = $this->email->get_open_count();
        $status = $this->email->get_status();
        if( $status === 'opened' || $status === 'clicked' ){
            $this->email->update( [ 'open_count' => ++$count ] );
        } else {
            $this->email->update( [ 'status' => 'opened', 'open_count' => ++$count ] );
        }
        $this->event_log = [ 
            'Open' => [ 
                'Timestamp' =>  $this->event->open->timestamp,
                'ipAddress' =>  $this->event->open->ipAddress
            ] 
        ];
        
	}

	/**
	 * Log click event
	 */
	private function ClickEvent() {
	    
	    $link = $this->event->click->link;
	    
        $count = $this->email->get_click_count();
        if( $this->email->get_status() === 'clicked' ){
            $this->email->update( [ 'click_count' => ++$count ] );
        } else {
            $this->email->update( [ 'status' => 'clicked', 'click_count' => ++$count ] );
        }

        $this->event_log = [ 
            'Click' => [ 
                'Timestamp' =>  $this->event->click->timestamp,
                'ipAddress' =>  $this->event->click->ipAddress,
                'Link'      =>  $link
            ] 
        ];

	}

	/**
	 * Log bounce event
	 */
	private function BounceEvent() {
	    
	    if( $this->event->bounce->bounceType === 'Permanent' ){
	        
            $this->email->update( [ 'status' => 'hard_bounce' ] );
        
	    } else {
	        
	        $this->email->update( [ 'status' => 'soft_bounce' ] );
	        
	    }
	    
        $this->event_log = [ 
            'Bounce' => [ 
                'Timestamp' =>  $this->event->bounce->timestamp,
                'BounceType' =>  $this->event->bounce->bounceType,
                'BounceSubType' =>  $this->event->bounce->bounceSubType
            ] 
        ];
        foreach( $this->event->bounce->bouncedRecipients as $bounce ){
            $this->event_log['Bounce']['EmailAddress'] = $bounce->emailAddress;
            $this->event_log['Bounce']['Action'] = $bounce->action;
            $this->event_log['Bounce']['DiagnosticCode'] = $bounce->diagnosticCode;
        }
    
	}

	/**
	 * Log complaint event
	 */
	private function ComplaintEvent() {
	    
        $this->email->update( [ 'status' => 'complaint' ] );
        $this->event_log = [ 
            'Complaint' => [ 
                'Timestamp' =>  $this->event->complaint->timestamp,
                'Feedback' =>  $this->event->complaint->complaintFeedbackType,
                'FeedbackSubType' =>  $this->event->complaint->complaintSubType,
            ] 
        ];
        foreach( $this->event->complaint->complainedRecipients as $complaint ){
            $this->event_log['Complaint']['EmailAddress'] = $complaint->emailAddress;
        }
        
	}

	/**
	 * Log reject event
	 */
	private function RejectEvent() {
	    
        $this->email->update( [ 'status' => 'rejected' ] );
        $this->event_log = [ 
            'Reject' => [ 
                'Reason' =>  $this->event->reject->reason
            ] 
        ];

	}

	/**
	 * Log fail event
	 */
	private function FailEvent() {
	    
        $this->email->update( [ 'status' => 'failed' ] );
        $this->event_log = [ 
            'Fail' => [ 
                'ErrorMessage' =>  $this->event->failure->errorMessage,
                'TemplateName' =>  $this->event->failure->templateName,
            ] 
        ];

	}

	/**
	 * Log events
	 */
	private function LogEvent() {
	    
        $event = $this->email->get_events();
        $event[] = $this->event_log;
        $this->email->update( [ 'events' => maybe_serialize( $event ) ] );

	}
}