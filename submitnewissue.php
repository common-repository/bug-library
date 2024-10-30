<head>
<link rel="stylesheet" type="text/css" media="screen" href="<?php echo plugins_url( 'stylesheet.css', __FILE__ ); ?>"/>
</head>
<body style='background-color: #FFFFFF;'>

<?php
	global $wpdb;

	$genoptions = get_option( 'BugLibraryGeneral' );
	$genoptions = wp_parse_args( $genoptions, $this->bl_reset_gen_settings( 'return' ) );

	if ( isset( $_GET['bugcatid'] ) ) {
		$bugcatid = intval( $_GET['bugcatid'] );
	} elseif ( isset( $_POST['new-bug-product'] ) ) {
		$bugcatid = intval( $_POST['new-bug-product'] );
	} elseif ( isset( $genoptions['defaultuserproduct'] ) && !empty( $genoptions['defaultuserproduct'] ) ) {
		$bugcatid = sanitize_text_field( $genoptions['defaultuserproduct'] );
	} else {
		$bugcatid = -1;
	}

	if ( isset( $_POST['new-bug-type'] ) ) {
		$bugtypeid = intval( $_POST['new-bug-type'] );
	} else {
		$bugtypeid = -1;
	}

	$valid = -1;

	if ( isset( $_POST['new-bug-submit'] ) )
	{
		if ($_POST['new-bug-title'] != '' && ( isset( $_POST['new-bug-product'] ) && $_POST['new-bug-product'] != '' ) && ( isset( $_POST['new-bug-type'] ) && $_POST['new-bug-type'] != '' ) && ( ( isset( $_POST['new-bug-version'] ) && $_POST['new-bug-version'] != '' ) || $genoptions['hideversionnumber'] ) && $_POST['new-bug-desc'] != '' && (($genoptions['requirename'] == false) || ($genoptions['requirename'] == true && $_POST['new-bug-reporter-name'] != '') && ( $genoptions['requireemail'] == false || ($genoptions['requireemail'] == true && $_POST['new-bug-reporter-email'] != '') ) ) )
		{
			if ($genoptions['showcaptcha'] == true)
			{
				if (empty($_REQUEST['confirm_code']))
				{
					$valid = 0;
					$validmessage = __('Confirm code not given', 'bug-library') . ".";
				}
				else
				{
					if ( isset($_COOKIE['Captcha']) )
					{
						list($Hash, $Time) = explode('.', sanitize_text_field( $_COOKIE['Captcha'] ) );
						if ( md5( "HFDJUJRPOSKKKLKUEB".$_REQUEST['confirm_code'].$_SERVER['REMOTE_ADDR'].$Time ) != $Hash )
						{
							$valid = 0;
							$validmessage = __('Captcha code is wrong', 'bug-library') . ".";
						}
						elseif( (time() - 5*60) > $Time)
						{
							$valid = 0;
							$validmessage = __('Captcha code is only valid for 5 minutes', 'bug-library') . ".";
						}
						else
						{
							$valid = 1;
						}
					}
					else
					{
						$valid = 0;
						$validmessage = __('No captcha cookie given. Make sure cookies are enabled', 'bug-library') . ".";
					}
				}
			}
			else
			{
				$valid = 1;
			}

			if ($valid == 1)
			{
				if ( $genoptions['moderatesubmissions'] == true )
					$bugvisible = 'private';
				elseif ( $genoptions['moderatesubmissions'] == false )
					$bugvisible = 'publish';

				$new_bug_data = array(
					'post_status' => $bugvisible,
					'post_type' => 'bug-library-bugs',
					'post_author' => '',
					'ping_status' => get_option('default_ping_status'),
					'post_parent' => 0,
					'menu_order' => 0,
					'to_ping' =>  '',
					'pinged' => '',
					'post_password' => '',
					'guid' => '',
					'post_content_filtered' => '',
					'post_excerpt' => '',
					'import_id' => 0,
					'comment_status' => 'open',
					'post_content' => sanitize_text_field( $_POST['new-bug-desc'] ),
					'post_date' => date("Y-m-d H:i:s", current_time('timestamp')),
					'post_date_gmt' => date("Y-m-d H:i:s", current_time('timestamp', 1)),
					'post_excerpt' => '',
					'post_title' => sanitize_text_field( $_POST['new-bug-title'] ) 
				);

				$newbugid = wp_insert_post( $new_bug_data );

				if ( !$genoptions['hideproduct'] ) {
					$productterm = get_term_by( 'id', intval( $_POST['new-bug-product'] ), "bug-library-products");
					if ( $productterm ) {
						wp_set_post_terms( $newbugid, sanitize_text_field( $productterm->name ), "bug-library-products" );
					}
				}

				wp_set_post_terms( $newbugid, sanitize_text_field( $genoptions['defaultuserbugstatus'] ), "bug-library-status" );

				wp_set_post_terms( $newbugid, sanitize_text_field( $genoptions['defaultuserbugpriority'] ), "bug-library-priority" );

				if ( ! $genoptions['hideissuetype'] ) {
					$typeterm = get_term_by( 'id', intval( $_POST['new-bug-type'] ), "bug-library-types");
					if ( $typeterm ) {
						wp_set_post_terms( $newbugid, sanitize_text_field( $typeterm->name ), "bug-library-types" );
					}
				}

				if ( ! $genoptions['hideversionnumber'] ) {
					if ( !empty( $_POST['new-bug-version'] ) ) {
						update_post_meta( $newbugid, 'bug-library-product-version', sanitize_text_field( $_POST['new-bug-version'] ) );
					}
				}

				if ( !empty( $_POST['new-bug-reporter-name'] ) ) {
					update_post_meta( $newbugid, 'bug-library-reporter-name', sanitize_text_field( $_POST['new-bug-reporter-name'] ) );
				}

				if ( !empty( $_POST['new-bug-reporter-email'] ) ) {
					update_post_meta( $newbugid, 'bug-library-reporter-email', sanitize_text_field( $_POST['new-bug-reporter-email'] ) );
				}

				update_post_meta( $newbugid, 'bug-library-resolution-date', '' );
				update_post_meta( $newbugid, 'bug-library-resolution-version', '' );

				$uploads = wp_upload_dir();

				if( array_key_exists( 'attachimage', $_FILES ) ) {
					$target_path = $uploads['basedir'] . '/bug-library/bugimage-' . $newbugid. '.jpg';
					$file_path = $uploads['baseurl'] . '/bug-library/bugimage-' . $newbugid . '.jpg';

					if ( move_uploaded_file( $_FILES['attachimage']['tmp_name'], $target_path ) ) {
						update_post_meta( $newbugid, "bug-library-image-path", esc_url( $file_path ) );
					}
				}

				update_post_meta( $newbugid, 'public_bug_id', intval( $genoptions['nextbugid'] ) );
				$genoptions['nextbugid'] += 1;
				update_option( 'BugLibraryGeneral', $genoptions );

				if ( $genoptions['newbugadminnotify'] == true ) {
					$adminmail = get_option('admin_email');
					$headers = "MIME-Version: 1.0\r\n";
					$headers .= "Content-type: text/html; charset=UTF-8\r\n";

					$message = __('A user submitted a new bug to your Wordpress Bug database.', 'bug-library') . "<br /><br />";
					$message .= __('Bug Title', 'bug-library') . ": " . sanitize_text_field( $_POST['new-bug-title'] ) . "<br />";
					$message .= __('Bug Description', 'bug-library') . ": " . sanitize_text_field( $_POST['new-bug-desc'] ) . "<br />";
					if ( !$genoptions['hideproduct'] ) {
						$message .= __('Bug Product', 'bug-library') . ": " . sanitize_text_field( $productterm->name ) . "<br />";
					}

					if ( !$genoptions['hideversionnumber'] ) {
						$message .= __('Bug Version', 'bug-library') . ": " . sanitize_text_field( $_POST['new-bug-version'] ) . "<br />";
					}

					if ( !$genoptions['hideissuetype'] ) {
						$message .= __('Bug Type', 'bug-library') . ": " . sanitize_text_field( $typeterm->name ) . "<br />";
					}

					$message .= __('Reporter Name', 'bug-library') . ": " . sanitize_text_field( $_POST['new-bug-reporter-name'] ) . "<br />";
					$message .= __('Reporter E-mail', 'bug-library') . ": " . sanitize_email( $_POST['new-bug-reporter-email'] ) . "<br /><br />";

					if ( true == $genoptions['moderatesubmissions'] ) {
						$message .= "<a href='" . add_query_arg( array( 'post_status' => 'private', 'post_type' => 'bug-library-bugs' ), admin_url( 'edit.php' ) ) . "'>" . __( 'Moderate new bugs', 'bug-library' ) . "</a>";
					} elseif ( false == $genoptions['moderatesubmissions'] ) {
						$message .= "<a href='" . add_query_arg( array( 'post_type' => 'bug-library-bugs' ), admin_url( 'edit.php' ) ) . "/edit.php?post_type=bug-library-bugs'>" . __( 'View bugs', 'bug-library' ) . "</a>";
					}

					$message .= "<br /><br />" . __('Message generated by', 'bug-library') . " <a href='https://ylefebvre.home.blog/wordpress-plugins/bug-library/'>Bug Library</a> for Wordpress";

					if ( $genoptions['bugnotifytitle'] != '' ) {
						$emailtitle = $genoptions['bugnotifytitle'];
						$emailtitle = str_replace( '%bugtitle%', esc_html( sanitize_text_field( $_POST['new-bug-title'] ) ), $emailtitle );
					} else {
						$emailtitle = get_option( 'blogname' ) . " - " . __('New bug added', 'bug-library') . ": " . sanitize_text_field( $_POST['new-bug-title'] );
					}

					wp_mail( $adminmail, esc_html( $emailtitle ), utf8_encode( esc_html( $message ) ), $headers );
				}
			}
		}
		else
		{
			$valid = 0;
			$validmessage = __("Missing required field(s). Please complete form.", 'bug-library');
		}
	}

	if (!isset($_POST['new-bug-submit']) || $valid == 0): ?>

