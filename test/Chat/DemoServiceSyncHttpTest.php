<?php declare(strict_types=1);

namespace Base3ChatWebsite\Test\Chat;

use Base3\Api\IRequest;
use Base3\Configuration\Api\IConfiguration;
use Base3ChatWebsite\Chat\DemoServiceSync;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class DemoServiceSyncHttpTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		require_once __DIR__ . '/CurlTransportState.php';
		require_once __DIR__ . '/CurlTransportFunctions.php';
		CurlTransportState::reset();
	}

	public function testSuccessfulCompletionSendsConfiguredRequestAndReturnsAssistantText(): void {
		CurlTransportState::$response = json_encode([
			'choices' => [['message' => ['content' => 'Grüße aus der API']]]
		], JSON_THROW_ON_ERROR);
		$result = $this->callWithPrompt('Wie geht es dir?', 'https://example.test/v1///', 'secret-key');

		$this->assertSame('chat_response', $result['type']);
		$this->assertSame('Grüße aus der API', $result['text']);
		$this->assertSame('assistent', $result['meta']['nodeid']);
		$this->assertFalse($result['meta']['blocked']);
		$this->assertNull($result['meta']['error']);
		$this->assertStringStartsWith('msg_', $result['id']);
		$this->assertNotFalse(strtotime($result['meta']['timestamp']));

		$options = CurlTransportState::$options;
		$this->assertSame('https://example.test/v1/chat/completions', $options[\Base3ChatWebsite\Chat\CURLOPT_URL]);
		$this->assertTrue($options[\Base3ChatWebsite\Chat\CURLOPT_POST]);
		$this->assertSame(['Content-Type: application/json', 'Authorization: Bearer secret-key'], $options[\Base3ChatWebsite\Chat\CURLOPT_HTTPHEADER]);
		$this->assertTrue($options[\Base3ChatWebsite\Chat\CURLOPT_RETURNTRANSFER]);
		$this->assertSame(30, $options[\Base3ChatWebsite\Chat\CURLOPT_TIMEOUT]);
		$payload = json_decode($options[\Base3ChatWebsite\Chat\CURLOPT_POSTFIELDS], true, 512, JSON_THROW_ON_ERROR);
		$this->assertSame('gpt-4o-mini', $payload['model']);
		$this->assertFalse($payload['stream']);
		$this->assertSame([['role' => 'user', 'content' => 'Wie geht es dir?']], $payload['messages']);
		$this->assertSame(1, CurlTransportState::$opened);
		$this->assertSame(1, CurlTransportState::$executed);
		$this->assertSame(1, CurlTransportState::$closed);
	}

	public function testEmptyAndNumericPromptsAreStillSent(): void {
		CurlTransportState::$response = '{"choices":[{"message":{"content":"ok"}}]}';
		$this->assertSame('ok', $this->callWithPrompt('')['text']);
		$this->assertSame('', json_decode(CurlTransportState::$options[\Base3ChatWebsite\Chat\CURLOPT_POSTFIELDS], true)['messages'][0]['content']);
		CurlTransportState::reset();
		CurlTransportState::$response = '{"choices":[{"message":{"content":"ok"}}]}';
		$this->assertSame('ok', $this->callWithPrompt(0)['text']);
		$this->assertSame(0, json_decode(CurlTransportState::$options[\Base3ChatWebsite\Chat\CURLOPT_POSTFIELDS], true)['messages'][0]['content']);
	}

	public function testHttpFailuresTakePrecedenceOverOtherwiseValidResponseBody(): void {
		foreach ([400, 401, 429, 500, 503] as $httpCode) {
			CurlTransportState::reset();
			CurlTransportState::$response = '{"choices":[{"message":{"content":"Do not show"}}]}';
			CurlTransportState::$httpCode = $httpCode;
			$result = $this->callWithPrompt('test');
			$this->assertSame('API error occurred.', $result['text']);
			$this->assertSame('HTTP ' . $httpCode, $result['meta']['error']);
			$this->assertSame(1, CurlTransportState::$closed);
		}
	}

	public function testTransportFailuresExposeCurlErrorAndStillCloseConnection(): void {
		CurlTransportState::$response = false;
		CurlTransportState::$error = 'connection reset';
		$result = $this->callWithPrompt('test');
		$this->assertSame('API error occurred.', $result['text']);
		$this->assertSame('connection reset', $result['meta']['error']);
		$this->assertSame(1, CurlTransportState::$closed);
	}

	public function testTransportFailureWithoutErrorTextUsesHttpCode(): void {
		CurlTransportState::$response = false;
		CurlTransportState::$httpCode = 0;
		$result = $this->callWithPrompt('test');
		$this->assertSame('HTTP 0', $result['meta']['error']);
	}

	public function testTransportErrorTextTakesPrecedenceOverHttpStatus(): void {
		CurlTransportState::$response = false;
		CurlTransportState::$error = 'read timed out';
		CurlTransportState::$httpCode = 504;
		$result = $this->callWithPrompt('test');
		$this->assertSame('read timed out', $result['meta']['error']);
	}

	public function testMalformedJsonResponsesReturnStructuredErrors(): void {
		foreach (['', 'null', 'false', '[]', '{}', '{invalid', '{"choices":[]}', '{"choices":[{"message":{}}]}', '{"choices":[{"message":{"content":null}}]}'] as $body) {
			CurlTransportState::reset();
			CurlTransportState::$response = $body;
			$result = $this->callWithPrompt('test');
			$this->assertSame('Invalid response from OpenAI.', $result['text']);
			$this->assertSame('Invalid API response', $result['meta']['error']);
			$this->assertSame('chat_response', $result['type']);
		}
	}

	public function testJsonSuccessPreservesUnicodeAndMarkupAsData(): void {
		CurlTransportState::$response = json_encode(['choices' => [['message' => ['content' => '<strong>Grüße ☀</strong>']]]], JSON_THROW_ON_ERROR);
		$response = $this->callWithPrompt('test');
		$this->assertSame('<strong>Grüße ☀</strong>', $response['text']);
		$this->assertNull($response['meta']['error']);
	}

	public function testPromptRequestPrecedesFeedbackHandling(): void {
		CurlTransportState::$response = '{"choices":[{"message":{"content":"reply"}}]}';
		$request = $this->request(['request' => ['prompt' => 'hello'], 'post' => ['feedback' => 'good']]);
		$service = new DemoServiceSync($request, $this->configuration());
		$this->assertSame('reply', json_decode($service->getOutput(), true, 512, JSON_THROW_ON_ERROR)['text']);
		$this->assertSame(1, CurlTransportState::$opened);
	}

	public function testBasePromptPreventsNetworkEvenWithPromptAndFeedback(): void {
		$request = $this->request(['get' => ['baseprompt' => ''], 'request' => ['prompt' => 'hello'], 'post' => ['feedback' => 'good']]);
		$service = new DemoServiceSync($request, $this->configuration());
		$this->assertSame('Testing...', $service->getOutput());
		$this->assertSame(0, CurlTransportState::$opened);
	}

	private function callWithPrompt(mixed $prompt, string $endpoint = 'https://example.test/v1', string $apiKey = 'secret'): array {
		$service = new DemoServiceSync($this->request(['request' => ['prompt' => $prompt]]), $this->configuration($endpoint, $apiKey));
		return json_decode($service->getOutput('json', true), true, 512, JSON_THROW_ON_ERROR);
	}

	private function request(array $values): IRequest {
		$request = $this->createStub(IRequest::class);
		$request->method('get')->willReturnCallback(static fn(string $key): mixed => $values['get'][$key] ?? null);
		$request->method('request')->willReturnCallback(static fn(string $key): mixed => $values['request'][$key] ?? null);
		$request->method('post')->willReturnCallback(static fn(string $key): mixed => $values['post'][$key] ?? null);
		return $request;
	}

	private function configuration(string $endpoint = 'https://example.test/v1', string $apiKey = 'secret'): IConfiguration {
		$configuration = $this->createStub(IConfiguration::class);
		$configuration->method('get')->willReturn(['endpoint' => $endpoint, 'apikey' => $apiKey]);
		return $configuration;
	}
}
