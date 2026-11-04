<?php declare(strict_types=1);

namespace Base3ChatWebsite\Test\MissionBay;

final class PhotoMosaicEventStreamSpy {

	public array $events = [];
	public bool $disconnected = false;
	public ?string $failure = null;
	public ?int $disconnectAfter = null;

	public function push(string $name, array $payload): void {
		if ($this->failure !== null) {
			throw new \RuntimeException($this->failure);
		}
		$this->events[] = ['name' => $name, 'payload' => $payload];
		if ($this->disconnectAfter !== null && count($this->events) >= $this->disconnectAfter) {
			$this->disconnected = true;
		}
	}

	public function isDisconnected(): bool {
		return $this->disconnected;
	}
}
