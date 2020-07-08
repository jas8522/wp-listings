<?php

// Google My Business settings.
$google_my_business_manager = WPL_Google_My_Business::getInstance();
$google_my_business_options = $google_my_business_manager->wpl_get_gmb_settings_options();

/**
 * Helper function to convert time interval to the needed slider value.
 *
 * @param string $timespan - time span string, weekly/biweekly/monthly.
 *
 * @return int
 */
function get_slider_frequency_value( $timespan = '' ) {
	if ( empty( $timespan ) ) {
		return '0';
	}
	switch ( $timespan ) {
		case 'weekly':
			return '0';
		case 'biweekly':
			return '50';
		case 'monthly':
			return '100';
		default:
			return '0';
	}
}

// If no refresh token saved, show Google login button.
if ( empty( $google_my_business_options['refresh_token'] ) ) {

	_e( '<div class="gmb-login-container">
		<h3 style="margin-bottom:0px;">Give Leads More Ways to Reach You</h3>
		<hr>
		<p><a onclick="showLightbox();" href="#">Log in</a> or <a href="https://google.com/business" target="_blank">Create a free Google My Business Profile</a> to connect with IMPress Listings.</p>

			<h3 style="margin-bottom:0px;">Connect to Google My Business</h3>
			<hr>
			<p>Once verified, connect your Google My Business (GMB) profile to IMPress Listings, to generate timely posts and photos of your listings and more… automatically.</p>

			<p>The automatic scheduler can be used to create and share posts to highlight your own featured listings as well as open house announcements, recent sales, local expertise and more.</p>

			<p>Posts have the potential to draw leads and clients directly to your IDX-enabled website for more home search opportunities. Google My Business posts are archived on a weekly basis, so automating the process with the scheduler is an easy way to maintain your real estate business’s online presence.</p> 

			<p><strong>Automatic posting requires a verified Google My Business account with a verified location.</strong>
			<!-- Tooltip -->
			<span class="tooltip"><span class="dashicons dashicons-editor-help wpl-gmb-main-desc-help"></span>
				<span class="tooltiptext">

				Posts made to Google My Business will be of the type "What&apos;s New". For more information about local posts, visit Google&apos;s <a href="https://support.google.com/business/answer/7662907?hl=en" target="_blank">About posts for local businesses</a> page. 

				</span>
			</span>
			</p>
		</div>', 'wp-listings' );
	echo '<div class="wpl-gmb-login-button-container"></div>';
}

// If refresh token is saved, show GMB settings.
if ( ! empty( $google_my_business_options['refresh_token'] ) ) {

	_e( '<p class="wpl-gmb-connected-status-container wpl-gmb-connected">
			<span class="dashicons dashicons-yes-alt" style="color:white;"></span>
			Connected to Google
		</p>', 'wp-listings' );

	// Last post status if available.
	if ( ! empty( $google_my_business_options['posting_logs']['last_post_status_message'] ) ) {
		_e( '<div id="wpl-gmb-last-status-container">Last Post Status: ' . $google_my_business_options['posting_logs']['last_post_status_message'] . '<button onclick="clearLastPostStatus();"><span class="dashicons dashicons-no-alt"></span></button></div>', 'wp-listings' );
	}
	echo '<hr>';
	_e( '<h3>Settings</h3>', 'wp-listings' );

	_e( '<p>The post type for all scheduled posts will be "What&apos;s New". For more information about posts and post types, visit the <a href="https://support.google.com/business/answer/7662907?hl=en" target="_blank">Google My Business post support page</a>.</p>', 'wp-listings' );

	echo '<div id="wpl-gmb-general-settings-container">';
	echo '<div class="general-settings-cell">';

	_e( '<div id="wpl-gmb-post-frequency-label-container">
			<div><strong>Posting Frequency: </strong>
				<span id="wpl-gmb-update-frequency-label">' . ucfirst( $google_my_business_options['posting_settings']['posting_frequency'] ) . '</span>
			</div>
			<div>

			<!-- Tooltip -->
			<span class="tooltip"><strong>Next Scheduled Post: </strong>
				<span class="tooltiptext">Due to the nature of the built-in WordPress task scheduling system, sites with low traffic might see delayed automatic posts.</span>
			</span>
				<span id="wpl-gmb-next-post-label">' . $google_my_business_manager->wpl_gmb_get_next_post_time() . '</span>
			</div>
		 </div>', 'wp-listings' );

	_e( '<input id="wpl-gmb-update-frequency-slider" type="range" min="0" max="100" value="' . get_slider_frequency_value($google_my_business_options['posting_settings']['posting_frequency']) . '" class="slider" step="50">', 'wp-listings' );

	_e( '<div id="wpl-gmb-reset-post-time-container"><button id="wpl-reset-next-post-time-button" title="Resets next scheduled post to 12 hours from now." class="button">Reset Next Scheduled Post Time</button></div>', 'wp-listings' );

	echo '</div>';


	_e( '<div id="general-setting-toggles-cell" class="general-settings-cell">
			<div><strong>Posting Preferences:</strong></div>
			<div class="toggle-container">
				<input name="" id="wpl-gmb-use-listing-data-checkbox" type="checkbox" value="1" class="wpl-gmp-settings-checkbox" ' . ( $google_my_business_options['posting_settings']['empty_schedule_auto_post'] ? 'checked' : '' ) . '>
				<label for="wpl-gmb-use-listing-data-checkbox" class="checkbox-label-slider"></label> Auto publish recent listing posts if schedule is empty

				<!-- Tooltip -->
				<div class="tooltip"><span class="dashicons dashicons-editor-help"></span>
					<span class="tooltiptext lefttooltip"> If no listing posts are scheduled at the time of publishing to Google My Business, listings data will be pulled from recently created or imported listing posts. If no listing posts are available, the below default values will be used.</span>
				</div>
			</div>
		</div>', 'wp-listings' );
	echo '</div><hr>';
	// End of General Settings Section.

	// Default Sharing Settings section.
	_e( '<h3>Default Post Settings</h3>', 'wp-listings' );
	echo '<div class="wpl-gmb-defaults-container">';
	_e( '<p id="wpl-gmb-default-sharing-settings-text-container">
			These settings will be used if featured listing data is disabled or unavailable (i.e. no current featured listings, if a listing is missing any required data, or if the override checkbox is selected). If Default Post Settings have not been created, and listing data is disabled or unavailable, automatic posting will not occur. 

			<strong>Pro tip: Consider using pages with lead capture capabilities such as your IDX Broker Saved Links or a community page as a default option.</strong>
		</p>', 'wp-listings' );

	_e( '<div>
			<strong>Default Landing Page:</strong>
			<input name="" id="wpl-gmb-default-link-field" type="text" placeholder="Any valid page URL, this could be your homepage or a landing page used to capture leads." value="' . $google_my_business_options['posting_defaults']['default_link'] . '">
			<br>
			<input name="" id="wpl-gmb-post-link-override-checkbox" type="checkbox" value="1" class="wpl-gmp-settings-checkbox" ' . ( $google_my_business_options['posting_defaults']['default_link_override'] ? 'checked' : '' ) . '>
			<label for="wpl-gmb-post-link-override-checkbox" class="checkbox-label-slider"></label> Override Listing Landing Page
			<!-- Tooltip -->
			<div class="tooltip"><span class="dashicons dashicons-editor-help"></span>
  				<span class="tooltiptext">When selected, this Default Landing Page will be used instead of a listing&apos;s page.</span>
			</div>
		</div>
		', 'wp-listings' );

	_e( '<div>
			<strong>Default Post Photo:</strong>
			<input name="" id="wpl-gmb-default-photo-field" type="text" placeholder="Input a valid URL pointing to a JPG or PNG formatted image. Note: 4:3 aspect ratio preferred, 400x300 minimum resolution, 10KB minimum size, 5MB maximum size." value="' . $google_my_business_options['posting_defaults']['default_photo'] . '">
			<br>
			<input name="" id="wpl-gmb-post-photo-override-checkbox" type="checkbox" value="1" class="wpl-gmp-settings-checkbox" ' . ( $google_my_business_options['posting_defaults']['default_photo_override'] ? 'checked' : '' ) . '>
			<label for="wpl-gmb-post-photo-override-checkbox" class="checkbox-label-slider"></label> Override Listing Photo
			<!-- Tooltip -->
			<div class="tooltip"><span class="dashicons dashicons-editor-help"></span>
  				<span class="tooltiptext">When selected, this image will be used instead of a listing&apos;s Feature Image.</span>
			</div>
		</div>', 'wp-listings' );

	_e( '<div>
			<strong>Default Post Summary:</strong>
			<textarea id="wpl-gmb-default-content-field" name="message" maxlength="1499" placeholder="Provide general text to be included in this default post. Must be fewer than 1500 characters and contain no HTML, text only.">' . $google_my_business_options['posting_defaults']['default_summary'] . '</textarea><br><input name="" id="wpl-gmb-post-content-override-checkbox" type="checkbox" value="1" class="wpl-gmp-settings-checkbox" ' . ( $google_my_business_options['posting_defaults']['default_summary_override'] ? 'checked' : '' ) . '>
			<label for="wpl-gmb-post-content-override-checkbox" class="checkbox-label-slider"></label> Override Listing Data Remarks
			<!-- Tooltip -->
			<div class="tooltip"><span class="dashicons dashicons-editor-help"></span>
  				<span class="tooltiptext">When selected, this text will be used instead of a listing&apos;s remarks.</span>
			</div>
		</div>', 'wp-listings' );
	echo '</div><hr>';
	// End of default sharing settings.

	// Location list control.
	_e( '<h3 class="wpl-gmb-location-header">Available Locations</h3>', 'wp-listings' );
	echo '<div id="gmb-location-picker-container">';
	$gmb_locations = $google_my_business_manager->get_saved_gmb_locations();
	foreach ( $gmb_locations as $key => $value ) {
		echo '<div class="wpl-gmb-location-tag">';
		_e( "<input name='$key' id='$key' type='checkbox' value='1' class='wpl-gmp-settings-checkbox'  " . ( 1 == $value['share_to_location'] ? "checked" : "" ) . "/>", 'wp-listings' );
		_e( "<label for='$key' class='checkbox-label-slider'></label>", 'wp-listings' );
		_e( '<strong> ' . $value['location_name'] . ':</strong> ' . $value['street_address'], 'wp-listings' );
		echo '</div>';
	}
	echo '</div><hr>';
	// End of location list control.

	// Save and logout button section.
	echo '<div class="wpl-gmb-buttons-container">';

	_e( '<div><button id="wpl-save-gmb-settings-button" class="button">Save Google My Business Preferences</button>', 'wp-listings' );
	_e( '<div id="wpl-update-gmb-settings-message-container"><span id="wpl-update-gmb-settings-message"></span></div></div>', 'wp-listings' );

	_e( '<div id="wpl-gmb-clear-btn-container" ><a id="wpl-gmb-clear-settings-button" href="#">Disconnect from Google My Business</a></div>', 'wp-listings' );

	echo '</div>';
	// End of save and logout section.
}
