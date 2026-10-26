DELETE FROM /*:cms.prefix:*/password_resets WHERE expires <= now();
