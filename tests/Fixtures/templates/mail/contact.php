<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<title>Message from <?= $name ?></title>
	<style>p { color: navy; }</style>
</head>
<body>
	<h1>New message</h1>
	<p><?= $name ?> wrote:</p>
	<blockquote><?= $message ?></blockquote>
	<p>Reply to <a href="mailto:<?= $email ?>"><?= $email ?></a> or see <a href="https://example.com/inbox">the inbox</a>.</p>
</body>
</html>
