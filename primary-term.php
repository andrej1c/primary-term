<?php
/* 
 * Plugin Name: Primary Tag
 * Description: WordPress plugin that allows post authors to select a primary tag.
 * Plugin URI: https://github.com/andrej1c/primary-term
 * Version: 1.0
 * Author: Andrej Ciho
 * Author URI: http://andrejciho.com
 */

class AC_Primary_Tag
{
	// post meta key we will use to store the primary tag id
	const POST_META_KEY = '_ac_primary_tag';
	
	// Hook into actions and filters
	static public function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save' ) );
		add_action( 'ac_primary_tag', array( __CLASS__, 'display_tag' ), 10, 1 );
		add_action( 'delete_tag', array( __CLASS__, 'delete_tag' ), 10, 3 );
		add_filter( 'get_the_terms', array( __CLASS__, 'show_primary_tag_first' ), 10, 3 );
	}
	
	static public function add_meta_box( $post_type ) {
		if ( 'post' == $post_type ) {
			add_meta_box(
				'ac_primary_tag_meta_box' ,
				_( 'Primary Tag' ),
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
		if ( 'page' == $_POST['post_type'] ) {
			if ( ! current_user_can( 'edit_page', $post_id ) ) {
				return $post_id;
			}
		} else {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return $post_id;
			}
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

		$tags = get_tags( array( 'hide_empty' => false ) );
		if ( empty( $tags ) ) {
			return;
		}
		
		$tags_a = array();
		foreach ( $tags as $tag ) {
			$tags_a[ $tag->term_id ] = $tag->name;
		}
		$none_selected = array( 0 => 'Select a Primary Tag' );
		$tags_a = $none_selected + $tags_a;

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
		_e( 'Primary Tag' );
		echo '</label> ';
		echo '<select type="text" id="ac_primary_tag_field" name="ac_primary_tag_field">';
		foreach ( $tags_a as $term_id => $term_name ) {
			$selected = $term_id == $current_value ? 'selected="selected"' : '';
			echo sprintf( '<option value="%d" %s />%s</option>', intval( $term_id ), intval( $selected ), esc_attr( $term_name ) );
		}
		echo '</select>';
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
		
		$primary_tag = get_term( $primary_tag_id, 'post_tag' );
		
		if ( false == $show_as_link ) {
			return $primary_tag->name;
		} else {
			$permalink = get_term_link( $primary_tag, 'post_tag' );
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
	static public function delete_tag( $term, $tt_id, $deleted_term ) {
		global $wpdb;
		// WordPress already verified that the user has permissions to delete the term so we'll execute the query
		$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE meta_key='%s' AND meta_value=%d", self::POST_META_KEY, $term ) );
	}
	
	/**
	 * Verifies if given term still exists
	 *
	 * @param integer $term_id
	 * @return boolean|object False if term doesn exist. Term object if it does
	 */
	static public function get_term( $term_id ) {
		$term = get_term_by( 'id',  (int) $term_id, 'post_tag' );
		if ( $term ) {
			return $term;
		} else {
			return false;
		}
	}
	
	/**
	 * Filter the list of terms attached to the given post to show the primary tag first. Useful for some breadcrumb plugins.
	 *
	 * @param array  $terms    List of attached terms.
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Name of the taxonomy.
	 */
	static public function show_primary_tag_first( $terms, $post_id, $taxonomy ) {
		if ( 'post_tag' != $taxonomy ) {
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
	
	/**
	 * Get primary tag of a post
	 * 
	 * @param integer $post_id 
	 * @return mixed|null|WP_Error Term Row from database. Will return null if $term is empty.
	 */
	static public function get_primary_tag( $post_id ) {
		return get_post_meta( $post_id, self::POST_META_KEY, true );
	}
	
	/**
	 * Delete primary tag reference for this post
	 *
	 * @param type $post_id
	 */
	static public function remove_primary_tag( $post_id ) {
		delete_post_meta( $post_id, self::POST_META_KEY );
	}
}
add_action( 'init', 'AC_Primary_Tag::init' );