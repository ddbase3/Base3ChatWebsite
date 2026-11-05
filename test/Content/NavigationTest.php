<?php declare(strict_types=1);

namespace Base3ChatWebsite\Test\Content;

use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3\Api\IMvcView;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Usermanager\Api\IUsermanager;
use Base3\Usermanager\User;
use Base3ChatWebsite\Content\Navigation;
use PHPUnit\Framework\TestCase;

final class NavigationTest extends TestCase {

	public function testTechnicalNameAndHelp(): void {
		$this->assertSame('navigation', Navigation::getName());
	}

	public function testAuthenticatedNavigationPassesRoleAndLinkCallbackToView(): void {
		$this->assertNavigation(42, 'admin', true);
	}

	public function testAnonymousNavigationLeavesRoleEmpty(): void {
		$this->assertNavigation(null, null, false);
	}

	private function assertNavigation(int|string|null $userId, ?string $role, bool $authenticated): void {
		$data = [];
		$view = $this->createStub(IMvcView::class);
		$view->method('assign')->willReturnCallback(static function(string $key, mixed $value) use (&$data): void {
			$data[$key] = $value;
		});
		$view->method('setPath')->willReturnCallback(function(string $path): void {
			$this->assertSame(DIR_PLUGIN . 'Base3ChatWebsite', $path);
		});
		$view->method('setTemplate')->willReturnCallback(function(string $template): void {
			$this->assertSame('Content/Navigation.php', $template);
		});
		$view->method('loadTemplate')->willReturn('NAVIGATION_CONTENT');
		$accesscontrol = $this->createStub(IAccesscontrol::class);
		$accesscontrol->method('getUserId')->willReturn($userId);
		$usermanager = $this->createStub(IUsermanager::class);
		$usermanager->method('getUser')->willReturn($role === null ? null : User::fromArray(['id' => $userId, 'role' => $role]));
		$links = $this->createStub(ILinkTargetService::class);
		$links->method('getLink')->willReturnCallback(static fn(array $target, array $params = []): string => '/route/' . $target['name'] . '?' . http_build_query($params));

		$navigation = new Navigation($view, $accesscontrol, $usermanager, $links);
		$this->assertSame("Help of Navigation\n", $navigation->getHelp());
		$this->assertSame('NAVIGATION_CONTENT', $navigation->getOutput());
		$this->assertSame($authenticated, $data['authenticated']);
		$this->assertSame($role ?? '', $data['role']);
		$this->assertSame('/route/home?id=7', ($data['getlink'])(['name' => 'home'], ['id' => 7]));
	}
}
