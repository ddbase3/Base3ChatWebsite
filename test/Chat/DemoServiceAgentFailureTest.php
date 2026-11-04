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

final class DemoServiceAgentFailureTest extends TestCase {

	public function testInvalidAndEmptyFlowDefinitionsReturnStructuredErrors(): void {
		foreach (['{broken', '[]', '{}', 'null', 'false', ''] as $rawFlow) {
			$this->withFlowFixture($rawFlow, 'System', function(): void {
				$factory = $this->createMock(IAgentFlowFactory::class);
				$factory->expects($this->never())->method('createFromArray');
				$result = $this->response($this->service('hello', $factory));
				$this->assertSame('error', $result['type']);
				$this->assertSame('[Flow JSON invalid]', $result['text']);
				$this->assertStringStartsWith('msg_', $result['id']);
				$this->assertNotFalse(strtotime($result['meta']['timestamp']));
			});
		}
	}

	public function testNumericPromptIsConvertedToStringAndSystemFileIsRead(): void {
		$this->withFlowFixture('{"nodes":[{"id":"assistant"}]}', 'System instruction', function(): void {
			$context = $this->createStub(IAgentContext::class);
			$contextFactory = $this->createStub(IAgentContextFactory::class);
			$contextFactory->method('createContext')->willReturn($context);
			$flow = $this->createMock(IAgentFlow::class);
			$flow->expects($this->once())->method('run')->with($this->callback(static fn(array $inputs): bool => $inputs === [
				'system' => 'System instruction', 'user' => '123'
			]))->willReturn(['assistant' => ['message' => ['id' => 'given-id', 'content' => 'Antwort']]]);
			$factory = $this->createMock(IAgentFlowFactory::class);
			$factory->expects($this->once())->method('createFromArray')->with(
				'strictflow', ['nodes' => [['id' => 'assistant']]], $context
			)->willReturn($flow);
			$data = $this->response($this->service(123, $factory, $contextFactory));
			$this->assertSame('chat_response', $data['type']);
			$this->assertSame('given-id', $data['id']);
			$this->assertSame('Antwort', $data['text']);
			$this->assertSame('assistant', $data['meta']['nodeid']);
		});
	}

	public function testEmptySystemPromptIsPassedAsAnEmptyString(): void {
		$this->withFlowFixture('{"nodes":[{"id":"assistant"}]}', '', function(): void {
			$flow = $this->createMock(IAgentFlow::class);
			$flow->expects($this->once())->method('run')->with(['system' => '', 'user' => 'hello'])->willReturn([
				'assistant' => ['message' => ['content' => 'Okay']]
			]);
			$factory = $this->createStub(IAgentFlowFactory::class);
			$factory->method('createFromArray')->willReturn($flow);
			$data = $this->response($this->service('hello', $factory));
			$this->assertSame('Okay', $data['text']);
			$this->assertStringStartsWith('msg_', $data['id']);
		});
	}

	public function testEmptyAndNullAssistantMessagesReturnMissingOutputError(): void {
		$this->withFlowFixture('{"nodes":[{"id":"assistant"}]}', 'System', function(): void {
			foreach ([[], ['assistant' => ['message' => []]], ['assistant' => ['message' => null]]] as $outputs) {
				$flow = $this->createStub(IAgentFlow::class);
				$flow->method('run')->willReturn($outputs);
				$factory = $this->createStub(IAgentFlowFactory::class);
				$factory->method('createFromArray')->willReturn($flow);
				$data = $this->response($this->service('hello', $factory));
				$this->assertSame('error', $data['type']);
				$this->assertSame('[No output from assistant node]', $data['text']);
			}
		});
	}

	public function testExceptionFromFlowFactoryPropagates(): void {
		$this->withFlowFixture('{"nodes":[{"id":"assistant"}]}', 'System', function(): void {
			$factory = $this->createStub(IAgentFlowFactory::class);
			$factory->method('createFromArray')->willThrowException(new \RuntimeException('Flow factory unavailable'));
			$this->expectException(\RuntimeException::class);
			$this->service('hello', $factory)->getOutput();
		});
	}

	public function testExceptionFromAgentFlowExecutionPropagates(): void {
		$this->withFlowFixture('{"nodes":[{"id":"assistant"}]}', 'System', function(): void {
			$flow = $this->createStub(IAgentFlow::class);
			$flow->method('run')->willThrowException(new \RuntimeException('Execution unavailable'));
			$factory = $this->createStub(IAgentFlowFactory::class);
			$factory->method('createFromArray')->willReturn($flow);
			$this->expectException(\RuntimeException::class);
			$this->service('hello', $factory)->getOutput();
		});
	}

	private function response(DemoServiceAgent $service): array {
		return json_decode($service->getOutput('json', true), true, 512, JSON_THROW_ON_ERROR);
	}

	private function service(mixed $prompt, IAgentFlowFactory $factory, ?IAgentContextFactory $contextFactory = null): DemoServiceAgent {
		$request = $this->createStub(IRequest::class);
		$request->method('post')->willReturnCallback(static fn(string $key): mixed => $key === 'prompt' ? $prompt : null);
		if ($contextFactory === null) {
			$contextFactory = $this->createStub(IAgentContextFactory::class);
			$contextFactory->method('createContext')->willReturn($this->createStub(IAgentContext::class));
		}
		return new DemoServiceAgent($request, $this->createStub(IClassMap::class), $contextFactory, $factory);
	}

	private function withFlowFixture(string $flowJson, string $systemPrompt, callable $callback): void {
		$original = getcwd();
		if ($original === false) {
			throw new \RuntimeException('Working directory is unavailable.');
		}
		$root = sys_get_temp_dir() . '/b3cw-agent-test-' . bin2hex(random_bytes(8));
		$flowDirectory = $root . '/plugin/Base3ChatWebsite/local/Chatbot';
		if (!mkdir($flowDirectory, 0700, true)) {
			throw new \RuntimeException('Unable to create temporary flow fixture.');
		}
		file_put_contents($flowDirectory . '/agentflow.json', $flowJson);
		file_put_contents($flowDirectory . '/systemprompt.txt', $systemPrompt);
		try {
			chdir($root);
			$callback();
		} finally {
			chdir($original);
			unlink($flowDirectory . '/agentflow.json');
			unlink($flowDirectory . '/systemprompt.txt');
			rmdir($flowDirectory);
			rmdir(dirname($flowDirectory));
			rmdir(dirname(dirname($flowDirectory)));
			rmdir(dirname(dirname(dirname($flowDirectory))));
			rmdir($root);
		}
	}
}
