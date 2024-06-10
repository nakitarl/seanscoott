<?php
/**
 * Paypal Logger Class.
 *
 * @package checkout-paypal-woo
 * @since 1.0.0
 */

namespace CPPW\Inc;

use CPPW\Inc\Helper;

/**
 * Paypal Logger Class.
 *
 * @since 1.0.0
 */
class Logger {

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function __construct() {
	}

	/**
	 * Log the provided message in the WC logs directory.
	 *
	 * @param string $level One of the following:
	 *     'emergency': System is unusable.
	 *     'alert': Action must be taken immediately.
	 *     'critical': Critical conditions.
	 *     'error': Error conditions.
	 *     'warning': Warning conditions.
	 *     'notice': Normal but significant condition.
	 *     'info': Informational messages.
	 *     'debug': Debug-level messages.
	 * @param string $message Error log information.
	 * @param bool   $separator End separator required or not.
	 * @since 1.0.0
	 * @return void
	 */
	public static function log( $level, $message, $separator = false ) {
		if ( 'yes' === Helper::get_setting( 'cppw_debug_log' ) ) {
			$log = wc_get_logger();
			if ( $separator ) {
				$message .= PHP_EOL . '----';
			}
			$log->log( $level, $message, [ 'source' => 'cppw-paypal' ] );
		}
	}

	/**
	 * Adds an emergency level message.
	 *
	 * @param string $message Message to log.
	 * @param bool   $separator End separator required or not.
	 * @since 1.0.0
	 * @return void
	 */
	public static function emergency( $message, $separator = false ) {
		self::log( 'emergency', $message, $separator );
	}

	/**
	 * Adds an alert level message.
	 *
	 * Action must be taken immediately.
	 * Example: Entire website down, database unavailable, etc.
	 *
	 * @param string $message Message to log.
	 * @param bool   $separator End separator required or not.
	 * @since 1.0.0
	 * @return void
	 */
	public static function alert( $message, $separator = false ) {
		self::log( 'alert', $message, $separator );
	}

	/**
	 * Adds a critical level message.
	 *
	 * Critical conditions.
	 * Example: Application component unavailable, unexpected exception.
	 *
	 * @param string $message Message to log.
	 * @param bool   $separator End separator required or not.
	 * @since 1.0.0
	 * @return void
	 */
	public static function critical( $message, $separator = false ) {
		self::log( 'critical', $message, $separator );
	}

	/**
	 * Adds an error level message.
	 *
	 * Runtime errors that do not require immediate action but should typically be logged
	 * and monitored.
	 *
	 * @param string $message Message to log.
	 * @param bool   $separator End separator required or not.
	 * @since 1.0.0
	 * @return void
	 */
	public static function error( $message, $separator = false ) {
		self::log( 'error', $message, $separator );
	}

	/**
	 * Adds a warning level message.
	 *
	 * Exceptional occurrences that are not errors.
	 *
	 * Example: Use of deprecated APIs, poor use of an API, undesirable things that are not
	 * necessarily wrong.
	 *
	 * @param string $message Message to log.
	 * @param bool   $separator End separator required or not.
	 * @since 1.0.0
	 * @return void
	 */
	public static function warning( $message, $separator = false ) {
		self::log( 'warning', $message, $separator );
	}

	/**
	 * Adds a notice level message.
	 *
	 * Normal but significant events.
	 *
	 * @param string $message Message to log.
	 * @param bool   $separator End separator required or not.
	 * @since 1.0.0
	 * @return void
	 */
	public static function notice( $message, $separator = false ) {
		self::log( 'notice', $message, $separator );
	}

	/**
	 * Adds a info level message.
	 *
	 * Interesting events.
	 * Example: User logs in, SQL logs.
	 *
	 * @param string $message Message to log.
	 * @param bool   $separator End separator required or not.
	 * @since 1.0.0
	 * @return void
	 */
	public static function info( $message, $separator = false ) {
		self::log( 'info', $message, $separator );
	}

	/**
	 * Adds a debug level message.
	 *
	 * Detailed debug information.
	 *
	 * @param string $message Message to log.
	 * @param bool   $separator End separator required or not.
	 * @since 1.0.0
	 * @return void
	 */
	public static function debug( $message, $separator = false ) {
		self::log( 'debug', $message, $separator );
	}
}
