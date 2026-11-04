<?php declare(strict_types=1);

namespace Base3ChatWebsite\Test;

use Base3ChatWebsite\Chat\DemoServiceAgent;
use Base3ChatWebsite\Chat\DemoServiceSync;
use Base3ChatWebsite\Content\DataHawkTest;
use Base3ChatWebsite\Content\Navigation;
use Base3ChatWebsite\Base3ChatWebsitePlugin;
use PHPUnit\Framework\TestCase;

final class ModuleIdentityTest extends TestCase {

	public function testManifestIdentifiesModuleAndRequiredDependencies(): void {
		$path = DIR_PLUGIN . 'Base3ChatWebsite/base3.json';
		$this->assertFileExists($path);
		$manifest = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
		$this->assertSame('Base3ChatWebsite', $manifest['name']);
		$this->assertSame('Base3ChatWebsite', $manifest['namespace']);
		$this->assertSame(1, $manifest['manifestVersion']);
		foreach (['Base3Framework', 'AssistantFoundation', 'ResourceFoundation', 'Chatbot', 'MissionBay', 'DataHawk'] as $dependency) {
			$this->assertArrayHasKey($dependency, $manifest['dependencies']);
		}
	}

	public function testRetiredStreamingDependenciesAndRoutesAreAbsent(): void {
		$root = DIR_PLUGIN . 'Base3ChatWebsite/';
		$manifest = json_decode(file_get_contents($root . 'base3.json'), true, 512, JSON_THROW_ON_ERROR);
		$this->assertArrayNotHasKey('EventTransport', $manifest['dependencies']);
		foreach ([
			'src/Chat/DemoService.php',
			'src/Chat/DemoServiceAgentStream.php',
			'src/Chat/DemoServiceAgentProxy.php',
			'src/Content/Sse.php',
			'src/Content/SseTest.php',
			'local/Page/page-chat.json',
			'local/Page/page-chatagentstream.json',
			'local/Page/page-chatproxy.json',
			'tpl/Content/SseTest.php',
			'local/Chatbot/agentflowstream.json',
			'local/Chatbot/agentflowstream.json.bak',
			'local/Chatbot/agentflowproxy.json',
			'local/Chatbot/agentflowsuggestions.json',
			'local/Chatbot/systempromptproxy.txt',
			'local/Chatbot/baseprompt.json',
			'src/Chat/normal.bin',
			'src/Chat/agent.bin',
			'test/Chat/DemoServiceTest.php',
			'test/Chat/DemoServiceAgentStreamTest.php',
			'test/Chat/DemoServiceAgentProxyTest.php',
			'test/Content/SseOutputTest.php',
			'test/Content/SseTestTest.php'
		] as $path) {
			$this->assertFileDoesNotExist($root . $path);
		}
		$navigation = file_get_contents($root . 'tpl/Content/Navigation.php');
		foreach (['chatagentstream', 'chatproxy', "'name' => 'chat' "] as $removedRoute) {
			$this->assertStringNotContainsString($removedRoute, $navigation);
		}
	}

	public function testOutputClassNamesAreUnique(): void {
		$classes = [
			Base3ChatWebsitePlugin::class,
			DemoServiceSync::class,
			DemoServiceAgent::class,
			DataHawkTest::class,
			Navigation::class
		];
		$names = array_map(static fn(string $class): string => $class::getName(), $classes);
		$this->assertCount(count($names), array_unique($names));
	}

	public function testPageAndFlowConfigurationsRemainValidJson(): void {
		$files = glob(DIR_PLUGIN . 'Base3ChatWebsite/local/Page/*.json') ?: [];
		$this->assertNotEmpty($files);
		foreach ($files as $file) {
			$document = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
			$this->assertArrayHasKey('pageheaders', $document);
		}
		$document = json_decode(file_get_contents(DIR_PLUGIN . 'Base3ChatWebsite/local/Chatbot/agentflow.json'), true, 512, JSON_THROW_ON_ERROR);
		$this->assertArrayHasKey('nodes', $document);
	}
}
