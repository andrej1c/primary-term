<?php
/* 
 * Plugin Name: Primary Tag
 * Author: Andrej Ciho
 */

class AC_Primary_Tag
{
	// post meta key we will use to store the primary tag id
	const POST_META_KEY      = '_ac_primary_tag';
	const TAXONOMY           = 'post_tag'; // tested with 'category' and 'post_tag'
	const TAXONOMY_NICE_NAME = 'Tag';
	
	// Hook into actions and filters
	static public function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save' ) );
		add_action( 'ac_primary_tag', array( __CLASS__, 'display_tag' ), 10, 1 );
		add_action( 'delete_term', array( __CLASS__, 'delete_term' ), 10, 3 );
		add_filter( 'get_the_terms', array( __CLASS__, 'show_primary_tag_first' ), 10, 3 );
		add_filter( 'wp_dropdown_cats', array( __CLASS__, 'append_0th_option' ), 10, 2 );
	}
	
	static public function add_meta_box( $post_type ) {
		if ( 'post' == $post_type ) {
			add_meta_box(
				'ac_primary_tag_meta_box' ,
				_( 'Primary ' . self::TAXONOMY_NICE_NAME ),
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
		if ( ! isset( $_POST['ac_primary_tag_inner_custom_box_nonce'] ) ) {
			return $post_id;
		}

		$nonce = $_POST['ac_primary_tag_inner_custom_box_nonce'];

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $nonce, 'ac_primary_tag_inner_custom_box' ) ) {
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
		$mydata = sanitize_text_field( $_POST['ac_primary_tag_field'] );
		$mydata = $_POST['ac_primary_tag_field'];
		// Update the meta field.
		update_post_meta( $post_id, self::POST_META_KEY, $mydata );
	}
	
	/**
	 * Render Meta Box content.
	 *
	 * @param WP_Post $post The post object.
	 */
	static public function render_meta_box_content( $post ) {
	
		// Add an nonce field so we can check for it later.
		wp_nonce_field( 'ac_primary_tag_inner_custom_box', 'ac_primary_tag_inner_custom_box_nonce' );

		// Use get_post_meta to retrieve an existing value from the database.
		$current_value = self::get_primary_tag( $post->ID );
		
		// Check if term still exists
		if ( ! empty( $current_value ) ) {
			$exists = self::get_term( $current_value );

			// Delete from this post if term no longer exists
			if ( empty( $exists ) ) {
				self::remove_primary_tag( $post->ID );
				$current_value = 0;
			}
		}
		
		// Display the form, using the current value.
		echo '<label for="ac_primary_tag_field">';
		_e( 'Primary ' . self::TAXONOMY_NICE_NAME );
		echo '</label> ';
		wp_dropdown_categories( array(
			'taxonomy'     => self::TAXONOMY,
			'hide_empty'   => false,
			'hierarchical' => true,
			'class'        => 'ac_primary_term_dropdown',
			'selected'     => $current_value,
		) );
	}
	
	static public function display_tag( $show_as_link = false ) {
		echo self::get_display_tag( $show_as_link );
	}
	
	static public function get_display_tag( $show_as_link = false ) {
		// We're assuming this is happening within a loop as it is tied to a post
		global $post;
		if ( empty( $post ) ) {
			return false;
		}
		
		$primary_tag_id = self::get_primary_tag( $post->ID );
		
		if ( empty( $primary_tag_id ) ) {
			return;
		}
		
		$primary_tag = get_term( $primary_tag_id, self::TAXONOMY );
		
		if ( empty( $primary_tag ) ) {
			return;
		}
		
		if ( false == $show_as_link ) {
			return $primary_tag->name;
		} else {
			$permalink = get_term_link( $primary_tag, self::TAXONOMY );
			return sprintf( '<a href="%s">%s</a>', $permalink, $primary_tag->name );
		}
	}

	/*
	 * Fires after a tag is deleted so we can remove the primary tag from posts
	 * 
	 * @param int     $term         Term ID.
	 * @param int     $tt_id        Term taxonomy ID.
	 * @param mixed   $deleted_term Copy of the already-deleted term, in the form specified
	 *                              by the parent function. WP_Error otherwise.
	 */
	static public function delete_term( $term, $tt_id, $taxonomy, $deleted_term ) {
		if ( self::TAXONOMY != $taxonomy ) {
			return;
		}

		global $wpdb;
		// WordPress already verified that the user has permissions to delete the term so we'll execute the query
		$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE meta_key='%s' AND meta_value=%d", self::POST_META_KEY, $term ) );
	}
	
	static public function get_term( $term_id ) {
		$term = get_term_by( 'id',  (int) $term_id, self::TAXONOMY );
		if ( $term ) {
			return $term;
		} else {
			return false;
		}
	}

	/**
	* Filter the list of terms attached to the given post.
	*
	* @param array  $terms    List of attached terms.
	* @param int    $post_id  Post ID.
	* @param string $taxonomy Name of the taxonomy.
	*/
	static public function show_primary_tag_first( $terms, $post_id, $taxonomy ) {
		if ( self::TAXONOMY != $taxonomy ) {
			return $terms;
		}
		$current_value = self::get_primary_tag( $post_id );

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
		$select->setAttribute( 'name', 'ac_primary_tag_field' );
		$select->setAttribute( 'id',   'ac_primary_tag_field' );

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
	 * Get primary tag of a post
	 * 
	 * @param integer $post_id 
	 * @return mixed|null|WP_Error Term Row from database. Will return null if $term is empty.
	 */
	static public function get_primary_tag( $post_id ) {
		return get_post_meta( $post_id, self::POST_META_KEY, true );
	}
	
	static public function remove_primary_tag( $post_id ) {
		delete_post_meta( $post_id, self::POST_META_KEY );
	}
}
add_action( 'init', 'AC_Primary_Tag::init' );