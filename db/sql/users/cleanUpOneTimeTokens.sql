DELETE FROM cms.one_time_tokens WHERE created < now()::time - INTERVAL '5 min';
