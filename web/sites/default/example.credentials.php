<?php

/**
 * @file
 * Example credentials file for Duccinis V4.
 *
 * This file documents the $config overrides that must be set in
 * web/sites/default/settings.php (which is gitignored and never committed).
 *
 * Copy the relevant sections into your local settings.php and fill in real
 * values. Never commit actual API keys or secrets to version control.
 *
 * ⚠️  SECURITY NOTICE — V3 Stripe key exposure (March 2026)
 * =========================================================
 * The V3 Stripe test keys (prefixed sk_test_51Sfs1Z… / pk_test_51Sfs1Z…,
 * Stripe Connect account acct_1Sfs1Z9RN1sG2AMq) were committed in the V3
 * git history and must be considered compromised.
 *
 * Required actions:
 *   1. Log in to https://dashboard.stripe.com/test/apikeys
 *   2. Revoke / roll the exposed keys (sk_test_51Sfs1Z… / pk_test_51Sfs1Z…).
 *   3. Issue new test keys and place them below in your local settings.php.
 *   4. Do NOT reuse any key with the prefix sk_test_51Sfs1Z… or pk_test_51Sfs1Z….
 *
 * For V4 use only the NEW keys issued after the V3 keys were revoked.
 */

// ---------------------------------------------------------------------------
// Stripe payment gateway — $config overrides.
// ---------------------------------------------------------------------------
// Populate these in settings.php. The corresponding YAML config
// (commerce_payment.commerce_payment_gateway.stripe.yml) must have empty
// strings ('') for all key fields so no credentials are ever committed.
//
// $config['commerce_payment.commerce_payment_gateway.stripe']['configuration']['secret_key'] = 'sk_test_YOUR_NEW_KEY_HERE';
// $config['commerce_payment.commerce_payment_gateway.stripe']['configuration']['publishable_key'] = 'pk_test_YOUR_NEW_KEY_HERE';
// $config['commerce_payment.commerce_payment_gateway.stripe']['configuration']['webhook_signing_secret'] = 'whsec_YOUR_WEBHOOK_SECRET_HERE';
//
// For Stripe Connect (if used):
// $config['commerce_payment.commerce_payment_gateway.stripe']['configuration']['access_token'] = 'sk_test_YOUR_ACCESS_TOKEN_HERE';
// $config['commerce_payment.commerce_payment_gateway.stripe']['configuration']['stripe_user_id'] = 'acct_YOUR_ACCOUNT_ID_HERE';
