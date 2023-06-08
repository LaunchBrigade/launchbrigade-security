<?php
/**
 * Plugin Name: Launch Brigade Security
 * Plugin URI: https://github.com/LaunchBrigade/launchbrigade-security
 * Description: Disables iThemes Security notifications and sends notifications to Launch Brigade only. See https://help.ithemes.com/hc/en-us/articles/360038144834-How-to-Modify-the-Notification-Email-Recipient-s-
 * Version: 0.2.1
 * Author: Launch Brigade
 * Author URI: https://launchbrigade.com/
 * License: GPL2
 */


// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check if iThemes Security plugin is active
if ( is_plugin_active( 'better-wp-security/better-wp-security.php' ) ) {
	return;
}

// Check if ITSEC_NOTIFICATION_EMAIL constant is set
if (!defined('ITSEC_NOTIFICATION_EMAIL')) {
	add_action('admin_notices', 'launchbrigade_security_display_admin_notice');
}
// Display admin notice if ITSEC_NOTIFICATION_EMAIL constant is not set
function launchbrigade_security_display_admin_notice()
{
	?>
	<div class="notice notice-error">
		<p><?php echo esc_html__('Please set the ITSEC_NOTIFICATION_EMAIL constant in wp-config.php', 'launchbrigade-security'); ?></p>
	</div>
	<?php
}


// Plugin initialization
function launchbrigade_security_init() {
	// Send notifications to Launch Brigade only
	// This will override any other notification settings
	function ithemes_security_custom_notification_email( $emails ) {
		return [
			ITSEC_NOTIFICATION_EMAIL
		];
	}

	add_filter( 'itsec_notification_email_recipients', 'ithemes_security_custom_notification_email' );


	// Check for updates (only if the transient is not set or expired)
	if ( false === ( $update_data = get_transient( 'launchbrigade_security_update' ) ) ) {
		$update_data = launchbrigade_security_check_updates();
		set_transient( 'launchbrigade_security_update', $update_data, 12 * HOUR_IN_SECONDS ); // Cache for 12 hours
	}

	// Process the update check result
	$current_version = 'v0.2.1'; // Change to the current version of your plugin
	if ( $update_data && version_compare( $current_version, $update_data->tag_name, '<' ) ) {

		add_filter( 'pre_set_site_transient_update_plugins', function ( $transient ) use ( $update_data ) {
			$transient->response['launchbrigade-security/launchbrigade-security.php'] = $update_data;

			return $transient;
		} );
	}
}

add_action( 'plugins_loaded', 'launchbrigade_security_init' );

// Check for plugin updates
function launchbrigade_security_check_updates() {
	$plugin_slug     = 'launchbrigade-security'; // Change to your plugin's slug

	$github_username = 'LaunchBrigade'; // Change to your GitHub username
	$github_repo     = 'launchbrigade-security'; // Change to your GitHub repository name

	$github_response = wp_remote_get( "https://api.github.com/repos/{$github_username}/{$github_repo}/releases/latest" );
	$github_data     = json_decode( wp_remote_retrieve_body( $github_response ) );

	if ( $github_response['response']['code'] === 200 && ! empty( $github_data->tag_name ) ) {
		$download_url = $github_data->zipball_url;

		return (object) [
			'slug'        => $plugin_slug,
			'new_version' => $github_data->tag_name,
			'url'         => $github_data->html_url,
			'package'     => $download_url,
		];
	}

	return false;
}
