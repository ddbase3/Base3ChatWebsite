<?php declare(strict_types=1);

namespace Base3ChatWebsite\Test\Chat;

final class CurlTransportState {

	public static string|false $response = '';
	public static int $httpCode = 200;
	public static string $error = '';
	public static array $options = [];
	public static int $opened = 0;
	public static int $executed = 0;
	public static int $closed = 0;

	public static function reset(): void {
		self::$response = '';
		self::$httpCode = 200;
		self::$error = '';
		self::$options = [];
		self::$opened = 0;
		self::$executed = 0;
		self::$closed = 0;
	}
}
