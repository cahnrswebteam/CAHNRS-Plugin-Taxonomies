<?php
/*
Plugin Name: CAHNRS Units and Topics
Version: 0.1.0
Description: Taxonomies for CAHNRS content.
Author:	CAHNRS, philcable
*/

class CAHNRSWP_Taxonomies {

	/**
	 * @var string Plugin version number.
	 */
	var $cahnrs_units_schema_version = '0.1.0';

	/**
	 * @var string Taxonomy slug for CAHNRS Units.
	 */
	var $cahnrs_units = 'cahnrs_unit'; // underscore for REST API retrieval.

	/**
	 * @var string Taxonomy slug for CAHNRS Topics.
	 */
	var $cahnrs_topics = 'topic';

	/**
	 * Fire necessary hooks.
	 */
	function __construct() {

		register_activation_hook( __FILE__, array( $this, 'cahnrs_taxonomies_activate' ) ); // include Topics here,
		add_action( 'admin_init', array( $this, 'admin_init' ) );  // here,
		add_action( 'init', array( $this, 'register_taxonomies' ), 11 );
		add_action( 'load-edit-tags.php', array( $this, 'compare_units' ), 10 );  // and here.
		add_action( 'load-edit-tags.php', array( $this, 'display_edit_tags' ), 11 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'cahnrs_unit_edit_form_fields', array( $this, 'edit_form_fields' ), 10, 2 );
		add_action( 'topic_edit_form_fields', array( $this, 'edit_form_fields' ), 10, 2 );
		add_action( 'edited_cahnrs_unit', array( $this, 'save_cahnrs_taxonomy_meta' ), 10, 2 );
		add_action( 'edited_topic', array( $this, 'save_cahnrs_taxonomy_meta' ), 10, 2 );
		add_filter( 'manage_taxonomies_for_wsuwp_people_profile_columns', array( $this, 'wsuwp_people_profile_columns' ) );
		add_filter( 'json_prepare_post', array( $this, 'json_prepare_post' ), 10, 3 );

	}

	/**
	 * Pre-load CAHNRS Units on plugin activation.
	 */
	public function cahnrs_taxonomies_activate() {

		add_option( 'cahnrs_units_schema_version', '0' );

	}

	/**
	 * Pre-load CAHNRS Units on plugin activation, part two.
	 */
	public function admin_init() {

		if ( $this->cahnrs_units_schema_version !== get_option( 'cahnrs_units_schema_version', false ) ) {
			$this->load_units();
			update_option( 'cahnrs_units_schema_version', $this->cahnrs_units_schema_version );
		}

	}

	/**
	 * Register CAHNRS taxonomies.
	 */
	public function register_taxonomies() {

		$units_args = array(
			'labels'       => array (
				'name'          => 'CAHNRS Units',
				'singular_name' => 'CAHNRS Unit',
				'search_items'  => 'Search Units',
				'all_items'     => 'All Units',
				'edit_item'     => 'Edit Unit',
				'update_item'   => 'Update Unit',
				'add_new_item'  => 'Add New Unit',
				'new_item_name' => 'New Unit Name',
				'menu_name'     => 'CAHNRS Units',
			),
			'description'  => 'Units within the College of Agricultural, Human, and Natural Resource Sciences',
			'public'       => true,
			'hierarchical' => true,
			'show_ui'      => true,
			'show_in_menu' => true,
			'query_var'    => $this->cahnrs_units,
		);

		register_taxonomy( $this->cahnrs_units, array( 'wsuwp_people_profile'/*, 'post', 'page', 'attachment'*/), $units_args );

		$topics_args = array(
			'labels'       => array(
				'name'          => 'Topics',
				'singular_name' => 'Topic',
				'search_items'  => 'Search Topics',
				'all_items'     => 'All Topics',
				'edit_item'     => 'Edit Topic',
				'update_item'   => 'Update Topic',
				'add_new_item'  => 'Add New Topic',
				'new_item_name' => 'New Topic Name',
				'menu_name'     => 'Topics',
			),
			'description'  => 'CAHNRS and Extension topics',
			'public'       => true,
			'hierarchical' => true,
			'show_ui'      => true,
			'show_in_menu' => true,
			'query_var'    => $this->cahnrs_topics,
		);

		register_taxonomy( $this->cahnrs_topics, array( 'wsuwp_people_profile'/*, 'post', 'page', 'attachment'*/ ), $topics_args );

	}

