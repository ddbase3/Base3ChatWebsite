<?php declare(strict_types=1);

namespace Base3ChatWebsite\Chat;

use Base3ChatWebsite\Test\Chat\CurlTransportState;

// These test-only namespaced functions are loaded exclusively in isolated PHPUnit processes.
const CURLOPT_URL = 1;
const CURLOPT_POST = 2;
const CURLOPT_HTTPHEADER = 3;
const CURLOPT_POSTFIELDS = 4;
const CURLOPT_RETURNTRANSFER = 5;
const CURLOPT_TIMEOUT = 6;
const CURLINFO_HTTP_CODE = 7;

function curl_init(): \stdClass {
	CurlTransportState::$opened++;
	return new \stdClass();
}

function curl_setopt_array(\stdClass $handle, array $options): bool {
	CurlTransportState::$options = $options;
	return true;
}

function curl_exec(\stdClass $handle): string|false {
	CurlTransportState::$executed++;
	return CurlTransportState::$response;
}

function curl_getinfo(\stdClass $handle, int $option): int {
	return CurlTransportState::$httpCode;
}

function curl_error(\stdClass $handle): string {
	return CurlTransportState::$error;
}

function curl_close(\stdClass $handle): void {
	CurlTransportState::$closed++;
}
