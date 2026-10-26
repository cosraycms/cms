<?php

declare(strict_types=1);

namespace Cosray\Exception;

/** Thrown by a job that cannot succeed by running again: it fails at once. */
class JobFailed extends RuntimeException {}
