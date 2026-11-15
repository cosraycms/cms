UPDATE /*:cms.prefix:*/users
SET
	username = :username,
	email = :email,
	roles = ARRAY(SELECT jsonb_array_elements_text(:roles::jsonb)),
	active = :active,
	panel_locale = :panel_locale,
	panel_region = :panel_region,
	panel_timezone = :panel_timezone,
	data = :data::jsonb,
	editor = :editor
WHERE
	usr = :usr;
