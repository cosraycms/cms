<?php

declare(strict_types=1);

use Celema\Quma\Connection;
use Celema\Quma\Database;
use Celema\Quma\Delimiters;
use Cosray\Config;
use Cosray\Queue;
use Cosray\User\PasswordResets;
use Cosray\Users;

// Usage: password-reset.php <session name> issue <user id> | complete <token>
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
$config = new Config($root);
$db = new Database(new Connection(getenv('COSRAY_RESET_DSN'), $root . '/db/sql')->placeholders(
	Delimiters::comments(),
	$config->db->placeholders,
));
$db->execute(file_get_contents(__DIR__ . '/sql/fulltext/worker.sql'), ['name' => $argv[1]])->run();
$users = new Users($db);
$resets = new PasswordResets($db, $users, $config, new Queue($db));

echo
	json_encode(match ($argv[2]) {
		'issue' => $resets->issue($users->byId((int) $argv[3]) ?? throw new RuntimeException("No user {$argv[3]}."))
			!== null,
		'complete' => $resets->complete($argv[3], 'a concurrent new password') !== null,
	});
