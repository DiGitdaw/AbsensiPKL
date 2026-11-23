<?php
require_once '../config/database.php';

if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard/");
    exit();
}

$error = '';
$remembered = $_COOKIE['djki_admin_user'] ?? '';

if (isset($_GET['lupa'])) {
    setcookie('djki_admin_user', '', time() - 3600, '/');
    header("Location: login.php");
    exit();
}

// Process the login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identifier === '' || $password === '') {
        $error = 'Nama/username dan password tidak boleh kosong.';
    } else {
        $sql = "SELECT * FROM admin WHERE username = :id1 OR LOWER(nama_lengkap) = LOWER(:id2) LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['id1' => $identifier, 'id2' => $identifier]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_cocok($password, $admin['password'])) {
            // Set session variables
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_name'] = $admin['nama_lengkap'];
            $_SESSION['admin_email'] = $admin['email'] ?? '';
            $_SESSION['admin_photo'] = $admin['foto_profil'];
            $_SESSION['admin_last_login'] = $admin['last_login'];

            // Record this login time
            $current_time = date('Y-m-d H:i:s');
            $update_sql = "UPDATE admin SET last_login = :login_time WHERE id = :admin_id";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->execute([
                'login_time' => $current_time,
                'admin_id' => $admin['id']
            ]);

            $_SESSION['admin_last_login'] = $current_time;

            // Log activity
            $log_sql = "INSERT INTO activity_log (user_type, user_id, activity_type, description) 
                        VALUES ('admin', :user_id, 'login', :description)";
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->execute([
                'user_id' => $admin['id'],
                'description' => "Admin {$admin['nama_lengkap']} login ke sistem"
            ]);

            // Ingat nama/username supaya login berikutnya cukup password
            setcookie('djki_admin_user', $identifier, time() + (30 * 24 * 60 * 60), '/');

            header("Location: dashboard/index.php");
            exit();
        } else {
            $error = 'Nama/username atau password salah.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Absensi DJKI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 1) ?>assets/css/theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .glass-effect {
            background: #ffffff;
            /* Darker background */
            backdrop-filter: blur(10px);
            border: 1px solid #BFDBFE;
        }

        /* Fix white background in autofill */
        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus {
            -webkit-text-fill-color: white;
            -webkit-box-shadow: 0 0 0px 1000px #1F2937 inset;
            transition: background-color 5000s ease-in-out 0s;
        }
    </style>
</head>

<body class="bg-white min-h-screen flex items-center justify-center bg-[url('../assets/default/bg-pattern.png')] bg-repeat">
    <!-- Overlay gradien biru -->
    <div class="fixed inset-0 bg-gradient-to-br from-blue-600/25 to-gray-100 pointer-events-none"></div>

    <div class="max-w-md w-full mx-4 relative z-10">
        <!-- Logo & Title -->
        <div class="text-center mb-8">
            <?= app_logo('../', 'h-24 mx-auto mb-4 drop-shadow-[0_0_15px_#2E77BC]') ?>
            <h2 class="text-3xl font-bold text-gray-900 mb-2 text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-blue-700">
                Login Admin
            </h2>
            <p class="text-gray-700">Sistem Absensi</p>
        </div>

        <!-- Login Form -->
        <div class="glass-effect rounded-xl shadow-2xl p-8 shadow-yellow-900/20">
            <?php if ($error): ?>
                <div class="bg-red-500/10 border border-red-500/50 text-red-500 px-4 py-3 rounded-lg relative mb-6 flex items-center" role="alert">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <p class="text-sm"><?= $error ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div class="space-y-4">
                    <?php if ($remembered !== ''): ?>
                        <input type="hidden" name="identifier" value="<?= htmlspecialchars($remembered) ?>">
                        <div class="flex items-center justify-between bg-gray-50 border border-gray-200 rounded-lg px-4 py-3">
                            <span class="text-sm text-gray-900">
                                <i class="fas fa-user text-blue-600 mr-2"></i><?= htmlspecialchars($remembered) ?>
                            </span>
                            <a href="login.php?lupa=1" class="text-xs text-blue-600 hover:underline">Ganti</a>
                        </div>
                    <?php else: ?>
                        <div class="group">
                            <label class="text-gray-700 text-sm font-medium mb-2 block opacity-90">
                                <i class="fas fa-user text-blue-600 mr-2"></i>Nama / Username
                            </label>
                            <input type="text" name="identifier" required
                                class="w-full px-5 py-4 rounded-lg bg-gray-50 border border-blue-200 text-gray-900 
                                focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-yellow-500/20 
                                transition-all duration-300 placeholder-gray-500"
                                placeholder="Masukkan nama atau username">
                        </div>
                    <?php endif; ?>

                    <div class="group">
                        <label class="text-gray-700 text-sm font-medium mb-2 block opacity-90">
                            <i class="fas fa-lock text-blue-600 mr-2"></i>Password
                        </label>
                        <div class="relative">
                            <input type="password" name="password" required id="password"
                                class="w-full px-5 py-4 rounded-lg bg-gray-50 border border-blue-200 text-gray-900 
                                focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-yellow-500/20
                                transition-all duration-300 placeholder-gray-500"
                                placeholder="Masukkan password">
                            <button type="button" onclick="togglePassword()"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-700 hover:text-blue-600
                                transition-colors duration-300">
                                <i class="fas fa-eye text-lg" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <button type="submit"
                    class="w-full bg-gradient-to-r from-blue-700 to-blue-800 text-white font-medium py-4 px-4 
                    rounded-lg transition duration-300 hover:opacity-90 transform hover:-translate-y-0.5
                    focus:outline-none focus:ring-2 focus:ring-yellow-500/20 flex items-center justify-center
                    shadow-lg shadow-yellow-900/30">
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Login
                </button>
            </form>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8 text-gray-700 text-sm">
            <p>&copy; <?= date('Y') ?> Absensi DJKI</p>
            <p class="mt-1 text-gray-500">Sistem Informasi Absensi Siswa</p>
        </div>
    </div>

    <script>
        function togglePassword() {
            const password = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');

            if (password.type === 'password') {
                password.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>

</html>