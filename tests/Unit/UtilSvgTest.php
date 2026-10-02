<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Tests\TestCase;
use Cosray\Util\Svg;
use PHPUnit\Framework\Attributes\DataProvider;

final class UtilSvgTest extends TestCase
{
	private const string OPEN = '<svg xmlns="http://www.w3.org/2000/svg">';
	private const string CLOSE = '</svg>';

	#[DataProvider('harmlessProvider')]
	public function testKeepsHarmlessMarkup(string $markup): void
	{
		$svg = self::OPEN . $markup . self::CLOSE;

		$this->assertSame($svg, Svg::sanitize($svg));
	}

	public static function harmlessProvider(): iterable
	{
		yield 'shapes and transforms' => [
			'<g transform="translate(10 10) rotate(45)"><path d="M0 0L10 10z" fill="#000" stroke-width="2"/>'
				. '<circle cx="1" cy="1" r="1"/><polygon points="0,0 1,1 0,1"/></g>',
		];
		yield 'local references' => [
			'<defs><linearGradient id="g" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#fff"/>'
				. '</linearGradient><symbol id="s" viewBox="0 0 1 1"><rect width="1" height="1"/></symbol></defs>'
				. '<rect fill="url(#g)"/><use href="#s"/>',
		];
		yield 'clipping, masks and markers' => [
			'<clipPath id="c"><rect/></clipPath><mask id="m"><rect/></mask>'
				. '<marker id="k" refX="1" refY="1"><path d="M0 0"/></marker>'
				. '<path clip-path="url(#c)" mask="url(\'#m\')" marker-end="url( #k )"/>',
		];
		yield 'filters' => [
			'<filter id="f" x="0" y="0"><feGaussianBlur in="SourceGraphic" stdDeviation="2" result="b"/>'
				. '<feColorMatrix type="saturate" values="0.5"/><feMerge><feMergeNode in="b"/></feMerge></filter>'
				. '<rect filter="url(#f)"/>',
		];
		yield 'text' => [
			'<text x="0" y="10" font-family="\'Open Sans\', sans-serif" text-anchor="middle">A '
				. '<tspan dy="1">B</tspan><textPath href="#p" startOffset="10%">C</textPath></text>',
		];
		yield 'accessibility' => [
			'<title>Logo</title><desc>Company logo</desc><g role="img" aria-label="Logo (dark)" lang="en"/>',
		];
		yield 'links' => [
			'<a href="https://example.com/"><rect/></a><a href="mailto:info@example.com"><rect/></a>'
				. '<a href="#top"><rect/></a>',
		];
		yield 'embedded raster image' => [
			'<image width="1" height="1" href="data:image/png;base64,iVBORw0KGgo="/>',
		];
		yield 'animation' => [
			'<rect><animate attributeName="opacity" values="0;1" dur="1s" repeatCount="indefinite"/>'
				. '<animateTransform attributeName="transform" type="rotate" from="0 5 5" to="360 5 5" dur="2s"/>'
				. '</rect><path id="p" d="M0 0H10"/>'
				. '<circle r="1"><animateMotion dur="2s"><mpath href="#p"/></animateMotion></circle>',
		];
		yield 'stylesheet' => [
			'<style>@media (min-width: 10px){.a{fill:rgb(0 0 0 / 50%);stroke:var(--s, #000)}}'
				. '@font-face{font-family:f;src:url(data:font/woff2;base64,d09GMgAB) format("woff2")}'
				. '@keyframes k{from{opacity:0}to{opacity:1}}'
				. '.a{animation:k 1s cubic-bezier(0.4, 0, 0.2, 1);fill:url(#g)}/* comment */</style>',
		];
		yield 'xml attributes' => ['<text xml:space="preserve" xml:lang="de">  A  </text>'];
		yield 'conditional processing' => ['<switch><g systemLanguage="de"/><g/></switch>'];
	}

