<?php

// The response of an action that reports only a toast and changes nothing on
// the page. Receives: kind, message.

$this->include('component/toast', ['kind' => $kind ?? 'info', 'message' => $message ?? '']);
