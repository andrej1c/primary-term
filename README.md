Primary Tag
=================

A WordPress plugin that allows you to enter select a primary term (tag, category, etc.) for a post

## Installation

* Download into your plugins directory (e.g. /wp-content/plugins)

## Displaying primary term in your theme

To display the name of the primary term

```php
do_action( 'ac_primary_term' );
```

To display the name of the primary term as a link pointing to its archive URL

```php
do_action( 'ac_primary_term', true );
```

To retrieve the primary tag of a post for your own processing

```php
if ( class_exists( 'AC_Primary_Term' ) {
	$primary_tag_id = AC_Primary_Tag::get_primary_term( $post_id )
}
```

## Using another taxonomy, label or post meta key

All of these three variables have filters applied so you can set them without modifying the plugin code:

Example of how to change the meta key

```php
function ac_primary_tag_post_meta_key( $var ) {
	return 'your_custom_post_meta_key';
}
add_filter( 'ac_primary_tag_post_meta_key', 'ac_primary_tag_post_meta_key', 10, 1 );
```

Example of how to change the taxonomy

```php
function ac_primary_tag_taxonomy( $var ) {
	return 'category';
}
add_filter( 'ac_primary_tag_taxonomy', 'ac_primary_tag_taxonomy', 10, 1 );
```

Example of how to change the label of the dropdown

```php
function ac_primary_tag_taxonomy_nice_name( $var ) {
	return 'Post Category';
}
add_filter( 'ac_primary_tag_taxonomy_nice_name', 'ac_primary_tag_taxonomy_nice_name', 10, 1 );
```

## Known Issues

* On export to XML and subsequent import the term id may not be the same
* Not sure how to deal with a situation when a post is not tagged with a term that is selected to be primary. 
We could hook into javascript, potentially but when javascript isn't enabled in the browser we'd need to 
disable this feature as well

## Credits

Methods that add the metabox and save value submitted are largely copied and pasted from the Codex (http://codex.wordpress.org/Function_Reference/add_meta_box)
