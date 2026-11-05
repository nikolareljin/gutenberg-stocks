<?php
/**
 * Shared HTTP mock + fixture loader for PHPUnit.
 */

namespace Nikolareljin\NrStocks\Tests;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves canned bodies for wp_remote_get via pre_http_request.
 */
trait Mocks_Http {

	/**
	 * Queued bodies.
	 *
	 * @var string[]
	 */
	protected $mock_bodies = array();

	/**
	 * HTTP status for mocked responses.
	 *
	 * @var int
	 */
	protected $mock_code = 200;

	/**
	 * Requested URLs, in order.
	 *
	 * @var string[]
	 */
	protected $mock_calls = array();

	/**
	 * Removes the mock filter after each test.
	 */
	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'serve_mock' ) );
		$this->mock_bodies = array();
		$this->mock_calls  = array();
		$this->mock_code   = 200;
		parent::tear_down();
	}

	/**
	 * Loads a fixture file.
	 *
	 * @param string $name File name under tests/phpunit/fixtures.
	 * @return string
	 */
	public static function fixture( $name ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test-only fixture loader.
		return (string) file_get_contents( __DIR__ . '/fixtures/' . $name );
	}

	/**
	 * Queues one canned response body.
	 *
	 * @param string $body Response body.
	 * @param int    $code HTTP status.
	 */
	public function mock_http( $body, $code = 200 ) {
		if ( empty( $this->mock_bodies ) && empty( $this->mock_calls ) ) {
			add_filter( 'pre_http_request', array( $this, 'serve_mock' ), 10, 3 );
		}
		$this->mock_bodies[] = $body;
		$this->mock_code     = $code;
	}

	/**
	 * Serves the next queued body.
	 *
	 * @param mixed  $response Short-circuit value.
	 * @param array  $args     Request args.
	 * @param string $url      Request URL.
	 * @return array
	 */
	public function serve_mock( $response, $args, $url ) {
		$this->mock_calls[] = $url;
		$body               = array_shift( $this->mock_bodies );
		if ( null === $body ) {
			$body = '';
		}
		return array(
			'headers'  => array(),
			'body'     => $body,
			'response' => array(
				'code'    => $this->mock_code,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => '',
		);
	}
}
