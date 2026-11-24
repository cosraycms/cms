<?php

declare(strict_types=1);

$this->include('kit/inspector-section', ['section' => $section, 'oob' => true]);
$this->include('component/toast', ['kind' => 'success', 'message' => 'Noted']);
