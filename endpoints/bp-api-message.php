<?php

class BP_API_Messages extends WP_REST_Controller {
    
    /**
    * Register the routes for the objects of the controller.
    */
	public function register_routes() { 
		register_rest_route( BP_API_SLUG, '/messages', array(
			array(
				'methods'         => WP_REST_Server::READABLE,
				'callback'        => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'bp_messages_permission' )
			),
            array(
                'methods'         => WP_REST_Server::CREATABLE,
                'callback'        => array( $this, 'create_item' ),
                'permission_callback' => array( $this, 'bp_messages_permission' ),
                'args'            => $this->get_endpoint_args_for_item_schema( true ),
            )
		) );
        register_rest_route( BP_API_SLUG, '/messages/(?P<id>\d+)', array(         
            array(
                'methods'         => WP_REST_Server::READABLE,
                'callback'        => array( $this, 'get_item' ),
                'permission_callback' => array( $this, 'bp_messages_permission' ),
                'args' => array(
                    'id' => array(
                        'validate_callback' => 'is_numeric'
                    ),
                )
		    ),
            array(
                'methods'  => WP_REST_Server::DELETABLE,
                'callback' => array( $this, 'delete_item' ),
                'permission_callback' => array( $this, 'bp_messages_permission' ),
                'args' => array(
                    'id' => array(
                        'validate_callback' => 'is_numeric'
                    ),
                )
            )
        ) );
	}
    
    
	/**
	 * Create a single message
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function create_item( $request ) {

		if ( ! empty( $request['id'] ) ) {
			return new WP_Error( 'bp_json_message_exists', __( 'Cannot create existing message.', BP_API_PLUGIN_SLUG ), array( 'status' => 400 ) );
		}

		$message = $this->prepare_item_for_database( $request );
        
        $message_id = messages_new_message( array(
            'sender_id'  => ($message->sender_id)?$message->sender_id:bp_loggedin_user_id(),
            'thread_id'  => $message->thread_id,   // false for a new message, thread id for a reply to a thread.
            'recipients' => $message->recipients, // Can be an array of usernames, user_ids or mixed.
            'subject'    => $message->subject,
            'content'    => $message->content,
            'date_sent'  => ($message->date_sent)?$message->date_sent:bp_core_current_time(),   
        ));

        if ( ! $message_id ) {
            return new WP_Error( 'bp_json_user_create', __( 'Error creating new user.', BP_API_PLUGIN_SLUG ), array( 'status' => 500 ) );
        }
		

		$this->update_additional_fields_for_object( $user, $request );

		/**
		 * Fires after a user is created via the REST API
		 *
		 * @param object $user Data used to create user (not a WP_User object)
		 * @param WP_REST_Request $request Request object.
		 * @param bool $bool A boolean that is false.
		 */
		do_action( 'rest_insert_user', $user, $request, false );

		$response = $this->get_item( array(
			'id'      => $message_id,
//			'context' => 'edit',
		));
		$response = rest_ensure_response( $response );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( '/users/' . $message_id ) );

		return $response;
	}
        
	/**
	 * Get all messages
	 *
	 * @param WP_REST_Request $request
	 * @return array|WP_Error
	 */
	public function get_items( $request ) {
		global $messages_template;
		$data = array();
		if ( bp_has_message_threads( bp_ajax_querystring( 'messages' ) ) ) {
			while ( bp_message_threads() ) : bp_message_thread();
				$single_msg=array(
				    'id'                => $messages_template->thread->thread_id,
				    'read'              => ( bp_message_thread_has_unread())? "unread": "read",
				    'total_thread'      => bp_get_message_thread_total_count( $messages_template->thread->thread_id ),
				    'unread_thread'     => bp_get_message_thread_unread_count( $messages_template->thread->thread_id ),
				    'avatar'            => bp_core_fetch_avatar( array( 'item_id' => $messages_template->thread->last_sender_id,'width' => 25, 'height' => 25, 'html' => false ) ),
				    'from'              => bp_core_get_username($messages_template->thread->last_sender_id),
				    'from_id'           => $messages_template->thread->last_sender_id,
				    'last_message_date' => $messages_template->thread->last_message_date,
				    'subject'           => bp_get_message_thread_subject(),
				    'excerpt'           => bp_get_message_thread_excerpt(),
				    'content'           => bp_get_message_thread_content()
                );
                $links = array(
                    'self' => array(
                        'href' => rest_url( sprintf( BP_API_SLUG.'/messages/%d', $messages_template->thread->thread_id ) ),
                    ),
                    'collection' => array(
                        'href' => rest_url( BP_API_SLUG.'/messages/' ),
                    )
                );
				$single_msg['_links']=$links;
				$data[]=$single_msg;
			endwhile;
        } else {
            return new WP_Error( 'bp_json_messages', __( 'No Messages Found.', BP_API_PLUGIN_SLUG ), array( 'status' => 200 ) );
        }
        
        $data = apply_filters( 'bp_json_prepare_messages', $data );

		return new WP_REST_Response( $data, 200 );
	}
				
	/**
	 * Get a specific message
	 *
	 * @param WP_REST_Request $request
	 * @return array|WP_Error
	 */
	public function get_item( $request ) {
		global $thread_template;
		if(bp_thread_has_messages(array('thread_id'=> $request['id']))) {
            $data = array();
			$data['id'] = $request['id'];
			$data['subject'] = bp_get_the_thread_subject();
			if ( bp_get_thread_recipients_count() <= 1 ) {
                $data['thread_title'] = __('You are alone in this conversation.', BP_API_PLUGIN_SLUG );
            } elseif ( bp_get_max_thread_recipients_to_list() <= bp_get_thread_recipients_count() ) {
                $data['thread_title'] = sprintf( __('Conversation between %s recipients.', BP_API_PLUGIN_SLUG ), number_format_i18n( bp_get_thread_recipients_count()) );
            } else {
                foreach( (array) $thread_template->thread->recipients as $recipient ) {
                    if ( (int) $recipient->user_id !== bp_loggedin_user_id() ) {
                        $recipient_link = bp_core_get_user_displayname( $recipient->user_id );

                        if ( empty( $recipient_link ) ) {
                            $recipient_link = __( 'Deleted User', BP_API_PLUGIN_SLUG );
                        }

                        $recipient_links[] = $recipient_link;
                    }
                }
                $data['thread_title'] = sprintf( __('Conversation between %s and you.' ), implode(',', $recipient_links) );
                while ( bp_thread_messages() ) : bp_thread_the_message();

                    $data['thread_msg'][$thread_template->message->id] = (array)$thread_template->message;
                    $data['thread_msg'][$thread_template->message->id]['sender_avatar'] = bp_core_fetch_avatar( array( 'item_id' => $thread_template->message->sender_id,'width' => 25, 'height' => 25, 'html' => false ) );
                    $data['thread_msg'][$thread_template->message->id]['sender_name'] = bp_get_the_thread_message_sender_name();

                endwhile;
            }
            
        } else {
            return new WP_Error( 'bp_json_message', __( 'Message Not Found.', BP_API_PLUGIN_SLUG ), array( 'status' => 404 ) );
        }
        
		return new WP_REST_Response( $data, 200 );	
    }
    
    /**
	 * Delete a single message
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function delete_item( $request ) {
        $id = (int) $request['id'];
        
        if ( ! bp_thread_has_messages(array('thread_id'=> $id)) ) {
			return new WP_Error( 'bp_json_message', __( 'Message Not Found.', BP_API_PLUGIN_SLUG ), array( 'status' => 404 ) );
		}
        
        $deleted = messages_delete_thread($id);
        
		if ( ! $deleted ) {
			return new WP_Error( 'bp_json_message_cannot_be_deleted', __( 'The message cannot be deleted.', BP_API_PLUGIN_SLUG ), array( 'status' => 500 ) );
		}
        
		return new WP_Error( 'bp_json_message_deleted', __( 'Message deleted successfully.', BP_API_PLUGIN_SLUG ), array( 'status' => 200 ) );
	}
    
    /**
	 * bp_messages_permission function.
	 *
	 * allow permission to access data
	 * 
	 * @access public
	 * @return void
	 */
	public function bp_messages_permission() {
	
		$response = apply_filters( 'bp_messages_permission', true );
		
		return $response;
	
	}
    
	/**
	 * Prepare a single message for create or update
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return object $message User object.
	 */
	protected function prepare_item_for_database( $request ) {
		$message = new stdClass;  

		// required arguments.
		if ( isset( $request['recipients'] ) ) {
			$message->recipients = $request['recipients'];
		}

		// optional arguments.
        if ( isset( $request['sender_id'] ) ) {
			$message->sender_id = absint($request['sender_id']);
		}
        if ( isset( $request['thread_id'] ) ) {
			$message->thread_id = absint($request['thread_id']);
		}
		if ( isset( $request['subject'] ) ) {
			$message->subject = $request['subject'];
		}
		if ( isset( $request['content'] ) ) {
			$message->content = $request['content'];
		}
		if ( isset( $request['date_sent'] ) ) {
			$message->date_sent = $request['date_sent'];
		}

		/**
		 * Filter user data before inserting user via REST API
		 *
		 * @param object $message Message object.
		 * @param WP_REST_Request $request Request object.
		 */
		return apply_filters( 'bp_json_pre_insert_message', $message, $request );
	}

}