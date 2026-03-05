# Changelog

All notable changes to `cashier-polar` will be documented in this file.

## v0.0.4 - 2026-03-05

Breaking Changes

This release updates the package to match the current Polar API. If you're upgrading, you'll need to re-run migrations (or create a migration
to alter existing tables).

- checkout() now takes a product ID instead of a price ID. Polar's checkout endpoint now uses a products array. Update your calls from
  $user->checkout('price_xxx') to $user->checkout('product_xxx').
- change() now takes a product ID. Update from $subscription->change('price_xxx') to $subscription->change('product_xxx'). An optional second
  parameter accepts a specific price ID.
- REVOKED status removed from SubscriptionStatus enum. The Polar API no longer includes revoked as a subscription status. Revoked
  subscriptions are now stored as canceled.
- Trial fields renamed. trial_ends_at is now trial_start and trial_end on the subscriptions table, matching the API. The onTrial() method now
  checks trial_end. If you referenced $subscription->trial_ends_at directly, update to $subscription->trial_end.

New Features

- Trial support. Subscriptions now store trial_start and trial_end from the Polar API. Use $subscription->onTrial(),
  $subscription->trialEnd(), $subscription->trialEndDate(), and the on_trial appended attribute.
- Cancellation details. canceled_at, customer_cancellation_reason, and customer_cancellation_comment are now stored and populated from
  webhooks.
- Subscription-level pricing. amount, currency, recurring_interval, and recurring_interval_count are now stored directly on the subscription
  (no longer only on items).
- New fields stored. product_id, discount_id, checkout_id, ended_at, and custom_field_data on subscriptions.
- hasProduct() method on PolarSubscription to check the subscribed product.
- getSubscription() method on the API client to fetch a subscription by ID.
- Prices array support. Webhook handler now correctly reads from the prices array instead of the removed price_id/price fields. Subscription
  items are synced and stale items are cleaned up automatically.

Fixed

- Checkout endpoint updated from checkouts/custom/ to checkouts/ matching current API.
- Checkout retrieval endpoint updated from checkouts/custom/{id} to checkouts/{id}.
- Subscription update API now sends product_id instead of deprecated product_price_id.
- Canceled webhook handler now properly sets status to canceled (previously only updated ends_at).
- ended_at is now populated by webhooks (column existed but was never written to).
- recurring() now checks the subscription's own recurring_interval instead of relying on items.
- ended() now uses the ended_at timestamp from the API.

## v0.3.5 - 2025-03-13

Laravel 12

## v0.3.4 - 2025-01-30

add subscription status checks and refine active method

## v0.3.1 - 2025-01-30

- return api responses from change, resume, cancel

## v0.3.0 - 2025-01-27

- Webhook improvements
- Cancel / Resume / Revoke subscription

## v0.2.4 - 2024-12-27

- fix billable and canceled status

## v0.2.3 - 2024-12-23

- update webhook signature validator

## v0.2.2 - 2024-12-23

- fix cancellation methods / grace period

## v0.2.1 - 2024-12-23

- add products endpoint
- few other tweaks

## v0.2.0 - 2024-12-22

- few bug fixes
- logging and database structure improvements
- add orders / invoicing
- update readme

## v0.1.0  - 2024-12-21

Initial release of Laravel Cashier for Polar.sh integration.

### Features

- Basic subscription management
- Checkout session creation
- Webhook handling for subscription events
- Billable trait for Laravel models
- Configuration publishing
- Webhook secret generation command

### Notes

- This is currently in active development
- Testing and feedback welcome!
