-- Keeps every state of a node's content, handle and paths as an immutable
-- revision. `nodes.revision` names the main version, a working copy
-- becomes a pointer to its revision, and unsaved edits name the revision
-- they started from. The distinct content states of `nodes_history`
-- become earlier revisions, without the handle and paths it never kept;
-- the draft history goes, since applied states are main revisions
-- already. Edits that are a conflict today keep it with a null base.
-- `nodes.editor` and `nodes.version` go: the main revision names its
-- author.

CREATE TABLE /*:cms.prefix:*/revisions (
	revision bigint GENERATED ALWAYS AS IDENTITY,
	node bigint NOT NULL,
	base bigint,
	editor bigint NOT NULL,
	created timestamp with time zone NOT NULL DEFAULT clock_timestamp(),
	content jsonb NOT NULL,
	settings jsonb NOT NULL DEFAULT '{}',
	CONSTRAINT /*:cms.obj:*/pk_revisions PRIMARY KEY (revision)
);

-- The distinct content states of every node in order, the current
-- one last, which also keeps its handle and paths. A run of equal
-- states counts once, dated by its first row; the current state
-- keeps the time and author of the main version.
INSERT INTO /*:cms.prefix:*/revisions (node, editor, created, content, settings)
SELECT node, editor, at, content, settings
FROM (
	WITH states AS (
		SELECT h.node, h.changed AS at, coalesce(u.usr, n.creator) AS editor, h.content,
			0 AS current, '{}'::jsonb AS settings
		FROM /*:cms.prefix:*/nodes_history h
		JOIN /*:cms.prefix:*/nodes n ON n.node = h.node
		LEFT JOIN /*:cms.prefix:*/users u ON u.usr = h.editor
		UNION ALL
		SELECT n.node, n.edited, n.editor, n.content, 1,
			jsonb_build_object(
				'handle', h.handle,
				'paths', coalesce((
					SELECT jsonb_object_agg(p.locale, p.path)
					FROM /*:cms.prefix:*/url_paths p
					WHERE p.node = n.node AND p.inactive IS NULL
				), '{}')
			)
		FROM /*:cms.prefix:*/nodes n
		LEFT JOIN /*:cms.prefix:*/handles h ON h.node = n.node
	), marked AS (
		SELECT s.*, (s.content IS DISTINCT FROM lag(s.content) OVER w)::int AS starts
		FROM states s
		WINDOW w AS (PARTITION BY s.node ORDER BY s.current, s.at)
	), runs AS (
		SELECT m.*, sum(m.starts) OVER (
			PARTITION BY m.node ORDER BY m.current, m.at ROWS UNBOUNDED PRECEDING
		) AS run
		FROM marked m
	)
	SELECT DISTINCT ON (node, run) node, run, editor, at, content, settings
	FROM runs
	ORDER BY node, run, current DESC, at
) chosen
ORDER BY node, run;

-- Before the lookups by node below.
CREATE INDEX /*:cms.obj:*/ix_revisions_node ON /*:cms.prefix:*/revisions (node, revision);

UPDATE /*:cms.prefix:*/revisions r SET base = l.previous
FROM (
	SELECT revision, lag(revision) OVER (PARTITION BY node ORDER BY revision) AS previous
	FROM /*:cms.prefix:*/revisions
) l
WHERE l.revision = r.revision AND l.previous IS NOT NULL;

ALTER TABLE /*:cms.prefix:*/nodes ADD COLUMN revision bigint;
UPDATE /*:cms.prefix:*/nodes n SET revision = (
	SELECT max(r.revision) FROM /*:cms.prefix:*/revisions r WHERE r.node = n.node
);

ALTER TABLE /*:cms.prefix:*/drafts ADD COLUMN revision bigint;
ALTER TABLE /*:cms.prefix:*/drafts ADD COLUMN base bigint;
WITH drafted AS (
	INSERT INTO /*:cms.prefix:*/revisions (node, base, editor, created, content, settings)
	SELECT d.node, n.revision, d.editor, d.changed, d.content, d.settings
	FROM /*:cms.prefix:*/drafts d
	JOIN /*:cms.prefix:*/nodes n ON n.node = d.node
	ORDER BY d.node
	RETURNING revision, node, base
)
UPDATE /*:cms.prefix:*/drafts d SET revision = drafted.revision, base = drafted.base
FROM drafted
WHERE drafted.node = d.node;

-- Edits as old as the working state or newer start from its revision;
-- older ones are a conflict today and keep it with a null base.
ALTER TABLE /*:cms.prefix:*/edits ADD COLUMN revision bigint;
UPDATE /*:cms.prefix:*/edits e SET revision = coalesce(d.revision, n.revision)
FROM /*:cms.prefix:*/nodes n
LEFT JOIN /*:cms.prefix:*/drafts d ON d.node = n.node
WHERE n.node = e.node AND e.base >= coalesce(d.changed, n.edited);
ALTER TABLE /*:cms.prefix:*/edits DROP COLUMN base;
ALTER TABLE /*:cms.prefix:*/edits RENAME COLUMN revision TO base;

ALTER TABLE /*:cms.prefix:*/events ADD COLUMN revision bigint;
UPDATE /*:cms.prefix:*/events e SET revision = (
	SELECT min(r.revision) FROM /*:cms.prefix:*/revisions r WHERE r.node = e.node
)
WHERE e.action = 'created';

