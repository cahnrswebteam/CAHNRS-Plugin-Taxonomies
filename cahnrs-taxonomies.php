<?php
/*
Plugin Name: CAHNRS Units and Topics
Version: 0.1.1
Description: Taxonomies for CAHNRS content.
Author:	CAHNRS, philcable
*/

class CAHNRSWP_Taxonomies {

	/**
	 * @var string Plugin version number.
	 */
	var $cahnrs_units_schema_version = '0.1.0';

	/**
	 * @var string Plugin version number.
	 */
	var $cahnrs_topics_schema_version = '0.1.0';

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

		register_activation_hook( __FILE__, array( $this, 'cahnrs_taxonomies_activate' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'init', array( $this, 'register_taxonomies' ), 11 );
		add_action( 'load-edit-tags.php', array( $this, 'compare' ), 10 );
		add_action( 'load-edit-tags.php', array( $this, 'display_edit_tags' ), 11 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

	}

	/**
	 * Pre-load CAHNRS taxonomies on plugin activation.
	 */
	public function cahnrs_taxonomies_activate() {

		add_option( 'cahnrs_units_schema_version', '0' );
		add_option( 'cahnrs_topics_schema_version', '0' );

	}

	/**
	 * Pre-load CAHNRS taxonomies on plugin activation, part two.
	 */
	public function admin_init() {

		if ( $this->cahnrs_units_schema_version !== get_option( 'cahnrs_units_schema_version', false ) ) {
			$this->load_units();
			update_option( 'cahnrs_units_schema_version', $this->cahnrs_units_schema_version );
		}
		
		if ( $this->cahnrs_topics_schema_version !== get_option( 'cahnrs_topics_schema_version', false ) ) {
			$this->load_topics();
			update_option( 'cahnrs_topics_schema_version', $this->cahnrs_topics_schema_version );
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
			'show_in_rest' => true,
			'query_var'    => $this->cahnrs_units,
		);

		register_taxonomy( $this->cahnrs_units, array(/*'post', 'page', 'attachment'*/), $units_args );

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
			'show_in_rest' => true,
			'query_var'    => $this->cahnrs_topics,
		);

		register_taxonomy( $this->cahnrs_topics, array(/*'post', 'page', 'attachment'*/), $topics_args );

	}

	/**
	 * Compare the current state of taxonomies and populate anything that is missing.
	 */
	public function compare() {

		if ( $this->cahnrs_topics !== get_current_screen()->taxonomy || $this->cahnrs_topics !== get_current_screen()->taxonomy ) {
			return;
		}

		if ( $this->cahnrs_units === get_current_screen()->taxonomy ) {
			if ( $this->cahnrs_units_schema_version !== get_option( 'cahnrs_units_schema_version', false ) ) {
				$this->load_units();
				update_option( 'cahnrs_units_schema_version', $this->cahnrs_units_schema_version );
			}
		}

		if ( $this->cahnrs_topics === get_current_screen()->taxonomy ) {
			if ( $this->cahnrs_topics_schema_version !== get_option( 'cahnrs_topics_schema_version', false ) ) {
				$this->load_topics();
				update_option( 'cahnrs_topics_schema_version', $this->cahnrs_topics_schema_version );
			}
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
				$new_term = wp_insert_term( $unit, $this->cahnrs_units, array( 'parent' => 0 ) );
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
	 * Load pre-configured topics when requested.
	 */
	public function load_topics() {

		// Get our current master list of topics.
		$master_list = $this->get_cahnrs_topics();

		// Get our current list of top level parents.
		$level1_exist = get_terms( $this->cahnrs_topics, array( 'hide_empty' => false, 'parent' => '0' ) );
		$level1_assign = array();
		foreach( $level1_exist as $level1 ) {
			$level1_assign[ $level1->name ] = array( 'term_id' => $level1->term_id );
		}

		$level1_names = array_keys( $master_list );
		/**
		 * Look for mismatches between the master list and the existing parent terms list.
		 *
		 * In this loop:
		 *
		 *     * $level1_names    array of top level parent names.
		 *     * $level1_name     string containing a top level category.
		 *     * $level1_children array containing all of the current parent's child arrays.
		 *     * $level1_assign   array of top level parents that exist in the database with term ids.
		 */
		foreach( $level1_names as $level1_name ) {
			if ( ! array_key_exists( $level1_name, $level1_assign ) ) {
				$new_term = wp_insert_term( $level1_name, $this->cahnrs_topics, array( 'parent' => '0' ) );
				if ( ! is_wp_error( $new_term ) ) {
					$level1_assign[ $level1_name ] = array( 'term_id' => $new_term['term_id'] );
				}
			}
		}

		/**
		 * Process the children of each top level parent.
		 *
		 * In this loop:
		 *
		 *     * $level1_names    array of top level parent names.
		 *     * $level1_name     string containing a top level category.
		 *     * $level1_children array containing all of the current parent's child arrays.
		 *     * $level2_assign   array of this parent's second level categories that exist in the database with term ids.
		 */
		foreach( $level1_names as $level1_name ) {

			$level2_exists = get_terms( $this->cahnrs_topics, array( 'hide_empty' => false, 'parent' => $level1_assign[ $level1_name ]['term_id'] ) );
			$level2_assign = array();

			foreach( $level2_exists as $level2 ) {
				$level2_assign[ $level2->name ] = array( 'term_id' =>  $level2->term_id );
			}

			$level2_names = array_keys( $master_list[ $level1_name ] );
			/**
			 * Look for mismatches between the expected and real children of the current parent.
			 *
			 * In this loop:
			 *
			 *     * $level2_names    array of the current parent's child level names.
			 *     * $level2_name     string containing a second level category.
			 *     * $level2_children array containing the current second level category's children. Unused in this context.
			 *     * $level2_assign   array of this parent's second level categories that exist in the database with term ids.
			 */
			foreach( $level2_names as $level2_name ) {
				if ( ! array_key_exists( $level2_name, $level2_assign ) ) {
					$new_term = wp_insert_term( $level2_name, $this->cahnrs_topics, array( 'parent' => $level1_assign[ $level1_name ]['term_id'] ) );
					if ( ! is_wp_error( $new_term ) ) {
						$level2_assign[ $level2_name ] = array( 'term_id' => $new_term['term_id'] );
					}
				}
			}

			/**
			 * Look for mismatches between second and third level category relationships.
			 */
			foreach( $level2_names as $level2_name ) {
				$level3_exists = get_terms( $this->cahnrs_topics, array( 'hide_empty' => false, 'parent' => $level2_assign[ $level2_name ]['term_id'] ) );
				$level3_exists = wp_list_pluck( $level3_exists, 'name' );

				$level3_names = $master_list[ $level1_name ][ $level2_name ];
				foreach( $level3_names as $level3_name ) {
					if ( ! in_array( $level3_name, $level3_exists ) ) {
						wp_insert_term( $level3_name, $this->cahnrs_topics, array( 'parent' => $level2_assign[ $level2_name ]['term_id'] ) );
					}
				}
			}

		}

	}

	/**
	 * Maintain an array of current CAHNRS units.
	 *
	 * @return array Current CAHNRS units.
	 */
	public function get_cahnrs_topics() {

		$topics = array();

		$response = wp_remote_get( 'http://api.wpdev.cahnrs.wsu.edu/?service=topics' );
		if ( ! is_wp_error( $response ) ) {
			$body = wp_remote_retrieve_body( $response );
			if ( ! is_wp_error( $body )  ) {
				$topics = json_decode( $body, true );
			}
		}

		return $topics;

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

}

new CAHNRSWP_Taxonomies();