<div id='bug-library-newissue-form'>

	<?php if ($valid == 0): ?>

		<div id='bug-library-invalid'><?php echo esc_html( $validmessage ); ?></div>

	<?php endif; ?>



<form name="input" action="<?php echo esc_url( add_query_arg( array( 'bug_library_popup_content' => 'true' ), home_url( '/' ) ) ); ?>" enctype="multipart/form-data" method="POST">
<div id='new-bug-form-title'><h2><?php _e( 'Submit a new issue', 'bug-library' ); ?></h2></div>

<div id='new-bug-title-section'><?php _e( 'Issue Title', 'bug-library' ); ?> <span id='required'>*</span><br />
<input type='text' id='new-bug-title' name='new-bug-title' size='80' <?php if ( $valid == 0 ) echo "value='" . esc_html( sanitize_text_field( $_POST['new-bug-title'] ) ) . "'"; ?> />
</div>

<?php if ( ! $genoptions['hideproduct'] ) { ?>
	<div id='new-bug-product-section'><?php _e( 'Issue Product', 'bug-library' ); ?> <span id='required'>*</span><br />
	<?php 	$products = get_terms('bug-library-products', 'orderby=name&hide_empty=0');

			if ( $products ) : ?>
				<select id='new-bug-product' name='new-bug-product'>
					<?php if ( $genoptions['productemptyoption'] ) { ?>
					<option value=""><?php _e( 'Select a product', 'bug-library' ); ?></option>
					<?php }

					    foreach ($products as $product) { ?>
						<option value="<?php echo intval( $product->term_id ); ?>" <?php selected( $product->term_id, $bugcatid ); ?>><?php echo esc_html( $product->name ); ?></option>
					<?php } ?>
				</select>
			<?php endif; ?>
	</div>
<?php } ?>

