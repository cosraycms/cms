<?php

declare(strict_types=1);

namespace Cosray\Text;

use Cosray\Cms;
use Cosray\Context;
use Cosray\Field\Field;
use Cosray\Finder\Nodes;
use Cosray\Node\Factory;
use Cosray\Node\Types;
use Cosray\Node\Wrapper;
use Cosray\Title\Resolver;
use Generator;

/**
 * The texts of the nodes a finder query selects, each with its node's
 * title and path in the text's locale:
 *
 *     foreach ($texts->of($cms->nodes->types('news')->published(true)) as $text) { … }
 *
 * It follows field classes, `#[When]`, `#[Allows]`, block and entry rows
 * and rich text, and reads nodes one at a time. It checks no permissions:
 * each caller checks them for its user.
 *
 * @api
 */
final class Texts
{
	private readonly Walker $walker;
	private readonly Resolver $titles;

	public function __construct(
		private readonly Context $context,
		Cms $cms,
		private readonly Types $types,
	) {
		$this->walker = new Walker($cms->nodeFactory()->hydrator()->services()->blocks);
		$this->titles = new Resolver($types);
	}

	/** @return Generator<int, Text> */
	public function of(Nodes $nodes): Generator
	{
		foreach ($nodes as $node) {
			yield from $this->node($node);
		}
	}

	/**
	 * The texts of one loaded node, as its content stands in the loaded
	 * version: the working copy when it was loaded with it.
	 *
	 * @return list<Text>
	 */
	public function node(object $node): array
	{
		$node = Wrapper::unwrap($node);

		return $this->content($node, self::map(Factory::meta($node, 'content')));
	}

	/**
	 * The texts of a node with other content than it was loaded with.
	 *
	 * @internal
	 *
	 * @param array<mixed> $content
	 * @return list<Text>
	 */
	public function content(object $node, array $content): array
	{
		$class = $node::class;
		$uid = (string) Factory::meta($node, 'uid');
		$titles = self::map(Factory::meta($node, 'title'));

		// Titles are materialized by saves and `db:titles`; imported content may lack them.
		if ($titles === []) {
			$field = $this->titles->writableField($class);
			$titles = $field === null ? [] : $this->titles->fieldMap($content, $field);
		}

		$paths = self::map(Factory::meta($node, 'paths'));
		$type = (string) $this->types->get($class, 'handle');
		$default = $this->context->locales()->getDefault()->id;
		$texts = [];

		foreach ($this->walker->texts($uid, $class, $content) as $found) {
			$locale = $found['locale'] === Field::NEUTRAL_LOCALE ? $default : $found['locale'];
			$texts[] = new Text(
				id: $found['id'],
				node: $uid,
				type: $type,
				title: self::pick($titles, $locale, $default),
				path: self::pick($paths, $locale, $default),
				field: $found['field'],
				locale: $found['locale'],
				pointer: $found['pointer'],
				value: $found['text'],
			);
		}

		return $texts;
	}

	/** @return array<mixed> */
	private static function map(mixed $value): array
	{
		if (is_string($value)) {
			$value = json_decode($value, true);
		}

		return is_array($value) ? $value : [];
	}

	/** @param array<mixed> $map */
	private static function pick(array $map, string $locale, string $default): ?string
	{
		foreach ([$locale, Field::NEUTRAL_LOCALE, $default] as $key) {
			if (is_string($map[$key] ?? null) && $map[$key] !== '') {
				return $map[$key];
			}
		}

		$first = array_find($map, static fn(mixed $value): bool => is_string($value) && $value !== '');

		return is_string($first) ? $first : null;
	}
}
