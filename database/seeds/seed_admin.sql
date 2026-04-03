-- Seed: Create default admin user
-- Password: 24AdaPlace (bcrypt hash)
-- Date: 2026-04-03

INSERT INTO `users` (`username`, `email`, `password`, `role`) VALUES
('admin', 'email4johnson@gmail.com', '$2y$10$placeholder_will_be_set_by_php', 'admin')
ON DUPLICATE KEY UPDATE `username` = `username`;
