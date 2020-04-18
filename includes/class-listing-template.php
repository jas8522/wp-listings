<?php
/**
 * Allows listing post type to use custom templates for single listings
 * Adapted from Single Post Template plugin by Nathan Rice (http://www.nathanrice.net/)
 * http://wordpress.org/plugins/single-post-template/
 *
 * Author: Nathan Rice
 * Author URI: http://www.nathanrice.net/
 * License: GNU General Public License v2.0
 * License URI: http://www.opensource.org/licenses/gpl-license.php
 *
 * @package WP Listings
 * @since 0.1.0
 */
class Single_Listing_Template {

	protected $templates;

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'wplistings_add_metabox' ] );
		add_action( 'save_post', [ $this, 'metabox_save' ], 1, 2 );
		add_filter( 'template_include', [ $this, 'load_listing_template' ] );

		$this->templates = [
			'listing-templates/single-listing-classical.php' => 'Classical',
			'listing-templates/single-listing-elegant.php' => 'Elegant',
			'listing-templates/single-listing-luxurious.php' => 'Luxurious',
			'listing-templates/single-listing-solid.php'   => 'Solid',
			'listing-templates/single-listing-spacious.php' => 'Spacious',
		];
		// Only availabe for the First Impression theme.
		if ( 'first-impression' === get_option( 'stylesheet' ) ) {
			$this->templates['single-listing-first-impression.php'] = 'First Impression';
		}
	}

	public function load_listing_template( $template ) {
		global $post;
		$post_meta = get_post_meta( $post->ID );

		if ( 'listing' === $post->post_type && isset( $post_meta['_wp_post_template'][0] ) ) {
			switch ( $post_meta['_wp_post_template'][0] ) {
				case 'listing-templates/single-listing-classical.php':
					return plugin_dir_path( __FILE__ ) . 'listing-templates/single-listing-classical.php';
				case 'listing-templates/single-listing-elegant.php':
					return plugin_dir_path( __FILE__ ) . 'listing-templates/single-listing-elegant.php';
				case 'listing-templates/single-listing-luxurious.php':
					return plugin_dir_path( __FILE__ ) . 'listing-templates/single-listing-luxurious.php';
				case 'listing-templates/single-listing-solid.php':
					return plugin_dir_path( __FILE__ ) . 'listing-templates/single-listing-solid.php';
				case 'listing-templates/single-listing-spacious.php':
					return plugin_dir_path( __FILE__ ) . 'listing-templates/single-listing-spacious.php';
				case 'single-listing-first-impression.php':
					return get_stylesheet_directory() . '/single-listing-first-impression.php';
			}
		}
		return $template;
	}

	public function get_listing_templates() {
		return $this->templates;
	}

	function listing_templates_dropdown() {

		global $post;

		$listing_templates = $this->get_listing_templates();

		/** Loop through templates, make them options */
		foreach ( (array) $listing_templates as $template_file => $template_name ) {
			$selected = ( $template_file == get_post_meta( $post->ID, '_wp_post_template', true ) ) ? ' selected="selected"' : '';
			$opt = '<option value="' . esc_attr( $template_file ) . '"' . $selected . '>' . esc_html( $template_name ) . '</option>';
			echo $opt;
		}

	}

	function wplistings_add_metabox( $post ) {
		add_meta_box( 'wplistings_listing_templates', __( 'Listing Template', 'wplistings' ), array( $this, 'listing_template_metabox' ), 'listing', 'side', 'high' );
	}

	function listing_template_metabox( $post ) {

		?>
		<input type="hidden" name="wplistings_single_noncename" id="wplistings_single_noncename" value="<?php echo wp_create_nonce( plugin_basename( __FILE__ ) ); ?>" />

		<label class="hidden" for="listing_template"><?php  _e( 'Listing Template', 'wp-listings' ); ?></label><br />
		<select name="_wp_post_template" id="listing_template" class="dropdown">
			<option value=""><?php _e( 'Default', 'wp-listings' ); ?></option>
			<?php $this->listing_templates_dropdown(); ?>
		</select><br />
		<?php

	}

	function metabox_save( $post_id, $post ) {

		/*
		 * Verify this came from our screen and with proper authorization,
		 * because save_post can be triggered at other times
		 */
		if ( !isset( $_POST['wplistings_single_noncename'] ) || ! wp_verify_nonce( $_POST['wplistings_single_noncename'], plugin_basename( __FILE__ ) ) )
			return $post->ID;

		/** Is the user allowed to edit the post or page? */
		if ( 'listing' == $_POST['post_type'] )
			if ( ! current_user_can( 'edit_page', $post->ID ) )
				return $post->ID;
		else
			if ( ! current_user_can( 'edit_post', $post->ID ) )
				return $post->ID;

		/** OK, we're authenticated: we need to find and save the data */

		/** Put the data into an array to make it easier to loop though and save */
		$mydata['_wp_post_template'] = $_POST['_wp_post_template'];

		/** Add values of $mydata as custom fields */
		foreach ( $mydata as $key => $value ) {
			/** Don't store custom data twice */
			if( 'revision' == $post->post_type )
				return;

			/** If $value is an array, make it a CSV (unlikely) */
			$value = implode( ',', (array) $value );

			/** Update the data if it exists, or add it if it doesn't */
			if( get_post_meta( $post->ID, $key, false ) )
				update_post_meta( $post->ID, $key, $value );
			else
				add_post_meta( $post->ID, $key, $value );

			/** Delete if blank */
			if( ! $value )
				delete_post_meta( $post->ID, $key );
		}

	}

}
