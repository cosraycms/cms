<?php

declare(strict_types=1);

// Gives the browser tests a fresh database of their own and the files they upload.

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$setting = static fn(string $key, string $default): string => getenv($key) ?: $default;
$host = $setting('COSRAY_DB_HOST', 'localhost');
$user = $setting('COSRAY_DB_USER', 'cosray');
$password = $setting('COSRAY_DB_PASSWORD', 'cosray');
$database = $setting('COSRAY_BROWSER_DB', 'cosray_browser');
$run = __DIR__ . '/.run';

$remove = static function (string $dir) use (&$remove): void {
	foreach (is_dir($dir) ? (scandir($dir) ?: []) : [] as $entry) {
		if ($entry !== '.' && $entry !== '..') {
			$path = "{$dir}/{$entry}";
			is_dir($path) && !is_link($path) ? $remove($path) : unlink($path);
		}
	}

	if (is_dir($dir)) {
		rmdir($dir);
	}
};
$remove($run);
mkdir("{$run}/public/assets", 0o755, true);
mkdir("{$run}/public/cache", 0o755, true);
mkdir("{$run}/files", 0o755, true);

$admin = new PDO(
	"pgsql:host={$host};dbname=" . $setting('COSRAY_DB_NAME', 'cosray'),
	$user,
	$password,
	[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);
$admin->exec("DROP DATABASE IF EXISTS {$database} WITH (FORCE)");
$admin->exec("CREATE DATABASE {$database}");
unset($admin);

$cms = dirname(__DIR__, 2);

foreach (['--namespace=install --apply', '--apply'] as $arguments) {
	$output = [];
	$command = sprintf(
		'cd %s && COSRAY_DB_NAME=%s php ./run db:migrations %s 2>&1',
		escapeshellarg($cms),
		escapeshellarg($database),
		$arguments,
	);
	exec($command, $output, $code);

	if ($code !== 0) {
		fwrite(STDERR, implode("\n", $output) . "\n");
		exit(1);
	}
}

$db = new PDO(
	"pgsql:host={$host};dbname={$database}",
	$user,
	$password,
	[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);
$db->prepare(
	"INSERT INTO cms.users (uid, username, email, password, roles, active, data, creator, editor)
	VALUES ('browsertester', 'tester', 'tester@example.com', :password, ARRAY['admin'], true, '{\"name\": \"Tester\"}', 1, 1)",
)->execute(['password' => password_hash('browser-tests-password', PASSWORD_ARGON2ID)]);
$type = (int) $db->query("INSERT INTO cms.types (handle) VALUES ('media-page') RETURNING type")->fetchColumn();

foreach (['focal', 'crop', 'texts', 'strict', 'gallery'] as $name) {
	$node = $db->prepare(
		'INSERT INTO cms.nodes (uid, published, hidden, locked, type, creator, editor, content, title)
		VALUES (:uid, true, false, false, :type, 1, 1, :content, :title) RETURNING node',
	);
	$node->execute([
		'uid' => "browser{$name}",
		'type' => $type,
		'content' => json_encode(['title' => ['type' => 'text', 'value' => ['zxx' => ucfirst($name)]]]),
		'title' => json_encode(['en' => ucfirst($name)]),
	]);
	$db->prepare("INSERT INTO cms.url_paths (node, path, locale, creator, editor) VALUES (:node, :path, 'en', 1, 1)")
		->execute(['node' => (int) $node->fetchColumn(), 'path' => "/{$name}"]);
}

/** Four colored quarters, so a cut shows in the pixels. */
$photo = static function (string $file, int $width, int $height, int $tint = 0): void {
	$image = imagecreatetruecolor($width, $height);
	$halfX = intdiv($width, 2);
	$halfY = intdiv($height, 2);
	imagefilledrectangle($image, 0, 0, $halfX, $halfY, 0xFF0000 + $tint);
	imagefilledrectangle($image, $halfX, 0, $width, $halfY, 0x00FF00 + $tint);
	imagefilledrectangle($image, 0, $halfY, $halfX, $height, 0x0000FF);
	imagefilledrectangle($image, $halfX, $halfY, $width, $height, 0xFFFFFF);
	imagejpeg($image, $file, 90);
};
$photo("{$run}/files/landscape.jpg", 1200, 600);
$photo("{$run}/files/portrait.jpg", 600, 1200, 10);

for ($i = 1; $i <= 12; $i++) {
	$photo("{$run}/files/tile-{$i}.jpg", 800, 600, $i * 20);
}