	/**
	 * Compare the current state of units and populate anything that is missing.
	 */
	public function compare_units() {

		if ( $this->cahnrs_units !== get_current_screen()->taxonomy ) {
			return;
		}

		if ( $this->cahnrs_units_schema_version !== get_option( 'cahnrs_units_schema_version', false ) ) {
			$this->load_units();
			update_option( 'cahnrs_units_schema_version', $this->cahnrs_units_schema_version );
		}

	}

	/**
	 * Load pre-configured units when requested.
	 */
	public function load_units() {

		// Get our current master list of units.
		$master_units = $this->get_cahnrs_units();

		// Get our current list of top level units.
		$current_units = get_terms( $this->cahnrs_units, array( 'hide_empty' => false ) );
		$current_units = wp_list_pluck( $current_units, 'name' );

		foreach ( $master_units as $unit => $child_units ) {

			$parent_id = false;

			// If the parent unit is not a term yet, insert it.
			if ( ! in_array( $unit, $current_units ) ) {
				$new_term    = wp_insert_term( $unit, $this->cahnrs_units, array( 'parent' => 0 ) );
				$parent_id = $new_term['term_id'];
			}

			// Loop through the parent's children to check term existence.
			foreach( $child_units as $child_unit ) {
				if ( ! in_array( $child_unit, $current_units ) ) {
					if ( ! $parent_id ) {
						$parent = get_term_by( 'name', $unit, $this->cahnrs_units );
						if ( isset( $parent->id ) ) {
							$parent_id = $parent->id;
						} else {
							$parent_id = 0;
						}
					}
					wp_insert_term( $child_unit, $this->cahnrs_units, array( 'parent' => $parent_id ) );
				}
			}
		}

	}

	/**
	 * Maintain an array of current CAHNRS units.
	 *
	 * @return array Current CAHNRS units.
	 */
	public function get_cahnrs_units() {

		$units = array();

		$response = wp_remote_get( 'http://api.wpdev.cahnrs.wsu.edu/?service=units' );
		if ( ! is_wp_error( $response ) ) {
			$body = wp_remote_retrieve_body( $response );
			if ( ! is_wp_error( $body )  ) {
				$units = json_decode( $body );
			}
		}

		return $units;

	}

	/**
	 * Display a different view for non-super admins.
	 */
	public function display_edit_tags() {

		if ( ( $this->cahnrs_topics !== get_current_screen()->taxonomy && $this->cahnrs_units !== get_current_screen()->taxonomy ) || current_user_can( 'manage_network' ) ) {
			return;
		}

		// Set up the page.
		global $title;
		$taxonomy = get_current_screen()->taxonomy;
		$tax = get_taxonomy( $taxonomy );
		$title = $tax->labels->name;
		require_once( ABSPATH . 'wp-admin/admin-header.php' );
		echo '<div class="wrap nosubsub"><h2>' . $title . '</h2>';
		echo '<p><em>' . wp_count_terms( $taxonomy ) . ' items</em></p>';

		$parent_terms = get_terms( $taxonomy, array( 'hide_empty' => false, 'parent' => '0' ) );

		foreach ( $parent_terms as $term ) {

			$child_terms = get_terms( $taxonomy, array( 'hide_empty' => false, 'parent' => $term->term_id ) );

			echo '<h3>' . esc_html( $term->name ) . '</h3>';

			echo '<ul>';

			foreach ( $child_terms as $child ) {

				$child = sanitize_term( $child, $taxonomy );
				$child_link = get_term_link( $child, $taxonomy );
				$grandchild_terms = get_terms( $taxonomy, array( 'hide_empty' => false, 'parent' => $child->term_id ) );

				echo '<li><h4><a href="' . esc_url( $child_link ) . '">' . esc_html( $child->name ) . '</a> (' . $child->count . ')</h4>';

				if ( ! empty( $grandchild_terms ) ) {

					echo '<ul>';

					foreach ( $grandchild_terms as $grandchild ) {

						$grandchild = sanitize_term( $grandchild, $taxonomy );
						$grandchild_link = get_term_link( $grandchild, $taxonomy );

						echo '<li><a href="' . esc_url( $grandchild_link ) . '">' . esc_html( $grandchild->name ) . '</a> (' . $grandchild->count . ')</li>';

					}

					echo '</ul>';

				}

				echo '</li>';

			}

			echo '</ul>';

		}

		// Close the page.
		echo '</div>';

		include( ABSPATH . 'wp-admin/admin-footer.php' );

		die();

	}