	#[DataProvider('unsafeProvider')]
	public function testRemovesWhatCouldRunOrLoad(string $dirty, string $clean): void
	{
		// The trailing sibling must survive whatever is removed before it.
		$this->assertSame(
			self::OPEN . $clean . '<rect/>' . self::CLOSE,
			Svg::sanitize(self::OPEN . $dirty . '<rect/>' . self::CLOSE),
		);
	}

	public static function unsafeProvider(): iterable
	{
		yield 'script' => ['<script>alert(1)</script>', ''];
		yield 'script in another namespace' => [
			'<h:script xmlns:h="http://www.w3.org/1999/xhtml">alert(1)</h:script>',
			'',
		];
		yield 'script in another case' => ['<SCRIPT>alert(1)</SCRIPT>', ''];
		yield 'event handlers' => ['<rect onclick="alert(1)" ONLOAD="alert(1)" width="1"/>', '<rect width="1"/>'];
		yield 'foreign object' => [
			'<foreignObject><iframe xmlns="http://www.w3.org/1999/xhtml" src="https://example.com/"/></foreignObject>',
			'',
		];
		yield 'filter image' => [
			'<filter id="f"><feImage href="https://example.com/x.png"/></filter>',
			'<filter id="f"/>',
		];
		yield 'elements that end foreign content in HTML' => ['<font color="red"/><p>x</p>', ''];
		yield 'javascript link' => ['<a href="javascript:alert(1)">x</a>', '<a>x</a>'];
		yield 'obfuscated javascript link' => ['<a href=" &#74;AVA&#x09;script&#x0A;:alert(1)">x</a>', '<a>x</a>'];
		yield 'javascript xlink' => [
			'<a xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="javascript:alert(1)">x</a>',
			'<a>x</a>',
		];
		yield 'data link' => ['<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>', '<a>x</a>'];
		yield 'remote use' => ['<use href="https://example.com/sprite.svg#icon"/>', '<use/>'];
		yield 'remote image' => ['<image href="https://example.com/x.png"/>', '<image/>'];
		yield 'embedded svg image' => ['<image href="data:image/svg+xml;base64,PHN2Zy8+"/>', '<image/>'];
		yield 'animated link' => ['<a><animate attributeName="href" values="javascript:alert(1)"/>x</a>', '<a>x</a>'];
		yield 'animated xlink' => ['<a><set attributeName="xlink:href" to="javascript:alert(1)"/>x</a>', '<a>x</a>'];
		yield 'animated style' => ['<rect><set attributeName=" style " to="fill:red"/></rect>', '<rect/>'];
		yield 'animation to remote paint' => [
			'<rect><set attributeName="fill" to="url(https://example.com/#p)"/></rect>',
			'<rect><set attributeName="fill"/></rect>',
		];
		yield 'remote paint' => [
			'<rect fill="url(https://example.com/#p)" stroke="url(//example.com/#p)"/>',
			'<rect/>',
		];
		yield 'escaped url' => ['<rect style="fill:u\72 l(https://example.com/#p)"/>', '<rect/>'];
		yield 'url split by a comment' => ['<rect style="fill:ur/**/l(https://example.com/#p)"/>', '<rect/>'];
		yield 'malformed url' => ['<rect style="fill:url(#a b)"/>', '<rect/>'];
		yield 'expression' => ['<rect style="width:expression(alert(1))"/>', '<rect/>'];
		yield 'embedded svg in css' => ['<rect style="fill:url(data:image/svg+xml;base64,PHN2Zy8+)"/>', '<rect/>'];
		yield 'import' => ['<style>@import "https://example.com/x.css";</style>', ''];
		yield 'import in another case' => ['<style>@IMPORT "https://example.com/x.css";</style>', ''];
		yield 'image set' => ['<style>.a{background:image-set("https://example.com/x.png" 1x)}</style>', ''];
		yield 'remote font' => ['<style>@font-face{font-family:f;src:url(https://example.com/f.woff2)}</style>', ''];
		yield 'unterminated comment' => ['<style>.a{fill:red}/* </style>', ''];
		yield 'stylesheet in another language' => ['<style type="text/xsl">.a{}</style>', ''];
		yield 'comments and processing instructions' => ['<!-- x --><?php echo 1 ?>', ''];
		yield 'editor metadata' => [
			'<metadata><x/></metadata><i:pgf xmlns:i="http://ns.adobe.com/AdobeIllustrator/10.0/">x</i:pgf>'
				. '<g xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape" inkscape:label="L" data-x="1"/>',
			'<g/>',
		];
	}

