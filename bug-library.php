<?php
/*
Plugin Name: Bug Library
Plugin URI: https://ylefebvre.github.io/wordpress-plugins/bug-library/
Description: Display bug manager on pages with a variety of options
Version: 2.1.4
Author: Yannick Lefebvre
Author URI: http://ylefebvre.github.io/
Text Domain: bug-library

A plugin for the blogging MySQL/PHP-based WordPress.
Copyright 2024 Yannick Lefebvre

SVG Plugin icon by Free Preloaders (https://freeicons.io/life-style-and-business/eggbirth-cancel-bug-icon-5812)

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.

You can also view a copy of the HTML version of the GNU General Public
License at http://www.gnu.org/copyleft/gpl.html

I, Yannick Lefebvre, can be contacted via e-mail at ylefebvre@gmail.com
*/

global $wpdb;

$pagehooktop          = "";
$pagehookstylesheet   = "";
$pagehookinstructions = "";

/*********************************** Bug Library Class *****************************************************************************/
class bug_library_plugin {

	//constructor of class, PHP4 compatible construction for backward compatibility
	function __construct() {

		$newoptions = get_option( 'BugLibraryGeneral', "" );

		if ( $newoptions == "" ) {
			$this->bl_reset_gen_settings( 'return_and_set' );
		}

		// Functions to be called when plugin is activated and deactivated
		register_activation_hook( __FILE__, array( $this, 'bl_install' ) );
		register_deactivation_hook( __FILE__, array( $this, 'bl_uninstall' ) );

		//add filter for WordPress 2.8 changed backend box system !
		add_filter( 'screen_layout_columns', array( $this, 'on_screen_layout_columns' ), 10, 2 );
		//register callback for admin menu  setup
		add_action( 'admin_menu', array( $this, 'on_admin_menu' ) );

		add_action( 'admin_init', array( $this, 'admin_init' ) );
		//register the callback been used if options of page been submitted and needs to be processed
		add_action( 'admin_post_save_bug_library_general', array( $this, 'on_save_changes_general' ) );
		add_action( 'admin_post_save_bug_library_stylesheet', array( $this, 'on_save_changes_stylesheet' ) );

		// Add short codes
		add_shortcode( 'bug-library', array( $this, 'bug_library_func' ) );

		// Function to print information in page header when plugin present
		add_action( 'wp_head', array( $this, 'bl_page_header' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'bl_admin_header' ) );

		add_action( 'init', array( $this, 'my_custom_taxonomies' ), 0 );
		add_action( 'init', array( $this, 'create_bug_post_type' ) );

		add_action( 'manage_posts_custom_column', array( $this, 'bugs_populate_columns' ) );
		add_filter( 'manage_edit-bug-library-bugs_columns', array( $this, 'bugs_columns_list' ) );

		add_filter( 'manage_edit-bug-library-types_columns', array( $this, 'bugs_types_custom_column_header' ), 10);
		add_filter( 'manage_bug-library-types_custom_column', array( $this, 'bugs_add_types_id' ), 10, 3 );

		add_filter( 'manage_edit-bug-library-products_columns', array( $this, 'bugs_products_custom_column_header' ), 10);
		add_filter( 'manage_bug-library-products_custom_column', array( $this, 'bugs_add_products_id' ), 10, 3 );

		add_filter( 'manage_edit-bug-library-status_columns', array( $this, 'bugs_status_custom_column_header' ), 10);
		add_filter( 'manage_bug-library-status_custom_column', array( $this, 'bugs_add_status_id' ), 10, 3 );

		add_filter( 'manage_edit-bug-library-priority_columns', array( $this, 'bugs_priority_custom_column_header' ), 10);
		add_filter( 'manage_bug-library-priority_custom_column', array( $this, 'bugs_add_priority_id' ), 10, 3 );

		add_action( 'restrict_manage_posts', array( $this, 'restrict_listings' ) );
		add_filter( 'parse_query', array( $this, 'convert_ids_to_taxonomy_term_in_query' ) );

		add_action( 'save_post', array( $this, 'add_bug_field' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save_quick_edit_data' ), 10, 2 );
		add_action( 'delete_post', array( $this, 'delete_bug_field' ) );
		add_filter( 'wp_insert_post_data', array( $this, 'filter_post_data' ), '99', 2 );

		add_action( 'template_redirect', array( $this, 'bl_template_redirect' ) );

		// Function to determine if Bug Library is used on a page before printing headers
		add_filter( 'the_posts', array( $this, 'conditionally_add_scripts_and_styles' ) );

		// Add quick Edit boxes
		add_action( 'quick_edit_custom_box',  array( $this, 'quick_edit_add' ), 10, 2);

		// Javascript to change 'defaults'
		add_action( 'admin_footer', array( $this, 'quick_edit_js' ) );
		add_filter( 'post_row_actions', array( $this, 'quick_edit_link' ), 10, 2 );

		// Load text domain for translation of admin pages and text strings
		load_plugin_textdomain( 'bug-library', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		add_filter( 'template_include', array( $this, 'bl_template_include' ) );
	}

	/************************** Bug Library Installation Function **************************/
	function bl_install() {

		global $wpdb;

		$productexist = $wpdb->get_var( "select * from " . $wpdb->get_blog_prefix() . "term_taxonomy where taxonomy = 'bug-library-products'" );

		if ( empty( $productexist ) ) {
			$wpdb->insert( $wpdb->get_blog_prefix() . 'terms', array(
				'name'       => 'Default Product',
				'slug'       => 'default-product',
				'term_group' => 0
			) );
			$producttermid = $wpdb->get_var( "select term_id from " . $wpdb->get_blog_prefix() . "terms where name = 'Default Product'" );
			$wpdb->insert( $wpdb->get_blog_prefix() . 'term_taxonomy', array(
				'term_id'     => $producttermid,
				'taxonomy'    => 'bug-library-products',
				'description' => '',
				'parent'      => 0,
				'count'       => 0
			) );
		}

		$typeexist = $wpdb->get_var( "select * from " . $wpdb->get_blog_prefix() . "term_taxonomy where taxonomy = 'bug-library-types'" );

		if ( empty( $typeexist ) ) {
			$wpdb->insert( $wpdb->get_blog_prefix() . 'terms', array(
				'name'       => 'Default Type',
				'slug'       => 'default-type',
				'term_group' => 0
			) );
			$typetermid = $wpdb->get_var( "select term_id from " . $wpdb->get_blog_prefix() . "terms where name = 'Default Type'" );
			$wpdb->insert( $wpdb->get_blog_prefix() . 'term_taxonomy', array(
				'term_id'     => $typetermid,
				'taxonomy'    => 'bug-library-types',
				'description' => '',
				'parent'      => 0,
				'count'       => 0
			) );
		}

		$statusexist = $wpdb->get_var( "select * from " . $wpdb->get_blog_prefix() . "term_taxonomy where taxonomy = 'bug-library-status'" );

		if ( empty( $statusexist ) ) {
			$wpdb->insert( $wpdb->get_blog_prefix() . 'terms', array(
				'name'       => 'Default Status',
				'slug'       => 'default-status',
				'term_group' => 0
			) );
			$statustermid = $wpdb->get_var( "select term_id from " . $wpdb->get_blog_prefix() . "terms where name = 'Default Status'" );
			$wpdb->insert( $wpdb->get_blog_prefix() . 'term_taxonomy', array(
				'term_id'     => $statustermid,
				'taxonomy'    => 'bug-library-status',
				'description' => '',
				'parent'      => 0,
				'count'       => 0
			) );
		}

		$priorityexist = $wpdb->get_var( "select * from " . $wpdb->get_blog_prefix() . "term_taxonomy where taxonomy = 'bug-library-priority'" );

		if ( empty( $priorityexist ) ) {
			$wpdb->insert( $wpdb->get_blog_prefix() . 'terms', array(
				'name'       => 'Default Priority',
				'slug'       => 'default-priority',
				'term_group' => 0
			) );
			$prioritytermid = $wpdb->get_var( "select term_id from " . $wpdb->get_blog_prefix() . "terms where name = 'Default Priority'" );
			$wpdb->insert( $wpdb->get_blog_prefix() . 'term_taxonomy', array(
				'term_id'     => $prioritytermid,
				'taxonomy'    => 'bug-library-priority',
				'description' => '',
				'parent'      => 0,
				'count'       => 0
			) );
		}

		$bugs = $wpdb->get_results( "select * from " . $wpdb->get_blog_prefix() . "posts where post_type = 'bug-library-bugs'" );

		if ( $bugs ) {
			foreach ( $bugs as $bug ) {
				$priorityterms = wp_get_post_terms( $bug->ID, 'bug-library-priority' );
				if ( ! $priorityterms ) {
					wp_set_post_terms( $bug->ID, 'Default Priority', 'bug-library-priority' );
				}
			}
		}
	}

	function admin_init() {
		add_meta_box( 'buglibrary_edit_bug_meta_box', __( 'Bug Details', 'bug-library' ), array(
			$this,
			'bug_library_edit_bug_details'
		), 'bug-library-bugs', 'normal', 'high' );
	}

	function my_custom_taxonomies() {

		register_taxonomy(
			'bug-library-products',        // internal name = machine-readable taxonomy name
			'bug-library-bugs',        // object type = post, page, link, or custom post-type
			array(
				'hierarchical'  => false,
				'label'         => __( 'Products', 'bug-library'),    // the human-readable taxonomy name
				'query_var'     => true,    // enable taxonomy-specific querying
				'rewrite'       => array( 'slug' => 'products' ),    // pretty permalinks for your taxonomy?
				'add_new_item'  => __( 'Add New Product', 'bug-library' ),
				'new_item_name' => __( 'New Product Name', 'bug-library' ),
				'public'		=> true,
				'show_ui'       => true,
				'show_in_menu'  => false,
				'show_in_nav_menus' => false,
				'show_tagcloud' => false,
				'show_in_quick_edit' => false,
				'show_admin_column' => false,
				'meta_box_cb' => array( $this, 'bug_library_products_metabox')
			)
		);

		register_taxonomy(
			'bug-library-status',        // internal name = machine-readable taxonomy name
			'bug-library-bugs',        // object type = post, page, link, or custom post-type
			array(
				'hierarchical'  => false,
				'label'         => __( 'Bug Status', 'bug-library' ),    // the human-readable taxonomy name
				'query_var'     => true,    // enable taxonomy-specific querying
				'rewrite'       => array( 'slug' => 'status' ),    // pretty permalinks for your taxonomy?
				'add_new_item'  => __( 'Add New Status', 'bug-library' ),
				'new_item_name' => __( 'New Status', 'bug-library' ),
				'show_ui'       => true,
				'show_tagcloud' => false,
				'show_in_menu'  => false,
				'show_in_nav_menus' => false,
				'show_in_quick_edit' => false
			)
		);

		register_taxonomy(
			'bug-library-types',        // internal name = machine-readable taxonomy name
			'bug-library-bugs',        // object type = post, page, link, or custom post-type
			array(
				'hierarchical'  => false,
				'label'         => __( 'Types', 'bug-library' ),    // the human-readable taxonomy name
				'query_var'     => true,    // enable taxonomy-specific querying
				'rewrite'       => array( 'slug' => 'types' ),    // pretty permalinks for your taxonomy?
				'add_new_item'  => __( 'Add New Type', 'bug-library' ),
				'new_item_name' => __( 'New Type', 'bug-library' ),
				'show_ui'       => true,
				'show_in_menu'  => false,
				'show_in_nav_menus' => false,
				'show_tagcloud' => false,
				'show_in_quick_edit' => false
			)
		);

		register_taxonomy(
			'bug-library-priority',        // internal name = machine-readable taxonomy name
			'bug-library-bugs',        // object type = post, page, link, or custom post-type
			array(
				'hierarchical'  => false,
				'label'         => __( 'Priorities', 'bug-library' ),    // the human-readable taxonomy name
				'query_var'     => true,    // enable taxonomy-specific querying
				'rewrite'       => array( 'slug' => 'priority' ),    // pretty permalinks for your taxonomy?
				'add_new_item'  => __( 'Add New Priority', 'bug-library' ),
				'new_item_name' => __( 'New Priority', 'bug-library' ),
				'show_ui'       => true,
				'show_in_menu'  => false,
				'show_in_nav_menus' => false,
				'show_tagcloud' => false,
				'show_in_quick_edit' => false
			)
		);
	}

	function validate_css( $css ) {
		require_once plugin_dir_path( __FILE__ ) . '/tools/class.csstidy.php';

		$csstidy = new csstidy();
		$csstidy->set_cfg( 'optimise_shorthands', 2 );
		$csstidy->set_cfg( 'template', 'low' );
		$csstidy->set_cfg( 'discard_invalid_properties', false );
		$csstidy->set_cfg( 'remove_last_;', false );
		$csstidy->set_cfg( 'preserve_css', true );
		$csstidy->set_cfg( 'remove_bslash', true );
		$csstidy->parse( $css );

		return $csstidy->print->plain();
	}

	function bl_template_include( $template_path ) {
		if ( get_post_type() == 'bug-library-bugs' && is_single() ) {
			// checks if the file exists in the theme first,
			// otherwise serve the file from the plugin
			if ( $theme_file = locate_template( array ( 'single-link_library_links.php' ) ) ) {
				$template_path = $theme_file;
			} else {
				$template_path = plugin_dir_path( __FILE__ ) .  'single-bug-library-bugs.php';
			}
		}
		return $template_path;
	}

	function bugs_add_types_id( $content, $column_name, $term_id ){
		$content = $term_id;
		return $content;
	}

	function bugs_types_custom_column_header( $columns ){
		$columns = array_merge( array_slice( $columns, 0, 2 ),
			array( 'taxonomy_id' => __( 'Type ID', 'bug-library' ) ),
			array_slice( $columns, 2 ) );
		return $columns;
	}

	function bugs_add_products_id( $content, $column_name, $term_id ){
		$content = $term_id;
		return $content;
	}

	function bugs_products_custom_column_header( $columns ){
		$columns = array_merge( array_slice( $columns, 0, 2 ),
			array( 'taxonomy_id' => __( 'Product ID', 'bug-library' ) ),
			array_slice( $columns, 2 ) );
		return $columns;
	}

	function bugs_add_status_id( $content, $column_name, $term_id ){
		$content = $term_id;
		return $content;
	}

	function bugs_status_custom_column_header( $columns ){
		$columns = array_merge( array_slice( $columns, 0, 2 ),
			array( 'taxonomy_id' => __( 'Status ID', 'bug-library' ) ),
			array_slice( $columns, 2 ) );
		return $columns;
	}

	function bugs_add_priority_id( $content, $column_name, $term_id ){
		$content = $term_id;
		return $content;
	}

	function bugs_priority_custom_column_header( $columns ){
		$columns = array_merge( array_slice( $columns, 0, 2 ),
			array( 'taxonomy_id' => __( 'Priority ID', 'bug-library' ) ),
			array_slice( $columns, 2 ) );
		return $columns;
	}

	function bug_library_products_metabox() { ?>
		<p>Set using <strong>Bug Details</strong> dialog</p>
	<?php }

	function create_bug_post_type() {
		$genoptions = get_option( 'BugLibraryGeneral', '' );
		$genoptions = wp_parse_args( $genoptions, $this->bl_reset_gen_settings( 'return' ) );

		if ( isset( $genoptions['permalinkpageid'] ) && $genoptions['permalinkpageid'] != - 1 ) {
			$page = get_post( intval( $genoptions['permalinkpageid'] ) );
			$slug = sanitize_text_field( $page->post_name );
		} else {
			$slug = 'bugs';
		}

		$menu_icon = '<?xml version="1.0" encoding="UTF-8" standalone="no"?> <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="black" d="M511.988 288.9c-.478 17.43-15.217 31.1-32.653 31.1H424v16c0 21.864-4.882 42.584-13.6 61.145l60.228 60.228c12.496 12.497 12.496 32.758 0 45.255-12.498 12.497-32.759 12.496-45.256 0l-54.736-54.736C345.886 467.965 314.351 480 280 480V236c0-6.627-5.373-12-12-12h-24c-6.627 0-12 5.373-12 12v244c-34.351 0-65.886-12.035-90.636-32.108l-54.736 54.736c-12.498 12.497-32.759 12.496-45.256 0-12.496-12.497-12.496-32.758 0-45.255l60.228-60.228C92.882 378.584 88 357.864 88 336v-16H32.666C15.23 320 .491 306.33.013 288.9-.484 270.816 14.028 256 32 256h56v-58.745l-46.628-46.628c-12.496-12.497-12.496-32.758 0-45.255 12.498-12.497 32.758-12.497 45.256 0L141.255 160h229.489l54.627-54.627c12.498-12.497 32.758-12.497 45.256 0 12.496 12.497 12.496 32.758 0 45.255L424 197.255V256h56c17.972 0 32.484 14.816 31.988 32.9zM257 0c-61.856 0-112 50.144-112 112h224C369 50.144 318.856 0 257 0z"/></svg>';

		register_post_type( 'bug-library-bugs',
			array(
				'labels'        => array(
					'name'               => __( 'Bugs', 'bug-library' ),
					'singular_name'      => __( 'Bug', 'bug-library' ),
					'add_new'            => __( 'Add New', 'bug-library' ),
					'add_new_item'       => __( 'Add New Bug', 'bug-library' ),
					'edit'               => __( 'Edit', 'bug-library' ),
					'edit_item'          => __( 'Edit Bug', 'bug-library' ),
					'new_item'           => __( 'New Bug', 'bug-library' ),
					'view'               => __( 'View Bug', 'bug-library' ),
					'view_item'          => __( 'View Bug', 'bug-library' ),
					'search_items'       => __( 'Search Bugs', 'bug-library' ),
					'not_found'          => __( 'No bugs found', 'bug-library' ),
					'not_found_in_trash' => __( 'No bugs found in Trash', 'bug-library' ),
					'parent'             => __( 'Parent Bug', 'bug-library' ),
				),
				'public'        => true,
				'menu_position' => 20,
				'supports'      => array( 'title', 'editor', 'comments', 'thumbnail' ),
				'taxonomies'    => array( '' ),
				'menu_icon'     => 'data:image/svg+xml;base64,' . base64_encode( $menu_icon ),
				'rewrite'       => array( 'slug' => $slug ),
				'exclude_from_search' => $genoptions['excludesitesearch']
			)
		);
	}

	function bugs_columns_list( $columns ) {
		$columns["bug-library-view-ID"]       = __( "ID", 'bug-library' );
		$columns["bug-library-view-product"]  = __( "Product", 'bug-library' );
		$columns["bug-library-view-status"]   = __( "Status", 'bug-library' );
		$columns["bug-library-view-type"]     = __( "Type", 'bug-library' );
		$columns["bug-library-view-priority"] = __( "Priority", 'bug-library' );
		$columns["bug-library-view-assignee"] = __( "Assignee", 'bug-library' );
		unset( $columns['comments'] );

		return $columns;
	}

	function bugs_populate_columns( $column ) {
		global $post;

		$products   = wp_get_post_terms( $post->ID, "bug-library-products" );
		$status     = wp_get_post_terms( $post->ID, "bug-library-status" );
		$types      = wp_get_post_terms( $post->ID, "bug-library-types" );
		$priorities = wp_get_post_terms( $post->ID, "bug-library-priority" );

		$assigneduserid = get_post_meta( $post->ID, "bug-library-assignee", true );
		if ( $assigneduserid != - 1 && $assigneduserid != '' ) {
			$assigneedata = get_userdata( $assigneduserid );
			if ( $assigneedata ) {
				$firstname = get_user_meta( $assigneduserid, 'first_name', true );
				$lastname  = get_user_meta( $assigneduserid, 'last_name', true );

				if ( $firstname == "" && $lastname == "" ) {
					$firstname = $assigneedata->user_login;
				}
			} else {
				$firstname = __( "Unassigned", 'bug-library' );
				$lastname  = "";
			}
		} else {
			$firstname = __( "Unassigned", 'bug-library' );
			$lastname  = "";
		}

		if ( "bug-library-view-ID" == $column && isset( $post->ID ) ) {
			echo esc_html( $post->ID );
		} elseif ( "bug-library-view-title" == $column && isset( $post->post_title ) ) {
			echo esc_html( $post->post_title );
		} elseif ( "bug-library-view-product" == $column && isset( $products ) && !empty( $products ) ) {
			echo esc_html( $products[0]->name );
		} elseif ( "bug-library-view-status" == $column && isset( $status ) && !empty( $status ) ) {
			echo esc_html( $status[0]->name );
		} elseif ( "bug-library-view-type" == $column && isset( $types ) && !empty( $types ) ) {
			echo esc_html( $types[0]->name );
		} elseif ( "bug-library-view-priority" == $column && isset( $priorities ) && !empty( $priorities ) ) {
			echo esc_html( $priorities[0]->name );
		} elseif ( "bug-library-view-assignee" == $column ) {
			echo esc_html( $firstname . " " . $lastname );
		}
	}

	function restrict_listings() {
		global $typenow;
		global $wp_query;
		if ( $typenow == 'bug-library-bugs' ) {
			$taxonomy         = 'bug-library-products';
			$product_taxonomy = get_taxonomy( $taxonomy );
			wp_dropdown_categories( array(
				'show_option_all' => __( "Show All", 'bug-library' ) . ' ' . esc_html( $product_taxonomy->label ),
				'taxonomy'        => $taxonomy,
				'name'            => 'bug-library-products',
				'orderby'         => 'name',
				'selected'        => ( isset( $wp_query->query['bug-library-products'] ) ? esc_html( $wp_query->query['bug-library-products'] ) : '' ),
				'hierarchical'    => true,
				'depth'           => 3,
				'show_count'      => false, // Show # listings in parens
				'hide_empty'      => false, // Don't show businesses w/o listings
			) );

			$taxonomy         = 'bug-library-types';
			$product_taxonomy = get_taxonomy( $taxonomy );
			wp_dropdown_categories( array(
				'show_option_all' => __( "Show All", 'bug-library' ) . ' ' . esc_html( $product_taxonomy->label ),
				'taxonomy'        => $taxonomy,
				'name'            => 'bug-library-types',
				'orderby'         => 'name',
				'selected'        => ( isset( $wp_query->query['bug-library-types'] ) ? esc_html( $wp_query->query['bug-library-types'] ) : '' ),
				'hierarchical'    => true,
				'depth'           => 3,
				'show_count'      => false, // Show # listings in parens
				'hide_empty'      => false, // Don't show businesses w/o listings
			) );

			$taxonomy         = 'bug-library-status';
			$product_taxonomy = get_taxonomy( $taxonomy );
			wp_dropdown_categories( array(
				'show_option_all' => __( "Show All", 'bug-library' ) . ' ' . esc_html( $product_taxonomy->label ),
				'taxonomy'        => $taxonomy,
				'name'            => 'bug-library-status',
				'orderby'         => 'name',
				'selected'        => ( isset( $wp_query->query['bug-library-status'] ) ? esc_html( $wp_query->query['bug-library-status'] ) : '' ),
				'hierarchical'    => true,
				'depth'           => 3,
				'show_count'      => false, // Show # listings in parens
				'hide_empty'      => false, // Don't show businesses w/o listings
			) );

			$taxonomy         = 'bug-library-priority';
			$product_taxonomy = get_taxonomy( $taxonomy );
			wp_dropdown_categories( array(
				'show_option_all' => __( "Show All", 'bug-library' ) . ' ' . esc_html( $product_taxonomy->label ),
				'taxonomy'        => $taxonomy,
				'name'            => 'bug-library-priority',
				'orderby'         => 'name',
				'selected'        => ( isset ( $wp_query->query['bug-library-priority'] ) ? esc_html( $wp_query->query['bug-library-priority'] ) : '' ),
				'hierarchical'    => true,
				'depth'           => 3,
				'show_count'      => false, // Show # listings in parens
				'hide_empty'      => false, // Don't show businesses w/o listings
			) );
		}
	}

	function quick_edit_add($column_name, $post_type) {
		$genoptions = get_option( 'BugLibraryGeneral', "" );
		$genoptions = wp_parse_args( $genoptions, $this->bl_reset_gen_settings( 'return' ) );

		switch ( $column_name ) {
			case 'bug-library-view-product':
				?>
				<fieldset class="inline-edit-col-right">
					<div class="inline-edit-col">
						<label><span class="title"><?php _e( 'Product', 'bug-library' ); ?></span></label>
						<input type="hidden" name="bug_library_product_noncename" id="bug_library_product_noncename" value="" />
						<?php
						$terms = get_terms( array( 'taxonomy' => 'bug-library-products','hide_empty' => false ) );
						?>
						<select name='bug_library_products' id='bug_library_products'>
							<?php
							foreach ( $terms as $term ) {
								echo "<option class='bug-library-products-option' value='" . esc_html( $term->name ) . "'>" . esc_html( $term->name ) . "</option>\n";
							}
							?>
						</select>
					</div>
				<?php
				break;
			case 'bug-library-view-status':
				?>
					<div class="inline-edit-col">
						<label><span class="title"><?php _e( 'Status', 'bug-library' ); ?></span></label>
						<input type="hidden" name="bug_library_status_noncename" id="bug_library_status_noncename" value="" />
						<?php
						$terms = get_terms( array( 'taxonomy' => 'bug-library-status','hide_empty' => false ) );
						?>
						<select name='bug_library_status' id='bug_library_status'>
							<?php
							foreach ($terms as $term) {
								echo "<option class='bug-library-status-option' value='" . esc_html( $term->name ) . "'>" . esc_html( $term->name ) . "</option>\n";
							}
							?>
						</select>
					</div>
				<?php
				break;
			case 'bug-library-view-type':
				?>
					<div class="inline-edit-col">
						<label><span class="title"><?php _e( 'Type', 'bug-library' ); ?></span></label>
						<input type="hidden" name="bug_library_type_noncename" id="bug_library_type_noncename" value="" />
						<?php
						$terms = get_terms( array( 'taxonomy' => 'bug-library-types','hide_empty' => false ) );
						?>
						<select name='bug_library_types' id='bug_library_types'>
							<?php
							foreach ($terms as $term) {
								echo "<option class='bug-library-types-option' value='" . esc_html( $term->name ) . "'>" . esc_html( $term->name ) . "</option>\n";
							}
							?>
						</select>
					</div>
				<?php
				break;
			case 'bug-library-view-priority':
				?>
					<div class="inline-edit-col">
						<label><span class="title"><?php _e( 'Priority', 'bug-library' ); ?></span></label>
						<input type="hidden" name="bug_library_priority_noncename" id="bug_library_priority_noncename" value="" />
						<?php
						$terms = get_terms( array( 'taxonomy' => 'bug-library-priority','hide_empty' => false ) );
						?>
						<select name='bug_library_priority' id='bug_library_priority'>
							<?php
							foreach ($terms as $term) {
								echo "<option class='bug-library-priority-option' value='" . esc_html( $term->name ) . "'>" . esc_html( $term->name ) . "</option>\n";
							}
							?>
						</select>
					</div>
				<?php
				break;
			case 'bug-library-view-assignee':
				?>
				<div class="inline-edit-col">
					<label><span class="title"><?php _e( 'Assignee', 'bug-library' ); ?></span></label>
					<input type="hidden" name="bug_library_assignee_noncename" id="bug_library_assignee_noncename" value="" />
					<?php
					global $wp_roles;
					$role_names = $wp_roles->roles;

					$users = array();
					foreach( $role_names as $role ) {
						$args = array( 'role' => $role['name'] );
						$new_users = get_users( $args );
						if ( !empty( $new_users ) ) {
							foreach ( $new_users as $new_user ) {
								$users[$new_user->data->ID] = $new_user->data->user_login;
							}
						}

						if ( $role['name'] == $genoptions['rolelevel'] ) {
							break;
						}

					}

					asort( $users );

					if ( $users ) {
						echo "<select name='bug_library_assignee' id='bug_library_assignee' style='width: 400px'>";
						echo "<option value='-1'>" . __( 'Assigned', 'bug-library' ) . "</option>";
						foreach ( $users as $user_ID => $user ) {
							$firstname = get_user_meta( $user_ID, 'first_name', true );

							$lastname = get_user_meta( $user_ID, 'last_name', true );

							echo "<option value='" . intval( $user_ID ) . "'>";

							if ( $firstname != '' || $lastname != '' ) {
								echo esc_html( $firstname . " " . $lastname );
							} else {
								echo esc_html( $user );
							}

							echo "</option>";
						}
						echo "</select>";
					}
					?>
				</div>
				</fieldset>
				<?php
				break;
		}
	}

	function convert_ids_to_taxonomy_term_in_query( $query ) {
		global $pagenow;
		$qv = &$query->query_vars;

		if ( $pagenow == 'edit.php' &&
		     isset( $qv['bug-library-products'] ) && is_numeric( $qv['bug-library-products'] )
		) {

			$term                       = get_term_by( 'id', intval( $qv['bug-library-products'] ), 'bug-library-products' );
			$qv['bug-library-products'] = sanitize_text_field( $term->slug );
		}

		if ( $pagenow == 'edit.php' &&
		     isset( $qv['bug-library-types'] ) && is_numeric( $qv['bug-library-types'] )
		) {

			$term                    = get_term_by( 'id', intval( $qv['bug-library-types'] ), 'bug-library-types' );
			$qv['bug-library-types'] = sanitize_text_field( $term->slug );
		}

		if ( $pagenow == 'edit.php' &&
		     isset( $qv['bug-library-status'] ) && is_numeric( $qv['bug-library-status'] )
		) {

			$term                     = get_term_by( 'id', intval( $qv['bug-library-status'] ), 'bug-library-status' );
			$qv['bug-library-status'] = sanitize_text_field( $term->slug );
		}

		if ( $pagenow == 'edit.php' &&
		     isset( $qv['bug-library-priority'] ) && is_numeric( $qv['bug-library-priority'] )
		) {

			$term                       = get_term_by( 'id', intval( $qv['bug-library-priority'] ), 'bug-library-priority' );
			$qv['bug-library-priority'] = sanitize_text_field( $term->slug );
		}

	}

	function bug_library_edit_bug_details( $bug ) {
		$genoptions = get_option( 'BugLibraryGeneral', "" );
		$genoptions = wp_parse_args( $genoptions, $this->bl_reset_gen_settings( 'return' ) );

		global $wpdb;

		$products          = wp_get_post_terms( $bug->ID, "bug-library-products" );
		$statuses          = wp_get_post_terms( $bug->ID, "bug-library-status" );
		$types             = wp_get_post_terms( $bug->ID, "bug-library-types" );
		$priorities        = wp_get_post_terms( $bug->ID, "bug-library-priority" );
		$productversion    = get_post_meta( $bug->ID, "bug-library-product-version", true );
		$reportername      = get_post_meta( $bug->ID, "bug-library-reporter-name", true );
		$reporteremail     = get_post_meta( $bug->ID, "bug-library-reporter-email", true );
		$resolutiondate    = get_post_meta( $bug->ID, "bug-library-resolution-date", true );
		$resolutionversion = get_post_meta( $bug->ID, "bug-library-resolution-version", true );
		$imagepath         = get_post_meta( $bug->ID, "bug-library-image-path", true );
		$assigneduserid    = get_post_meta( $bug->ID, "bug-library-assignee", true );

		echo "<table>\n";

		echo "<tr><td>" . __( 'Assigned user', 'bug-library' ) . "</td><td>\n";
		global $wp_roles;
		$role_names = $wp_roles->roles;

		$users = array();
		foreach( $role_names as $role ) {
			$args = array( 'role' => $role['name'] );
			$new_users = get_users( $args );
			if ( !empty( $new_users ) ) {
				foreach ( $new_users as $new_user ) {
					$users[$new_user->data->ID] = $new_user->data->user_login;
				}
			}

			if ( $role['name'] == $genoptions['rolelevel'] ) {
				break;
			}

		}

		asort( $users );

		if ( $users ) {
			echo "<select name='bug-library-assignee' style='width: 400px'>";
			echo "<option value='-1'>" . __( 'Unassigned', 'bug-library' ) . "</option>";
			foreach ( $users as $user_ID => $user ) {
				$firstname = get_user_meta( $user_ID, 'first_name', true );
				$lastname = get_user_meta( $user_ID, 'last_name', true );

				echo "<option value='" . intval( $user_ID ) . "' " . selected( $user_ID == $assigneduserid ) . ">";

				if ( $firstname != '' || $lastname != '' ) {
					echo esc_html( $firstname . " " . $lastname );
				} else {
					echo esc_html( $user );
				}

				echo "</option>";
			}
			echo "</select>";
		}

		echo "</td></tr>\n";

		echo "\t<tr>\n";
		echo "\t\t<td style='width: 150px'>" . __( 'Product', 'bug-library' ) . "</td><td>";

		$productterms = get_terms( 'bug-library-products', 'orderby=name&hide_empty=0' );

		if ( $productterms  ) {
			echo "<select name='bug-library-product' style='width: 400px'>";
			foreach ( $productterms as $productterm ) {
				echo "<option value='" . intval( $productterm->term_id ) . "' " . selected( !empty( $products ) && $products[0]->term_id == $productterm->term_id ) . ">" . esc_html( $productterm->name ) . "</option>";
			}
			echo "</select>";
		}

		echo "\t\t</td>\t";
		echo "\t</tr>\n";

		echo "\t<tr>\n";
		echo "\t\t<td>" . __( 'Status', 'bug-library' ) . "</td><td>\n";

		$statusterms = get_terms( 'bug-library-status', 'orderby=name&hide_empty=0' );

		if ( $statusterms ) {
			echo "<select name='bug-library-status' style='width: 400px'>\n";
			foreach ( $statusterms as $statusterm ) {
				$selectedterm = false;

				if ( !empty( $statuses ) && !empty( $statuses[0]->term_id ) ) {
					if ( $statuses[0]->term_id == $statusterm->term_id ) {
						$selectedterm = true;
					}
				} elseif ( empty( $statuses[0]->term_id ) && !empty( $genoptions['defaultuserbugstatus'] ) ) {
					if ( $genoptions['defaultuserbugstatus'] == $statusterm->term_id ) {
						$selectedterm = true;
					}
				}

				echo "<option value='" . intval( $statusterm->term_id ) . "' " . selected( $selectedterm ) . ">" . esc_html( $statusterm->name ) . "</option>\n";
			}
			echo "</select>\n";
		}

		echo "</td>\n";
		echo "</tr>\n";

		echo "\t<tr>\n";
		echo "\t\t<td>" . __( 'Type', 'bug-library' ) . "</td><td>\n";

		$typesterms = get_terms( 'bug-library-types', 'orderby=name&hide_empty=0' );

		if ( $typesterms ) {
			echo "<select name='bug-library-types' style='width: 400px'>\n";
			foreach ( $typesterms as $typesterm ) {
				echo "<option value='" . intval( $typesterm->term_id ) . "' " . selected( !empty( $types ) && $types[0]->term_id == $typesterm->term_id ) . ">" . esc_html( $typesterm->name ) . "</option>\n";
			}
			echo "</select>\n";
		}

		echo "</td>\n";
		echo "</tr>\n";

		echo "\t<tr>\n";
		echo "\t\t<td>" . __( 'Priority', 'bug-library' ) . "</td><td>\n";

		$prioritiesterms = get_terms( 'bug-library-priority', 'orderby=name&hide_empty=0' );

		if ( $prioritiesterms ) {
			echo "<select name='bug-library-priority' style='width: 400px'>\n";
			foreach ( $prioritiesterms as $priorityterm ) {
				$selectedterm = false;
				if ( !empty( $priorities ) && $priorities[0]->term_id != '' ) {
					if ( $priorities[0]->term_id == $priorityterm->term_id ) {
						$selectedterm = true;
					}
				} elseif ( empty( $priorities[0]->term_id ) && isset( $genoptions['defaultuserbugpriority'] ) ) {
					if ( $genoptions['defaultuserbugpriority'] == $priorityterm->term_id ) {
						$selectedterm = true;
					}
				}

				echo "<option value='" . intval( $priorityterm->term_id ) . "' " . selected( $selectedterm ) . ">" . esc_html( $priorityterm->name ) . "</option>\n";
			}
			echo "</select>\n";
		}

		echo "</td>\n";
		echo "</tr>\n";

		echo "<tr>\n";
		echo "\t<td>" . __( 'Version', 'bug-library' ) . "</td><td><input type='text' name='bug-library-product-version' ";

		if ( $productversion != '' ) {
			echo "value='" . esc_html( $productversion ) . "'";
		}

		echo " /></td>\n";
		echo "</tr>\n";

		echo "<tr>\n";
		echo "\t<td>" . __( 'Reporter Name', 'bug-library' ) . "</td><td><input type='text' size='80' name='bug-library-reporter-name' ";

		if ( $reportername != '' ) {
			echo "value='" . esc_html( $reportername ) . "'";
		}

		echo " /></td>\n";
		echo "</tr>\n";

		echo "<tr>\n";
		echo "\t<td>" . __( 'Reporter E-mail', 'bug-library' ) . "</td><td><input type='text' size='80' name='bug-library-reporter-email' ";

		if ( $reporteremail != '' ) {
			echo "value='" . esc_html( $reporteremail ) . "'";
		}

		echo " /></td>\n";
		echo "</tr>\n";

		echo "<tr>\n";
		echo "\t<td>" . __( 'Resolution Date', 'bug-library' ) . "</td><td><input type='text' id='bug-library-resolution-date' name='bug-library-resolution-date' ";

		if ( $resolutiondate != '' ) {
			echo "value='" . esc_html( $resolutiondate ) . "'";
		}

		echo " /></td>\n";
		echo "</tr>\n";

		echo "<tr>\n";
		echo "\t<td>" . __( 'Resolution Version', 'bug-library' ) . "</td><td><input type='text' name='bug-library-resolution-version' ";

		if ( $resolutionversion != '' ) {
			echo "value='" . esc_html( $resolutionversion ) . "'";
		}

		echo " /></td>\n";
		echo "</tr>\n";

		echo "<tr>\n";
		echo "\t<td>" . __( 'Attached File', 'bug-library' ) . "</td><td>";

		if ( $imagepath != '' ) {
			echo "<a href='" . esc_url( $imagepath ) . "'>" . __( 'File Attachment', 'bug-library' ) . "</a>";
		} else {
			echo __( "No file attached to this bug", 'bug-library' );
		}

		echo "</td></tr><tr><td></td><td>" . __( 'Attach new file', 'bug-library' ) . ": <input type='file' name='attachimage' id='attachimage' />";

		echo "</td>\n";
		echo "</tr>\n";

		echo "</table>\t";

		echo "<script type='text/javascript'>\n";
		echo "\tjQuery(document).ready(function() {\n";
		echo "\t\tjQuery('#bug-library-resolution-date').datepicker({minDate: '+0', dateFormat: 'mm-dd-yy', showOn: 'both', constrainInput: true, buttonImage: '" . plugins_url( '/icons/calendar.png', __FILE__ ) . "'}) });\n";

		echo "jQuery( 'form#post' )\n";
		echo "\t.attr( 'enctype', 'multipart/form-data' )\n";
		echo "\t.attr( 'encoding', 'multipart/form-data' )\n";
		echo ";\n";

		echo "</script>\n";

	}

	function add_bug_field( $ID = false, $post = false ) {
		$post = get_post( $ID );
		if ( $post->post_type = 'bug-library-bugs' ) {
			if ( isset( $_POST['bug-library-product'] ) ) {
				$productterm = get_term_by( 'id', intval( $_POST['bug-library-product'] ), 'bug-library-products' );

				if ( $productterm ) {
					wp_set_post_terms( $post->ID, sanitize_text_field( $productterm->name ), "bug-library-products" );
				}
			}

			if ( isset( $_POST['bug-library-status'] ) ) {
				$statusterm = get_term_by( 'id', intval( $_POST['bug-library-status'] ), 'bug-library-status' );
				if ( $statusterm ) {
					wp_set_post_terms( $post->ID, sanitize_text_field( $statusterm->name ), "bug-library-status" );
				}
			}

			if ( isset( $_POST['bug-library-types'] ) ) {
				$typeterm = get_term_by( 'id', intval( $_POST['bug-library-types'] ), "bug-library-types" );
				if ( $typeterm ) {
					wp_set_post_terms( $post->ID, sanitize_text_field( $typeterm->name ), "bug-library-types" );
				}
			}

			if ( isset( $_POST['bug-library-priority'] ) ) {
				$priorityterm = get_term_by( 'id', intval( $_POST['bug-library-priority'] ), "bug-library-priority" );
				if ( $priorityterm ) {
					wp_set_post_terms( $post->ID, sanitize_text_field( $priorityterm->name ), "bug-library-priority" );
				}
			}

			if ( isset( $_POST['bug-library-product-version'] ) && $_POST['bug-library-product-version'] != '' ) {
				update_post_meta( $post->ID, "bug-library-product-version", sanitize_text_field( $_POST['bug-library-product-version'] ) );
			}

			if ( isset( $_POST['bug-library-reporter-name'] ) && $_POST['bug-library-reporter-name'] != '' ) {
				update_post_meta( $post->ID, "bug-library-reporter-name", sanitize_text_field( $_POST['bug-library-reporter-name'] ) );
			}

			if ( isset( $_POST['bug-library-reporter-email'] ) && $_POST['bug-library-reporter-email'] != '' ) {
				update_post_meta( $post->ID, "bug-library-reporter-email", sanitize_text_field( $_POST['bug-library-reporter-email'] ) );
			}

			if ( isset( $_POST['bug-library-resolution-date'] ) && $_POST['bug-library-resolution-date'] != '' ) {
				update_post_meta( $post->ID, "bug-library-resolution-date", sanitize_text_field( $_POST['bug-library-resolution-date'] ) );
			}

			if ( isset( $_POST['bug-library-resolution-version'] ) && $_POST['bug-library-resolution-version'] != '' ) {
				update_post_meta( $post->ID, "bug-library-resolution-version", sanitize_text_field( $_POST['bug-library-resolution-version'] ) );
			}

			if ( isset( $_POST['bug_library_assignee'] ) && $_POST['bug_library_assignee'] != '' ) {
				update_post_meta( $post->ID, "bug-library-assignee", sanitize_text_field( $_POST['bug_library_assignee'] ) );
			}

			$uploads = wp_upload_dir();

			if ( array_key_exists( 'attachimage', $_FILES ) ) {
				$file_extension = pathinfo( $_FILES['attachimage']['name'], PATHINFO_EXTENSION );
				$target_path    = $uploads['basedir'] . "/bug-library/bugimage-" . $post->ID . '.' . $file_extension;
				$file_path      = $uploads['baseurl'] . "/bug-library/bugimage-" . $post->ID . '.' . $file_extension;

				if ( in_array( $file_extension, array( 'bmp', 'txt', 'png', 'jpg', 'pdf', 'jpeg' ) ) ) {
					if ( move_uploaded_file( $_FILES['attachimage']['tmp_name'], $target_path ) ) {
						update_post_meta( $post->ID, "bug-library-image-path", esc_url( $file_path ) );
					}
				} else {
					unlink( $_FILES['attachimage']['tmp_name'] );
				}				
			}
		}
	}

	function save_quick_edit_data( $ID = false, $post = false ) {
		// Criteria for not saving: Auto-saves, not post_type_characters, can't edit
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ( isset( $_POST['post_type'] ) && 'bug-library-bugs' != $_POST['post_type'] ) || !current_user_can( 'edit_page', $ID ) ) {
			return $ID;
		}

		$post = get_post( $ID );

		if ( isset( $_POST['bug_library_products'] ) && ( $post->post_type != 'revision' ) ) {
			$bug_library_product_term = sanitize_text_field($_POST['bug_library_products']);
			$term = term_exists( $bug_library_product_term, 'bug-library-products');
			if ( $term !== 0 && $term !== null ) {
				wp_set_object_terms( $ID, $bug_library_product_term, 'bug-library-products' );
			}
		}

		if ( isset( $_POST['bug_library_status'] ) && ( $post->post_type != 'revision' ) ) {
			$bug_library_status_term = sanitize_text_field($_POST['bug_library_status']);
			$term = term_exists( $bug_library_status_term, 'bug-library-status');
			if ( $term !== 0 && $term !== null ) {
				wp_set_object_terms( $ID, $bug_library_status_term, 'bug-library-status' );
			}
		}

		if ( isset( $_POST['bug_library_types'] ) && ( $post->post_type != 'revision' ) ) {
			$bug_library_types_term = sanitize_text_field($_POST['bug_library_types']);
			$term = term_exists( $bug_library_types_term, 'bug-library-types');
			if ( $term !== 0 && $term !== null ) {
				wp_set_object_terms( $ID, $bug_library_types_term, 'bug-library-types' );
			}
		}

		if ( isset( $_POST['bug_library_priority'] ) && ( $post->post_type != 'revision' ) ) {
			$bug_library_priority_term = sanitize_text_field($_POST['bug_library_priority']);
			$term = term_exists( $bug_library_priority_term, 'bug-library-priority');
			if ( $term !== 0 && $term !== null ) {
				wp_set_object_terms( $ID, $bug_library_priority_term, 'bug-library-priority' );
			}
		}

		if ( isset( $_POST['bug-library-assignee'] ) && $_POST['bug-library-assignee'] != '' ) {
			update_post_meta( $ID, "bug-library-assignee", sanitize_text_field( $_POST['bug-library-assignee'] ) );
		}
	}

	function quick_edit_js() {
		global $current_screen;
		if ( ($current_screen->id !== 'edit-bug-library-bugs') || ($current_screen->post_type !== 'bug-library-bugs') ) return;
		?>
		<script type="text/javascript">
			<!--
			function set_inline_bug_library_product( widgetSetProduct, widgetSetStatus, widgetSetTypes, widgetSetPriority, widgetSetAssignee ) {
				// revert Quick Edit menu so that it refreshes properly
				inlineEditPost.revert();
				var widgetInputProduct = document.getElementById('bug_library_products');
				var nonceInputProduct = document.getElementById('bug_library_product_noncename');
				nonceInputProduct.value = widgetSetProduct[1];

				// check option manually
				for (i = 0; i < widgetInputProduct.options.length; i++) {
					if (widgetInputProduct.options[i].value == widgetSetProduct[0]) {
						widgetInputProduct.options[i].setAttribute("selected", "selected");
					} else { widgetInputProduct.options[i].removeAttribute("selected"); }
				}

				var widgetInputStatus = document.getElementById('bug_library_status');
				var nonceInputStatus = document.getElementById('bug_library_status_noncename');
				nonceInputStatus.value = widgetSetStatus[1];

				// check option manually
				for (i = 0; i < widgetInputStatus.options.length; i++) {
					if (widgetInputStatus.options[i].value == widgetSetStatus[0]) {
						widgetInputStatus.options[i].setAttribute("selected", "selected");
					} else { widgetInputStatus.options[i].removeAttribute("selected"); }
				}

				var widgetInputTypes = document.getElementById('bug_library_types');
				var nonceInputTypes = document.getElementById('bug_library_type_noncename');
				nonceInputTypes.value = widgetSetTypes[1];

				// check option manually
				for (i = 0; i < widgetInputTypes.options.length; i++) {
					if (widgetInputTypes.options[i].value == widgetSetTypes[0]) {
						widgetInputTypes.options[i].setAttribute("selected", "selected");
					} else { widgetInputTypes.options[i].removeAttribute("selected"); }
				}

				var widgetInputPriority = document.getElementById('bug_library_priority');
				var nonceInputPriority = document.getElementById('bug_library_priority_noncename');
				nonceInputPriority.value = widgetSetPriority[1];

				// check option manually
				for (i = 0; i < widgetInputPriority.options.length; i++) {
					if (widgetInputPriority.options[i].value == widgetSetPriority[0]) {
						widgetInputPriority.options[i].setAttribute("selected", "selected");
					} else { widgetInputPriority.options[i].removeAttribute("selected"); }
				}

				var widgetInputAssignee = document.getElementById('bug_library_assignee');
				var nonceInputAssignee = document.getElementById('bug_library_assignee_noncename');
				nonceInputAssignee.value = widgetSetAssignee[1];

				// check option manually
				for (i = 0; i < widgetInputAssignee.options.length; i++) {
					if (widgetInputAssignee.options[i].index == widgetSetAssignee[0]) {
						widgetInputAssignee.options[i].setAttribute("selected", "selected");
					} else { widgetInputAssignee.options[i].removeAttribute("selected"); }
				}
			}
			//-->
		</script>
		<?php
	}

	function quick_edit_link($actions, $post) {
		global $current_screen;
		$post_id = '';

		if ( ( isset( $current_screen ) && $current_screen->id != 'edit-bug-library-bugs' && $current_screen->post_type != 'bug-library-bugs' ) || ( isset( $_POST['screen'] ) && $_POST['screen'] != 'edit-bug-library-bugs' ) ) return $actions;

		if ( !empty( $post->ID ) ) {
			$post_id = $post->ID;
		} elseif ( isset( $_POST['post_ID'] ) ) {
			$post_id = intval( $_POST['post_ID'] );
		}

		if ( !empty( $post_id ) ) {
			$bug_library_product_nonce = wp_create_nonce( 'bug_library_' . $post_id );
			$bug_library_products   = wp_get_post_terms( $post_id, 'bug-library-products', array( 'fields' => 'all' ) );

			$bug_library_status_nonce = wp_create_nonce( 'bug_library_' . $post_id );
			$bug_library_status   = wp_get_post_terms( $post_id, 'bug-library-status', array( 'fields' => 'all' ) );

			$bug_library_types_nonce = wp_create_nonce( 'bug_library_' . $post_id );
			$bug_library_types   = wp_get_post_terms( $post_id, 'bug-library-types', array( 'fields' => 'all' ) );

			$bug_library_priority_nonce = wp_create_nonce( 'bug_library_' . $post_id );
			$bug_library_priority   = wp_get_post_terms( $post_id, 'bug-library-priority', array( 'fields' => 'all' ) );

			$bug_library_assignee_nonce = wp_create_nonce( 'bug_library_' . $post_id );
			$bug_library_assignee = get_post_meta( $post_id, 'bug-library-assignee', true );

			$bug_library_product_string = isset( $bug_library_products[0]->name ) ? $bug_library_products[0]->name : '';
			$bug_library_status_string = isset( $bug_library_status[0]->name ) ? $bug_library_status[0]->name : '';
			$bug_library_type_string = isset( $bug_library_types[0]->name ) ? $bug_library_types[0]->name : '';
			$bug_priority_string = isset( $bug_library_priority[0]->name ) ? $bug_library_priority[0]->name : '';

			$actions['inline hide-if-no-js'] = '<a href="#" class="editinline" title="';
			$actions['inline hide-if-no-js'] .= esc_attr( __( 'Edit this item inline', 'bug-library' ) ) . '" ';
			$actions['inline hide-if-no-js'] .= " onclick=\"var productArray = new Array('" . esc_html( $bug_library_product_string ) . "', '" . esc_html( $bug_library_product_nonce ) . "');var statusArray = new Array('" . esc_html( $bug_library_status_string ) . "', '" . esc_html( $bug_library_status_nonce ) . "');var typesArray = new Array('" . esc_html( $bug_library_type_string ) . "', '" . esc_html( $bug_library_types_nonce ) . "');var priorityArray = new Array('" . esc_html( $bug_priority_string ) . "', '" . esc_html( $bug_library_priority_nonce ) . "');var assigneeArray = new Array('" . esc_html( $bug_library_assignee ) . "', '" . esc_html( $bug_library_assignee_nonce ) . "');set_inline_bug_library_product(productArray, statusArray, typesArray, priorityArray, assigneeArray)\">";
			$actions['inline hide-if-no-js'] .= __( 'Quick&nbsp;Edit', 'bug-library');
			$actions['inline hide-if-no-js'] .= '</a>';

		}
		return $actions;
	}

	function delete_bug_field( $bug_id ) {
		delete_post_meta( $bug_id, "bug-library-product-version" );
		delete_post_meta( $bug_id, "bug-library-reporter-name" );
		delete_post_meta( $bug_id, "bug-library-reporter-email" );
		delete_post_meta( $bug_id, "bug-library-resolution-date" );
		delete_post_meta( $bug_id, "bug-library-resolution-version" );
	}

	function filter_post_data( $post, $postarr ) {
		$genoptions = get_option( 'BugLibraryGeneral' );
		$genoptions = wp_parse_args( $genoptions, $this->bl_reset_gen_settings( 'return' ) );

		if ( $post['post_type'] == 'bug-library-bugs' && $genoptions['closecommentsonclosure'] ) {
			if ( isset( $_POST['bug-library-status'] ) ) {
				$statusterm = get_term_by( 'id', intval( $_POST['bug-library-status'] ), 'bug-library-status' );
				if ( $statusterm ) {
					if ( $statusterm->name == $genoptions['bugclosedstatus'] ) {
						$post['comment_status'] = 'closed';
					}
				}
			}
		}

		return $post;
	}

	/************************** Bug Library Uninstall Function **************************/
	function bl_uninstall() {
		$genoptions = get_option( 'BugLibraryGeneral' );
	}

	// Function used to set initial settings or reset them on user request
	function bl_reset_gen_settings( $setoptions = 'return' ) {
		global $wpdb;

		$genoptions['moderatesubmissions']    = true;
		$genoptions['showcaptcha']            = true;
		$genoptions['requirelogin']           = false;
		$genoptions['entriesperpage']         = 10;
		$genoptions['allowattach']            = false;
		$genoptions['defaultuserbugstatus']   = __( 'Default Status', 'bug-library' );
		$genoptions['defaultuserbugpriority'] = __( 'Default Priority', 'bug-library' );
		$genoptions['newbugadminnotify']      = true;
		$genoptions['bugnotifytitle']         = __( 'New bug added to Wordpress Bug Library: %bugtitle%', 'bug-library' );
		$genoptions['permalinkpageid']        = - 1;
		$genoptions['firstrowheaders']        = false;
		$genoptions['showpriority']           = false;
		$genoptions['showreporter']           = false;
		$genoptions['rolelevel']              = 'Administrator';
		$genoptions['showassignee']           = false;
		$genoptions['editlevel']              = 'Administrator';
		$genoptions['requirename']            = false;
		$genoptions['requireemail']           = false;
		$genoptions['hideproduct']            = false;
		$genoptions['hideversionnumber']      = false;
		$genoptions['hideissuetype']          = false;
		$genoptions['bugclosedstatus']        = __( 'Default Status', 'bug-library' );
		$genoptions['closecommentsonclosure'] = false;
		$genoptions['excludesitesearch']	  = false;
		$genoptions['productemptyoption']	  = false;
		$genoptions['issueemptyoption']		  = false;
		$genoptions['nextbugid']			  = 1;

		$stylesheetlocation           = plugins_url( 'stylesheet.css', __FILE__ );
		$genoptions['fullstylesheet'] = wp_remote_fopen( $stylesheetlocation );

		if ( 'return_and_set' == $setoptions ) {
			update_option( 'BugLibraryGeneral', $genoptions );
		}

		return $genoptions;
	}

	//for WordPress 2.8 we have to tell, that we support 2 columns !
	function on_screen_layout_columns( $columns, $screen ) {
		return $columns;
	}

	function remove_querystring_var( $url, $key ) {
		$keypos = strpos( $url, $key );
		if ( $keypos ) {
			$ampersandpos = strpos( $url, '&', $keypos );
			$newurl       = substr( $url, 0, $keypos - 1 );

			if ( $ampersandpos ) {
				$newurl .= substr( $url, $ampersandpos );
			}
		} else {
			$newurl = $url;
		}

		return $newurl;
	}

	//extend the admin menu
	function on_admin_menu() {
		//add our own option page, you can also add it to different sections or use your own one
		global $wpdb, $pagehooktop, $pagehookstylesheet, $pagehookinstructions;

		$pagehooktop = add_submenu_page( 'edit.php?post_type=bug-library-bugs', __( 'Bug Library General Options', 'bug-library' ), __( 'General Options', 'bug-library' ) , 'manage_options', 'bug-library-general-options', array(
			$this,
			'on_show_page'
		) );

		$pagehookstylesheet = add_submenu_page( 'edit.php?post_type=bug-library-bugs', __( 'Bug Library - Stylesheet Editor', 'bug-library' ), __( 'Stylesheet', 'bug-library' ), 'manage_options', 'bug-library-stylesheet', array(
			$this,
			'on_show_page'
		) );

		$pagehookinstructions = add_submenu_page( 'edit.php?post_type=bug-library-bugs', __( 'Bug Library - Instructions', 'bug-library' ), __( 'Instructions', 'bug-library' ), 'manage_options', 'bug-library-instructions', array(
			$this,
			'on_show_page'
		) );

		//register  callback gets call prior your own page gets rendered
		add_action( 'load-' . $pagehooktop, array( $this, 'on_load_page' ) );
		add_action( 'load-' . $pagehookstylesheet, array( $this, 'on_load_page' ) );
		add_action( 'load-' . $pagehookinstructions, array( $this, 'on_load_page' ) );

		add_submenu_page( 'edit.php?post_type=bug-library-bugs', __( 'Products', 'bug-library' ), __( 'Products', 'bug-library'), 'read', 'edit-tags.php?taxonomy=bug-library-products&post_type=bug-library-bugs');
		add_submenu_page( 'edit.php?post_type=bug-library-bugs', __( 'Status', 'bug-library'), __( 'Status', 'bug-library'), 'read', 'edit-tags.php?taxonomy=bug-library-status&post_type=bug-library-bugs');
		add_submenu_page( 'edit.php?post_type=bug-library-bugs', __( 'Types', 'bug-library'), __( 'Types', 'bug-library'), 'read', 'edit-tags.php?taxonomy=bug-library-types&post_type=bug-library-bugs');
		add_submenu_page( 'edit.php?post_type=bug-library-bugs', __( 'Priority', 'bug-library'), __( 'Priority', 'bug-library'), 'read', 'edit-tags.php?taxonomy=bug-library-priority&post_type=bug-library-bugs');

	}

	//will be executed if wordpress core detects this page has to be rendered
	function on_load_page() {

		global $pagehooktop, $pagehookstylesheet, $pagehookinstructions;

		wp_enqueue_script( 'tiptip', plugins_url( 'tiptip/jquery.tipTip.minified.js', __FILE__ ), array( 'jquery' ), "1.0rc3" );
		wp_enqueue_style( 'tiptipstyle', plugins_url( 'tiptip/tipTip.css', __FILE__ ) );
		wp_enqueue_script( 'postbox' );

		//add several metaboxes now, all metaboxes registered during load page can be switched off/on at "Screen Options" automatically, nothing special to do therefore
		add_meta_box( 'buglibrary_general_meta_box', __( 'General Settings', 'bug-library' ), array(
			$this,
			'general_meta_box'
		), $pagehooktop, 'normal', 'high' );
		add_meta_box( 'buglibrary_general_newissue_meta_box', __( 'User Submission Settings', 'bug-library' ), array(
			$this,
			'general_meta_newissue_box'
		), $pagehooktop, 'normal', 'high' );
		add_meta_box( 'buglibrary_general_import_meta_box', __( 'Import / Export', 'bug-library' ), array(
			$this,
			'general_importexport_meta_box'
		), $pagehooktop, 'normal', 'high' );
		add_meta_box( 'buglibrary_general_save_meta_box', __( 'Save', 'bug-library' ), array(
			$this,
			'general_save_meta_box'
		), $pagehooktop, 'normal', 'high' );

		add_meta_box( 'buglibrary_stylesheet_meta_box', __( 'Stylesheet', 'bug-library' ), array(
			$this,
			'stylesheet_meta_box'
		), $pagehookstylesheet, 'normal', 'high' );

		add_meta_box( 'buglibrary_instructions_meta_box', __( 'Instructions', 'bug-library' ), array(
			$this,
			'instructions_meta_box'
		), $pagehookinstructions, 'normal', 'high' );
	}

	//executed to show the plugins complete admin page
	function on_show_page() {
		//we need the global screen column value to beable to have a sidebar in WordPress 2.8
		global $screen_layout_columns;

		// Retrieve general options
		$genoptions = get_option( 'BugLibraryGeneral' );
		$genoptions = wp_parse_args( $genoptions, $this->bl_reset_gen_settings( 'return' ) );

		// If general options don't exist, create them
		if ( $genoptions == false ) {
			$this->bl_reset_gen_settings();
		}

		// Check for current page to set some page=specific variables
		if ( isset( $_GET['page'] ) && $_GET['page'] == 'bug-library-general-options' ) {
			if ( isset( $_GET['message'] ) && $_GET['message'] == '1' ) {
				echo "<div id='message' class='updated fade'><p><strong>" . __( 'General Settings Saved', 'bug-library' ) . ".</strong></p></div>";
			} elseif ( isset( $_GET['message'] ) && $_GET['message'] == '2' ) {
				echo "<div id='message' class='updated fade'><p><strong>" . __( 'Please create a folder called uploads under your Wordpress /wp-content/ directory with write permissions to use this functionality.', 'bug-library' ) . ".</strong></p></div>";
			} elseif ( isset( $_GET['message'] ) && $_GET['message'] == '3' ) {
				echo "<div id='message' class='updated fade'><p><strong>" . __( 'Please make sure that the /wp-content/uploads/ directory has write permissions to use this functionality.', 'bug-library' ) . ".</strong></p></div>";
			} elseif ( isset( $_GET['message'] ) && $_GET['message'] == '4' ) {
				echo "<div id='message' class='updated fade'><p><strong>" . __( 'Invalid column count for bug on row', 'bug-library' ) . "</strong></p></div>";
			} elseif ( isset( $_GET['message'] ) && $_GET['message'] == '9' ) {
				echo "<div id='message' class='updated fade'><p><strong>" . intval( $_GET['importrowscount'] ) . " " . __( 'row(s) found', 'bug-library' ) . ". " . intval( $_GET['successimportcount'] ) . " " . __( 'bugs(s) imported successfully', 'bugs-library' ) . ".</strong></p></div>";
			} elseif ( isset( $_GET['message'] ) && $_GET['message'] == '10' ) {
				echo "<div id='message' class='updated fade'><p><strong>" . __( 'Bugs(s) exported successfully', 'bugs-library' ) . ".</strong></p></div>";
			} 

			$formvalue = 'save_bug_library_general';
			$pagetitle = __( "Bug Library General Settings", 'bug-library' );
		} elseif ( $_GET['page'] == 'bug-library-stylesheet' ) {
			$formvalue = 'save_bug_library_stylesheet';

			$pagetitle = __( "Bug Library Stylesheet Editor", 'bug-library' );

			if ( isset( $_GET['message'] ) && $_GET['message'] == '1' ) {
				echo "<div id='message' class='updated fade'><p><strong>" . __( 'Stylesheet updated', 'bug-library' ) . ".</strong></p></div>";
			} elseif ( isset( $_GET['message'] ) && $_GET['message'] == '2' ) {
				echo "<div id='message' class='updated fade'><p><strong>" . __( 'Stylesheet reset to original state', 'bug-library' ) . ".</strong></p></div>";
			}
		} elseif ( $_GET['page'] == 'bug-library-instructions' ) {
			$formvalue = 'save_bug_library_instructions';

			$pagetitle = __( "Bug Library Usage Instructions", 'bug-library' );

		}

		$data               = array();
		$data['genoptions'] = $genoptions;
		global $pagehooktop, $pagehookstylesheet, $pagehookinstructions;
		?>
		<div id="bug-library-general" class="wrap">
			<h2><?php echo esc_html( $pagetitle ); ?>
				<span style='padding-left: 50px'><a href="https://ylefebvre.github.io/wordpress-plugins/bug-library/" target="buglibrary"><img src="<?php echo plugins_url( '/icons/btn_donate_LG.gif', __FILE__ ); ?>" /></a></span>
			</h2>

			<form name='buglibrary' enctype="multipart/form-data" action="admin-post.php" method="post">
				<input type="hidden" name="MAX_FILE_SIZE" value="100000" />

				<?php wp_nonce_field( 'bug-library' ); ?>
				<?php wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ); ?>
				<?php wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false ); ?>
				<input type="hidden" name="action" value="<?php echo esc_html( $formvalue ); ?>" />

				<div id="poststuff" class="metabox-holder">
					<div id="post-body" class="has-sidebar">
						<div id="post-body-content" class="has-sidebar-content">
							<?php
							if ( $_GET['page'] == 'bug-library-general-options' ) {
								do_meta_boxes( $pagehooktop, 'normal', $data );
							} elseif ( $_GET['page'] == 'bug-library-stylesheet' ) {
								do_meta_boxes( $pagehookstylesheet, 'normal', $data );
							} elseif ( $_GET['page'] == 'bug-library-instructions' ) {
								do_meta_boxes( $pagehookinstructions, 'normal', $data );
							}
							?>
						</div>
					</div>
					<br class="clear" />
				</div>
			</form>
		</div>
		<script type="text/javascript">
			//<![CDATA[
			jQuery(document).ready(function ($) {
				// close postboxes that should be closed
				jQuery('.if-js-closed').removeClass('if-js-closed').addClass('closed');
				// postboxes setup
				postboxes.add_postbox_toggles('<?php
				if ($_GET['page'] == 'bug-library')
					{echo esc_html( $pagehooktop );}
				elseif ($_GET['page'] == 'bug-library-stylesheet')
					{echo esc_html( $pagehookstylesheet );}
				elseif ($_GET['page'] == 'bug-library-instructions')
					{echo esc_html( $pagehookinstructions );}
				?>');

				jQuery('.bltooltip').each(function () {
						jQuery(this).tipTip();
					}
				);

			});
			//]]>

		</script>

	<?php
	}

	//executed if the post arrives initiated by pressing the submit button of form
	function on_save_changes_general() {
		//user permission check
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Not allowed', 'bug-library' ) );
		}
		//cross check the given referer
		check_admin_referer( 'bug-library' );

		$message = '';

		$genoptions = get_option( 'BugLibraryGeneral' );
		$genoptions = wp_parse_args( $genoptions, $this->bl_reset_gen_settings( 'return' ) );

		if ( isset( $_POST['importbugs'] ) ) {
			global $wpdb;
			$row = 1;
			$successfulimport = 0;

			$handle = fopen( $_FILES['bugsfile']['tmp_name'], "r" );

			if ( $handle ) {
				$skiprow = 1;

				while ( ( $data = fgetcsv( $handle, 5000, "," ) ) !== false ) {
					$row += 1;
					if ( $skiprow == 1 && isset( $_POST['firstrowheaders'] ) && $row >= 2 ) {
						$skiprow = 0;
					} elseif ( ! isset( $_POST['firstrowheaders'] ) ) {
						$skiprow = 0;
					}

					if ( ! $skiprow ) {
						if ( count( $data ) == 13 ) {
							$new_bug_data = array(
								'post_status'           => sanitize_text_field( $data[9] ),
								'post_type'             => 'bug-library-bugs',
								'post_author'           => '',
								'ping_status'           => get_option( 'default_ping_status' ),
								'post_parent'           => 0,
								'menu_order'            => 0,
								'to_ping'               => '',
								'pinged'                => '',
								'post_password'         => '',
								'guid'                  => '',
								'post_content_filtered' => '',
								'post_excerpt'          => '',
								'import_id'             => 0,
								'comment_status'        => 'open',
								'post_content'          => sanitize_text_field( stripslashes( $data[5] ) ),
								'post_date'             => date_i18n( 'Y-m-d H:i:s', strtotime( $data[8] ) ),
								'post_excerpt'          => '',
								'post_title'            => sanitize_text_field( stripslashes( $data[4] ) )
							);

							$newbugid = wp_insert_post( $new_bug_data );

							if ( $newbugid != - 1 ) {
								$successfulimport += 1;
								$message = '9';

								if ( $data[1] != '' ) {
									wp_set_post_terms( $newbugid, sanitize_text_field( $data[1] ), "bug-library-products" );
								}

								if ( $data[3] != '' ) {
									wp_set_post_terms( $newbugid, sanitize_text_field( $data[3] ), "bug-library-status" );
								}

								if ( $data[0] != '' ) {
									wp_set_post_terms( $newbugid, sanitize_text_field( $data[0] ), "bug-library-types" );
								}

								if ( $data[2] != '' ) {
									update_post_meta( $newbugid, "bug-library-product-version", sanitize_text_field( $data[2] ) );
								}

								if ( $data[6] != '' ) {
									update_post_meta( $newbugid, "bug-library-reporter-name", sanitize_text_field( $data[6] ) );
								}

								if ( $data[7] != '' ) {
									update_post_meta( $newbugid, "bug-library-reporter-email", sanitize_text_field( $data[7] ) );
								}

								if ( $data[10] != '' ) {
									update_post_meta( $newbugid, "bug-library-resolution-date", sanitize_text_field( $data[10] ) );
								}

								if ( $data[11] != '' ) {
									update_post_meta( $newbugid, "bug-library-resolution-version", sanitize_text_field( $data[11] ) );
								}

								if ( $data[12] != '' ) {
									wp_set_post_terms( $newbugid, sanitize_text_field( $data[12] ), "bug-library-priority" );
								}

							}
						} else {
							$messages[] = '4';
						}
					}
				}
			}

			if ( isset( $_POST['firstrowheaders'] ) ) {
				$row -= 1;
			}

			$message = '9';
		} elseif ( isset( $_POST['exportbugs'] ) ) {
			$upload_dir = wp_upload_dir();

			if ( is_writable( $upload_dir['path'] ) ) {
				$myFile = $upload_dir['path'] . "/BugsExport.csv";
				$fh = fopen( $myFile, 'w' ) or die( "can't open file" );

				$bugs_query_args = array( 'post_type' => 'bug-library-bugs', 'posts_per_page' => -1, 'post_status' => array( 'publish', 'pending', 'draft', 'future', 'private' ) );

				$bugs_to_export = new WP_Query( $bugs_query_args );

				if ( $bugs_to_export->have_posts() ) {
					$bug_items = array();
					while ( $bugs_to_export->have_posts() ) {
						$bug_object = array();
						$bugs_to_export->the_post();

						$products          = wp_get_post_terms( get_the_ID(), 'bug-library-products' );
						$statuses          = wp_get_post_terms( get_the_ID(), 'bug-library-status' );
						$types             = wp_get_post_terms( get_the_ID(), 'bug-library-types' );
						$priorities        = wp_get_post_terms( get_the_ID(), 'bug-library-priority' );
						$productversion    = get_post_meta( get_the_ID(), 'bug-library-product-version', true );
						$reportername      = get_post_meta( get_the_ID(), 'bug-library-reporter-name', true );
						$reporteremail     = get_post_meta( get_the_ID(), 'bug-library-reporter-email', true );
						$resolutiondate    = get_post_meta( get_the_ID(), 'bug-library-resolution-date', true );
						$resolutionversion = get_post_meta( get_the_ID(), 'bug-library-resolution-version', true );
						$imagepath         = get_post_meta( get_the_ID(), 'bug-library-image-path', true );
						$assigneduserid    = get_post_meta( get_the_ID(), 'bug-library-assignee', true );

						$bug_object['ID'] = get_the_ID();
						$bug_object['Title'] = get_the_title();
						$bug_object['Description'] = get_the_content();

						$bug_products_array = array();
						$bug_products = '';
						if ( !empty( $products ) ) {
							foreach ( $products as $bug_product ) {
								$bug_products_array[] = $bug_product->name;
							}
							if ( !empty( $bug_products_array ) ) {
								$bug_products = implode( ', ', $bug_products_array );
							}
						}

						$bug_object['Bug Product'] = $bug_products;

						$bug_statuses_array = array();
						$bug_statuses = '';
						if ( !empty( $statuses ) ) {
							foreach ( $statuses as $bug_status ) {
								$bug_statuses_array[] = $bug_status->name;
							}
							if ( !empty( $bug_statuses_array ) ) {
								$bug_statuses = implode( ', ', $bug_statuses_array );
							}
						}

						$bug_object['Bug Status'] = $bug_statuses;

						$bug_types_array = array();
						$bug_types = '';
						if ( !empty( $types ) ) {
							foreach ( $types as $bug_type ) {
								$bug_types_array[] = $bug_type->name;
							}
							if ( !empty( $bug_types_array ) ) {
								$bug_types = implode( ', ', $bug_types_array );
							}
						}

						$bug_object['Bug Type'] = $bug_types;

						$bug_priorities_array = array();
						$bug_priorities = '';
						if ( !empty( $types ) ) {
							foreach ( $types as $bug_priority ) {
								$bug_priorities_array[] = $bug_priority->name;
							}
							if ( !empty( $bug_priorities_array ) ) {
								$bug_priorities = implode( ', ', $bug_priorities_array );
							}
						}

						$bug_object['Bug Priority'] = $bug_priorities;
										
						$bug_object['Product Version'] = $productversion;
						$bug_object['Reporter Name'] = $reportername;
						$bug_object['Reporter E-mail'] = $reporteremail;
						$bug_object['Resolution Date'] = $resolutiondate;
						$bug_object['Resolution Version'] = $resolutionversion;
						
						$bug_items[] = $bug_object;
					}
				}

				wp_reset_postdata();
				
				$bug_items = apply_filters( 'bug_library_export_all_links', $bug_items );

				if ( $bug_items ) {
					$headerrow = array();

					foreach ( $bug_items[0] as $key => $option ) {
						$headerrow[] = '"' . $key . '"';
					}

					$headerdata = join( ',', $headerrow ) . "\n";
					fwrite( $fh, $headerdata );

					foreach ( $bug_items as $bug_item ) {
						$datarow = array();
						foreach ( $bug_item as $key => $value ) {
							$datarow[] = $value;
						}
						fputcsv( $fh, $datarow, ',', '"' );
					}

					fclose( $fh );

					if ( file_exists( $myFile ) ) {
						header( 'Content-Description: File Transfer' );
						header( 'Content-Type: application/octet-stream' );
						header( 'Content-Disposition: attachment; filename=' . basename( $myFile ) );
						header( 'Expires: 0' );
						header( 'Cache-Control: must-revalidate' );
						header( 'Pragma: public' );
						header( 'Content-Length: ' . filesize( $myFile ) );
						readfile( $myFile );
						exit;
					}
				}
			} else {
				$message = '10';
			}			
		} else {
			$statusterm                         = get_term_by( 'id', intval( $_POST['bug-library-status'] ), 'bug-library-status' );
			$genoptions['defaultuserbugstatus'] = $statusterm->name;

			$closedstatusterm              = get_term_by( 'id', intval( $_POST['bug-library-closed-status'] ), 'bug-library-status' );
			$genoptions['bugclosedstatus'] = $closedstatusterm->name;

			$priorityterm                         = get_term_by( 'id', intval( $_POST['bug-library-priority'] ), 'bug-library-priority' );
			$genoptions['defaultuserbugpriority'] = $priorityterm->name;

			$productterm                         = get_term_by( 'id', intval( $_POST['bug-library-products'] ), 'bug-library-products' );
			$genoptions['defaultuserproduct']    = $productterm->term_id;

			if ( ( ! isset( $genoptions['allowattach'] ) || false == $genoptions['allowattach'] ) && isset( $_POST['allowattach'] ) ) {
				$uploads = wp_upload_dir();

				if ( ! file_exists( $uploads['basedir'] ) ) {
					$message                   = 2;
					$genoptions['allowattach'] = false;
				} elseif ( ! is_writable( $uploads['basedir'] ) ) {
					$message                   = 3;
					$genoptions['allowattach'] = false;
				} else {
					if ( ! file_exists( $uploads['basedir'] . '/bug-library' ) ) {
						mkdir( $uploads['basedir'] . '/bug-library' );
					}

					$genoptions['allowattach'] = true;
				}
			} elseif ( ! isset( $_POST['allowattach'] ) ) {
				$genoptions['allowattach'] = false;
			}

			foreach (
				array(
					'entriesperpage',
					'bugnotifytitle',
					'permalinkpageid',
					'rolelevel',
					'editlevel'
				) as $option_name
			) {
				if ( isset( $_POST[ $option_name ] ) ) {
					$genoptions[ $option_name ] = sanitize_text_field( $_POST[ $option_name ] );
				}
			}

			foreach (
				array(
					'moderatesubmissions',
					'showcaptcha',
					'requirelogin',
					'newbugadminnotify',
					'firstrowheaders',
					'showpriority',
					'showreporter',
					'showassignee',
					'requirename',
					'requireemail',
					'hideproduct',
					'hideversionnumber',
					'hideissuetype',
					'closecommentsonclosure',
					'excludesitesearch',
					'productemptyoption',
					'issueemptyoption'
				) as $option_name
			) {
				if ( isset( $_POST[ $option_name ] ) ) {
					$genoptions[ $option_name ] = true;
				} else {
					$genoptions[ $option_name ] = false;
				}
			}

			update_option( 'BugLibraryGeneral', $genoptions );

			if ( $message == '' ) {
				$message = 1;
			}
		}

		global $wp_rewrite;
		$wp_rewrite->flush_rules();

		//lets redirect the post request into get request (you may add additional params at the url, if you need to show save results
		wp_redirect( $this->remove_querystring_var( $_POST['_wp_http_referer'], 'message' ) . "&message=" . intval( $message ) . ( isset( $row ) && $row != 0 ? "&importrowscount=" . intval( $row ) : '' ) . ( $successfulimport != 0 ? "&successimportcount=" . intval( $successfulimport ) : "" ) );
	}

