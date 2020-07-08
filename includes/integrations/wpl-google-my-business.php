<?php

/**
 * Class WPL_Google_My_Business.
 *
 * Class used to automate posting to Google My Business.
 */
class WPL_Google_My_Business {


	private static $instance = null;

	/**
	 * Class constructor.
	 */
	private function __construct() {
		add_action( 'wp_ajax_wpl_gmb_set_initial_tokens', [ $this, 'wpl_gmb_set_initial_tokens' ] );
		add_action( 'wp_ajax_wpl_clear_gmb_settings', [ $this, 'wpl_clear_gmb_settings' ] );
		add_action( 'wp_ajax_wpl_update_gmb_preferences', [ $this, 'wpl_update_gmb_preferences' ] );
		add_action( 'wp_ajax_wpl_reset_next_post_time_request', [ $this, 'wpl_reset_next_post_time_request' ] );

		add_action( 'wp_ajax_wpl_post_next_scheduled_now', [ $this, 'wpl_post_next_scheduled_now' ] );
		add_action( 'wp_ajax_wpl_update_scheduled_posts', [ $this, 'wpl_update_scheduled_posts' ] );
		add_action( 'wp_ajax_wpl_clear_scheduled_posts', [ $this, 'wpl_clear_scheduled_posts' ] );
		add_action( 'wp_ajax_wpl_update_exclusion_list', [ $this, 'wpl_update_exclusion_list' ] );
		add_action( 'wp_ajax_wpl_clear_last_post_status', [ $this, 'wpl_clear_last_post_status' ] );

		// Set hook for cron event and custom schedules.
		add_filter( 'cron_schedules', [ $this, 'wpl_gmb_event_schedules' ], 10, 2 );
		add_action( 'wp_listings_gmb_auto_post', [ $this, 'wpl_gmb_scheduled_post' ] );
	}

	// The object is created from within the class itself
	// only if the class has no instance.
	public static function getInstance() {
		if ( self::$instance == null ) {
			self::$instance = new WPL_Google_My_Business();
		}
		return self::$instance;
	}


	/**
	 * Get_GMB_Settings_Options.
	 * Getter for GMB options/settings, also sets default values.
	 */
	public function wpl_get_gmb_settings_options() {
		$options  = get_option( 'wp_listings_google_my_business_options', [] );
		$defaults = [
			'access_token'     => '',
			'refresh_token'    => '',
			'locations'        => [],
			'posting_settings' => [
				'posting_frequency'        => 'weekly',
				'empty_schedule_auto_post' => 0,
				'scheduled_posts'          => [],
				'excluded_posts'           => [],
			],
			'posting_defaults' => [
				'default_link'             => '',
				'default_link_override'    => 0,
				'default_summary'          => '',
				'default_summary_override' => 0,
				'default_photo'            => '',
				'default_photo_override'   => 0,
			],
			'posting_logs'     => [
				'last_post_status_message' => '',
				'used_post_ids'            => [],
				'last_post_timestamp'      => '',
			],
		];
		return array_merge( $defaults, $options );

	}

	/**
	 * Update_Logs.
	 * Used to update posting_logs portion of wp_listings_google_my_business_options.
	 *
	 * @param  string $log_key - Included Error_Message/Used_Post_IDs/Last_Post_Timestamp.
	 * @param  mixed  $log_value - Value to be assigned to one of the 3 supported keys.
	 * @return void
	 */
	public function wpl_gmb_update_logs( $log_key, $log_value ) {
		$options = $this->wpl_get_gmb_settings_options();

		if ( 'last_post_status_message' === $log_key && is_string( $log_value ) ) {
			$options['posting_logs']['last_post_status_message'] = $log_value;
		}

		if ( 'used_post_ids' === $log_key && is_int( $log_value ) ) {
			array_push( $options['posting_logs']['used_post_ids'], $log_value );
			// Only keep track of 50 most recently posts.
			if ( count( $options['posting_logs']['used_post_ids'] ) > 50 ) {
				array_shift( $options['posting_logs']['used_post_ids'] );
			}
		}
		// Handle user_post_id getting wiped upon sharing all available listings.
		if ( 'used_post_ids' === $log_key && is_array( $log_value ) ) {
			$options['posting_logs']['used_post_ids'] = [];
		}

		if ( 'last_post_timestamp' === $log_key && is_string( $log_value ) ) {
			$options['posting_logs']['last_post_timestamp'] = $log_value;
		}

		update_option( 'wp_listings_google_my_business_options', $options );
	}

