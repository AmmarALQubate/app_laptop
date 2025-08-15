<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// جلب إحصائيات الأجهزة
$total_broken = $pdo->query("SELECT COUNT(*) FROM broken_laptops")->fetchColumn();
$under_repair = $pdo->query("SELECT COUNT(*) FROM broken_laptops WHERE status IS NULL OR status='تحت الإصلاح'")->fetchColumn();
$closed = $pdo->query("SELECT COUNT(*) FROM broken_laptops WHERE status='مغلق'")->fetchColumn();

// جلب آخر الأجهزة المضافة
$stmt = $pdo->query("SELECT b.*, u.username AS entered_by FROM broken_laptops b LEFT JOIN users u ON b.entered_by_user_id=u.user_id ORDER BY b.laptop_id DESC LIMIT 10");
$laptops = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>لوحة التحكم الرئيسية</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-4">

<div class="max-w-6xl mx-auto">
    <header class="mb-6 flex justify-between items-center">
        <h1 class="text-2xl font-bold">لوحة التحكم - مرحباً <?= htmlspecialchars($username) ?></h1>
        <a href="logout.php" class="bg-red-500 text-white px-3 py-1 rounded">تسجيل الخروج</a>
    </header>

    <!-- إحصائيات سريعة -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white p-4 rounded shadow text-center">
            <h2 class="font-bold mb-2">إجمالي الأجهزة</h2>
            <p class="text-3xl"><?= $total_broken ?></p>
        </div>
        <div class="bg-yellow-100 p-4 rounded shadow text-center">
            <h2 class="font-bold mb-2">تحت الإصلاح</h2>
            <p class="text-3xl"><?= $under_repair ?></p>
        </div>
        <div class="bg-green-100 p-4 rounded shadow text-center">
            <h2 class="font-bold mb-2">المغلقة</h2>
            <p class="text-3xl"><?= $closed ?></p>
        </div>
    </div>

    <!-- روابط سريعة -->
    <div class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
        <a href="add_broken_laptop.php" class="bg-blue-500 text-white p-4 rounded text-center hover:bg-blue-600">إضافة جهاز جديد</a>
        <a href="broken_laptops.php" class="bg-indigo-500 text-white p-4 rounded text-center hover:bg-indigo-600">عرض الأجهزة</a>
        <a href="users.php" class="bg-purple-500 text-white p-4 rounded text-center hover:bg-purple-600">إدارة المستخدمين</a>
        <a href="locks_overview.php" class="bg-gray-500 text-white p-4 rounded text-center hover:bg-gray-600">الأقفال</a>
    </div>

    <!-- آخر الأجهزة المضافة -->
    <div class="bg-white p-4 rounded shadow">
        <h2 class="font-bold mb-4">آخر الأجهزة المضافة</h2>
        <?php if(count($laptops) === 0): ?>
            <p>لا توجد أجهزة مضافة بعد.</p>
        <?php else: ?>
            <table class="w-full table-auto border-collapse border border-gray-300">
                <thead>
                    <tr class="bg-gray-200">
                        <th class="border border-gray-300 p-2">السيريال</th>
                        <th class="border border-gray-300 p-2">الصنف</th>
                        <th class="border border-gray-300 p-2">الفرع</th>
                        <th class="border border-gray-300 p-2">مدخل البيانات</th>
                        <th class="border border-gray-300 p-2">الحالة</th>
                        <th class="border border-gray-300 p-2">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($laptops as $lap): ?>
                        <tr>
                            <td class="border border-gray-300 p-2"><?= htmlspecialchars($lap['serial_number']) ?></td>
                            <td class="border border-gray-300 p-2"><?= htmlspecialchars($lap['category_id']) ?></td>
                            <td class="border border-gray-300 p-2"><?= htmlspecialchars($lap['branch_name']) ?></td>
                            <td class="border border-gray-300 p-2"><?= htmlspecialchars($lap['entered_by']) ?></td>
                            <td class="border border-gray-300 p-2"><?= htmlspecialchars($lap['status'] ?? 'تحت الإصلاح') ?></td>
                            <td class="border border-gray-300 p-2">
                                <a href="laptop_chat.php?laptop_id=<?= $lap['laptop_id'] ?>" class="text-blue-500 hover:underline">الدردشة</a> |
                                <a href="operations.php?laptop_id=<?= $lap['laptop_id'] ?>" class="text-green-500 hover:underline">العمليات</a> |
                                <a href="locks.php?laptop_id=<?= $lap['laptop_id'] ?>" class="text-red-500 hover:underline">إغلاق</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>

</body>
</html>
