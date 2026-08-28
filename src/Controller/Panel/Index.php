<?php

declare(strict_types=1);

namespace Cosray\Controller\Panel;

use Celema\Core\Request;
use Celema\Quma\Database;
use Celema\Wire\Creator;
use Cosray\Bootstrap;
use Cosray\Context;
use Cosray\Contract\DashboardCard;
use Cosray\Node\Types;
use Cosray\Panel\Dashboard;
use Cosray\Title\Resolver as TitleResolver;
use DateTimeImmutable;
use IntlDateFormatter;
use NumberFormatter;

final class Index extends Panel
{
	private const int RECENT_LIMIT = 6;

	protected const string AREA = 'dashboard';

	public function index(
		Context $context,
		Database $db,
		Dashboard $dashboard,
		Types $types,
	): array {
		return $this->context([
			'cards' => $this->cards($context, $dashboard),
			'recent' => $this->recent($context, $db, $types),
		]);
	}

	/** @return list<array{label: string, value: string, note: ?string, url: ?string}> */
	private function cards(Context $context, Dashboard $dashboard): array
	{
		$creator = new Creator($this->container);
		$cards = [];
		$formatter = new NumberFormatter($this->localeId(), NumberFormatter::DECIMAL);
		$formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 0);
		$formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 0);

		foreach ($dashboard->cards() as $definition) {
			$provider = is_string($definition)
				? $creator->create($definition, predefinedTypes: [
					Context::class => $context,
					Request::class => $this->request,
				])
				: $definition;
			assert($provider instanceof DashboardCard, 'The dashboard registry must contain card providers');
			$card = $provider->card();

			if ($card === null) {
				continue;
			}

			$value = is_int($card->value) ? (string) $formatter->format($card->value) : $card->value;
			$cards[] = [
				'label' => $card->label,
				'value' => $value,
				'note' => $card->note,
				'url' => $card->url,
			];
		}

		return $cards;
	}

	/**
	 * @return list<array{
	 *     uid: string,
	 *     title: string,
	 *     type: string,
	 *     changed: string,
	 *     datetime: string,
	 *     published: bool,
	 *     status: string,
	 * }>
	 */
	private function recent(Context $context, Database $db, Types $types): array
	{
		$labels = $this->typeLabels($types);
		$titles = new TitleResolver($types);
		$date = new IntlDateFormatter(
			$this->localeId(),
			IntlDateFormatter::MEDIUM,
			IntlDateFormatter::NONE,
			$this->config->app->timezone->getName(),
		);
		$recent = [];

		foreach ($db->dashboard->recent(['limit' => self::RECENT_LIMIT])->all() as $row) {
			$uid = (string) ($row['uid'] ?? '');
			$map = $row['title'] ?? [];

			if (is_string($map)) {
				$map = json_decode($map, true);
			}

			$title = is_array($map) ? $titles->stored($map, $context->locale()) : null;
			$changed = new DateTimeImmutable((string) ($row['changed'] ?? 'now'));
			$formatted = $date->format($changed->getTimestamp());
			$published = (bool) ($row['published'] ?? false);
			$type = (string) ($row['type'] ?? '');
			$recent[] = [
				'uid' => $uid,
				'title' => $title ?? $uid,
				'type' => $labels[$type] ?? $type,
				'changed' => $formatted === false ? '' : $formatted,
				'datetime' => $changed->format(DATE_ATOM),
				'published' => $published,
				'status' => $published ? __('status:published') : __('status:draft'),
			];
		}

		return $recent;
	}

	/** @return array<string, string> */
	private function typeLabels(Types $types): array
	{
		$registered = $this->container->tag(Bootstrap::NODE_TAG);
		$labels = [];

		foreach ($registered->entries() as $handle) {
			$class = $registered->entry($handle)->definition();
			assert(is_string($class) && class_exists($class), 'Registered node types must be existing classes');
			$labels[$handle] = __((string) $types->get($class, 'label', $handle));
		}

		return $labels;
	}
}
