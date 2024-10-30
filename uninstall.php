<?php
// Check that code was called from WordPress with
// uninstallation constant declared
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) )
	exit;

$my_custom_posts = get_posts( array( 'post_type' => 'bug-library-bugs', 'posts_per_page' => -1 ) );

foreach( $my_custom_posts as $my_post ) {
	// Delete's each post.
	wp_delete_post( $my_post->ID, true);
}

$taxonomies = array( 'bug-library-products', 'bug-library-status', 'bug-library-types', 'bug-library-priority' );

foreach( $taxonomies as $taxonomy ) {
	$terms = get_terms( $taxonomy );
	$count = count( $terms );
	if ( $count > 0 ){
		foreach ( $terms as $term ) {
			wp_delete_term( $term->term_id, $taxonomy );
		}
	}
}

delete_option( 'BugLibraryGeneral' );

