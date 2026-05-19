-- Sample nodes for integration testing
-- Assumes basic-types.sql has been loaded

-- Get the type IDs (these should exist after loading basic-types.sql)
DO $$
DECLARE
    test_home_type BIGINT;
    test_page_type BIGINT;
    test_article_type BIGINT;
    test_block_type BIGINT;
BEGIN
    SELECT type INTO test_home_type FROM cms.types WHERE handle = 'test-home' LIMIT 1;
    SELECT type INTO test_page_type FROM cms.types WHERE handle = 'test-page' LIMIT 1;
    SELECT type INTO test_article_type FROM cms.types WHERE handle = 'test-article' LIMIT 1;
    SELECT type INTO test_block_type FROM cms.types WHERE handle = 'test-block' LIMIT 1;

    -- Test homepage
    INSERT INTO cms.nodes (uid, parent, published, hidden, locked, type, creator, editor, content)
    VALUES (
        'test-homepage',
        NULL,
        true,
        false,
        false,
        test_home_type,
        1,  -- System user
        1,
        jsonb_build_object(
            'title', jsonb_build_object(
                'type', 'text',
                'value', jsonb_build_object(
                    'de', 'Testhomepage',
                    'en', 'Test Homepage'
                )
            ),
            'content', jsonb_build_object(
                'type', 'richtext',
                'value', jsonb_build_object(
                    'de', '<p>Willkommen auf der Testseite</p>',
                    'en', '<p>Welcome to the test page</p>'
                )
            )
        )
    )
    ON CONFLICT (uid) DO NOTHING;

    -- Add URL path for homepage
    INSERT INTO cms.url_paths (node, path, locale, creator, editor)
    SELECT
        (SELECT node FROM cms.nodes WHERE uid = 'test-homepage'),
        '/',
        'en',
        1,
        1
    WHERE EXISTS (SELECT 1 FROM cms.nodes WHERE uid = 'test-homepage')
    ON CONFLICT DO NOTHING;

    -- Test article 1 (published)
    INSERT INTO cms.nodes (uid, parent, published, hidden, locked, type, creator, editor, content)
    VALUES (
        'test-article-1',
        NULL,
        true,
        false,
        false,
        test_article_type,
        1,
        1,
        jsonb_build_object(
            'title', jsonb_build_object(
                'type', 'text',
                'value', jsonb_build_object(
                    'de', 'Testartikel 1',
                    'en', 'Test Article 1'
                )
            ),
            'body', jsonb_build_object(
                'type', 'richtext',
                'value', jsonb_build_object(
                    'de', '<p>Inhalt des ersten Testartikels</p>',
                    'en', '<p>Content of the first test article</p>'
                )
            )
        )
    )
    ON CONFLICT (uid) DO NOTHING;

    -- Test article 2 (unpublished/draft)
    INSERT INTO cms.nodes (uid, parent, published, hidden, locked, type, creator, editor, content)
    VALUES (
        'test-article-2',
        NULL,
        false,  -- unpublished
        false,
        false,
        test_article_type,
        1,
        1,
        jsonb_build_object(
            'title', jsonb_build_object(
                'type', 'text',
                'value', jsonb_build_object(
                    'de', 'Testartikel 2 (Entwurf)',
                    'en', 'Test Article 2 (Draft)'
                )
            ),
            'body', jsonb_build_object(
                'type', 'richtext',
                'value', jsonb_build_object(
                    'de', '<p>Dies ist ein unveröffentlichter Artikel</p>',
                    'en', '<p>This is an unpublished article</p>'
                )
            )
        )
    )
    ON CONFLICT (uid) DO NOTHING;

    -- Test block
    INSERT INTO cms.nodes (uid, parent, published, hidden, locked, type, creator, editor, content)
    VALUES (
        'test-block-1',
        NULL,
        true,
        false,
        false,
        test_block_type,
        1,
        1,
        jsonb_build_object(
            'content', jsonb_build_object(
                'type', 'richtext',
                'value', jsonb_build_object(
                    'de', '<div class="test-block">Testblock Inhalt</div>',
                    'en', '<div class="test-block">Test block content</div>'
                )
            )
        )
    )
    ON CONFLICT (uid) DO NOTHING;
END $$;
