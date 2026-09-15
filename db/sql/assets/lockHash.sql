-- Ingest, protection and rendition generation must agree before exposing bytes.
SELECT pg_advisory_xact_lock(hashtextextended(:hash, 0));
