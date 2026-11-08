CREATE DATABASE IF NOT EXISTS web_project_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE web_project_db;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(80) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY users_username_unique (username),
    UNIQUE KEY users_email_unique (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    KEY items_user_id_index (user_id),
    CONSTRAINT items_user_id_foreign
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO users (username, email, password)
VALUES ('Demo User', 'demo@example.com', '$2y$10$ZMU/vNRnlOAMO0t1jF8SB.kkdkCRxPMclZe73MGP/1c9XxV5An9h6')
ON DUPLICATE KEY UPDATE id = id;

INSERT INTO items (title, description, user_id)
SELECT 'Make space to think', 'A short pause can bring the important things back into focus.', id
FROM users
WHERE email = 'demo@example.com'
  AND NOT EXISTS (
      SELECT 1
      FROM items
      WHERE user_id = users.id
        AND title = 'Make space to think'
  );

INSERT INTO items (title, description, user_id)
SELECT 'Choose one next step', 'Turn a big idea into one small, clear action.', id
FROM users
WHERE email = 'demo@example.com'
  AND NOT EXISTS (
      SELECT 1
      FROM items
      WHERE user_id = users.id
        AND title = 'Choose one next step'
  );
