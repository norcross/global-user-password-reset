<?php
/**
 * Global User Password Reset - Admin Module
 *
 * Contains our admin side related functionality.
 *
 * @package Global User Password Reset
 */

/**
 * Start our engines.
 */
class GlobalUserReset_Admin {

	/**
	 * Call our hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_init',                       array( $this, 'run_reset'              )           );
		add_action( 'admin_notices',                    array( $this, 'reset_notices'          )           );
		add_action( 'admin_menu',                       array( $this, 'admin_menu'             )           );
	}

	/**
	 * Run our reset function.
	 *
	 * @return void
	 */
	public function run_reset() {

		// Bail if missing or incorrect page reference.
		if ( empty( $_GET['page'] ) || ! empty( $_GET['page'] ) && 'global-reset' !== $_GET['page'] ) {
			return;
		}

		// Check nonce and bail if missing or not valid.
		if ( empty( $_POST['gupr-reset-nonce'] ) || ! wp_verify_nonce( $_POST['gupr-reset-nonce'], 'gupr-reset-nonce' ) ) {
			return;
		}

		// Set my base URL for redirecting.
		$link   = menu_page_url( 'global-reset', 0 );

		// Bail if the checkmark is missing.
		if ( empty( $_POST['gupr-reset-check'] ) ) {

			// Set my redirect URL.
			$link   = add_query_arg( array( 'gupr-process' => 1, 'error' => 1, 'reason' => 'checkmark' ), $link );

			// and do the redirect
			wp_safe_redirect( $link );
			exit;
		}

		// Get my user IDs to run.
		if ( false === $users = self::get_all_user_ids( get_current_user_id() ) ) {

			// Set my redirect URL.
			$link   = add_query_arg( array( 'gupr-process' => 1, 'error' => 1, 'reason' => 'users' ), $link );

			// and do the redirect
			wp_safe_redirect( $link );
			exit;
		}

		// Run the actual reset processing itself.
		if ( false === $process = self::reset_user_passwords( $users ) ) {

			// Set my redirect URL.
			$link   = add_query_arg( array( 'gupr-process' => 1, 'error' => 1, 'reason' => 'process' ), $link );

			// and do the redirect
			wp_safe_redirect( $link );
			exit;
		}

		// Get a count of users for the message.
		$count  = count( $users );

		// It worked, so redirect to a success page.
		$link   = add_query_arg( array( 'gupr-process' => 1, 'success' => 1, 'count' => absint( $count ) ), $link );

		// and do the redirect
		wp_safe_redirect( $link );
		exit;
	}

	/**
	 * Show the notices for the user reset processing.
	 *
	 * @return void
	 */
	public function reset_notices() {

		// Bail if missing or incorrect page reference.
		if ( empty( $_GET['page'] ) || ! empty( $_GET['page'] ) && 'global-reset' !== $_GET['page'] ) {
			return;
		}

		// Bail without our special flag.
		if ( empty( $_GET['gupr-process'] ) ) {
			return;
		}

		// Check for the error and success flags.
		$error   = ! empty( $_GET['error'] ) ? sanitize_key( $_GET['error'] ) : '';
		$success = ! empty( $_GET['success'] ) ? sanitize_key( $_GET['success'] ) : '';

		// Check for error first.
		if ( ! empty( $error ) ) {

			// Set a default message.
			$notice = __( 'There was an error with your request. Please try again later.', 'global-user-password-reset' );

			// Set our reason.
			$reason = ! empty( $_GET['reason'] ) ? sanitize_text_field( wp_unslash( $_GET['reason'] ) ) : ''; // Input var okay.

			// Do the case switch on our reasons.
			switch ( $reason ) {

				case 'checkmark': // They didn't check the box.

					$notice = __( 'You did not check the required box. Please try again.', 'global-user-password-reset' );
					break;

				case 'users': // No users to reset.

					$notice = __( 'There are no users to process.', 'global-user-password-reset' );
					break;

				case 'process': // The process didn't run.

					$notice = __( 'The reset process failed. Please try again.', 'global-user-password-reset' );
					break;

				// End all case breaks.
			}

			// Echo out the message.
			echo '<div id="message" class="error notice fade is-dismissible">';
				echo '<p>' . esc_html( $notice ) . '</p>';
			echo '</div>';

			// And return.
			return;
		}

		// Check for success.
		if ( ! empty( $success ) ) {

			// If we don't have a count, just display a generic message.
			if ( empty( absint( $_GET['count'] ) ) ) {

				// Create the message.
				$notice = __( 'Success! The passwords have been reset.', 'global-user-password-reset' );

				// Echo out the message.
				echo '<div id="message" class="updated notice fade is-dismissible">';
					echo '<p>' . esc_html( $notice ) . '</p>';
				echo '</div>';

				// And return.
				return;
			}

			// Set the count.
			$count  = absint( $_GET['count'] );

			// Set the message.
			$notice = sprintf( _n( 'Success! %d password have been reset.', 'Success! %d passwords have been reset.', $count, 'global-user-password-reset' ), $count );

			// Echo out the message.
			echo '<div id="message" class="updated notice fade is-dismissible">';
				echo '<p>' . esc_html( $notice ) . '</p>';
			echo '</div>';

			// And return.
			return;
		}
	}

