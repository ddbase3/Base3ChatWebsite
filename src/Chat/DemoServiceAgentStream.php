<?php declare(strict_types=1);

namespace Base3ChatWebsite\Chat;

use Base3\Api\IRequest;
use Chatbot\Service\ChatbotService;
use MissionBay\Api\IAgentContextFactory;
use MissionBay\Api\IAgentFlowFactory;

/**
 * DemoServiceAgentStream
 *
 * Chat-specific configuration for the generic ChatbotService.
 */
class DemoServiceAgentStream extends ChatbotService {

	public function __construct(
		IRequest $request,
		IAgentContextFactory $contextFactory,
		IAgentFlowFactory $flowFactory
	) {
		parent::__construct($request, $contextFactory, $flowFactory);
	}

	public static function getName(): string {
		return 'demoserviceagentstream';
	}

	public function getHelp(): string {
		return 'Help information for DemoServiceAgentStream.';
	}

	protected function getBasePromptFile(): string {
		return 'plugin/Base3ChatWebsite/local/Chatbot/baseprompt.json';
	}

	protected function getSystemPromptFile(): string {
		return 'plugin/Base3ChatWebsite/local/Chatbot/systemprompt.txt';
	}

	protected function getAgentFlowFile(): string {
		return 'plugin/Base3ChatWebsite/local/Chatbot/agentflowstream.json';
	}

	protected function getSuggestionFlowFile(): string {
		return 'plugin/Base3ChatWebsite/local/Chatbot/agentflowsuggestions.json';
	}

	protected function getSimpleSuggestionPrompt(): string {
		return <<<TXT
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
	}
}