<?php if ( ! $genoptions['hideversionnumber'] ) { ?>
<div id='new-bug-version-section'><?php _e( 'Version Number', 'bug-library' ); ?> <span id='required'>*</span><br />
	<input type='text' id='new-bug-version' name='new-bug-version' size='16' <?php if ($valid == 0) echo "value='" . esc_html( sanitize_text_field( $_POST['new-bug-version'] ) ) . "'"; ?> />
</div>
<?php } ?>

<?php if ( ! $genoptions['hideissuetype'] ) { ?>
<div id='new-bug-type-section'><?php _e( 'Issue Type', 'bug-library' ); ?> <span id='required'>*</span><br />
<?php $types = get_terms('bug-library-types', 'orderby=name&hide_empty=0');

		if ($types) : ?>
			<select id='new-bug-type' name='new-bug-type'>
				<?php if ( $genoptions['issueemptyoption'] ) { ?>
					<option value=""><?php _e( 'Select an issue type', 'bug-library' ); ?></option>
				<?php }

				    foreach ( $types as $type ): ?>
					<option value="<?php echo intval( $type->term_id ); ?>" <?php selected( $type->term_id, $bugtypeid ); ?>><?php echo esc_html( $type->name ); ?></option>
				<?php endforeach; ?>
			</select>
		<?php endif; ?>
</div>
<?php } ?>

