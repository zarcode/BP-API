<?php

class BP_API_Activity extends WP_REST_Controller {


	public function __construct() {

	}


	/**
	 * register_routes function.
	 *
	 * Register the routes for the objects of the controller.
	 * 
	 * @access public
	 * @return void
	 */
	public function register_routes() {
	
		register_rest_route( BP_API_SLUG, '/activity', array(
			'methods'         => WP_REST_Server::READABLE,
			'callback'        => array( $this, 'get_items' ),
			'permission_callback' => array( $this, 'bp_activity_permission' )
		) );
		register_rest_route( BP_API_SLUG, '/activity/(?P<id>\d+)', array(
			'methods'         => WP_REST_Server::READABLE,
			'callback'        => array( $this, 'get_item' ),
			'permission_callback' => array( $this, 'bp_activity_permission' ),
		) );
		
	}


	/**
	 * get_items function.
	 * 
	 * @access public
	 * @param array $filter (default: array())
	 * @return void
	 */
	public function get_items( $filter = array() ) {

		$response = $this->get_activity( $filter['filter'] );

		return $response;

	}

	
	/**
	 * get_item function.
	 * 
	 * @access public
	 * @param mixed $request
	 * @return void
	 */
	public function get_item( $request ) {

		$response = 'a single activity item';

		return $response;

	}


	
	/**
	 * get_activity function.
	 * 
	 * @access public
	 * @param mixed $filter
	 * @return void
	 */
	public function get_activity( $filter ) {

		$args = $filter;

		if ( bp_has_activities( $args ) ) {

			while ( bp_activities() ) {

				bp_the_activity();

				$activity = array(
					'avatar'	 		=> bp_core_fetch_avatar( array( 'html' => false, 'item_id' => bp_get_activity_id() ) ),
					'action'	 		=> bp_get_activity_action(),
					'content'	  		=> bp_get_activity_content_body(),
					'activity_id'		=> bp_get_activity_id(),
					'activity_username' => bp_core_get_username( bp_get_activity_user_id() ),
					'user_id'	 		=> bp_get_activity_user_id(),
					'comment_count'  	=> bp_activity_get_comment_count(),
					'can_comment'	 	=> bp_activity_can_comment(),
					'can_favorite'	  	=> bp_activity_can_favorite(),
					'is_favorite'	 	=> bp_get_activity_is_favorite(),
					'can_delete'  		=> bp_activity_user_can_delete()
				);

				$activity = apply_filters( 'bp_json_prepare_activity', $activity );

				$activities[] =	 $activity;

			}

			$data = array(
				'activity' => $activities,
				'has_more_items' => bp_activity_has_more_items()
			);

			$data = apply_filters( 'bp_json_prepare_activities', $data );

		} else {
			return new WP_Error( 'bp_json_activity', __( 'No Activity Found.', BP_API_PLUGIN_SLUG ), array( 'status' => 200 ) );
		}

		$response = new WP_REST_Response();
		$response->set_data( $data );
		$response = rest_ensure_response( $response );

		return $response;

	}

	
	/**
	 * add_activity function.
	 * 
	 * @access public
	 * @return void
	 */
	public function add_activity() {

		//add activity code here

	}

	
	/**
	 * edit_activity function.
	 * 
	 * @access public
	 * @return void
	 */
	public function edit_activity() {

		//edit activity code here

	}

	
	/**
	 * remove_activity function.
	 * 
	 * @access public
	 * @return void
	 */
	public function remove_activity() {

		//remove activity code here

	}
	
	
	/**
	 * bp_activity_permission function.
	 *
	 * allow permission to access data
	 * 
	 * @access public
	 * @return void
	 */
	public function bp_activity_permission() {
	
		$response = apply_filters( 'bp_activity_permission', true );
		
		return $response;
	
	}

	

}