	/**
	 * Set_initial_tokens.
	 * Sets initial access and refresh tokens upon authenticating to Google.
	 */
	public function wpl_gmb_set_initial_tokens() {
		// User capability check.
		if ( ! current_user_can( 'manage_categories' ) ) {
			echo 'check permissions';
			wp_die();
		}

		// Validate and process request.
		if ( isset( $_POST['access_token'], $_POST['refresh_token'], $_POST['nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wpl_gmb_set_initial_tokens_nonce' ) ) {
			$refresh_token = sanitize_text_field( wp_unslash( $_POST['refresh_token'] ) );
			$access_token  = sanitize_text_field( wp_unslash( $_POST['access_token'] ) );
			$this->save_authentication_keys( $access_token, $refresh_token );
			$this->wpl_schedule_posting_event();
		}

		wp_die();
	}

	/**
	 * Get_Google_Access_Token.
	 * Gets current access token from transient data, if expired it will request a new code from Google using the refresh token.
	 */
	public function get_google_access_token() {

		$auth_transient = get_transient( 'wp_listings_google_my_business_auth_cache' );
		if ( $auth_transient ) {
			return $auth_transient;
		}

		$auth_settings = $this->wpl_get_gmb_settings_options();

		$response = wp_remote_get( 'https://hheqsfm21f.execute-api.us-west-2.amazonaws.com/v1/token-refresh?refresh_token=' . $auth_settings['refresh_token'] );
		if ( ! is_wp_error( $response ) ) {
			if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
				$jsondata         = json_decode( preg_replace( '/("\w+"):(\d+(\.\d+)?)/', '\\1:"\\2"', $response['body'] ), true );
				$new_access_token = sanitize_text_field( $jsondata['body']['access_token'] );
				$this->save_authentication_keys( $new_access_token );
				return $new_access_token;
			}
		}

		// Log error and return false.
		$this->wpl_gmb_update_logs( 'last_post_status_message', 'Failed - Required token missing.' );
		return false;
	}

	/**
	 * Save_Authentication_Keys.
	 * Saves access_token and optionally a refresh_token to the options table.
	 */
	public function save_authentication_keys( $access_token, $refresh_token = '' ) {
		$options = $this->wpl_get_gmb_settings_options();

		$options['access_token'] = $access_token;
		if ( '' !== $refresh_token ) {
			$options['refresh_token'] = $refresh_token;
		}

		update_option( 'wp_listings_google_my_business_options', $options );
		set_transient( 'wp_listings_google_my_business_auth_cache', $access_token, MINUTE_IN_SECONDS * 45 );
	}


