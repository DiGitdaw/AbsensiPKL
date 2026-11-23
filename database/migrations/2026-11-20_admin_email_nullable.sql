-- Email admin tidak lagi dipakai, jadikan opsional.
ALTER TABLE `admin`
  MODIFY `email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL;
