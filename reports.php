<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// الفلاتر
$filter_branch = $_GET['branch'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_assigned = $_GET['assigned'] ?? '';
$filter_start = $_GET['start_date'] ?? '';
$filter_end = $_GET['end_date'] ?? '';

// جلب جميع المستخدمين والمهندسين
$users = $pdo->query("SELECT * FROM users")->fetchAll();
$branches = $pdo->query("SELECT DISTINCT branch_name FROM broken_laptops")->fetchAll(PDO::FETCH_COLUMN);

// بناء استعلام الأجهزة مع الفلاتر
$query = "SELECT b.*, u1.username AS entered_by, u2.username AS assigned_to, c.category_name 
          FROM broken_laptops b
          LEFT JOIN users u1 ON b.entered_by_user_id=u1.user_id
          LEFT JOIN users u2 ON b.assigned_user_id=u2.user_id
          LEFT JOIN categories c ON b.category_id=c.category_id
          WHERE 1";
$params = [];

if ($filter_status) { $query .= " AND b.status=?"; $params[] = $filter_status; }
if ($filter_branch) { $query .= " AND b.branch_name=?"; $params[] = $filter_branch; }
if ($filter_assigned) { $query .= " AND b.assigned_user_id=?"; $params[] = $filter_assigned; }
if ($filter_start) { $query .= " AND b.laptop_id IN (SELECT laptop_id FROM operations WHERE operation_date >= ?)"; $params[] = $filter_start . " 00:00:00"; }
if ($filter_end) { $query .= " AND b.laptop_id IN (SELECT laptop_id FROM operations WHERE operation_date <= ?)"; $params[] = $filter_end . " 23:59:59"; }

$query .= " ORDER BY b.laptop_id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$laptops = $stmt->fetchAll();

// إحصائيات عامة
$total_laptops = count($laptops);
$total_closed = count(array_filter($laptops, fn($l)=>$l['status']=='مغلق'));
$total_open = $total_laptops - $total_closed;

?>
<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>التقارير - النظام</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-4">

<div class="max-w-7xl mx-auto">

    <header class="mb-6 flex justify-between items-center">
        <h1 class="text-2xl font-bold">التقارير</h1>
        <div>
            <span class="mr-4">مرحبا، <?= htmlspecialchars($username) ?></span>
            <a href="logout.php" class="bg-red-500 text-white px-3 py-1 rounded">تسجيل الخروج</a>
        </div>
    </header>

    <!-- فلترة -->
    <div class="bg-white p-4 rounded shadow mb-6">
        <h2 class="font-bold mb-2">فلترة البيانات</h2>
        <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-3">
            <select name="status" class="p-2 border rounded">
                <option value="">كل الحالات</option>
                <option value="تحت الإصلاح" <?= $filter_status=='تحت الإصلاح'?'selected':'' ?>>تحت الإصلاح</option>
                <option value="مغلق" <?= $filter_status=='مغلق'?'selected':'' ?>>مغلق</option>
            </select>

            <select name="branch" class="p-2 border rounded">
                <option value="">كل الفروع</option>
                <?php foreach($branches as $b): ?>
                    <option value="<?= htmlspecialchars($b) ?>" <?= $filter_branch==$b?'selected':'' ?>><?= htmlspecialchars($b) ?></option>
                <?php endforeach; ?>
            </select>

            <select name="assigned" class="p-2 border rounded">
                <option value="">كل المهندسين</option>
                <?php foreach($users as $u): ?>
                    <option value="<?= $u['user_id'] ?>" <?= $filter_assigned==$u['user_id']?'selected':'' ?>><?= htmlspecialchars($u['username']) ?></option>
                <?php endforeach; ?>
            </select>

            <input type="date" name="start_date" class="p-2 border rounded" value="<?= htmlspecialchars($filter_start) ?>" placeholder="من تاريخ">
            <input type="date" name="end_date" class="p-2 border rounded" value="<?= htmlspecialchars($filter_end) ?>" placeholder="إلى تاريخ">

            <button type="submit" class="bg-gray-500 text-white px-4 py-2 rounded">تصفية</button>
        </form>
    </div>

    <!-- إحصائيات -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white p-4 rounded shadow text-center">
            <h3 class="font-bold text-lg">إجمالي الأجهزة</h3>
            <p class="text-2xl"><?= $total_laptops ?></p>
        </div>
        <div class="bg-white p-4 rounded shadow text-center">
            <h3 class="font-bold text-lg">الأجهزة المغلقة</h3>
            <p class="text-2xl text-green-600"><?= $total_closed ?></p>
        </div>
        <div class="bg-white p-4 rounded shadow text-center">
            <h3 class="font-bold text-lg">الأجهزة المفتوحة</h3>
            <p class="text-2xl text-red-600"><?= $total_open ?></p>
        </div>
    </div>

    <!-- جدول الأجهزة -->
    <div class="bg-white p-4 rounded shadow">
        <h2 class="font-bold mb-4">الأجهزة المفلترة</h2>
        <?php if(count($laptops)===0): ?>
            <p>لا توجد بيانات.</p>
        <?php else: ?>
            <table class="w-full table-auto border-collapse border border-gray-300">
                <thead>
                    <tr class="bg-gray-200">
                        <th class="border border-gray-300 p-2">السيريال</th>
                        <th class="border border-gray-300 p-2">الصنف</th>
                        <th class="border border-gray-300 p-2">الفرع</th>
                        <th class="border border-gray-300 p-2">مدخل البيانات</th>
                        <th class="border border-gray-300 p-2">المهندس المسؤول</th>
                        <th class="border border-gray-300 p-2">نوع المشكلة</th>
                        <th class="border border-gray-300 p-2">الحالة</th>
                        <th class="border border-gray-300 p-2">عدد التكرار</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($laptops as $l): ?>
                        <tr>
                            <td class="border border-gray-300 p-2"><?= htmlspecialchars($l['serial_number']) ?></td>
                            <td class="border border-gray-300 p-2"><?= htmlspecialchars($l['category_name']) ?></td>
                            <td class="border border-gray-300 p-2"><?= htmlspecialchars($l['branch_name']) ?></td>
                            <td class="border border-gray-300 p-2"><?= htmlspecialchars($l['entered_by']) ?></td>
                            <td class="border border-gray-300 p-2"><?= htmlspecialchars($l['assigned_to']) ?></td>
                            <td class="border border-gray-300 p-2"><?= htmlspecialchars($l['problem_type']) ?></td>
                            <td class="border border-gray-300 p-2"><?= htmlspecialchars($l['status'] ?? 'تحت الإصلاح') ?></td>
                            <td class="border border-gray-300 p-2"><?= htmlspecialchars($l['repeat_problem_count']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>

</body>
</html>
