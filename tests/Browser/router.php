<?php

declare(strict_types=1);

// The web server of the browser tests: files first, like a real one, then the app.
$file = __DIR__ . '/.run/public' . rawurldecode((string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH));

if (is_file($file)) {
	return false;
}

return (require __DIR__ . '/boot.php')->serve();
