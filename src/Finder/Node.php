<?php

declare(strict_types=1);

namespace Cosray\Finder;

use Celemas\Core\Exception\HttpBadRequest;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Node\Factory;
use Cosray\Node\Node as NodeWrapper;
use Cosray\Node\Types;
use Cosray\Plugin;

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
	): ?NodeWrapper {
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
	): ?NodeWrapper {
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
	): ?NodeWrapper {
		return $this->get([
			'handle' => $handle,
			'published' => $published,
			'deleted' => $deleted,
		]);
	}

	public function get(
		array $params,
	): ?NodeWrapper {
		$data = $this->context
			->db
			->nodes
			->find($params)
			->first();

		if (!$data) {
			return null;
		}

		$data['content'] = json_decode($data['content'], true);
		$data['editor_data'] = json_decode($data['editor_data'], true);
		$data['creator_data'] = json_decode($data['creator_data'], true);
		$data['paths'] = json_decode($data['paths'], true);
		$class = $this->context
			->container
			->tag(Plugin::NODE_TAG)
			->entry($data['type_handle'])
			->definition();

		if ($this->types->isNode($class)) {
			$node = $this->nodeFactory->create($class, $this->context, $this->cms, $data);

			return $this->nodeFactory->proxy($node, $this->context->request, $this->context, $this->cms);
		}

		throw new HttpBadRequest($this->context->request);
	}

	public function find(
		string $query,
	): array {
		return [];
	}
}
