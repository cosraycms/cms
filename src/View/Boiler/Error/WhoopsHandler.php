<?php

declare(strict_types=1);

namespace Cosray\View\Boiler\Error;

use Celemas\Core\Error\DebugHandler;
use Override;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use RuntimeException;
use Throwable;

final class WhoopsHandler implements DebugHandler
{
	public static function available(): bool
	{
		return class_exists(\Whoops\Run::class) && class_exists(\Whoops\Handler\PrettyPageHandler::class);
	}

	#[Override]
	public function handle(Throwable $exception, ResponseFactory $factory): Response
	{
		if (!self::available()) {
			throw new RuntimeException('Install filp/whoops to use the CMS Whoops debug handler.');
		}

		$whoops = new \Whoops\Run();
		$whoops->allowQuit(false);
		$whoops->writeToOutput(false);
		$whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler());

		$response = $factory->createResponse()->withStatus(500)->withHeader('Content-type', 'text/html');
		$response->getBody()->write($whoops->handleException($exception));

		return $response;
	}
}
