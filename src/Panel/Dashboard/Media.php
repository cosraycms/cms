<?php

declare(strict_types=1);

namespace Cosray\Panel\Dashboard;

use Celema\Core\Request;
use Celema\Quma\Database;
use Cosray\Config;
use Cosray\Contract\DashboardCard;
use Cosray\Panel\AssetFacts;

final readonly class Media implements DashboardCard
{
	public function __construct(
		private Database $db,
		private Config $config,
		private Request $request,
	) {}

	public function card(): Card
	{
		$row = $this->db->dashboard->media()->one();

		return new Card(
			label: __('dashboard:media'),
			value: (int) ($row['total'] ?? 0),
			note: __('dashboard:storage-used', [
				'size' => AssetFacts::size((int) ($row['bytes'] ?? 0), $this->locale()),
			]),
			url: $this->config->panel->path . '/media',
		);
	}

	private function locale(): string
	{
		$locale = $this->request->get('panelLocale', 'en');

		return is_string($locale) ? $locale : 'en';
	}
}
