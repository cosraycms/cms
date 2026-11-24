<?php

declare(strict_types=1);

namespace Cosray\Text;

use Cosray\Cms;
use Cosray\Context;
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
		$this->store = Store::of($context, $cms, $types);
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
