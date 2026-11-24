<?php
/**
 * Removes the plugin data when an administrator deletes the plugin.
 *
 * @package Soacot_Event_Booking_Limit
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_metadata( 'user', 0, 'soacot_event_booking_limit', '', true );
