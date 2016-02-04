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

			// And do the redirect.
			wp_safe_redirect( $link );
			exit;
		}

		// Bail if no user roles were passed.
		if ( empty( $_POST['gupr-roles'] ) ) {

			// Set my redirect URL.
			$link   = add_query_arg( array( 'gupr-process' => 1, 'error' => 1, 'reason' => 'noroles' ), $link );

			// And do the redirect.
			wp_safe_redirect( $link );
			exit;
		}

		// Set my roles.
		$roles  = array_map( 'sanitize_key', $_POST['gupr-roles'] );

		// Get my user IDs to run.
		if ( false === $users = self::get_all_user_ids( get_current_user_id(), $roles ) ) {

			// Set my redirect URL.
			$link   = add_query_arg( array( 'gupr-process' => 1, 'error' => 1, 'reason' => 'users' ), $link );

			// And do the redirect.
			wp_safe_redirect( $link );
			exit;
		}

		// Run the actual reset processing itself.
		if ( false === $process = self::reset_user_passwords( $users ) ) {

			// Set my redirect URL.
			$link   = add_query_arg( array( 'gupr-process' => 1, 'error' => 1, 'reason' => 'process' ), $link );

			// And do the redirect.
			wp_safe_redirect( $link );
			exit;
		}

		// Get a count of users for the message.
		$count  = count( $users );

		// It worked, so redirect to a success page.
		$link   = add_query_arg( array( 'gupr-process' => 1, 'success' => 1, 'count' => absint( $count ) ), $link );

		// And do the redirect.
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

				case 'noroles': // They didn't select a role.

					$notice = __( 'You must select at least 1 user role. Please try again.', 'global-user-password-reset' );
					break;

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
			echo '<p>' . esc_html__( 'Select the user role or roles you want to reset passwords for.', 'global-user-password-reset' ) . '</p>';

			// Wrap the form itself.
			echo '<form method="post" action="' . esc_url( $link ) . '">';

				// List my display of available user roles.
				echo self::list_all_user_roles();

				// A line break. For reasons.
				echo '<br>';

				// Warn people.
				echo '<p><strong>' . esc_html__( 'WARNING', 'global-user-password-reset' ) . '</strong>: ' . esc_html__( 'This is not reversable, and any existing password data will not be retrievable.', 'global-user-password-reset' ) . '</p>';

				// Output a checkbox to make sure people know what's up.
				echo '<p><label for="gupr-reset-check"><input required="required" type="checkbox" id="gupr-reset-check" name="gupr-reset-check" value="1">' . esc_html__( 'Yes, I am aware of what I am doing.', 'global-user-password-reset' ) . '</label></p>';

				// Output the button itself.
				submit_button( esc_html__( 'Reset Passwords', 'global-user-password-reset' ), 'primary', 'gupr-reset-submit' );

				// Output the nonce field.
				wp_nonce_field( 'gupr-reset-nonce', 'gupr-reset-nonce', false, true );

			echo '</form>';

		// Close out the page.
		echo '</div>';
	}

	/**
	 * Get the user role data array, or a count of it.
	 *
	 * @param  boolean $count  Whether to return the count or not.
	 *
	 * @return array   $data   The user role data array, or count.
	 */
	public static function get_user_role_data( $count = false ) {

		// First call the global for wp_roles.
		global $wp_roles;

		// Fetch the name array.
		$roles  = $wp_roles->get_names();

		// Bail without roles.
		if ( empty( $roles ) || ! is_array( $roles ) ) {
			return false;
		}

		// Return the array, or the count of the array.
		return ! empty( $count ) ? count( $roles ) : $roles;
	}

	/**
	 * Get the list of all available user roles.
	 *
	 * @return HTML the markup list with the values.
	 */
	public static function list_all_user_roles() {

		// Fetch my roles
		if ( false === $roles = self::get_user_role_data() ) {
			return;
		}

		// Set an empty.
		$build  = '';

		// Start the list markup.
		$build .= '<ul>';

		// Loop the roles into a key / value pair.
		foreach ( $roles as $name => $label ) {

			// Open the single item markup.
			$build .= '<li>';

				// Build the checkbox label.
				$build .= '<label for="gupr-role-' . esc_attr( $name ) . '"><input type="checkbox" id="gupr-role-' . esc_attr( $name ) . '" name="gupr-roles[]" value="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label>';

			// Close the single item markup.
			$build .= '</li>';
		}

		// Close the list markup.
		$build .= '</ul>';

		// Return it.
		return $build;
	}

	/**
	 * Fetch all the user IDs contained on site.
	 *
	 * @param  integer $current  The current user ID.
	 * @param  array   $roles    The user role(s) to include.
	 *
	 * @return array   $users    The array of user IDs.
	 */
	public static function get_all_user_ids( $current = 0, $roles = array() ) {

		// Bail if on admin, or not an authorized user.
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Make sure we have a current user ID and it's the same person running the function.
		$exclude    = ! empty( $current ) && absint( $current ) === get_current_user_id() ? $current : get_current_user_id();

		// Get our role count.
		$count  = self::get_user_role_data( true );

		// If we all roles, no need to loop.
		if ( absint( $count ) === count( $roles ) ) {

			// Set my args for the `get_users` call.
			$args   = array(
				'exclude'   => absint( $exclude ),
				'fields'    => 'ID',
			);

			// Filter the args and call the function itself.
			$users  = get_users( apply_filters( 'gupr_user_query_args', $args ) );

			// Either return the users or false if we have none.
			return ! empty( $users ) ? $users : false;
		}

		// Now set an empty array for our users, since we are gonna do a lot of loopin' going on.
		$users  = array();

		// Now loop my user roles.
		foreach ( $roles as $role ) {

			// Set my args for the `get_users` call.
			$args   = array(
				'role'      => $role,
				'exclude'   => absint( $exclude ),
				'fields'    => 'ID',
			);

			// Filter the args and call the function itself.
			$data  = get_users( apply_filters( 'gupr_user_query_args', $args, $role ) );

			// If we have no users in this role, continue to the next.
			if ( empty( $data ) ) {
				continue;
			}

			// Merge our data array.
			$users = array_merge( $data, $users );
		}

		// Return the array of IDs, or false if none exist.
		return ! empty( $users ) ? $users : false;
	}

	/**
	 * Run the reset function itself on the array of user IDs.
	 *
	 * @param  array $users  The array of user IDs.
	 *
	 * @return bool          Whether or not the function succeeded.
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

