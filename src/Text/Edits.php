<?php

declare(strict_types=1);

namespace Cosray\Text;

use Cosray\Cms;
use Cosray\Context;
use Cosray\Node\PathManager;
use Cosray\Node\ReservedPaths;
use Cosray\Node\Store;
use Cosray\Node\Types;
use Cosray\Node\Wrapper;

/**
 * Opens a node's working copy to change its texts and save them where the
 * panel saves an edit, with the cause the history records:
 *
 *     $edit = $edits->open($uid);
 *     $edit->replace($pointer, 0, 9, 'Golden');
 *     $edit->save($actor, cause: ['source' => 'acme-proofreader']);
 *
 * It checks no permissions: each caller checks them for its user.
 *
 * @api
 */
final class Edits
{
	private readonly Store $store;
	private readonly Texts $texts;

	public function __construct(
		private readonly Context $context,
		private readonly Cms $cms,
		Types $types,
	) {
		$factory = $cms->nodeFactory();
		$this->store = new Store(
			$context->db,
			new PathManager(ReservedPaths::fromConfig($context->config)),
			$types,
			$factory->uid(),
			factory: $factory,
			cms: $cms,
			context: $context,
		);
		$this->texts = new Texts($context, $cms, $types);
	}

	/** The node with its working copy, or null when it does not exist or is deleted. */
	public function open(string $uid): ?Edit
	{
		$wrapper = $this->cms->node->working($uid);

		if ($wrapper === null) {
			return null;
		}

		return new Edit(Wrapper::unwrap($wrapper), $this->texts, $this->store, $this->context->locales());
	}
}
