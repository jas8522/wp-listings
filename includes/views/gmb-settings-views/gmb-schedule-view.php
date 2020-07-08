<?php

// Google My Business settings. 
$google_my_business_manager = WPL_Google_My_Business::getInstance();
$google_my_business_options = $google_my_business_manager->wpl_get_gmb_settings_options();

/**
 * Generate_Listing_Cells.
 *
 * @param array $listings - array of listing posts.
 * @return void
 */
function generate_listing_cells( $listings ) {
	if ( empty( $listings ) || ! is_array( $listings ) ) {
		_e( '<div>No listings posts found.</div>', 'wp-listings' );
		return;
	}

	foreach ( $listings as $key => $listing ) {

		$listing_meta = get_post_meta( $listing->ID );

		echo '
				<div id="' . esc_attr( $listing->ID ) . '" class="draggable" draggable="true" ondragstart="onDragStart(event);" style="background-image: url(' . esc_attr( get_the_post_thumbnail_url( $listing->ID ) ) . ');" data-summary="' . esc_attr( wp_strip_all_tags( $listing->post_content ) ) . '">
					<div class="listing-cell-controls-container">
						<button class="button add-to-schedule-button unscheduled-control" title="Add to Schedule" onclick="addToScheduleClicked(' . esc_attr( $listing->ID ) . ');">
							<span class="dashicons dashicons-calendar-alt"></span>
						</button>
						<button class="button hide-post-button unscheduled-control" title="Exclude from Auto-Posting". onclick="excludeListingClicked(' . esc_attr( $listing->ID ) . ');">
							<span class="dashicons dashicons-hidden"></span>
						</button>
						<button class="button remove-from-schedule-button scheduled-control" title="Remove from schedule?" onclick="removeFromScheduleClicked(' . esc_attr( $listing->ID ) . ');">
							<span class="dashicons dashicons-no"></span>
						</button>

						<button class="button remove-from-exclusion-button exclusion-control" title="Remove from excluded list?" onclick="removedFromExludeListClicked(' . esc_attr( $listing->ID ) . ');">
							<span class="dashicons dashicons-arrow-up-alt2"></span>
						</button>
					</div>
					<div class="listing-cell-description">
						<div class="cell-description-text">
							' . ( empty( $listing_meta['_listing_address'][0] ) ? 'Address Unavailable' : esc_attr( $listing_meta['_listing_address'][0] ) ) . '
							<br>
							' . ( empty( $listing_meta['_listing_mls'][0] ) ? 'MLS# N/A' : 'MLS# ' . esc_attr( $listing_meta['_listing_mls'][0] ) ) . '
						</div>
					</div>
				</div>
		';
	}
}

/**
 * Uses the following frontend files enqueued in plugin.php.
 * JS  -> includes/js/admin-gmb-settings.js.
 * CSS -> includes/css/wp-listings-gmb-admin.css.
 * The JS function updatePostPreview() and styling for the .preview-cell-photo class are done below for easy access to the WP_LISTINGS_URL variable for placeholder elements.
*/
?>

<script>
	function updatePostPreview() {
		for (let i = 0; i < 12; i++) {
			if ( document.getElementById('dropzone-' + i).firstElementChild ) {
				document.querySelector('.preview-cell-text-area').innerText = document.getElementById('dropzone-' + i).firstElementChild.dataset.summary;
				document.querySelector('.preview-cell-photo').style.backgroundImage = document.getElementById('dropzone-' + i).firstElementChild.style.backgroundImage;
				return
			}
		}

		document.querySelector('.preview-cell-text-area').innerText = '...';
		document.querySelector('.preview-cell-photo').style.backgroundImage = "url(<?php echo esc_attr( WP_LISTINGS_URL . 'images/gmb-photo-placeholder.png' ); ?>)";

	}
</script>

<style>
	.preview-cell-photo {
		background-image: url('<?php echo WP_LISTINGS_URL . 'images/gmb-photo-placeholder.png'; ?>');
	}
</style>

