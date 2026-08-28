<?php

declare(strict_types=1);

namespace Cosray\Panel\Dashboard;

use Celema\Core\Request;
use Celema\Quma\Database;
use Cosray\Config;
use Cosray\Contract\DashboardCard;
use NumberFormatter;

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
				'size' => $this->humanSize((int) ($row['bytes'] ?? 0)),
			]),
			url: $this->config->panel->path . '/media',
		);
	}

	private function humanSize(int $bytes): string
	{
		$units = ['B', 'KB', 'MB', 'GB', 'TB'];
		$size = (float) max(0, $bytes);
		$unit = 0;

		while ($size >= 1024 && $unit < (count($units) - 1)) {
			$size /= 1024;
			$unit++;
		}

		$locale = $this->request->get('panelLocale', 'en');
		$formatter = new NumberFormatter(is_string($locale) ? $locale : 'en', NumberFormatter::DECIMAL);
		$digits = $unit === 0 ? 0 : 1;
		$formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $digits);
		$formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $digits);
		$value = $formatter->format($size);

		return ($value === false ? (string) $size : $value) . ' ' . $units[$unit];
	}
}
