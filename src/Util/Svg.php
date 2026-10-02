<?php

declare(strict_types=1);

namespace Cosray\Util;

use Dom\Attr;
use Dom\Comment;
use Dom\Element;
use Dom\Node;
use Dom\Text;
use Dom\XMLDocument;
use Dom\XPath;
use DOMException;
use ValueError;

/**
 * Rebuilds SVG markup from an allowlist, so that it can be served from or
 * inlined into the site origin without running script or loading remote
 * resources.
 *
 * The input is parsed strictly as XML and only allowed nodes are copied
 * into a new document. Whatever is not copied explicitly (doctype,
 * processing instructions, comments other than legal comments, entity
 * references, other namespaces) cannot survive. The serializer escapes
 * text, so the result is also safe inside HTML.
 *
 * @internal
 */
final class Svg
{
	private const string SVG = 'http://www.w3.org/2000/svg';
	private const string XLINK = 'http://www.w3.org/1999/xlink';
	private const string XML = 'http://www.w3.org/XML/1998/namespace';
	private const string XMLNS = 'http://www.w3.org/2000/xmlns/';

	/**
	 * Elements of the SVG namespace that are kept; any other element is
	 * dropped with its subtree. Left out on purpose: `script`,
	 * `foreignObject` (HTML content), `feImage` (loads documents), SVG
	 * fonts (`font` also ends foreign content when inlined into HTML),
	 * `cursor`, `color-profile`, `tref`, `metadata`, and deprecated or
	 * unsupported elements.
	 */
	private const array ELEMENTS = [
		'a',
		'animate',
		'animateMotion',
		'animateTransform',
		'circle',
		'clipPath',
		'defs',
		'desc',
		'ellipse',
		'feBlend',
		'feColorMatrix',
		'feComponentTransfer',
		'feComposite',
		'feConvolveMatrix',
		'feDiffuseLighting',
		'feDisplacementMap',
		'feDistantLight',
		'feDropShadow',
		'feFlood',
		'feFuncA',
		'feFuncB',
		'feFuncG',
		'feFuncR',
		'feGaussianBlur',
		'feMerge',
		'feMergeNode',
		'feMorphology',
		'feOffset',
		'fePointLight',
		'feSpecularLighting',
		'feSpotLight',
		'feTile',
		'feTurbulence',
		'filter',
		'g',
		'image',
		'line',
		'linearGradient',
		'marker',
		'mask',
		'mpath',
		'path',
		'pattern',
		'polygon',
		'polyline',
		'radialGradient',
		'rect',
		'set',
		'stop',
		'style',
		'svg',
		'switch',
		'symbol',
		'text',
		'textPath',
		'title',
		'tspan',
		'use',
		'view',
	];

	private const array ANIMATIONS = ['animate', 'animateMotion', 'animateTransform', 'set'];

