<?php declare(strict_types=1);

namespace Base3ChatWebsite\Chat;

use Base3\Api\IOutput;
use Base3\Api\IRequest;
use Base3\Api\IClassMap;
use MissionBay\Api\IAgentContextFactory;
use MissionBay\Api\IAgentFlowFactory;

/**
 * DemoServiceAgentProxy
 *
 * Very thin wrapper around the MissionBay AgentFlow.
 * The StreamingAiAssistantNode performs:
 * - tool-calling phase incl proxy tool
 * - opens EventStream
 * - streams tokens
 * - writes memory
 *
 * This service only loads and runs the flow.
 */
class DemoServiceAgentProxy implements IOutput {

	private IRequest $request;
	private IClassMap $classmap;
	private IAgentContextFactory $contextFactory;
	private IAgentFlowFactory $flowFactory;

	public function __construct(
		IRequest $request,
		IClassMap $classmap,
		IAgentContextFactory $contextFactory,
		IAgentFlowFactory $flowFactory
	) {
		$this->request = $request;
		$this->classmap = $classmap;
		$this->contextFactory = $contextFactory;
		$this->flowFactory = $flowFactory;
	}

	public static function getName(): string {
		return 'demoserviceagentproxy';
	}

	public function getOutput(string $out = 'html', bool $final = false): string {

		// Provide a base-test prompt
		if ($this->request->get('baseprompt') !== null) {
			return $this->getBasePrompt();
		}

		// Streaming request
		if ($this->request->request('prompt') !== null) {
			return $this->runStreamingFlow();
		}

		// Suggestion
		if ($this->request->request('suggestions') !== null) {
			return $this->suggestPrompts();
		}

		return '';
	}

	public function getHelp(): string {
		return 'Help information for DemoServiceAgentProxy.';
	}

	/**
	 * Executes the AgentFlow for streaming.
	 * The actual streaming (SSE) is executed inside the StreamingAiAssistantNode.
	 */
	private function runStreamingFlow(): string {

		$context = $this->contextFactory->createContext();

		$flowFile = 'plugin/Base3ChatWebsite/local/Chatbot/agentflowproxy.json';
		$systemFile = 'plugin/Base3ChatWebsite/local/Chatbot/systempromptproxy.txt';

		$json = file_get_contents($flowFile);
		$config = json_decode($json, true);

		if (!$config) {
			return $this->errorResponse("[Invalid Flow JSON]");
		}

		$flow = $this->flowFactory->createFromArray("strictflow", $config, $context);

		$systemPrompt = file_get_contents($systemFile) ?: '';
		$userPrompt = (string)$this->request->request('prompt');

		$inputs = [
			'system' => $systemPrompt,
			'user' => $userPrompt
		];

		// The StreamingAiAssistantNode opens the SSE stream internally.
		$flow->run($inputs);

		// Necessary, otherwise mime type error.
		exit;

		// Nothing else to output here, the stream is already running.
		return '';
	}

	/**
	 * Returns a random base prompt.
	 */
        private function getBasePrompt(): string {
                $json = file_get_contents('plugin/Base3ChatWebsite/local/Chatbot/baseprompt.json');
                $prompts = json_decode($json);
                return $prompts[array_rand($prompts)];
        }

	/**
	 * Suggests follow-up prompts based on conversation memory.
	 */
	private function suggestPrompts(): string {

		$context = $this->contextFactory->createContext();

		// Load suggestions flow
		$flowFile = 'plugin/Base3ChatWebsite/local/Chatbot/agentflowsuggestions.json';
		$json = file_get_contents($flowFile);
		$config = json_decode($json, true);

		if (!$config) {
			return $this->errorResponse("[Invalid Suggestions Flow JSON]");
		}

		$flow = $this->flowFactory->createFromArray("strictflow", $config, $context);

		$systemPrompt = <<<TXT
You are a prompt suggestion generator for a chat interface.

Rules:
- Produce 3 extreme short, concrete follow-up prompts.
- They MUST be relevant to the user's most recent message and the ongoing conversation.
- Do NOT ask the user what they want. Do NOT output generic questions like “How can I help you?”.
- Each suggestion must be actionable and specific.
- Use the same language the user used in their last message.
- Output ONLY a JSON array of strings. No explanations, no code fences.

Your task: Based on the last user message and conversation context, propose what the user could naturally ask or explore next.
TXT;

		$userPrompt = "Generate suggestions.";

		$inputs = [
			'system' => $systemPrompt,
			'prompt' => $userPrompt,
			'mode'   => 'suggestions'
		];

		$output = $flow->run($inputs);

		// Flow outputs are keyed by node id ("assistant"), then by port name ("message")
		$msg = '';
		if (isset($output['assistant']['message']['content'])) {
			$msg = (string)$output['assistant']['message']['content'];
		} elseif (isset($output['message']['content'])) {
			// fallback, just in case
			$msg = (string)$output['message']['content'];
		}

		// --- CLEAN JSON codeblock ---
		$clean = trim($msg);
		$clean = preg_replace('/^```json/i', '', $clean);
		$clean = preg_replace('/^```/i', '', $clean);
		$clean = preg_replace('/```$/', '', $clean);
		$clean = trim($clean);

		$decoded = json_decode($clean, true);

		if (!is_array($decoded)) {
			return json_encode([
				'error' => 'Invalid JSON from suggestions model',
				'raw'   => $msg,
				'clean' => $clean
			], JSON_UNESCAPED_UNICODE);
		}

		return json_encode($decoded, JSON_UNESCAPED_UNICODE);
	}

	/**
	 * Returns a JSON error object.
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
