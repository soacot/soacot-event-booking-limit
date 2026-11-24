# SOACOT Event Booking Limit

A WordPress plugin that limits the number of [Events Manager](https://wordpress.org/plugins/events-manager/) events that a non-member can book. A member is a user with an active [Paid Memberships Pro](https://wordpress.org/plugins/paid-memberships-pro/) membership level.

## Requirements

- WordPress 6.5 or later
- PHP 7.4 or later
- Events Manager
- Paid Memberships Pro

## What it does

- A user with no active membership level can book a maximum of two different events in total.
- Pending, approved, and waitlist bookings count. Rejected, cancelled, and expired waitlist bookings do not count.
- Past events count.
- When a non-member at the limit opens a booking form, a message shows above the form.
- When a non-member at the limit sends a booking, Events Manager refuses the booking and shows the message.
- Members have no limit.

## Overrides

### Limit for one person

1. In wp-admin, go to **Users** and edit the user.
2. Find **Event booking limit**.
3. Enter a number in **Events this person can book**:
   - Empty: the default limit applies.
   - `0`: no limit.
   - `1` or more: the limit for this person.
4. Select **Update User**.

Only a user with the `edit_users` capability can see and change this field.

### Manual booking by staff

A manual booking in wp-admin (Events Manager Pro) with the override option selected ignores the limit.

## Limitation

The limit applies to each user account. Events Manager makes an account from the email address of a guest. A person who books with a different email address gets a new account and a new limit.

## Filters

| Filter | Purpose |
| --- | --- |
| `soacot_event_booking_limit_default` | Changes the default limit (2). `0` means no limit. |
| `soacot_event_booking_limit_message` | Changes the message. Parameters: message HTML, limit, user ID. Only `<a href>` tags stay in the output. |

Example:

```php
add_filter(
	'soacot_event_booking_limit_default',
	function () {
		return 3;
	}
);
```

The message links to `/join/` and `/contact/` on the site. Use the message filter if your site has different pages.

## Data

The plugin keeps one user meta key, `soacot_event_booking_limit`, for each user with an override. The plugin removes this data when you delete the plugin in wp-admin.

## Licence

GPL-3.0-or-later. See [LICENSE](LICENSE).
