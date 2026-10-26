<?php

declare(strict_types=1);

use Celema\Quma\Connection;
use Celema\Quma\Database;
use Celema\Quma\Delimiters;
use Cosray\Config;
use Cosray\Queue;
use Cosray\User\PasswordResets;
use Cosray\Users;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
$config = new Config($root);
$db = new Database(new Connection(getenv('COSRAY_RESET_DSN'), $root . '/db/sql')->placeholders(
	Delimiters::comments(),
	$config->db->placeholders,
));
$db->execute(file_get_contents(__DIR__ . '/sql/fulltext/worker.sql'), ['name' => $argv[2]])->run();
$users = new Users($db);
$user = $users->byId((int) $argv[1]) ?? throw new RuntimeException("No user {$argv[1]}.");
echo json_encode(['issued' => new PasswordResets($db, $users, $config, new Queue($db))->issue($user) !== null]);
