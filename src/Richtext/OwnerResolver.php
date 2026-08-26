<?php

declare(strict_types=1);

namespace Cosray\Richtext;

use Cosray\Assets\Asset;
use Cosray\Field\Field;
use Cosray\Field\Owner;

/**
 * Production resolver backed by a field owner: assets from the
 * catalog repository, node paths from `url_paths`, locale maps
 * resolved along the owner's locale fallback chain.
 */
final class OwnerResolver implements Resolver
{
	public function __construct(
		private readonly Owner $owner,
	) {}

	public function asset(string $uid): ?Asset
	{
		return $uid === '' ? null : $this->owner->assets()->get($uid);
	}

	public function nodePath(string $uid): ?string
	{
		$map = $this->owner->paths()->map($uid);

		if ($map === []) {
			return null;
		}

		$path = $this->localize($map);

		return is_string($path) ? $path : null;
	}

	public function localize(array $map): mixed
	{
		$locale = $this->owner->locale();

		while ($locale) {
			if (
				($map[$locale->id] ?? null) !== null
				&& $map[$locale->id] !== ''
				&& $map[$locale->id] !== []
			) {
				return $map[$locale->id];
			}

			$locale = $locale->fallback();
		}

		return $map[Field::NEUTRAL_LOCALE] ?? null;
	}
}
