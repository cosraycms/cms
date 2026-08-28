<?php

declare(strict_types=1);

namespace Cosray\Panel;

use Traversable;

final class CollectionParent
{
	/** @param list<array{kind: string, label: string}> $status */
	private function __construct(
		public readonly string $uid,
		public readonly ?string $title,
		public readonly ?string $type,
		public readonly array $status,
		public readonly string $rootUrl,
		public readonly string $editUrl,
		public readonly string $treeUrl,
	) {}

	public static function from(
		CollectionUrls $urls,
		?string $title,
		?string $type,
		iterable $status,
	): ?self {
		$uid = $urls->query->parent;

		if ($uid === null) {
			return null;
		}

		return new self(
			uid: $uid,
			title: self::label($title),
			type: self::label($type),
			status: self::status($status),
			rootUrl: $urls->collection([
				'parent' => '',
				'offset' => '',
				'view' => '',
				'open' => '',
			]),
			editUrl: $urls->edit($uid),
			treeUrl: $urls->showInTree($uid),
		);
	}

	private static function label(?string $label): ?string
	{
		$label = trim((string) $label);

		return $label === '' ? null : $label;
	}

	/** @return list<array{kind: string, label: string}> */
	private static function status(iterable $status): array
	{
		$badges = [];

		foreach ($status as $badge) {
			$badge = self::arrayFrom($badge);
			$kind = trim((string) ($badge['kind'] ?? ''));
			$label = trim((string) ($badge['label'] ?? ''));

			if ($kind === '' || $label === '') {
				continue;
			}

			$badges[] = [
				'kind' => $kind,
				'label' => $label,
			];
		}

		return $badges;
	}

	/** @return array<array-key, mixed> */
	private static function arrayFrom(mixed $value): array
	{
		if ($value instanceof Traversable) {
			return iterator_to_array($value);
		}

		return is_array($value) ? $value : [];
	}
}
