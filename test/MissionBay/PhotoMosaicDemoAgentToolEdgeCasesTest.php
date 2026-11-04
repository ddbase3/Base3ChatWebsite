<?php declare(strict_types=1);

namespace Base3ChatWebsite\Test\MissionBay;

use AssistantFoundation\Api\IAgentContext;
use Base3ChatWebsite\MissionBay\PhotoMosaicDemoAgentTool;
use PHPUnit\Framework\TestCase;

final class PhotoMosaicDemoAgentToolEdgeCasesTest extends TestCase {

	public function testBooleanLikeValuesDetermineWhetherCanvasOpeningIsEmitted(): void {
		$tool = new PhotoMosaicDemoAgentTool();
		foreach ([false, 0, '0', 'off', 'no', 'false', 'FALSE'] as $value) {
			$stream = new PhotoMosaicEventStreamSpy();
			$result = $tool->callTool('open_photo_mosaic_demo', ['open' => $value], $this->context($stream));
			$this->assertTrue($result['ok']);
			$this->assertSame(['canvas.render'], array_column($stream->events, 'name'));
		}
		foreach ([true, 1, '1', 'on', 'yes', 'TRUE', null] as $value) {
			$stream = new PhotoMosaicEventStreamSpy();
			$result = $tool->callTool('open_photo_mosaic_demo', ['open' => $value], $this->context($stream));
			$this->assertTrue($result['ok']);
			$this->assertSame(['canvas.open', 'canvas.render'], array_column($stream->events, 'name'));
		}
	}

	public function testFalsyContextStreamsReturnMissingStreamError(): void {
		foreach ([null, false, '', 0, []] as $stream) {
			$result = (new PhotoMosaicDemoAgentTool())->callTool('open_photo_mosaic_demo', [], $this->context($stream));
			$this->assertSame(['ok' => false, 'error' => 'Missing eventstream in context.'], $result);
		}
	}

	public function testInvalidNonEmptyStreamReturnsControlledFailure(): void {
		$result = (new PhotoMosaicDemoAgentTool())->callTool('open_photo_mosaic_demo', [], $this->context('unusable-stream'));
		$this->assertFalse($result['ok']);
		$this->assertStringContainsString('Failed to push canvas events:', $result['error']);
		$this->assertStringContainsString('isDisconnected', $result['error']);
	}

	public function testFailureDuringSecondEventIsNotReportedAsSuccess(): void {
		$stream = new PhotoMosaicSecondPushFailureSpy();
		$result = (new PhotoMosaicDemoAgentTool())->callTool('open_photo_mosaic_demo', [], $this->context($stream));
		$this->assertSame(['ok' => false, 'error' => 'Failed to push canvas events: render not delivered'], $result);
		$this->assertSame(['canvas.open'], array_column($stream->events, 'name'));
	}

	public function testHtmlAttributesAreEscapedWithoutChangingEventMetadata(): void {
		$stream = new PhotoMosaicEventStreamSpy();
		$result = (new PhotoMosaicDemoAgentTool())->callTool('open_photo_mosaic_demo', [
			'canvas_id' => ' secondary ',
			'title' => '  <img src=x onerror="alert(1)"> O\'Brian & Co  '
		], $this->context($stream));
		$this->assertSame('secondary', $result['canvas_id']);
		$this->assertSame('<img src=x onerror="alert(1)"> O\'Brian & Co', $stream->events[0]['payload']['title']);
		$html = $stream->events[1]['payload']['blocks'][0]['html'];
		$this->assertStringContainsString('&lt;img src=x onerror=&quot;alert(1)&quot;&gt; O&#039;Brian &amp; Co', $html);
		$this->assertStringNotContainsString('<img', $html);
		$this->assertStringContainsString('referrerpolicy="no-referrer-when-downgrade"', $html);
	}

	private function context(mixed $stream): IAgentContext {
		$context = $this->createStub(IAgentContext::class);
		$context->method('getVar')->willReturnCallback(static fn(string $key): mixed => $key === 'eventstream' ? $stream : null);
		return $context;
	}
}
