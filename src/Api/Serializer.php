<?php

declare(strict_types=1);

namespace Cosray\Api;

use Cosray\Context;
use Cosray\DateTime\Codec;
use Cosray\Node\Factory;
use Cosray\Node\Types;
use Cosray\Node\Wrapper;
use DateTimeImmutable;
use stdClass;

/**
 * Nodes as the API delivers them. Both views share the node's identity and
 * state; the resolved view adds `fields`, every value resolved for the
 * request locale through `Value::json()`, the source view adds `content`,
 * the stored values of every locale without the field class.
 */
final class Serializer
{
	public function __construct(
		private readonly Context $context,
		private readonly Types $types,
	) {}

	/**
	 * @param ?list<string> $fields only these fields; null for all, unknown names are skipped
	 * @return array<string, mixed>
	 */
	public function node(object $node, bool $source = false, ?array $fields = null): array
	{
		$inner = Wrapper::unwrap($node);
		$data = Factory::dataFor($inner);
		$names = Factory::fieldNamesFor($inner);

		if ($fields !== null) {
			$names = array_values(array_intersect($names, $fields));
		}

		$paths = is_array($data['paths'] ?? null) ? $data['paths'] : [];
		$wrapper = $node instanceof Wrapper ? $node : null;
		$result = [
			'uid' => (string) $data['uid'],
			'type' => (string) ($data['type_handle'] ?? $this->types->get($inner::class, 'handle')),
			'handle' => $data['handle'] ?? null,
			'parent' => $data['parent'] ?? null,
			'locale' => $this->context->localeId(),
			'title' => $wrapper?->title() ?? '',
			'path' => $this->path($paths),
			'paths' => $paths === [] ? new stdClass() : $paths,
			'published' => (bool) $data['published'],
			'hidden' => (bool) $data['hidden'],
			'created' => self::instant($data['created'] ?? null),
			'changed' => self::instant($data['changed'] ?? null),
			'deleted' => self::instant($data['deleted'] ?? null),
		];

		if ($source) {
			$result['content'] = self::object($this->content($inner, $data, $names));
		} else {
			$result['fields'] = self::object($this->fields($inner, $names));
		}

		return $result;
	}

	/**
	 * @param list<string> $names
	 * @return array<string, mixed>
	 */
	private function fields(object $node, array $names): array
	{
		$fields = [];

		foreach ($names as $name) {
			$fields[$name] = Factory::fieldFor($node, $name)->value()->json();
		}

		return $fields;
	}

	/**
	 * @param array<mixed> $data
	 * @param list<string> $names
	 * @return array<string, mixed>
	 */
	private function content(object $node, array $data, array $names): array
	{
		$content = [];

		foreach ($names as $name) {
			$stored = $data['content'][$name] ?? [];
			$value = array_merge(Factory::fieldFor($node, $name)->structure(), is_array($stored) ? $stored : []);
			unset($value['type']);
			$content[$name] = $value;
		}

		return $content;
	}

	/** @param array<mixed> $paths */
	private function path(array $paths): ?string
	{
		$locale = $this->context->locale();

		while ($locale) {
			$path = $paths[$locale->id] ?? null;

			if (is_string($path) && $path !== '') {
				return $path;
			}

			$locale = $locale->fallback();
		}

		return null;
	}

	private static function instant(mixed $value): ?string
	{
		return is_string($value) && $value !== '' ? Codec::format(new DateTimeImmutable($value)) : null;
	}

	/**
	 * @param array<string, mixed> $map
	 * @return array<string, mixed>|stdClass
	 */
	private static function object(array $map): array|stdClass
	{
		return $map === [] ? new stdClass() : $map;
	}
}
