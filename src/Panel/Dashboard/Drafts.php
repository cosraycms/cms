<?php

declare(strict_types=1);

namespace Cosray\Panel\Dashboard;

use Celema\Quma\Database;
use Cosray\Contract\DashboardCard;

final readonly class Drafts implements DashboardCard
{
	public function __construct(
		private Database $db,
	) {}

	public function card(): Card
	{
		$row = $this->db->dashboard->drafts()->one();
		$recent = (int) ($row['recent'] ?? 0);

		return new Card(
			label: __('dashboard:drafts'),
			value: (int) ($row['total'] ?? 0),
			note: __n(
				'dashboard:recent-drafts',
				'dashboard:recent-drafts-plural',
				$recent,
			),
		);
	}
}
