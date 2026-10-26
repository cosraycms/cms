<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<title><?= $this->escape(__('auth:reset-mail-subject', ['site' => u($site)])) ?></title>
</head>
<body>
	<p><?= $this->escape(__('auth:reset-mail-greeting', ['name' => u($name)])) ?></p>
	<p><?= $this->escape(__('auth:reset-mail-intro', ['site' => u($site)])) ?></p>
	<p><a href="<?= $url ?>"><?= $this->escape(__('auth:reset-choose')) ?></a></p>
	<p><?= $this->escape(__('auth:reset-mail-expiry')) ?></p>
	<p><?= $this->escape(__('auth:reset-mail-ignore')) ?></p>
</body>
</html>