	/**
	 * Attributes without namespace that are kept. Event handlers are not
	 * listed and therefore never kept. `href` is checked as a link and
	 * `style` as CSS; values of the others are checked as CSS values
	 * unless listed in PLAIN.
	 */
	private const array ATTRIBUTES = [
		// Core, structure and conditional processing
		'baseProfile',
		'class',
		'href',
		'id',
		'lang',
		'preserveAspectRatio',
		'requiredExtensions',
		'requiredFeatures',
		'role',
		'style',
		'systemLanguage',
		'transform',
		'version',
		'viewBox',
		// Geometry
		'cx',
		'cy',
		'd',
		'height',
		'pathLength',
		'points',
		'r',
		'rx',
		'ry',
		'width',
		'x',
		'x1',
		'x2',
		'y',
		'y1',
		'y2',
		// Markers, patterns, gradients, clipping and masking
		'clipPathUnits',
		'fr',
		'fx',
		'fy',
		'gradientTransform',
		'gradientUnits',
		'markerHeight',
		'markerUnits',
		'markerWidth',
		'maskContentUnits',
		'maskUnits',
		'offset',
		'orient',
		'patternContentUnits',
		'patternTransform',
		'patternUnits',
		'refX',
		'refY',
		'spreadMethod',
		// Text
		'dx',
		'dy',
		'lengthAdjust',
		'method',
		'path',
		'rotate',
		'side',
		'spacing',
		'startOffset',
		'textLength',
		// Filters
		'amplitude',
		'azimuth',
		'baseFrequency',
		'bias',
		'diffuseConstant',
		'divisor',
		'edgeMode',
		'elevation',
		'exponent',
		'filterUnits',
		'in',
		'in2',
		'intercept',
		'k1',
		'k2',
		'k3',
		'k4',
		'kernelMatrix',
		'kernelUnitLength',
		'limitingConeAngle',
		'mode',
		'numOctaves',
		'operator',
		'order',
		'pointsAtX',
		'pointsAtY',
		'pointsAtZ',
		'preserveAlpha',
		'primitiveUnits',
		'radius',
		'result',
		'scale',
		'seed',
		'slope',
		'specularConstant',
		'specularExponent',
		'stdDeviation',
		'stitchTiles',
		'surfaceScale',
		'tableValues',
		'targetX',
		'targetY',
		'type',
		'values',
		'xChannelSelector',
		'yChannelSelector',
		'z',
		// Animation
		'accumulate',
		'additive',
		'attributeName',
		'attributeType',
		'begin',
		'by',
		'calcMode',
		'dur',
		'end',
		'from',
		'keyPoints',
		'keySplines',
		'keyTimes',
		'max',
		'min',
		'repeatCount',
		'repeatDur',
		'restart',
		'to',
		// Presentation attributes
		'alignment-baseline',
		'baseline-shift',
		'clip',
		'clip-path',
		'clip-rule',
		'color',
		'color-interpolation',
		'color-interpolation-filters',
		'color-rendering',
		'direction',
		'display',
		'dominant-baseline',
		'enable-background',
		'fill',
		'fill-opacity',
		'fill-rule',
		'filter',
		'flood-color',
		'flood-opacity',
		'font-family',
		'font-size',
		'font-size-adjust',
		'font-stretch',
		'font-style',
		'font-variant',
		'font-weight',
		'glyph-orientation-horizontal',
		'glyph-orientation-vertical',
		'image-rendering',
		'letter-spacing',
		'lighting-color',
		'marker-end',
		'marker-mid',
		'marker-start',
		'mask',
		'mask-type',
		'opacity',
		'overflow',
		'paint-order',
		'pointer-events',
		'shape-rendering',
		'stop-color',
		'stop-opacity',
		'stroke',
		'stroke-dasharray',
		'stroke-dashoffset',
		'stroke-linecap',
		'stroke-linejoin',
		'stroke-miterlimit',
		'stroke-opacity',
		'stroke-width',
		'text-anchor',
		'text-decoration',
		'text-overflow',
		'text-rendering',
		'transform-origin',
		'unicode-bidi',
		'vector-effect',
		'visibility',
		'white-space',
		'word-spacing',
		'writing-mode',
	];

	/** Attributes whose values are neither CSS nor URLs, kept as they are. */
	private const array PLAIN = ['attributeName', 'class', 'id', 'lang', 'role'];

	/** Elements whose content is kept as text only. */
	private const array TEXT_ONLY = ['desc', 'title'];

	/** Root attributes that let an inlined drawing leave its box. */
	private const array ESCAPING = ['filter', 'overflow', 'style', 'transform'];

	/** Animating these would bypass the link and CSS checks. */
	private const array UNANIMATABLE = ['href', 'style'];

