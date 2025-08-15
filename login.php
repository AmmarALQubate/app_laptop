<?php
session_start();
require 'db.php';


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && $password === $user['password']) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['permissions'] = $user['permissions'];
    
        switch ($user['permissions']) {
            case 'admin':
                header("Location: admin_dashboard.php");
                exit;
            case 'technician':
                header("Location: technician_dashboard.php");
                exit;
            case 'manager':
                header("Location: manager_dashboard.php");
                exit;
            default:
                $error = "صلاحية المستخدم غير معرفة";
        }
    } else {
        $error = "اسم المستخدم أو كلمة المرور غير صحيحة";
    }
    
}

?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-b from-blue-100 to-blue-300 flex justify-center items-center min-h-screen p-4">
    <div class="bg-white p-6 rounded-2xl shadow-lg w-full max-w-md">
        <h1 class="text-3xl font-bold mb-6 text-center text-blue-700">تسجيل الدخول</h1>

        <?php if (!empty($error)): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded mb-4 text-center font-medium">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block mb-1 font-medium text-gray-700">اسم المستخدم</label>
                <input type="text" name="username" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400" placeholder="ادخل اسم المستخدم" required>
            </div>

            <div>
                <label class="block mb-1 font-medium text-gray-700">كلمة المرور</label>
                <input type="password" name="password" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400" placeholder="ادخل كلمة المرور" required>
            </div>

            <button type="submit" class="w-full bg-blue-600 text-white font-semibold p-3 rounded-lg hover:bg-blue-700 transition duration-200">
                دخول
            </button>
        </form>

        <p class="text-center text-gray-500 text-sm mt-4">نسيت كلمة المرور؟ اتصل بالمسؤول.</p>
    </div>
</body>
</html>
