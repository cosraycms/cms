<?php

declare(strict_types=1);

namespace Cosray\View\Boiler\Error;

use Celema\Core\Error\Renderer as RendererInterface;
use Cosray\Exception\UnderMaintenance;
use Override;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface as Logger;
use Throwable;

/**
 * Renders the `maintenance` template: the site's, where its error views have
 * one, else Cosray's. A site template that fails falls back to Cosray's, as
 * the database it may read can be in the middle of a migration.
 *
 * @phpstan-import-type DirsInput from \Celema\Boiler\Engine
 */
final class MaintenanceRenderer implements RendererInterface
{
	private const string TEMPLATE = 'maintenance';
	private const array LANGUAGES = ['en', 'de'];

	/**
	 * @param DirsInput $dirs the site's error views, then Cosray's
	 * @param non-empty-string $builtin Cosray's own error views
	 * @param array<string, mixed> $context
	 * @param list<class-string> $trusted
	 */
	public function __construct(
		private readonly string|array $dirs,
		private readonly string $builtin,
		private readonly Logger $logger,
		private readonly array $context = [],
		private readonly array $trusted = [],
	) {}

	#[Override]
	public function render(Throwable $exception, ResponseFactory $factory, Request $request, bool $debug): Response
	{
		$context = [...$this->context, ...$this->values($exception, $request)];

		try {
			return new Renderer(self::TEMPLATE, $this->dirs, $context, $this->trusted)->render(
				$exception,
				$factory,
				$request,
				$debug,
			);
		} catch (Throwable $e) {
			$this->logger->warning("The site's maintenance template failed, Cosray's is shown instead: {message}", [
				'message' => $e->getMessage(),
				'exception' => $e,
			]);

			return new Renderer(self::TEMPLATE, $this->builtin, $context, $this->trusted)->render(
				$exception,
				$factory,
				$request,
				$debug,
			);
		}
	}

	/** @return array{message: ?string, retry: int, since: ?string, full: bool, lang: string} */
	private function values(Throwable $exception, Request $request): array
	{
		$payload = $exception instanceof UnderMaintenance ? $exception->payload() : null;
		$payload = is_array($payload) ? $payload : [];
		$message = $payload['message'] ?? null;
		$since = $payload['since'] ?? null;

		return [
			'message' => is_string($message) && $message !== '' ? $message : null,
			'retry' => $exception instanceof UnderMaintenance ? $exception->retry() : 60,
			'since' => is_string($since) && $since !== '' ? $since : null,
			'full' => ($payload['full'] ?? false) === true,
			'lang' => self::language($request->getHeaderLine('Accept-Language')),
		];
	}

	/** The first of Cosray's own languages the client accepts, by weight; English otherwise. */
	private static function language(string $header): string
	{
		$weights = [];

		foreach (explode(',', $header) as $position => $part) {
			$pieces = explode(';', trim($part));
			$language = strtolower(explode('-', trim($pieces[0]))[0]);
			$weight = 1.0;

			foreach (array_slice($pieces, 1) as $parameter) {
				if (preg_match('/^\s*q\s*=\s*([0-9.]+)\s*$/', $parameter, $match) === 1) {
					$weight = (float) $match[1];
				}
			}

			if (in_array($language, self::LANGUAGES, true) && $weight > 0 && !isset($weights[$language])) {
				// The position breaks ties, so earlier languages of equal weight win.
				$weights[$language] = $weight - ($position / 1000);
			}
		}

		arsort($weights);

		return array_key_first($weights) ?? 'en';
	}
}