	/**
	 * Update_GMB_Preferences.
	 * Set preferences via Ajax call from the Integrations settings page.
	 */
	public function wpl_update_gmb_preferences() {
		// User capability check.
		if ( ! current_user_can( 'manage_categories' ) ) {
			echo 'check permissions';
			wp_die();
		}

		// Validate and process request.
		if ( isset( $_POST['settings']['posting_settings'], $_POST['settings']['posting_defaults'], $_POST['settings']['locations'], $_POST['nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wpl_update_gmb_settings_nonce' ) ) {
			$options = $this->wpl_get_gmb_settings_options();

			// Parse posting settings.
			// Posting frequency.
			if ( ! empty( $_POST['settings']['posting_settings']['posting_frequency'] ) && is_string( $_POST['settings']['posting_settings']['posting_frequency'] ) ) {
				$options['posting_settings']['posting_frequency'] = sanitize_text_field( wp_unslash( $_POST['settings']['posting_settings']['posting_frequency'] ) );
				$this->wpl_gmb_update_scheduled_posting_interval( $options['posting_settings']['posting_frequency'] );
			}
			// Use listing data and post without schedule.
			$options['posting_settings']['empty_schedule_auto_post'] = ( ! empty( $_POST['settings']['posting_settings']['empty_schedule_auto_post'] ) ? 1 : 0 );

			// Parse default posting settings.
			// Default Link/Photo/Content strings.
			if ( isset( $_POST['settings']['posting_defaults']['default_link'] ) ) {
				$options['posting_defaults']['default_link'] = sanitize_text_field( wp_unslash( $_POST['settings']['posting_defaults']['default_link'] ) );
			}

			if ( isset( $_POST['settings']['posting_defaults']['default_photo'] ) ) {
				$options['posting_defaults']['default_photo'] = sanitize_text_field( wp_unslash( $_POST['settings']['posting_defaults']['default_photo'] ) );
			}

			if ( isset( $_POST['settings']['posting_defaults']['default_summary'] ) ) {
				$options['posting_defaults']['default_summary'] = sanitize_text_field( wp_unslash( $_POST['settings']['posting_defaults']['default_summary'] ) );
			}

			// Listings data override toggles.
			$options['posting_defaults']['default_link_override']    = ( ! empty( $_POST['settings']['posting_defaults']['default_link_override'] ? 1 : 0 ) );
			$options['posting_defaults']['default_photo_override']   = ( ! empty( $_POST['settings']['posting_defaults']['default_photo_override'] ? 1 : 0 ) );
			$options['posting_defaults']['default_summary_override'] = ( ! empty( $_POST['settings']['posting_defaults']['default_summary_override'] ? 1 : 0 ) );

			// Parse location settings.
			$location_share_settings = [];
			if ( ! empty( $_POST['settings']['locations'] ) && is_array( $_POST['settings']['locations'] ) ) {
				$location_share_settings = filter_var_array( wp_unslash( $_POST['settings']['locations'] ), FILTER_SANITIZE_NUMBER_INT );
			}

			foreach ( $location_share_settings as $key => $value ) {
				if ( array_key_exists( $key, $options['locations'] ) && ! empty( $value['share_to_location'] ) ) {
					$options['locations'][ $key ]['share_to_location'] = 1;
				} else {
					$options['locations'][ $key ]['share_to_location'] = 0;
				}
			}
			// Update options, echo success, and kill connection.
			update_option( 'wp_listings_google_my_business_options', $options );

			echo 'success';
			wp_die();
		}

		echo 'request failed';
		wp_die();
	}

	/**
	 * Clear_GMB_Settings.
	 * Clears all saved GMB settings, sets feature back to unlogged-in/default state.
	 */
	public function wpl_clear_gmb_settings() {
		// User capability check.
		if ( ! current_user_can( 'manage_categories' ) ) {
			echo 'check permissions';
			wp_die();
		}
		// Validate and process request.
		if ( isset( $_REQUEST['nonce'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['nonce'] ), 'wpl_clear_gmb_settings_nonce' ) ) {
			// Clear options.
			delete_option( 'wp_listings_google_my_business_options' );
			// Clear transients.
			delete_transient( 'wp_listings_google_my_business_auth_cache' );
			delete_transient( 'wp_listings_google_my_business_account_cache' );
			delete_transient( 'wp_listings_google_my_business_location_settings' );
			wp_clear_scheduled_hook( 'wp_listings_gmb_auto_post' );
			echo 'success';
			wp_die();
		}

		echo 'request failed';
		wp_die();
	}

	/**
	 * Get_GMB_Accounts.
	 * Gets raw/full Google My Business account information required for making local posts.
	 *
	 * @return array
	 */
	public function get_gmb_accounts() {

		$account_transient = get_transient( 'wp_listings_google_my_business_account_cache' );

		if ( $account_transient ) {
			return $account_transient;
		}

		// Make sure token is available before making request.
		if ( ! $this->get_google_access_token() ) {
			return;
		}

		$response = wp_remote_get(
			'https://mybusiness.googleapis.com/v4/accounts',
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $this->get_google_access_token(),
				],
			]
		);

