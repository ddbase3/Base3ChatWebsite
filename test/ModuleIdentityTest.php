<?php declare(strict_types=1);

/** Checks the BASE3 module's names, source layout, page targets, and JSON configuration. */
$root = dirname(__DIR__);
$module = 'Base3ChatWebsite';
$manifestPath = $root . '/base3.json';
$manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

$check = static function(bool $condition, string $description): void {
	if (!$condition) {
		fwrite(STDERR, 'FAIL: ' . $description . "\n");
		exit(1);
	}
};

$check($manifest['manifestVersion'] === 1, 'manifestVersion');
$check($manifest['name'] === $module, 'manifest name');
$check($manifest['namespace'] === $module, 'manifest namespace');
$check(is_string($manifest['version']) && $manifest['version'] !== '', 'manifest version');
$check(is_file($root . '/src/' . $module . 'Plugin.php'), 'plugin entry class path');

$src = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src', FilesystemIterator::SKIP_DOTS));
$outputNames = [];
$numPhp = 0;
foreach ($src as $file) {
	if ($file->getExtension() !== 'php') {
		continue;
	}
	++$numPhp;
	$path = $file->getPathname();
	$relative = substr($path, strlen($root . '/src/'));
	$parts = explode('/', $relative);
	$basename = substr(array_pop($parts), 0, -4);
	$namespace = $module . ($parts ? '\\' . implode('\\', $parts) : '');
	$content = (string) file_get_contents($path);
	$check((bool) preg_match('/^namespace\s+' . preg_quote($namespace, '/') . '\s*;/m', $content), 'namespace in ' . $relative);
	$check((bool) preg_match('/^\s*(?:final\s+|abstract\s+)?class\s+' . preg_quote($basename, '/') . '\b/m', $content), 'class in ' . $relative);
	if (preg_match('/public\s+static\s+function\s+getName\s*\(\s*\)\s*:\s*string\s*\{\s*return\s+[\'"]([^\'"]+)[\'"]/s', $content, $match)) {
		$check($match[1] === strtolower($basename), 'class-map name in ' . $relative);
		$outputNames[] = $match[1];
	}
	if (preg_match_all('~plugin/' . $module . '/([a-zA-Z0-9_./-]+)~', $content, $matches)) {
		foreach ($matches[1] as $asset) {
			$check(is_file($root . '/' . $asset), 'referenced file: ' . $asset);
		}
	}
}
$check($numPhp > 0, 'PHP classes present');
$check(in_array('base3chatwebsiteplugin', $outputNames, true), 'plugin class map name');

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/local', FilesystemIterator::SKIP_DOTS));
$numJson = 1;
foreach ($files as $file) {
	if (!str_ends_with($file->getFilename(), '.json') && !str_ends_with($file->getFilename(), '.json.bak')) {
		continue;
	}
	++$numJson;
	$content = json_decode((string) file_get_contents($file->getPathname()), true, 512, JSON_THROW_ON_ERROR);
	if (str_starts_with($file->getFilename(), 'page-')) {
		foreach ($content['pagecontents'] ?? [] as $item) {
			$service = $item['data']['service'] ?? null;
			if ($service !== null) {
				$check(in_array(pathinfo($service, PATHINFO_FILENAME), $outputNames, true), 'page service: ' . $service);
			}
		}
	}
}

echo 'OK: ' . $module . ', ' . $numPhp . ' PHP classes, ' . $numJson . " JSON documents\n";