<div class="schedule-view-container">
	<!-- Post Preview Area-->
	<div class="preview-container">
		<div class="preview-cell">
			<div class="preview-cell-photo-text"><?php esc_attr_e( 'Posted just now', 'wp-listings' ); ?></div>
			<div class="preview-cell-photo"></div>
			<div class="preview-cell-text-area">...</div>
			<button class="preview-cell-button btn-primary"><?php esc_attr_e( 'Learn More', 'wp-listings' ); ?></button>
		</div>
		<div id="next-post-preview-label" class="preview-container-label">
			<strong><?php esc_attr_e( 'Next Post Preview', 'wp-listings' ); ?></strong>
			<!-- Tooltip -->
			<div class="tooltip">
				<span class="dashicons dashicons-editor-help"></span>
				<span class="tooltiptext"><?php esc_attr_e( 'Next post preview is an approximation of how the post will look on Google My Business.', 'wp-listings' ); ?></span>
			</div>
		</div>
	</div>
	<!-- End Post Preview Area -->

	<div class="dropzone-section-container">
		<div id="schedule-top-section-wrapper">
			<div id="schedule-update-status-area" class="preview-container-label">
				<strong><?php esc_attr_e( 'Scheduled Posts: ', 'wp-listings' ); ?></strong>
				<div id="wpl-schedule-status">
					<span id="wpl-schedule-save-status"></span>
				</div>
			</div>
			<div class="schedule-area-text"><?php esc_attr_e( 'Use the drag-and-drop scheduler to determine the sequence of your posts!', 'wp-listings' ); ?></div>
			<div class="dropzones">
				<?php
				for ( $i = 0; $i < 12; $i++ ) {
					echo '<div id="dropzone-' . esc_attr( $i ) . '" style="background-image: url(' . esc_attr( WP_LISTINGS_URL ) . 'images/schedule-number-backgrounds/' . esc_attr( $i + 1 ) . '.svg)" class="schedule-dropzone" ondragover="onDragOver(event);" ondrop="onDrop(event);">';
					if ( ! empty( $google_my_business_options['posting_settings']['scheduled_posts'][ $i ] ) ) {
						generate_listing_cells( [ get_post( $google_my_business_options['posting_settings']['scheduled_posts'][ $i ] ) ] );
					}
					echo '</div>';
				}
				?>
			</div>
		</div>
		<?php
		// Last post status if available.
		if ( ! empty( $google_my_business_options['posting_logs']['last_post_status_message'] ) ) {
			_e( '<div id="wpl-gmb-last-status-container">Last Post Status: ' . $google_my_business_options['posting_logs']['last_post_status_message'] . '<button onclick="clearLastPostStatus();"><span class="dashicons dashicons-no-alt"></span></button></div>', 'wp-listings' );
		}
		?>
		<div id="dropzone-controls-container">
			<button id="post-next-button" class="button scheduling-button" onclick="postNextScheduledNow();"><?php esc_attr_e( 'Publish Next Post', 'wp-listings' ); ?></button>
			<button id="save-schedule-button" class="button scheduling-button" onclick="updateScheduledPosts();"><?php esc_attr_e( 'Save Schedule', 'wp-listings' ); ?></button>
			<button id="clear-schedule-button" class="button scheduling-button" onclick="clearScheduledPosts();"><?php esc_attr_e( 'Clear Schedule', 'wp-listings' ); ?></button>
		</div>
	</div>
</div>

<h3 class="schedule-section-header"><?php esc_attr_e( 'Listing Posts', 'wp-listings' ); ?></h3>
<hr>
<div id="active-listings-container" class="listing-cells-container" ondragover="onDragOver(event);" ondrop="onDrop(event);">
	<?php
	$listing_posts = get_posts(
		[
			'post_type'   => 'listing',
			'post_status' => 'publish',
			'numberposts' => -1,
			'order'       => 'DESC',
		]
	);

	foreach ( $listing_posts as $key => $listing_post ) {
		// Check for already scheduled listings.
		if ( in_array( $listing_post->ID, $google_my_business_options['posting_settings']['scheduled_posts'] ) ) {
			unset( $listing_posts[ $key ] );
		}
		// Check for excluded listings.
		if ( in_array( $listing_post->ID, $google_my_business_options['posting_settings']['excluded_posts'] ) ) {
			unset( $listing_posts[ $key ] );
		}
	}

	generate_listing_cells( $listing_posts );
	?>
</div>

<h3 class="schedule-section-header"><?php esc_attr_e( 'Excluded Listing Posts', 'wp-listings' ); ?></h3>
<hr>
<div id="hidden-listings-container" class="listing-cells-container" ondragover="onDragOver(event);" ondrop="onDrop(event);">
<?php
if ( ! empty( $google_my_business_options['posting_settings']['excluded_posts'] ) ) {
	$excluded_posts = get_posts(
		[
			'post_type'   => 'listing',
			'post_status' => 'publish',
			'nopaging'    => true,
			'order'       => 'DESC',
			'post__in'    => $google_my_business_options['posting_settings']['excluded_posts'],
		]
	);
	generate_listing_cells( $excluded_posts );
}
?>
</div>
<div id="clear-excluded-button-container">
	<button id="clear-excluded-button" class="button" onclick="clearExclusionList()"> <?php esc_attr_e( 'Clear all excluded listings', 'wp-listings' ); ?></button>
</div>
