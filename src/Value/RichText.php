<?php

declare(strict_types=1);

namespace Cosray\Value;

use Cosray\Markup;
use Cosray\Richtext\Envelope;
use Cosray\Richtext\OwnerResolver;
use Cosray\Richtext\Renderer;
use Cosray\Util\Html as HtmlUtil;
use Override;

class RichText extends Text
{
	#[Override]
	public function __toString(): string
	{
		return $this->raw();
	}

	/**
	 * The locale-resolved structured document, or null for empty
	 * values.
	 *
	 * @return ?array<string, mixed>
	 */
	public function doc(): ?array
	{
		if (!Envelope::isStructured($this->data)) {
			return null;
		}

		$doc = $this->value();

		return is_array($doc) ? $doc : null;
	}

	/**
	 * The rendered HTML, escaped by construction. Values without the
	 * format envelope (unmigrated legacy HTML) render empty — the
	 * HTML-to-JSON migration ships with the code that requires it.
	 */
	#[Override]
	public function raw(): string
	{
		if (isset($this->value)) {
			return $this->value;
		}

		$doc = $this->doc();

		if ($doc === null) {
			return $this->value = '';
		}

		return $this->value = new Renderer(new OwnerResolver($this->owner))->render($doc);
	}

	/** @return array{html: string} */
	#[Override]
	public function json(): array
	{
		return ['html' => $this->raw()];
	}

	public function excerpt(
		int $words = 30,
		string $allowedTags = '',
	): Markup {
		return new Markup(HtmlUtil::excerpt($this->raw(), $words, $allowedTags));
	}
}
