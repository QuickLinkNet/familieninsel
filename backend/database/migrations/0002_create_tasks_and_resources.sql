CREATE TABLE resources (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    icon_key TEXT NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE family_resources (
    family_id INTEGER NOT NULL REFERENCES families (id) ON DELETE CASCADE,
    resource_id INTEGER NOT NULL REFERENCES resources (id) ON DELETE CASCADE,
    amount INTEGER NOT NULL DEFAULT 0 CHECK (amount >= 0),
    updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now')),
    PRIMARY KEY (family_id, resource_id)
);

CREATE TABLE tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    family_id INTEGER NOT NULL REFERENCES families (id) ON DELETE CASCADE,
    assigned_player_id INTEGER NOT NULL REFERENCES players (id),
    created_by_player_id INTEGER NOT NULL REFERENCES players (id),
    title TEXT NOT NULL,
    description TEXT,
    status TEXT NOT NULL DEFAULT 'open'
        CHECK (status IN ('open', 'completed_pending', 'approved', 'rejected', 'cancelled')),
    due_date TEXT,
    recurrence_type TEXT NOT NULL DEFAULT 'once'
        CHECK (recurrence_type IN ('once', 'daily', 'weekly', 'weekdays')),
    parent_note TEXT,
    completed_at TEXT,
    approved_at TEXT,
    approved_by_player_id INTEGER REFERENCES players (id),
    rewarded_at TEXT,
    created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now')),
    updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now'))
);

CREATE INDEX idx_tasks_family_id ON tasks (family_id);
CREATE INDEX idx_tasks_assigned_player_id ON tasks (assigned_player_id);

CREATE TABLE task_rewards (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id INTEGER NOT NULL REFERENCES tasks (id) ON DELETE CASCADE,
    resource_id INTEGER NOT NULL REFERENCES resources (id),
    amount INTEGER NOT NULL CHECK (amount >= 0 AND amount <= 99)
);

CREATE INDEX idx_task_rewards_task_id ON task_rewards (task_id);

CREATE TABLE resource_transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    family_id INTEGER NOT NULL REFERENCES families (id) ON DELETE CASCADE,
    player_id INTEGER REFERENCES players (id),
    resource_id INTEGER NOT NULL REFERENCES resources (id),
    amount INTEGER NOT NULL,
    transaction_type TEXT NOT NULL,
    reference_type TEXT,
    reference_id INTEGER,
    description TEXT,
    created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now'))
);

CREATE INDEX idx_resource_transactions_family_id ON resource_transactions (family_id);

CREATE TABLE activity_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    family_id INTEGER NOT NULL REFERENCES families (id) ON DELETE CASCADE,
    player_id INTEGER REFERENCES players (id),
    event_type TEXT NOT NULL,
    message TEXT NOT NULL,
    metadata_json TEXT,
    created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now'))
);

CREATE INDEX idx_activity_log_family_id ON activity_log (family_id);
