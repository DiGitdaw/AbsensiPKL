<?php
require_once '../../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

$error = '';
$success = false;

$default_pkl_mulai = get_pengaturan($conn, 'pkl_mulai', '');
$default_pkl_selesai = get_pengaturan($conn, 'pkl_selesai', '');

// Isian terakhir supaya tidak perlu mengetik ulang sekolah/kelas/jurusan
$last_input = $_SESSION['last_input_siswa'] ?? [];

// Pilihan tingkat kelas: SMK cukup X/XI/XII, sisanya diketik manual.
$tingkat_smk = ['X', 'XI', 'XII'];
$tingkat_mahasiswa = ['Semester 1', 'Semester 2', 'Semester 3', 'Semester 4', 'Semester 5', 'Semester 6'];

$sekolah_list = sekolah_options($conn);
$jurusan_list = jurusan_options($conn);

$jenis_tampil = $_POST['jenis'] ?? ($last_input['jenis'] ?? 'smk');
if (!in_array($jenis_tampil, ['smk', 'mahasiswa'], true)) {
    $jenis_tampil = 'smk';
}

function rapikan_tingkat($tingkat)
{
    $tingkat = trim($tingkat);
    if (preg_match('/^Semester\s+(\d+)$/i', $tingkat, $m)) {
        return 'Semester ' . (int)$m[1];
    }
    return strtoupper($tingkat);
}

$val_kelas_tingkat = rapikan_tingkat($_POST['kelas_tingkat'] ?? '');
$val_kelas_suffix = strtoupper(trim($_POST['kelas_suffix'] ?? ''));

// Kalau belum ada isian, ambil dari tambahan terakhir lalu pisah tingkat + sisanya.
if ($val_kelas_tingkat === '' && $val_kelas_suffix === '') {
    $last_kelas = trim($last_input['kelas'] ?? '');
    if ($last_kelas !== '') {
        if (preg_match('/^(XII|XI|X)\b\s*(.*)$/i', $last_kelas, $m)) {
            $val_kelas_tingkat = strtoupper($m[1]);
            $val_kelas_suffix = strtoupper(trim($m[2]));
        } elseif (preg_match('/^Semester\s+(\d+)\b\s*(.*)$/i', $last_kelas, $m)) {
            $val_kelas_tingkat = 'Semester ' . (int)$m[1];
            $val_kelas_suffix = strtoupper(trim($m[2]));
        } else {
            $val_kelas_suffix = strtoupper($last_kelas);
        }
    }
}

$sekolah_pilihan = $_POST['sekolah_pilihan'] ?? '';
$val_sekolah = ($sekolah_pilihan === 'lainnya') ? strtoupper(trim($_POST['sekolah_custom'] ?? '')) : trim($sekolah_pilihan);
if ($val_sekolah === '') {
    $val_sekolah = $last_input['sekolah'] ?? '';
}

$val_jurusan = strtoupper(trim($_POST['jurusan'] ?? ($last_input['jurusan'] ?? '')));
$val_jurusan_manual_aktif = $val_jurusan !== '' && !in_array($val_jurusan, $jurusan_list, true);
$val_pkl_mulai = $_POST['pkl_mulai'] ?? ($last_input['pkl_mulai'] ?? $default_pkl_mulai);
$val_pkl_selesai = $_POST['pkl_selesai'] ?? ($last_input['pkl_selesai'] ?? $default_pkl_selesai);

