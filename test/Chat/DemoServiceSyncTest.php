<?php declare(strict_types=1);

namespace Base3ChatWebsite\Test\Chat;

use Base3\Api\IRequest;
use Base3\Configuration\Api\IConfiguration;
use Base3ChatWebsite\Chat\DemoServiceSync;
use PHPUnit\Framework\TestCase;

final class DemoServiceSyncTest extends TestCase {

	public function testTechnicalNameAndHelp(): void {
		$this->assertSame('demoservicesync', DemoServiceSync::getName());
		$this->assertSame('Help of DemoServiceSync.', $this->service([])->getHelp());
	}

	public function testEmptyRequestReturnsEmptyResponse(): void {
		$this->assertSame('', $this->service([])->getOutput('json'));
	}

	public function testBasePromptReturnsDemoTextBeforeAnyNetworkRequest(): void {
		$this->assertSame('Testing...', $this->service(['get' => ['baseprompt' => ''], 'request' => ['prompt' => 'not sent']])->getOutput());
		$this->assertSame('Testing...', $this->service(['get' => ['baseprompt' => false]])->getOutput('html', true));
	}

	public function testFeedbackResponseHasExpectedShapeAndTimestamp(): void {
		$decoded = json_decode($this->service(['post' => ['feedback' => 'positive']])->getOutput('json'), true, 512, JSON_THROW_ON_ERROR);
		$this->assertSame('feedback_ack', $decoded['type']);
		$this->assertSame('ignored', $decoded['status']);
		$this->assertArrayHasKey('timestamp', $decoded['meta']);
		$this->assertNotFalse(strtotime($decoded['meta']['timestamp']));
	}

	public function testEmptyFeedbackValueStillAcknowledges(): void {
		$decoded = json_decode($this->service(['post' => ['feedback' => '']])->getOutput(), true, 512, JSON_THROW_ON_ERROR);
		$this->assertSame('ignored', $decoded['status']);
	}

	private function service(array $values): DemoServiceSync {
		$request = $this->createStub(IRequest::class);
		$request->method('get')->willReturnCallback(static fn(string $key): mixed => $values['get'][$key] ?? null);
		$request->method('request')->willReturnCallback(static fn(string $key): mixed => $values['request'][$key] ?? null);
		$request->method('post')->willReturnCallback(static fn(string $key): mixed => $values['post'][$key] ?? null);
		return new DemoServiceSync($request, $this->createStub(IConfiguration::class));
	}
}