	//executed if the post arrives initiated by pressing the submit button of form
	function on_save_changes_stylesheet() {
		//user permission check
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Not allowed', 'bug-library' ) );
		}
		//cross check the given referer
		check_admin_referer( 'bug-library' );

		$message = '';
		global $wpdb;

		if ( isset( $_POST['submitstyle'] ) ) {
			$genoptions = get_option( 'BugLibraryGeneral' );
			$genoptions = wp_parse_args( $genoptions, $this->bl_reset_gen_settings( 'return' ) );			

			// Get back the optimized CSS Code
			$genoptions['fullstylesheet'] = $this->validate_css( sanitize_text_field( $_POST['fullstylesheet'] ));

			update_option( 'BugLibraryGeneral', $genoptions );
			$message = 1;
		} elseif ( isset( $_POST['resetstyle'] ) ) {
			$genoptions = get_option( 'BugLibraryGeneral' );
			$genoptions = wp_parse_args( $genoptions, $this->bl_reset_gen_settings( 'return' ) );

			$stylesheetlocation = plugin_dir_path( __FILE__ ) . 'stylesheet.css';
			if ( file_exists( $stylesheetlocation ) ) {

				$genoptions['fullstylesheet'] = file_get_contents( $stylesheetlocation );

				// Get back the optimized CSS Code
				$genoptions['fullstylesheet'] = $this->validate_css( $genoptions['fullstylesheet'] );
			}

			update_option( 'BugLibraryGeneral', $genoptions );

			$message = 2;
		}

