<?php
/**
 * Plugin Name:       SOACOT Event Booking Limit
 * Description:       Limits the number of Events Manager events that a person without a Paid Memberships Pro membership can book.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  events-manager, paid-memberships-pro
 * Author:            EncodeDotHost
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       soacot-event-booking-limit
 *
 * @package Soacot_Event_Booking_Limit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * User meta key for the limit of one person.
 *
 * No value: the default limit applies. 0: no limit. 1 or more: the limit for this person.
 */
const SOACOT_EVENT_BOOKING_LIMIT_META_KEY = 'soacot_event_booking_limit';

/**
 * Highest limit that the profile field accepts.
 */
const SOACOT_EVENT_BOOKING_LIMIT_MAX = 999;

/**
 * Get the default limit for non-members.
 *
 * @return int Number of events. 0 means no limit.
 */
function soacot_event_booking_limit_get_default() {
	/**
	 * Filters the default number of events that a non-member can book.
	 *
	 * @param int $limit Number of events. 0 means no limit.
	 */
	return absint( apply_filters( 'soacot_event_booking_limit_default', 2 ) );
}

/**
 * Get the limit that applies to one user.
 *
 * @param int $user_id User ID.
 * @return int Number of events. 0 means no limit.
 */
function soacot_event_booking_limit_get_limit( $user_id ) {
	$override = get_user_meta( $user_id, SOACOT_EVENT_BOOKING_LIMIT_META_KEY, true );

	if ( '' === $override || ! is_numeric( $override ) ) {
		return soacot_event_booking_limit_get_default();
	}

	return absint( $override );
}

/**
 * Count the events that a user has a booking for.
 *
 * Rejected (2), cancelled (3), and expired waitlist (8) bookings do not count.
 * Booking intents (10) are not bookings, thus they do not count.
 *
 * @param int $user_id          User ID.
 * @param int $exclude_event_id Events Manager event ID to ignore, or 0.
 * @return int Number of different events.
 */
function soacot_event_booking_limit_count_events( $user_id, $exclude_event_id = 0 ) {
	global $wpdb;

	if ( ! defined( 'EM_BOOKINGS_TABLE' ) ) {
		return 0;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom Events Manager table. The count must be current.
	$count = $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(DISTINCT event_id) FROM %i WHERE person_id = %d AND event_id != %d AND booking_status NOT IN (2, 3, 8, 10)',
			EM_BOOKINGS_TABLE,
			absint( $user_id ),
			absint( $exclude_event_id )
		)
	);

	return absint( $count );
}

/**
 * Find if a user cannot book one more event.
 *
 * @param int $user_id  User ID. 0 is a visitor without an account.
 * @param int $event_id Events Manager event ID of the event to book.
 * @return bool True if the user is at the limit.
 */
function soacot_event_booking_limit_is_reached( $user_id, $event_id ) {
	$user_id = absint( $user_id );

	// A visitor without an account has no bookings.
	if ( 0 === $user_id ) {
		return false;
	}

	// Without Paid Memberships Pro there are no members, thus do not block bookings.
	if ( ! function_exists( 'pmpro_hasMembershipLevel' ) || pmpro_hasMembershipLevel( null, $user_id ) ) {
		return false;
	}

	$limit = soacot_event_booking_limit_get_limit( $user_id );
	if ( 0 === $limit ) {
		return false;
	}

	return soacot_event_booking_limit_count_events( $user_id, $event_id ) >= $limit;
}

/**
 * Get the message for a user who is at the limit.
 *
 * @param int $user_id User ID.
 * @return string HTML that is safe to print.
 */
function soacot_event_booking_limit_get_message( $user_id ) {
	$limit = soacot_event_booking_limit_get_limit( $user_id );

	$message = sprintf(
		/* translators: 1: number of events, 2: URL of the membership page, 3: URL of the contact page. */
		_n(
			'Non-members can book a maximum of %1$d event. See our <a href="%2$s">membership page</a> to find out more, or <a href="%3$s">contact us</a> if you think this is wrong.',
			'Non-members can book a maximum of %1$d events. See our <a href="%2$s">membership page</a> to find out more, or <a href="%3$s">contact us</a> if you think this is wrong.',
			$limit,
			'soacot-event-booking-limit'
		),
		$limit,
		esc_url( home_url( '/join/' ) ),
		esc_url( home_url( '/contact/' ) )
	);

	/**
	 * Filters the message for a user who is at the limit.
	 *
	 * @param string $message Message HTML.
	 * @param int    $limit   Limit that applies to the user.
	 * @param int    $user_id User ID.
	 */
	$message = apply_filters( 'soacot_event_booking_limit_message', $message, $limit, $user_id );

	return wp_kses( (string) $message, array( 'a' => array( 'href' => true ) ) );
}

/**
 * Refuse a booking if the person is at the limit.
 *
 * A manual booking with "override" in wp-admin sets EM_Bookings::$disable_restrictions,
 * thus staff can always book for a person.
 *
 * @param bool       $result  Result of the validation at this point.
 * @param EM_Booking $booking Booking in validation.
 * @return bool
 */
