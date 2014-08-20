<?php
/* 
 * Plugin Name: Primary Term
 * Description: WordPress plugin that allows post authors to select a primary term for a post.
 * Plugin URI: https://github.com/andrej1c/primary-term
 * Version: 1.0
 * Author: Andrej Ciho
 * Author URI: http://andrejciho.com
 */

class AC_Primary_Term
{
	static $post_meta_key;
	static $taxonomy;
	static $taxonomy_nice_name;

	// Hook into actions and filters
	static public function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save' ) );
		add_action( 'ac_primary_term', array( __CLASS__, 'display_term' ), 10, 1 );
		add_action( 'delete_term', array( __CLASS__, 'delete_term' ), 10, 3 );
		add_filter( 'get_the_terms', array( __CLASS__, 'show_primary_term_first' ), 10, 3 );
		add_filter( 'wp_dropdown_cats', array( __CLASS__, 'append_0th_option' ), 10, 2 );

		self::$post_meta_key      = apply_filters( 'ac_primary_tag_post_meta_key', '_ac_primary_term' );
		self::$taxonomy           = apply_filters( 'ac_primary_tag_taxonomy', 'post_tag' ); // tested with 'category' and 'post_tag'
		self::$taxonomy_nice_name = apply_filters( 'ac_primary_tag_taxonomy_nice_name', 'Tag' );
	}
	
	static public function add_meta_box( $post_type ) {
		if ( 'post' == $post_type ) {
			add_meta_box(
				'ac_primary_term_meta_box' ,
				_( 'Primary ' . self::$taxonomy_nice_name ),
				array( __CLASS__, 'render_meta_box_content' ),
				$post_type,
				'advanced',
				'high'
			);
		}
	}
	
	static public function save( $post_id ) {
		/*
		 * We need to verify this came from the our screen and with proper authorization,
		 * because save_post can be triggered at other times.
		 */

		// Check if our nonce is set.
		if ( ! isset( $_POST['ac_primary_term_inner_custom_box_nonce'] ) ) {
			return $post_id;
		}

		$nonce = $_POST['ac_primary_term_inner_custom_box_nonce'];

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $nonce, 'ac_primary_term_inner_custom_box' ) ) {
			return $post_id;
		}

		// If this is an autosave, our form has not been submitted,
		// so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		// Check the user's permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $post_id;
		}

		/* OK, its safe for us to save the data now. */

		// Sanitize the user input.
		$mydata = sanitize_text_field( $_POST['ac_primary_term_field'] );
		$mydata = $_POST['ac_primary_term_field'];
		// Update the meta field.
		update_post_meta( $post_id, self::$post_meta_key, $mydata );
	}
	
	/**
	 * Render Meta Box content.
	 *
	 * @param WP_Post $post The post object.
	 */
	static public function render_meta_box_content( $post ) {
	
		// Add an nonce field so we can check for it later.
		wp_nonce_field( 'ac_primary_term_inner_custom_box', 'ac_primary_term_inner_custom_box_nonce' );

		// Use get_post_meta to retrieve an existing value from the database.
		$current_value = self::get_primary_term( $post->ID );
		
		// Check if term still exists
		if ( ! empty( $current_value ) ) {
			$exists = self::get_term( $current_value );

			// Delete from this post if term no longer exists
			if ( empty( $exists ) ) {
				self::remove_primary_term( $post->ID );
				$current_value = 0;
			}
		}
		
		// Display the form, using the current value.
		echo '<label for="ac_primary_term_field">';
		_e( 'Primary ' . self::$taxonomy_nice_name );
		echo '</label> ';
		wp_dropdown_categories( array(
			'taxonomy'     => self::$taxonomy,
			'hide_empty'   => false,
			'hierarchical' => true,
			'class'        => 'ac_primary_term_dropdown',
			'selected'     => $current_value,
		) );
	}
	
	static public function display_term( $show_as_link = false ) {
		echo self::get_display_term( $show_as_link );
	}
	
	static public function get_display_term( $show_as_link = false ) {
		// We're assuming this is happening within a loop as it is tied to a post
		global $post;
		if ( empty( $post ) ) {
			return false;
		}
		
		$primary_term_id = self::get_primary_term( $post->ID );
		
		if ( empty( $primary_term_id ) ) {
			return;
		}
		
		$primary_term = get_term( $primary_term_id, self::$taxonomy );
		
		if ( empty( $primary_term ) ) {
			return;
		}
		
		if ( false == $show_as_link ) {
			return $primary_term->name;
		} else {
			$permalink = get_term_link( $primary_term, self::$taxonomy );
			return sprintf( '<a href="%s">%s</a>', $permalink, $primary_term->name );
		}
	}

	/*
	 * Fires after a term is deleted so we can remove the primary term from posts
	 * 
	 * @param int     $term         Term ID.
	 * @param int     $tt_id        Term taxonomy ID.
	 * @param mixed   $deleted_term Copy of the already-deleted term, in the form specified
	 *                              by the parent function. WP_Error otherwise.
	 */
	static public function delete_term( $term, $tt_id, $taxonomy, $deleted_term ) {
		if ( self::$taxonomy != $taxonomy ) {
			return;
		}

		global $wpdb;
		// WordPress already verified that the user has permissions to delete the term so we'll execute the query
		$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE meta_key='%s' AND meta_value=%d", self::$post_meta_key, $term ) );
	}
	
	/**
	 * Verifies if given term still exists
	 *
	 * @param integer $term_id
	 * @return boolean|object False if term doesn exist. Term object if it does
	 */
	static public function get_term( $term_id ) {
		$term = get_term_by( 'id',  (int) $term_id, self::$taxonomy );
		if ( $term ) {
			return $term;
		} else {
			return false;
		}
	}

	/**
	 * Filter the list of terms attached to the given post to show the primary term first. Useful for some breadcrumb plugins.
	 *
	 * @param array  $terms    List of attached terms.
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Name of the taxonomy.
	 */
	static public function show_primary_term_first( $terms, $post_id, $taxonomy ) {
		if ( self::$taxonomy != $taxonomy ) {
			return $terms;
		}
		$current_value = self::get_primary_term( $post_id );

		// if no terms, return
		if ( empty( $terms ) ) {
			return $terms;
		}

		$primary_term = array();
		$all_other_terms = array();
		foreach ( $terms as $term ) {
			if ( $current_value == $term->term_id ) {
				$primary_term[] = $term;
			} else {
				$all_other_terms[] = $term;
			}
		}

		// If primary term wasn't among terms the post is tagged with
		if ( empty( $primary_term ) ) {
			// We could tag it for the user at this point but that that could cause confusion
			return $terms;
		}

		// merge 
		return array_merge( $primary_term, $all_other_terms );
	}

	static public	function append_0th_option( $output, $r ) {
		if ( 'ac_primary_term_dropdown' != $r['class'] ) {
			return $output;
		}

		$doc = new DOMDocument();
		$output = str_replace( '&nbsp;', '-', $output );
		$doc->loadXML( $output );

		$parent_path = '//select';
		$next_path = '//select/*[1]';

		// Find the parent node
		$xpath = new DomXPath( $doc );

		// Find parent node
		$parent = $xpath->query( $parent_path );

		// Append name and id to select
		$select = $doc->getElementById( 'cat' );
		$select = $xpath->query( "//*[@id='cat']" )->item( 0 );
		$select->setAttribute( 'name', 'ac_primary_term_field' );
		$select->setAttribute( 'id',   'ac_primary_term_field' );

		// new node will be inserted before this node
		$next = $xpath->query( $next_path );

		// Create the new element
		$element = $doc->createElement( 'option', 'Please select a default term' );

		$domAttribute = $doc->createAttribute( 'value' );

		// Value for the created attribute
		$domAttribute->value = '0';

		// Don't forget to append it to the element
		$element->appendChild( $domAttribute );

		// Insert the new element
		$parent->item( 0 )->insertBefore( $element, $next->item( 0 ) );

		return $doc->saveXML();
	}

	/**
	 * Get primary term of a post
	 * 
	 * @param integer $post_id 
	 * @return mixed|null|WP_Error Term Row from database. Will return null if $term is empty.
	 */
	static public function get_primary_term( $post_id ) {
		return get_post_meta( $post_id, self::$post_meta_key, true );
	}
	
	/**
	 * Delete primary term reference for this post
	 *
	 * @param type $post_id
	 */
	static public function remove_primary_term( $post_id ) {
		delete_post_meta( $post_id, self::$post_meta_key );
	}
}
add_action( 'init', 'AC_Primary_Term::init' );
