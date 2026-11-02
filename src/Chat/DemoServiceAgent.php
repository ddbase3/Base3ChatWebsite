<?php declare(strict_types=1);

namespace Base3ChatWebsite\Chat;

use Base3\Api\IOutput;
use Base3\Api\IRequest;
use Base3\Api\IClassMap;
use MissionBay\Api\IAgentContextFactory;
use MissionBay\Api\IAgentFlowFactory;

/**
 * Minimal chatbot service using MissionBay AgentFlow.
 * - Loads flow from local/Chatbot/agentflow.json
 * - Loads system prompt from local/Chatbot/systemprompt.txt
 * - Executes AiAssistantNode with session memory + telegram tool
 */
class DemoServiceAgent implements IOutput {

	public function __construct(
		private readonly IRequest $request,
		private readonly IClassMap $classmap,
		private readonly IAgentContextFactory $contextFactory,
		private readonly IAgentFlowFactory $flowFactory
	) {}

	public static function getName(): string {
		return 'demoserviceagent';
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		if ($this->request->get('baseprompt') !== null) {
			return $this->getBasePrompt();
		}

		if ($this->request->post('prompt') !== null) {
			return $this->getAgentResponse();
		}

		return '';
	}

	public function getHelp(): string {
		return 'Help for DemoServiceAgent.';
	}

	/**
	 * Returns static base prompt for testing.
	 */
	private function getBasePrompt(): string {
		return "Testing...";
	}

	/**
	 * Executes the AgentFlow and returns assistant message.
	 */
	private function getAgentResponse(): string {
		$context = $this->contextFactory->createContext();

		$flowFile = 'plugin/Base3ChatWebsite/local/Chatbot/agentflow.json';
		$systemFile = 'plugin/Base3ChatWebsite/local/Chatbot/systemprompt.txt';

		$flowJson = file_get_contents($flowFile);
		$flowConfig = json_decode($flowJson, true);

		if (!$flowConfig) {
			return $this->errorResponse("[Flow JSON invalid]");
		}

		$flow = $this->flowFactory->createFromArray("strictflow", $flowConfig, $context);

		$systemPrompt = file_get_contents($systemFile) ?: '';
		$userPrompt = (string)$this->request->post('prompt');

		$input = [
			'system' => $systemPrompt,
			'user' => $userPrompt
		];

		$outputs = $flow->run($input);

		// expect: outputs['assistant']['message']
		$response = $outputs['assistant']['message'] ?? null;

		if (!$response) {
			return $this->errorResponse("[No output from assistant node]");
		}

		// Build final JSON response
		return json_encode([
			'id' => $response['id'] ?? uniqid('msg_', true),
			'type' => 'chat_response',
			'text' => $response['content'] ?? '',
			'meta' => [
				'timestamp' => gmdate('c'),
				'nodeid' => 'assistant'
			]
		], JSON_UNESCAPED_UNICODE);
	}

	/**
	 * Helper for error responses.
	 */
	private function errorResponse(string $msg): string {
		return json_encode([
			'id' => uniqid('msg_', true),
			'type' => 'error',
			'text' => $msg,
			'meta' => [
				'timestamp' => gmdate('c')
			]
		], JSON_UNESCAPED_UNICODE);
	}
}
