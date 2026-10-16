<?php

declare(strict_types=1);

namespace Cosray\Collection\Schema;

use Cosray\CollectionListMeta;
use Cosray\Schema\Listing;
use Override;

class ListingHandler extends Handler
{
	#[Override]
	public function resolve(object $meta, string $class): array
	{
		assert($meta instanceof Listing, 'The registry maps #[Listing] to this handler');

		return [
			'listing' => new CollectionListMeta(
				showPublished: $meta->published,
				showLocked: $meta->locked,
				showHidden: $meta->hidden,
				showChildren: $meta->children,
				sortable: $meta->sortable,
			),
			'search' => $meta->search,
		];
	}
}
