<?php

declare(strict_types=1);

namespace Cosray\Panel\Dashboard;

use Celema\Quma\Database;
use Cosray\Contract\DashboardCard;

final readonly class Changes implements DashboardCard
{
	public function __construct(
		private Database $db,
	) {}

	public function card(): Card
	{
		$row = $this->db->dashboard->changes()->one();
		$recent = (int) ($row['recent'] ?? 0);

		return new Card(
			label: __('dashboard:changes'),
			value: (int) ($row['total'] ?? 0),
			note: __n(
				'dashboard:recent-changes',
				'dashboard:recent-changes-plural',
				$recent,
			),
		);
	}
}
