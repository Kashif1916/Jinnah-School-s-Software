-- SFMS Database Backup
-- Generated on: 2026-07-13 09:15:18

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `dropped_students`;
CREATE TABLE `dropped_students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `dropped_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `dropped_by` varchar(50) NOT NULL,
  `drop_reason` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `dropped_students_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `amount` decimal(10,2) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `expenses` (`id`, `amount`, `reason`, `user_id`, `username`, `created_at`) VALUES ('2', '400.00', 'Stationary work k liyea liyea', '2', 'Main office', '2026-06-30 11:40:08');


DROP TABLE IF EXISTS `fee_records`;
CREATE TABLE `fee_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `month` varchar(20) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('paid','unpaid') DEFAULT 'unpaid',
  `payment_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_month` (`student_id`,`month`),
  KEY `idx_fee_status` (`status`),
  KEY `idx_fee_month` (`month`),
  CONSTRAINT `fee_records_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=717 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('384', '32', 'Jun-2026', '0.00', 'paid', '2026-06-30 08:38:11', '2026-06-30 09:39:37');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('385', '32', 'Jul-2026', '0.00', 'paid', '2026-06-30 08:38:11', '2026-06-30 09:39:37');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('386', '32', 'Aug-2026', '0.00', 'paid', '2026-06-30 08:38:11', '2026-06-30 09:39:37');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('387', '32', 'Sep-2026', '0.00', 'paid', '2026-06-30 08:38:11', '2026-06-30 09:39:37');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('388', '32', 'Oct-2026', '0.00', 'paid', '2026-06-30 08:38:11', '2026-06-30 09:39:37');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('389', '32', 'Nov-2026', '0.00', 'paid', '2026-06-30 08:38:11', '2026-06-30 09:39:37');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('390', '32', 'Dec-2026', '0.00', 'paid', '2026-06-30 08:38:11', '2026-06-30 09:39:37');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('391', '32', 'Jan-2027', '0.00', 'paid', '2026-06-30 08:38:11', '2026-06-30 09:39:37');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('392', '32', 'Feb-2027', '0.00', 'paid', '2026-06-30 08:39:36', '2026-06-30 09:39:37');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('393', '32', 'Mar-2027', '0.00', 'paid', '2026-06-30 08:39:36', '2026-06-30 09:39:37');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('394', '32', 'Apr-2027', '0.00', 'paid', '2026-07-11 13:07:58', '2026-06-30 09:39:37');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('395', '32', 'May-2027', '0.00', 'paid', '2026-07-11 13:07:58', '2026-06-30 09:39:37');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('396', '33', 'Jun-2026', '0.00', 'paid', '2026-07-10 11:52:35', '2026-06-30 11:12:20');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('397', '33', 'Jul-2026', '0.00', 'paid', '2026-07-10 11:52:35', '2026-06-30 11:12:20');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('398', '33', 'Aug-2026', '2200.00', 'unpaid', NULL, '2026-06-30 11:12:20');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('399', '33', 'Sep-2026', '2200.00', 'unpaid', NULL, '2026-06-30 11:12:20');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('400', '33', 'Oct-2026', '2200.00', 'unpaid', NULL, '2026-06-30 11:12:20');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('401', '33', 'Nov-2026', '2200.00', 'unpaid', NULL, '2026-06-30 11:12:20');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('402', '33', 'Dec-2026', '2200.00', 'unpaid', NULL, '2026-06-30 11:12:20');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('403', '33', 'Jan-2027', '2200.00', 'unpaid', NULL, '2026-06-30 11:12:20');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('404', '33', 'Feb-2027', '2200.00', 'unpaid', NULL, '2026-06-30 11:12:20');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('405', '33', 'Mar-2027', '2200.00', 'unpaid', NULL, '2026-06-30 11:12:20');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('406', '33', 'Apr-2027', '2200.00', 'unpaid', NULL, '2026-06-30 11:12:20');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('407', '33', 'May-2027', '2200.00', 'unpaid', NULL, '2026-06-30 11:12:20');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('408', '34', 'Jun-2026', '0.00', 'paid', '2026-07-02 05:58:39', '2026-06-30 11:12:53');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('409', '34', 'Jul-2026', '2200.00', 'unpaid', NULL, '2026-06-30 11:12:53');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('410', '34', 'Aug-2026', '2200.00', 'unpaid', NULL, '2026-06-30 11:12:53');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('411', '34', 'Sep-2026', '2200.00', 'unpaid', NULL, '2026-06-30 11:12:53');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('412', '34', 'Oct-2026', '2200.00', 'unpaid', NULL, '2026-06-30 11:12:53');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('413', '34', 'Nov-2026', '2200.00', 'unpaid', NULL, '2026-06-30 11:12:53');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('414', '34', 'Dec-2026', '2200.00', 'unpaid', NULL, '2026-06-30 11:12:53');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('415', '34', 'Jan-2027', '2200.00', 'unpaid', NULL, '2026-06-30 11:12:53');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('416', '34', 'Feb-2027', '2200.00', 'unpaid', NULL, '2026-06-30 11:12:53');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('417', '34', 'Mar-2027', '2200.00', 'unpaid', NULL, '2026-06-30 11:12:53');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('418', '34', 'Apr-2027', '2200.00', 'unpaid', NULL, '2026-06-30 11:12:53');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('419', '34', 'May-2027', '2200.00', 'unpaid', NULL, '2026-06-30 11:12:53');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('420', '35', 'Jun-2026', '2700.00', 'unpaid', NULL, '2026-06-30 11:13:23');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('421', '35', 'Jul-2026', '2500.00', 'unpaid', NULL, '2026-06-30 11:13:23');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('422', '35', 'Aug-2026', '2500.00', 'unpaid', NULL, '2026-06-30 11:13:23');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('423', '35', 'Sep-2026', '2500.00', 'unpaid', NULL, '2026-06-30 11:13:23');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('424', '35', 'Oct-2026', '2500.00', 'unpaid', NULL, '2026-06-30 11:13:23');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('425', '35', 'Nov-2026', '2500.00', 'unpaid', NULL, '2026-06-30 11:13:23');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('426', '35', 'Dec-2026', '2500.00', 'unpaid', NULL, '2026-06-30 11:13:23');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('427', '35', 'Jan-2027', '2500.00', 'unpaid', NULL, '2026-06-30 11:13:23');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('428', '35', 'Feb-2027', '2500.00', 'unpaid', NULL, '2026-06-30 11:13:23');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('429', '35', 'Mar-2027', '2500.00', 'unpaid', NULL, '2026-06-30 11:13:23');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('430', '35', 'Apr-2027', '2500.00', 'unpaid', NULL, '2026-06-30 11:13:23');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('431', '35', 'May-2027', '2500.00', 'unpaid', NULL, '2026-06-30 11:13:23');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('432', '36', 'Jun-2026', '0.00', 'paid', '2026-07-11 13:07:58', '2026-06-30 11:13:54');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('433', '36', 'Jul-2026', '0.00', 'paid', '2026-07-11 13:07:58', '2026-06-30 11:13:54');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('434', '36', 'Aug-2026', '2500.00', 'unpaid', NULL, '2026-06-30 11:13:54');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('435', '36', 'Sep-2026', '2500.00', 'unpaid', NULL, '2026-06-30 11:13:54');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('436', '36', 'Oct-2026', '2500.00', 'unpaid', NULL, '2026-06-30 11:13:54');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('437', '36', 'Nov-2026', '2500.00', 'unpaid', NULL, '2026-06-30 11:13:54');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('438', '36', 'Dec-2026', '2500.00', 'unpaid', NULL, '2026-06-30 11:13:54');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('439', '36', 'Jan-2027', '2500.00', 'unpaid', NULL, '2026-06-30 11:13:54');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('440', '36', 'Feb-2027', '2500.00', 'unpaid', NULL, '2026-06-30 11:13:54');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('441', '36', 'Mar-2027', '2500.00', 'unpaid', NULL, '2026-06-30 11:13:54');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('442', '36', 'Apr-2027', '2500.00', 'unpaid', NULL, '2026-06-30 11:13:54');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('443', '36', 'May-2027', '2500.00', 'unpaid', NULL, '2026-06-30 11:13:54');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('444', '37', 'Jun-2026', '0.00', 'paid', '2026-07-11 14:43:48', '2026-06-30 11:14:46');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('445', '37', 'Jul-2026', '-800.00', 'unpaid', NULL, '2026-06-30 11:14:46');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('446', '37', 'Aug-2026', '-800.00', 'unpaid', NULL, '2026-06-30 11:14:46');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('447', '37', 'Sep-2026', '-800.00', 'unpaid', NULL, '2026-06-30 11:14:46');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('448', '37', 'Oct-2026', '-800.00', 'unpaid', NULL, '2026-06-30 11:14:46');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('449', '37', 'Nov-2026', '-800.00', 'unpaid', NULL, '2026-06-30 11:14:46');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('450', '37', 'Dec-2026', '-800.00', 'unpaid', NULL, '2026-06-30 11:14:46');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('451', '37', 'Jan-2027', '-800.00', 'unpaid', NULL, '2026-06-30 11:14:46');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('452', '37', 'Feb-2027', '-800.00', 'unpaid', NULL, '2026-06-30 11:14:46');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('453', '37', 'Mar-2027', '-800.00', 'unpaid', NULL, '2026-06-30 11:14:46');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('454', '37', 'Apr-2027', '-800.00', 'unpaid', NULL, '2026-06-30 11:14:46');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('455', '37', 'May-2027', '-800.00', 'unpaid', NULL, '2026-06-30 11:14:46');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('456', '38', 'Jun-2026', '2750.00', 'unpaid', NULL, '2026-06-30 11:16:07');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('457', '38', 'Jul-2026', '2750.00', 'unpaid', NULL, '2026-06-30 11:16:07');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('458', '38', 'Aug-2026', '2750.00', 'unpaid', NULL, '2026-06-30 11:16:07');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('459', '38', 'Sep-2026', '2750.00', 'unpaid', NULL, '2026-06-30 11:16:07');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('460', '38', 'Oct-2026', '2750.00', 'unpaid', NULL, '2026-06-30 11:16:07');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('461', '38', 'Nov-2026', '2750.00', 'unpaid', NULL, '2026-06-30 11:16:07');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('462', '38', 'Dec-2026', '2750.00', 'unpaid', NULL, '2026-06-30 11:16:07');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('463', '38', 'Jan-2027', '2750.00', 'unpaid', NULL, '2026-06-30 11:16:07');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('464', '38', 'Feb-2027', '2750.00', 'unpaid', NULL, '2026-06-30 11:16:07');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('465', '38', 'Mar-2027', '2750.00', 'unpaid', NULL, '2026-06-30 11:16:07');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('466', '38', 'Apr-2027', '2750.00', 'unpaid', NULL, '2026-06-30 11:16:07');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('467', '38', 'May-2027', '2750.00', 'unpaid', NULL, '2026-06-30 11:16:07');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('468', '39', 'Jun-2026', '3000.00', 'unpaid', NULL, '2026-06-30 11:17:00');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('469', '39', 'Jul-2026', '3000.00', 'unpaid', NULL, '2026-06-30 11:17:00');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('470', '39', 'Aug-2026', '3000.00', 'unpaid', NULL, '2026-06-30 11:17:00');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('471', '39', 'Sep-2026', '3000.00', 'unpaid', NULL, '2026-06-30 11:17:00');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('472', '39', 'Oct-2026', '3000.00', 'unpaid', NULL, '2026-06-30 11:17:00');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('473', '39', 'Nov-2026', '3000.00', 'unpaid', NULL, '2026-06-30 11:17:00');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('474', '39', 'Dec-2026', '3000.00', 'unpaid', NULL, '2026-06-30 11:17:00');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('475', '39', 'Jan-2027', '3000.00', 'unpaid', NULL, '2026-06-30 11:17:00');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('476', '39', 'Feb-2027', '3000.00', 'unpaid', NULL, '2026-06-30 11:17:00');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('477', '39', 'Mar-2027', '3000.00', 'unpaid', NULL, '2026-06-30 11:17:00');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('478', '39', 'Apr-2027', '3000.00', 'unpaid', NULL, '2026-06-30 11:17:00');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('479', '39', 'May-2027', '3000.00', 'unpaid', NULL, '2026-06-30 11:17:00');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('480', '40', 'Jun-2026', '3000.00', 'unpaid', NULL, '2026-06-30 11:17:29');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('481', '40', 'Jul-2026', '3000.00', 'unpaid', NULL, '2026-06-30 11:17:29');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('482', '40', 'Aug-2026', '3000.00', 'unpaid', NULL, '2026-06-30 11:17:29');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('483', '40', 'Sep-2026', '3000.00', 'unpaid', NULL, '2026-06-30 11:17:29');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('484', '40', 'Oct-2026', '3000.00', 'unpaid', NULL, '2026-06-30 11:17:29');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('485', '40', 'Nov-2026', '3000.00', 'unpaid', NULL, '2026-06-30 11:17:29');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('486', '40', 'Dec-2026', '3000.00', 'unpaid', NULL, '2026-06-30 11:17:29');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('487', '40', 'Jan-2027', '3000.00', 'unpaid', NULL, '2026-06-30 11:17:29');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('488', '40', 'Feb-2027', '3000.00', 'unpaid', NULL, '2026-06-30 11:17:29');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('489', '40', 'Mar-2027', '3000.00', 'unpaid', NULL, '2026-06-30 11:17:29');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('490', '40', 'Apr-2027', '3000.00', 'unpaid', NULL, '2026-06-30 11:17:29');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('491', '40', 'May-2027', '3000.00', 'unpaid', NULL, '2026-06-30 11:17:29');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('492', '41', 'Jun-2026', '3300.00', 'unpaid', NULL, '2026-06-30 11:18:18');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('493', '41', 'Jul-2026', '3300.00', 'unpaid', NULL, '2026-06-30 11:18:18');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('494', '41', 'Aug-2026', '3300.00', 'unpaid', NULL, '2026-06-30 11:18:18');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('495', '41', 'Sep-2026', '3300.00', 'unpaid', NULL, '2026-06-30 11:18:18');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('496', '41', 'Oct-2026', '3300.00', 'unpaid', NULL, '2026-06-30 11:18:18');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('497', '41', 'Nov-2026', '3300.00', 'unpaid', NULL, '2026-06-30 11:18:18');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('498', '41', 'Dec-2026', '3300.00', 'unpaid', NULL, '2026-06-30 11:18:18');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('499', '41', 'Jan-2027', '3300.00', 'unpaid', NULL, '2026-06-30 11:18:18');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('500', '41', 'Feb-2027', '3300.00', 'unpaid', NULL, '2026-06-30 11:18:18');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('501', '41', 'Mar-2027', '3300.00', 'unpaid', NULL, '2026-06-30 11:18:18');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('502', '41', 'Apr-2027', '3300.00', 'unpaid', NULL, '2026-06-30 11:18:18');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('503', '41', 'May-2027', '3300.00', 'unpaid', NULL, '2026-06-30 11:18:18');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('504', '32', 'Jun-2027', '0.00', 'paid', '2026-07-11 13:07:58', '2026-06-30 11:38:11');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('505', '32', 'Jul-2027', '2200.00', 'unpaid', NULL, '2026-06-30 11:38:11');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('506', '32', 'Aug-2027', '2200.00', 'unpaid', NULL, '2026-06-30 11:38:11');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('507', '32', 'Sep-2027', '2200.00', 'unpaid', NULL, '2026-06-30 11:38:11');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('508', '32', 'Oct-2027', '2200.00', 'unpaid', NULL, '2026-06-30 11:38:11');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('509', '42', 'Jun-2026', '0.00', 'paid', '2026-06-30 09:07:59', '2026-06-30 12:07:04');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('510', '42', 'Jul-2026', '0.00', 'paid', '2026-06-30 09:07:59', '2026-06-30 12:07:04');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('511', '42', 'Aug-2026', '0.00', 'paid', '2026-07-07 06:33:16', '2026-06-30 12:07:04');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('512', '42', 'Sep-2026', '0.00', 'paid', '2026-07-07 06:33:16', '2026-06-30 12:07:04');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('513', '42', 'Oct-2026', '0.00', 'paid', '2026-07-07 06:34:22', '2026-06-30 12:07:04');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('514', '42', 'Nov-2026', '0.00', 'paid', '2026-07-07 06:34:22', '2026-06-30 12:07:04');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('515', '42', 'Dec-2026', '2500.00', 'unpaid', NULL, '2026-06-30 12:07:04');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('516', '42', 'Jan-2027', '2500.00', 'unpaid', NULL, '2026-06-30 12:07:04');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('517', '42', 'Feb-2027', '2500.00', 'unpaid', NULL, '2026-06-30 12:07:04');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('518', '42', 'Mar-2027', '2500.00', 'unpaid', NULL, '2026-06-30 12:07:04');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('519', '42', 'Apr-2027', '2500.00', 'unpaid', NULL, '2026-06-30 12:07:04');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('520', '42', 'May-2027', '2500.00', 'unpaid', NULL, '2026-06-30 12:07:04');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('521', '43', 'Jul-2026', '3400.00', 'unpaid', NULL, '2026-07-06 10:43:01');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('522', '43', 'Aug-2026', '3400.00', 'unpaid', NULL, '2026-07-06 10:43:01');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('523', '43', 'Sep-2026', '3400.00', 'unpaid', NULL, '2026-07-06 10:43:01');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('524', '43', 'Oct-2026', '3400.00', 'unpaid', NULL, '2026-07-06 10:43:01');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('525', '43', 'Nov-2026', '3400.00', 'unpaid', NULL, '2026-07-06 10:43:01');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('526', '43', 'Dec-2026', '3400.00', 'unpaid', NULL, '2026-07-06 10:43:01');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('527', '43', 'Jan-2027', '3400.00', 'unpaid', NULL, '2026-07-06 10:43:01');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('528', '43', 'Feb-2027', '3400.00', 'unpaid', NULL, '2026-07-06 10:43:01');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('529', '43', 'Mar-2027', '3400.00', 'unpaid', NULL, '2026-07-06 10:43:01');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('530', '43', 'Apr-2027', '3400.00', 'unpaid', NULL, '2026-07-06 10:43:01');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('531', '43', 'May-2027', '3400.00', 'unpaid', NULL, '2026-07-06 10:43:01');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('532', '43', 'Jun-2027', '3400.00', 'unpaid', NULL, '2026-07-06 10:43:01');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('533', '44', 'Jul-2026', '3400.00', 'unpaid', NULL, '2026-07-06 14:28:47');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('534', '44', 'Aug-2026', '3400.00', 'unpaid', NULL, '2026-07-06 14:28:47');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('535', '44', 'Sep-2026', '3400.00', 'unpaid', NULL, '2026-07-06 14:28:47');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('536', '44', 'Oct-2026', '3400.00', 'unpaid', NULL, '2026-07-06 14:28:47');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('537', '44', 'Nov-2026', '3400.00', 'unpaid', NULL, '2026-07-06 14:28:47');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('538', '44', 'Dec-2026', '3400.00', 'unpaid', NULL, '2026-07-06 14:28:47');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('539', '44', 'Jan-2027', '3400.00', 'unpaid', NULL, '2026-07-06 14:28:47');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('540', '44', 'Feb-2027', '3400.00', 'unpaid', NULL, '2026-07-06 14:28:47');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('541', '44', 'Mar-2027', '3400.00', 'unpaid', NULL, '2026-07-06 14:28:47');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('542', '44', 'Apr-2027', '3400.00', 'unpaid', NULL, '2026-07-06 14:28:47');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('543', '44', 'May-2027', '3400.00', 'unpaid', NULL, '2026-07-06 14:28:47');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('544', '44', 'Jun-2027', '3400.00', 'unpaid', NULL, '2026-07-06 14:28:47');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('545', '45', 'Jul-2026', '2500.00', 'unpaid', NULL, '2026-07-06 14:50:09');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('546', '45', 'Aug-2026', '2500.00', 'unpaid', NULL, '2026-07-06 14:50:09');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('547', '45', 'Sep-2026', '2500.00', 'unpaid', NULL, '2026-07-06 14:50:09');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('548', '45', 'Oct-2026', '2500.00', 'unpaid', NULL, '2026-07-06 14:50:09');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('549', '45', 'Nov-2026', '2500.00', 'unpaid', NULL, '2026-07-06 14:50:09');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('550', '45', 'Dec-2026', '2500.00', 'unpaid', NULL, '2026-07-06 14:50:09');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('551', '45', 'Jan-2027', '2500.00', 'unpaid', NULL, '2026-07-06 14:50:09');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('552', '45', 'Feb-2027', '2500.00', 'unpaid', NULL, '2026-07-06 14:50:09');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('553', '45', 'Mar-2027', '2500.00', 'unpaid', NULL, '2026-07-06 14:50:09');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('554', '45', 'Apr-2027', '2500.00', 'unpaid', NULL, '2026-07-06 14:50:09');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('555', '45', 'May-2027', '2500.00', 'unpaid', NULL, '2026-07-06 14:50:09');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('556', '45', 'Jun-2027', '2500.00', 'unpaid', NULL, '2026-07-06 14:50:09');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('557', '46', 'Jul-2026', '3200.00', 'unpaid', NULL, '2026-07-06 14:50:37');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('558', '46', 'Aug-2026', '3200.00', 'unpaid', NULL, '2026-07-06 14:50:37');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('559', '46', 'Sep-2026', '3200.00', 'unpaid', NULL, '2026-07-06 14:50:37');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('560', '46', 'Oct-2026', '3200.00', 'unpaid', NULL, '2026-07-06 14:50:37');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('561', '46', 'Nov-2026', '3200.00', 'unpaid', NULL, '2026-07-06 14:50:37');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('562', '46', 'Dec-2026', '3200.00', 'unpaid', NULL, '2026-07-06 14:50:37');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('563', '46', 'Jan-2027', '3200.00', 'unpaid', NULL, '2026-07-06 14:50:37');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('564', '46', 'Feb-2027', '3200.00', 'unpaid', NULL, '2026-07-06 14:50:37');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('565', '46', 'Mar-2027', '3200.00', 'unpaid', NULL, '2026-07-06 14:50:37');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('566', '46', 'Apr-2027', '3200.00', 'unpaid', NULL, '2026-07-06 14:50:37');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('567', '46', 'May-2027', '3200.00', 'unpaid', NULL, '2026-07-06 14:50:37');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('568', '46', 'Jun-2027', '3200.00', 'unpaid', NULL, '2026-07-06 14:50:37');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('569', '47', 'Jul-2026', '3400.00', 'unpaid', NULL, '2026-07-06 14:51:18');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('570', '47', 'Aug-2026', '3400.00', 'unpaid', NULL, '2026-07-06 14:51:18');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('571', '47', 'Sep-2026', '3400.00', 'unpaid', NULL, '2026-07-06 14:51:18');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('572', '47', 'Oct-2026', '3400.00', 'unpaid', NULL, '2026-07-06 14:51:18');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('573', '47', 'Nov-2026', '3400.00', 'unpaid', NULL, '2026-07-06 14:51:18');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('574', '47', 'Dec-2026', '3400.00', 'unpaid', NULL, '2026-07-06 14:51:18');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('575', '47', 'Jan-2027', '3400.00', 'unpaid', NULL, '2026-07-06 14:51:18');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('576', '47', 'Feb-2027', '3400.00', 'unpaid', NULL, '2026-07-06 14:51:18');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('577', '47', 'Mar-2027', '3400.00', 'unpaid', NULL, '2026-07-06 14:51:18');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('578', '47', 'Apr-2027', '3400.00', 'unpaid', NULL, '2026-07-06 14:51:18');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('579', '47', 'May-2027', '3400.00', 'unpaid', NULL, '2026-07-06 14:51:18');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('580', '47', 'Jun-2027', '3400.00', 'unpaid', NULL, '2026-07-06 14:51:19');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('581', '48', 'Jul-2026', '3400.00', 'unpaid', NULL, '2026-07-06 14:53:18');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('582', '48', 'Aug-2026', '3400.00', 'unpaid', NULL, '2026-07-06 14:53:18');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('583', '48', 'Sep-2026', '3400.00', 'unpaid', NULL, '2026-07-06 14:53:18');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('584', '48', 'Oct-2026', '3400.00', 'unpaid', NULL, '2026-07-06 14:53:18');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('585', '48', 'Nov-2026', '3400.00', 'unpaid', NULL, '2026-07-06 14:53:18');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('586', '48', 'Dec-2026', '3400.00', 'unpaid', NULL, '2026-07-06 14:53:18');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('587', '48', 'Jan-2027', '3400.00', 'unpaid', NULL, '2026-07-06 14:53:18');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('588', '48', 'Feb-2027', '3400.00', 'unpaid', NULL, '2026-07-06 14:53:18');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('589', '48', 'Mar-2027', '3400.00', 'unpaid', NULL, '2026-07-06 14:53:18');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('590', '48', 'Apr-2027', '3400.00', 'unpaid', NULL, '2026-07-06 14:53:18');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('591', '48', 'May-2027', '3400.00', 'unpaid', NULL, '2026-07-06 14:53:18');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('592', '48', 'Jun-2027', '3400.00', 'unpaid', NULL, '2026-07-06 14:53:18');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('593', '49', 'Jul-2026', '3500.00', 'unpaid', NULL, '2026-07-06 14:54:34');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('594', '49', 'Aug-2026', '3500.00', 'unpaid', NULL, '2026-07-06 14:54:34');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('595', '49', 'Sep-2026', '3500.00', 'unpaid', NULL, '2026-07-06 14:54:34');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('596', '49', 'Oct-2026', '3500.00', 'unpaid', NULL, '2026-07-06 14:54:34');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('597', '49', 'Nov-2026', '3500.00', 'unpaid', NULL, '2026-07-06 14:54:34');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('598', '49', 'Dec-2026', '3500.00', 'unpaid', NULL, '2026-07-06 14:54:34');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('599', '49', 'Jan-2027', '3500.00', 'unpaid', NULL, '2026-07-06 14:54:34');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('600', '49', 'Feb-2027', '3500.00', 'unpaid', NULL, '2026-07-06 14:54:34');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('601', '49', 'Mar-2027', '3500.00', 'unpaid', NULL, '2026-07-06 14:54:34');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('602', '49', 'Apr-2027', '3500.00', 'unpaid', NULL, '2026-07-06 14:54:34');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('603', '49', 'May-2027', '3500.00', 'unpaid', NULL, '2026-07-06 14:54:34');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('604', '49', 'Jun-2027', '3500.00', 'unpaid', NULL, '2026-07-06 14:54:34');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('605', '50', 'Jul-2026', '3500.00', 'unpaid', NULL, '2026-07-06 15:05:33');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('606', '50', 'Aug-2026', '3500.00', 'unpaid', NULL, '2026-07-06 15:05:33');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('607', '50', 'Sep-2026', '3500.00', 'unpaid', NULL, '2026-07-06 15:05:33');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('608', '50', 'Oct-2026', '3500.00', 'unpaid', NULL, '2026-07-06 15:05:33');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('609', '50', 'Nov-2026', '3500.00', 'unpaid', NULL, '2026-07-06 15:05:33');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('610', '50', 'Dec-2026', '3500.00', 'unpaid', NULL, '2026-07-06 15:05:33');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('611', '50', 'Jan-2027', '3500.00', 'unpaid', NULL, '2026-07-06 15:05:33');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('612', '50', 'Feb-2027', '3500.00', 'unpaid', NULL, '2026-07-06 15:05:33');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('613', '50', 'Mar-2027', '3500.00', 'unpaid', NULL, '2026-07-06 15:05:33');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('614', '50', 'Apr-2027', '3500.00', 'unpaid', NULL, '2026-07-06 15:05:33');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('615', '50', 'May-2027', '3500.00', 'unpaid', NULL, '2026-07-06 15:05:33');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('616', '50', 'Jun-2027', '3500.00', 'unpaid', NULL, '2026-07-06 15:05:33');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('617', '51', 'Jan-2026', '0.00', 'paid', '2026-07-10 11:30:18', '2026-07-07 12:33:19');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('618', '51', 'Feb-2026', '0.00', 'paid', '2026-07-10 11:30:18', '2026-07-07 12:33:19');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('619', '51', 'Mar-2026', '0.00', 'paid', '2026-07-10 11:30:39', '2026-07-07 12:33:19');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('620', '51', 'Apr-2026', '0.00', 'paid', '2026-07-10 11:30:39', '2026-07-07 12:33:19');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('621', '51', 'May-2026', '0.00', 'paid', '2026-07-10 11:34:07', '2026-07-07 12:33:19');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('622', '51', 'Jun-2026', '0.00', 'paid', '2026-07-10 16:44:38', '2026-07-07 12:33:19');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('623', '51', 'Jul-2026', '0.00', 'paid', '2026-07-10 16:45:10', '2026-07-07 12:33:19');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('624', '51', 'Aug-2026', '0.00', 'paid', '2026-07-10 16:52:24', '2026-07-07 12:33:19');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('625', '51', 'Sep-2026', '2400.00', 'unpaid', NULL, '2026-07-07 12:33:19');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('626', '51', 'Oct-2026', '2400.00', 'unpaid', NULL, '2026-07-07 12:33:19');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('627', '51', 'Nov-2026', '2400.00', 'unpaid', NULL, '2026-07-07 12:33:19');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('628', '51', 'Dec-2026', '2400.00', 'unpaid', NULL, '2026-07-07 12:33:19');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('629', '52', 'Jan-2026', '0.00', 'paid', '2026-07-07 09:34:09', '2026-07-07 12:34:09');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('630', '52', 'Feb-2026', '0.00', 'paid', '2026-07-07 09:34:09', '2026-07-07 12:34:09');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('631', '52', 'Mar-2026', '0.00', 'paid', '2026-07-07 09:34:09', '2026-07-07 12:34:09');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('632', '52', 'Apr-2026', '0.00', 'paid', '2026-07-07 09:34:09', '2026-07-07 12:34:09');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('633', '52', 'May-2026', '0.00', 'paid', '2026-07-07 09:34:09', '2026-07-07 12:34:09');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('634', '52', 'Jun-2026', '2200.00', 'unpaid', NULL, '2026-07-07 12:34:09');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('635', '52', 'Jul-2026', '2500.00', 'unpaid', NULL, '2026-07-07 12:34:09');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('636', '52', 'Aug-2026', '2500.00', 'unpaid', NULL, '2026-07-07 12:34:09');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('637', '52', 'Sep-2026', '2500.00', 'unpaid', NULL, '2026-07-07 12:34:09');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('638', '52', 'Oct-2026', '2500.00', 'unpaid', NULL, '2026-07-07 12:34:09');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('639', '52', 'Nov-2026', '2500.00', 'unpaid', NULL, '2026-07-07 12:34:09');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('640', '52', 'Dec-2026', '2500.00', 'unpaid', NULL, '2026-07-07 12:34:09');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('641', '53', 'Admission', '0.00', 'paid', '2026-07-08 11:48:35', '2026-07-08 14:46:45');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('642', '53', 'Jul-2026', '0.00', 'paid', '2026-07-08 11:48:35', '2026-07-08 14:46:45');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('643', '53', 'Aug-2026', '0.00', 'paid', '2026-07-08 11:48:35', '2026-07-08 14:46:45');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('644', '53', 'Sep-2026', '0.00', 'paid', '2026-07-08 11:48:35', '2026-07-08 14:46:45');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('645', '53', 'Oct-2026', '0.00', 'paid', '2026-07-08 11:48:35', '2026-07-08 14:46:45');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('646', '53', 'Nov-2026', '0.00', 'paid', '2026-07-08 11:55:13', '2026-07-08 14:46:45');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('647', '53', 'Dec-2026', '0.00', 'paid', '2026-07-08 11:55:13', '2026-07-08 14:46:46');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('648', '53', 'Jan-2027', '0.00', 'paid', '2026-07-08 11:59:45', '2026-07-08 14:46:46');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('649', '53', 'Feb-2027', '0.00', 'paid', '2026-07-08 12:03:59', '2026-07-08 14:46:46');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('650', '53', 'Mar-2027', '2400.00', 'unpaid', NULL, '2026-07-08 14:46:46');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('651', '53', 'Apr-2027', '2400.00', 'unpaid', NULL, '2026-07-08 14:46:46');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('652', '53', 'May-2027', '2400.00', 'unpaid', NULL, '2026-07-08 14:46:46');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('653', '53', 'Jun-2027', '2400.00', 'unpaid', NULL, '2026-07-08 14:46:46');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('654', '53', 'Jul-2027', '2400.00', 'unpaid', NULL, '2026-07-08 14:59:45');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('655', '53', 'Aug-2027', '2400.00', 'unpaid', NULL, '2026-07-08 14:59:45');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('656', '53', 'Sep-2027', '2400.00', 'unpaid', NULL, '2026-07-08 14:59:45');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('657', '53', 'Oct-2027', '2400.00', 'unpaid', NULL, '2026-07-08 14:59:45');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('658', '53', 'Nov-2027', '2400.00', 'unpaid', NULL, '2026-07-08 14:59:45');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('659', '54', 'Jan-2026', '0.00', 'paid', '2026-07-08 12:14:25', '2026-07-08 15:14:25');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('660', '54', 'Feb-2026', '0.00', 'paid', '2026-07-08 12:14:25', '2026-07-08 15:14:25');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('661', '54', 'Mar-2026', '0.00', 'paid', '2026-07-08 12:14:25', '2026-07-08 15:14:25');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('662', '54', 'Apr-2026', '0.00', 'paid', '2026-07-08 12:14:25', '2026-07-08 15:14:25');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('663', '54', 'May-2026', '0.00', 'paid', '2026-07-08 12:14:25', '2026-07-08 15:14:25');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('664', '54', 'Jun-2026', '0.00', 'paid', '2026-07-08 12:14:25', '2026-07-08 15:14:25');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('665', '54', 'Jul-2026', '0.00', 'paid', '2026-07-08 12:14:25', '2026-07-08 15:14:25');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('666', '54', 'Aug-2026', '0.00', 'paid', '2026-07-08 12:23:10', '2026-07-08 15:14:25');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('667', '54', 'Sep-2026', '0.00', 'paid', '2026-07-08 12:23:10', '2026-07-08 15:14:25');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('668', '54', 'Oct-2026', '0.00', 'paid', '2026-07-08 12:23:10', '2026-07-08 15:14:25');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('669', '54', 'Nov-2026', '0.00', 'paid', '2026-07-08 12:23:10', '2026-07-08 15:14:25');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('670', '54', 'Dec-2026', '0.00', 'paid', '2026-07-08 12:23:10', '2026-07-08 15:14:25');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('671', '54', 'Jan-2027', '3500.00', 'unpaid', NULL, '2026-07-08 15:23:10');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('672', '54', 'Feb-2027', '3500.00', 'unpaid', NULL, '2026-07-08 15:23:10');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('673', '54', 'Mar-2027', '3500.00', 'unpaid', NULL, '2026-07-08 15:23:10');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('674', '54', 'Apr-2027', '3500.00', 'unpaid', NULL, '2026-07-08 15:23:10');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('675', '54', 'May-2027', '3500.00', 'unpaid', NULL, '2026-07-08 15:23:10');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('676', '54', 'Jun-2027', '3500.00', 'unpaid', NULL, '2026-07-08 15:23:10');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('677', '54', 'Jul-2027', '3500.00', 'unpaid', NULL, '2026-07-08 15:23:10');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('678', '54', 'Aug-2027', '3500.00', 'unpaid', NULL, '2026-07-08 15:23:10');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('679', '54', 'Sep-2027', '3500.00', 'unpaid', NULL, '2026-07-08 15:23:10');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('680', '54', 'Oct-2027', '3500.00', 'unpaid', NULL, '2026-07-08 15:23:10');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('681', '51', 'Jan-2027', '2400.00', 'unpaid', NULL, '2026-07-10 16:45:10');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('682', '51', 'Feb-2027', '2400.00', 'unpaid', NULL, '2026-07-10 16:45:10');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('683', '51', 'Mar-2027', '2400.00', 'unpaid', NULL, '2026-07-10 16:45:10');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('684', '51', 'Apr-2027', '2400.00', 'unpaid', NULL, '2026-07-10 16:45:10');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('685', '51', 'May-2027', '2400.00', 'unpaid', NULL, '2026-07-10 16:45:10');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('686', '55', 'Admission', '0.00', 'paid', '2026-07-11 14:39:07', '2026-07-11 13:03:51');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('687', '55', 'Jul-2026', '0.00', 'paid', '2026-07-11 14:39:07', '2026-07-11 13:03:51');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('688', '55', 'Aug-2026', '3000.00', 'unpaid', NULL, '2026-07-11 13:03:51');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('689', '55', 'Sep-2026', '3000.00', 'unpaid', NULL, '2026-07-11 13:03:51');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('690', '55', 'Oct-2026', '3000.00', 'unpaid', NULL, '2026-07-11 13:03:51');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('691', '55', 'Nov-2026', '3000.00', 'unpaid', NULL, '2026-07-11 13:03:51');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('692', '55', 'Dec-2026', '3000.00', 'unpaid', NULL, '2026-07-11 13:03:51');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('693', '55', 'Jan-2027', '3000.00', 'unpaid', NULL, '2026-07-11 13:03:51');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('694', '55', 'Feb-2027', '3000.00', 'unpaid', NULL, '2026-07-11 13:03:51');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('695', '55', 'Mar-2027', '3000.00', 'unpaid', NULL, '2026-07-11 13:03:51');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('696', '55', 'Apr-2027', '3000.00', 'unpaid', NULL, '2026-07-11 13:03:51');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('697', '55', 'May-2027', '3000.00', 'unpaid', NULL, '2026-07-11 13:03:51');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('698', '55', 'Jun-2027', '3000.00', 'unpaid', NULL, '2026-07-11 13:03:51');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('699', '32', 'Nov-2027', '2500.00', 'unpaid', NULL, '2026-07-11 13:07:58');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('700', '32', 'Dec-2027', '2500.00', 'unpaid', NULL, '2026-07-11 13:07:58');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('701', '32', 'Jan-2028', '2500.00', 'unpaid', NULL, '2026-07-11 13:07:58');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('702', '32', 'Feb-2028', '2500.00', 'unpaid', NULL, '2026-07-11 13:07:58');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('703', '32', 'Mar-2028', '2500.00', 'unpaid', NULL, '2026-07-11 13:07:58');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('704', '56', 'Admission', '0.00', 'paid', '2026-07-11 15:03:34', '2026-07-11 15:02:57');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('705', '56', 'Jul-2026', '0.00', 'paid', '2026-07-11 15:03:34', '2026-07-11 15:02:57');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('706', '56', 'Aug-2026', '2500.00', 'unpaid', NULL, '2026-07-11 15:02:57');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('707', '56', 'Sep-2026', '2500.00', 'unpaid', NULL, '2026-07-11 15:02:57');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('708', '56', 'Oct-2026', '2500.00', 'unpaid', NULL, '2026-07-11 15:02:57');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('709', '56', 'Nov-2026', '2500.00', 'unpaid', NULL, '2026-07-11 15:02:57');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('710', '56', 'Dec-2026', '2500.00', 'unpaid', NULL, '2026-07-11 15:02:57');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('711', '56', 'Jan-2027', '2500.00', 'unpaid', NULL, '2026-07-11 15:02:57');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('712', '56', 'Feb-2027', '2500.00', 'unpaid', NULL, '2026-07-11 15:02:57');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('713', '56', 'Mar-2027', '2500.00', 'unpaid', NULL, '2026-07-11 15:02:57');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('714', '56', 'Apr-2027', '2500.00', 'unpaid', NULL, '2026-07-11 15:02:57');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('715', '56', 'May-2027', '2500.00', 'unpaid', NULL, '2026-07-11 15:02:57');
INSERT INTO `fee_records` (`id`, `student_id`, `month`, `amount`, `status`, `payment_date`, `created_at`) VALUES ('716', '56', 'Jun-2027', '2500.00', 'unpaid', NULL, '2026-07-11 15:02:57');


DROP TABLE IF EXISTS `fee_schedule`;
CREATE TABLE `fee_schedule` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class` varchar(50) NOT NULL,
  `fixed_monthly_fee` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `class` (`class`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `fee_schedule` (`id`, `class`, `fixed_monthly_fee`, `created_at`, `updated_at`) VALUES ('1', 'P.G', '2400.00', '2026-06-30 09:11:06', '2026-06-30 09:11:06');
INSERT INTO `fee_schedule` (`id`, `class`, `fixed_monthly_fee`, `created_at`, `updated_at`) VALUES ('2', 'Nursury', '2500.00', '2026-06-30 09:36:27', '2026-06-30 09:36:27');
INSERT INTO `fee_schedule` (`id`, `class`, `fixed_monthly_fee`, `created_at`, `updated_at`) VALUES ('3', 'Pre', '2600.00', '2026-06-30 09:36:35', '2026-06-30 09:36:35');
INSERT INTO `fee_schedule` (`id`, `class`, `fixed_monthly_fee`, `created_at`, `updated_at`) VALUES ('4', '1', '2700.00', '2026-06-30 09:36:42', '2026-06-30 09:36:42');
INSERT INTO `fee_schedule` (`id`, `class`, `fixed_monthly_fee`, `created_at`, `updated_at`) VALUES ('5', '2', '2800.00', '2026-06-30 09:36:48', '2026-06-30 09:36:48');
INSERT INTO `fee_schedule` (`id`, `class`, `fixed_monthly_fee`, `created_at`, `updated_at`) VALUES ('6', '3', '2900.00', '2026-06-30 09:37:02', '2026-06-30 09:37:02');
INSERT INTO `fee_schedule` (`id`, `class`, `fixed_monthly_fee`, `created_at`, `updated_at`) VALUES ('7', '4', '3000.00', '2026-06-30 09:37:08', '2026-06-30 09:37:51');
INSERT INTO `fee_schedule` (`id`, `class`, `fixed_monthly_fee`, `created_at`, `updated_at`) VALUES ('8', '5', '3100.00', '2026-06-30 09:37:18', '2026-06-30 09:37:18');
INSERT INTO `fee_schedule` (`id`, `class`, `fixed_monthly_fee`, `created_at`, `updated_at`) VALUES ('11', '6', '3200.00', '2026-06-30 09:38:05', '2026-06-30 09:38:05');
INSERT INTO `fee_schedule` (`id`, `class`, `fixed_monthly_fee`, `created_at`, `updated_at`) VALUES ('12', '7', '3300.00', '2026-06-30 09:38:11', '2026-06-30 09:38:11');
INSERT INTO `fee_schedule` (`id`, `class`, `fixed_monthly_fee`, `created_at`, `updated_at`) VALUES ('13', '8', '3400.00', '2026-06-30 09:38:16', '2026-06-30 09:38:16');
INSERT INTO `fee_schedule` (`id`, `class`, `fixed_monthly_fee`, `created_at`, `updated_at`) VALUES ('14', '9', '3500.00', '2026-06-30 09:38:49', '2026-06-30 09:38:49');
INSERT INTO `fee_schedule` (`id`, `class`, `fixed_monthly_fee`, `created_at`, `updated_at`) VALUES ('15', '10', '3600.00', '2026-06-30 09:39:00', '2026-06-30 09:39:00');
INSERT INTO `fee_schedule` (`id`, `class`, `fixed_monthly_fee`, `created_at`, `updated_at`) VALUES ('16', '11', '3700.00', '2026-06-30 09:39:05', '2026-06-30 09:39:05');
INSERT INTO `fee_schedule` (`id`, `class`, `fixed_monthly_fee`, `created_at`, `updated_at`) VALUES ('17', '12', '3800.00', '2026-06-30 09:39:10', '2026-06-30 09:39:10');


DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `paid_for_month` varchar(20) NOT NULL,
  `payment_date` datetime NOT NULL,
  `received_by` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_mode` varchar(20) DEFAULT 'cash',
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `idx_payment_date` (`payment_date`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=171 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('108', '32', '2200.00', 'Jun-2026', '2026-06-30 08:38:11', 'Main office', '2026-06-30 11:38:11', 'bank_transfer');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('109', '32', '2200.00', 'Jul-2026', '2026-06-30 08:38:11', 'Main office', '2026-06-30 11:38:11', 'bank_transfer');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('110', '32', '2200.00', 'Aug-2026', '2026-06-30 08:38:11', 'Main office', '2026-06-30 11:38:11', 'bank_transfer');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('111', '32', '2200.00', 'Sep-2026', '2026-06-30 08:38:11', 'Main office', '2026-06-30 11:38:11', 'bank_transfer');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('112', '32', '2200.00', 'Oct-2026', '2026-06-30 08:38:11', 'Main office', '2026-06-30 11:38:11', 'bank_transfer');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('113', '32', '2200.00', 'Nov-2026', '2026-06-30 08:38:11', 'Main office', '2026-06-30 11:38:11', 'bank_transfer');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('114', '32', '2200.00', 'Dec-2026', '2026-06-30 08:38:11', 'Main office', '2026-06-30 11:38:11', 'bank_transfer');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('115', '32', '2200.00', 'Jan-2027', '2026-06-30 08:38:11', 'Main office', '2026-06-30 11:38:11', 'bank_transfer');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('116', '32', '2200.00', 'Feb-2027', '2026-06-30 08:39:36', 'Main office', '2026-06-30 11:39:36', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('117', '32', '2200.00', 'Mar-2027', '2026-06-30 08:39:36', 'Main office', '2026-06-30 11:39:36', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('118', '42', '2200.00', 'Jun-2026', '2026-06-30 09:07:59', 'master', '2026-06-30 12:07:59', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('119', '42', '2200.00', 'Jul-2026', '2026-06-30 09:07:59', 'master', '2026-06-30 12:07:59', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('120', '34', '2200.00', 'Jun-2026', '2026-07-02 05:58:39', 'master', '2026-07-02 08:58:39', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('121', '42', '2500.00', 'Aug-2026', '2026-07-07 06:33:16', 'master', '2026-07-07 09:33:16', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('122', '42', '2500.00', 'Sep-2026', '2026-07-07 06:33:16', 'master', '2026-07-07 09:33:16', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('123', '42', '2500.00', 'Oct-2026', '2026-07-07 06:34:22', 'master', '2026-07-07 09:34:22', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('124', '42', '2500.00', 'Nov-2026', '2026-07-07 06:34:22', 'master', '2026-07-07 09:34:22', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('125', '52', '2200.00', 'Jan-2026', '2026-07-07 09:34:09', 'admission', '2026-07-07 12:34:09', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('126', '52', '2200.00', 'Feb-2026', '2026-07-07 09:34:09', 'admission', '2026-07-07 12:34:09', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('127', '52', '2200.00', 'Mar-2026', '2026-07-07 09:34:09', 'admission', '2026-07-07 12:34:09', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('128', '52', '2200.00', 'Apr-2026', '2026-07-07 09:34:09', 'admission', '2026-07-07 12:34:09', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('129', '52', '2200.00', 'May-2026', '2026-07-07 09:34:09', 'admission', '2026-07-07 12:34:09', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('130', '53', '4000.00', 'Admission', '2026-07-08 11:48:35', 'master', '2026-07-08 14:48:35', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('131', '53', '2400.00', 'Jul-2026', '2026-07-08 11:48:35', 'master', '2026-07-08 14:48:35', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('132', '53', '2400.00', 'Aug-2026', '2026-07-08 11:48:35', 'master', '2026-07-08 14:48:35', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('133', '53', '2400.00', 'Sep-2026', '2026-07-08 11:48:35', 'master', '2026-07-08 14:48:35', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('134', '53', '2400.00', 'Oct-2026', '2026-07-08 11:48:35', 'master', '2026-07-08 14:48:35', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('135', '53', '2400.00', 'Nov-2026', '2026-07-08 11:55:13', 'master', '2026-07-08 14:55:13', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('136', '53', '2400.00', 'Dec-2026', '2026-07-08 11:55:13', 'master', '2026-07-08 14:55:13', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('137', '53', '2400.00', 'Jan-2027', '2026-07-08 11:59:45', 'master', '2026-07-08 14:59:45', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('138', '53', '2400.00', 'Feb-2027', '2026-07-08 12:03:59', 'master', '2026-07-08 15:03:59', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('139', '54', '3500.00', 'Jan-2026', '2026-07-08 12:14:25', 'admission', '2026-07-08 15:14:25', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('140', '54', '3500.00', 'Feb-2026', '2026-07-08 12:14:25', 'admission', '2026-07-08 15:14:25', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('141', '54', '3500.00', 'Mar-2026', '2026-07-08 12:14:25', 'admission', '2026-07-08 15:14:25', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('142', '54', '3500.00', 'Apr-2026', '2026-07-08 12:14:25', 'admission', '2026-07-08 15:14:25', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('143', '54', '3500.00', 'May-2026', '2026-07-08 12:14:25', 'admission', '2026-07-08 15:14:25', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('144', '54', '3500.00', 'Jun-2026', '2026-07-08 12:14:25', 'admission', '2026-07-08 15:14:25', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('145', '54', '3500.00', 'Jul-2026', '2026-07-08 12:14:25', 'admission', '2026-07-08 15:14:25', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('146', '54', '3500.00', 'Aug-2026', '2026-07-08 12:23:10', 'master', '2026-07-08 15:23:10', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('147', '54', '3500.00', 'Sep-2026', '2026-07-08 12:23:10', 'master', '2026-07-08 15:23:10', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('148', '54', '3500.00', 'Oct-2026', '2026-07-08 12:23:10', 'master', '2026-07-08 15:23:10', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('149', '54', '3500.00', 'Nov-2026', '2026-07-08 12:23:10', 'master', '2026-07-08 15:23:10', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('150', '54', '3500.00', 'Dec-2026', '2026-07-08 12:23:10', 'master', '2026-07-08 15:23:10', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('151', '51', '2400.00', 'Jan-2026', '2026-07-10 11:30:18', 'master', '2026-07-10 14:30:18', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('152', '51', '2400.00', 'Feb-2026', '2026-07-10 11:30:18', 'master', '2026-07-10 14:30:18', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('153', '51', '2400.00', 'Mar-2026', '2026-07-10 11:30:39', 'master', '2026-07-10 14:30:39', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('154', '51', '2400.00', 'Apr-2026', '2026-07-10 11:30:39', 'master', '2026-07-10 14:30:39', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('155', '51', '2400.00', 'May-2026', '2026-07-10 11:34:07', 'master', '2026-07-10 14:34:07', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('156', '33', '2200.00', 'Jun-2026', '2026-07-10 11:52:35', 'master', '2026-07-10 14:52:35', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('157', '33', '2200.00', 'Jul-2026', '2026-07-10 11:52:35', 'master', '2026-07-10 14:52:35', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('158', '51', '2400.00', 'Jun-2026', '2026-07-10 16:44:38', 'master', '2026-07-10 16:44:38', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('159', '51', '2400.00', 'Jul-2026', '2026-07-10 16:45:10', 'master', '2026-07-10 16:45:10', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('160', '51', '2400.00', 'Aug-2026', '2026-07-10 16:52:24', 'master', '2026-07-10 16:52:24', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('161', '32', '2200.00', 'Apr-2027', '2026-07-11 13:07:58', 'master', '2026-07-11 13:07:58', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('162', '32', '2200.00', 'May-2027', '2026-07-11 13:07:58', 'master', '2026-07-11 13:07:58', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('163', '32', '2200.00', 'Jun-2027', '2026-07-11 13:07:58', 'master', '2026-07-11 13:07:58', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('164', '36', '2650.00', 'Jun-2026', '2026-07-11 13:07:58', 'master', '2026-07-11 13:07:58', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('165', '36', '2650.00', 'Jul-2026', '2026-07-11 13:07:58', 'master', '2026-07-11 13:07:58', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('166', '55', '5000.00', 'Admission', '2026-07-11 14:39:07', 'Main office', '2026-07-11 14:39:07', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('167', '55', '3000.00', 'Jul-2026', '2026-07-11 14:39:07', 'Main office', '2026-07-11 14:39:07', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('168', '37', '2400.00', 'Jun-2026', '2026-07-11 14:43:48', 'Main office', '2026-07-11 14:43:48', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('169', '56', '2000.00', 'Admission', '2026-07-11 15:03:34', 'master', '2026-07-11 15:03:34', 'cash');
INSERT INTO `payments` (`id`, `student_id`, `amount`, `paid_for_month`, `payment_date`, `received_by`, `created_at`, `payment_mode`) VALUES ('170', '56', '2500.00', 'Jul-2026', '2026-07-11 15:03:34', 'master', '2026-07-11 15:03:34', 'cash');


DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES ('receipt_note', 'YOU Can Write Custom Note Here.');


DROP TABLE IF EXISTS `students`;
CREATE TABLE `students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `father_name` varchar(100) NOT NULL,
  `class` varchar(50) NOT NULL,
  `section` varchar(10) NOT NULL,
  `fixed_monthly_fee` decimal(10,2) NOT NULL,
  `admission_fee` decimal(10,2) DEFAULT 0.00,
  `contact_number` varchar(15) DEFAULT NULL,
  `status` enum('active','dropped') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `contact_number2` varchar(15) DEFAULT NULL,
  `whatsapp_number` varchar(15) DEFAULT NULL,
  `concession_amount` decimal(10,2) DEFAULT 0.00,
  `concession_reason` varchar(255) DEFAULT NULL,
  `monthly_fee` decimal(10,2) GENERATED ALWAYS AS (`fixed_monthly_fee` - `concession_amount`) VIRTUAL,
  `drop_reason` varchar(255) DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_student_status` (`status`),
  KEY `idx_student_class` (`class`,`section`)
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `students` (`id`, `name`, `father_name`, `class`, `section`, `fixed_monthly_fee`, `admission_fee`, `contact_number`, `status`, `created_at`, `updated_at`, `contact_number2`, `whatsapp_number`, `concession_amount`, `concession_reason`, `monthly_fee`, `drop_reason`, `created_by`) VALUES ('32', 'kashif', 'javed', 'Nursury', 'B', '2500.00', '0.00', '0327489327', 'active', '2026-06-30 09:39:37', '2026-07-10 17:16:11', '4535435345', '0327849237', '0.00', 'S.C', '2500.00', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `father_name`, `class`, `section`, `fixed_monthly_fee`, `admission_fee`, `contact_number`, `status`, `created_at`, `updated_at`, `contact_number2`, `whatsapp_number`, `concession_amount`, `concession_reason`, `monthly_fee`, `drop_reason`, `created_by`) VALUES ('33', 'Aqib Javed', 'javed', 'Nursury', 'B', '2500.00', '0.00', '0327489327', 'active', '2026-06-30 11:12:20', '2026-06-30 11:12:20', '032242376281', '03278492372', '300.00', 'S.C', '2200.00', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `father_name`, `class`, `section`, `fixed_monthly_fee`, `admission_fee`, `contact_number`, `status`, `created_at`, `updated_at`, `contact_number2`, `whatsapp_number`, `concession_amount`, `concession_reason`, `monthly_fee`, `drop_reason`, `created_by`) VALUES ('34', 'Hanzla', 'Iqbal', 'Pre', 'B', '2600.00', '0.00', '03064329231', 'active', '2026-06-30 11:12:53', '2026-06-30 11:12:53', '03224237628', '03005467823', '400.00', 'EMP', '2200.00', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `father_name`, `class`, `section`, `fixed_monthly_fee`, `admission_fee`, `contact_number`, `status`, `created_at`, `updated_at`, `contact_number2`, `whatsapp_number`, `concession_amount`, `concession_reason`, `monthly_fee`, `drop_reason`, `created_by`) VALUES ('35', 'Ali Abbas', 'Abbas', 'Nursury', 'B', '2500.00', '0.00', '0327489327', 'active', '2026-06-30 11:13:23', '2026-07-11 12:35:23', '03224237628', '0327849237', '0.00', '', '2500.00', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `father_name`, `class`, `section`, `fixed_monthly_fee`, `admission_fee`, `contact_number`, `status`, `created_at`, `updated_at`, `contact_number2`, `whatsapp_number`, `concession_amount`, `concession_reason`, `monthly_fee`, `drop_reason`, `created_by`) VALUES ('36', 'Javed', 'Iqbal', '1', 'B', '2700.00', '0.00', '03064329231', 'active', '2026-06-30 11:13:54', '2026-07-11 13:21:54', '03273878232', '03278492372', '200.00', 'Sibling', '2500.00', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `father_name`, `class`, `section`, `fixed_monthly_fee`, `admission_fee`, `contact_number`, `status`, `created_at`, `updated_at`, `contact_number2`, `whatsapp_number`, `concession_amount`, `concession_reason`, `monthly_fee`, `drop_reason`, `created_by`) VALUES ('37', 'kashif2', 'Abbas', '2', 'B', '2800.00', '0.00', '03064329231', 'active', '2026-06-30 11:14:46', '2026-07-11 12:36:41', '032242376281', '4329842389', '800.00', 'Sibling', '2000.00', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `father_name`, `class`, `section`, `fixed_monthly_fee`, `admission_fee`, `contact_number`, `status`, `created_at`, `updated_at`, `contact_number2`, `whatsapp_number`, `concession_amount`, `concession_reason`, `monthly_fee`, `drop_reason`, `created_by`) VALUES ('38', 'mian Muhmmad', 'mosin', '4', 'B', '3000.00', '0.00', 'asjf9834942', 'active', '2026-06-30 11:16:07', '2026-06-30 11:16:07', '03224237628', '43535345', '250.00', 'Orfan', '2750.00', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `father_name`, `class`, `section`, `fixed_monthly_fee`, `admission_fee`, `contact_number`, `status`, `created_at`, `updated_at`, `contact_number2`, `whatsapp_number`, `concession_amount`, `concession_reason`, `monthly_fee`, `drop_reason`, `created_by`) VALUES ('39', 'Hazala Sultan', 'father2', '5', 'B', '3100.00', '0.00', '03064329231', 'active', '2026-06-30 11:17:00', '2026-06-30 11:17:00', '032242376281', '03278492372', '100.00', 'EMP', '3000.00', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `father_name`, `class`, `section`, `fixed_monthly_fee`, `admission_fee`, `contact_number`, `status`, `created_at`, `updated_at`, `contact_number2`, `whatsapp_number`, `concession_amount`, `concession_reason`, `monthly_fee`, `drop_reason`, `created_by`) VALUES ('40', 'Anees Haider', 'Haider Ali', '6', 'B', '3200.00', '0.00', '0327489327', 'active', '2026-06-30 11:17:29', '2026-06-30 11:17:29', '032242376281', '4738924723', '200.00', 'EMP', '3000.00', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `father_name`, `class`, `section`, `fixed_monthly_fee`, `admission_fee`, `contact_number`, `status`, `created_at`, `updated_at`, `contact_number2`, `whatsapp_number`, `concession_amount`, `concession_reason`, `monthly_fee`, `drop_reason`, `created_by`) VALUES ('41', 'Zulqarnain haider', 'Haider Saleem', '7', 'B', '3300.00', '0.00', '345435', 'active', '2026-06-30 11:18:18', '2026-06-30 11:18:18', '4e823974923', '03278492372', '0.00', '', '3300.00', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `father_name`, `class`, `section`, `fixed_monthly_fee`, `admission_fee`, `contact_number`, `status`, `created_at`, `updated_at`, `contact_number2`, `whatsapp_number`, `concession_amount`, `concession_reason`, `monthly_fee`, `drop_reason`, `created_by`) VALUES ('42', 'new student', 'new', 'Nursury', 'B', '2500.00', '0.00', '0327489327', 'active', '2026-06-30 12:07:04', '2026-06-30 12:10:28', '03224237628', '0327849237', '0.00', 'Hafiz', '2500.00', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `father_name`, `class`, `section`, `fixed_monthly_fee`, `admission_fee`, `contact_number`, `status`, `created_at`, `updated_at`, `contact_number2`, `whatsapp_number`, `concession_amount`, `concession_reason`, `monthly_fee`, `drop_reason`, `created_by`) VALUES ('43', 'new', 'javed', '8', 'B', '3400.00', '0.00', '37829479', 'active', '2026-07-06 10:43:01', '2026-07-06 10:43:01', '43278648', '03005467823', '0.00', '', '3400.00', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `father_name`, `class`, `section`, `fixed_monthly_fee`, `admission_fee`, `contact_number`, `status`, `created_at`, `updated_at`, `contact_number2`, `whatsapp_number`, `concession_amount`, `concession_reason`, `monthly_fee`, `drop_reason`, `created_by`) VALUES ('44', 'kashif', 'joiya', '8', 'B', '3400.00', '0.00', 'sds', 'active', '2026-07-06 14:28:46', '2026-07-06 14:28:46', 'sa342423', '4324234', '0.00', '', '3400.00', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `father_name`, `class`, `section`, `fixed_monthly_fee`, `admission_fee`, `contact_number`, `status`, `created_at`, `updated_at`, `contact_number2`, `whatsapp_number`, `concession_amount`, `concession_reason`, `monthly_fee`, `drop_reason`, `created_by`) VALUES ('45', 'kashif', 'joiya', 'Nursury', 'B', '2500.00', '0.00', 'sds', 'active', '2026-07-06 14:50:09', '2026-07-06 14:50:09', 'sa342423', '4324234', '0.00', '', '2500.00', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `father_name`, `class`, `section`, `fixed_monthly_fee`, `admission_fee`, `contact_number`, `status`, `created_at`, `updated_at`, `contact_number2`, `whatsapp_number`, `concession_amount`, `concession_reason`, `monthly_fee`, `drop_reason`, `created_by`) VALUES ('46', 'kashif', 'joiya', '8', 'B', '3400.00', '0.00', 'sds', 'active', '2026-07-06 14:50:37', '2026-07-06 14:50:37', 'sa342423', '4324234', '200.00', 'Hafiz', '3200.00', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `father_name`, `class`, `section`, `fixed_monthly_fee`, `admission_fee`, `contact_number`, `status`, `created_at`, `updated_at`, `contact_number2`, `whatsapp_number`, `concession_amount`, `concession_reason`, `monthly_fee`, `drop_reason`, `created_by`) VALUES ('47', 'kashif', 'dsafhkj', '11', 'B', '3700.00', '0.00', 'sds', 'active', '2026-07-06 14:51:18', '2026-07-06 14:51:18', 'sa342423', '4324234', '300.00', 'S.C', '3400.00', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `father_name`, `class`, `section`, `fixed_monthly_fee`, `admission_fee`, `contact_number`, `status`, `created_at`, `updated_at`, `contact_number2`, `whatsapp_number`, `concession_amount`, `concession_reason`, `monthly_fee`, `drop_reason`, `created_by`) VALUES ('48', 'kashif', 'dsafhkj', '11', 'B', '3700.00', '0.00', 'sds', 'active', '2026-07-06 14:53:18', '2026-07-06 14:53:18', 'sa342423', '4324234', '300.00', 'S.C', '3400.00', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `father_name`, `class`, `section`, `fixed_monthly_fee`, `admission_fee`, `contact_number`, `status`, `created_at`, `updated_at`, `contact_number2`, `whatsapp_number`, `concession_amount`, `concession_reason`, `monthly_fee`, `drop_reason`, `created_by`) VALUES ('49', 'kashif', 'joiya', '11', 'B', '3700.00', '0.00', 'sds', 'active', '2026-07-06 14:54:34', '2026-07-06 14:54:34', 'sa342423', '4324234', '200.00', 'Sibling', '3500.00', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `father_name`, `class`, `section`, `fixed_monthly_fee`, `admission_fee`, `contact_number`, `status`, `created_at`, `updated_at`, `contact_number2`, `whatsapp_number`, `concession_amount`, `concession_reason`, `monthly_fee`, `drop_reason`, `created_by`) VALUES ('50', 'kashif', 'joiya', '11', 'B', '3700.00', '0.00', 'sds', 'active', '2026-07-06 15:05:33', '2026-07-06 15:05:33', 'sa342423', '4324234', '200.00', 'Sibling', '3500.00', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `father_name`, `class`, `section`, `fixed_monthly_fee`, `admission_fee`, `contact_number`, `status`, `created_at`, `updated_at`, `contact_number2`, `whatsapp_number`, `concession_amount`, `concession_reason`, `monthly_fee`, `drop_reason`, `created_by`) VALUES ('51', 'Najum Gull', 'Gull MUhammad', 'P.G', 'B', '2400.00', '0.00', '24541456', 'active', '2026-07-07 12:33:19', '2026-07-07 12:33:19', '445', '', '0.00', '', '2400.00', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `father_name`, `class`, `section`, `fixed_monthly_fee`, `admission_fee`, `contact_number`, `status`, `created_at`, `updated_at`, `contact_number2`, `whatsapp_number`, `concession_amount`, `concession_reason`, `monthly_fee`, `drop_reason`, `created_by`) VALUES ('52', 'testing', 'testing', 'Nursury', 'B', '2500.00', '0.00', '24541456', 'active', '2026-07-07 12:34:09', '2026-07-07 12:37:46', '4234723', '486425465', '0.00', 'Sibling', '2500.00', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `father_name`, `class`, `section`, `fixed_monthly_fee`, `admission_fee`, `contact_number`, `status`, `created_at`, `updated_at`, `contact_number2`, `whatsapp_number`, `concession_amount`, `concession_reason`, `monthly_fee`, `drop_reason`, `created_by`) VALUES ('53', 'new testeting', 'tsets', 'P.G', 'G', '2400.00', '4000.00', '24541456', 'active', '2026-07-08 14:46:45', '2026-07-08 14:46:45', '4234723', '', '0.00', '', '2400.00', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `father_name`, `class`, `section`, `fixed_monthly_fee`, `admission_fee`, `contact_number`, `status`, `created_at`, `updated_at`, `contact_number2`, `whatsapp_number`, `concession_amount`, `concession_reason`, `monthly_fee`, `drop_reason`, `created_by`) VALUES ('54', 'data entry', 'new data enter', '11', 'G', '3700.00', '0.00', '24541456', 'active', '2026-07-08 15:14:25', '2026-07-08 15:14:25', '4234723', '3279842', '200.00', 'Orfan', '3500.00', NULL, NULL);
INSERT INTO `students` (`id`, `name`, `father_name`, `class`, `section`, `fixed_monthly_fee`, `admission_fee`, `contact_number`, `status`, `created_at`, `updated_at`, `contact_number2`, `whatsapp_number`, `concession_amount`, `concession_reason`, `monthly_fee`, `drop_reason`, `created_by`) VALUES ('55', 'Testing Logs', 'logs', '6', 'B', '3200.00', '5000.00', '03064329231', 'active', '2026-07-11 13:03:51', '2026-07-11 14:37:18', '032242376281', '4329842389', '200.00', '0', '3000.00', NULL, 'master');
INSERT INTO `students` (`id`, `name`, `father_name`, `class`, `section`, `fixed_monthly_fee`, `admission_fee`, `contact_number`, `status`, `created_at`, `updated_at`, `contact_number2`, `whatsapp_number`, `concession_amount`, `concession_reason`, `monthly_fee`, `drop_reason`, `created_by`) VALUES ('56', 'testing admisison', 'admisison', '1', 'B', '2700.00', '2000.00', '03064329231', 'active', '2026-07-11 15:02:57', '2026-07-11 15:02:57', '', '4329842389', '200.00', 'Orfan', '2500.00', NULL, 'master');


DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('master','finance','admission') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_frozen` tinyint(4) DEFAULT 0,
  `frozen_until` datetime DEFAULT NULL,
  `edit_access` tinyint(4) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`, `is_frozen`, `frozen_until`, `edit_access`) VALUES ('1', 'master', '1234', 'master', '2026-02-24 21:26:11', '0', NULL, '0');
INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`, `is_frozen`, `frozen_until`, `edit_access`) VALUES ('2', 'Main office', '1234', 'finance', '2026-02-24 21:26:11', '0', NULL, '1');
INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`, `is_frozen`, `frozen_until`, `edit_access`) VALUES ('3', 'admission', '1234', 'admission', '2026-06-15 22:54:18', '0', NULL, '1');
INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`, `is_frozen`, `frozen_until`, `edit_access`) VALUES ('4', 'finance2', '1234', 'finance', '2026-06-19 23:01:50', '0', NULL, '0');
INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`, `is_frozen`, `frozen_until`, `edit_access`) VALUES ('5', 'Financeno3', '1234', 'finance', '2026-06-30 22:25:07', '0', NULL, '0');
INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`, `is_frozen`, `frozen_until`, `edit_access`) VALUES ('6', 'adnission2', '1234', 'admission', '2026-06-30 22:27:22', '0', NULL, '0');


SET FOREIGN_KEY_CHECKS=1;
