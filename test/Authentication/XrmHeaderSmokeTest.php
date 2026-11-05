<?php declare(strict_types=1);

/** Standalone: php plugin/Base3ChatWebsite/test/Authentication/XrmHeaderSmokeTest.php */

use Base3\Api\IMvcView;
use Base3\Core\BaseAssetResolver;
use Base3\Core\ServiceLocator;
use Base3\Microservice\Api\IMicroserviceConnector;
use Base3Template\Content\PageHeader;

$root = dirname(__DIR__, 4);
foreach (['DIR_ROOT' => $root . '/', 'DIR_SRC' => $root . '/src/', 'DIR_PLUGIN' => $root . '/plugin/', 'DIR_TEST' => $root . '/test/', 'DIR_CNF' => $root . '/cnf/'] as $name => $path) {
	if (!defined($name)) define($name, $path);
}
require_once DIR_SRC . 'Core/Autoloader.php';
Base3\Core\Autoloader::register();

final class HeaderTestView implements IMvcView {
	public array $values = [];
	public function setPath(string $path = '.') {}
	public function assign(string $key, $value) { $this->values[$key] = $value; }
	public function setTemplate(string $template = 'default') {}
	public function loadTemplate(): string { return ''; }
	public function loadBricks(string $set, string $language = '') {}
	public function getBricks(string $set): ?array { return []; }
}

final class HeaderTestRemoteLogin implements IMicroserviceConnector {
	public function getMicroserviceUrl() { return 'https://account.example.test/login'; }
	public function getProxiedInstance(): object { return $this; }
	public function __call($name, $args) { return null; }
}

$view = new HeaderTestView();
$locator = new ServiceLocator();
$locator->set('statushandler', new class { public function get(string $key) { return 50; } });
$locator->set('mainurl', 'https://chat.example.test/');
$locator->set('logo', 'plugin/Base3ChatWebsite/assets/logo/logo.svg');
$locator->set('navigation', new class { public function getOutput(): string { return ''; } });
$locator->set('language', new class { public function getLanguage(): string { return 'en'; } });
$locator->set('accesscontrol', new class { public function getUserId(): string { return 'alice'; } });
$locator->set('usermanager', new class { public function getUser(): object { return (object)['name'=>'Alice']; } });
$locator->set('loginpage', new class { public function getUrl(): string { return 'login.php'; } });
$_REQUEST['name'] = 'login';
(new PageHeader($view, $locator, new BaseAssetResolver()))->getHtml();
if ($view->values['loginurl'] !== 'login.php' || $view->values['remote_login'] !== false ||
	$view->values['logouturl'] !== 'https://chat.example.test/login.php?logout=1') {
	throw new RuntimeException('Local Chat login/logout still points to the Account service.');
}
$locator->set('loginpage', new HeaderTestRemoteLogin());
(new PageHeader($view, $locator, new BaseAssetResolver()))->getHtml();
if ($view->values['remote_login'] !== true || $view->values['logouturl'] !== 'https://account.base3.de/logout.php') {
	throw new RuntimeException('Shared header changed the existing external login mode.');
}
echo "Chat header local logout and legacy remote layout: OK\n";
