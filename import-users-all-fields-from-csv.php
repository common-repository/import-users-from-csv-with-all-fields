<?php
/*
Plugin Name: Import Users from CSV With All Fields
Description: Import Users data with all fields from a csv file.
Version: 1.0.0
Author: ramorg2018
License: GPL2
Text Domain: import-users-all-fields-from-csv
*/

if ( ! defined( 'RCH_CSV_DELIMITER' ) ){
	define ( 'RCH_CSV_DELIMITER', ',' );
}


class RCH_Import_Users {
	private static $log_dir_path = '';
	private static $log_dir_url  = '';
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_rch_admin_pages' ) );
		add_action( 'init', array( __CLASS__, 'post_data_csv' ) );

		$upload_dir = wp_upload_dir();
		self::$log_dir_path = trailingslashit( $upload_dir['basedir'] );
		self::$log_dir_url  = trailingslashit( $upload_dir['baseurl'] );

		do_action('rch_after_init');
	}

	/**
	 * Add administration menus
	 *
	 * @since 0.1
	 **/
	public static function add_rch_admin_pages() {
		add_users_page( __( 'Import Users' , 'import-users-all-fields-from-csv'), __( 'Import Users' , 'import-users-all-fields-from-csv'), 'create_users', 'import-users-all-fields-from-csv', array( __CLASS__, 'rch_users_page' ) );
	}
	public static function post_data_csv() {
		if ( isset( $_POST['_wpnonce-is-iu-import-users-users-page_import'] ) ) {
			check_admin_referer( 'is-iu-import-users-users-page_import', '_wpnonce-is-iu-import-users-users-page_import' );

			if ( !empty( $_FILES['users_csv']['tmp_name'] ) ) {
				/* Setup settings variables */
				$filename              = sanitize_text_field( $_FILES['users_csv']['tmp_name'] );
				$password_nag          = isset( $_POST['password_nag'] ) ? sanitize_text_field( $_POST['password_nag'] ) : false;
				$users_update          = isset( $_POST['users_update'] ) ? sanitize_text_field( $_POST['users_update'] ) : false;
				$new_user_notification = isset( $_POST['new_user_notification'] ) ? sanitize_text_field( $_POST['new_user_notification'] ) : false;

				$results = self::rch_import_csv( $filename, array(
					'password_nag' => intval( $password_nag ),
					'new_user_notification' => intval( $new_user_notification ),
					'users_update' => intval( $users_update )
				) );

				if ( ! $results['user_ids'] ){
					/* No users imported? */
					wp_redirect( add_query_arg( 'import', 'fail', wp_get_referer() ) );
				} else if ( $results['errors'] ){
					/* Some users imported? */
					wp_redirect( add_query_arg( 'import', 'errors', wp_get_referer() ) );
				} else {
					/* All users imported? :D */
					wp_redirect( add_query_arg( 'import', 'success', wp_get_referer() ) );
				}
				exit;
			}

			wp_redirect( add_query_arg( 'import', 'file', wp_get_referer() ) );
			exit;
		}
	}
	public static function rch_users_page() {
		if ( ! current_user_can( 'create_users' ) ){
			wp_die( __( 'You do not have sufficient permissions to access this page.' , 'import-users-all-fields-from-csv') );
		}

		?>

		<div class="wrap">
			<h2><?php _e( 'Import Users All Fields From a CSV File' , 'import-users-all-fields-from-csv'); ?></h2>
			<?php
				$error_log_file = self::$log_dir_path . 'rch_errors.log';
				$error_log_url  = self::$log_dir_url . 'rch_errors.log';

				if ( ! file_exists( $error_log_file ) ) {
					if ( ! @fopen( $error_log_file, 'x' ) ){
						$message = sprintf( __( 'Notice: please make the directory %s writable so that you can see the error log.' , 'import-users-all-fields-from-csv'), self::$log_dir_path );
						self::rch_render_notice('updated', $message);
					}
				}

				$import = isset( $_GET['import'] ) ? sanitize_text_field( $_GET['import'] ) : false;

				if ( $import ) {
					$error_log_msg = '';
					if ( file_exists( $error_log_file ) ){
						$error_log_msg = sprintf( __( ", please <a href='%s' target='_blank'>check the error log</a>", 'import-users-all-fields-from-csv'), esc_url( $error_log_url ) );
					}

					switch ( $import ) {
						case 'file':
							$message = __( 'Error during file upload.' , 'import-users-all-fields-from-csv');
							self::rch_render_notice('error', $message);
							break;
						case 'data':
							$message = __( 'Cannot extract data from uploaded file or no file was uploaded.' , 'import-users-all-fields-from-csv');
							self::rch_render_notice('error', $message);
							break;
						case 'fail':
							$message = sprintf( __( 'No user was successfully imported%s.' , 'import-users-all-fields-from-csv'), $error_log_msg );
							self::rch_render_notice('error', $message);
							break;
						case 'errors':
							$message = sprintf( __( 'Some users were successfully imported but some were not%s.' , 'import-users-all-fields-from-csv'), $error_log_msg );
							self::rch_render_notice('update-nag', $message);
							break;
						case 'success':
							$message = __( 'Users import was successful.' , 'import-users-all-fields-from-csv');
							self::rch_render_notice('updated', $message);
							break;
						default:
							break;
					}
				}
			?>

			<form method="post" action="" enctype="multipart/form-data">
				<?php wp_nonce_field( 'is-iu-import-users-users-page_import', '_wpnonce-is-iu-import-users-users-page_import' ); ?>

				<?php do_action('rch_import_page_before_table'); ?>

				<table><tr><td>
				<table class="form-table widefat wp-list-table" style='padding: 1px;'>
					<?php do_action('rch_import_page_inside_table_top'); ?>
					<tr valign="top">
						<td scope="row">
							<strong>
								<label for="users_csv"><?php _e( 'CSV file' , 'import-users-all-fields-from-csv'); ?></label>
							</strong>
						</td>
						<td>
							<input type="file" id="users_csv" name="users_csv" value="" class="all-options" /><br />
							<span class="description">
								<?php
									echo sprintf( __( 'Please Download Demo File: <a href="%s">Download Demo File</a>.' , 'import-users-all-fields-from-csv'), esc_url( plugin_dir_url(__FILE__).'demo-file/demo-import-users-csv.csv' ) );
								?>
							</span>
						</td>
					</tr>
					<tr valign="top">
						<td scope="row">
							<strong>
								<?php _e( 'Notification' , 'import-users-all-fields-from-csv'); ?>
							</strong>
						</td>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><span><?php _e( 'Notification' , 'import-users-all-fields-from-csv'); ?></span></legend>

								<label for="new_user_notification">
									<input id="new_user_notification" name="new_user_notification" type="checkbox" value="1" />
									<?php _e('Send to new users', 'import-users-all-fields-from-csv'); ?>
								</label>
							</fieldset>
						</td>
					</tr>
					<tr valign="top">
						<td scope="row">
							<strong>
								<?php _e( 'Password nag' , 'import-users-all-fields-from-csv'); ?>
							</strong>
						</td>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><span><?php _e( 'Password nag' , 'import-users-all-fields-from-csv'); ?></span></legend>

								<label for="password_nag">
									<input id="password_nag" name="password_nag" type="checkbox" value="1" />
									<?php _e('Show password nag on new users signon', 'import-users-all-fields-from-csv') ?>
								</label>
							</fieldset>
						</td>
					</tr>
					<tr valign="top">
						<td scope="row"><strong><?php _e( 'Users update' , 'import-users-all-fields-from-csv'); ?></strong></td>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><span><?php _e( 'Users update' , 'import-users-all-fields-from-csv' ); ?></span></legend>

								<label for="users_update">
									<input id="users_update" name="users_update" type="checkbox" value="1" />
									<?php _e( 'Update user when a username or email exists', 'import-users-all-fields-from-csv' ) ;?>
								</label>
							</fieldset>
						</td>
					</tr>

					<?php do_action('rch_import_page_inside_table_bottom'); ?>

				</table>
				</td>
				</tr>
				<tr>
				<td valign="top">
					<a title="Download Demo File" href="<?php echo esc_url( plugin_dir_url(__FILE__).'demo-file/demo-import-users-csv.csv' ); ?>">
					<img width="700px;" src="<?php echo esc_url( plugin_dir_url(__FILE__).'demo-file/import_users.png' ); ?>">
					</a>
				</td>

				</tr>
				</table>

				<?php do_action('rch_import_page_after_table'); ?>

				<p class="submit">
				 	<input type="submit" class="button-primary" value="<?php _e( 'Import' , 'import-users-all-fields-from-csv'); ?>" />
				</p>
			</form>
		<?php
	}

	public static function rch_import_csv( $filename, $args ) {
		@set_time_limit(0);

		if ( ! class_exists( 'RCHReadCSV' ) ) {
			include( plugin_dir_path( __FILE__ ) . 'rch-class-readcsv.php' );
		}

		$errors = $user_ids = array();

		$defaults = array(
			'password_nag' 			=> false,
			'new_user_notification' => false,
			'users_update' 			=> false
		);

		extract( wp_parse_args( $args, $defaults ) );

		/*
		 * User data field map, used to match datasets
		*/
		$userdata_fields = array(
			'ID',
			'user_login',
			'user_pass',
			'user_email',
			'user_url',
			'user_nicename',
			'display_name',
			'user_registered',
			'first_name',
			'last_name',
			'nickname',
			'description',
			'rich_editing',
			'comment_shortcuts',
			'admin_color',
			'use_ssl',
			'show_admin_bar_front',
			'show_admin_bar_admin',
			'role',
			'billing_company',
			'billing_address_1',
			'billing_address_2',
			'billing_city',
			'billing_postcode',
			'billing_country',
			'billing_state',
			'billing_phone'
		);

		/* Filter for the user field map */
		apply_filters('rch_userdata_fields', $userdata_fields);

		/* Loop through the file lines */
		$file_handle = @fopen( $filename, 'r' );
		if($file_handle) {
			$csv_reader = new RCHReadCSV( $file_handle, RCH_CSV_DELIMITER, "\xEF\xBB\xBF" ); // Skip any UTF-8 byte order mark.

			$first = true;
			$rkey = 0;
			while ( ( $line = $csv_reader->csv_row() ) !== NULL ) {
				if ( empty( $line ) ) {
					if ( $first ){
						/* If the first line is empty, abort */
						break;
					} else{
						/* If another line is empty, just skip it */
						continue;
					}
				}

				if ( $first ) {
					/* If we are on the first line, the columns are the headers */
					$headers = $line;
					$first = false;
					continue;
				}

				/* Separate user data from meta */
				$userdata = $usermeta = array();
				foreach ( $line as $ckey => $column ) {
					$column_name = $headers[$ckey];
					$column = trim( $column );

					if ( in_array( $column_name, $userdata_fields ) ) {
						$userdata[$column_name] = $column;
					} else {
						/**
						 * Data cleanup:
						 *
						 * Let's do a loose match on the column name
						 * This is to allow for small typos like 'UsEr PaSS' to be converted to 'user_pass'
						 *
						 * Todo: Add support for all uppercase as well
						*/
						$formatted_column_name = strtolower($column_name);
						$formatted_column_name = str_replace(' ', '_', $formatted_column_name);
						$formatted_column_name = str_replace('-', '_', $formatted_column_name);
						if( in_array( $formatted_column_name, $userdata_fields) ){
							/**
							 * We have a formatted match
							*/
							$userdata[$formatted_column_name] = $column;
						} else {
							/*
							 * We still have no match
							 * let's assume this is a meta value
							*/
							$usermeta[$column_name] = $column;
						}
					}
				}

				/*
				 * Hooks to allow other plugins from filtering this data
				*/
				$userdata = apply_filters( 'rch_import_userdata', $userdata, $usermeta );
				$usermeta = apply_filters( 'rch_import_usermeta', $usermeta, $userdata );

				if ( empty( $userdata ) ){
					/* If no user data, bailout! */
					continue;
				}

				/* Hook to allow other plugins to execute additional code pre-import */
				do_action( 'pre_user_import', $userdata, $usermeta );

				$user = $user_id = false;
				if ( isset( $userdata['ID'] ) ){
					$user = get_user_by( 'ID', $userdata['ID'] );
				}

				/**
				 * Find the user by some alternative fields
				 *
				 * Fields checked: user_login, user_email
				*/
				if ( ! $user && $users_update ) {
					if ( isset( $userdata['user_login'] ) ){
						$user = get_user_by( 'login', $userdata['user_login'] );
					}

					if ( ! $user && isset( $userdata['user_email'] ) ){
						$user = get_user_by( 'email', $userdata['user_email'] );
					}
				}

				$update = false;
				if ( $user ) {
					$userdata['ID'] = $user->ID;
					$update = true;
				}

				if ( ! $update && empty( $userdata['user_pass'] ) ){
					/* No password set for this user, let's generate one automatically */
					$userdata['user_pass'] = wp_generate_password( 12, false );
				}

				if ( $update ){
					$user_id = wp_update_user( $userdata );
				} else {
					$user_id = wp_insert_user( $userdata );
				}

				/* Is there an error o_O? */
				if ( is_wp_error( $user_id ) ) {
					$errors[$rkey] = $user_id;
				} else {
					/* If no error, let's update the user meta too! */
					if ( $usermeta ) {
						foreach ( $usermeta as $metakey => $metavalue ) {
							$metavalue = maybe_unserialize( $metavalue );
							update_user_meta( $user_id, $metakey, $metavalue );
						}
					}

					/* If we created a new user, maybe set password nag and send new user notification? */
					if ( ! $update ) {
						if ( $password_nag ){
							update_user_option( $user_id, 'default_password_nag', true, true );
						}

						if ( $new_user_notification ) {
							wp_new_user_notification( $user_id, null, 'user' );
						}
					}

					/* Hook to allow other plugins to run functionality post import */
					do_action( 'user_import', $user_id );

					//billing address
					update_user_meta( $user_id, 'billing_company', $userdata['billing_company'] );
					update_user_meta( $user_id, 'billing_address_1', $userdata['billing_address_1'] );
					update_user_meta( $user_id, 'billing_address_2', $userdata['billing_address_2'] );
					update_user_meta( $user_id, 'billing_city', $userdata['billing_city'] );
					update_user_meta( $user_id, 'billing_postcode', $userdata['billing_postcode'] );
					update_user_meta( $user_id, 'billing_phone', $userdata['billing_phone'] );
					update_user_meta( $user_id, 'billing_state', $userdata['billing_state'] );
					update_user_meta( $user_id, 'billing_country', $userdata['billing_country'] );

					$user_ids[] = $user_id;
				}

				$rkey++;
			}
			fclose( $file_handle );
		} else {
			$errors[] = new WP_Error('file_read', 'Unable to open CSV file.');
		}

		/* One more thing to do after all imports? */
		do_action( 'users_import', $user_ids, $errors );

		/* Let's log the errors */
		self::rch_log_errors( $errors );

		return array(
			'user_ids' => $user_ids,
			'errors'   => $errors
		);
	}

	private static function rch_log_errors( $errors ) {
		if ( empty( $errors ) ){
			return;
		}

		$log = @fopen( self::$log_dir_path . 'rch_errors.log', 'a' );
		@fwrite( $log, sprintf( __( 'BEGIN %s' , 'import-users-all-fields-from-csv'), date_i18n( 'Y-m-d H:i:s', time() ) ) . "\n" );

		foreach ( $errors as $key => $error ) {
			$line = $key + 1;
			$message = $error->get_error_message();
			@fwrite( $log, sprintf( __( '[Line %1$s] %2$s' , 'import-users-all-fields-from-csv'), $line, $message ) . "\n" );
		}

		@fclose( $log );
	}
	private static function rch_render_notice($class, $message){
		$class = esc_attr($class);
		echo "<div class='$class'><p><strong>$message</strong></p></div>";
	}
}

RCH_Import_Users::init();
