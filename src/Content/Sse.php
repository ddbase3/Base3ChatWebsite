<?php declare(strict_types=1);

namespace Base3ChatWebsite\Content;

use Base3\Api\IOutput;
use EventTransport\Api\IEventStreamFactory;

class Sse implements IOutput {

	public function __construct(
		private readonly IEventStreamFactory $eventstreamfactory
	) {}

	public static function getName(): string {
		return 'sse';
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		// Create SSE stream
		$stream = $this->eventstreamfactory->createStream(
			'ssestream-example',
			'sse-test'
		);

		// Start output early
		$stream->start();

		// Send 10 ticks
		for ($i = 1; $i <= 10; $i++) {

			$stream->push('tick', [
				'index' => $i,
				'time'  => microtime(true)
			]);

			if ($stream->isDisconnected()) {
				break;
			}

			sleep(1);
		}

		// Final event
		$stream->finish([
			'status' => 'complete'
		]);

		// IMPORTANT:
		// Return nothing else, SSE is already streamed directly.
		return '';
	}

	public function getHelp(): string {
		return "Help of Sse\n";
	}
}

