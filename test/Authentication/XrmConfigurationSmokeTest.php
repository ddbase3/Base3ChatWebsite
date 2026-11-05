<?php declare(strict_types=1);

/** Standalone: php plugin/Base3ChatWebsite/test/Authentication/XrmConfigurationSmokeTest.php */

use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3Template\Content\LoginBlock;
use Base3\Configuration\Api\IConfiguration;
use Base3\Api\IContainer;
use Base3\Configuration\ArrayConfiguration;
use Base3\Core\ServiceLocator;
use Base3\Usermanager\Api\IUsermanager;
use Base3ChatWebsite\Base3ChatWebsitePlugin;
use Base3Template\Usermanager\XrmUsermanager;
use KeyHarborClient\Microservice\KeyHarborConnector;

$root = dirname(__DIR__, 4);
foreach (['DIR_ROOT' => $root . '/', 'DIR_SRC' => $root . '/src/', 'DIR_PLUGIN' => $root . '/plugin/', 'DIR_TEST' => $root . '/test/', 'DIR_CNF' => $root . '/cnf/'] as $name => $path) {
	if (!defined($name)) define($name, $path);
}
require_once DIR_SRC . 'Core/Autoloader.php';
Base3\Core\Autoloader::register();

$locator = new ServiceLocator();
ServiceLocator::useInstance($locator);
$locator->set(IConfiguration::class, new ArrayConfiguration([
	'xrm' => [
		'url' => 'https://xrm.example.test/',
		'token' => 'b3k_' . str_repeat('a', 20) . '_' . str_repeat('A', 43)
	]
]), IContainer::SHARED);
(new Base3ChatWebsitePlugin($locator))->init();
$auth = $locator->get('xrmauthenticationconnector');
$user = $locator->get('xrmusermanagerconnector');
if (!$auth instanceof KeyHarborConnector || !$user instanceof KeyHarborConnector ||
	$auth->getMicroserviceUrl() !== 'https://xrm.example.test/xrmauthenticationmicroservice.json' ||
	$user->getMicroserviceUrl() !== 'https://xrm.example.test/xrmusermanagermicroservice.json' ||
	!$locator->has(IUsermanager::class) || !$locator->has(IAccesscontrol::class) || !$locator->has('xrmauthentication') || !$locator->has('xrmusermanager')) {
	throw new RuntimeException('XRM connector or usermanager DI mapping differs from the agreed configuration.');
}
$source = file_get_contents(DIR_PLUGIN . 'Base3ChatWebsite/src/Base3ChatWebsitePlugin.php');
if (str_contains($source, "get('Account'") || str_contains($source, 'ssotoken')) {
	throw new RuntimeException('Legacy Account identity registration remains in Chat.');
}
echo "Chat XRM configuration and DI wiring: OK\n";
