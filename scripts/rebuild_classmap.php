<?php declare(strict_types=1);

/** Refresh the existing BASE3 class map after installing/removing a discoverable service. */
if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit(1);
}

$root = dirname(__DIR__, 3);
define('DIR_ROOT', $root . '/');
foreach (['CNF' => 'cnf', 'SRC' => 'src', 'LOCAL' => 'local', 'PLUGIN' => 'plugin', 'TEST' => 'test', 'TMP' => 'tmp', 'TPL' => 'tpl', 'USERFILES' => 'userfiles'] as $constant => $folder) {
	define('DIR_' . $constant, DIR_ROOT . $folder . '/');
}

require_once DIR_SRC . 'Core/Autoloader.php';
Base3\Core\Autoloader::register();
(new Base3\Core\PluginClassMap(new Base3\Core\ServiceLocator()))->generate(true);
echo "BASE3 class map regenerated.\n";
