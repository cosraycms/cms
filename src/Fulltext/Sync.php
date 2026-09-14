<?php

declare(strict_types=1);

namespace Cosray\Fulltext;

use Celema\Quma\Database;
use Cosray\Exception\RuntimeException;
use Cosray\Locales;
use Throwable;

final class Sync
{
	private readonly Configurations $configurations;

	public function __construct(
		private readonly Database $db,
		private readonly Builder $builder,
	) {
		$this->configurations = new Configurations($db);
	}

	public function validate(Locales $locales): void
	{
		$this->configurations->load($locales);
	}

	/**
	 * The caller must hold the node's row lock until the transaction ends.
	 *
	 * @param class-string $class
	 * @return array{indexed: int, missingTitles: int}
	 */
	public function replace(
		int $node,
		string $uid,
		string $class,
		array $content,
		array $titles,
		Locales $locales,
	): array {
		if (!$this->db->getConn()->inTransaction()) {
			throw new RuntimeException('Fulltext synchronization requires a transaction and a locked node row.');
		}

		$documents = [];
		$missingTitles = 0;
		foreach ($locales as $locale) {
			$config = $this->configurations->for($locale);
			try {
				$document = $this->builder->build($class, $content, $titles, $locale);
			} catch (Throwable $e) {
				throw new RuntimeException(
					"Fulltext node '{$uid}', locale '{$locale->id}': " . $e->getMessage(),
					previous: $e,
				);
			}
			$missingTitles += (int) $document->missingTitle;
			if ($document->contributions !== []) {
				$documents[] = [
					'locale' => $locale->id,
					'config' => $config,
					'contributions' => $document->contributions,
					'source' => $document->source,
				];
			}
		}

		$this->db->fulltext->delete(['node' => $node])->run();
		if ($documents !== []) {
			$this->db->fulltext->insert([
				'node' => $node,
				'uid' => $uid,
				'documents' => json_encode($documents, JSON_THROW_ON_ERROR),
			])->run();
		}

		return ['indexed' => count($documents), 'missingTitles' => $missingTitles];
	}
}
