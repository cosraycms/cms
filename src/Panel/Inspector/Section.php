<?php

declare(strict_types=1);

namespace Cosray\Panel\Inspector;

use Cosray\Exception\RuntimeException;

/**
 * What an inspector section shows: a heading and the content a panel
 * template renders. The editor form wraps the inspector and forms cannot
 * nest, so with an `action` the editor adds a form outside it that posts
 * there; the template's controls join it with `form="<?= $form ?>"`.
 */
final readonly class Section
{
	/**
	 * @param string $key names the section in the page, like the plugin's id
	 * @param string $template the panel template of the content, such as `acme-shop:stock`
	 * @param array<string, mixed> $context what the template receives, besides `form`
	 * @param ?string $action where the section's controls post to
	 */
	public function __construct(
		public string $key,
		public string $title,
		public string $template,
		public array $context = [],
		public ?string $action = null,
	) {
		if (preg_match('/\A[a-z][a-z0-9-]*\z/', $key) !== 1) {
			throw new RuntimeException(
				"An inspector section's key has lowercase letters, digits and dashes and starts with a letter: {$key}",
			);
		}
	}

	/** The id of the form the section's controls post with. */
	public function form(): string
	{
		return "cms-inspector-form-{$this->key}";
	}
}
