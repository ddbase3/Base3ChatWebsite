<?php declare(strict_types=1);

namespace Base3ChatWebsite\Test;

use Base3\Api\IClassMap;
use Base3\Api\IDisplay;
use Base3\Api\IRequest;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationDecision;
use Base3\Authorization\Dto\AuthorizationRequest;
use Base3ChatWebsite\Chat\GlobalChatbotRenderOutput;
use PHPUnit\Framework\TestCase;

final class GlobalChatbotRenderOutputTest extends TestCase {

	public function testDeniedFeatureCannotCreateChatbot(): void {
		$classMap = $this->createMock(IClassMap::class);
		$classMap->expects(self::never())->method('getInstanceByInterfaceName');
		$output = new GlobalChatbotRenderOutput($this->authorization(false), $classMap, $this->ajaxRequest());
		self::assertSame('', $output->getOutput());
		self::assertSame(403, http_response_code());
	}

	public function testNonAjaxOrMutatingRequestCannotCreateChatbot(): void {
		$classMap = $this->createMock(IClassMap::class);
		$classMap->expects(self::never())->method('getInstanceByInterfaceName');
		$request = $this->createStub(IRequest::class);
		$request->method('server')->willReturnCallback(static fn(string $name, $default = null) => match ($name) {
			'REQUEST_METHOD' => 'POST', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest', default => $default
		});
		$output = new GlobalChatbotRenderOutput($this->authorization(true), $classMap, $request);
		self::assertSame('', $output->getOutput());
		self::assertSame(403, http_response_code());
	}

	public function testAllowedAjaxRequestRendersOnlyTheStoredGlobalChatbot(): void {
		$display = $this->createMock(IDisplay::class);
		$display->expects(self::once())->method('setData')->with([
			'config_group' => GlobalChatbotRenderOutput::CONFIG_GROUP,
			'config_name' => GlobalChatbotRenderOutput::CONFIG_NAME
		]);
		$display->expects(self::once())->method('getOutput')->with('html', false)->willReturn('<div>Chat</div>');
		$classMap = $this->createMock(IClassMap::class);
		$classMap->expects(self::once())->method('getInstanceByInterfaceName')
			->with(IDisplay::class, 'chatbotdisplay')->willReturn($display);
		$authorization = $this->createMock(IAuthorizationService::class);
		$authorization->method('getDefaultAuthority')->willReturn('site');
		$authorization->expects(self::once())->method('decide')->willReturnCallback(static function(AuthorizationRequest $request): AuthorizationDecision {
			self::assertSame('site', $request->authority);
			self::assertSame('globalchatbotlauncherfeature', $request->permission->permission);
			return AuthorizationDecision::allow();
		});
		$output = new GlobalChatbotRenderOutput($authorization, $classMap, $this->ajaxRequest());
		self::assertSame('<div>Chat</div>', $output->getOutput());
	}

	private function authorization(bool $allowed): IAuthorizationService {
		$authorization = $this->createStub(IAuthorizationService::class);
		$authorization->method('getDefaultAuthority')->willReturn('site');
		$authorization->method('decide')->willReturn($allowed ? AuthorizationDecision::allow() : AuthorizationDecision::deny());
		return $authorization;
	}

	private function ajaxRequest(): IRequest {
		$request = $this->createStub(IRequest::class);
		$request->method('server')->willReturnCallback(static fn(string $name, $default = null) => match ($name) {
			'REQUEST_METHOD' => 'GET', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest', default => $default
		});
		return $request;
	}
}
