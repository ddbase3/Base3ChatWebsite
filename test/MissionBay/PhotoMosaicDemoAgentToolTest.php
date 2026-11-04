<?php declare(strict_types=1);

namespace Base3ChatWebsite\Test\MissionBay;

use AssistantFoundation\Api\IAgentContext;
use Base3ChatWebsite\MissionBay\PhotoMosaicDemoAgentTool;
use PHPUnit\Framework\TestCase;

final class PhotoMosaicDemoAgentToolTest extends TestCase {

	public function testTechnicalIdentityAndToolDefinition(): void {
		$tool = new PhotoMosaicDemoAgentTool('photo-mosaic-test');
		$this->assertSame('photomosaicdemoagenttool', PhotoMosaicDemoAgentTool::getName());
		$this->assertSame('photo-mosaic-test', $tool->id());
		$this->assertStringContainsString('Photo Mosaic demo', $tool->getDescription());
		$definitions = $tool->getToolDefinitions();
		$this->assertCount(1, $definitions);
		$this->assertSame('open_photo_mosaic_demo', $definitions[0]['function']['name']);
		$this->assertSame('demo', $definitions[0]['category']);
		$this->assertSame('boolean', $definitions[0]['function']['parameters']['properties']['open']['type']);
		$this->assertSame([], $tool->getDockDefinitions());
	}

	public function testUnknownFunctionIsRejectedBeforeReadingContext(): void {
		$context = $this->createMock(IAgentContext::class);
		$context->expects($this->never())->method('getVar');
		$this->expectException(\InvalidArgumentException::class);
		(new PhotoMosaicDemoAgentTool())->callTool('unknown', [], $context);
	}

	public function testMissingStreamReturnsFailureWithoutThrowing(): void {
		$this->assertSame([
			'ok' => false,
			'error' => 'Missing eventstream in context.'
		], (new PhotoMosaicDemoAgentTool())->callTool('open_photo_mosaic_demo', [], $this->context(null)));
	}

	public function testDefaultArgumentsOpenAndRenderSanitizedIframeUrl(): void {
		$stream = new PhotoMosaicEventStreamSpy();
		$result = (new PhotoMosaicDemoAgentTool())->callTool('open_photo_mosaic_demo', [], $this->context($stream));
		$this->assertSame('main', $result['canvas_id']);
		$this->assertTrue($result['ok']);
		$this->assertSame('https://mosaic-creator.de/mosaicgallery.php?embed', $result['url']);
		$this->assertCount(2, $stream->events);
		$this->assertSame('canvas.open', $stream->events[0]['name']);
		$this->assertSame(['id' => 'main', 'title' => 'Photo Mosaic Demo', 'focus' => true], $stream->events[0]['payload']);
		$this->assertSame('canvas.render', $stream->events[1]['name']);
		$render = $stream->events[1]['payload'];
		$this->assertSame('replace', $render['mode']);
		$this->assertSame('html', $render['blocks'][0]['type']);
		$this->assertFalse($render['blocks'][0]['sanitize']);
		$this->assertStringContainsString('mosaicgallery.php?embed', $render['blocks'][0]['html']);
	}

	public function testCustomIdentifiersAndTitlesAreTrimmedAndEscaped(): void {
		$stream = new PhotoMosaicEventStreamSpy();
		$result = (new PhotoMosaicDemoAgentTool())->callTool('open_photo_mosaic_demo', [
			'canvas_id' => '  sidebar  ',
			'title' => '  <Title> "quoted" & more  ',
			'open' => 'yes'
		], $this->context($stream));
		$this->assertSame('sidebar', $result['canvas_id']);
		$this->assertSame('<Title> "quoted" & more', $stream->events[0]['payload']['title']);
		$html = $stream->events[1]['payload']['blocks'][0]['html'];
		$this->assertStringContainsString('&lt;Title&gt; &quot;quoted&quot; &amp; more', $html);
		$this->assertStringNotContainsString('title="<Title>', $html);
	}

	public function testEmptyArgumentsUseDefaultsAndFalseSkipsOpenEvent(): void {
		$stream = new PhotoMosaicEventStreamSpy();
		$result = (new PhotoMosaicDemoAgentTool())->callTool('open_photo_mosaic_demo', [
			'canvas_id' => '   ', 'title' => '   ', 'open' => 'false'
	], $this->context($stream));
		$this->assertTrue($result['ok']);
		$this->assertSame('main', $result['canvas_id']);
		$this->assertCount(1, $stream->events);
		$this->assertSame('canvas.render', $stream->events[0]['name']);
		$this->assertSame('Photo Mosaic Demo', $stream->events[0]['payload']['title']);
	}

	public function testInvalidBooleanDefaultsToOpeningCanvas(): void {
		$stream = new PhotoMosaicEventStreamSpy();
		(new PhotoMosaicDemoAgentTool())->callTool('open_photo_mosaic_demo', ['open' => 'maybe'], $this->context($stream));
		$this->assertSame('canvas.open', $stream->events[0]['name']);
	}

	public function testDisconnectedStreamDoesNotEmitEvents(): void {
		$stream = new PhotoMosaicEventStreamSpy();
		$stream->disconnected = true;
		$result = (new PhotoMosaicDemoAgentTool())->callTool('open_photo_mosaic_demo', [], $this->context($stream));
		$this->assertTrue($result['ok']);
		$this->assertSame([], $stream->events);
	}

	public function testDisconnectAfterOpeningSkipsRender(): void {
		$stream = new PhotoMosaicEventStreamSpy();
		$stream->disconnectAfter = 1;
		$result = (new PhotoMosaicDemoAgentTool())->callTool('open_photo_mosaic_demo', [], $this->context($stream));
		$this->assertTrue($result['ok']);
		$this->assertSame(['canvas.open'], array_column($stream->events, 'name'));
	}

	public function testEventFailuresReturnStructuredError(): void {
		$stream = new PhotoMosaicEventStreamSpy();
		$stream->failure = 'transport failed';
		$result = (new PhotoMosaicDemoAgentTool())->callTool('open_photo_mosaic_demo', [], $this->context($stream));
		$this->assertSame(['ok' => false, 'error' => 'Failed to push canvas events: transport failed'], $result);
		$this->assertSame([], $stream->events);
	}

	private function context(?PhotoMosaicEventStreamSpy $stream): IAgentContext {
		$context = $this->createStub(IAgentContext::class);
		$context->method('getVar')->willReturnCallback(static fn(string $key): mixed => $key === 'eventstream' ? $stream : null);
		return $context;
	}
}
