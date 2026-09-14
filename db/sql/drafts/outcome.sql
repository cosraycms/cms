-- Tells the history trigger why the draft row deleted in this transaction
-- goes away; without it the row reads as discarded.
SELECT set_config('cosray.draft_outcome', :outcome, true);