	/** CSS functions that cannot load anything; `url()` is checked separately. */
	private const array FUNCTIONS = [
		'blur',
		'brightness',
		'calc',
		'circle',
		'clamp',
		'color',
		'color-mix',
		'contrast',
		'cubic-bezier',
		'drop-shadow',
		'ellipse',
		'format',
		'grayscale',
		'hsl',
		'hsla',
		'hue-rotate',
		'hwb',
		'inset',
		'invert',
		'lab',
		'lch',
		'linear',
		'local',
		'matrix',
		'matrix3d',
		'max',
		'min',
		'oklab',
		'oklch',
		'opacity',
		'perspective',
		'polygon',
		'rgb',
		'rgba',
		'rotate',
		'rotate3d',
		'rotatex',
		'rotatey',
		'rotatez',
		'saturate',
		'scale',
		'scale3d',
		'scalex',
		'scaley',
		'scalez',
		'sepia',
		'skew',
		'skewx',
		'skewy',
		'steps',
		'translate',
		'translate3d',
		'translatex',
		'translatey',
		'translatez',
		'url',
		'var',
	];

	private const array AT_RULES = ['-webkit-keyframes', 'charset', 'font-face', 'keyframes', 'media', 'supports'];

	private const string IMAGE_DATA = '~^data:image/(?:png|jpe?g|gif|webp);base64,[a-z0-9+/]*={0,2}$~i';
	private const string CSS_DATA = '~^data:(?:image/(?:png|jpe?g|gif|webp)|font/[a-z0-9.+-]+|application/(?:x-)?font-[a-z0-9.+-]+);base64,[a-z0-9+/]*={0,2}$~i';

	/**
	 * Returns the cleaned markup without XML declaration, or null when the
	 * input is rejected as a whole: empty, not well-formed XML, a root
	 * other than `svg` in the SVG namespace, or a doctype with an internal
	 * subset (entity declarations).
	 *
	 * Pass `inline: true` for markup that is placed into an HTML page
	 * rather than served as a file or embedded with `<img>`; see confine().
	 */
	public static function sanitize(string $svg, bool $inline = false): ?string
	{
		$source = self::parse($svg);
		$root = $source?->documentElement;

		if (
			$root === null
			|| $root->namespaceURI !== self::SVG
			|| $root->localName !== 'svg'
			|| trim((string) $source?->doctype?->internalSubset) !== ''
		) {
			return null;
		}

		$document = XMLDocument::createEmpty();
		$copy = self::copy($root, $document);
		$document->append($copy);
		self::moveLegalComments($source, $document, $copy);

		if ($inline) {
			self::confine($document, $copy);
		}

		foreach ($document->getElementsByTagNameNS(self::SVG, '*') as $element) {
			if ($element->hasAttributeNS(self::XLINK, 'href')) {
				// Declared once on the root instead of on every linking element.
				$copy->setAttributeNS(self::XMLNS, 'xmlns:xlink', self::XLINK);

				break;
			}
		}

		$markup = $document->saveXml($copy);

		return $markup === false ? null : $markup;
	}

	private static function parse(string $svg): ?XMLDocument
	{
		if (trim($svg) === '') {
			return null;
		}

		// Parse errors are collected instead of reported; the flag and the
		// error buffer are restored for whatever runs next in this process.
		$internalErrors = libxml_use_internal_errors(true);

		try {
			// Without LIBXML_NOENT and LIBXML_DTDLOAD no entity is substituted
			// and no external DTD is read; LIBXML_NONET forbids network access.
			return XMLDocument::createFromString($svg, LIBXML_NONET);
		} catch (DOMException|ValueError) {
			return null;
		} finally {
			libxml_clear_errors();
			libxml_use_internal_errors($internalErrors);
		}
	}

	private static function copy(Element $source, XMLDocument $document): Element
	{
		$target = self::create($source, $document);

		foreach ($source->childNodes as $child) {
			if ($child instanceof Text) {
				// Also CDATA sections, which are written back as escaped text.
				$target->append($document->createTextNode($child->data));

				continue;
			}

			if (self::isLegalComment($child)) {
				$target->append($document->createComment($child->data));

				continue;
			}

			// Inside inline SVG, `title` and `desc` switch the HTML parser back
			// to HTML; text only leaves nothing it could read differently.
			if (
				!$child instanceof Element
				|| in_array($source->localName, self::TEXT_ONLY, true)
				|| !self::allowed($child)
			) {
				continue;
			}

			$copy = $child->localName === 'style'
				? self::stylesheet($child, $document)
				: self::copy($child, $document);

			if ($copy !== null) {
				$target->append($copy);
			}
		}

		return $target;
	}

