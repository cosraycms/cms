<?php

declare(strict_types=1);

namespace Celemas\Cms\Controller;

use Celemas\Cms\Cms;
use Celemas\Cms\Config;
use Celemas\Cms\Locales;
use Celemas\Cms\Middleware\Permission;
use Celemas\Cms\Node\Factory as NodeFactory;
use Celemas\Cms\Node\Serializer;
use Celemas\Cms\Node\Types;
use Celemas\Container\Container;
use Celemas\Core\Exception\HttpBadRequest;
use Celemas\Core\Factory\Factory;
use Celemas\Core\Request;
use Celemas\Core\Response;
use stdClass;

class Nodes
{
	public function __construct(
		protected readonly Request $request,
		protected readonly Config $config,
		protected readonly Container $container,
		protected readonly Locales $locales,
		protected readonly Types $types,
	) {}

	#[Permission('panel')]
	public function get(Cms $cms, Factory $factory): Response
	{
		if ($this->request->method() === 'GET') {
			$query = new GetQuery($this->request);
		} else {
			$query = new PostQuery($this->request);
		}

		if ($query->query) {
			$nodes = $cms->nodes($query->query);
		} elseif (count($query->uids) > 0) {
			if (count($query->uids) > 1) {
				$quoted = implode(',', array_map(static fn($uid) => "'{$uid}'", $query->uids));
				$queryString = "uid @ [{$quoted}]";
			} else {
				$queryString = "uid = '{$query->uids[0]}'";
			}

			$nodes = $cms->nodes($queryString);
		} else {
			throw new HttpBadRequest($this->request);
		}

		$nodeFactory = $cms->nodeFactory();
		$hydrator = $nodeFactory->hydrator();
		$serializer = new Serializer($hydrator, $this->types);
		$result = [];

		foreach ($nodes
			->published($query->published)
			->hidden($query->hidden)
			->order($query->order)
			->deleted($query->deleted) as $node) {
			$uid = $node->meta->uid;
			$n = [
				'uid' => $uid,
				'title' => $node->title(),
				'handle' => $node->meta->handle,
				'published' => $node->meta->published,
				'hidden' => $node->meta->hidden,
				'locked' => $node->meta->locked,
				'created' => $node->meta->created,
				'changed' => $node->meta->changed,
				'deleted' => $node->meta->deleted,
				'paths' => $node->meta->paths,
			];

			foreach ($query->fields as $field) {
				if (!$field) {
					continue;
				}

				$fieldName = trim($field);
				$fieldObj = $hydrator->getField($node, $fieldName);
				$value = $fieldObj->value();
				$n[$field] = $value->isset() ? $value->unwrap() : null;
			}

			if ($query->content) {
				$n['content'] = $serializer->content(
					$node,
					NodeFactory::dataFor($node),
					NodeFactory::fieldNamesFor($node),
				);
			}

			if ($query->map) {
				$result[$uid] = $n;
			} else {
				$result[] = $n;
			}
		}

		if (count($result) === 0 && $query->map) {
			$result = new stdClass();
		}

		return new Response($factory->response())->json($result);
	}
}