	public function testEscapesTextThatWouldEndAnElementInHtml(): void
	{
		$svg = Svg::sanitize(
			self::OPEN
				. '<style><![CDATA[.a{}</style><img src=x>]]></style>'
				. '<title>&lt;/title&gt;&lt;img src=x onerror=alert(1)&gt;</title>'
				. self::CLOSE,
		);

		$this->assertSame(
			self::OPEN
				. '<style>.a{}&lt;/style&gt;&lt;img src=x&gt;</style>'
				. '<title>&lt;/title&gt;&lt;img src=x onerror=alert(1)&gt;</title>'
				. self::CLOSE,
			$svg,
		);
	}

	public function testWritesSvgNamespaceWithoutPrefix(): void
	{
		$svg = Svg::sanitize('<s:svg xmlns:s="http://www.w3.org/2000/svg"><s:rect width="1"/></s:svg>');

		$this->assertSame(self::OPEN . '<rect width="1"/>' . self::CLOSE, $svg);
	}

	public function testDropsDoctypeWithoutInternalSubset(): void
	{
		$svg = Svg::sanitize(
			'<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">'
				. self::OPEN
				. '<text>&nbsp;A</text>'
				. self::CLOSE,
		);

		$this->assertSame(self::OPEN . '<text>A</text>' . self::CLOSE, $svg);
	}

	#[DataProvider('rejectedProvider')]
	public function testRejectsDocument(string $svg): void
	{
		$this->assertNull(Svg::sanitize($svg));
	}

	public static function rejectedProvider(): iterable
	{
		yield 'empty' => [''];
		yield 'whitespace' => [" \n\t"];
		yield 'not xml' => ['<<< this is not valid svg'];
		yield 'not well-formed' => ['<svg xmlns="http://www.w3.org/2000/svg"><rect></svg>'];
		yield 'svg without namespace' => ['<svg><rect/></svg>'];
		yield 'other root' => [
			'<html xmlns="http://www.w3.org/1999/xhtml"><svg xmlns="http://www.w3.org/2000/svg"/></html>',
		];
		yield 'other svg root' => ['<g xmlns="http://www.w3.org/2000/svg"/>'];
		yield 'internal entity' => [
			'<!DOCTYPE svg [<!ENTITY x "javascript:alert(1)">]>'
				. '<svg xmlns="http://www.w3.org/2000/svg"><a href="&x;">x</a></svg>',
		];
		yield 'external entity' => [
			'<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]>'
				. '<svg xmlns="http://www.w3.org/2000/svg"><text>&x;</text></svg>',
		];
		yield 'entity expansion' => [
			'<!DOCTYPE svg [<!ENTITY a "aaaaaaaaaa"><!ENTITY b "&a;&a;&a;&a;&a;&a;&a;&a;&a;&a;">'
				. '<!ENTITY c "&b;&b;&b;&b;&b;&b;&b;&b;&b;&b;"><!ENTITY d "&c;&c;&c;&c;&c;&c;&c;&c;&c;&c;">]>'
				. '<svg xmlns="http://www.w3.org/2000/svg"><text>&d;</text></svg>',
		];
	}