		if ( ! is_wp_error( $response ) ) {
			if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
				$json     = json_decode( $response['body'], true );
				$accounts = $json['accounts'];
				set_transient( 'wp_listings_google_my_business_account_cache', $accounts, WEEK_IN_SECONDS );
				return $accounts;
			}
		}

		return [];
	}

	/**
	 * Get_Selected_GMB_Accounts.
	 * Gets selected Google My Business account.
	 * This is currently a placeholder function as there is no user-facing option to select an account.
	 * The first account returned from Google will be used for creating posts.
	 *
	 * @return mixed
	 */
	public function get_selected_gmb_account() {
		$options = $this->wpl_get_gmb_settings_options();

		if ( ! empty( $options['account_name'] ) ) {
			return $options['account_name'];
		}

		$accounts = $this->get_gmb_accounts();
		if ( ! empty( $accounts ) && ! empty( $accounts[0]['name'] ) ) {
			return $accounts[0]['name'];
		}

		return false;
	}

	/**
	 * Get__GMB_Locations.
	 * Gets raw/full Google My Business location information required for making local posts.
	 *
	 * @return array
	 */
	public function get_gmb_locations() {
		// Check for transient data first.
		$locations_transient = get_transient( 'wp_listings_google_my_business_location_settings' );
		if ( $locations_transient ) {
			return $locations_transient;
		}

		$locations = [];

		$account = $this->get_selected_gmb_account();

		// Make sure token is available before making request.
		if ( ! $this->get_google_access_token() ) {
			return;
		}

		$response = wp_remote_get(
			'https://mybusiness.googleapis.com/v4/'. $account . '/locations',
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $this->get_google_access_token(),
				],
			]
		);

		if ( ! is_wp_error( $response ) ) {
			if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
				$json = json_decode( $response['body'], true );
				$locations = $json['locations'];
				set_transient( 'wp_listings_google_my_business_location_settings', $locations, WEEK_IN_SECONDS );
				return $locations;
			}
		}
		return $locations;
	}

	/**
	 * Get_Saved_GMB_Locations.
	 * Gets saved location data which includes sharing preferences for locations.
	 *
	 * @return mixed
	 */
	public function get_saved_gmb_locations() {
		// Get all locations, return false if none.
		$all_locations = $this->get_gmb_locations();
		if ( empty( $all_locations ) ) {
			return false;
		}

		// Get current GMB options. 
		$options = $this->wpl_get_gmb_settings_options();
		// // Add any locations from all_locations that are not already in saved_locations.
		foreach ( $all_locations as $current_location ) {
			if ( ! isset( $options['locations'][ $current_location['name'] ] ) ) {
				$options['locations'][ $current_location['name'] ] = [
					'location_name'     => $current_location['locationName'],
					'street_address'    => $current_location['address']['addressLines'][0],
					'share_to_location' => 1,
				];
			}
		}

		update_option( 'wp_listings_google_my_business_options', $options );
		return $options['locations'];
	}

	/**
	 * Get_Full_Location_Information.
	 * Gets all info for a given location from its name.
	 *
	 * @return mixed
	 */
	public function get_full_location_information( $location_name ) {
		$options = $this->wpl_get_gmb_settings_options();

		$locations = $this->get_gmb_locations();

		if ( is_array( $locations ) ) {
			foreach ( $locations as $location ) {
				if ( $location_name === $location['name'] ) {
					return $location;
				}
			}
		}

		return false;
	}


	// Posting Functions.

	/**
	 * Publish_default_post_to_gmb.
	 * Takes saved default values and posts them using publish_post_to_gmb().
	 *
	 * @return void
	 */
	public function publish_default_post_to_gmb() {
		$options   = $this->wpl_get_gmb_settings_options();
		$summary   = $options['posting_defaults']['default_summary'];
		$photo_url = $options['posting_defaults']['default_photo'];
		$page_url  = $options['posting_defaults']['default_link'];
		$this->publish_post_to_gmb( $summary, $photo_url, $page_url );
	}

	/**
	 * Post_With_Listing_Data.
	 * Gathers 50 most recent listing posts, looks for one that has not been shared yet, and submits it to wpl_gmb_get_data_from_post_id().
	 *
	 * @return void
	 */
	public function wpl_gmb_post_with_listing_data() {
		$options = $this->wpl_get_gmb_settings_options();
		$recent_listing_posts = wp_get_recent_posts(
			[
				'post_type'   => 'listing',
				'post_status' => 'publish',
				'numberposts' => 50,
			]
		);

		// Fallback if no listing posts are imported, attempt to post default values.
		if ( empty( $recent_listing_posts ) ) {
			$this->publish_default_post_to_gmb();
			return;
		}

		foreach ( $recent_listing_posts as $key => $listing_post ) {
			if ( ! in_array( $listing_post['ID'], $options['posting_logs']['used_post_ids'] ) ) {
				$this->wpl_gmb_get_data_from_post_id( $listing_post['ID'] );
				return;
			}
		}

		// Reaching this point means listings exist but all have been shared or at least attempted. Reset the list and try sharing the newest listing.
		$this->wpl_gmb_update_logs( 'used_post_ids', [] );
		// Start sharing over again with most recent post.
		if ( $recent_listing_posts[0]['ID'] ) {
			$this->wpl_gmb_get_data_from_post_id( $recent_listing_posts[0]['ID'] );
		} else {
			// Final fallback if everything else failed trying to post using listing data.
			$this->publish_default_post_to_gmb();
		}
	}

	/**
	 * Get_Data_From_Post_ID.
	 * Gathers info from a listing post and passed the required values to publish_post_to_gmb().
	 *
	 * @param int $post_id - Post ID.
	 *
	 * @return void
	 */
	public function wpl_gmb_get_data_from_post_id( $post_id ) {
		$options = $this->wpl_get_gmb_settings_options();

		$post = get_post( $post_id );

		// Just in case get_post fails.
		if ( ! $post ) {
			$this->wpl_gmb_update_logs( 'last_post_status_message', 'Failed - Issue with locating listing post from ID.' );
			return;
		}

		// If override is set for a given field, use the default value instead of the value found in the post.
		$summary  = ( $options['posting_defaults']['default_summary_override'] ? $options['posting_defaults']['default_summary'] : $post->post_content );
		$page_url = ( $options['posting_defaults']['default_link_override'] ? $options['posting_defaults']['default_link'] : get_permalink( $post_id ) );

		$photo_url = '';
		// If photo is set to use default value, use that. Otherwise try to the post thumbnail, and lastely fall back to default if thumbnail fails.
		if ( $options['posting_defaults']['default_photo_override'] ) {
			$photo_url = $options['posting_defaults']['default_photo'];
		} elseif ( has_post_thumbnail( $post_id ) ) {
			// Between 10 KB and 5 MB, Minimum resolution: 250px height, 250px wide.
			$listing_image_url = get_the_post_thumbnail_url( $post_id, 'full' );
			// If full sized image is not available, grab what is.
			if ( ! $listing_image_url ) {
				$listing_image_url = get_the_post_thumbnail_url( $post_id );
			}
			// Get image headers for file size.
			$image_headers = get_headers( $listing_image_url, true );
			// Get image size info for dimensions.
			$image_size_info = getimagesize( $listing_image_url );
			// If no Content-Length is found to check image size, assume image is above 10240 byte threshold.
			$image_size = 10241;
			if ( isset( $headers['Content-Length'] ) ) {
				$image_size = intval( $headers['Content-Length'] );
			}
			// Check image height, width, minimum size, and maximum size.
			if ( $image_size_info[0] > 250 && $image_size_info[1] > 250 && $image_size > 10240 && $image_size < 5242880 ) {
				$photo_url = $listing_image_url;
			}
		}

		// If the photo default override isn't set, and getting the thumbnail URL fails, assign the default value as a final fallback.
		if ( empty( $photo_url ) ) {
			$photo_url = $options['posting_defaults']['default_photo'];
		}

		// Check if all values are populated and submit post.
		if ( isset( $summary, $photo_url, $page_url ) ) {
			$this->publish_post_to_gmb( $summary, $photo_url, $page_url, $post_id );
			return;
		}

	}

	/**
	 * Publish_Post_To_GMB.
	 * Used to create a "What's New - Learn More" Local Post on Google My Business using the passed in values.
	 *
	 * @param string $summary - Post summary string.
	 * @param string $photo_url - Post photo URL.
	 * @param string $page_url - Post page URL.
	 * @param string $post_id - Post ID is optional, only used for logging post success/failure.
	 *
	 * @return void
	 */
	public function publish_post_to_gmb( $summary, $photo_url, $page_url, $post_id = null ) {
		// Make sure summary is below 1500 characters. Strip tags just incase HTML came through in a listing summary.
		$summary = substr( strip_tags( $summary ), 0, 1499 );

		// Validate URLs.
		$photo_url = wp_http_validate_url( $photo_url );
		$page_url  = wp_http_validate_url( $page_url );

		// Final validation before attempting to post.
		if ( empty( $photo_url ) || empty( $page_url ) || empty( $summary ) ) {
			$this->wpl_gmb_update_logs( 'last_post_status_message', 'Final check before posting failed, verify both photo and page URL links work and that a summary is included.' );
			return;
		}

		$post_body = [
			'languageCode' => get_locale(),
			'summary'      => $summary,
			'callToAction' => [
				'url'        => $page_url,
				'actionType' => 'LEARN_MORE',
			],
			'media'        => [
				'sourceUrl'   => $photo_url,
				'mediaFormat' => 'PHOTO',
			],
		];

		// Encode $post_body before sending.
		$post_body = json_encode( $post_body );

		// Get locations to post.
		$locations = $this->get_saved_gmb_locations();

		// If no locations available, log error and return.
		if ( empty( $locations ) ) {
			$this->wpl_gmb_update_logs( 'last_post_status_message', 'No posting locations available.' );
			return;
		}

		// Make sure token is available before making requests.
		if ( ! $this->get_google_access_token() ) {
			return;
		}

		foreach ( $locations as $key => $location ) {

			if ( ! $location['share_to_location'] ) {
				continue;
			}

			$response = wp_remote_post(
				'https://mybusiness.googleapis.com/v4/' . $key . '/localPosts',
				[
					'headers' => [
						'Authorization' => 'Bearer ' . $this->get_google_access_token(),
						'Content-Type'  => 'application/json; charset=utf-8',
					],
					'body'    => $post_body,
				]
			);

			if ( ! is_wp_error( $response ) ) {

				$json          = json_decode( $response['body'], true );
				$response_code = wp_remote_retrieve_response_code( $response );
				$options       = $this->wpl_get_gmb_settings_options();

				if ( 200 === $response_code ) {
					// If a post ID was included in the function call, remove it from the schedule and update posting log.
					if ( $post_id ) {
						$this->wpl_gmb_update_logs( 'used_post_ids', $post_id );
						$scheduled_key = array_search( $post_id, $options['posting_settings']['scheduled_posts'], true );
						if ( false !== $scheduled_key ) {
							array_splice( $options['posting_settings']['scheduled_posts'], $scheduled_key, 1 );
							update_option( 'wp_listings_google_my_business_options', $options );
						}
					}
					$this->wpl_gmb_update_logs( 'last_post_status_message', 'Post Successful' );
					return;
				}

				// Posting failed, schedule re-attempt.
				$this->wpl_reset_next_scheduled_post_time( true );

				// Invalid link or photo URL.
				if ( 400 === $response_code ) {
					$this->wpl_gmb_update_logs( 'last_post_status_message', 'Oops! Post Unsuccessful - Invalid photo or page URL provided.' . ( ! empty( $post_id ) ? " Post ID: $post_id" : '' ) );
					return;
				}

				// Location not authorized by Google to accept location posts.
				if ( 403 === $response_code ) {
					// Check for unverified location error.
					if ( 'Creating/Updating a local post is not authorized for this location.' === $json['error']['message'] ) {
						$this->wpl_gmb_update_logs( 'last_post_status_message', 'Oops! Post Unsuccessful - Creating/Updating a local post is not authorized for this location. Check with Google on the status of verifying your business location.' . ( ! empty( $post_id ) ? " Post ID: $post_id" : '' ) );
						return;
					}
				}

				// Catch any other remaining errors, include status code in .
				$this->wpl_gmb_update_logs( 'last_post_status_message', 'Oops! Post Unsuccessful - Response code received from Google: ' . $response_code . '.' . ( ! empty( $post_id ) ? " Post ID: $post_id" : '' ) );
				return;
			}

			// WP_Error found, log error.
			$this->wpl_gmb_update_logs( 'last_post_status_message', 'Oops! Post Unsuccessful - WP_Error returned.' );
			return;
		}

		// Only reachable if no locations are found with sharing enabled.
		$this->wpl_gmb_update_logs( 'last_post_status_message', 'Oops! Post Unsuccessful - No locations selected.' );
		return;

	}

	// Scheduling Functions.

	/**
	 * WPL_GMB_Scheduled_Post.
	 * Actual cron task used to post to Google My Business.
	 *
	 * @return void
	 */
	public function wpl_gmb_scheduled_post() {
		// Clear last post status message in preparation for this attempt's message.
		$this->wpl_gmb_update_logs( 'last_post_status_message', '' );

		$options = $this->wpl_get_gmb_settings_options();

		// If post is scheduled.
		if ( ! empty( $options['posting_settings']['scheduled_posts'] ) && get_post_status( $options['posting_settings']['scheduled_posts'][0] ) ) {
			$this->wpl_gmb_get_data_from_post_id( $options['posting_settings']['scheduled_posts'][0] );
			return;
		}

		// If use schedule is empty and empty_schedule_auto_post is enabled.
		if ( $options['posting_settings']['empty_schedule_auto_post'] ) {
			$this->wpl_gmb_post_with_listing_data();
			return;
		}
	}

	/**
	 * Schedule_Posting_Event.
	 * Used to schedule first import upon successful login to Google.
	 *
	 * @return void
	 */
	public function wpl_schedule_posting_event() {
		if ( ! wp_next_scheduled( 'wp_listings_gmb_auto_post' ) ) {
			// Fire first post in 12 hours from enabling.
			wp_schedule_event( ( time() + ( HOUR_IN_SECONDS * 12 ) ), 'weekly', 'wp_listings_gmb_auto_post' );
		}
	}

	/**
	 * Update_Scheduled_Posting_Interval.
	 * Upon updating settings, wipes out existing job and reschedules using the new interval, existing timestamp is preserved.
	 *
	 * @param string $interval - Reoccurance interval for cron job.
	 *
	 * @return void
	 */
	public function wpl_gmb_update_scheduled_posting_interval( $interval ) {
		$current_event = wp_get_scheduled_event( 'wp_listings_gmb_auto_post' );
		// If interval the same, return.
		if ( $interval === $current_event->schedule ) {
			return;
		}
		// Clear current event before scheduling a new one to prevent any duplication.
		wp_clear_scheduled_hook( 'wp_listings_gmb_auto_post' );
		switch ( $interval ) {
			case 'weekly':
				wp_schedule_event( $current_event->timestamp, 'weekly', 'wp_listings_gmb_auto_post' );
				break;
			case 'biweekly':
				wp_schedule_event( $current_event->timestamp, 'biweekly', 'wp_listings_gmb_auto_post' );
				break;
			case 'monthly':
				wp_schedule_event( $current_event->timestamp, 'monthly', 'wp_listings_gmb_auto_post' );
				break;
			default:
				// Something is askew if this happens, manually set timestamp just in case.
				wp_schedule_event( ( time() + WEEK_IN_SECONDS ), 'weekly', 'wp_listings_gmb_auto_post' );
		}
	}

	/**
	 * Event_Schedules.
	 * Used to add custom time intervals to the cron_schedules filter.
	 *
	 * @param array $schedules - Current array of schedules.
	 *
	 * @return array
	 */
	public function wpl_gmb_event_schedules( $schedules ) {

		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = [
				'display'  => __( 'Every Week', 'wp-listings' ),
				'interval' => WEEK_IN_SECONDS,
			];
		}

		if ( ! isset( $schedules['biweekly'] ) ) {
			$schedules['biweekly'] = [
				'display'  => __( 'Every 2 Weeks', 'wp-listings' ),
				'interval' => ( WEEK_IN_SECONDS * 2 ),
			];
		}

		if ( ! isset( $schedules['monthly'] ) ) {
			$schedules['monthly'] = [
				'display'  => __( 'Every Month', 'wp-listings' ),
				'interval' => MONTH_IN_SECONDS,
			];
		}

		return $schedules;
	}

	/**
	 * Reset_Next_Post_Time_Request.
	 * Handles Ajax request from WP dashboard to reset the next posting time, once request is verified the actual request occurs in wpl_reset_next_scheduled_post_time().
	 *
	 * @return void
	 */
	public function wpl_reset_next_post_time_request() {
		// User capability check.
		if ( ! current_user_can( 'manage_categories' ) ) {
			echo 'check permissions';
			wp_die();
		}

		// Validate and process request.
		if ( isset( $_REQUEST['nonce'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['nonce'] ), 'wpl_reset_next_post_time_request_nonce' ) ) {
			$current_event = wp_get_scheduled_event( 'wp_listings_gmb_auto_post' );
			// Wipe out current event and reschedule for 12 hours from now.
			$this->wpl_reset_next_scheduled_post_time( true );
			echo esc_attr( $this->wpl_gmb_get_next_post_time() );
			wp_die();
		}

		wp_die();
	}

	/**
	 * Reset_Next_Scheduled_Post_Time.
	 * Resets next scheduled post time to either the next date based on posting frequency or 12 hours from now in cases of a posting failure or a user initiated reschedule.
	 *
	 * @param bool $retry_post - Used to 
	 * @return void
	 */
	public function wpl_reset_next_scheduled_post_time( $retry_post = false ) {
		$options = $this->wpl_get_gmb_settings_options();

		wp_clear_scheduled_hook( 'wp_listings_gmb_auto_post' );

		if ( $retry_post ) {
			wp_schedule_event( ( time() + ( HOUR_IN_SECONDS * 12 ) ), $options['posting_settings']['posting_frequency'], 'wp_listings_gmb_auto_post' );
			return;
		}

		$current_schedules   = wp_get_schedules();
		$posting_frequency   = $options['posting_settings']['posting_frequency'];
		$frequency_timestamp = $current_schedules[ $posting_frequency ]['interval'];
		wp_schedule_event( ( time() + $frequency_timestamp ), $posting_frequency, 'wp_listings_gmb_auto_post' );
	}

	/**
	 * Get_Next_Post_Time.
	 * Helper function to get approximate next post time as a string.
	 *
	 * @return string
	 */
	public function wpl_gmb_get_next_post_time() {
		$current_event = wp_get_scheduled_event( 'wp_listings_gmb_auto_post' );
		if ( ! empty( $current_event->timestamp ) ) {
			return date_i18n( 'l, F j', $current_event->timestamp );
		}
		return 'Unscheduled';
	}

	/**
	 * Post_Next_Scheduled_Now.
	 * Updates scheduled posts list.
	 *
	 * @return void
	 */
	public function wpl_post_next_scheduled_now() {
		// User capability check.
		if ( ! current_user_can( 'manage_categories' ) ) {
			echo 'check permissions';
			wp_die();
		}

		// Validate and process request.
		if ( isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wpl_post_next_scheduled_now_nonce' ) ) {
			$this->wpl_reset_next_scheduled_post_time();
			$this->wpl_gmb_scheduled_post();
			echo 'success';
		}

		wp_die();
	}

	/**
	 * Update_Scheduled_Posts.
	 * Updates scheduled posts list.
	 *
	 * @return void
	 */
	public function wpl_update_scheduled_posts() {
		// User capability check.
		if ( ! current_user_can( 'manage_categories' ) ) {
			echo 'check permissions';
			wp_die();
		}

		// Validate and process request.
		if ( isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wpl_update_scheduled_posts_nonce' ) ) {
			$options            = $this->wpl_get_gmb_settings_options();
			$scheduled_post_ids = [];

			if ( ! empty( $_POST['scheduled_posts'] ) && is_array( $_POST['scheduled_posts'] ) ) {
				$scheduled_post_ids = filter_var_array( wp_unslash( $_POST['scheduled_posts'] ), FILTER_SANITIZE_STRING );
			}

			$options['posting_settings']['scheduled_posts'] = $scheduled_post_ids;
			update_option( 'wp_listings_google_my_business_options', $options );
			echo 'success';
		}

		wp_die();
	}

	/**
	 * Clear_Scheduled_Posts.
	 * Clears scheduled posts list.
	 *
	 * @return void
	 */
	public function wpl_clear_scheduled_posts() {
		// User capability check.
		if ( ! current_user_can( 'manage_categories' ) ) {
			echo 'check permissions';
			wp_die();
		}

		// Validate and process request.
		if ( isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wpl_clear_scheduled_posts_nonce' ) ) {
			$options = $this->wpl_get_gmb_settings_options();
			$options['posting_settings']['scheduled_posts'] = [];
			update_option( 'wp_listings_google_my_business_options', $options );
			echo 'success';
		}

		wp_die();
	}

	/**
	 * Update_Exclusion_List.
	 * Updates the post exclusion list, these posts will not be used for sharing.
	 *
	 * @return void
	 */
	public function wpl_update_exclusion_list() {
		// User capability check.
		if ( ! current_user_can( 'manage_categories' ) ) {
			echo 'check permissions';
			wp_die();
		}

		// Validate and process request.
		if ( isset( $_POST['nonce'], $_POST['update_type'], $_POST['post_id'] ) && wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wpl_update_exclusion_list_nonce' ) ) {
			$options = $this->wpl_get_gmb_settings_options();

			if ( 'clear' === $_POST['update_type'] ) {
				$options['posting_settings']['excluded_posts'] = [];
				update_option( 'wp_listings_google_my_business_options', $options );
				echo 'success';
			}

			if ( 'add' === $_POST['update_type'] && ! empty( $_POST['post_id'] ) ) {
				array_push( $options['posting_settings']['excluded_posts'], absint( $_POST['post_id'] ) );
				$options['posting_settings']['excluded_posts'] = array_unique( $options['posting_settings']['excluded_posts'] );
				update_option( 'wp_listings_google_my_business_options', $options );
				echo 'success';
			}

			if ( 'remove' === $_POST['update_type'] && ! empty( $_POST['post_id'] ) ) {
				foreach ( $options['posting_settings']['excluded_posts'] as $key => $value ) {
					if ( $value == $_POST['post_id'] ) {
						array_splice( $options['posting_settings']['excluded_posts'], $key, 1 );
						update_option( 'wp_listings_google_my_business_options', $options );
						echo 'success';
						break;
					}
				}
			}
		}
		wp_die();
	}

	/**
	 * Clear_Last_Post_Status.
	 * Clears last post status msg.
	 *
	 * @return void
	 */
	public function wpl_clear_last_post_status() {
		// User capability check.
		if ( ! current_user_can( 'manage_categories' ) ) {
			echo 'check permissions';
			wp_die();
		}

		// Validate and process request.
		if ( isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wpl_clear_last_post_status_nonce' ) ) {
			$options = $this->wpl_get_gmb_settings_options();
			$options['posting_logs']['last_post_status_message'] = '';
			update_option( 'wp_listings_google_my_business_options', $options );
		}

		wp_die();
	}

	/**
	 * Get_Error_Log.
	 * Helper function to get the stored error message.
	 *
	 * @return string
	 */
	public function wpl_gmb_get_error_log() {
		$options = $this->wpl_get_gmb_settings_options();
		if ( ! empty( $option['posting_logs']['last_post_status_message'] ) ) {
			return $option['posting_logs']['last_post_status_message'];
		}
		return '';
	}

	/**
	 * Pop_Last_Shared_Post_ID.
	 * Helper function used to pop last post ID from the post log in case of a posting error.
	 *
	 * @return string
	 */
	public function wpl_gmb_pop_last_shared_post_id() {
		$options = $this->wpl_get_gmb_settings_options();

		if ( ! empty( $option['posting_logs']['used_post_ids'] ) ) {
			$last_post_id = array_pop( $option['posting_logs']['used_post_ids'] );
			// Save 
			update_option( 'wp_listings_google_my_business_options', $options );
			return $last_post_id;
		}
		return '';
	}

}