// Baris siswa untuk ditampilkan ulang kalau validasi gagal
$jumlah_tampil = max(1, min(50, (int)($_POST['jumlah'] ?? 1)));
$nama_tampil = $_POST['siswa_nama'] ?? [];
$hp_tampil = $_POST['siswa_no_hp'] ?? [];
$gender_tampil = $_POST['siswa_gender'] ?? [];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $jumlah = max(1, min(50, (int)($_POST['jumlah'] ?? 1)));

        $kelas_tingkat = rapikan_tingkat($_POST['kelas_tingkat'] ?? '');
        $kelas_suffix = strtoupper(trim($_POST['kelas_suffix'] ?? ''));
        $sekolah_pilihan = $_POST['sekolah_pilihan'] ?? '';
        $sekolah = ($sekolah_pilihan === 'lainnya') ? strtoupper(trim($_POST['sekolah_custom'] ?? '')) : trim($sekolah_pilihan);
        $jurusan = strtoupper(trim($_POST['jurusan'] ?? ''));
        $jenis = $_POST['jenis'] ?? 'smk';
        $pkl_mulai = $_POST['pkl_mulai'] ?? '';
        $pkl_selesai = $_POST['pkl_selesai'] ?? '';
        $password = $_POST['password'] ?? '';

        if ($password === '') {
            throw new Exception("Password wajib diisi.");
        }

        if (!in_array($jenis, ['smk', 'mahasiswa'], true)) {
            $jenis = 'smk';
        }

        $tingkat_dipakai = $jenis === 'mahasiswa' ? $tingkat_mahasiswa : $tingkat_smk;
        if (!in_array($kelas_tingkat, $tingkat_dipakai, true)) {
            throw new Exception("Tingkat kelas wajib dipilih.");
        }

        $kelas = trim($kelas_tingkat . ($kelas_suffix !== '' ? ' ' . $kelas_suffix : ''));
        if (strlen($kelas) > 30) {
            throw new Exception("Kelas maksimal 30 karakter.");
        }
        if (strlen($sekolah) > 100) {
            throw new Exception("Nama sekolah maksimal 100 karakter.");
        }
        if ($jurusan === '' || strlen($jurusan) > 50) {
            throw new Exception("Jurusan wajib diisi, maksimal 50 karakter.");
        }
        if ($pkl_mulai !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $pkl_mulai)) {
            $pkl_mulai = '';
        }
        if ($pkl_selesai !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $pkl_selesai)) {
            $pkl_selesai = '';
        }

        // Nama, No HP, dan jenis kelamin diisi per baris
        $nama_rows = $_POST['siswa_nama'] ?? [];
        $hp_rows = $_POST['siswa_no_hp'] ?? [];
        $gender_rows = $_POST['siswa_gender'] ?? [];

        $siswa_rows = [];
        for ($i = 0; $i < $jumlah; $i++) {
            $nama = strtoupper(trim($nama_rows[$i] ?? ''));
            $no_hp = trim($hp_rows[$i] ?? '');
            $jenis_kelamin = $gender_rows[$i] ?? '';

            if ($nama === '') {
                throw new Exception("Nama siswa baris ke-" . ($i + 1) . " wajib diisi.");
            }
            if (strlen($nama) > 100) {
                throw new Exception("Nama siswa baris ke-" . ($i + 1) . " maksimal 100 karakter.");
            }
            if ($no_hp !== '' && !preg_match('/^[0-9+\-\s()]{8,20}$/', $no_hp)) {
                throw new Exception("No HP siswa baris ke-" . ($i + 1) . " tidak valid.");
            }
            if (!in_array($jenis_kelamin, ['L', 'P'], true)) {
                $jenis_kelamin = '';
            }

            $siswa_rows[] = [
                'nama' => $nama,
                'no_hp' => $no_hp,
                'jenis_kelamin' => $jenis_kelamin,
            ];
        }

        // Start transaction
        $password_hash = password_siap_simpan($password);
        $conn->beginTransaction();

        // Handle profile photo upload
        $foto_profil = 'assets/default/photo-profile.png'; // Default photo

        if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
            $file_type = $_FILES['foto_profil']['type'];

            if (!in_array($file_type, $allowed_types)) {
                throw new Exception("Format file tidak didukung. Gunakan JPG atau PNG.");
            }

            $max_size = 2 * 1024 * 1024; // 2MB
            if ($_FILES['foto_profil']['size'] > $max_size) {
                throw new Exception("Ukuran file terlalu besar. Maksimum 2MB.");
            }

            // Create upload directory if it doesn't exist
            $upload_dir = '../../uploads/profile/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_extension = pathinfo($_FILES['foto_profil']['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . time() . '_' . mt_rand(1000, 9999) . '.' . $file_extension;
            $target_file = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['foto_profil']['tmp_name'], $target_file)) {
                $foto_profil = 'uploads/profile/' . $filename;
            } else {
                throw new Exception("Gagal mengunggah foto profil.");
            }
        }

        // Insert student data
        $stmt = $conn->prepare("INSERT INTO siswa (nama_lengkap, kelas, sekolah, jurusan, jenis, jenis_kelamin, no_hp, pkl_mulai, pkl_selesai, password, foto_profil)
                                VALUES (:nama, :kelas, :sekolah, :jurusan, :jenis, :jenis_kelamin, :no_hp, :pkl_mulai, :pkl_selesai, :password, :foto_profil)");

        $student_id = null;
        foreach ($siswa_rows as $row) {
            $stmt->execute([
                'nama' => $row['nama'],
                'kelas' => $kelas,
                'sekolah' => $sekolah !== '' ? $sekolah : null,
                'jurusan' => $jurusan,
                'jenis' => $jenis,
                'jenis_kelamin' => $row['jenis_kelamin'] !== '' ? $row['jenis_kelamin'] : null,
                'no_hp' => $row['no_hp'] !== '' ? $row['no_hp'] : null,
                'pkl_mulai' => $pkl_mulai !== '' ? $pkl_mulai : null,
                'pkl_selesai' => $pkl_selesai !== '' ? $pkl_selesai : null,
                'password' => $password_hash,
                'foto_profil' => $jumlah === 1 ? $foto_profil : 'assets/default/photo-profile.png'
            ]);

            if ($jumlah === 1) {
                $student_id = $conn->lastInsertId();
            }
        }

        if ($jumlah > 1) {
            $deskripsi = "Admin menambahkan " . count($siswa_rows) . " siswa sekolah $sekolah";
        } else {
            $deskripsi = "Admin menambahkan siswa baru: " . $siswa_rows[0]['nama'];
        }

        // Log activity
        $sql = "INSERT INTO activity_log (user_type, user_id, activity_type, description) 
                VALUES ('admin', :admin_id, 'create', :description)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'admin_id' => $_SESSION['admin_id'],
            'description' => $deskripsi
        ]);

        $conn->commit();

        // Simpan isian terakhir
        $_SESSION['last_input_siswa'] = [
            'jenis' => $jenis,
            'kelas' => $kelas,
            'sekolah' => $sekolah,
            'jurusan' => $jurusan,
            'pkl_mulai' => $pkl_mulai,
            'pkl_selesai' => $pkl_selesai,
        ];

        if ($jumlah > 1) {
            header("Location: index.php?ditambahkan=" . count($siswa_rows));
            exit();
        }

        header("Location: detail.php?id=$student_id&created=true");
        exit();
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = $e->getMessage();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Siswa - Absensi DJKI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 1) ?>assets/css/theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .glass-effect {
            background: #ffffff;
            backdrop-filter: blur(10px);
            border: 1px solid #BFDBFE;
        }

        body {
            background: #F5F7FA;
        }

        .menu-active {
            background: linear-gradient(to right, #EFF6FF, #EFF6FF);
            border-left: 4px solid #2E77BC;
        }

        /* Mobile responsive styles */
        .sidebar-transition {
            transition: transform 0.3s ease-in-out;
        }

        .mobile-overlay {
            background-color: rgba(0, 0, 0, 0.5);
            transition: opacity 0.3s ease-in-out;
        }

        /* Hide scrollbar */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        /* Form animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in {
            animation: fadeIn 0.3s ease forwards;
        }

        /* Improved touch targets for mobile */
        @media (max-width: 640px) {
            .touch-target {
                min-height: 44px;
            }

            input,
            select,
            button {
                font-size: 16px;
                /* Prevents iOS zoom on focus */
            }
        }

        /* Fix for iOS full height */
        @supports (-webkit-touch-callout: none) {
            .min-h-screen {
                min-height: -webkit-fill-available;
            }
        }

        /* Profile image responsive styles */
        .profile-upload {
            transition: all 0.2s ease;
        }

        .profile-upload:active {
            transform: scale(0.95);
        }

        /* Selectable cards for mode, gender, and jurusan */
        .pick-card {
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 2px;
            padding: 0.75rem 1rem;
            border: 1px solid #E5E7EB;
            border-radius: 0.75rem;
            background: #F9FAFB;
            cursor: pointer;
            transition: border-color 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
            min-height: 44px;
        }

        .pick-card:hover {
            border-color: rgba(46, 119, 188, 0.45);
            background: #F3F7FC;
        }

        .pick-card:has(input:checked) {
            border-color: #2E77BC;
            background: #EFF6FF;
            box-shadow: inset 0 0 0 1px #2E77BC;
        }

        .pick-card input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .pick-title {
            font-weight: 600;
            color: #111827;
        }

        .pick-sub {
            font-size: 0.75rem;
            color: #6B7280;
        }

        .pick-inline {
            flex-direction: row;
            align-items: center;
            gap: 0.5rem;
        }
    </style>
</head>

<body class="min-h-screen text-gray-900 bg-fixed">
    <!-- Mobile Overlay - only visible when sidebar is open on mobile -->
    <div id="mobile-overlay" class="fixed inset-0 bg-gray-900/40 z-40 lg:hidden hidden" onclick="toggleSidebar()"></div>

    <!-- Side Navigation -->
    <aside id="sidebar" class="fixed top-0 left-0 h-screen w-64 glass-effect border-r border-blue-100 z-50 sidebar-transition -translate-x-full lg:translate-x-0">
        <div class="flex items-center justify-between p-4 lg:p-6 border-b border-blue-100">
            <div class="flex items-center gap-3">
                <?= app_logo('../../', 'h-8 lg:h-10 w-auto') ?>
                <div>
                    <h1 class="font-semibold text-sm lg:text-base">Absensi DJKI</h1>
                    <p class="text-xs text-gray-700">Sistem Absensi</p>
                </div>
            </div>
            <!-- Close sidebar button - only visible on mobile -->
            <button class="text-gray-700 hover:text-gray-900 lg:hidden" onclick="toggleSidebar()">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <nav class="p-4 space-y-2 overflow-y-auto no-scrollbar" style="max-height: calc(100vh - 76px);">
            <a href="../dashboard/" class="flex items-center gap-3 text-gray-700 p-3 rounded-lg hover:bg-blue-500/10 transition-colors">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="../absensi/" class="flex items-center gap-3 text-gray-700 p-3 rounded-lg hover:bg-blue-500/10 transition-colors">
                <i class="fas fa-calendar-check"></i>
                <span>Absensi</span>
            </a>
            <a href="index.php" class="flex items-center gap-3 text-gray-900 p-3 rounded-lg menu-active">
                <i class="fas fa-users text-blue-600"></i>
                <span>Data Siswa</span>
            </a>
            <a href="../laporan/" class="flex items-center gap-3 text-gray-700 p-3 rounded-lg hover:bg-blue-500/10 transition-colors">
                <i class="fas fa-file-alt"></i>
                <span>Laporan</span>
            </a>
            <a href="../profil/" class="flex items-center gap-3 text-gray-700 p-3 rounded-lg hover:bg-blue-500/10 transition-colors">
                <i class="fas fa-user-cog"></i>
                <span>Profil</span>
            </a>
            <a href="../logout.php" class="flex items-center gap-3 text-gray-700 p-3 rounded-lg hover:bg-red-500/10 hover:text-red-500 transition-colors mt-10">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="lg:ml-64 min-h-screen bg-gradient-to-br from-white to-gray-50 transition-all duration-300">
        <!-- Mobile Header -->
        <div class="lg:hidden bg-white/80 backdrop-blur-lg sticky top-0 z-30 px-4 py-3 flex items-center justify-between border-b border-blue-100">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="text-gray-900 p-2 -ml-2 rounded-lg hover:bg-gray-50" aria-label="Menu">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                <?= app_logo('../../', 'h-8 w-auto') ?>
            </div>
            <div class="flex items-center gap-3">
                <span id="current-time-mobile" class="text-sm font-medium hidden sm:block"></span>
                <?php
                // Use admin photo from session if available
                $photo_path = $_SESSION['admin_photo'] ?? 'assets/default/avatar.png';
                ?>
                <img src="../../<?= $photo_path ?>" alt="Profile" class="h-8 w-8 rounded-full object-cover border border-blue-300">
            </div>
        </div>

        <div class="p-4 lg:p-8">
            <div class="max-w-4xl mx-auto animate-fade-in">
                <!-- Header with back button - enhanced for mobile -->
                <div class="flex items-center mb-6">
                    <a href="index.php" class="mr-3 p-2 rounded-full hover:bg-gray-50 transition-colors">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold">Tambah Siswa</h1>
                        <p class="text-sm md:text-base text-gray-700">Tambahkan data siswa baru</p>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="bg-red-500/10 border border-red-500/30 text-red-500 rounded-lg p-4 mb-6 flex items-start animate-fade-in">
                        <i class="fas fa-exclamation-circle mt-0.5 mr-3"></i>
                        <div>
                            <p class="font-medium">Gagal menambahkan siswa</p>
                            <p class="text-sm text-red-500/80 mt-1"><?= $error ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Add Form - Mobile optimized -->
                <div class="glass-effect rounded-xl p-4 md:p-6 mb-6">
                    <form method="POST" enctype="multipart/form-data">
                        <!-- Profile Picture - Improved for mobile -->
                        <div class="mb-6 text-center">
                            <div class="relative w-28 h-28 md:w-32 md:h-32 mx-auto">
                                <img id="preview-image" src="../../assets/default/photo-profile.png"
                                    alt="Profile" class="w-28 h-28 md:w-32 md:h-32 object-cover rounded-full border-4 border-gray-200">

                                <label for="foto_profil" class="absolute -bottom-2 -right-2 bg-blue-600 hover:bg-blue-700 rounded-full w-9 h-9 md:w-10 md:h-10 flex items-center justify-center cursor-pointer transition-colors profile-upload">
                                    <i class="fas fa-camera"></i>
                                </label>
                                <input type="file" id="foto_profil" name="foto_profil" accept="image/*" class="hidden" onchange="previewImage()">
                            </div>
                            <p class="text-xs md:text-sm text-gray-700 mt-3">Upload foto profil (opsional)</p>
                        </div>

                        <!-- Mode pendaftar -->
                        <div class="mb-6">
                            <span class="block text-sm text-gray-700 mb-2">Jenis Pendaftar</span>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="pick-card">
                                    <input type="radio" name="jenis" value="smk" <?= $jenis_tampil === 'smk' ? 'checked' : '' ?>>
                                    <span class="flex items-center gap-2">
                                        <i class="fas fa-school text-blue-600"></i>
                                        <span class="pick-title">SMK</span>
                                    </span>
                                    <span class="pick-sub">Siswa sekolah</span>
                                </label>
                                <label class="pick-card">
                                    <input type="radio" name="jenis" value="mahasiswa" <?= $jenis_tampil === 'mahasiswa' ? 'checked' : '' ?>>
                                    <span class="flex items-center gap-2">
                                        <i class="fas fa-university text-blue-600"></i>
                                        <span class="pick-title">Mahasiswa</span>
                                    </span>
                                    <span class="pick-sub">Peserta magang / PKL</span>
                                </label>
                            </div>
                        </div>

                        <!-- Jumlah siswa -->
                        <div class="mb-4 md:mb-6">
                            <label for="jumlah" class="block text-sm text-gray-700 mb-2">Jumlah Siswa</label>
                            <input type="number" id="jumlah" name="jumlah" min="1" max="50" value="<?= $jumlah_tampil ?>"
                                class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-3 md:py-2 text-gray-900 focus:outline-none focus:border-blue-500 touch-target">
                            <p class="text-xs text-gray-500 mt-1">Ubah angka ini untuk menampilkan baris siswa. Nama, No HP, dan jenis kelamin diisi per siswa.</p>
                        </div>

                        <!-- Data per siswa -->
                        <div class="mb-4 md:mb-6">
                            <div class="flex flex-wrap items-center justify-between gap-1 mb-2">
                                <span class="block text-sm font-medium text-gray-700">Data Siswa</span>
                                <span class="text-xs text-gray-500">Nama, No HP, dan jenis kelamin tiap orang.</span>
                            </div>
                            <div id="siswa-rows" class="space-y-3">
                                <?php for ($i = 0; $i < $jumlah_tampil; $i++): ?>
                                    <div class="siswa-row rounded-lg border border-gray-200 bg-gray-50 p-3 md:p-4">
                                        <p class="row-label text-xs font-semibold text-blue-700 mb-2">Siswa <?= $i + 1 ?></p>
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                            <div>
                                                <label class="block text-xs text-gray-700 mb-1">Nama Lengkap</label>
                                                <input type="text" name="siswa_nama[]" data-upper required maxlength="100"
                                                    value="<?= htmlspecialchars(strtoupper($nama_tampil[$i] ?? '')) ?>"
                                                    class="uppercase w-full bg-white border border-gray-200 rounded-lg px-3 py-3 md:py-2 text-gray-900 focus:outline-none focus:border-blue-500 touch-target">
                                            </div>
                                            <div>
                                                <label class="block text-xs text-gray-700 mb-1">No HP</label>
                                                <input type="tel" name="siswa_no_hp[]" maxlength="20" placeholder="08xxxxxxxxxx"
                                                    value="<?= htmlspecialchars($hp_tampil[$i] ?? '') ?>"
                                                    class="w-full bg-white border border-gray-200 rounded-lg px-3 py-3 md:py-2 text-gray-900 focus:outline-none focus:border-blue-500 touch-target">
                                            </div>
                                            <div>
                                                <label class="block text-xs text-gray-700 mb-1">Jenis Kelamin</label>
                                                <select name="siswa_gender[]"
                                                    class="w-full bg-white border border-gray-200 rounded-lg px-3 py-3 md:py-2 text-gray-900 focus:outline-none focus:border-blue-500 touch-target">
                                                    <option value="">-</option>
                                                    <option value="L" <?= ($gender_tampil[$i] ?? '') === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                                                    <option value="P" <?= ($gender_tampil[$i] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <!-- Data bersama untuk semua siswa -->
                        <div class="rounded-lg border border-blue-100 bg-blue-50/40 p-3 md:p-4 mb-4 md:mb-6">
                            <p class="text-sm font-medium text-gray-700 mb-3">Data Sekolah, Kelas, dan PKL
                                <span class="text-xs font-normal text-gray-500">(dipakai untuk semua siswa di atas)</span>
                            </p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                                <!-- Kelas: tingkat + ketikan bebas -->
                                <div class="md:col-span-2">
                                    <label for="kelas_tingkat" id="label-kelas" class="block text-sm text-gray-700 mb-2">Kelas</label>
                                    <div class="grid grid-cols-3 gap-2">
                                        <select id="kelas_tingkat" name="kelas_tingkat"
                                            class="w-full bg-white border border-gray-200 rounded-lg px-3 py-3 md:py-2 text-gray-900 focus:outline-none focus:border-blue-500 touch-target">
                                            <option value="">Tingkat</option>
                                            <?php foreach (($jenis_tampil === 'mahasiswa' ? $tingkat_mahasiswa : $tingkat_smk) as $t): ?>
                                                <option value="<?= htmlspecialchars($t) ?>" <?= $val_kelas_tingkat === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="text" id="kelas_suffix" name="kelas_suffix" data-upper maxlength="19"
                                            value="<?= htmlspecialchars($val_kelas_suffix) ?>" placeholder="RPL 1"
                                            class="uppercase col-span-2 w-full bg-white border border-gray-200 rounded-lg px-3 py-3 md:py-2 text-gray-900 focus:outline-none focus:border-blue-500 touch-target">
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">Pilih tingkat, lalu ketik sisanya. Contoh: <b>XI</b> + <b>RPL 1</b> = XI RPL 1.</p>
                                </div>

                                <!-- Nama Sekolah -->
                                <div>
                                    <label for="sekolah_pilihan" class="block text-sm text-gray-700 mb-2">Nama Sekolah</label>
                                    <select id="sekolah_pilihan" name="sekolah_pilihan"
                                        class="uppercase w-full bg-white border border-gray-200 rounded-lg px-3 py-3 md:py-2 text-gray-900 focus:outline-none focus:border-blue-500 touch-target">
                                        <option value="">Pilih sekolah</option>
                                        <?php foreach ($sekolah_list as $option): ?>
                                            <option value="<?= htmlspecialchars($option) ?>" <?= $val_sekolah === $option ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
                                        <?php endforeach; ?>
                                        <option value="lainnya" <?= ($val_sekolah !== '' && !in_array($val_sekolah, $sekolah_list, true)) ? 'selected' : '' ?>>Lainnya...</option>
                                    </select>
                                    <input type="text" id="sekolah_custom" name="sekolah_custom" data-upper maxlength="100" placeholder="Ketik nama sekolah"
                                        value="<?= ($val_sekolah !== '' && !in_array($val_sekolah, $sekolah_list, true)) ? htmlspecialchars($val_sekolah) : '' ?>"
                                        class="uppercase <?= ($val_sekolah !== '' && !in_array($val_sekolah, $sekolah_list, true)) ? '' : 'hidden' ?> mt-2 w-full bg-white border border-gray-200 rounded-lg px-3 py-3 md:py-2 text-gray-900 focus:outline-none focus:border-blue-500 touch-target">
                                </div>

                                <!-- Password -->
                                <div>
                                    <label for="password" class="block text-sm text-gray-700 mb-2">Password</label>
                                    <div class="relative">
                                        <input type="password" id="password" name="password" required autocomplete="new-password" spellcheck="false"
                                            placeholder="Buat password untuk semua siswa"
                                            class="w-full bg-white border border-gray-200 rounded-lg px-3 py-3 md:py-2 pr-10 text-gray-900 focus:outline-none focus:border-blue-500 touch-target">
                                        <button type="button" onclick="togglePassword()" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-700 w-8 h-8 flex items-center justify-center">
                                            <i class="fas fa-eye" id="password-toggle-icon"></i>
                                        </button>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">Satu password untuk semua siswa di batch ini.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Jurusan / Program Studi -->
                        <div class="mt-4 md:mt-6">
                            <span class="block text-sm text-gray-700 mb-2" id="label-jurusan">Jurusan</span>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                <?php foreach ($jurusan_list as $code): ?>
                                    <label class="pick-card">
                                        <input type="radio" name="jurusan_pilihan" value="<?= htmlspecialchars($code) ?>" <?= $val_jurusan === $code ? 'checked' : '' ?>>
                                        <span class="pick-title"><?= htmlspecialchars($code) ?></span>
                                        <?php if (jurusan_label($code) !== $code): ?>
                                            <span class="pick-sub"><?= htmlspecialchars(jurusan_label($code)) ?></span>
                                        <?php endif; ?>
                                    </label>
                                <?php endforeach; ?>
                                <label class="pick-card">
                                    <input type="radio" name="jurusan_pilihan" value="lainnya" <?= $val_jurusan_manual_aktif ? 'checked' : '' ?>>
                                    <span class="pick-title">Lainnya</span>
                                    <span class="pick-sub">Ketik jurusan manual</span>
                                </label>
                            </div>
                            <div id="jurusan-manual-wrap" class="mt-3 <?= $val_jurusan_manual_aktif ? '' : 'hidden' ?>">
                                <input type="text" id="jurusan_manual" name="jurusan_manual" data-upper maxlength="50" autocomplete="off"
                                    value="<?= $val_jurusan_manual_aktif ? htmlspecialchars($val_jurusan) : '' ?>"
                                    placeholder="KETIK JURUSAN / PROGRAM STUDI"
                                    class="uppercase w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-3 md:py-2 text-gray-900 focus:outline-none focus:border-blue-500 touch-target">
                            </div>
                            <input type="hidden" name="jurusan" id="jurusan" value="">
                            <p class="text-xs text-gray-700 mt-1">Pilih satu kartu, atau pilih Lainnya lalu ketik manual.</p>
                        </div>

                        <!-- Durasi PKL -->
                        <div class="mt-4 md:mt-6">
                            <span class="block text-sm text-gray-700 mb-2" id="label-pkl">Durasi PKL</span>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="pkl_mulai" class="block text-xs text-gray-500 mb-1">Mulai</label>
                                    <input type="date" id="pkl_mulai" name="pkl_mulai" value="<?= htmlspecialchars($val_pkl_mulai) ?>"
                                        class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-3 md:py-2 text-gray-900 focus:outline-none focus:border-blue-500 touch-target">
                                </div>
                                <div>
                                    <label for="pkl_selesai" class="block text-xs text-gray-500 mb-1">Selesai</label>
                                    <input type="date" id="pkl_selesai" name="pkl_selesai" value="<?= htmlspecialchars($val_pkl_selesai) ?>"
                                        class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-3 md:py-2 text-gray-900 focus:outline-none focus:border-blue-500 touch-target">
                                </div>
                            </div>
                        </div>

                        <!-- Submit button - Full width on mobile -->
                        <div class="flex justify-end mt-6 md:mt-8">
                            <button type="submit" class="w-full md:w-auto px-6 py-3 md:py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors font-medium">
                                <i class="fas fa-save mr-2"></i> Simpan Data Siswa
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Back button on mobile only -->
                <div class="mt-6 flex justify-center lg:hidden">
                    <a href="index.php" class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 rounded-lg flex items-center justify-center text-sm transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i> Kembali ke Daftar Siswa
                    </a>
                </div>
            </div>
        </div>
    </main>

    <script src="<?= str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 1) ?>assets/js/diome.js"></script>
    <script>
        // Image preview functionality with mobile optimizations
        function previewImage() {
            const input = document.getElementById('foto_profil');
            const preview = document.getElementById('preview-image');

            if (input.files && input.files[0]) {
                const reader = new FileReader();

                reader.onload = function(e) {
                    preview.src = e.target.result;

                    // Add visual feedback for mobile
                    preview.classList.add('scale-[0.98]');
                    setTimeout(() => {
                        preview.classList.remove('scale-[0.98]');
                    }, 200);
                }

                reader.readAsDataURL(input.files[0]);
            }
        }

        // Toggle password visibility with better mobile touch area
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('password-toggle-icon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Mode-specific labels
        const labelKelas = document.getElementById('label-kelas');
        const labelPkl = document.getElementById('label-pkl');
        const labelJurusan = document.getElementById('label-jurusan');
        const modeRadios = document.querySelectorAll('input[name="jenis"]');
        const kelasTingkat = document.getElementById('kelas_tingkat');
        const kelasSuffix = document.getElementById('kelas_suffix');

        const tingkatSmk = ['X', 'XI', 'XII'];
        const tingkatMahasiswa = ['Semester 1', 'Semester 2', 'Semester 3', 'Semester 4', 'Semester 5', 'Semester 6'];

        function rebuildTingkat(jenis) {
            const list = jenis === 'mahasiswa' ? tingkatMahasiswa : tingkatSmk;
            const current = kelasTingkat.value;
            kelasTingkat.innerHTML = '<option value="">Tingkat</option>' +
                list.map(function (t) { return '<option value="' + t + '">' + t + '</option>'; }).join('');
            kelasTingkat.value = list.indexOf(current) !== -1 ? current : '';
        }

        function applyMode(jenis) {
            const mahasiswa = jenis === 'mahasiswa';
            labelKelas.textContent = mahasiswa ? 'Kelas / Semester' : 'Kelas';
            labelPkl.textContent = mahasiswa ? 'Durasi Magang / PKL' : 'Durasi PKL';
            labelJurusan.textContent = mahasiswa ? 'Program Studi' : 'Jurusan';
            kelasSuffix.placeholder = mahasiswa ? 'TEKNIK INFORMATIKA' : 'RPL 1';
            rebuildTingkat(jenis);
        }

        modeRadios.forEach(radio => radio.addEventListener('change', () => applyMode(radio.value)));
        applyMode(document.querySelector('input[name="jenis"]:checked')?.value || 'smk');

        // Baris siswa: jumlah menentukan berapa baris nama/No HP/jenis kelamin
        const jumlahInput = document.getElementById('jumlah');
        const rowsWrap = document.getElementById('siswa-rows');

        function rowMarkup(index) {
            return `
                <div class="siswa-row rounded-lg border border-gray-200 bg-gray-50 p-3 md:p-4">
                    <p class="row-label text-xs font-semibold text-blue-700 mb-2">Siswa ${index}</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs text-gray-700 mb-1">Nama Lengkap</label>
                            <input type="text" name="siswa_nama[]" data-upper required maxlength="100"
                                class="uppercase w-full bg-white border border-gray-200 rounded-lg px-3 py-3 md:py-2 text-gray-900 focus:outline-none focus:border-blue-500 touch-target">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-700 mb-1">No HP</label>
                            <input type="tel" name="siswa_no_hp[]" maxlength="20" placeholder="08xxxxxxxxxx"
                                class="w-full bg-white border border-gray-200 rounded-lg px-3 py-3 md:py-2 text-gray-900 focus:outline-none focus:border-blue-500 touch-target">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-700 mb-1">Jenis Kelamin</label>
                            <select name="siswa_gender[]"
                                class="w-full bg-white border border-gray-200 rounded-lg px-3 py-3 md:py-2 text-gray-900 focus:outline-none focus:border-blue-500 touch-target">
                                <option value="">-</option>
                                <option value="L">Laki-laki</option>
                                <option value="P">Perempuan</option>
                            </select>
                        </div>
                    </div>
                </div>`;
        }

        function renumberRows() {
            rowsWrap.querySelectorAll('.siswa-row').forEach(function (row, idx) {
                const label = row.querySelector('.row-label');
                if (label) label.textContent = 'Siswa ' + (idx + 1);
            });
        }

        function syncRows() {
            let n = parseInt(jumlahInput.value, 10);
            if (isNaN(n)) n = 1;
            n = Math.max(1, Math.min(50, n));

            let rows = rowsWrap.querySelectorAll('.siswa-row').length;
            while (rows < n) {
                rowsWrap.insertAdjacentHTML('beforeend', rowMarkup(rows + 1));
                rows++;
            }
            while (rows > n) {
                rowsWrap.lastElementChild.remove();
                rows--;
            }
            renumberRows();
        }

        jumlahInput.addEventListener('input', syncRows);
        jumlahInput.addEventListener('change', function () {
            syncRows();
            const n = Math.max(1, Math.min(50, parseInt(jumlahInput.value, 10) || 1));
            jumlahInput.value = n;
        });
        syncRows();

        // Sekolah: opsi Lainnya memunculkan kolom ketik manual
        const sekolahSelect = document.getElementById('sekolah_pilihan');
        const sekolahCustom = document.getElementById('sekolah_custom');

        function applySekolah(focus) {
            const lainnya = sekolahSelect.value === 'lainnya';
            sekolahCustom.classList.toggle('hidden', !lainnya);
            if (lainnya && focus) sekolahCustom.focus();
        }

        sekolahSelect.addEventListener('change', function () { applySekolah(true); });
        applySekolah(false);

        // Semua kolom bertanda data-upper otomatis jadi huruf besar
        document.addEventListener('input', function (e) {
            if (!e.target.matches('input[data-upper]')) return;
            const start = e.target.selectionStart;
            const end = e.target.selectionEnd;
            e.target.value = e.target.value.toUpperCase();
            if (start !== null && end !== null) {
                e.target.setSelectionRange(start, end);
            }
        });

        // Jurusan card selection feeds the hidden field
        const jurusanRadios = document.querySelectorAll('input[name="jurusan_pilihan"]');
        const jurusanHidden = document.getElementById('jurusan');
        const jurusanManualWrap = document.getElementById('jurusan-manual-wrap');
        const jurusanManual = document.getElementById('jurusan_manual');

        function syncJurusan() {
            const checked = document.querySelector('input[name="jurusan_pilihan"]:checked');
            if (!checked) return;
            if (checked.value === 'lainnya') {
                jurusanManualWrap.classList.remove('hidden');
                jurusanHidden.value = jurusanManual.value.trim().toUpperCase();
            } else {
                jurusanManualWrap.classList.add('hidden');
                jurusanHidden.value = checked.value;
            }
        }

        jurusanRadios.forEach(radio => radio.addEventListener('change', () => {
            syncJurusan();
            if (radio.value === 'lainnya') jurusanManual.focus();
        }));
        jurusanManual.addEventListener('input', syncJurusan);
        syncJurusan();

        // Mobile sidebar toggle function
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');

            if (sidebar.classList.contains('-translate-x-full')) {
                // Open sidebar
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            } else {
                // Close sidebar
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
        }

        // Update time for mobile view
        function updateMobileTime() {
            const mobileTimeElement = document.getElementById('current-time-mobile');
            if (mobileTimeElement) {
                const now = new Date();
                const timeString = now.toLocaleTimeString('id-ID', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                });
                mobileTimeElement.textContent = timeString;
            }
        }

        // Add mobile time updater
        setInterval(updateMobileTime, 60000); // Update every minute
        updateMobileTime(); // Initial call

        // Make sure sidebar closes when pressing escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                // Close sidebar on mobile
                if (window.innerWidth < 1024) {
                    const sidebar = document.getElementById('sidebar');
                    if (!sidebar.classList.contains('-translate-x-full')) {
                        toggleSidebar();
                    }
                }
            }
        });

        // Fix viewport height issues on mobile browsers
        function setMobileHeight() {
            const vh = window.innerHeight * 0.01;
            document.documentElement.style.setProperty('--vh', `${vh}px`);
        }

        window.addEventListener('resize', setMobileHeight);
        setMobileHeight();

        // Add better touch handling for mobile devices
        document.addEventListener('DOMContentLoaded', function() {
            if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
                // Enhance touch areas for mobile
                document.querySelectorAll('input, select, button').forEach(element => {
                    element.classList.add('touch-target');
                });

                // Prevent iOS zoom on input focus
                const viewportMeta = document.querySelector('meta[name="viewport"]');
                if (viewportMeta) {
                    if (/(iPhone|iPad|iPod)/i.test(navigator.userAgent)) {
                        viewportMeta.content = 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0';
                    }
                }

                // Improve profile image upload on mobile
                const profileImgContainer = document.querySelector('.profile-upload');
                if (profileImgContainer) {
                    profileImgContainer.addEventListener('touchstart', function() {
                        this.style.transform = 'scale(0.95)';
                    });

                    profileImgContainer.addEventListener('touchend', function() {
                        this.style.transform = 'scale(1)';
                    });
                }
            }
        });
    </script>
</body>

</html>