	public function testKeepsDrawingFromIllustratorExport(): void
	{
		$svg = (string) Svg::sanitize(<<<'SVG'
			<?xml version="1.0" encoding="utf-8"?>
			<!-- Generator: Adobe Illustrator 27.0.0, SVG Export Plug-In . SVG Version: 6.00 Build 0)  -->
			<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">
			<svg version="1.1" id="Ebene_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
				 viewBox="0 0 100 100" style="enable-background:new 0 0 100 100;" xml:space="preserve">
			<style type="text/css">
				.st0{fill:#E30613;}
			</style>
			<rect class="st0" width="100" height="100"/>
			</svg>
			SVG);

		$this->assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg" version="1.1" id="Ebene_1"', $svg);
		$this->assertStringContainsString('viewBox="0 0 100 100" style="enable-background:new 0 0 100 100;"', $svg);
		$this->assertStringContainsString("<style type=\"text/css\">\n\t.st0{fill:#E30613;}\n</style>", $svg);
		$this->assertStringContainsString('<rect class="st0" width="100" height="100"/>', $svg);
		$this->assertStringNotContainsString('Generator', $svg);
	}

	public function testKeepsDrawingFromInkscapeExport(): void
	{
		$svg = (string) Svg::sanitize(<<<'SVG'
			<?xml version="1.0" encoding="UTF-8" standalone="no"?>
			<svg width="10mm" height="10mm" viewBox="0 0 10 10" version="1.1" id="svg1"
			   inkscape:version="1.3" sodipodi:docname="drawing.svg"
			   xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape"
			   xmlns:sodipodi="http://sodipodi.sourceforge.net/DTD/sodipodi-0.dtd"
			   xmlns="http://www.w3.org/2000/svg" xmlns:svg="http://www.w3.org/2000/svg">
			  <sodipodi:namedview id="namedview1" pagecolor="#ffffff"/>
			  <g inkscape:label="Layer 1" inkscape:groupmode="layer" id="layer1">
			    <rect style="fill:#ff0000;stroke-width:0.26" id="rect1" width="5" height="5" x="1" y="1"/>
			  </g>
			</svg>
			SVG);

		$this->assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg" width="10mm" height="10mm"', $svg);
		$this->assertStringContainsString(
			'<g id="layer1">' . "\n    " . '<rect style="fill:#ff0000;stroke-width:0.26" id="rect1"',
			$svg,
		);
		$this->assertStringNotContainsString('inkscape', $svg);
		$this->assertStringNotContainsString('sodipodi', $svg);
	}

	public function testKeepsDrawingFromFigmaExport(): void
	{
		$svg = (string) Svg::sanitize(<<<'SVG'
			<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
			<rect width="24" height="24" fill="url(#pattern0)"/>
			<defs>
			<pattern id="pattern0" patternContentUnits="objectBoundingBox" width="1" height="1">
			<use xlink:href="#image0" transform="scale(0.01)"/>
			</pattern>
			<image id="image0" width="100" height="100" xlink:href="data:image/png;base64,iVBORw0KGgo="/>
			</defs>
			</svg>
			SVG);

		$this->assertStringStartsWith(
			'<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" '
				. 'xmlns:xlink="http://www.w3.org/1999/xlink">',
			$svg,
		);
		$this->assertStringContainsString('<rect width="24" height="24" fill="url(#pattern0)"/>', $svg);
		$this->assertStringContainsString('<use xlink:href="#image0" transform="scale(0.01)"/>', $svg);
		$this->assertStringContainsString('xlink:href="data:image/png;base64,iVBORw0KGgo="', $svg);
	}

	public function testLeavesLibxmlErrorHandlingAsFound(): void
	{
		$previous = libxml_use_internal_errors();
		$warnings = [];
		set_error_handler(static function (int $level, string $message) use (&$warnings): bool {
			$warnings[] = $message;

			return true;
		});

		try {
			foreach ([false, true] as $internalErrors) {
				libxml_use_internal_errors($internalErrors);

				$this->assertNull(Svg::sanitize(self::OPEN . '<rect>' . self::CLOSE));
				$this->assertNotNull(Svg::sanitize(self::OPEN . self::CLOSE));
				$this->assertSame($internalErrors, libxml_use_internal_errors());
				$this->assertSame([], libxml_get_errors());
			}
		} finally {
			restore_error_handler();
			libxml_use_internal_errors($previous);
		}

		$this->assertSame([], $warnings);
	}
}