	/**
	 * Legal comments, `<!--! … -->`, carry copyright and license notices,
	 * and licenses such as CC BY may require them to stay with the file;
	 * Font Awesome writes its notice this way, and SVGO keeps such comments
	 * for that reason. Removing them could breach the license, so they are
	 * the only comments kept.
	 *
	 * A comment is inert in an SVG file. Inlined into HTML, one that starts
	 * with `!` and contains no `--` cannot be closed early by the HTML
	 * parser either. The XML parser already rejects `--` in comments; the
	 * explicit check keeps that guarantee visible. Inline markup drops all
	 * comments anyway, see confine().
	 */
	private static function isLegalComment(Node $node): bool
	{
		return $node instanceof Comment && str_starts_with($node->data, '!') && !str_contains($node->data, '--');
	}

	/** The copy holds only the root element, so notices around it move inside. */
	private static function moveLegalComments(XMLDocument $source, XMLDocument $document, Element $copy): void
	{
		$comments = [];

		foreach ($source->childNodes as $node) {
			if ($node === $source->documentElement) {
				$copy->prepend(...$comments);
				$comments = [];
			} elseif (self::isLegalComment($node)) {
				$comments[] = $document->createComment($node->data);
			}
		}

		$copy->append(...$comments);
	}

	/**
	 * Inline SVG shares the page's CSS and layout: a `<style>` element
	 * applies to the whole page, and the root could be moved, enlarged or
	 * filtered over surrounding content. Drawing inside its own box stays.
	 * Comments, legal ones included, have no use there and are removed.
	 */
	private static function confine(XMLDocument $document, Element $root): void
	{
		foreach (self::ESCAPING as $name) {
			$root->removeAttributeNS(null, $name);
		}

		foreach (iterator_to_array(new XPath($document)->query('//comment()')) as $comment) {
			$comment->remove();
		}

		$id = $root->getAttributeNS(null, 'id');

		// The collection is live; removing while iterating it skips elements.
		foreach (iterator_to_array($root->getElementsByTagNameNS(self::SVG, '*')) as $element) {
			if ($element->localName === 'style' || self::animatesRoot($element, $root, $id)) {
				$element->remove();
			}
		}
	}

	/** Animations target their parent, or the element their link points to. */
	private static function animatesRoot(Element $element, Element $root, ?string $id): bool
	{
		if (!in_array($element->localName, self::ANIMATIONS, true)) {
			return false;
		}

		$href = $element->getAttributeNS(null, 'href') ?? $element->getAttributeNS(self::XLINK, 'href');

		return $href === null ? $element->parentNode === $root : $id !== null && $href === "#{$id}";
	}

	/** The element and its allowed attributes, without children. */
	private static function create(Element $source, XMLDocument $document): Element
	{
		$target = $document->createElementNS(self::SVG, $source->localName);

		foreach ($source->attributes as $attr) {
			self::attribute($attr, $target);
		}

		return $target;
	}

	private static function allowed(Element $element): bool
	{
		return (
			$element->namespaceURI === self::SVG
				&& in_array($element->localName, self::ELEMENTS, true)
				&& self::animates($element)
		);
	}

	/**
	 * An animation may only target attributes that are kept anyway and
	 * whose values are checked on the element itself; its `values`, `from`,
	 * `to` and `by` go through the CSS check. A prefixed name like
	 * `xlink:href` is not in the list.
	 */
	private static function animates(Element $element): bool
	{
		if (
			!in_array($element->localName, self::ANIMATIONS, true)
			|| !$element->hasAttributeNS(null, 'attributeName')
		) {
			return true;
		}

		$name = trim((string) $element->getAttributeNS(null, 'attributeName'));

		return in_array($name, self::ATTRIBUTES, true) && !in_array($name, self::UNANIMATABLE, true);
	}

