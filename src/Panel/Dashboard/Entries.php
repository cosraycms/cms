<?php

declare(strict_types=1);

namespace Cosray\Panel\Dashboard;

use Celema\Quma\Database;
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
		$collections = count($this->navigation->refs());

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
