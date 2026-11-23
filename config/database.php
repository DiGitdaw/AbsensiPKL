<?php
session_start();

// Database configuration
$db_host = 'localhost';
$db_name = 'absensi_siswa';
$db_user = 'root';
$db_pass = '';

try {
    // Create PDO connection
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);

    // Set error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Set default fetch mode to associative array
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Set timezone for database
    $conn->exec("SET time_zone = '+07:00'");
} catch (PDOException $e) {
    // Display connection error
    die("Database Connection Failed: " . $e->getMessage());
}

// Set timezone for PHP
date_default_timezone_set('Asia/Jakarta');

// Application identity
define('APP_NAME', 'Absensi DJKI');
define('APP_SHORT_NAME', 'DJKI');
define('APP_LOGO', 'assets/default/logo-djki.png');

// Render the app logo, falling back to a text mark when the file is absent
function app_logo($prefix = '', $class = 'h-8 w-auto')
{
    if (file_exists(__DIR__ . '/../' . APP_LOGO)) {
        return '<img src="' . $prefix . APP_LOGO . '" alt="' . APP_SHORT_NAME . '" width="225" height="131" class="' . $class . '">';
    }

    return '<span class="' . $class . ' inline-flex items-center justify-center rounded bg-blue-600 px-2 text-[10px] font-bold tracking-wide">' . APP_SHORT_NAME . '</span>';
}

// Password: data lama tersimpan apa adanya, data baru memakai hash bcrypt
function password_cocok($input, $tersimpan)
{
    if (!is_string($tersimpan) || $tersimpan === '') {
        return false;
    }

    if (strpos($tersimpan, '$2y$') === 0 || strpos($tersimpan, '$2a$') === 0 || strpos($tersimpan, '$2b$') === 0) {
        return password_verify((string)$input, $tersimpan);
    }

    return hash_equals($tersimpan, (string)$input);
}

function password_siap_simpan($input)
{
    return password_hash((string)$input, PASSWORD_DEFAULT);
}

// Jurusan suggestions: the base template plus values already used in the database
function jurusan_options($conn)
{
    $base = array_keys(jurusan_map());
    $used = [];

    try {
        $stmt = $conn->query("SELECT DISTINCT jurusan FROM siswa WHERE jurusan IS NOT NULL AND jurusan <> ''");
        $used = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        $used = [];
    }

    $options = array_values(array_unique(array_merge($base, $used)));
    natcasesort($options);

    return array_values($options);
}

// Daftar kode jurusan dan nama panjangnya
function jurusan_map()
{
    return require __DIR__ . '/jurusan.php';
}

// Nama panjang jurusan, kembali ke kode kalau tidak ada di daftar
function jurusan_label($code)
{
    $map = jurusan_map();
    return $map[$code] ?? $code;
}

// Nama jurusan lengkap dengan singkatannya, misal: Rekayasa Perangkat Lunak (RPL)
function jurusan_display($code)
{
    $label = jurusan_label($code);
    return $label === $code ? $label : $label . ' (' . $code . ')';
}

// Ambil satu nilai pengaturan
function get_pengaturan($conn, $kunci, $default = null)
{
    try {
        $stmt = $conn->prepare("SELECT nilai FROM pengaturan WHERE kunci = :kunci");
        $stmt->execute(['kunci' => $kunci]);
        $nilai = $stmt->fetchColumn();
        return ($nilai !== false && $nilai !== null && $nilai !== '') ? $nilai : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

// Simpan satu nilai pengaturan
function set_pengaturan($conn, $kunci, $nilai)
{
    $sql = "INSERT INTO pengaturan (kunci, nilai) VALUES (:kunci, :nilai)
            ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        'kunci' => $kunci,
        'nilai' => $nilai !== '' ? $nilai : null
    ]);
}

// Saran kelas: daftar umum per jenis, ditambah nilai yang sudah dipakai
function kelas_options($conn, $jenis = 'smk')
{
    $base = $jenis === 'mahasiswa'
        ? ['Semester 1', 'Semester 2', 'Semester 3', 'Semester 4', 'Semester 5', 'Semester 6']
        : ['X', 'XI', 'XII'];

    $used = [];

    try {
        $stmt = $conn->query("SELECT DISTINCT kelas FROM siswa WHERE kelas IS NOT NULL AND kelas <> ''");
        $used = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        $used = [];
    }

    $options = array_values(array_unique(array_merge($base, $used)));
    sort($options, SORT_NATURAL);

    return $options;
}

// Daftar nama sekolah yang sudah dipakai
function sekolah_options($conn)
{
    try {
        $stmt = $conn->query("SELECT DISTINCT sekolah FROM siswa WHERE sekolah IS NOT NULL AND sekolah <> '' ORDER BY sekolah");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return [];
    }
}

// Function to check if user is logged in as admin
function isAdminLoggedIn()
{
    return isset($_SESSION['admin_id']);
}

// Function to check if user is logged in as student
function isSiswaLoggedIn()
{
    return isset($_SESSION['siswa_id']);
}

// Helper function to sanitize input
function sanitize($data)
{
    return htmlspecialchars(trim($data));
}

// Helper function to generate random string
function random_string($length = 10)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $length > $i; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

// Function to log activity (simplified)
function log_activity($user_type, $user_id, $activity_type, $description, $conn)
{
    $sql = "INSERT INTO activity_log (user_type, user_id, activity_type, description) 
            VALUES (:user_type, :user_id, :activity_type, :description)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        'user_type' => $user_type,
        'user_id' => $user_id,
        'activity_type' => $activity_type,
        'description' => $description
    ]);
}