function soacot_event_booking_limit_validate( $result, $booking ) {
	if ( ! $booking instanceof EM_Booking || ! empty( EM_Bookings::$disable_restrictions ) ) {
		return $result;
	}

	$person  = $booking->get_person();
	$user_id = empty( $person->ID ) ? 0 : absint( $person->ID );

	if ( soacot_event_booking_limit_is_reached( $user_id, absint( $booking->event_id ) ) ) {
		$booking->add_error( soacot_event_booking_limit_get_message( $user_id ) );
		return false;
	}

	return $result;
}
add_filter( 'em_booking_validate', 'soacot_event_booking_limit_validate', 10, 2 );

/**
 * Show the message above the booking form if the current user is at the limit.
 *
 * @param EM_Event $event Event of the booking form.
 * @return void
 */
function soacot_event_booking_limit_form_notice( $event ) {
	if ( ! $event instanceof EM_Event || ! is_user_logged_in() || ! empty( EM_Bookings::$disable_restrictions ) ) {
		return;
	}

	$user_id = get_current_user_id();
	if ( ! soacot_event_booking_limit_is_reached( $user_id, absint( $event->event_id ) ) ) {
		return;
	}

	// Events Manager shows its own message to a person who has a booking for this event.
	if ( $event->get_bookings()->has_booking( $user_id ) ) {
		return;
	}

	printf(
		'<div class="statusnotice soacot-event-booking-limit-notice"><div class="em-notice em-notice-error notice notice-error"><p>%s</p></div></div>',
		soacot_event_booking_limit_get_message( $user_id ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The function returns wp_kses() output.
	);
}
add_action( 'em_booking_form_top', 'soacot_event_booking_limit_form_notice' );

/**
 * Show the limit field on the user profile page in wp-admin.
 *
 * @param WP_User $user User in the editor.
 * @return void
 */
function soacot_event_booking_limit_profile_field( $user ) {
	if ( ! current_user_can( 'edit_users' ) || ! current_user_can( 'edit_user', $user->ID ) ) {
		return;
	}

	$override = get_user_meta( $user->ID, SOACOT_EVENT_BOOKING_LIMIT_META_KEY, true );
	$value    = ( '' === $override || ! is_numeric( $override ) ) ? '' : (string) absint( $override );

	wp_nonce_field( 'soacot_event_booking_limit_save_' . $user->ID, 'soacot_event_booking_limit_nonce' );
	?>
	<h2><?php esc_html_e( 'Event booking limit', 'soacot-event-booking-limit' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">
				<label for="soacot_event_booking_limit"><?php esc_html_e( 'Events this person can book', 'soacot-event-booking-limit' ); ?></label>
			</th>
			<td>
				<input type="number" class="small-text" min="0" max="<?php echo esc_attr( SOACOT_EVENT_BOOKING_LIMIT_MAX ); ?>" step="1" name="soacot_event_booking_limit" id="soacot_event_booking_limit" value="<?php echo esc_attr( $value ); ?>">
				<p class="description">
					<?php
					printf(
						/* translators: 1: default number of events, 2: number of events booked. */
						esc_html__( 'Applies only while this person has no membership. Leave empty for the default limit (%1$d). Enter 0 for no limit. Events booked at this time: %2$d.', 'soacot-event-booking-limit' ),
						absint( soacot_event_booking_limit_get_default() ),
						absint( soacot_event_booking_limit_count_events( $user->ID ) )
					);
					?>
				</p>
			</td>
		</tr>
	</table>
	<?php
}
add_action( 'show_user_profile', 'soacot_event_booking_limit_profile_field' );
add_action( 'edit_user_profile', 'soacot_event_booking_limit_profile_field' );

/**
 * Save the limit field of the user profile page.
 *
 * @param int $user_id ID of the user in the editor.
 * @return void
 */
function soacot_event_booking_limit_profile_save( $user_id ) {
	$user_id = absint( $user_id );

	if ( ! current_user_can( 'edit_users' ) || ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}

	if ( ! isset( $_POST['soacot_event_booking_limit_nonce'], $_POST['soacot_event_booking_limit'] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['soacot_event_booking_limit_nonce'] ) );
	if ( ! wp_verify_nonce( $nonce, 'soacot_event_booking_limit_save_' . $user_id ) ) {
		return;
	}

	$value = trim( sanitize_text_field( wp_unslash( $_POST['soacot_event_booking_limit'] ) ) );

	// Empty or not a whole number: remove the override, thus the default limit applies.
	if ( '' === $value || ! ctype_digit( $value ) ) {
		delete_user_meta( $user_id, SOACOT_EVENT_BOOKING_LIMIT_META_KEY );
		return;
	}

	update_user_meta( $user_id, SOACOT_EVENT_BOOKING_LIMIT_META_KEY, min( absint( $value ), SOACOT_EVENT_BOOKING_LIMIT_MAX ) );
}
add_action( 'personal_options_update', 'soacot_event_booking_limit_profile_save' );
add_action( 'edit_user_profile_update', 'soacot_event_booking_limit_profile_save' );
