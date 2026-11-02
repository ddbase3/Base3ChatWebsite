<?php declare(strict_types=1);

namespace Base3ChatWebsite\Chat;

use Base3\Api\IOutput;
use Base3\Api\IRequest;
use Base3\Configuration\Api\IConfiguration;
use EventTransport\Api\IEventStreamFactory;

class DemoService implements IOutput {

	private IRequest $request;
	private IConfiguration $configuration;
	private IEventStreamFactory $eventstreamfactory;

	public function __construct(
		IRequest $request,
		IConfiguration $configuration,
		IEventStreamFactory $eventstreamfactory
	) {
		$this->request = $request;
		$this->configuration = $configuration;
		$this->eventstreamfactory = $eventstreamfactory;
	}

	public static function getName(): string {
		return 'demoservice';
	}

	public function getOutput(string $out = 'html', bool $final = false): string {

		// baseprompt
		if ($this->request->get('baseprompt') !== null) {
			return 'Testing...';
		}

		// streaming chat message
		if ($this->request->request('prompt') !== null) {
			return $this->getAgentResponse();
		}

		// feedback endpoint (ignored in demo)
		if ($this->request->post('feedback') !== null) {
			return '';
		}

		return '';
	}

	public function getHelp(): string {
		return 'Help of DemoService.';
	}

	/**
	 * STREAMING 4o-mini Chat Completion
	 * → sendet zuerst msgid Event
	 * → dann token Events
	 * → dann done Event
	 */
	private function getAgentResponse() {

		// Load OpenAI configuration
		$conf = $this->configuration->get('openai');
		$endpoint = rtrim($conf['endpoint'], '/');
		$apiKey = $conf['apikey'];

		$userPrompt = $this->request->request('prompt');

		// Generate a unique assistant message ID (same schema as the REST service)
		$messageId = uniqid('msg_', true);

		// EventTransport stream erzeugen
		$stream = $this->eventstreamfactory->createStream(
			'demoservice',
			uniqid('chat-', true)
		);

		// Stream starten (Header senden)
		$stream->start();

		// ------------------------------------------------------
		// 1) Sofort Message-ID senden (Front-End erwartet das!)
		// ------------------------------------------------------
		$stream->push('msgid', [
			"id" => $messageId
		]);

		// ------------------------------------------------------
		// 2) Streaming des OpenAI-Modells (token für token)
		// ------------------------------------------------------

		$payload = [
			"model" => "gpt-4o-mini",
			"stream" => true,
			"messages" => [
				["role" => "user", "content" => $userPrompt]
			]
		];

		$ch = curl_init();
		curl_setopt_array($ch, [
			CURLOPT_URL            => $endpoint . "/chat/completions",
			CURLOPT_POST           => true,
			CURLOPT_HTTPHEADER     => [
				"Content-Type: application/json",
				"Authorization: Bearer " . $apiKey
			],
			CURLOPT_POSTFIELDS     => json_encode($payload),
			CURLOPT_RETURNTRANSFER => false, // wir verarbeiten live
			CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) use ($stream) {

				// Wenn der Client getrennt hat → abbrechen
				if ($stream->isDisconnected()) {
					return 0;
				}

				$lines = preg_split("/\r\n|\n|\r/", $chunk);

				foreach ($lines as $line) {
					$line = trim($line);
					if ($line === '' || !str_starts_with($line, 'data:')) {
						continue;
					}

					$content = trim(substr($line, 5)); // nach "data:"

					if ($content === '[DONE]') {
						$stream->push('done', ["status" => "done"]);
						return strlen($chunk);
					}

					$json = json_decode($content, true);
					if (!$json) {
						continue;
					}

					$delta = $json['choices'][0]['delta']['content'] ?? null;

					if ($delta !== null) {
						$stream->push('token', ["text" => $delta]);
					}
				}

				return strlen($chunk);
			},
			CURLOPT_TIMEOUT => 0
		]);

		curl_exec($ch);
		curl_close($ch);

		// falls done nie gesendet wurde
		if (!$stream->isDisconnected()) {
			$stream->push('done', ["status" => "complete"]);
		}

		return '';
	}
}
