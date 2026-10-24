DELETE FROM /*:cms.prefix:*/one_time_tokens WHERE created < now() - interval '5 minutes';
