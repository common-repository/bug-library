<?php
/**
 * The template for displaying all single posts and attachments
 *
 * @package WordPress
 * @subpackage Twenty_Fifteen
 * @since Twenty Fifteen 1.0
 */

get_header(); ?>

<div id="primary" class="content-area">
	<main id="main" class="site-main" role="main">

		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<div class="entry-content">

				<div id='bug-library-list'>
					<?php if ( have_posts() ) while ( have_posts() ) : the_post(); ?>

						<?php
						global $post, $wpdb;
						//print_r($post);
						$products = wp_get_post_terms( $post->ID, "bug-library-products");
						$product = $products[0];
						$statuses = wp_get_post_terms( $post->ID, "bug-library-status");
						$status = $statuses[0];
						$types = wp_get_post_terms( $post->ID, "bug-library-types");
						$type = $types[0];
						$productversion = get_post_meta($post->ID, "bug-library-product-version", true);
						$resolutiondate = get_post_meta($post->ID, "bug-library-resolution-date", true);
						$resolutionversion = get_post_meta($post->ID, "bug-library-resolution-version", true);
						$imagepath = get_post_meta($post->ID, "bug-library-image-path", true);

						$reportername      = get_post_meta( $post->ID, "bug-library-reporter-name", true );

						$user_data         = get_user_by( 'login', $reportername );

						if ( false === $user_data ) {
							$cleanreportername = $reportername;
						} else {
							$cleanreportername = $user_data->display_name;
						}

						$firstname    = get_user_meta( $post->ID, 'first_name', true );
						$lastname     = get_user_meta( $post->ID, 'last_name', true );

						$assigneduserid = get_post_meta( $post->ID, 'bug-library-assignee', true );
						$assigneedata = get_userdata( $assigneduserid );

						$dateformat = get_option("date_format");
						$postdatetimestamp = $wpdb->get_var("SELECT UNIX_TIMESTAMP(post_date) from " . $wpdb->get_blog_prefix() . "posts where ID = " . $post->ID);

						$genoptions = get_option('BugLibraryGeneral', "");
						if ( $genoptions['permalinkpageid'] != -1 ) {
							$parentpage = get_post($genoptions['permalinkpageid']);
							$parentslug = $parentpage->post_name;
						} else {
							$parentslug = "bugs";
						}
						?>

						<div id='bug-library-breadcrumb'><a href='<?php echo home_url( $parentslug ); ?>'>All Items</a> &raquo; <a href='<?php echo home_url( $parentslug . '/?bugcatid=' . intval( $products[0]->term_id ) ); ?>'><?php echo esc_html( $products[0]->name ); ?></a> &raquo; Bug #<?php echo intval( $post->ID ); ?></div>

						<div id='bug-library-item-table'>
							<table>

								<tr id="odd">
									<td id='bug-library-type'><div id='bug-library-type-<?php echo esc_html( $type->slug ); ?>'><?php echo esc_html( $type->name ) ?></div></td>
									<td id='bug-library-title'><a href='<?php echo get_permalink( $post->ID ); ?>'><?php echo esc_html( $post->post_title ); ?></a></td>
								</tr>
								<tr id="even">
									<td id='bug-library-data' colspan='2'><span id="bug-library-data-id">ID: <a href='<?php echo get_permalink( $post->ID ); ?>'><?php echo intval( $post->ID ); ?></a></span><span id="bug-library-data-status">Status: <?php echo esc_html( $status->name ); ?></span><span id="bug-library-data-version">Version: <?php if ($productversion != '') echo esc_html( $productversion ); else echo 'N/A'; ?></span><span id="bug-library-data-report-date">Report Date: <?php echo esc_html( date($dateformat, $postdatetimestamp) ); ?></span><span id="bug-library-data-product">Product: <?php echo esc_html( $product->name ); ?></span>
										<?php
										if ($resolutiondate != '' && $resolutiondate > 0 ) {
											$timeobject = DateTime::createFromFormat('m-j-Y', $resolutiondate);
											$formatteddate = $timeobject->format( $dateformat );
											echo "<span id='bug-library-data-resolution'>Resolution Date: " . esc_html( $formatteddate ) . "</span><span id='bug-library-data-resolution-version'>Resolution Version: " . esc_html( $resolutionversion ) . '</span>';
										} ?>
									</td>
								</tr>
								<tr id="odd">
									<td>Reporter</td><td><?php echo esc_html( $cleanreportername ); ?></td>
								</tr>
								<tr id="even">
									<td>Assignee</td><td><?php

										if ( $firstname != '' || $lastname != '' ) {
											echo esc_html( $firstname . " " . $lastname );
										} else {
											if ( !empty( $assigneedata ) ) {
												echo esc_html( $assigneedata->user_login );
											}
										} ?>
									</td>
								</tr>
								<tr id='bug-library-filler'><td></td></tr>
								<tr id='bug-library-desc'>
									<td colspan ='2'><div id='bug-library-desc-title'>Description</div><br />
										<?php if ( $post->post_content != '' ): ?>
											<?php the_content(); ?>
										<?php else: ?>
											No Description Available
										<?php endif; ?>

										<?php 	if ( $imagepath != '' ) {
											echo "File Attachment<br /><br />";
											echo "<a href='" . esc_url( $imagepath ) . "'>Attached File</a><br />";
										}
										?>
									</td>
								</tr>
							</table>
						</div>


						<?php if ( comments_open() || get_comments_number() ) :
							comments_template();
						endif; ?>

					<?php endwhile; // end of the loop. ?>
				</div></div>

		</article>

	</main><!-- #content -->
</div><!-- #container -->

<?php get_footer(); ?>
