Primary Tag
=================

A WordPress plugin that allows you to enter select a primary tag for a post.

## Installation

* Download into your plugins directory (e.g. /wp-content/plugins)

## Displaying primary tag in your theme

To display the name of the primary tag

```php
do_action( 'ac_primary_tag' );
```

To display the name of the primary tag as a link

```php
do_action( 'ac_primary_tag', true );
```

To retrieve the primary tag of a post for your own processing

```php
if ( class_exists( 'AC_Primary_Tag' ) {
	$primary_tag_id = AC_Primary_Tag::get_primary_tag( $post_id )
}
```

## Potential improvements

* Could be applied to any taxonomy. Tested with tags only so far. Needs to be tested with hierarchical ones. (Branch any_taxonomy is 90% there for hierarchical taxonomies)

## Known Issues

* On export to XML and subsequent import the term id may not be the same
* Not sure how to deal with a situation when a post is not tagged with a term that is selected to be primary. 
We could hook into javascript, potentially but when javascript isn't enabled in the browser we'd need to 
disable this feature as well

## Credits

Methods that add the metabox and save value submitted are largely copied and pasted from the Codex (http://codex.wordpress.org/Function_Reference/add_meta_box)
