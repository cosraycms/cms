SELECT EXISTS (SELECT FROM pg_stat_activity WHERE datname = current_database() AND application_name = :name AND wait_event_type = 'Lock') AS waiting;
