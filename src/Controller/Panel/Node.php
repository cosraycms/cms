<?php

declare(strict_types=1);

namespace Cosray\Controller\Panel;

use Celemas\Core\Exception\HttpNotFound;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Field\Blocks;
use Cosray\Field\Checkbox;
use Cosray\Field\Decimal;
use Cosray\Field\Entries;
use Cosray\Field\Field;
use Cosray\Field\File;
use Cosray\Field\Image;
use Cosray\Field\Number;
use Cosray\Field\Video;
use Cosray\Node\Factory as NodeFactory;
use Cosray\Node\Node as NodeWrapper;
use Cosray\Node\PathManager;
use Cosray\Node\Store;
use Cosray\Node\Types;
use Cosray\Panel\FieldRenderer;
use Cosray\Renderer;

final class Node extends Panel
{
	public function edit(Cms $cms, string $uid): array
	{
		return $this->editContext($cms, $uid);
	}

	public function save(Context $context, Cms $cms, Types $types, string $uid): array
	{
		$node = $this->fetch($cms, $uid);
		$store = new Store($context->db, new PathManager(), $types, $cms->nodeFactory()->uid());
		$store->save(
			NodeWrapper::unwrap($node),
			$this->formData($uid),
			$this->request,
			$context->locales(),
		);

		// Re-read so the rendered form reflects the persisted values.
		return $this->editContext($cms, $uid, ['saved' => true]);
	}

	private function editContext(Cms $cms, string $uid, array $data = []): array
	{
		$node = $this->fetch($cms, $uid);
		$inner = NodeWrapper::unwrap($node);
		$factory = $cms->nodeFactory();
		$fieldRenderer = $this->fieldRenderer();
		$fields = [];

		foreach ($factory->hydrator()->getFields($inner, NodeFactory::fieldNamesFor($inner)) as $field) {
			$fields[] = $fieldRenderer->render($field, [
				'panelPath' => self::PANEL_PATH,
				'componentAssets' => $this->componentAssets(),
			]);
		}

		$nodeData = NodeFactory::dataFor($inner);

		return $this->context(array_merge([
			'uid' => $nodeData['uid'] ?? $uid,
			'title' => $node->title(),
			'published' => ($nodeData['published'] ?? false) === true,
			'hidden' => ($nodeData['hidden'] ?? false) === true,
			'locked' => ($nodeData['locked'] ?? false) === true,
			'saved' => false,
			'fields' => $fields,
		], $data));
	}

	private function fetch(Cms $cms, string $uid): object
	{
		$node = $cms->node->byUid($uid, published: null);

		if ($node === null) {
			throw new HttpNotFound($this->request);
		}

		return $node;
	}

	private function formData(string $uid): array
	{
		$form = $this->request->form() ?? [];

		return [
			'uid' => is_string($form['uid'] ?? null) ? $form['uid'] : $uid,
			'published' => $this->bool($form['published'] ?? false),
			'hidden' => $this->bool($form['hidden'] ?? false),
			'locked' => $this->bool($form['locked'] ?? false),
			'content' => $this->content($form['content'] ?? []),
		];
	}

	private function bool(mixed $value): bool
	{
		return in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true);
	}

	private function content(mixed $content): array
	{
		if (!is_array($content)) {
			return [];
		}

		$fields = [];

		foreach ($content as $name => $data) {
			if (!is_string($name) || !is_array($data)) {
				continue;
			}

			$type = $data['type'] ?? null;

			if (!is_string($type) || trim($type) === '') {
				continue;
			}

			$value = $data['value'] ?? [];
			$fields[$name] = [
				'type' => $type,
				'value' => $this->values($type, $value),
			];

			if (isset($data['meta']) && is_array($data['meta'])) {
				$fields[$name]['meta'] = $data['meta'];
			}
		}

		return $fields;
	}

	private function values(string $type, mixed $value): array
	{
		$values = is_array($value) ? $value : [Field::NEUTRAL_LOCALE => $value];

		foreach ($values as $locale => $item) {
			$values[$locale] = $this->value($type, $item);
		}

		return $values;
	}

	private function value(string $type, mixed $value): mixed
	{
		if (is_a($type, Checkbox::class, true)) {
			return $this->bool($value);
		}

		if (is_a($type, Number::class, true) || is_a($type, Decimal::class, true)) {
			return $value === '' || $value === null ? null : (float) $value;
		}

		if ($this->isJsonField($type)) {
			return $this->json($value);
		}

		return $value;
	}

	private function isJsonField(string $type): bool
	{
		return (
			is_a($type, Blocks::class, true)
			|| is_a($type, Entries::class, true)
			|| is_a($type, Image::class, true)
			|| is_a($type, Video::class, true)
			|| is_a($type, File::class, true)
		);
	}

	private function json(mixed $value): array
	{
		if (is_array($value)) {
			return $value;
		}

		if (!is_string($value) || trim($value) === '') {
			return [];
		}

		$decoded = json_decode($value, true);

		return is_array($decoded) ? $decoded : [];
	}

	private function fieldRenderer(): FieldRenderer
	{
		$renderer = $this->container->tag(Renderer::class)->get('panel');
		assert($renderer instanceof Renderer, 'The panel renderer service must be available.');

		return new FieldRenderer($renderer);
	}
}
