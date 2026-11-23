<?php
require_once '../../config/database.php';

if (!isset($_SESSION['siswa_id'])) {
    header("Location: ../login.php");
    exit();
}

$stmt = $conn->query("SELECT username, nama_lengkap, no_hp, foto_profil FROM admin ORDER BY id LIMIT 1");
$pembimbing = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembimbing - Absensi DJKI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 1) ?>assets/css/theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>

<body class="min-h-screen" style="background:#F5F7FA">
    <div class="max-w-2xl mx-auto p-4 lg:p-8">
        <a href="javascript:history.back()" class="inline-flex items-center gap-2 text-sm text-gray-700 hover:text-blue-600 mb-4">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>

        <div class="glass-effect rounded-xl p-6 text-center">
            <img src="../../<?= htmlspecialchars($pembimbing['foto_profil'] ?? 'assets/default/photo-profile.png') ?>"
                alt="Pembimbing" class="w-28 h-28 rounded-full object-cover mx-auto border-4 border-white shadow-lg">
            <p class="text-xs font-semibold tracking-wide text-blue-600 mt-4 uppercase">Pembimbing</p>
            <h1 class="text-xl font-bold mt-1"><?= htmlspecialchars($pembimbing['nama_lengkap'] ?? '') ?></h1>
            <p class="text-sm text-gray-500"><?= htmlspecialchars($pembimbing['username'] ?? '') ?></p>

            <div class="mt-5 flex items-center justify-center gap-2 text-gray-900">
                <i class="fas fa-phone text-blue-600"></i>
                <span><?= htmlspecialchars($pembimbing['no_hp'] ?? 'Belum diisi') ?></span>
            </div>
        </div>

        <p class="text-center text-xs text-gray-500 mt-4">Hubungi pembimbing untuk urusan jadwal PKL dan persetujuan absensi.</p>
    </div>
</body>

</html>
