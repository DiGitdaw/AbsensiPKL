-- Penyimpanan pengaturan aplikasi, dipakai untuk jadwal PKL.
CREATE TABLE IF NOT EXISTS `pengaturan` (
  `kunci` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `nilai` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`kunci`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `pengaturan` (`kunci`, `nilai`) VALUES
  ('pkl_mulai', NULL),
  ('pkl_selesai', NULL)
ON DUPLICATE KEY UPDATE `kunci` = `kunci`;
