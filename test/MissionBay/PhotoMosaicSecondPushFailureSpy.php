<?php declare(strict_types=1);

namespace Base3ChatWebsite\Test\MissionBay;

final class PhotoMosaicSecondPushFailureSpy {

	public array $events = [];

	public function isDisconnected(): bool {
		return false;
	}

	public function push(string $name, array $payload): void {
		if (count($this->events) === 1) {
			throw new \RuntimeException('render not delivered');
		}
		$this->events[] = ['name' => $name, 'payload' => $payload];
	}
}
