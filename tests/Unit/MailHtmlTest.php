<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Mail\Html;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class MailHtmlTest extends TestCase
{
	public function testTheSubjectIsTheTitleOfTheHtml(): void
	{
		$this->assertSame(
			'Message from Ann & Bob',
			Html::subject("<title>\n Message from\n Ann &amp; Bob </title><p>Body</p>"),
		);
		$this->assertSame('', Html::subject('<p>No title</p>'));
	}

	public function testTheTextKeepsWhatTheHtmlSaysAndItsStructure(): void
	{
		$html = <<<'HTML'
			<html>
			<head><title>Hidden</title><style>p { color: navy; }</style></head>
			<body>
				<h1>New message</h1>
				<p>Ann   wrote:<br>Hello</p>
				<ul><li>One</li><li>Two</li></ul>
				<table><tr><td>Name</td><td>Ann</td></tr><tr><td>Mail</td><td>ann@example.com</td></tr></table>
				<p>Reply to <a href="mailto:ann@example.com">ann@example.com</a>, see <a href="https://example.com/inbox">the inbox</a>,
				<a href="#top">go up</a> or open <a href="https://example.com/x"></a>.</p>
				<p><img src="logo.png" alt="Logo"></p>
			<pre>  keep
			  this</pre>
			</body>
			</html>
			HTML;

		$this->assertSame(
			"New message\n\nAnn wrote:\nHello\n\n- One\n- Two\n\nName Ann\nMail ann@example.com\n\n"
				. "Reply to ann@example.com, see the inbox (https://example.com/inbox), go up or open https://example.com/x.\n\n"
				. "Logo\n\n  keep\n  this",
			Html::text($html),
		);
	}
}
