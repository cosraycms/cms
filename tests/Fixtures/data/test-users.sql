-- Test users for integration testing
-- Password for all users: 'password'

-- Test superuser
INSERT INTO cms.users (
    uid,
    username,
    email,
    password,
    rolename,
    active,
    data,
    creator,
    editor
) VALUES (
    'test-superuser',
    'test-superuser',
    'superuser@example.com',
    '$argon2id$v=19$m=65536,t=4,p=1$ZGZuVmhYbTlwZ0g0VjNkSg$xVLvB0L8B9Gm6F8aB5vBxQ0L8B9Gm6F8aB5vBxQ0L8B',
    'superuser',
    true,
    '{"name":"Test Superuser"}'::jsonb,
    1,
    1
)
ON CONFLICT (uid) DO NOTHING;

-- Test admin user
INSERT INTO cms.users (
    uid,
    username,
    email,
    password,
    rolename,
    active,
    data,
    creator,
    editor
) VALUES (
    'test-admin',
    'test-admin',
    'admin@example.com',
    '$argon2id$v=19$m=65536,t=4,p=1$ZGZuVmhYbTlwZ0g0VjNkSg$xVLvB0L8B9Gm6F8aB5vBxQ0L8B9Gm6F8aB5vBxQ0L8B',
    'admin',
    true,
    '{"name":"Test Admin"}'::jsonb,
    1,
    1
)
ON CONFLICT (uid) DO NOTHING;

-- Test editor user
INSERT INTO cms.users (
    uid,
    username,
    email,
    password,
    rolename,
    active,
    data,
    creator,
    editor
) VALUES (
    'test-editor',
    'test-editor',
    'editor@example.com',
    '$argon2id$v=19$m=65536,t=4,p=1$ZGZuVmhYbTlwZ0g0VjNkSg$xVLvB0L8B9Gm6F8aB5vBxQ0L8B9Gm6F8aB5vBxQ0L8B',
    'editor',
    true,
    '{"name":"Test Editor"}'::jsonb,
    1,
    1
)
ON CONFLICT (uid) DO NOTHING;
