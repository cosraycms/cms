<?php

declare(strict_types=1);

namespace Cosray\Finder;

use Celema\Core\Exception\HttpBadRequest;
use Cosray\Bootstrap;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Node\Factory;
use Cosray\Node\Types;
use Cosray\Node\Wrapper;

class Node
{
	public function __construct(
		private readonly Context $context,
		private readonly Cms $cms,
		private readonly Factory $nodeFactory,
		private readonly Types $types,
	) {}

	public function byPath(
		string $path,
		?bool $deleted = false,
		?bool $published = true,
	): ?Wrapper {
		return $this->get([
			'path' => $path,
			'published' => $published,
			'deleted' => $deleted,
		]);
	}

	public function byUid(
		string $uid,
		?bool $deleted = false,
		?bool $published = true,
	): ?Wrapper {
		return $this->get([
			'uid' => $uid,
			'published' => $published,
			'deleted' => $deleted,
		]);
	}

	public function byHandle(
		string $handle,
		?bool $deleted = false,
		?bool $published = true,
	): ?Wrapper {
		return $this->get([
			'handle' => $handle,
			'published' => $published,
			'deleted' => $deleted,
		]);
	}

	/**
	 * The node as the editor sees it: the working copy where one exists,
	 * the live row otherwise. Published or not, never deleted.
	 */
	public function working(string $uid): ?Wrapper
	{
		return $this->get([
			'uid' => $uid,
			'published' => null,
			'deleted' => false,
			'working' => true,
		]);
	}

	public function get(
		array $params,
	): ?Wrapper {
		$data = $this->context
			->db
			->nodes
			->find($params)
			->first();

		if (!$data) {
			return null;
		}

		$data['content'] = json_decode($data['content'], true);
		$data['title'] = json_decode($data['title'], true);
		$data['editor_data'] = json_decode($data['editor_data'], true);
		$data['creator_data'] = json_decode($data['creator_data'], true);
		$data['paths'] = json_decode($data['paths'], true);
		$data = self::foldDraft($data);
		$class = $this->context
			->container
			->tag(Bootstrap::NODE_TAG)
			->entry($data['type_handle'])
			->definition();

		if ($this->types->isNode($class)) {
			$this->context->access()->require(\Cosray\Access::permission($class, $this->types));
			$node = $this->nodeFactory->create($class, $this->context, $this->cms, $data);

			return $this->nodeFactory->proxy($node, $this->context, $this->cms);
		}

		throw new HttpBadRequest($this->context->request);
	}

	public function find(
		string $query,
	): array {
		return [];
	}

	/**
	 * Folds the joined draft columns of a `nodes/find` row into one `draft`
	 * entry (null without a working copy). When the working copy was
	 * requested, its handle and paths replace the row's live values the
	 * same way its content did.
	 *
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	public static function foldDraft(array $data): array
	{
		$hasDraft = (bool) ($data['has_draft'] ?? false);
		$data['draft'] = $hasDraft
			? [
				'created' => $data['draft_created'],
				'changed' => $data['draft_changed'],
				'editor' => (int) $data['draft_editor'],
				'editorName' => $data['draft_editor_name'] ?? null,
			]
			: null;
		$settings = json_decode((string) ($data['draft_settings'] ?? ''), true);
		unset(
			$data['has_draft'],
			$data['draft_created'],
			$data['draft_changed'],
			$data['draft_editor'],
			$data['draft_editor_name'],
			$data['draft_settings'],
		);

		if (!$hasDraft || !is_array($settings)) {
			return $data;
		}

		if (array_key_exists('handle', $settings)) {
			$data['handle'] = $settings['handle'];
		}

		if (is_array($settings['paths'] ?? null)) {
			$data['paths'] = $settings['paths'];
		}

		return $data;
	}
}
