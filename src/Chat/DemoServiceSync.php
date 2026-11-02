<?php declare(strict_types=1);

namespace Base3ChatWebsite\Chat;

use Base3\Api\IOutput;
use Base3\Api\IRequest;
use Base3\Configuration\Api\IConfiguration;

class DemoServiceSync implements IOutput {

	private IRequest $request;
	private IConfiguration $configuration;

	public function __construct(
		IRequest $request,
		IConfiguration $configuration
	) {
		$this->request = $request;
		$this->configuration = $configuration;
	}

	public static function getName(): string {
		return 'demoservicesync';
	}

	public function getOutput(string $out = 'html', bool $final = false): string {

		// base prompt
		if ($this->request->get('baseprompt') !== null) {
			return 'Testing...';
		}

		// Chat message (REST)
		if ($this->request->request('prompt') !== null) {
			return $this->getAgentResponseSync();
		}

		// Feedback (ignored in demo)
		if ($this->request->post('feedback') !== null) {
			return json_encode([
				"type" => "feedback_ack",
				"status" => "ignored",
				"meta" => [
					"timestamp" => gmdate('c')
				]
			], JSON_UNESCAPED_UNICODE);
		}

		return '';
	}

	public function getHelp(): string {
		return 'Help of DemoServiceSync.';
	}

	/**
	 * Non-streaming chat completion endpoint
	 * Returns full text response wrapped in a JSON object with id and meta.
	 */
	private function getAgentResponseSync(): string {

		// Load OpenAI configuration
		$conf = $this->configuration->get('openai');
		$endpoint = rtrim($conf['endpoint'], '/');
		$apiKey = $conf['apikey'];

		$userPrompt = $this->request->request('prompt');

		// Prepare OpenAI API payload
		$payload = [
			"model" => "gpt-4o-mini",
			"stream" => false,
			"messages" => [
				[
					"role"    => "user",
					"content" => $userPrompt
				]
			]
		];

		// cURL request (synchronous)
		$ch = curl_init();
		curl_setopt_array($ch, [
			CURLOPT_URL            => $endpoint . "/chat/completions",
			CURLOPT_POST           => true,
			CURLOPT_HTTPHEADER     => [
				"Content-Type: application/json",
				"Authorization: Bearer " . $apiKey
			],
			CURLOPT_POSTFIELDS     => json_encode($payload),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 30
		]);

		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error    = curl_error($ch);
		curl_close($ch);

		// cURL or HTTP error
		if ($response === false || $httpCode >= 400) {
			return json_encode([
				"id"   => uniqid('msg_', true),
				"type" => "chat_response",
				"text" => "API error occurred.",
				"meta" => [
					"blocked"   => false,
					"error"     => $error ?: ("HTTP " . $httpCode),
					"timestamp" => gmdate('c'),
					"nodeid"    => "assistent"
				]
			], JSON_UNESCAPED_UNICODE);
		}

		// Parse JSON
		$json = json_decode($response, true);

		if (!$json || !isset($json['choices'][0]['message']['content'])) {
			return json_encode([
				"id"   => uniqid('msg_', true),
				"type" => "chat_response",
				"text" => "Invalid response from OpenAI.",
				"meta" => [
					"blocked"   => false,
					"error"     => "Invalid API response",
					"timestamp" => gmdate('c'),
					"nodeid"    => "assistent"
				]
			], JSON_UNESCAPED_UNICODE);
		}

		// Extract assistant text
		$text = $json['choices'][0]['message']['content'];

		// Build unified response object
		$result = [
			"id"   => uniqid('msg_', true),
			"type" => "chat_response",
			"text" => $text,
			"meta" => [
				"blocked"   => false,
				"error"     => null,
				"timestamp" => gmdate('c'),
				"nodeid"    => "assistent"
			]
		];

		return json_encode($result, JSON_UNESCAPED_UNICODE);
	}
}
