-- Migration: Clear content history before JSON normalization

TRUNCATE TABLE /*:cms.prefix:*/nodes_history;
TRUNCATE TABLE /*:cms.prefix:*/drafts_history;
