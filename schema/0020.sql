ALTER TABLE users
CHANGE `blacklist` `blocklist` TEXT CHARACTER SET utf8mb4;
ALTER TABLE users
CHANGE `whitelist` `allowlist` TEXT CHARACTER SET utf8mb4;
