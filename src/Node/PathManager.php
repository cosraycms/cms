<?php

declare(strict_types=1);

namespace Cosray\Node;

use Celemas\Quma\Database;
use Cosray\Exception\RuntimeException;
use Cosray\Locale;
use Cosray\Locales;
use Cosray\Uid;

class PathManager
{
	public function __construct(
		private readonly Uid $uid = new Uid(Uid::ALPHABET_LOWERCASE_WORD_SAFE, 13),
	) {}

	public function path(array $rawData, ?Locale $locale, Locale $requestLocale): string
	{
		$paths = $rawData['paths'];

		if (!$locale) {
			$locale = $requestLocale;
		}

		while ($locale) {
			if (isset($paths[$locale->id])) {
				return $paths[$locale->id];
			}

			$locale = $locale->fallback();
		}

		throw new RuntimeException('No url path found');
	}

	public function persist(
		Database $db,
		array $data,
		int $editor,
		int $nodeId,
		Locales $locales,
	): void {
		$noPathsGiven = true;

		foreach ($data['paths'] ?? [] as $path) {
			if (!$path) {
				continue;
			}

			$noPathsGiven = false;
			break;
		}

		if ($noPathsGiven) {
			$data['paths'] = $data['generatedPaths'];
		}

		$defaultLocale = $locales->getDefault();
		$defaultPath = trim($data['paths'][$defaultLocale->id] ?? '');

		if (!$defaultPath) {
			throw new RuntimeException(sprintf(
				'The URL path for the main language "%s" must be set',
				$defaultLocale->title,
			));
		}

		$currentPaths = array_column($db->nodes->getPaths(['node' => $nodeId])->all(), 'path', 'locale');

		if ($currentPaths) {
			$baseStructure = [];

			foreach ($locales as $locale) {
				$baseStructure[$locale->id] = '';
			}

			$this->saveUrlPaths(
				$db,
				array_merge($baseStructure, $currentPaths),
				$data['paths'],
				$editor,
				$nodeId,
			);
		} else {
			$this->createUrlPaths($db, $data['paths'], $editor, $nodeId);
		}
	}

	private function prepareUrlPath(Database $db, string $path): string
	{
		if (!str_starts_with($path, '/')) {
			$path = '/' . $path;
		}

		$db->nodes->deleteInactivePath(['path' => $path])->run();

		return $path;
	}

	private function createUrlPaths(Database $db, array $paths, int $editor, int $node): void
	{
		$alreadyPersisted = [];

		foreach ($paths as $locale => $path) {
			if (!$path) {
				continue;
			}

			$this->prepareUrlPath($db, $path);

			if (in_array($path, $alreadyPersisted, true)) {
				continue;
			}

			if ($db->nodes->pathExists(['path' => $path])->first()) {
				$path = $path . '-' . $this->uid->generate(5);
			}

			$db->nodes->savePath([
				'node' => $node,
				'path' => $path,
				'locale' => $locale,
				'editor' => $editor,
			])->run();

			$alreadyPersisted[] = $path;
		}
	}

	private function saveUrlPaths(
		Database $db,
		array $currentPaths,
		array $paths,
		int $editor,
		int $node,
	): void {
		$alreadyPersisted = [];

		foreach ($currentPaths as $locale => $currentPath) {
			$newPath = trim($paths[$locale] ?? '');

			if ($newPath) {
				$newPath = $this->prepareUrlPath($db, $newPath);

				if ($currentPath) {
					if ($currentPath === $newPath) {
						$alreadyPersisted[] = $newPath;

						continue;
					}

					$db->nodes->deactivatePath([
						'path' => $currentPath,
						'locale' => $locale,
						'editor' => $editor,
					])->run();
				}

				if (in_array($newPath, $alreadyPersisted, true)) {
					continue;
				}

				if ($db->nodes->pathExists(['path' => $newPath])->first()) {
					$newPath = $newPath . '-' . $this->uid->generate(5);
				}

				$db->nodes->savePath([
					'node' => $node,
					'path' => $newPath,
					'locale' => $locale,
					'editor' => $editor,
				])->run();

				$alreadyPersisted[] = $newPath;
			} else {
				if ($currentPath) {
					$db->nodes->deactivatePath([
						'path' => $currentPath,
						'locale' => $locale,
						'editor' => $editor,
					])->run();
				}
			}
		}
	}
}
