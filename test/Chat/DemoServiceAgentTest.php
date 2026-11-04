<?php declare(strict_types=1);

namespace Base3ChatWebsite\Test\Chat;

use AssistantFoundation\Api\IAgentContext;
use Base3\Api\IClassMap;
use Base3\Api\IRequest;
use Base3ChatWebsite\Chat\DemoServiceAgent;
use MissionBay\Api\IAgentContextFactory;
use MissionBay\Api\IAgentFlow;
use MissionBay\Api\IAgentFlowFactory;
use PHPUnit\Framework\TestCase;

final class DemoServiceAgentTest extends TestCase {

	public function testTechnicalNameAndHelp(): void {
		$this->assertSame('demoserviceagent', DemoServiceAgent::getName());
		$this->assertSame('Help for DemoServiceAgent.', $this->service([])->getHelp());
	}

	public function testEmptyRequestDoesNotStartAgentFlow(): void {
		$this->assertSame('', $this->service([])->getOutput());
		$this->assertSame('', $this->service(['post' => ['feedback' => 'ignored']])->getOutput());
	}

	public function testBasePromptTakesPrecedenceOverUserPrompt(): void {
		$this->assertSame('Testing...', $this->service(['get' => ['baseprompt' => '0'], 'post' => ['prompt' => 'ignored']])->getOutput());
		$this->assertSame('Testing...', $this->service(['get' => ['baseprompt' => '']])->getOutput('html', true));
	}

	public function testValidFlowProducesRestResponseAndPassesPromptInputs(): void {
		$flow = $this->createStub(IAgentFlow::class);
		$flow->method('run')->willReturnCallback(function(array $input): array {
			$this->assertSame('Grüße aus dem Chat', $input['user']);
			$this->assertSame(file_get_contents(DIR_ROOT . 'plugin/Base3ChatWebsite/local/Chatbot/systemprompt.txt'), $input['system']);
			return ['assistant' => ['message' => ['id' => 'assistant-17', 'content' => 'Antwort aus dem Agenten']]];
		});
		$data = $this->fromFlow(['post' => ['prompt' => 'Grüße aus dem Chat']], $flow);
		$this->assertSame('assistant-17', $data['id']);
		$this->assertSame('chat_response', $data['type']);
		$this->assertSame('Antwort aus dem Agenten', $data['text']);
		$this->assertSame('assistant', $data['meta']['nodeid']);
		$this->assertNotFalse(strtotime($data['meta']['timestamp']));
	}

	public function testMissingAssistantNodeReturnsStructuredError(): void {
		$flow = $this->createStub(IAgentFlow::class);
		$flow->method('run')->willReturn(['other' => ['message' => ['content' => 'not assistant']]]);
		$data = $this->fromFlow(['post' => ['prompt' => 'Hello']], $flow);
		$this->assertSame('error', $data['type']);
		$this->assertSame('[No output from assistant node]', $data['text']);
		$this->assertStringStartsWith('msg_', $data['id']);
	}

	public function testMissingMessageIdAndContentUseConfiguredResponseDefaults(): void {
		$flow = $this->createStub(IAgentFlow::class);
		$flow->method('run')->willReturn(['assistant' => ['message' => ['other' => 'value']]]);
		$data = $this->fromFlow(['post' => ['prompt' => ''] ], $flow);
		$this->assertSame('chat_response', $data['type']);
		$this->assertStringStartsWith('msg_', $data['id']);
		$this->assertSame('', $data['text']);
	}

	private function fromFlow(array $values, IAgentFlow $flow): array {
		$context = $this->createStub(IAgentContext::class);
		$contextFactory = $this->createStub(IAgentContextFactory::class);
		$contextFactory->method('createContext')->willReturn($context);
		$flowFactory = $this->createStub(IAgentFlowFactory::class);
		$flowFactory->method('createFromArray')->willReturnCallback(function(string $type, array $config, IAgentContext $actualContext) use ($context, $flow): IAgentFlow {
			$this->assertSame('strictflow', $type);
			$this->assertSame($context, $actualContext);
			$this->assertNotEmpty($config['nodes']);
			return $flow;
		});
		$previous = getcwd();
		if ($previous === false) {
			throw new \RuntimeException('Working directory is unavailable.');
		}
		chdir(DIR_ROOT);
		try {
			return json_decode($this->service($values, $contextFactory, $flowFactory)->getOutput(), true, 512, JSON_THROW_ON_ERROR);
		} finally {
			chdir($previous);
		}
	}

	private function service(array $values, ?IAgentContextFactory $contextFactory = null, ?IAgentFlowFactory $flowFactory = null): DemoServiceAgent {
		$request = $this->createStub(IRequest::class);
		$request->method('get')->willReturnCallback(static fn(string $key): mixed => $values['get'][$key] ?? null);
		$request->method('post')->willReturnCallback(static fn(string $key): mixed => $values['post'][$key] ?? null);
		return new DemoServiceAgent(
			$request,
			$this->createStub(IClassMap::class),
			$contextFactory ?? $this->createStub(IAgentContextFactory::class),
			$flowFactory ?? $this->createStub(IAgentFlowFactory::class)
		);
	}
}
