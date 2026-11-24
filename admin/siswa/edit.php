<?php
require_once '../../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

// Check for required ID
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$id = $_GET['id'];
$error = '';
$success = false;

// Get student data
$sql = "SELECT * FROM siswa WHERE id = :id";
$stmt = $conn->prepare($sql);
$stmt->execute(['id' => $id]);
$siswa = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$siswa) {
    header("Location: index.php?error=not_found");
    exit();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $nama_lengkap = $_POST['nama_lengkap'];
        $kelas_pilihan = $_POST['kelas_pilihan'] ?? '';
        $kelas = ($kelas_pilihan === 'lainnya') ? trim($_POST['kelas_custom'] ?? '') : trim($kelas_pilihan);
        $sekolah = trim($_POST['sekolah'] ?? '');
        $jurusan = trim($_POST['jurusan'] ?? '');
        $jenis = $_POST['jenis'] ?? 'smk';
        $jenis_kelamin = $_POST['jenis_kelamin'] ?? '';
        $no_hp = trim($_POST['no_hp'] ?? '');
        $pkl_mulai = $_POST['pkl_mulai'] ?? '';
        $pkl_selesai = $_POST['pkl_selesai'] ?? '';
        $password = !empty($_POST['password']) ? password_siap_simpan($_POST['password']) : $siswa['password'];

        if (!in_array($jenis, ['smk', 'mahasiswa'], true)) {
            $jenis = 'smk';
        }
        if (!in_array($jenis_kelamin, ['L', 'P', ''], true)) {
            $jenis_kelamin = '';
        }
        if ($kelas === '' || strlen($kelas) > 30) {
            throw new Exception("Kelas wajib diisi, maksimal 30 karakter.");
        }
        if (strlen($sekolah) > 100) {
            throw new Exception("Nama sekolah maksimal 100 karakter.");
        }
        if ($jurusan === '' || strlen($jurusan) > 50) {
            throw new Exception("Jurusan wajib diisi, maksimal 50 karakter.");
        }
        if ($no_hp !== '' && !preg_match('/^[0-9+\-\s()]{8,20}$/', $no_hp)) {
            throw new Exception("No HP tidak valid.");
        }
        if ($pkl_mulai !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $pkl_mulai)) {
            $pkl_mulai = '';
        }
        if ($pkl_selesai !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $pkl_selesai)) {
            $pkl_selesai = '';
        }

        // Start transaction
        $conn->beginTransaction();

        // Handle profile photo upload
        $foto_profil = $siswa['foto_profil']; // Default to current photo

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
            $filename = 'profile_' . $id . '_' . time() . '.' . $file_extension;
            $target_file = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['foto_profil']['tmp_name'], $target_file)) {
                $foto_profil = 'uploads/profile/' . $filename;

                // Delete old photo if it's not the default one
                if ($siswa['foto_profil'] !== 'assets/default/photo-profile.png' && file_exists('../../' . $siswa['foto_profil'])) {
                    unlink('../../' . $siswa['foto_profil']);
                }
            } else {
                throw new Exception("Gagal mengunggah foto profil.");
            }
        }

        // Update student data
        $sql = "UPDATE siswa SET 
                nama_lengkap = :nama_lengkap,
                kelas = :kelas,
                sekolah = :sekolah,
                jurusan = :jurusan,
                jenis = :jenis,
                jenis_kelamin = :jenis_kelamin,
                no_hp = :no_hp,
                pkl_mulai = :pkl_mulai,
                pkl_selesai = :pkl_selesai,
                password = :password,
                foto_profil = :foto_profil
                WHERE id = :id";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'nama_lengkap' => $nama_lengkap,
            'kelas' => $kelas,
            'sekolah' => $sekolah !== '' ? $sekolah : null,
            'jurusan' => $jurusan,
            'jenis' => $jenis,
            'jenis_kelamin' => $jenis_kelamin !== '' ? $jenis_kelamin : null,
            'no_hp' => $no_hp !== '' ? $no_hp : null,
            'pkl_mulai' => $pkl_mulai !== '' ? $pkl_mulai : null,
            'pkl_selesai' => $pkl_selesai !== '' ? $pkl_selesai : null,
            'password' => $password,
            'foto_profil' => $foto_profil,
            'id' => $id
        ]);

        // Log activity
        $sql = "INSERT INTO activity_log (user_type, user_id, activity_type, description) 
                VALUES ('admin', :admin_id, 'update', :description)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'admin_id' => $_SESSION['admin_id'],
            'description' => "Admin mengedit data siswa: $nama_lengkap"
        ]);

        $conn->commit();
        $success = true;

        // Refresh data
        $stmt = $conn->prepare("SELECT * FROM siswa WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $siswa = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($success) {
            header("Location: detail.php?id=$id&updated=true");
            exit();
        }
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
    <title>Edit Siswa - Absensi DJKI</title>
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
                    <a href="detail.php?id=<?= $id ?>" class="mr-3 p-2 rounded-full hover:bg-gray-50 transition-colors">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold">Edit Siswa</h1>
                        <p class="text-sm md:text-base text-gray-700">Ubah informasi data siswa</p>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="bg-green-500/10 border border-green-500/30 text-green-500 rounded-lg p-4 mb-6 flex items-start animate-fade-in">
                        <i class="fas fa-check-circle mt-0.5 mr-3"></i>
                        <div>
                            <p class="font-medium">Data berhasil diperbarui</p>
                            <p class="text-sm text-green-500/80 mt-1">Perubahan telah disimpan ke sistem.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="bg-red-500/10 border border-red-500/30 text-red-500 rounded-lg p-4 mb-6 flex items-start animate-fade-in">
                        <i class="fas fa-exclamation-circle mt-0.5 mr-3"></i>
                        <div>
                            <p class="font-medium">Gagal menyimpan perubahan</p>
                            <p class="text-sm text-red-500/80 mt-1"><?= $error ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Edit Form - Mobile responsive -->
                <div class="glass-effect rounded-xl p-4 md:p-6 mb-6">
                    <form method="POST" enctype="multipart/form-data">
                        <!-- Profile Picture - Improved for mobile -->
                        <div class="mb-6 text-center">
                            <div class="relative w-28 h-28 md:w-32 md:h-32 mx-auto">
                                <img id="preview-image" src="../../<?= $siswa['foto_profil'] ?>"
                                    alt="Profile" class="w-28 h-28 md:w-32 md:h-32 object-cover rounded-full border-4 border-gray-200">

                                <label for="foto_profil" class="absolute -bottom-2 -right-2 bg-blue-600 hover:bg-blue-700 rounded-full w-9 h-9 md:w-10 md:h-10 flex items-center justify-center cursor-pointer transition-colors profile-upload">
                                    <i class="fas fa-camera"></i>
                                </label>
                                <input type="file" id="foto_profil" name="foto_profil" accept="image/*" class="hidden" onchange="previewImage()">
                            </div>
                            <p class="text-xs md:text-sm text-gray-700 mt-3">Klik icon kamera untuk mengubah foto profil</p>
                        </div>

                        <!-- Mode pendaftar -->
                        <?php
                        $currentJenis = $siswa['jenis'] ?? 'smk';

                        $kelas_smk = kelas_options($conn, 'smk');
                        $kelas_mahasiswa = kelas_options($conn, 'mahasiswa');
                        $kelas_sekarang = trim($siswa['kelas']);
                        $kelas_terpilih = '';
                        foreach (array_merge($kelas_smk, $kelas_mahasiswa) as $option) {
                            if (strcasecmp($option, $kelas_sekarang) === 0) {
                                $kelas_terpilih = $option;
                                break;
                            }
                        }
                        $kelas_custom = $kelas_terpilih === '' ? $kelas_sekarang : '';
                        ?>
                        <div class="mb-6">
                            <span class="block text-sm text-gray-700 mb-2">Jenis Pendaftar</span>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="pick-card">
                                    <input type="radio" name="jenis" value="smk" <?= $currentJenis === 'smk' ? 'checked' : '' ?>>
                                    <span class="flex items-center gap-2">
                                        <i class="fas fa-school text-blue-600"></i>
                                        <span class="pick-title">SMK</span>
                                    </span>
                                    <span class="pick-sub">Siswa sekolah</span>
                                </label>
                                <label class="pick-card">
                                    <input type="radio" name="jenis" value="mahasiswa" <?= $currentJenis === 'mahasiswa' ? 'checked' : '' ?>>
                                    <span class="flex items-center gap-2">
                                        <i class="fas fa-university text-blue-600"></i>
                                        <span class="pick-title">Mahasiswa</span>
                                    </span>
                                    <span class="pick-sub">Peserta magang / PKL</span>
                                </label>
                            </div>
                        </div>

                        <!-- Form fields - Responsive grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                            <!-- Nama Lengkap -->
                            <div class="mb-2 md:mb-0">
                                <label for="nama_lengkap" class="block text-sm text-gray-700 mb-2">Nama Lengkap</label>
                                <input type="text" id="nama_lengkap" name="nama_lengkap" value="<?= htmlspecialchars($siswa['nama_lengkap']) ?>" required
                                    class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-3 md:py-2 text-gray-900 focus:outline-none focus:border-blue-500 touch-target">
                            </div>

                            <!-- Kelas -->
                            <div class="mb-2 md:mb-0">
                                <label for="kelas_pilihan" id="label-kelas" class="block text-sm text-gray-700 mb-2">Kelas</label>
                                <select id="kelas_pilihan" name="kelas_pilihan"
                                    class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-3 md:py-2 text-gray-900 focus:outline-none focus:border-blue-500 touch-target">
                                    <option value="">Pilih kelas</option>
                                    <?php if (!empty($kelas_smk)): ?>
                                        <optgroup label="SMK">
                                            <?php foreach ($kelas_smk as $option): ?>
                                                <option value="<?= htmlspecialchars($option) ?>" <?= $kelas_terpilih === $option ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>
                                    <?php if (!empty($kelas_mahasiswa)): ?>
                                        <optgroup label="Mahasiswa">
                                            <?php foreach ($kelas_mahasiswa as $option): ?>
                                                <option value="<?= htmlspecialchars($option) ?>" <?= $kelas_terpilih === $option ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>
                                    <option value="lainnya" <?= $kelas_custom !== '' ? 'selected' : '' ?>>Lainnya...</option>
                                </select>
                                <input type="text" id="kelas_custom" name="kelas_custom" maxlength="30" autocomplete="off"
                                    value="<?= htmlspecialchars($kelas_custom) ?>" placeholder="Ketik kelas / semester"
                                    class="w-full mt-2 bg-gray-50 border border-gray-200 rounded-lg px-3 py-3 md:py-2 text-gray-900 focus:outline-none focus:border-blue-500 touch-target <?= $kelas_custom === '' ? 'hidden' : '' ?>">
                            </div>

                            <!-- Nama Sekolah -->
                            <div class="mb-2 md:mb-0">
                                <label for="sekolah" class="block text-sm text-gray-700 mb-2">Nama Sekolah</label>
                                <input type="text" id="sekolah" name="sekolah" list="sekolah-options" maxlength="100" autocomplete="off"
                                    value="<?= htmlspecialchars($siswa['sekolah'] ?? '') ?>" placeholder="Contoh: SMAN 1 Jakarta"
                                    class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-3 md:py-2 text-gray-900 focus:outline-none focus:border-blue-500 touch-target">
                                <datalist id="sekolah-options">
                                    <?php foreach (sekolah_options($conn) as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>"></option>
                                    <?php endforeach; ?>
                                </datalist>
                            </div>

                            <!-- Jenis Kelamin -->
                            <div class="mb-2 md:mb-0">
                                <span class="block text-sm text-gray-700 mb-2">Jenis Kelamin</span>
                                <div class="grid grid-cols-2 gap-3">
                                    <label class="pick-card pick-inline">
                                        <input type="radio" name="jenis_kelamin" value="L" <?= ($siswa['jenis_kelamin'] ?? '') === 'L' ? 'checked' : '' ?>>
                                        <i class="fas fa-mars text-blue-600"></i>
                                        <span class="pick-title">Laki-laki</span>
                                    </label>
                                    <label class="pick-card pick-inline">
                                        <input type="radio" name="jenis_kelamin" value="P" <?= ($siswa['jenis_kelamin'] ?? '') === 'P' ? 'checked' : '' ?>>
                                        <i class="fas fa-venus text-blue-600"></i>
                                        <span class="pick-title">Perempuan</span>
                                    </label>
                                </div>
                            </div>

                            <!-- No HP -->
                            <div class="mb-2 md:mb-0">
                                <label for="no_hp" class="block text-sm text-gray-700 mb-2">No HP</label>
                                <input type="tel" id="no_hp" name="no_hp" maxlength="20" value="<?= htmlspecialchars($siswa['no_hp'] ?? '') ?>" placeholder="08xxxxxxxxxx"
                                    class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-3 md:py-2 text-gray-900 focus:outline-none focus:border-blue-500 touch-target">
                            </div>

                            <!-- Password - Improved for mobile -->
                            <div class="mb-2 md:mb-0">
                                <label for="password" class="block text-sm text-gray-700 mb-2">Password</label>
                                <div class="relative">
                                    <input type="password" id="password" name="password" placeholder="Biarkan kosong jika tidak diubah"
                                        autocomplete="new-password" spellcheck="false"
                                        class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-3 md:py-2 pr-10 text-gray-900 focus:outline-none focus:border-blue-500 touch-target">
                                    <button type="button" onclick="togglePassword()" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-700 w-8 h-8 flex items-center justify-center">
                                        <i class="fas fa-eye" id="password-toggle-icon"></i>
                                    </button>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Kosongkan jika tidak ingin mengubah password</p>
                            </div>
                        </div>

                        <!-- Jurusan / Program Studi -->
                        <?php
                        $currentJurusan = $siswa['jurusan'];
                        $jurusanDaftar = jurusan_options($conn);
                        $jurusanKnown = in_array($currentJurusan, $jurusanDaftar, true);
                        ?>
                        <div class="mt-4 md:mt-6">
                            <span class="block text-sm text-gray-700 mb-2" id="label-jurusan">Jurusan</span>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                <?php foreach ($jurusanDaftar as $code): ?>
                                    <label class="pick-card">
                                        <input type="radio" name="jurusan_pilihan" value="<?= htmlspecialchars($code) ?>" <?= $currentJurusan === $code ? 'checked' : '' ?>>
                                        <span class="pick-title"><?= htmlspecialchars($code) ?></span>
                                        <?php if (jurusan_label($code) !== $code): ?>
                                            <span class="pick-sub"><?= htmlspecialchars(jurusan_label($code)) ?></span>
                                        <?php endif; ?>
                                    </label>
                                <?php endforeach; ?>
                                <label class="pick-card">
                                    <input type="radio" name="jurusan_pilihan" value="lainnya" <?= $jurusanKnown ? '' : 'checked' ?>>
                                    <span class="pick-title">Lainnya</span>
                                    <span class="pick-sub">Ketik jurusan manual</span>
                                </label>
                            </div>
                            <div id="jurusan-manual-wrap" class="mt-3 <?= $jurusanKnown ? 'hidden' : '' ?>">
                                <input type="text" id="jurusan_manual" name="jurusan_manual" maxlength="50" autocomplete="off"
                                    value="<?= $jurusanKnown ? '' : htmlspecialchars($currentJurusan) ?>" placeholder="Ketik jurusan / program studi"
                                    class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-3 md:py-2 text-gray-900 focus:outline-none focus:border-blue-500 touch-target">
                            </div>
                            <input type="hidden" name="jurusan" id="jurusan" value="<?= htmlspecialchars($currentJurusan) ?>">
                            <p class="text-xs text-gray-700 mt-1">Pilih satu kartu, atau pilih Lainnya lalu ketik manual.</p>
                        </div>

                        <!-- Durasi PKL -->
                        <div class="mt-4 md:mt-6">
                            <span class="block text-sm text-gray-700 mb-2" id="label-pkl">Durasi PKL</span>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="pkl_mulai" class="block text-xs text-gray-500 mb-1">Mulai</label>
                                    <input type="date" id="pkl_mulai" name="pkl_mulai" value="<?= htmlspecialchars($siswa['pkl_mulai'] ?? '') ?>"
                                        class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-3 md:py-2 text-gray-900 focus:outline-none focus:border-blue-500 touch-target">
                                </div>
                                <div>
                                    <label for="pkl_selesai" class="block text-xs text-gray-500 mb-1">Selesai</label>
                                    <input type="date" id="pkl_selesai" name="pkl_selesai" value="<?= htmlspecialchars($siswa['pkl_selesai'] ?? '') ?>"
                                        class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-3 md:py-2 text-gray-900 focus:outline-none focus:border-blue-500 touch-target">
                                </div>
                            </div>
                        </div>

                        <!-- Submit button - Full width on mobile -->
                        <div class="flex justify-end mt-6 md:mt-8">
                            <button type="submit" class="w-full md:w-auto px-6 py-3 md:py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors font-medium">
                                <i class="fas fa-save mr-2"></i> Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Back button on mobile only -->
                <div class="mt-6 flex justify-center lg:hidden">
                    <a href="detail.php?id=<?= $id ?>" class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 rounded-lg flex items-center justify-center text-sm transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i> Kembali ke Detail Siswa
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

        function applyMode(jenis) {
            const mahasiswa = jenis === 'mahasiswa';
            labelKelas.textContent = mahasiswa ? 'Kelas / Semester' : 'Kelas';
            labelPkl.textContent = mahasiswa ? 'Durasi Magang / PKL' : 'Durasi PKL';
            labelJurusan.textContent = mahasiswa ? 'Program Studi' : 'Jurusan';
        }

        modeRadios.forEach(radio => radio.addEventListener('change', () => applyMode(radio.value)));
        applyMode(document.querySelector('input[name="jenis"]:checked')?.value || 'smk');

        // Kelas: input manual hanya muncul kalau pilih "Lainnya"
        const kelasSelect = document.getElementById('kelas_pilihan');
        const kelasCustom = document.getElementById('kelas_custom');

        function syncKelas() {
            const lainnya = kelasSelect.value === 'lainnya';
            kelasCustom.classList.toggle('hidden', !lainnya);
        }

        kelasSelect.addEventListener('change', function () {
            syncKelas();
            if (kelasSelect.value === 'lainnya') kelasCustom.focus();
        });
        syncKelas();

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
                jurusanHidden.value = jurusanManual.value.trim();
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
                    }, {
                        passive: true
                    });

                    profileImgContainer.addEventListener('touchend', function() {
                        this.style.transform = 'scale(1)';
                    }, {
                        passive: true
                    });
                }
            }
        });
    </script>
</body>

</html>