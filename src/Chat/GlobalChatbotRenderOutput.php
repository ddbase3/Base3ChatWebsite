<?php declare(strict_types=1);

namespace Base3ChatWebsite\Chat;

use Base3\Api\IClassMap;
use Base3\Api\IDisplay;
use Base3\Api\IOutput;
use Base3\Api\IRequest;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationRequest;
use Base3\Usermanager\Permission;
use Chatbot\Content\ChatbotDisplay;
use ClientStack\GlobalChatbotLauncher\GlobalChatbotLauncherFeature;

/** Renders the single server-owned global chatbot instance for this website. */
final class GlobalChatbotRenderOutput implements IOutput {

	public const CONFIG_GROUP = 'chatbot';
	public const CONFIG_NAME = 'default';

	public function __construct(
		private readonly IAuthorizationService $authorization,
		private readonly IClassMap $classMap,
		private readonly IRequest $request
	) {}

	public static function getName(): string {
		return 'globalchatbotrenderoutput';
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		if ($out !== 'html') {
			http_response_code(404);
			return '';
		}
		if (strtoupper((string)$this->request->server('REQUEST_METHOD', 'GET')) !== 'GET'
			|| $this->request->server('HTTP_X_REQUESTED_WITH', '') !== 'XMLHttpRequest') {
			http_response_code(403);
			return '';
		}
		if (!$this->authorization->decide(new AuthorizationRequest(
			$this->authorization->getDefaultAuthority(),
			Permission::for('feature', GlobalChatbotLauncherFeature::getName())
		))->isAllowed()) {
			http_response_code(403);
			return '';
		}

		$display = $this->classMap->getInstanceByInterfaceName(IDisplay::class, ChatbotDisplay::getName());
		if (!$display instanceof IDisplay) {
			http_response_code(503);
			return '';
		}
		$display->setData([
			'config_group' => self::CONFIG_GROUP,
			'config_name' => self::CONFIG_NAME
		]);
		return $display->getOutput('html', false);
	}
}
