<?php

declare(strict_types=1);

namespace Cosray\Api\Controller;

use Celema\Core\Exception\HttpNotFound;
use Celema\Core\Request;
use Cosray\Api\Responses;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Field\Blocks;
use Cosray\Field\Entries;
use Cosray\Field\Field;
use Cosray\Node\Factory;
use Cosray\Node\Types as NodeTypes;
use Cosray\Security\NodeAccess;
use Cosray\User;
use Psr\Http\Message\ResponseInterface as Response;

/** The node types a client may read, with the field schema writes have to follow. */
final class Types
{
	/** Descriptor keys a client needs; the rest only lay out the panel's editor. */
	private const array PROPERTIES = [
		'label',
		'description',
		'required',
		'translate',
		'translateMode',
		'options',
		'limit',
		'syntaxes',
		'immutable',
		'columns',
	];

	public function __construct(
		private readonly Responses $responses,
		private readonly NodeAccess $access,
		private readonly NodeTypes $types,
	) {}

	public function list(Request $request): Response
	{
		$user = self::user($request);
		$items = [];

		foreach ($this->readable($user) as $handle => $class) {
			$items[] = $this->summary($user, $handle, $class);
		}

		return $this->responses->json(['items' => $items]);
	}

	public function type(Request $request, Context $context, Cms $cms, string $type): Response
	{
		$user = self::user($request);
		$class = $this->readable($user)[$type]
			?? throw new HttpNotFound($request, payload: ['message' => "There is no type '{$type}'."]);
		$blueprint = $cms->nodeFactory()->blueprint($class, $context, $cms);
		$fields = [];

		foreach (Factory::fieldNamesFor($blueprint) as $name) {
			$fields[] = $this->field(Factory::fieldFor($blueprint, $name), withDefault: true);
		}

		return $this->responses->json([...$this->summary($user, $type, $class), 'fields' => $fields]);
	}

	/**
	 * @param class-string $class
	 * @return array<string, mixed>
	 */
	private function summary(?User $user, string $handle, string $class): array
	{
		$children = $this->types->get($class, 'children', []);
		$route = $this->types->get($class, 'route');

		return [
			'handle' => $handle,
			'label' => __((string) $this->types->get($class, 'label')),
			'routable' => (bool) $this->types->get($class, 'routable', false),
			'renderable' => (bool) $this->types->get($class, 'renderable', false),
			'route' => $route === '' ? null : $route,
			'children' => array_values(array_map(
				fn(string $child): string => (string) $this->types->get($child, 'handle'),
				is_array($children) ? array_filter($children, class_exists(...)) : [],
			)),
			'actions' => [
				'read' => $this->access->allows($user, NodeAccess::READ, $class),
				'create' => $this->access->allows($user, NodeAccess::CREATE, $class),
				'change' => $this->access->allows($user, NodeAccess::CHANGE, $class),
				'delete' => $this->access->allows($user, NodeAccess::DELETE, $class),
			],
		];
	}

	/** @return array<string, mixed> */
	private function field(Field $field, bool $withDefault): array
	{
		$properties = $field->properties();
		$schema = ['name' => $field->name, 'type' => self::kind($field)];

		foreach (self::PROPERTIES as $key) {
			if (array_key_exists($key, $properties)) {
				$schema[$key] = $properties[$key];
			}
		}

		$rows = match (true) {
			$field instanceof Blocks => array_map(fn(string $type): array => $this->row(
				$type,
				$field->rowHandle($type),
				$field->blockFields($type),
			), $field->allowedBlockTypes()),
			$field instanceof Entries => array_map(fn(string $type): array => $this->row(
				$type,
				$field->rowHandle($type),
				$field->entryFields($type),
			), $field->allowedEntryTypes()),
			default => null,
		};

		if ($rows !== null) {
			$schema['rows'] = array_values($rows);
		}

		if ($withDefault) {
			$default = $field->structure();
			unset($default['type']);
			$schema['default'] = $default;
		}

		return $schema;
	}

	/**
	 * @param class-string $type
	 * @param array<string, Field> $fields
	 * @return array<string, mixed>
	 */
	private function row(string $type, string $handle, array $fields): array
	{
		return [
			'type' => $handle,
			'label' => __((string) $this->types->get($type, 'label')),
			'fields' => array_values(array_map(fn(Field $field): array => $this->field($field, false), $fields)),
		];
	}

	/** @return array<string, class-string> */
	private function readable(?User $user): array
	{
		$readable = $this->access->readable($user);
		$handles = [...$readable['any'], ...$readable['live']];
		sort($handles);
		$registered = $this->access->registered();

		return array_combine($handles, array_map(static fn(string $handle): string => $registered[$handle], $handles));
	}

	/** A field class as a type name: `RichText` reads `rich-text`. */
	private static function kind(Field $field): string
	{
		$name = basename(str_replace('\\', '/', $field::class));

		return strtolower(ltrim((string) preg_replace('/[A-Z]/', '-$0', $name), '-'));
	}

	private static function user(Request $request): ?User
	{
		$user = $request->get('user', null);

		return $user instanceof User ? $user : null;
	}
}
