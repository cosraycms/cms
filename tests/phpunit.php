<?php

declare(strict_types=1);

/*
 * Runs PHPUnit with the arguments given. A loaded Xdebug slows every test,
 * so it stays off unless it has to measure coverage: PCOV does when loaded.
 */

$root = dirname(__DIR__);
$args = array_slice($argv, 1);
$coverage = !in_array('--no-coverage', $args, true);
$pcov = extension_loaded('pcov');
// The variable outranks the ini setting, also one CI sets.
putenv('XDEBUG_MODE=' . ($coverage && !$pcov ? 'coverage' : 'off'));
$ini = $coverage && $pcov ? ['-d', 'pcov.enabled=1'] : [];
$process = proc_open(
	[PHP_BINARY, ...$ini, $root . '/vendor/bin/phpunit', ...$args],
	[STDIN, STDOUT, STDERR],
	$pipes,
	$root,
);

exit($process === false ? 1 : proc_close($process));
