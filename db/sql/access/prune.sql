DELETE FROM /*:cms.prefix:*/access_attempts WHERE expires <= now();
