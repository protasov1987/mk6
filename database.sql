CREATE TABLE IF NOT EXISTS app_state (
  id INTEGER PRIMARY KEY,
  data LONGTEXT NOT NULL
);

ALTER TABLE app_state ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
CREATE INDEX idx_app_state_id ON app_state (id);

INSERT INTO app_state (id, data)
SELECT 1, '{}'
WHERE NOT EXISTS (SELECT 1 FROM app_state WHERE id = 1);

UPDATE app_state SET updated_at = CURRENT_TIMESTAMP WHERE updated_at IS NULL;
