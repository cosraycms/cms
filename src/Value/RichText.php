<?php

declare(strict_types=1);

namespace Cosray\Value;

use Cosray\Richtext\Envelope;
use Cosray\Richtext\OwnerResolver;
use Cosray\Richtext\Renderer;
use Cosray\Util\Html as HtmlUtil;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class RichText extends Text
{
	public function __toString(): string
	{
		return $this->clean();
	}

	/**
	 * The locale-resolved structured document, or null for empty
	 * values and legacy HTML content.
	 */
	public function doc(): ?array
	{
		if (!Envelope::isStructured($this->data)) {
			return null;
		}

		$doc = $this->value();

		return is_array($doc) ? $doc : null;
	}

	public function unwrap(): string
	{
		if (isset($this->value)) {
			return $this->value;
		}

		if (!Envelope::isStructured($this->data)) {
			return parent::unwrap();
		}

		$doc = $this->doc();

		if ($doc === null) {
			return $this->value = '';
		}

		return $this->value = new Renderer(new OwnerResolver($this->owner))->render($doc);
	}

	/**
	 * Structured documents render escaped-by-construction HTML and
	 * bypass the sanitizer; it only guards legacy HTML strings.
	 */
	public function clean(
		?HtmlSanitizerConfig $config = null,
		bool $removeEmptyLines = true,
	): string {
		if (Envelope::isStructured($this->data)) {
			return $this->unwrap();
		}

		return HtmlUtil::sanitize($this->unwrap(), $config, $removeEmptyLines);
	}

	public function excerpt(
		int $words = 30,
		string $allowedTags = '',
	): string {
		return HtmlUtil::excerpt($this->unwrap(), $words, $allowedTags);
	}
}
