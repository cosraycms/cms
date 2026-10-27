# Mail

Cosray sends mail with [Symfony Mailer](https://symfony.com/doc/current/mailer.html) through the [job queue](queue.md). `send()` renders and checks a mail right away and queues it; a runner delivers it after the response or from cron and retries when the mail server fails. See [Mailer](../src/Mailer.php).

## Configuration

| Setting | Environment | Meaning |
| --- | --- | --- |
| `mail.transports` | `MAILER_DSN` | Symfony Mailer DSNs by name. The first one is the default; `MAILER_DSN` fills the one named `default`. |
| `mail.from` | `MAILER_FROM` | The sender of mails that name none, like `Example <noreply@example.com>`. |
| `mail.redirect` | `MAILER_REDIRECT` | Addresses that receive every mail instead of its recipients, for staging and development. Comma-separated in the environment. |
| `mail.views` | – | Template directories, searched in order. By default `mail` in the views directory. |

```php
$app = App::create($root, [
    'mail.transports' => [
        'default' => env('MAILER_DSN'),
        'newsletter' => env('NEWSLETTER_DSN'),
    ],
    'mail.from' => 'Example <noreply@example.com>',
]);
```

SMTP needs nothing else: `smtp://user:password@smtp.example.com:587`. Symfony Mailer also takes `sendmail://default` and `null://null`, which drops every mail. For a provider's API, install its bridge, like `symfony/mailersend-mailer`, and use the DSN its documentation gives. Locally, a mail catcher such as Mailpit works with `smtp://localhost:1025`.

`mail.redirect` changes where a mail goes, not the mail: its `To` and `Cc` headers stay as they are, so a redirected mail still shows whom it was meant for.

`php run mail:test <address>` sends a test mail right away, past the queue, and prints the mail server's error if there is one. `--transport=<name>` picks a transport other than the default. With `mail.redirect`, the test mail goes to those addresses instead, and the command names them.

## Sending

Declare `Cosray\Mailer` as a constructor parameter, like in a node handling a form:

```php
use Celema\Core\Request;
use Celema\Core\Response;
use Cosray\Contract\HttpPost;
use Cosray\Mailer;
use Cosray\Util\Form;

final class Contact implements HttpPost
{
    public function __construct(
        private readonly Request $request,
        private readonly Mailer $mailer,
    ) {}

    public function httpPost(): Response
    {
        $form = Form::body($this->request);
        $this->mailer->send('contact', to: 'office@example.com', data: $form, replyTo: $form['email']);

        // ...
    }
}
```

`send()` takes a template name and, all optional:

- `to`, `cc`, `bcc` and `replyTo`: an address, like `ann@example.com` or `Ann <ann@example.com>`, a `Symfony\Component\Mime\Address`, or a list of them.
- `data`: the template's variables.
- `subject`: replaces the subject the template sets.
- `locale`: one of the site's locales to render in, instead of the current one.
- `delay`: seconds before the mail is sent.

It returns the id of the queued delivery. It throws when the mail cannot be sent at all: without a transport, sender, recipient or subject, or with an invalid address. Inside a transaction, the mail is queued only if the transaction commits, so storing a form submission and mailing about it happen together or not at all; see [transactions](queue.md#transactions).

`via()` returns a mailer for another transport: `$this->mailer->via('newsletter')->send(...)`.

For attachments, render the template with `compose()`, complete the mail, and send it:

```php
$email = $this->mailer->compose('application', $form)
    ->attachFromPath($upload, 'cv.pdf', 'application/pdf');

$this->mailer->send($email, to: 'jobs@example.com');
```

`send()` also takes a mail built with Symfony's `Email` alone. Queued mails carry addresses, subject, UTF-8 text and HTML bodies, attachments and embedded images of up to 10 MiB in all, priority and text headers; `send()` refuses mails that use anything else. Attachments are read when the mail is queued, so the file can go once `send()` returns.

## Templates

A mail template is a Boiler template that renders an HTML document. Its `<title>` is the subject:

```php
<!-- views/mail/contact.php -->
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Message from <?= $name ?></title>
</head>
<body>
    <p><?= $name ?> wrote:</p>
    <blockquote><?= $message ?></blockquote>
</body>
</html>
```

Values print escaped, as in page templates, and `__()` translates in the locale the mail renders in. The text body comes from `contact.txt.php` next to it, rendered without escaping, or else from the HTML: paragraphs, line breaks, lists and table rows keep their lines, and links show their address after the text. Cosray inlines no CSS; give elements `style` attributes where mail programs need them.

Links in mails must be absolute. Image and file values passed in `data` already are: their `url()` and `<img>` tags start with [`Context::origin()`](../src/Context.php), the request's scheme and host, or those of `app.url` in jobs and commands. A mail rendered in a locale with domains of its own, as in `$locales->add('en', 'English', domains: ['www.example.com'])`, links to the first of them, unless the request came in on one of them or was answered in that locale, so a mail sent with `locale: 'en'` leads to the English site. For other links, pass the `Context` in `data` and put its origin in front of the path in the template, where the mail's locale applies: `<a href="<?= $context->origin() ?><?= $node->path() ?>">`.

Cosray's own mails, like `cosray/password-reset` for the [password reset](application.md#password-reset), come from templates in Cosray; a file of the same name in a `mail.views` directory replaces one.

## Delivery

Mails are queued under the name `mail` and run like any other job: a failed attempt runs again later, a mail that cannot be sent fails at once. Every sent mail is logged at `info` with the number of its recipients and its transport; addresses are not logged. [Failed jobs](queue.md#failed-jobs) lists the commands to inspect and retry failed mails.

## Testing

`$mailer->queued()` returns the mails that wait for delivery, oldest first, as Symfony `Email` objects decoded from the queue, just as the delivery job will send them; a mail for a named transport carries its name in `X-Transport`. A mail sent inside a transaction that is rolled back was never queued and does not show up.

Requests run due jobs once their response is out, and so deliver their mails and remove them from the queue before a test can read them. Tests that make requests therefore turn that off with `queue.after_response => false` or `QUEUE_AFTER_RESPONSE=false`. Tests that call the code under test directly need nothing else:

```php
$this->contact->submit(['email' => 'ann@example.com', 'message' => 'Hello']);

$mail = $this->mailer->queued()[0];
$this->assertSame('ann@example.com', $mail->getTo()[0]->getAddress());
$this->assertSame('Thanks for your message', $mail->getSubject());
```

The queue holds what was sent, not how a transport handled it: `mail.redirect` applies only on delivery, so queued mails keep their recipients. `php run mail:test` checks a transport.
