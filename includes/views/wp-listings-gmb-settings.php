<?php
$google_my_business_manager = WPL_Google_My_Business::getInstance();
$google_my_business_options = $google_my_business_manager->wpl_get_gmb_settings_options();
?>
<div id="icon-options-general" class="icon32"></div>
<div class="wrap">

	<div class="gmb-settings-page-header-container">
		<h1 class="gmb-settings-page-header"><?php esc_attr_e( 'IMPress Listings - Google My Business Settings', 'wp-listings' ); ?></h1>
		<div class="beta-label-tag">
			beta
		</div>
	</div>
	<hr>
	<div id="poststuff" class="metabox-holder has-right-sidebar">
		<div id="side-info-column" class="inner-sidebar">
		<?php do_meta_boxes('wp-listings-options', 'side', null); ?>
		</div>

		<div id="post-body">
			<div id="post-body-content">
			<script>
				jQuery( function() {
					jQuery( "#post-body-content" ).tabs();
				} );
				function updateSettingsUrl( selectedTab ) {
					var urlString = window.location.href;
					if ( urlString.includes('#') ) {
						urlString = urlString.split('#')[0];
					}
					history.pushState( {}, {}, selectedTab.href );
				}
			</script>

				<?php
				if ( ! empty( $google_my_business_options['refresh_token'] ) ) {
					echo '<ul>';
					_e( '<li><a href="#tab-gmb-settings" onclick="updateSettingsUrl(this);">General Settings</a></li>', 'wp-listings' );
					_e( '<li><a href="#tab-gmb-schedule" onclick="updateSettingsUrl(this);">Post Schedule</a></li>', 'wp-listings' );
					echo '</ul>';
				}
				?>

				<?php

				// General Settings Tab.
				echo '<div id="tab-gmb-settings">';
					include( plugin_dir_path( __FILE__ ) . 'gmb-settings-views/gmb-settings-view.php' );
				echo '</div>';

				// Post Schedule Tab.
				if ( ! empty( $google_my_business_options['refresh_token'] ) ) {
					echo '<div id="tab-gmb-schedule">';
					include( plugin_dir_path( __FILE__ ) . 'gmb-settings-views/gmb-schedule-view.php' );
					echo '</div>';
				}

				?>

			</div>
		</div>
	</div>
</div>
<!-- Terms of Service Lightbox -->
<div id="terms-lightbox" class="lightbox">
	<div class="lightbox-modal">
		<div class="lightbox-title"><?php esc_attr_e( 'Terms of Service', 'wp-listings' ); ?></div>
		<div class="lightbox-terms-container">
			<p><?php esc_attr_e( 'Important:', 'wp-listings' ); ?></p>

			<strong>
				<?php esc_attr_e( 'The IMPress Listings plugin is designed to further power and enhance the functionality of websites and applications used by real estate agents, brokers, and technology partners.', 'wp-listings' ); ?>
				<br><br>
				<?php esc_attr_e( ' Using this plugin to publish, or otherwise make public, information related to any listing data which violates your local MLS system agreements in any way is prohibited. URLs, landing pages, listing pages, community pages, or any “linked” resources that contains IDX data must be approved for public display by your MLS system.', 'wp-listings' ); ?></strong>
		</div>
		<div class="lightbox-button-container">
			<div class="toggle-container">
				<?php esc_attr_e( 'Agree to terms:', 'wp-listings' ); ?>
				<input id="terms-agreement-checkbox" type="checkbox" value="1" class="wpl-gmp-settings-checkbox" onchange="agreeToTermsChecked(this);">
				<label for="terms-agreement-checkbox" class="checkbox-label-slider"></label>

			</div>
			<?php
				echo '<a href="https://accounts.google.com/o/oauth2/v2/auth?
				scope=https://www.googleapis.com/auth/plus.business.manage
				&access_type=offline
				&include_granted_scopes=true
				&state=' . rawurlencode( get_admin_url() ) . '
				&redirect_uri=https://hheqsfm21f.execute-api.us-west-2.amazonaws.com/v1/initial-token
				&response_type=code
				&client_id=53079160906-ari2lj7pscegfvu89p6bqjadi60igb01.apps.googleusercontent.com
				&prompt=consent"
				id="agree-to-terms-button" 
				class="button lightbox-modal-button" disabled>
				<i style="color: #4a8af4;" class="fa fa-google" aria-hidden="true"></i> Connect with GMB
				</a>';
			?>
			<button id="cancel-terms-button" class="button lightbox-modal-button" onclick="cancelLoginClicked();">Cancel</button>
		</div>
	</div>
</div>
