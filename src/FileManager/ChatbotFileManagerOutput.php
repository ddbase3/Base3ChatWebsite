<?php declare(strict_types=1);

namespace Base3ChatWebsite\FileManager;

use Base3\Api\IOutput;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Api\IRequest;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationRequest;
use Base3\Usermanager\Api\IUsermanager;
use Base3\Usermanager\Permission;
use Base3\Translation\Api\ITranslation;
use Chatbot\SettingsTab\ChatbotconfigSettingsTab;
use ClientStack\FileManager\FileManagerHttpService;
use ResourceFoundation\Api\IManagedFileStorageService;

/** Authorizes the site's single chatbot settings owner before using the shared FileManager protocol. */
final class ChatbotFileManagerOutput implements IOutput {

	private const OWNER_GROUP = 'chatbot';
	private const OWNER_NAME = 'default';

	public function __construct(
		private readonly IRequest $request,
		private readonly IUsermanager $userManager,
		private readonly IAuthorizationService $authorization,
		private readonly IManagedFileStorageService $storage,
		private readonly FileManagerHttpService $http,
		private readonly ITranslation $translation
	) {}

	public static function getName(): string {
		return 'chatbotfilemanageroutput';
	}

	/** @return array<string,string> Same-origin URLs consumed by ClientStack's HttpFileManagerAdapter. */
	public static function buildEndpoints(ILinkTargetService $links): array {
		$base = $links->getLink(
			['name' => self::getName(), 'out' => 'html'],
			['fm_group' => self::OWNER_GROUP, 'fm_name' => self::OWNER_NAME]
		);
		return FileManagerHttpService::buildEndpoints($base);
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		if (!in_array($out, ['html', 'json'], true)) return $this->error(404);
		$actor = $this->userManager->getUser();
		if ($actor === null || trim((string)$actor->id) === '') return $this->error(403);
		$authority = $this->authorization->getDefaultAuthority();
		foreach ([Permission::for('feature', ChatbotconfigSettingsTab::getName()), Permission::for('system', 'admin')] as $permission) {
			if (!$this->authorization->decide(new AuthorizationRequest($authority, $permission))->isAllowed()) {
				return $this->error(403);
			}
		}
		if ($this->request->get('fm_group') !== self::OWNER_GROUP || $this->request->get('fm_name') !== self::OWNER_NAME) {
			return $this->error(404);
		}
		$action = $this->request->get('fm_action', '');
		if (!is_string($action) || $action === '') return $this->error(404);
		if ($action !== 'download' && $this->request->server('HTTP_X_REQUESTED_WITH', '') !== 'XMLHttpRequest') {
			return $this->error(403);
		}

		// Ensures the owner storage and its server-side access denial are provisioned.
		$this->storage->openOrCreate(self::OWNER_GROUP, self::OWNER_NAME, IManagedFileStorageService::MODE_COLLECTION);
		return $this->http->handle(
			$action, self::OWNER_GROUP, self::OWNER_NAME,
			IManagedFileStorageService::MODE_COLLECTION,
			(string)$actor->id, FileManagerHttpService::DEFAULT_MAX_FILE_SIZE
		);
	}

	private function error(int $status): string {
		http_response_code($status);
		if (!headers_sent()) {
			header('Content-Type: application/json; charset=utf-8');
			header('Cache-Control: no-store');
		}
		return json_encode(['message' => $this->translation->translate('FileManager', self::getName(), 'denied')], JSON_THROW_ON_ERROR);
	}
}
