DELETE FROM /*:cms.prefix:*/rate_limits WHERE ends <= now();
