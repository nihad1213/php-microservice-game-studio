CREATE DATABASE IF NOT EXISTS developer_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS team_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS game_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'developer_svc'@'%' IDENTIFIED BY 'DevDatabase';
CREATE USER IF NOT EXISTS 'team_svc'@'%' IDENTIFIED BY 'TeamDatabase';
CREATE USER IF NOT EXISTS 'game_svc'@'%' IDENTIFIED BY 'GameDatabase';

GRANT ALL PRIVILEGES ON developer_db.* TO 'developer_svc'@'%';
GRANT ALL PRIVILEGES ON team_db.* TO 'team_svc'@'%';
GRANT ALL PRIVILEGES ON game_db.*TO 'game_svc'@'%';

FLUSH PRIVILEGES;