<div id='new-bug-desc-section'>
	<?php _e( 'Description', 'bug-library' ); ?> <span id='required'>*</span><br />
<textarea cols="60" rows="10" name="new-bug-desc"><?php if ($valid == 0) echo esc_html( sanitize_text_field( $_POST['new-bug-desc'] ) ); ?></textarea>
</div>

<div id='new-bug-name-section'>
	<?php _e( 'Issue Reporter Name', 'bug-library' ); ?><?php if ($genoptions['requirename'] == false) echo " (" . __( 'optional', 'bug-library' ) . ")"; else echo " <span id='required'>*</span>"; ?><br />
<input type='text' id='new-bug-reporter-name' name='new-bug-reporter-name' size='60' <?php if ($valid == 0) echo "value='" . esc_html( sanitize_text_field( $_POST['new-bug-reporter-name'] ) ) . "'"; ?> />
</div>

<div id='new-bug-email-section'>
	<?php _e( 'Issue Reported E-mail', 'bug-library' ); ?><?php if ($genoptions['requireemail'] == false) echo " (" . __( 'optional, for update notifications only', 'bug-library' ) . ")"; else echo " <span id='required'>*</span>";?><br />
<input type='text' id='new-bug-reporter-email' name='new-bug-reporter-email' size='60' <?php if ($valid == 0) echo "value='" . esc_html( sanitize_text_field( $_POST['new-bug-reporter-email'] ) ) . "'"; ?> />
</div>

<?php if ($genoptions['allowattach']): ?>
<?php _e( 'Attach File', 'bug-library' ); ?><br />
<input type="file" name="attachimage" id="attachimage" />
<?php endif; ?>

<?php if ($genoptions['showcaptcha']): ?>
	<div id='new-bug-captcha'><span id='captchaimage'><img src='<?php echo plugins_url( "captcha/easycaptcha.php", __FILE__ ); ?>' /></span><br />
	<?php _e('Enter code from above image', 'bug-library'); ?><input type='text' name='confirm_code' />
	</div>
<?php endif; ?>

<input type="submit" id='new-bug-submit' name='new-bug-submit' value="Submit" />

</form>
</div>
<?php elseif ($valid == 1): ?>
<div id='bug-library-submissionaccepted'>
<h2><?php _e( 'Thank you for your submission.', 'bug-library' ); ?></h2><br /><br />
<?php if ($genoptions['moderatesubmissions'] == 'true') echo __( "Your new issue will appear on the site once it has been moderated.", 'bug-library' ) . "<br /><br />"; ?>
<?php _e( 'Click', 'bug-library' ); ?> <a href='<?php echo home_url(); ?>?bug_library_popup_content=true'><?php _e( 'here', 'bug-library' ); ?></a> <?php _e( 'to submit a new issue or close the window to go continue browsing the database.', 'bug-library' ); ?>
</div>
<?php endif; ?>
</body>
