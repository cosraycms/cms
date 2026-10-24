<?php

declare(strict_types=1);

namespace Cosray\Api\Controller;

use Celema\Core\Exception\HttpNotFound;
use Celema\Core\Request;
use Cosray\Api\Responses;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Exception\RuntimeException;
use Cosray\Finder\MenuItem;
use Psr\Http\Message\ResponseInterface as Response;

/** Menus as the website renders them: hidden items left out, `children` items expanded. */
final class Menus
{
	public function __construct(
		private readonly Responses $responses,
	) {}

	public function list(Context $context): Response
	{
		$items = [];

		foreach ($context->db->menus->list()->all() as $row) {
			$description = json_decode((string) ($row['description'] ?? 'null'), true);
			$items[] = [
				'handle' => $row['menu'],
				'description' => is_array($description) ? $this->localized($context, $description) : $description,
			];
		}

		return $this->responses->json(['items' => $items]);
	}

	public function menu(Request $request, Cms $cms, string $menu): Response
	{
		try {
			$items = iterator_to_array($cms->menu($menu), false);
		} catch (RuntimeException $e) {
			throw new HttpNotFound($request, payload: ['message' => "There is no menu '{$menu}'."], previous: $e);
		}

		return $this->responses->json([
			'handle' => $menu,
			'items' => array_map($this->item(...), $items),
		]);
	}

	/** @return array<string, mixed> */
	private function item(MenuItem $item): array
	{
		return [
			'id' => $item->id(),
			'type' => $item->type(),
			'title' => $item->title(),
			'href' => $item->href(),
			'node' => $item->node(),
			'target' => $item->target(),
			'image' => $item->image(),
			'class' => $item->class(),
			'children' => array_map($this->item(...), iterator_to_array($item->children(), false)),
		];
	}

	/** @param array<array-key, mixed> $map */
	private function localized(Context $context, array $map): ?string
	{
		$locale = $context->locale();

		while ($locale) {
			if (is_string($map[$locale->id] ?? null) && $map[$locale->id] !== '') {
				return $map[$locale->id];
			}

			$locale = $locale->fallback();
		}

		return is_string($map['zxx'] ?? null) ? $map['zxx'] : null;
	}
}
