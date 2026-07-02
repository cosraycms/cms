<?php

declare(strict_types=1);

namespace Cosray\Collection\Schema;

use Cosray\CollectionListMeta;

class ListingHandler extends Handler
{
	public function resolve(object $meta, string $class): array
	{
		return [
			'listing' => new CollectionListMeta(
				showPublished: $meta->published,
				showLocked: $meta->locked,
				showHidden: $meta->hidden,
				showChildren: $meta->children,
			),
		];
	}
}