		//lets redirect the post request into get request (you may add additional params at the url, if you need to show save results
		$cleanredirecturl = $this->remove_querystring_var( $_POST['_wp_http_referer'], 'message' );

		if ( $message != '' ) {
			$cleanredirecturl .= "&message=" . intval( $message );
		}

		wp_redirect( $cleanredirecturl );
	}

	//executed if the post arrives initiated by pressing the submit button of form
	function on_save_changes_instructions() {
		//user permission check
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Not allowed', 'bug-library' ) );
		}
		//cross check the given referer
		check_admin_referer( 'bug-library' );

		wp_redirect( $this->remove_querystring_var( $_POST['_wp_http_referer'], 'message' ) . "&message=1" );
	}

	function general_meta_box( $data ) {
		$genoptions = $data['genoptions'];
		?>
		<table>
			<tr>
				<td style='vertical-align: top; padding-right: 10px;'>
					<table>
						<tr>
							<td style='width: 200px'><?php _e( 'Number of entries per page', 'bug-library' ); ?></td>
							<td>
								<input style="width:100%" type="text" name="entriesperpage" <?php echo "value='" . intval( $genoptions['entriesperpage'] ) . "'"; ?>/>
							</td>
						</tr>
						<tr>
							<td class='bltooltip' title='<?php _e( 'Must re-apply permalink rules for this option to take effect', 'bug-library' ); ?>'><?php _e( 'Parent page (for permalink structure)', 'bug-library' ); ?></td>
							<td class='bltooltip' title='<?php _e( 'Must re-apply permalink rules for this option to take effect', 'bug-library' ); ?>'>
								<?php $pages = get_pages( array( 'parent' => 0, 'sort_column' => 'post_title' ) );

								if ( $pages ): ?>
									<select name='permalinkpageid' style='width: 200px'>
										<option value='-1'><?php _e( 'Default (bugs)', 'bug-library' ); ?></option>
										<?php foreach ( $pages as $page ): ?>

											<option value='<?php echo intval( $page->ID ); ?>' <?php selected( $page->ID == $genoptions['permalinkpageid'] ); ?>><?php echo esc_html( $page->post_title ); ?></option>
										<?php endforeach; ?>
									</select>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<td><?php _e( 'Show bug priorities', 'bug-library' ); ?></td>
							<td>
								<input type="checkbox" id="showpriority" name="showpriority" <?php checked( $genoptions['showpriority'] ); ?>/></td>
						</tr>
						<tr>
							<td><?php _e( 'Show reporter name', 'bug-library' ); ?></td>
							<td>
								<input type="checkbox" id="showreporter" name="showreporter" <?php checked( $genoptions['showreporter'] ); ?>/></td>
						</tr>
						<tr>
							<td><?php _e( 'Show assigned user', 'bug-library' ); ?></td>
							<td>
								<input type="checkbox" id="showassignee" name="showassignee" <?php checked( $genoptions['showassignee'] ); ?>/></td>
						</tr>
						<tr>
							<td><?php _e( 'Minimum role for bug assignment', 'bug-library' ); ?></td>
							<td>
								<?php global $wp_roles;
								if ( $wp_roles ):?>
									<select name='rolelevel' style='width: 200px'>
										<?php $roles = $wp_roles->roles;

										foreach ( $roles as $role ): ?>
											<option value='<?php echo esc_html( $role['name'] ); ?>' <?php selected( $genoptions['rolelevel'] == $role['name'] ); ?>><?php echo esc_html( $role['name'] ); ?></option>
										<?php endforeach; ?>
									</select>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<td><?php _e( 'Minimum role to get bug edit link', 'bug-library' ); ?></td>
							<td>
								<?php if ( $wp_roles ): ?>
									<select name='editlevel' style='width: 200px'>
										<?php $roles = $wp_roles->roles;

										foreach ( $roles as $role ): ?>
											<option value='<?php echo esc_html( $role['name'] ); ?>' <?php selected( $genoptions['editlevel'] == $role['name'] ); ?>><?php echo esc_html( $role['name'] ); ?></option>
										<?php endforeach; ?>
									</select>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<td><?php _e( 'Exclude bugs from site search', 'bug-library' ); ?></td>
							<td><input type="checkbox" id="excludesitesearch" name="excludesitesearch" <?php checked( $genoptions['excludesitesearch'] ); ?>/></td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
	<?php }

	function general_meta_newissue_box( $data ) {
		$genoptions = $data['genoptions'];
		?>
		<table>
			<tr>
				<td style='width: 300px'><?php _e( 'Moderate new submissions', 'bug-library' ); ?></td>
				<td>
					<input type="checkbox" id="moderatesubmissions" name="moderatesubmissions" <?php checked( $genoptions['moderatesubmissions'] ); ?>/>
				</td>
				<td style='width: 40px'></td>
				<td style='width: 300px'><?php _e( 'Show Captcha in submission form', 'bug-library' ); ?></td>
				<td>
					<input type="checkbox" id="showcaptcha" name="showcaptcha" <?php checked( $genoptions['showcaptcha'] );; ?>/>
				</td>
			</tr>
			<tr>
				<td><?php _e( 'Allow file attachments', 'bug-library' ); ?></td>
				<td>
					<input type="checkbox" id="allowattach" name="allowattach" <?php checked( $genoptions['allowattach'] ); ?>/>
				</td>
				<td></td>
				<td><?php _e( 'Require login to submit new issues', 'bug-library' ); ?></td>
				<td>
					<input type="checkbox" id="requirelogin" name="requirelogin" <?php checked( $genoptions['requirelogin'] ); ?>/>
				</td>
			</tr>
			<tr>
				<td><?php _e( 'Require Reporter Name', 'bug-library' ); ?></td>
				<td>
					<input type="checkbox" id="requirename" name="requirename" <?php checked( $genoptions['requirename'] ); ?>/>
				</td>
				<td></td>
				<td><?php _e( 'Require Reporter E-mail', 'bug-library' ); ?></td>
				<td>
					<input type="checkbox" id="requireemail" name="requireemail" <?php checked( $genoptions['requireemail'] ); ?>/>
				</td>
			</tr>
			<tr>
				<td><?php _e( 'Hide product selection field', 'bug-library' ); ?></td>
				<td>
					<input type="checkbox" id="hideproduct" name="hideproduct" <?php checked( $genoptions['hideproduct'] ); ?>/>
				</td>
				<td></td>
				<td><?php _e( 'Hide version number field', 'bug-library' ); ?></td>
				<td>
					<input type="checkbox" id="hideversionnumber" name="hideversionnumber" <?php checked( $genoptions['hideversionnumber'] ); ?>/>
				</td>
			</tr>
			<tr>
				<td><?php _e( 'Hide issue type field', 'bug-library' ); ?></td>
				<td>
					<input type="checkbox" id="hideissuetype" name="hideissuetype" <?php checked( $genoptions['hideissuetype'] ); ?>/>
				</td>
				<td></td>
				<td></td>
				<td>
				</td>
			</tr>
			<tr>
				<td><?php _e( 'Add empty option to product list', 'bug-library' ); ?></td>
				<td>
					<input type="checkbox" id="productemptyoption" name="productemptyoption" <?php checked( $genoptions['productemptyoption'] ); ?>/>
				</td>
				<td></td>
				<td><?php _e( 'Add empty option to issue list', 'bug-library' ); ?></td>
				<td>
					<input type="checkbox" id="issueemptyoption" name="issueemptyoption" <?php checked( $genoptions['issueemptyoption'] ); ?>/>
				</td>
			</tr>
			<tr>
				<td><?php _e( 'Default user bug status', 'bug-library' ); ?></td>
				<td>

					<?php $statusterms = get_terms( 'bug-library-status', 'orderby=name&hide_empty=0' );

					if ( $statusterms ): ?>
						<select name='bug-library-status' style='width: 200px'>
							<?php foreach ( $statusterms as $statusterm ): ?>

								<option value='<?php echo intval( $statusterm->term_id ); ?>' <?php selected( $statusterm->name == $genoptions['defaultuserbugstatus'] ); ?>><?php echo esc_html( $statusterm->name ); ?></option>
							<?php endforeach; ?>
						</select>
					<?php endif; ?>
				</td>
				<td></td>
				<td><?php _e( 'Default user bug priority', 'bug-library' ); ?></td>
				<td>

					<?php $priorityterms = get_terms( 'bug-library-priority', 'orderby=name&hide_empty=0' );

					if ( $priorityterms ): ?>
						<select name='bug-library-priority' style='width: 200px'>
							<?php foreach ( $priorityterms as $priorityterm ): ?>
								<option value='<?php echo intval( $priorityterm->term_id ); ?>' <?php selected( isset( $genoptions['defaultuserbugpriority'] ) && $priorityterm->name == $genoptions['defaultuserbugpriority'] ); ?>><?php echo esc_html( $priorityterm->name ); ?></option>
							<?php endforeach; ?>
						</select>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td><?php _e( 'Default product', 'bug-library' ); ?></td>
				<td>

					<?php $productterms = get_terms( 'bug-library-products', 'orderby=name&hide_empty=0' );

					if ( $productterms ): ?>
						<select name='bug-library-products' style='width: 200px'>
							<option value=""><?php _e( 'No default', 'bug-library' ); ?></option>
							<?php foreach ( $productterms as $productterm ): ?>
								<option value='<?php echo intval( $productterm->term_id ); ?>' <?php selected( isset( $genoptions['defaultuserproduct'] ) && $productterm->term_id == $genoptions['defaultuserproduct'] ); ?>><?php echo esc_html( $productterm->name ); ?></option>
							<?php endforeach; ?>
						</select>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td><?php _e( 'Closed bug status', 'bug-library' ); ?></td>
				<td>

					<?php $closedstatusterms = get_terms( 'bug-library-status', 'orderby=name&hide_empty=0' );

					if ( $closedstatusterms ): ?>
						<select name='bug-library-closed-status' style='width: 200px'>
							<?php foreach ( $closedstatusterms as $closedstatusterm ): ?>
								<option value='<?php echo intval( $closedstatusterm->term_id ); ?>' <?php selected( $closedstatusterm->name == $genoptions['bugclosedstatus'] ); ?>><?php echo esc_html( $closedstatusterm->name ); ?></option>
							<?php endforeach; ?>
						</select>
					<?php endif; ?>
				</td>
				<td></td>
				<td><?php _e( 'Close comments on closed status', 'bug-library' ); ?></td>
				<td>
					<input type="checkbox" id="closecommentsonclosure" name="closecommentsonclosure" <?php checked( $genoptions['closecommentsonclosure'] ); ?>/>
				</td>
			</tr>
			<tr>
				<td><?php _e( 'Notify admin of new bugs', 'bug-library' ); ?></td>
				<td>
					<input type="checkbox" id="newbugadminnotify" name="newbugadminnotify" <?php checked( $genoptions['newbugadminnotify'] ); ?>/>
				</td>
			</tr>
			<tr>
				<td class='bltooltip' title='<?php _e( 'Set the title of new bug e-mail notifications. Use variable %bugtitle% to be replaced by the new bug title.', 'bug-library' ); ?>'><?php _e( 'New bug notification title', 'bug-library' ); ?></td>
				<td colspan='4' class='bltooltip' title='<?php _e( 'Set the title of new bug e-mail notifications. Use variable %bugtitle% to be replaced by the new bug title.', 'bug-library' ); ?>'>
					<input style="width:100%" type="text" size='80' name="bugnotifytitle" <?php echo "value='" . esc_html( $genoptions['bugnotifytitle'] ) . "'"; ?>/>
				</td>
			</tr>
		</table>

	<?php }

	function general_importexport_meta_box( $data ) {
		$genoptions = $data['genoptions'];
		?>
		<table>
			<tr>
				<td><?php _e( 'First Row Contains Headers', 'bug-library' ); ?></td>
				<td>
					<input type="checkbox" id="firstrowheaders" name="firstrowheaders" <?php if ( $genoptions['firstrowheaders'] ) {
						echo ' checked="checked" ';
					} ?>/></td>
			</tr>
			<tr>
				<td class='bltooltip' title='<?php _e( 'Allows for bugs to be added in batch to the Wordpress bugs database. CSV file needs to follow template for column layout.', 'bug-library' ); ?>' style='width: 330px'><?php _e( 'CSV file to upload to import bugs', 'bug-library' ); ?> (<a href="<?php echo plugins_url( 'importtemplate.csv', __FILE__ ); ?>"><?php _e( 'file template', 'bug-library' ); ?></a>)
				</td>
				<td><input size="80" name="bugsfile" type="file" /></td>
				<td><input type="submit" name="importbugs" value="<?php _e( 'Import Bugs', 'link-library' ); ?>" /></td>
			</tr>
			<tr>
				<td><?php _e( 'Export all bugs to a CSV file', 'bug-library' ); ?></td>
				<td><input type="submit" name="exportbugs" value="<?php _e( 'Export Bugs', 'link-library' ); ?>" /></td>
			</tr>
		</table>
	<?php
	}

	function general_save_meta_box() {
		?>
		<div class="submitbox">
			<input type="submit" name="submit" class="button-primary" value="<?php _e( 'Save', 'bug-library' ); ?>" />
		</div>
	<?php
	}

	function stylesheet_meta_box( $data ) {
		$genoptions = $data['genoptions'];
		?>
		<textarea name='fullstylesheet' id='fullstylesheet' style='font-family:Courier' rows="30" cols="90"><?php echo stripslashes( sanitize_text_field( $genoptions['fullstylesheet'] ) ); ?></textarea>
		<div>
			<input type="submit" name="submitstyle" value="<?php _e( 'Submit', 'bug-library' ); ?>" /><input type="submit" name="resetstyle" value="<?php _e( 'Reset to default', 'bug-library' ); ?>" />
		</div>

	<?php
	}

	function instructions_meta_box() {
		?>
		<ol>
			<li><?php _e( 'To get a basic Bug Library list showing on one of your Wordpress pages, create a new page and type the following text: [bug-library]' ); ?></li>
			<li><?php _e( 'Configure the Bug Library General Options section for more control over the plugin functionality.' , 'bug-library' ); ?></li>
			<li><?php _e( 'Copy the file single-bug-library-bugs.php from the bug-library plugin directory to your theme directory to display all information related to your bugs. You might have to edit this file a bit and compare it to single.php to get the proper layout to show up on your web site.', 'bug-library' ); ?></li>
		</ol>
	<?php
	}


	/******************************************** Print style data to header *********************************************/

	function bl_page_header() {
		$genoptions = get_option( 'BugLibraryGeneral' );
		$genoptions = wp_parse_args( $genoptions, $this->bl_reset_gen_settings( 'return' ) );

		echo "<style id='BugLibraryStyle' type='text/css'>\n";
		echo stripslashes( sanitize_text_field( $genoptions['fullstylesheet'] ) );
		echo "</style>\n";
	}

	function bl_admin_header() {
		wp_enqueue_style( 'datePickerstyle-css', plugins_url( 'css/ui-lightness/jquery-ui-1.8.4.custom.css', __FILE__ ) );
		wp_enqueue_script( 'jquery-ui-datepicker' );
	}

	function bl_highlight_phrase( $str, $phrase, $tag_open = '<strong>', $tag_close = '</strong>' ) {
		if ( $str == '' ) {
			return '';
		}

		if ( $phrase != '' ) {
			return preg_replace( '/(' . preg_quote( $phrase, '/' ) . '(?![^<]*>))/i', $tag_open . "\\1" . $tag_close, $str );
		}

		return $str;
	}

	function BugLibrary(
		$entriesperpage = 10, $moderatesubmissions = true, $bugcategorylist = '', $requirelogin = false, $permalinkpageid = - 1,
		$showpriority = false, $showreporter = false, $showassignee = false, $shortcodebugtypeid = '', $shortcodebugstatusid = '', $shortcodebugpriorityid = ''
	) {

		global $wpdb;

		if ( isset( $_GET['bugid'] ) ) {
			$bugid = intval( $_GET['bugid'] );
			$view  = 'single';
		} else {
			$bugid = - 1;
			$view  = 'list';

			if ( isset( $_GET['bugpage'] ) ) {
				$pagenumber = intval( $_GET['bugpage'] );
			} else {
				$pagenumber = 1;
			}

			if ( isset( $_GET['bugcatid'] ) ) {
				$bugcatid = intval( $_GET['bugcatid'] );
			} else {
				$bugcatid = - 1;
			}

			if ( isset( $_GET['bugtypeid'] ) ) {
				$bugtypeid = intval( $_GET['bugtypeid'] );
			} elseif ( $shortcodebugtypeid != '' ) {
				$bugtypeid = intval( $shortcodebugtypeid );
			} else {
				$bugtypeid = - 1;
			}

			if ( isset( $_GET['bugstatusid'] ) ) {
				$bugstatusid = intval( $_GET['bugstatusid'] );
			} elseif ( $shortcodebugstatusid != '' ) {
				$bugstatusid = intval( $shortcodebugstatusid );
			} else {
				$bugstatusid = - 1;
			}

			if ( isset( $_GET['bugpriorityid'] ) ) {
				$bugpriorityid = intval( $_GET['bugpriorityid'] );
			} elseif ( $shortcodebugpriorityid != '' ) {
				$bugpriorityid = intval( $shortcodebugpriorityid );
			} else {
				$bugpriorityid = - 1;
			}
		}

		$bugquery = "SELECT bugs.*, UNIX_TIMESTAMP(bugs.post_date) as bug_date_unix, pt.name as productname, pt.term_id as pid, st.name as statusname, ";
		$bugquery .= "st.term_id as sid, tt.name as typename, tt.term_id as tid, pt.slug as productslug, st.slug as statusslug, tt.slug as typeslug, tpr.name as priorityname ";
		$bugquery .= "FROM $wpdb->posts bugs LEFT JOIN " . $wpdb->get_blog_prefix() . "term_relationships trp ";
		$bugquery .= "ON bugs.ID = trp.object_id LEFT JOIN ";
		$bugquery .= $wpdb->get_blog_prefix() . "term_taxonomy ttp ON trp.term_taxonomy_id = ttp.term_taxonomy_id LEFT JOIN " . $wpdb->get_blog_prefix();
		$bugquery .= "terms pt ON ttp.term_id = pt.term_id LEFT JOIN " . $wpdb->get_blog_prefix() . "term_relationships trs ON bugs.ID = trs.object_id ";
		$bugquery .= "LEFT JOIN " . $wpdb->get_blog_prefix() . "term_taxonomy tts ON trs.term_taxonomy_id = tts.term_taxonomy_id LEFT JOIN " . $wpdb->get_blog_prefix();
		$bugquery .= "terms st ON tts.term_id = st.term_id LEFT JOIN " . $wpdb->get_blog_prefix() . "term_relationships trt ON bugs.ID = trt.object_id ";
		$bugquery .= "LEFT JOIN " . $wpdb->get_blog_prefix() . "term_taxonomy ttt ON trt.term_taxonomy_id = ttt.term_taxonomy_id LEFT JOIN " . $wpdb->get_blog_prefix();
		$bugquery .= "terms tt ON ttt.term_id = tt.term_id LEFT JOIN " . $wpdb->get_blog_prefix() . "term_relationships trpr ON bugs.ID = trpr.object_id ";
		$bugquery .= "LEFT OUTER JOIN " . $wpdb->get_blog_prefix() . "term_taxonomy ttpr ON trpr.term_taxonomy_id = ttpr.term_taxonomy_id LEFT OUTER JOIN " . $wpdb->get_blog_prefix();
		$bugquery .= "terms tpr ON ttpr.term_id = tpr.term_id ";

		$bugquery .= "WHERE bugs.post_type = 'bug-library-bugs' AND ttp.taxonomy = 'bug-library-products' ";
		$bugquery .= "AND tts.taxonomy = 'bug-library-status' AND ttt.taxonomy = 'bug-library-types' AND ttpr.taxonomy = 'bug-library-priority' ";
		$bugquery .= "AND bugs.post_status != 'trash' ";

		if ( $bugcategorylist != '' ) {
			$bugquery .= "AND pt.term_id in ('" . sanitize_text_field( $bugcategorylist ) . "') ";
		}

		if ( $view == 'single' ) {
			if ( $bugid != - 1 ) {
				$bugquery .= " and ID = " . intval( $bugid );
			}
		} elseif ( $view == 'list' ) {
			if ( $bugstatusid != - 1 ) {
				$bugquery .= " and tts.term_id = " . intval( $bugstatusid );
			}

			if ( $bugcatid != - 1 ) {
				$bugquery .= " and ttp.term_id = " . intval( $bugcatid );
			}

			if ( $bugtypeid != - 1 ) {
				$bugquery .= " and ttt.term_id = " . intval( $bugtypeid );
			}

			if ( $bugpriorityid != - 1 ) {
				$bugquery .= " and ttpr.term_id = " . intval( $bugpriorityid );
			}
		}

		if ( $moderatesubmissions == true ) {
			$bugquery .= " and bugs.post_status = 'publish' ";
		}

		$bugquery .= " order by bugs.post_date DESC";

		//echo $bugquery;

		$startingentry = ( $pagenumber - 1 ) * $entriesperpage;
		$quantity      = $entriesperpage + 1;

		$countbugsquery = str_replace( 'bugs.*, UNIX_TIMESTAMP(bugs.post_date) as bug_date_unix, pt.name as productname, pt.term_id as pid, st.name as statusname, st.term_id as sid, tt.name as typename, tt.term_id as tid, pt.slug as productslug, st.slug as statusslug, tt.slug as typeslug', 'count(*)', $bugquery );

		$bugscount = $wpdb->get_var( $countbugsquery );

		if ( $view == 'list' ) {
			$bugquery .= " LIMIT " . intval( $startingentry ) . ", " . intval( $quantity );
		}

		$bugs = $wpdb->get_results( $bugquery, ARRAY_A );

		//print_r($bugs);

		if ( $entriesperpage == 0 && $entriesperpage == '' ) {
			$entriesperpage = 10;
		}

		if ( count( $bugs ) > $entriesperpage ) {
			array_pop( $bugs );
			$nextpage = true;
		} else {
			$nextpage = false;
		}

		$preroundpages = $bugscount / $entriesperpage;
		$numberofpages = ceil( $preroundpages * 1 ) / 1;

		$output = "<div id='bug-library-list'>\n";

		if ( $view == 'list' ) {
			// Filter List

			$output .= "<div id='bug-library-currentfilters'>" . __( 'Filtered by', 'bug-library' ) . ": ";

			if ( ( $bugcatid == - 1 ) && ( $bugtypeid == - 1 ) && ( $bugstatusid == - 1 ) && ( $bugpriorityid == - 1 ) ) {
				$output .= __( "None", 'bug-library' );
			}

			if ( $bugcatid != - 1 ) {
				$products = get_term_by( 'id', intval( $bugcatid ), "bug-library-products", ARRAY_A );
				$output .= __( 'Products', 'bug-library' ) . " (" . esc_html( $products['name'] ) . ")";
			}

			if ( $bugtypeid != - 1 ) {
				if ( $bugcatid != - 1 ) {
					$output .= ", ";
				}

				$types = get_term_by( 'id', intval( $bugtypeid ), "bug-library-types", ARRAY_A );
				$output .= __( 'Type', 'bug-library' ) . " (" . esc_html( $types['name'] ) . ")";
			}

			if ( $bugstatusid != - 1 ) {
				if ( ( $bugcatid != - 1 ) || ( $bugtypeid != - 1 ) ) {
					$output .= ", ";
				}
				$statuses = get_term_by( 'id', intval( $bugstatusid ), "bug-library-status", ARRAY_A );
				$output .= __( 'Status', 'bug-library' ) . " (" . esc_html( $statuses['name'] ) . ")";
			}

			if ( $bugpriorityid != - 1 ) {
				if ( ( $bugcatid != - 1 ) || ( $bugtypeid != - 1 ) || ( $bugstatusid != - 1 ) ) {
					$output .= ", ";
				}
				$priorities = get_term_by( 'id', intval( $bugpriorityid ), "bug-library-priority", ARRAY_A );
				$output .= __( 'Priority', 'bug-library' ) . " (" . esc_html( $priorities['name'] ) . ")";
			}

			$output .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span id='bug-library-filterchange'>" . __( 'Change Filter', 'bug-library' ) . "</span>";

			$cleanuri = $this->remove_querystring_var( $_SERVER['REQUEST_URI'], "bugid" );
			$cleanuri = $this->remove_querystring_var( $cleanuri, "bugcatid" );
			$cleanuri = $this->remove_querystring_var( $cleanuri, "bugstatusid" );
			$cleanuri = $this->remove_querystring_var( $cleanuri, "bugtypeid" );
			$cleanuri = $this->remove_querystring_var( $cleanuri, "bugpriorityid" );

			if ( $permalinkpageid != - 1 ) {
				$parentpage = get_post( $permalinkpageid );
				$parentslug = $parentpage->post_name;
			} else {
				$parentslug = 'bugs';
			}

			$output .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href='" . home_url() .  "/" . esc_html( $parentslug ) . "'>" . __( 'Remove all filters', 'bug-library' ) . "</a>";

			$output .= "</div>";

			if ( $view == 'list' && ( $requirelogin == false || is_user_logged_in() ) ) {
				$output .= "<div id='bug-library-newissuebutton'><button id='submitnewissue'>" . __( 'Report new issue', 'bug-library' ) . "</button></div>";
			}

			$output .= "<div id='bug-library-filters'>";
			$output .= "<div id='bug-library-filter-product'>";
			$output .= "<div id='bug-library-filter-producttitle'>" . __( 'Products', 'bug-library' ) . "</div>";

			$output .= "<div id='bug-library-filter-productitems'>";

			$products = get_terms( 'bug-library-products', 'orderby=name&hide_empty=0' );

			if ( $products ) {
				$bugcaturi = $this->remove_querystring_var( $_SERVER['REQUEST_URI'], "bugcatid" );

				if ( strpos( $bugcaturi, '?' ) === false ) {
					if ( strpos( $bugcaturi, '&' ) === false ) {
						$queryoperator = '?';
					} elseif ( strpos( $bugcaturi, '&' ) !== false ) {
						$ampersandpos  = strpos( $bugcaturi, '&' );
						$bugcaturi     = preg_replace( '/&/', '?', $bugcaturi, 1 );
						$queryoperator = '&';
					}
				} else {
					$queryoperator = '&';
				}

				if ( $bugcatid == - 1 ) {
					$output .= "<span id='bug-library-filter-currentproduct'>" . __( 'All Products', 'bug-library' ) . "</span><br />";
				} else {
					$output .= "<a href='" . esc_url( $bugcaturi ) . "'>" . __( 'All Products', 'bug-library' ) . "</a><br />";
				}

				foreach ( $products as $product ) {
					$bugcategoryarray = explode( ",", $bugcategorylist );

					if ( ( $bugcategorylist != '' && in_array( $product->term_id, $bugcategoryarray ) ) || $bugcategorylist == '' ) {
						if ( $product->term_id == $bugcatid ) {
							$output .= "<span id='bug-library-filter-currentproduct'>" . esc_html( $product->name ) . "</span><br />";
						} else {
							$output .= "<a href='" . esc_url( $bugcaturi . $queryoperator . "bugcatid=" . intval( $product->term_id ) ) . "'>" . esc_html( $product->name ) . "</a><br />";
						}
					}
				}
			}

			$output .= "</div></div>";

			$output .= "<div id='bug-library-filter-types'>";
			$output .= "<div id='bug-library-filter-typestitle'>" . __( 'Types', 'bug-library' ) . "</div>";

			$output .= "<div id='bug-library-filter-typesitems'>";

			$types = get_terms( 'bug-library-types', 'orderby=name&hide_empty=0' );

			if ( $types ) {
				$bugtypeuri = $this->remove_querystring_var( $_SERVER['REQUEST_URI'], "bugtypeid" );

				if ( strpos( $bugtypeuri, '?' ) === false ) {
					if ( strpos( $bugtypeuri, '&' ) === false ) {
						$queryoperator = '?';
					} elseif ( strpos( $bugtypeuri, '&' ) !== false ) {
						$ampersandpos  = strpos( $bugtypeuri, '&' );
						$bugtypeuri    = preg_replace( '/&/', '?', $bugtypeuri, 1 );
						$queryoperator = '&';
					}
				} else {
					$queryoperator = '&';
				}

				if ( $bugtypeid == - 1 ) {
					$output .= "<span id='bug-library-filter-currentproduct'>" . __( 'All Types', 'bug-library' ) . "</span><br />";
				} else {
					$output .= "<a href='" . esc_url( $bugtypeuri ) . "'>" . __( 'All Types', 'bug-library' ) . "</a><br />";
				}

				foreach ( $types as $type ) {
					if ( $type->term_id == $bugtypeid ) {
						$output .= "<span id='bug-library-filter-currentproduct'>" . esc_html( $type->name ) . "</span><br />";
					} else {
						$output .= "<a href='" . esc_url( $bugtypeuri . $queryoperator . "bugtypeid=" . intval( $type->term_id ) ) . "'>" . esc_html( $type->name ) . "</a><br />";
					}
				}
			}

			$output .= "</div></div>";

			$output .= "<div id='bug-library-filter-status'>";
			$output .= "<div id='bug-library-filter-statustitle'>" . __( 'Status', 'bug-library' ) . "</div>";

			$output .= "<div id='bug-library-filter-statusitems'>";

			$statuses = get_terms( 'bug-library-status', 'orderby=name&hide_empty=0' );

			if ( $statuses ) {
				$bugstatusuri = $this->remove_querystring_var( $_SERVER['REQUEST_URI'], "bugstatusid" );

				if ( strpos( $bugstatusuri, '?' ) === false ) {
					if ( strpos( $bugstatusuri, '&' ) === false ) {
						$queryoperator = '?';
					} elseif ( strpos( $bugstatusuri, '&' ) !== false ) {
						$ampersandpos  = strpos( $bugstatusuri, '&' );
						$bugstatusuri  = preg_replace( '/&/', '?', $bugstatusuri, 1 );
						$queryoperator = '&';
					}
				} else {
					$queryoperator = '&';
				}

				if ( $bugstatusid == - 1 ) {
					$output .= "<span id='bug-library-filter-currentstatus'>" . __( 'All Statuses', 'bug-library' ) . "</span><br />";
				} else {
					$output .= "<a href='" . $bugstatusuri . "'>" . __( 'All Statuses', 'bug-library' ) . "</a><br />";
				}

				foreach ( $statuses as $status ) {
					if ( $status->term_id == $bugstatusid ) {
						$output .= "<span id='bug-library-filter-currentproduct'>" . esc_html( $status->name ) . "</span><br />";
					} else {
						$output .= "<a href='" . esc_url( $bugstatusuri . $queryoperator . "bugstatusid=" . intval( $status->term_id ) ) . "'>" . esc_html( $status->name ) . "</a><br />";
					}
				}
			}

			$output .= "</div></div>";

			$output .= "<div id='bug-library-filter-priorities'>";
			$output .= "<div id='bug-library-filter-prioritiestitle'>" . __( 'Priorities', 'bug-library' ) . "</div>";

			$output .= "<div id='bug-library-filter-prioritiesitems'>";

			$priorities = get_terms( 'bug-library-priority', 'orderby=name&hide_empty=0' );

			if ( $priorities ) {
				$bugpriorityuri = $this->remove_querystring_var( $_SERVER['REQUEST_URI'], "bugpriorityid" );

				if ( strpos( $bugpriorityuri, '?' ) === false ) {
					if ( strpos( $bugpriorityuri, '&' ) === false ) {
						$queryoperator = '?';
					} elseif ( strpos( $bugpriorityuri, '&' ) !== false ) {
						$ampersandpos   = strpos( $bugpriorityuri, '&' );
						$bugpriorityuri = preg_replace( '/&/', '?', $bugpriorityuri, 1 );
						$queryoperator  = '&';
					}
				} else {
					$queryoperator = '&';
				}

				if ( $bugpriorityid == - 1 ) {
					$output .= "<span id='bug-library-filter-currentpriorities'>" . __( 'All Priorities', 'bug-library' ) . "</span><br />";
				} else {
					$output .= "<a href='" . esc_url( $bugpriorityuri ) . "'>" . __( 'All Priorities', 'bug-library' ) . "</a><br />";
				}

				foreach ( $priorities as $priority ) {
					if ( $priority->term_id == $bugpriorityid ) {
						$output .= "<span id='bug-library-filter-currentproduct'>" . esc_html( $priority->name ) . "</span><br />";
					} else {
						$output .= "<a href='" . esc_url( $bugpriorityuri . $queryoperator . "bugpriorityid=" . intval( $priority->term_id ) ) . "'>" . esc_html( $priority->name ) . "</a><br />";
					}
				}
			}

			$output .= "</div></div>";

			$output .= "</div>";
		}

		if ( $bugs ) {
			$output .= "<div id='bug-library-item-table'>";

			$counter = 1;

			foreach ( $bugs as $bug ) {
				$productversion    = get_post_meta( $bug['ID'], "bug-library-product-version", true );
				$reportername      = get_post_meta( $bug['ID'], "bug-library-reporter-name", true );

				if( !empty( $reportername ) ) {
					$user_data         = get_user_by( 'login', $reportername );
					if ( false === $user_data ) {
						$cleanreportername = $reportername;
					} else {
						$cleanreportername = $user_data->display_name;
					}
				} else {
					$cleanreportername = '';
				}

				$reporteremail     = get_post_meta( $bug['ID'], "bug-library-reporter-email", true );
				$resolutiondate    = get_post_meta( $bug['ID'], "bug-library-resolution-date", true );
				$resolutionversion = get_post_meta( $bug['ID'], "bug-library-resolution-version", true );
				$assigneduserid    = get_post_meta( $bug['ID'], "bug-library-assignee", true );

				$dateformat = get_option( "date_format" );

				$output .= "<table>\n";

				$output .= "<tr id='" . ( $counter % 2 == 1 ? 'odd' : 'even' ) . "'><td id='bug-library-type'><div id='bug-library-type-" . esc_html( $bug['typeslug'] );
				$output .= "'>" . esc_html( $bug['typename'] ) . "</div></td><td id='bug-library-title'><a href='" . get_permalink( $bug['ID'] ) . "'>" . esc_html( $bug['post_title'] ) . "</a></td>";

				$output .= "</tr>";
				$output .= "<tr id='" . ( $counter % 2 == 1 ? 'odd' : 'even' ) . "'><td id='bug-library-data' colspan='2'>ID: <a href='" . get_permalink( $bug['ID'] ) . "'>";
				$output .= intval( $bug['ID'] ) . "</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . __( 'Product', 'bug-library' ) . ": " . esc_html( $bug['productname'] );
				$output .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Version: " . ( $productversion != '' ? esc_html( $productversion ) : 'N/A' );
				$output .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . __( 'Report Date', 'bug-library' ) . ": " . date_i18n( $dateformat, $bug['bug_date_unix'] ) . "</td></tr>";

				$output .= "<tr id='" . ( $counter % 2 == 1 ? 'odd' : 'even' ) . "'><td id='bug-library-data2' colspan='2'>" . __( 'Status', 'bug-library' ) . ": " . esc_html( $bug['statusname'] );

				if ( $showpriority ) {
					$output .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . __( 'Priority', 'bug-library' ) . ": " . esc_html( $bug['priorityname'] );
				}

				if ( $showreporter ) {
					$output .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . __( 'Reporter', 'bug-library' ) . ": " . esc_html( $cleanreportername );
				}

				$output .= "</td></tr>";

				if ( $showassignee && $assigneduserid != - 1 && $assigneduserid != '' ) {
					$output .= "<tr id='" . ( $counter % 2 == 1 ? 'odd' : 'even' ) . "'><td id='bug-library-data' colspan='2'>\n";
					$firstname    = get_user_meta( $bug['ID'], 'first_name', true );
					$lastname     = get_user_meta( $bug['ID'], 'last_name', true );
					$assigneedata = get_userdata( $assigneduserid );

					$output .= __( "Assigned to", 'bug-library' ) . ": ";

					if ( $firstname != '' || $lastname != '' ) {
						$output .= esc_html( $firstname . " " . $lastname );
					} else {
						$output .= esc_html( $assigneedata->user_login );
					}

					$output .= "</td></tr>\n";
				}

				$counter ++;

				$output .= "</table>\n";
			}

			$previouspagenumber = $pagenumber - 1;
			$nextpagenumber     = $pagenumber + 1;
			$dotbelow           = false;
			$dotabove           = false;

			$currentpageuri = $this->remove_querystring_var( $_SERVER['REQUEST_URI'], "bugpage" );
			$currentpageuri = $this->remove_querystring_var( $currentpageuri, "page_id" );

			if ( strpos( $currentpageuri, '?' ) === false ) {
				if ( strpos( $currentpageuri, '&' ) === false ) {
					$queryoperator = '?';
				} elseif ( strpos( $currentpageuri, '&' ) !== false ) {
					$ampersandpos   = strpos( $currentpageuri, '&' );
					$currentpageuri = preg_replace( '/&/', '?', $currentpageuri, 1 );
					$currentpageuri = '&';
				}
			} else {
				$queryoperator = '&';
			}

			if ( $numberofpages > 1 && $view == 'list' ) {
				$output .= "<div class='bug-library-pageselector'>";

				if ( $pagenumber != 1 ) {
					$output .= "<span class='bug-library-previousnextactive'>";

					$output .= "<a href='" . esc_url( $currentpageuri . $queryoperator . "page_id=" . get_the_ID() . "&bugpage=" . intval( $previouspagenumber ) ) . "'>" . __( 'Previous', 'bug-library' ) . "</a>";

					$output .= "</span>";
				} else {
					$output .= "<span class='bug-library-previousnextinactive'>" . __( 'Previous', 'bug-library' ) . "</span>";
				}

				for ( $counter = 1; $counter <= $numberofpages; $counter ++ ) {
					if ( $counter <= 2 || $counter >= $numberofpages - 1 || ( $counter <= $pagenumber + 2 && $counter >= $pagenumber - 2 ) ) {
						if ( $counter != $pagenumber ) {
							$output .= "<span class='bug-library-unselectedpage'>";
						} else {
							$output .= "<span class='bug-library-selectedpage'>";
						}

						$output .= "<a href='" . esc_url( $currentpageuri . $queryoperator . "page_id=" . get_the_ID() . "&bugpage=" . intval( $counter ) ) . "'>" . intval( $counter ) . "</a>";

						$output .= "</a></span>";
					}

					if ( $counter >= 2 && $counter < $pagenumber - 2 && $dotbelow == false ) {
						$output .= "...";
						$dotbelow = true;
					}

					if ( $counter > $pagenumber + 2 && $counter < $numberofpages - 1 && $dotabove == false ) {
						$output .= "...";
						$dotabove = true;
					}
				}

				if ( $pagenumber != $numberofpages ) {
					$output .= "<span class='bug-library-previousnextactive'>";

					$output .= "<a href='" . esc_url( $currentpageuri . $queryoperator . "page_id=" . get_the_ID() . "&bugpage=" . intval( $nextpagenumber ) ) . "'>" . __( 'Next', 'bug-library' ) . "</a>";

					$output .= "</span>";
				} else {
					$output .= "<span class='bug-library-previousnextinactive'>" . __( 'Next', 'bug-library' ) . "</span>";
				}

				$output .= "</div>";
			}

			$output .= "</div>";
		} else {
			$output .= "<div id='bug-library-item-table'>";
			$output .= __( "There are 0 bugs to view based on the currently selected filters.", 'bug-library' );
			$output .= "</div>";
		}

		$output .= "</div>";

		$output .= "<script type='text/javascript'>";
		$output .= "/* <![CDATA[ */";
		$output .= "jQuery(document).ready(function() {";
		$output .= "\tjQuery('#bug-library-filterchange').click(function() { jQuery('#bug-library-filters').slideToggle('slow'); });";

		$queryarray = array( 'bug_library_popup_content' => 'true' );

		if ( $bugcatid != - 1 ) {
			$queryarray['bugcatid'] = intval( $bugcatid );
		}

		$target_address = add_query_arg( $queryarray, home_url() );

		$output .= "\tjQuery('#submitnewissue').colorbox({href:'" . esc_url( $target_address ) . "', opacity: 0.3, iframe:true, width:'580px', height:'720px'});";
		$output .= "});";
		$output .= "/* ]]> */";
		$output .= "</script>";

		return $output;
	}


	/********************************************** Function to Process [bug-library] shortcode *********************************************/

	function bug_library_func( $atts ) {
		extract( shortcode_atts( array(
			'bugcategorylist' => '',
			'bugtypeid'       => '',
			'bugstatusid'     => '',
			'bugpriorityid'   => ''
		), $atts ) );

		$genoptions = get_option( 'BugLibraryGeneral' );
		$genoptions = wp_parse_args( $genoptions, $this->bl_reset_gen_settings( 'return' ) );

		return $this->BugLibrary( $genoptions['entriesperpage'], $genoptions['moderatesubmissions'], sanitize_text_field( $bugcategorylist ), $genoptions['requirelogin'],
			$genoptions['permalinkpageid'], $genoptions['showpriority'], $genoptions['showreporter'], $genoptions['showassignee'], sanitize_text_field( $bugtypeid ), sanitize_text_field( $bugstatusid ), sanitize_text_field( $bugpriorityid ) );
	}


	function conditionally_add_scripts_and_styles( $posts ) {
		if ( empty( $posts ) ) {
			return $posts;
		}

		$load_jquery   = false;
		$load_fancybox = false;
		$load_style    = false;

		if ( is_admin() ) {
			$load_jquery   = false;
			$load_fancybox = false;
			$load_style    = false;
		} else {
			foreach ( $posts as $post ) {
				$buglibrarypos = stripos( $post->post_content, 'bug-library' );
				if ( $buglibrarypos !== false ) {
					$load_jquery   = true;
					$load_fancybox = true;
					$load_style    = true;
				}
			}
		}

		global $blstylesheet;

		if ( $load_style ) {
			global $blstylesheet;
			$blstylesheet = true;
		} else {
			global $blstylesheet;
			$blstylesheet = false;
		}

		if ( $load_jquery ) {
			wp_enqueue_script( 'jquery' );
		}

		if ( $load_fancybox ) {
			wp_enqueue_script( 'colorbox', plugins_url( 'colorbox/jquery.colorbox-min.js', __FILE__ ), array( 'jquery' ), "1.3.9" );
			wp_enqueue_style( 'colorboxstyle', plugins_url( 'colorbox/colorbox.css', __FILE__ ) );
		}

		return $posts;
	}

	function bl_template_redirect( $template ) {
		if ( !empty( $_GET['bug_library_popup_content'] ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'submitnewissue.php';
			exit;
		} else {
			return $template;
		}
	}
}

$my_bug_library_plugin = new bug_library_plugin();

?>
