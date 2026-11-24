PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS admins (
    id INTEGER PRIMARY KEY,
    username TEXT NOT NULL UNIQUE COLLATE NOCASE,
    password_hash TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS login_attempts (
    bucket TEXT PRIMARY KEY,
    attempts INTEGER NOT NULL,
    expires INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS templates (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    style TEXT NOT NULL,
    role TEXT NOT NULL,
    html TEXT NOT NULL,
    animated INTEGER NOT NULL DEFAULT 0 CHECK (animated IN (0, 1)),
    visible INTEGER NOT NULL DEFAULT 1 CHECK (visible IN (0, 1)),
    accent TEXT NOT NULL DEFAULT '#18634f',
    motion TEXT NOT NULL DEFAULT 'dots'
);

CREATE TABLE IF NOT EXISTS posts (
    id INTEGER PRIMARY KEY,
    title TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    excerpt TEXT NOT NULL DEFAULT '',
    content TEXT NOT NULL,
    cover TEXT NOT NULL DEFAULT '',
    meta_title TEXT NOT NULL DEFAULT '',
    meta_description TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'published', 'hidden')),
    published_at TEXT,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    category TEXT NOT NULL DEFAULT '',
    art TEXT NOT NULL DEFAULT ''
);

CREATE INDEX IF NOT EXISTS posts_public_order ON posts (status, published_at DESC, id DESC);

CREATE TABLE IF NOT EXISTS ads (
    placement TEXT PRIMARY KEY,
    mode TEXT NOT NULL DEFAULT 'banner' CHECK (mode IN ('banner', 'adsense')),
    enabled INTEGER NOT NULL DEFAULT 0 CHECK (enabled IN (0, 1)),
    code TEXT NOT NULL DEFAULT '',
    image TEXT NOT NULL DEFAULT '',
    target TEXT NOT NULL DEFAULT '',
    alt TEXT NOT NULL DEFAULT '',
    clicks INTEGER NOT NULL DEFAULT 0,
    headline TEXT NOT NULL DEFAULT '',
    description TEXT NOT NULL DEFAULT '',
    button_text TEXT NOT NULL DEFAULT '',
    tone TEXT NOT NULL DEFAULT 'forest',
    gallery TEXT NOT NULL DEFAULT '[]',
    transition TEXT NOT NULL DEFAULT 'fade'
);

INSERT OR IGNORE INTO ads (placement) VALUES ('header'), ('preview'), ('article'), ('footer'), ('left'), ('right');

CREATE TABLE IF NOT EXISTS contact_messages (
    id INTEGER PRIMARY KEY,
    reference TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    subject TEXT NOT NULL,
    message TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at TEXT
);

CREATE TABLE IF NOT EXISTS contact_limits (
    bucket TEXT PRIMARY KEY,
    attempts INTEGER NOT NULL,
    expires INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS activity_counts (
    day TEXT NOT NULL,
    event TEXT NOT NULL,
    entity TEXT NOT NULL,
    total INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (event, day, entity)
);

CREATE TABLE IF NOT EXISTS brand_upload_limits (
    bucket TEXT PRIMARY KEY,
    attempts INTEGER NOT NULL,
    expires INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS brand_uploads (
    digest TEXT PRIMARY KEY,
    filename TEXT NOT NULL UNIQUE,
    bytes INTEGER NOT NULL
);