DROP TRIGGER /*:cms.obj:*/drafts_trigger_01_history ON /*:cms.prefix:*/drafts;
DROP TRIGGER /*:cms.obj:*/drafts_trigger_02_change ON /*:cms.prefix:*/drafts;
DROP TRIGGER /*:cms.obj:*/edits_trigger_01_change ON /*:cms.prefix:*/edits;
DROP FUNCTION /*:cms.prefix:*/record_draft_history();
DROP TABLE /*:cms.prefix:*/drafts_history;
DROP TABLE /*:cms.prefix:*/nodes_history;
ALTER TABLE /*:cms.prefix:*/drafts DROP COLUMN content;
ALTER TABLE /*:cms.prefix:*/drafts DROP COLUMN settings;
ALTER TABLE /*:cms.prefix:*/drafts DROP COLUMN changed;
ALTER TABLE /*:cms.prefix:*/drafts DROP COLUMN editor;
ALTER TABLE /*:cms.prefix:*/drafts ALTER COLUMN revision SET NOT NULL;
ALTER TABLE /*:cms.prefix:*/drafts ALTER COLUMN created DROP DEFAULT;
ALTER TABLE /*:cms.prefix:*/nodes DROP COLUMN editor;
ALTER TABLE /*:cms.prefix:*/nodes DROP COLUMN version;

CREATE INDEX /*:cms.obj:*/ix_events_revision ON /*:cms.prefix:*/events (revision)
	WHERE revision IS NOT NULL;
ALTER TABLE /*:cms.prefix:*/revisions
	ADD CONSTRAINT /*:cms.obj:*/fk_revisions_nodes FOREIGN KEY (node)
		REFERENCES /*:cms.prefix:*/nodes (node) ON DELETE CASCADE,
	ADD CONSTRAINT /*:cms.obj:*/fk_revisions_revisions FOREIGN KEY (base)
		REFERENCES /*:cms.prefix:*/revisions (revision) ON DELETE SET NULL,
	ADD CONSTRAINT /*:cms.obj:*/fk_revisions_users FOREIGN KEY (editor)
		REFERENCES /*:cms.prefix:*/users (usr);
ALTER TABLE /*:cms.prefix:*/nodes
	ADD CONSTRAINT /*:cms.obj:*/fk_nodes_revisions FOREIGN KEY (revision)
		REFERENCES /*:cms.prefix:*/revisions (revision) DEFERRABLE INITIALLY DEFERRED;
ALTER TABLE /*:cms.prefix:*/drafts DROP CONSTRAINT /*:cms.obj:*/fk_drafts_nodes;
ALTER TABLE /*:cms.prefix:*/drafts
	ADD CONSTRAINT /*:cms.obj:*/fk_drafts_nodes FOREIGN KEY (node)
		REFERENCES /*:cms.prefix:*/nodes (node) ON DELETE CASCADE,
	ADD CONSTRAINT /*:cms.obj:*/fk_drafts_revisions FOREIGN KEY (revision)
		REFERENCES /*:cms.prefix:*/revisions (revision),
	ADD CONSTRAINT /*:cms.obj:*/fk_drafts_revisions_base FOREIGN KEY (base)
		REFERENCES /*:cms.prefix:*/revisions (revision) ON DELETE SET NULL;
ALTER TABLE /*:cms.prefix:*/edits
	ADD CONSTRAINT /*:cms.obj:*/fk_edits_revisions FOREIGN KEY (base)
		REFERENCES /*:cms.prefix:*/revisions (revision) ON DELETE SET NULL;
ALTER TABLE /*:cms.prefix:*/events
	ADD CONSTRAINT /*:cms.obj:*/fk_events_revisions FOREIGN KEY (revision)
		REFERENCES /*:cms.prefix:*/revisions (revision) ON DELETE SET NULL;

CREATE FUNCTION /*:cms.prefix:*/check_node_revision()
	RETURNS TRIGGER AS $$
BEGIN
	IF EXISTS (
		SELECT FROM /*:cms.prefix:*/nodes n
		LEFT JOIN /*:cms.prefix:*/revisions r ON r.revision = n.revision AND r.node = n.node
		WHERE n.node = NEW.node AND (r.revision IS NULL OR r.content IS DISTINCT FROM n.content)
	) THEN
		RAISE EXCEPTION 'Node % does not hold the content of its main revision', NEW.node
			USING ERRCODE = 'integrity_constraint_violation';
	END IF;

	RETURN NULL;
END;
$$ LANGUAGE plpgsql;
CREATE CONSTRAINT TRIGGER /*:cms.obj:*/nodes_trigger_02_revision
	AFTER INSERT OR UPDATE OF content, revision ON /*:cms.prefix:*/nodes
	DEFERRABLE INITIALLY DEFERRED
	FOR EACH ROW EXECUTE FUNCTION /*:cms.prefix:*/check_node_revision();

CREATE FUNCTION /*:cms.prefix:*/check_revision_content()
	RETURNS TRIGGER AS $$
BEGIN
	IF EXISTS (
		SELECT FROM /*:cms.prefix:*/nodes n
		JOIN /*:cms.prefix:*/revisions r ON r.revision = n.revision
		WHERE n.revision = NEW.revision AND r.content IS DISTINCT FROM n.content
	) THEN
		RAISE EXCEPTION 'Revision % is the main version of a node with other content', NEW.revision
			USING ERRCODE = 'integrity_constraint_violation';
	END IF;

	RETURN NULL;
END;
$$ LANGUAGE plpgsql;
CREATE CONSTRAINT TRIGGER /*:cms.obj:*/revisions_trigger_01_main
	AFTER UPDATE OF content ON /*:cms.prefix:*/revisions
	DEFERRABLE INITIALLY DEFERRED
	FOR EACH ROW EXECUTE FUNCTION /*:cms.prefix:*/check_revision_content();
