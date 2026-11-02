-- Campus Lost and Found System Seed Data

INSERT INTO `categories` (`category_name`, `is_active`) VALUES
('Electronics', 1),
('Documents', 1),
('Bags', 1),
('Accessories', 1),
('Clothing', 1),
('Keys', 1),
('Other', 1);

-- Default Users for each role (Password for all: Password1234!)
INSERT INTO `users` (`student_id`, `full_name`, `email`, `phone`, `password_hash`, `role`, `account_status`) VALUES
('673380001-1', 'ผู้ดูแลระบบ ทดสอบ', 'admin@campus.local', '0811111111', '$2y$10$J/awl7uMLhYaxX93ny1j1O.Wh66u8CKxHYRd3eiI7yUwxO26PFOKi', 'admin', 'active'),
('673380002-2', 'สมชาย เจ้าหน้าที่', 'staff@campus.local', '0822222222', '$2y$10$J/awl7uMLhYaxX93ny1j1O.Wh66u8CKxHYRd3eiI7yUwxO26PFOKi', 'staff', 'active'),
('673380003-3', 'สมศักดิ์ นักศึกษา', 'student@campus.local', '0833333333', '$2y$10$J/awl7uMLhYaxX93ny1j1O.Wh66u8CKxHYRd3eiI7yUwxO26PFOKi', 'student', 'active')
ON DUPLICATE KEY UPDATE `role` = VALUES(`role`);
