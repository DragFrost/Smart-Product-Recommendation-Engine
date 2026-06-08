<?php
/**
 * SPRE GDPR User Tracker.
 *
 * @package SPRE\Tracking
 */

declare(strict_types=1);

namespace SPRE\Tracking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UserTracker
 *
 * Configures tracking cookies and maintains guest identities securely.
 */
class UserTracker {

	/**
	 * Cookie name.
	 *
	 * @var string
	 */
	private string $cookie_name = 'spre_session_id';

	/**
	 * Retrieve the SHA-256 hashed tracking token for the current visitor.
	 *
	 * If no cookie exists and consent is granted, registers a random UUID cookie.
	 * Returns a static fallback string if cookie consent is denied.
	 *
	 * @return string SHA-256 hash or fallback.
	 */
	public function get_session_hash(): string {
		if ( ! $this->is_tracking_allowed() ) {
			return 'gdpr_opt_out';
		}

		$cookie_value = '';

		if ( isset( $_COOKIE[ $this->cookie_name ] ) ) {
			$cookie_value = sanitize_text_field( wp_unslash( $_COOKIE[ $this->cookie_name ] ) );
		} else {
			// Generate secure random UUID for the visitor
			if ( function_exists( 'wp_generate_uuid4' ) ) {
				$cookie_value = wp_generate_uuid4();
				$this->set_tracking_cookie( $cookie_value );
			}
		}

		if ( empty( $cookie_value ) ) {
			return 'gdpr_opt_out';
		}

		return hash( 'sha256', $cookie_value );
	}

	/**
	 * Set the tracking cookie on the user's browser.
	 *
	 * @param string $uuid Random UUID identifier.
	 */
	private function set_tracking_cookie( string $uuid ): void {
		if ( headers_sent() ) {
			return;
		}

		$expiry = time() + ( 30 * DAY_IN_SECONDS ); // 30 days retention
		$secure = is_ssl();
		$httponly = true; // Protects cookie from custom JS reads (helps mitigate XSS)

		setcookie(
			$this->cookie_name,
			$uuid,
			[
				'expires'  => $expiry,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
				'secure'   => $secure,
				'httponly' => $httponly,
				'samesite' => 'Lax',
			]
		);

		$_COOKIE[ $this->cookie_name ] = $uuid;
	}

	/**
	 * Check if cookie tracking is permitted under GDPR consent controls.
	 *
	 * Respects popular third-party WordPress cookie consent frameworks.
	 *
	 * @return bool True if tracking is allowed.
	 */
	public function is_tracking_allowed(): bool {
		// Read general settings
		$settings = get_option( 'spre_settings', [] );
		$require_consent = isset( $settings['require_cookie_consent'] ) ? (bool) $settings['require_cookie_consent'] : false;

		if ( ! $require_consent ) {
			return true;
		}

		// 1. Complianz cookie plugin check
		if ( function_exists( 'cmplz_has_consent' ) && ! cmplz_has_consent( 'statistics' ) ) {
			return false;
		}

		// 2. CookieYes consent check
		if ( isset( $_COOKIE['cookieyes-consent'] ) ) {
			if ( strpos( $_COOKIE['cookieyes-consent'], 'analytics:no' ) !== false || strpos( $_COOKIE['cookieyes-consent'], 'statistics:no' ) !== false ) {
				return false;
			}
		}

		// 3. GDPR Cookie Consent plugin check
		if ( isset( $_COOKIE['viewed_cookie_policy'] ) && $_COOKIE['viewed_cookie_policy'] === 'yes' ) {
			if ( isset( $_COOKIE['cookielawinfo-checkbox-analytics'] ) && $_COOKIE['cookielawinfo-checkbox-analytics'] === 'no' ) {
				return false;
			}
		}

		return true;
	}
}
