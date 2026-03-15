<?php
/**
 * Phone normalizer service.
 *
 * @package TMASD\Signals\Dispatch\Services
 */

declare(strict_types=1);

namespace TMASD\Signals\Dispatch\Services;

use TMASD\Signals\Dispatch\Contracts\PhoneNormalizerInterface;
use TMASD\Signals\Dispatch\Core\AbstractService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Phone normalizer service.
 *
 * Normalizes phone numbers to E.164 international format.
 * Uses encapsulation to hide normalization logic.
 *
 * @final
 */
final class PhoneNormalizerService extends AbstractService implements PhoneNormalizerInterface {

	/**
	 * Minimum digits for a valid phone number.
	 *
	 * @var int
	 */
	private const MIN_DIGITS = 7;

	/**
	 * Maximum digits for a valid phone number.
	 *
	 * @var int
	 */
	private const MAX_DIGITS = 15;

	/**
	 * Normalize phone number to E.164 format.
	 *
	 * @param string $phone Raw phone number input.
	 * @return string Normalized phone number or empty string if invalid.
	 */
	public function normalize( string $phone ): string {
		$phone = $this->sanitize_input( $phone );

		if ( '' === $phone ) {
			return '';
		}

		$has_plus = $this->has_plus_prefix( $phone );
		$digits   = $this->extract_digits( $phone );

		if ( ! $this->validate_digit_count( $digits ) ) {
			return '';
		}

		return $this->format_e164( $digits, $has_plus );
	}

	/**
	 * Validate if a phone number can be normalized.
	 *
	 * @param string $phone Raw phone number input.
	 * @return bool True if phone is valid.
	 */
	public function is_valid( string $phone ): bool {
		return '' !== $this->normalize( $phone );
	}

	/**
	 * Sanitize the input phone string.
	 *
	 * @param string $phone Raw phone input.
	 * @return string Trimmed and sanitized phone.
	 */
	private function sanitize_input( string $phone ): string {
		return trim( $phone );
	}

	/**
	 * Check if phone starts with plus sign.
	 *
	 * @param string $phone Phone string.
	 * @return bool True if has plus prefix.
	 */
	private function has_plus_prefix( string $phone ): bool {
		return '' !== $phone && '+' === $phone[0];
	}

	/**
	 * Extract only digits from phone string.
	 *
	 * @param string $phone Phone string.
	 * @return string Digits only.
	 */
	private function extract_digits( string $phone ): string {
		$digits = preg_replace( '/\D+/', '', $phone );
		return is_string( $digits ) ? $digits : '';
	}

	/**
	 * Validate digit count is within acceptable range.
	 *
	 * @param string $digits Digits string.
	 * @return bool True if valid count.
	 */
	private function validate_digit_count( string $digits ): bool {
		$length = strlen( $digits );
		return $length >= self::MIN_DIGITS && $length <= self::MAX_DIGITS;
	}

	/**
	 * Format digits to E.164 format.
	 *
	 * @param string $digits  Phone digits.
	 * @param bool   $had_plus Original had plus prefix.
	 * @return string E.164 formatted phone.
	 */
	private function format_e164( string $digits, bool $had_plus ): string {
		// Remove leading 00 for international format.
		if ( ! $had_plus && substr( $digits, 0, 2 ) === '00' ) {
			$digits = substr( $digits, 2 );
		}

		return '+' . $digits;
	}
}
