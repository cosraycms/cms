-- Whether the revision holds exactly this content and these settings.
SELECT
	content = :content::jsonb AND settings = :settings::jsonb AS holds
FROM
	/*:cms.prefix:*/revisions
WHERE
	revision = :revision;
