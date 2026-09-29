<?php

declare(strict_types=1);

namespace Cosray\Panel\Dashboard;

use Celema\Quma\Database;
use Cosray\Collection\AllContent;
use Cosray\Collection\Ref;
use Cosray\Contract\DashboardCard;
use Cosray\Navigation;

final readonly class Entries implements DashboardCard
{
	public function __construct(
		private Database $db,
		private Navigation $navigation,
	) {}

	public function card(): Card
	{
		$row = $this->db->dashboard->entries()->one();
		// The built-in overview lists everything the others do, so it is not
		// a collection of its own here.
		$collections = count(array_filter(
			$this->navigation->refs(),
			static fn(Ref $ref): bool => $ref->class !== AllContent::class,
		));

		return new Card(
			label: __('dashboard:entries'),
			value: (int) ($row['total'] ?? 0),
			note: __n(
				'dashboard:collection-count',
				'dashboard:collection-count-plural',
				$collections,
			),
		);
	}
}
