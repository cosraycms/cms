<?php

declare(strict_types=1);

namespace Cosray\Value;

use Cosray\Richtext\Envelope;
use Cosray\Richtext\OwnerResolver;
use Cosray\Richtext\Renderer;
use Cosray\Util\Html as HtmlUtil;

class RichText extends Text
{
	public function __toString(): string
	{
		return $this->unwrap();
	}

	/**
	 * The locale-resolved structured document, or null for empty
	 * values.
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
	public function unwrap(): string
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

	public function excerpt(
		int $words = 30,
		string $allowedTags = '',
	): string {
		return HtmlUtil::excerpt($this->unwrap(), $words, $allowedTags);
	}
}