	/**
	 * Call our individual admin pages.
	 *
	 * @return void
	 */
	public function admin_menu() {
		add_users_page( __( 'Global User Password Reset', 'global-user-password-reset' ), __( 'Password Reset', 'global-user-password-reset' ), 'manage_options', 'global-reset', array( $this, 'admin_page' ) );
	}

	/**
	 * Build out the page to run our processes.
	 *
	 * @return void
	 */
	public function admin_page() {

		// Bail on a non authorized user.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Get my page link.
		$link   = menu_page_url( 'global-reset', false );

		// Build out the page.
		echo '<div class="wrap">';

			// Title it.
			echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';

			// Intro it.
			echo '<p>This will be some content about how this works, what to do, etc etc. </p>';

			// Wrap the form itself.
			echo '<form method="post" action="' . esc_url( $link ) . '">';

				// Output a checkbox to make sure people know what's up.
				echo '<p><label for="gupr-reset-check"><input required="required" type="checkbox" id="gupr-reset-check" name="gupr-reset-check" value="1">' . __( 'Yes, I am aware of what I am doing.', 'global-user-password-reset' ) . '</label></p>';

				// Output the button itself.
				echo get_submit_button( __( 'Reset Passwords', 'global-user-password-reset' ), array( 'primary', 'large' ), 'gupr-reset-submit' );

				// Output the nonce field
				echo wp_nonce_field( 'gupr-reset-nonce', 'gupr-reset-nonce', false, false );

			echo '</form>';

		// Close out the page.
		echo '</div>';
	}

	/**
	 * Fetch all the user IDs contained on site.
	 *
	 * @param  integer $current   The current user ID.
	 * @param  bool    $count     Whether to return the count instead of the IDs.
	 *
	 * @return array   $user_ids  The array of user IDs.
	 */
	public static function get_all_user_ids( $current = 0, $count = false ) {

		// Bail if on admin, or not an authorized user.
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Make sure we have a current user ID and it's the same person running the function.
		$exclude    = ! empty( $current ) && absint( $current ) === get_current_user_id() ? $current : get_current_user_id();

		// Call the global database object.
		global $wpdb;

		// Set up our query.
		$query	= $wpdb->prepare("
			SELECT	ID
			FROM	$wpdb->users
			WHERE	ID NOT LIKE '%d'
		", absint( $exclude ) );

		// Fetch the column data.
		$user_ids   = $wpdb->get_col( $query );

		// If we have none, just return false.
		if ( empty( $user_ids ) ) {
			return false;
		}

		// Return the array of IDs, or the count if requested.
		return ! empty( $count ) ? count( $user_ids ) : $user_ids;
	}

	/**
	 * Run the reset function itself on the array of user IDs.
	 *
	 * @param  array  $users  The array of user IDs.
	 *
	 * @return bool           Whether or not the function succeeded.
	 */
	public static function reset_user_passwords( $users = array() ) {

		// Bail right away if we don't have users, or if it isn't an array.
		if ( empty( $users ) || ! is_array( $users ) ) {
			return false;
		}

		// Loop my user IDs.
		foreach ( $users as $user_id ) {

			// Get my password.
			$pass   = wp_generate_password( 20, true, false );

			// Generate the actual password.
			wp_set_password( $pass, $user_id );
		}

		// Return true to indicate the function completed.
		return true;
	}

	// End our class.
}

// Call our class.
$GlobalUserReset_Admin = new GlobalUserReset_Admin();
$GlobalUserReset_Admin->init();

