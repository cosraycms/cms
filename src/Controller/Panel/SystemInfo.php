<?php

declare(strict_types=1);

namespace Cosray\Controller\Panel;

use Celema\Container\Container;
use Celema\Core\Request;
use Cosray\Config;
use Cosray\Middleware\Permission;
use Cosray\SystemInfo as Report;
use Cosray\SystemInfo\Text;

final class SystemInfo extends Panel
{
	protected const string AREA = 'system';

	public function __construct(
		Config $config,
		Container $container,
		Request $request,
		private readonly Report $report,
	) {
		parent::__construct($config, $container, $request);
	}

	/** @return array<string, mixed> */
	#[Permission('view-system-info')]
	public function index(): array
	{
		$url = $this->panelPath() . '/system-info';
		$tab = $this->request->param('tab', '') === 'php' ? 'php' : 'overview';
		$tabs = [
			['label' => __('system-info:overview'), 'url' => $url, 'active' => $tab === 'overview'],
			['label' => 'PHP', 'url' => $url . '?tab=php', 'active' => $tab === 'php'],
		];

		if ($tab === 'php') {
			$search = $this->request->param('q', '');
			$search = is_string($search) ? trim($search) : '';
			$matches = static fn(array $row): bool => (
				$search === ''
				|| stripos((string) $row['name'], $search) !== false
			);
			$php = $this->report->php();

			return $this->context([
				'tab' => $tab,
				'tabs' => $tabs,
				'url' => $url,
				'search' => $search,
				'directives' => array_values(array_filter($php['directives'], $matches)),
				'extensions' => array_values(array_filter($php['extensions'], $matches)),
			]);
		}

		$text = new Text($this->formatLocale());
		$groups = [];
		$problems = ['fail' => [], 'warn' => []];

		foreach ($this->report->report()['groups'] as $group) {
			$entries = [];

			foreach ($group['entries'] as $entry) {
				$row = [
					'label' => $text->label($entry['key']),
					'value' => $text->value($entry),
					'status' => $entry['status'],
					'hint' => $text->hint($entry),
				];
				$entries[] = $row;

				if ($row['status'] === 'fail' || $row['status'] === 'warn') {
					$problems[$row['status']][] = $row;
				}
			}

			$groups[] = ['title' => $text->group($group['key']), 'entries' => $entries];
		}

		return $this->context([
			'tab' => $tab,
			'tabs' => $tabs,
			'url' => $url,
			'groups' => $groups,
			'failures' => $problems['fail'],
			'warnings' => $problems['warn'],
		]);
	}
}