	private static function attribute(Attr $attr, Element $target): void
	{
		$namespace = $attr->namespaceURI;
		$name = $attr->localName;
		$value = $attr->value;

		if ($name === 'href' && ($namespace === null || $namespace === self::XLINK)) {
			$url = self::link($target->localName, $value);

			if ($url !== null) {
				$target->setAttributeNS($namespace, $namespace === null ? 'href' : 'xlink:href', $url);
			}

			return;
		}

		if ($namespace === self::XML && ($name === 'space' || $name === 'lang')) {
			$target->setAttributeNS(self::XML, "xml:{$name}", $value);

			return;
		}

		if ($namespace !== null) {
			return;
		}

		if (in_array($name, self::PLAIN, true) || str_starts_with($name, 'aria-')) {
			$target->setAttributeNS(null, $name, $value);

			return;
		}

		if (in_array($name, self::ATTRIBUTES, true) && self::css($value)) {
			$target->setAttributeNS(null, $name, $value);
		}
	}

	/**
	 * Links point into the document, except for `<a>`, which may also
	 * navigate to web and mail addresses, and `<image>`, which may embed a
	 * raster image. The value is compared and written without the
	 * whitespace and control characters browsers ignore in URLs.
	 */
	private static function link(string $element, string $value): ?string
	{
		$url = self::compact($value);

		$allowed = match ($element) {
			'a' => str_starts_with($url, '#') || preg_match('~^(?:https?|mailto):~i', $url) === 1,
			'image' => preg_match(self::IMAGE_DATA, $url) === 1,
			default => str_starts_with($url, '#'),
		};

		return $allowed ? $url : null;
	}

	/** A `<style>` element is kept only as a whole, with its text as the only child. */
	private static function stylesheet(Element $source, XMLDocument $document): ?Element
	{
		$type = $source->getAttributeNS(null, 'type');
		$css = (string) $source->textContent;

		if ($type !== null && strtolower(trim($type)) !== 'text/css' || !self::css($css)) {
			return null;
		}

		$target = self::create($source, $document);
		$target->append($document->createTextNode($css));

		return $target;
	}

	/**
	 * Accepts a stylesheet, declaration list or CSS value when it cannot
	 * load anything: only harmless functions and at-rules, and `url()`
	 * only for local references and embedded raster images or fonts.
	 * Strings and comments are not parsed; text in them that looks like a
	 * function or at-rule leads to a rejection, which errs on the safe side.
	 */
	private static function css(string $css): bool
	{
		// Without backslashes CSS has no escapes, so the plain-text checks
		// below see the same function and at-rule names a browser sees.
		if (str_contains($css, '\\')) {
			return false;
		}

		$css = preg_replace('~/\*.*?\*/~s', ' ', $css);

		if ($css === null || str_contains($css, '/*')) {
			return false;
		}

		preg_match_all('~@([\w-]+)~', $css, $atRules);

		foreach ($atRules[1] as $atRule) {
			if (!in_array(strtolower($atRule), self::AT_RULES, true)) {
				return false;
			}
		}

		preg_match_all('~([\w\x80-\xff-]+)\(~', $css, $functions);

		foreach ($functions[1] as $function) {
			if (!in_array(strtolower($function), self::FUNCTIONS, true)) {
				return false;
			}
		}

		$found = preg_match_all(
			'~url\(\s*(?:"([^"]*)"|\'([^\']*)\'|([^"\'()\s]*))\s*\)~i',
			$css,
			$urls,
			PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL,
		);

		// Every `url(` must be one of the well-formed calls checked here.
		if ($found !== substr_count(strtolower($css), 'url(')) {
			return false;
		}

		foreach ($urls as $url) {
			$target = self::compact((string) ($url[1] ?? $url[2] ?? $url[3]));

			if (!str_starts_with($target, '#') && preg_match(self::CSS_DATA, $target) !== 1) {
				return false;
			}
		}

		return true;
	}

	private static function compact(string $value): string
	{
		return (string) preg_replace('~[\x00-\x20]+~', '', $value);
	}
}
