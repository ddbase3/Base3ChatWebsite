<?php declare(strict_types=1);

namespace Base3ChatWebsite\Test;

use Base3\Microservice\Microservice\MicroserviceHelper;

final class MicroserviceHelperSpy extends MicroserviceHelper {

	public array $calls = [];

	public function __construct(private readonly array $services) {}

	public function get($servicename, $interface, $name, $retry = false) {
		$this->calls[] = [$servicename, $interface, $name, $retry];
		return $this->services[$name] ?? null;
	}
}