	/**
	 * Enqueue stylesheets.
	 */
	public function admin_enqueue_scripts( $hook ) {

		if ( 'edit-tags.php' !== $hook && 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		if ( 'post-new.php' === $hook || 'post.php' === $hook ) {
			wp_enqueue_style( 'cahnrs-taxonomies-style', plugins_url( 'css/cahnrs-taxonomies-edit-post.css', __FILE__ ) );
			//wp_enqueue_script( 'cahnrs-units-script', plugins_url( 'js/cahnrs-units.js', __FILE__ ) );
		}
		
		if ( ( $this->cahnrs_units === get_current_screen()->taxonomy || $this->cahnrs_topics === get_current_screen()->taxonomy ) && ! current_user_can( 'manage_network' ) ) {
			wp_enqueue_style( 'cahnrs-taxonomies-edit-tags-style', plugins_url( 'css/cahnrs-taxonomies-edit-tags.css', __FILE__ ) );
		}

	}

	/**
	 * Add a metabox for selecting the leader of a unit.
	 */
	public function edit_form_fields( $term, $taxonomy ) {

		if ( post_type_exists( 'wsuwp_people_profile' ) ) {

			echo '<tr class="form-field">
			<th scope="row" valign="top"><label for="item_leader">Leader</label></th>
			<td>';
			echo '<select name="item_leader" id="item_leader">
			<option value="">Select</option>';

			$item_posts_args = array(
				'posts_per_page' => -1,
				'post_type' => 'wsuwp_people_profile',
				'tax_query' => array(
					array(
						'taxonomy' => $taxonomy,
						'field'    => 'id',
						'terms'    => $term->term_id,
					),
				),
				'order'     => 'ASC',
				'orderby'   => 'meta_value',
				'meta_key'  => '_wsuwp_profile_ad_name_last',
			);

			$item_posts = get_posts( $item_posts_args );

			if ( $item_posts ) :
				foreach ( $item_posts as $post ) :
					$leader = get_post_meta( $post->ID, '_wsuwp_profile_leader_of', true );
					echo '<option value="' . $post->ID . '" ';
					if ( $leader === $term->slug ) echo 'selected="selected"';
					echo '>' . get_the_title( $post->ID ) . '</option>';
				endforeach;
			endif;

			echo '</select>
			</td>
			</tr>';

			if ( $this->cahnrs_units === $taxonomy ) { // Limit this to the CAHNRS Unit taxonomy for now
				// Co- or assistant leaders
				echo '<tr class="form-field">
				<th scope="row" valign="top"><label for="item_co_leader">Co- or Assistant Leader(s)</label></th>
				<td>';
				echo '<select name="item_co_leader[]" id="item_co_leader" multiple style="min-height:300px;">';

				if ( $item_posts ) :
					foreach ( $item_posts as $post ) :
						$co_leader = get_post_meta( $post->ID, '_wsuwp_profile_co_leader_of', true );
						echo '<option value="' . $post->ID . '" ';
						if ( $co_leader === $term->slug ) echo 'selected="selected"';
						echo '>' . get_the_title( $post->ID ) . '</option>';
					endforeach;
				endif;

				echo '</select>
				</td>
				</tr>';
			}

		}

	}

	/**
	 * Save the leader meta.
	 */
	public function save_cahnrs_taxonomy_meta( $term_id ) {

		if ( post_type_exists( 'wsuwp_people_profile' ) ) {

			// Get the term.
			$term = get_term( $term_id, $_POST['taxonomy'] );

			// Loop args.
			$updater_args = array(
				'post_type' => 'wsuwp_people_profile',
				'posts_per_page' => -1,
				'tax_query' => array(
					array(
						'taxonomy' => $_POST['taxonomy'],
						'field'    => 'id',
						'terms'    => $term_id,
					),
				),
			);

			$leader_args = $updater_args;
			$co_leader_args = $updater_args;

			// A leader was selected.
			if ( isset( $_POST['item_leader'] ) && is_numeric( $_POST['item_leader'] ) ) {

				// Save the term's slug as appropriate meta value to the selected profile.
				add_post_meta( $_POST['item_leader'], '_wsuwp_profile_leader_of', sanitize_text_field( $term->slug ) );

				// Delete leader meta value from other profiles.
				$leader_args['post__not_in'] = array( $_POST['item_leader'] );
				$leader_update_query = new WP_Query( $leader_args );
				if ( $leader_update_query->have_posts() ) {
					while( $leader_update_query->have_posts() ) {
						$leader_update_query->the_post();
						delete_post_meta( $leader_update_query->post->ID, '_wsuwp_profile_leader_of' );
					}
				}
				wp_reset_postdata();
				
			} else { // No leader selected, clear out any leader meta values that match this item's slug.

				$leader_update_query = new WP_Query( $updater_args );
				if ( $leader_update_query->have_posts() ) {
					while( $leader_update_query->have_posts() ) {
						$leader_update_query->the_post();
						if ( sanitize_text_field( $term->slug ) === get_post_meta( $leader_update_query->post->ID, '_wsuwp_profile_leader_of', true ) ) {
							delete_post_meta( $leader_update_query->post->ID, '_wsuwp_profile_leader_of' );
						}
					}
				}
				wp_reset_postdata();

			} // $_POST['item_leader']

			// A co-leader was selected
			if ( isset( $_POST['item_co_leader'] ) ) {

				$co_leaders = array();

				// Save the term's slug as appropriate meta value to the selected profile(s).
				foreach ( $_POST['item_co_leader'] as $profile_id ) {
					if ( is_numeric( $profile_id ) ) {
						add_post_meta( $profile_id, '_wsuwp_profile_co_leader_of', sanitize_text_field( $term->slug ) );
						$co_leaders[] = $profile_id;
					}
				}

				// Delete co-leader meta value from other profiles.
				$co_leader_args['post__not_in'] = $co_leaders;
				$co_leader_update_query = new WP_Query( $co_leader_args );
				if ( $co_leader_update_query->have_posts() ) {
					while( $co_leader_update_query->have_posts() ) {
						$co_leader_update_query->the_post();
						delete_post_meta( $co_leader_update_query->post->ID, '_wsuwp_profile_co_leader_of' );
					}
				}
				wp_reset_postdata();
				
			} else { // No co-leader selected, clear out any co-leader meta values that match this item's slug.

				$co_leader_update_query = new WP_Query( $updater_args );
				if ( $co_leader_update_query->have_posts() ) {
					while( $co_leader_update_query->have_posts() ) {
						$co_leader_update_query->the_post();
						if ( sanitize_text_field( $term->slug ) === get_post_meta( $co_leader_update_query->post->ID, '_wsuwp_profile_co_leader_of', true ) ) {
							delete_post_meta( $co_leader_update_query->post->ID, '_wsuwp_profile_co_leader_of' );
						}
					}
				}
				wp_reset_postdata();

			} // $_POST['item_co_leader']

		} // post_type_exists( 'wsuwp_people_profile' )

	}

	/**
	 * Show a column for CAHNRS Units and Topics on the "All Profiles" screen.
	 */
	public function wsuwp_people_profile_columns( $taxonomies ) {

    $taxonomies[] = $this->cahnrs_units;
		$taxonomies[] = $this->cahnrs_topics;

    return $taxonomies;

	}

	/**
	 * Include meta in the REST API output.
	 */
	public function json_prepare_post( $post_response, $post, $context ) {

		if ( 'wsuwp_people_profile' !== $post['post_type'] ) {
			return $post_response;
		}

		$post_response['_wsuwp_profile_leader_of'] = get_post_meta( $post['ID'], '_wsuwp_profile_leader_of', true );
		$post_response['_wsuwp_profile_co_leader_of'] = get_post_meta( $post['ID'], '_wsuwp_profile_co_leader_of', true );

		return $post_response;

	}

}

new CAHNRSWP_Taxonomies();