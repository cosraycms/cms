<?php

declare(strict_types=1);

namespace Cosray\Schema;

use Attribute;

/**
 * Configures a Reference field's pickable node set (read by the search
 * endpoint). `types` restricts the node type — the common case, so it is
 * positional; pass one class-string/handle or an array for several.
 * `where` narrows further with the finder DSL (`=`/`&`/`|`), e.g.
 * "productLine = 'klassiker'". `published`/`hidden` override the pickable
 * gates (default: any publication, hidden included); soft-deleted nodes
 * are never pickable.
 *
 *   #[Pick(Beer::class, where: "productLine = 'klassiker'")]
 *   #[Pick([Beer::class, Wine::class], published: true)]
 *
 * Use the typed params for the node gates and `where` for content-field
 * predicates; they compose with AND, so don't split one across both.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Pick
{
	/** @var list<string> */
	public array $types;

	/** @param string|list<string> $types */
	public function __construct(
		string|array $types = [],
		public string $where = '',
		public ?bool $published = null,
		public ?bool $hidden = null,
	) {
		$this->types = array_values(array_filter(
			is_array($types) ? $types : [$types],
			static fn(string $type): bool => $type !== '',
		));
	}
}
