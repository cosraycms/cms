<?php

declare(strict_types=1);

namespace Cosray;

/**
 * A node's stages as the panel shows them: its main version, the draft the
 * editorial team saved and the current user's own changes. A record's main
 * version is its saved state; records have no draft.
 */
enum Stage: string
{
	case Main = 'main';
	case Draft = 'draft';
	case Mine = 'mine';
}
