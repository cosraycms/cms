<?php

declare(strict_types=1);

namespace Cosray;

use Cosray\Exception\RuntimeException;
use Cosray\Field\Services;
use Cosray\Finder\Menu;
use Cosray\Finder\Node;
use Cosray\Finder\Nodes;
use Cosray\Finder\Render;
use Cosray\Node\Factory;
use Cosray\Node\Types;

/**
 * @property-read Nodes $nodes
 * @property-read Node $node
 */
class Cms
{
	private readonly Factory $nodeFactory;
	private readonly Types $types;

	public function __construct(
		private readonly Context $context,
		Services $services,
	) {
		$uid = $context->config->uid;
		$this->types = $services->types;
		$this->nodeFactory = new Factory(
			$context->container,
			$services,
			new Uid($uid->alphabet, $uid->length),
		);
	}

	public function __get($key): Nodes|Node|Menu
	{
		return match ($key) {
			'nodes' => new Nodes($this->context, $this, $this->nodeFactory, $this->types),
			'node' => new Node($this->context, $this, $this->nodeFactory, $this->types),
			default => throw new RuntimeException('Property not supported'),
		};
	}

	public function nodes(
		string $query = '',
	): Nodes {
		return new Nodes($this->context, $this, $this->nodeFactory, $this->types)->filter($query);
	}

	public function node(
		string $query,
		array $types = [],
		int $limit = 0,
		string $order = '',
	): array {
		$finder = new Nodes($this->context, $this, $this->nodeFactory, $this->types)->filter($query);

		if ($types !== []) {
			$finder->types(...$types);
		}

		if ($order !== '') {
			$finder->order($order);
		}

		if ($limit > 0) {
			$finder->limit($limit);
		}

		return iterator_to_array($finder);
	}

	public function menu(string $menu): Menu
	{
		return new Menu($this->context, $menu, $this);
	}

	public function render(
		string $id,
		array $templateContext = [],
		?bool $deleted = false,
		?bool $published = true,
	): Render {
		return new Render(
			$this->context,
			$this,
			$this->nodeFactory,
			$this->types,
			$id,
			$templateContext,
			$deleted,
			$published,
		);
	}

	/**
	 * The script tag for the dev server's live reload, or an empty string.
	 * `celema/server` only sets the script URL while running with
	 * `--watch`, so layouts can include it unconditionally.
	 *
	 * The URL gets the host the page was requested under, so the script
	 * also loads on other devices, in virtual machines, and under local
	 * domain names, as long as the dev server listens there.
	 */
	public function liveReload(): string
	{
		$url = getenv('CELEMA_LIVE_RELOAD');

		if (!is_string($url) || $url === '') {
			return '';
		}

		$host = $this->context->request?->uri()->getHost() ?? '';
		$url = $host === '' ? $url : self::withHost($url, $host);

		return '<script src="' . htmlspecialchars($url, ENT_QUOTES) . '" defer></script>';
	}

	private static function withHost(string $url, string $host): string
	{
		$parts = parse_url($url);

		if ($parts === false) {
			return $url;
		}

		if (str_contains($host, ':') && !str_starts_with($host, '[')) {
			$host = "[{$host}]";
		}

		return (
			($parts['scheme'] ?? 'http')
				. "://{$host}"
				. (isset($parts['port']) ? ":{$parts['port']}" : '')
				. ($parts['path'] ?? '')
				. (isset($parts['query']) ? "?{$parts['query']}" : '')
		);
	}

	public function nodeFactory(): Factory
	{
		return $this->nodeFactory;
	}
}
