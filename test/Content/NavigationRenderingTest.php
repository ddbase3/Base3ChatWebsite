<?php declare(strict_types=1);

namespace Base3ChatWebsite\Test\Content;

use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3\Api\IContainer;
use Base3\Core\MvcView;
use Base3\Core\ServiceLocator;
use Base3\Language\Api\ILanguage;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Usermanager\Api\IUsermanager;
use Base3ChatWebsite\Content\Navigation;
use PHPUnit\Framework\TestCase;

final class NavigationRenderingTest extends TestCase {

	public function testRenderedNavigationContainsOnlyActiveRestAndMermaidRoutes(): void {
		$original = ServiceLocator::getInstance();
		$locator = new ServiceLocator();
		$usermanager = $this->createStub(IUsermanager::class);
		$usermanager->method('getUser')->willReturn(null);
		$locator->set('usermanager', $usermanager, IContainer::SHARED);
		ServiceLocator::useInstance($locator);
		try {
			$accesscontrol = $this->createStub(IAccesscontrol::class);
			$accesscontrol->method('getUserId')->willReturn(null);
			$language = $this->createStub(ILanguage::class);
			$links = $this->createMock(ILinkTargetService::class);
			$routes = [];
			$links->expects($this->exactly(3))->method('getLink')->willReturnCallback(static function(array $target, array $params = []) use (&$routes): string {
				$routes[] = $target['name'];
				return '/demo/' . $target['name'];
			});
			$navigation = new Navigation(new MvcView($language), $accesscontrol, $links);
			$html = $navigation->getOutput('html', true);
			$this->assertSame(['chatsync', 'chatagent', 'mermaid'], $routes);
			$this->assertStringContainsString('href="/demo/chatsync"', $html);
			$this->assertStringContainsString('href="/demo/chatagent"', $html);
			$this->assertStringContainsString('href="/demo/mermaid"', $html);
			$this->assertStringContainsString('Chat Simple Rest', $html);
			$this->assertStringContainsString('Chat Agent Rest', $html);
			$this->assertStringContainsString('Mermaid Diagrams', $html);
			$this->assertStringNotContainsString('chatagentstream', $html);
			$this->assertStringNotContainsString('chatproxy', $html);
			$this->assertStringNotContainsString('Unable to find template', $html);
		} finally {
			ServiceLocator::useInstance($original);
		}
	}
}
