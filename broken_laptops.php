<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// إضافة جهاز جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_laptop'])) {
    $category_id = $_POST['category_id'];
    $serial_number = $_POST['serial_number'];
    $specs = $_POST['specs'];
    $with_charger = isset($_POST['with_charger']) ? 1 : 0;
    $branch_name = $_POST['branch_name'];
    $problem_details = $_POST['problem_details'];
    $assigned_user_id = $_POST['assigned_user_id'];
    $problem_type = $_POST['problem_type'];
    $repeat_problem_count = $_POST['repeat_problem_count'] ?? 0;

    $stmt = $pdo->prepare("INSERT INTO broken_laptops (category_id, serial_number, specs, with_charger, branch_name, problem_details, entered_by_user_id, assigned_user_id, problem_type, repeat_problem_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$category_id, $serial_number, $specs, $with_charger, $branch_name, $problem_details, $user_id, $assigned_user_id, $problem_type, $repeat_problem_count]);

    header("Location: broken_laptops.php?msg=created");
    exit;
}

// البحث والتصفية
$filter_status = $_GET['status'] ?? '';
$filter_branch = $_GET['branch'] ?? '';
$filter_assigned = $_GET['assigned'] ?? '';

// جلب الأجهزة
$query = "SELECT b.*, u1.username AS entered_by, u2.username AS assigned_to, c.category_name 
          FROM broken_laptops b
          LEFT JOIN users u1 ON b.entered_by_user_id=u1.user_id
          LEFT JOIN users u2 ON b.assigned_user_id=u2.user_id
          LEFT JOIN categories c ON b.category_id=c.category_id
          WHERE 1";

$params = [];
if ($filter_status) {
    $query .= " AND b.status=?";
    $params[] = $filter_status;
}
if ($filter_branch) {
    $query .= " AND b.branch_name LIKE ?";
    $params[] = "%$filter_branch%";
}
if ($filter_assigned) {
    $query .= " AND b.assigned_user_id=?";
    $params[] = $filter_assigned;
}

$query .= " ORDER BY b.laptop_id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$laptops = $stmt->fetchAll();

// جلب جميع المستخدمين والمهندسين
$users = $pdo->query("SELECT * FROM users")->fetchAll();
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>إدارة اللابتوبات المعطلة</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-4">

<div class="max-w-7xl mx-auto">

    <header class="mb-6 flex justify-between items-center">
        <h1 class="text-2xl font-bold">إدارة اللابتوبات المعطلة</h1>
        <div>
            <span class="mr-4">مرحبا، <?= htmlspecialchars($username) ?></span>
            <a href="logout.php" class="bg-red-500 text-white px-3 py-1 rounded">تسجيل الخروج</a>
        </div>
    </header>

    <!-- رسالة نجاح -->
    <?php if(isset($_GET['msg']) && $_GET['msg']=='created'): ?>
        <div class="bg-green-100 text-green-800 p-2 rounded mb-3">تم إضافة الجهاز بنجاح!</div>
    <?php endif; ?>

    <!-- نموذج إضافة جهاز -->
    <div class="bg-white p-4 rounded shadow mb-6">
        <h2 class="font-bold mb-2">إضافة جهاز جديد</h2>
        <form method="POST">
            <input type="hidden" name="add_laptop" value="1">

            <label>الصنف</label>
            <select name="category_id" class="w-full p-2 border rounded mb-3" required>
                <option value="">اختر الصنف</option>
                <?php foreach($categories as $cat): ?>
                    <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label>السيريال</label>
            <input type="text" name="serial_number" class="w-full p-2 border rounded mb-3" required>

            <label>المواصفات</label>
            <textarea name="specs" class="w-full p-2 border rounded mb-3"></textarea>

            <label>بشاحن؟</label>
            <input type="checkbox" name="with_charger" class="mb-3">

            <label>الفرع</label>
            <input type="text" name="branch_name" class="w-full p-2 border rounded mb-3" required>

            <label>تفاصيل المشكلة</label>
            <textarea name="problem_details" class="w-full p-2 border rounded mb-3" required></textarea>

            <label>المهندس المسؤول</label>
            <select name="assigned_user_id" class="w-full p-2 border rounded mb-3">
                <option value="">اختياري</option>
                <?php foreach($users as $u): ?>
                    <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['username']) ?> (<?= $u['permissions'] ?>)</option>
                <?php endforeach; ?>
            </select>

            <label>نوع المشكلة</label>
            <input type="text" name="problem_type" class="w-full p-2 border rounded mb-3">

            <label>عدد مرات تكرار المشكلة</label>
            <input type="number" name="repeat_problem_count" class="w-full p-2 border rounded mb-3" value="0">

            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">إضافة الجهاز</button>
        </form>
    </div>

    <!-- التصفية والبحث -->
    <div class="bg-white p-4 rounded shadow mb-6">
        <h2 class="font-bold mb-2">تصفية الأجهزة</h2>
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <select name="status" class="p-2 border rounded">
                <option value="">كل الحالات</option>
                <option value="تحت الإصلاح" <?= $filter_status=='تحت الإصلاح'?'selected':'' ?>>تحت الإصلاح</option>
                <option value="مغلق" <?= $filter_status=='مغلق'?'selected':'' ?>>مغلق</option>
            </select>
            <input type="text" name="branch" placeholder="بحث بالفرع" class="p-2 border rounded" value="<?= htmlspecialchars($filter_branch) ?>">
            <select name="assigned" class="p-2 border rounded">
                <option value="">كل المهندسين</option>
                <?php foreach($users as $u): ?>
                    <option value="<?= $u['user_id'] ?>" <?= $filter_assigned==$u['user_id']?'selected':'' ?>><?= htmlspecialchars($u['username']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="bg-gray-500 text-white px-4 py-2 rounded">تصفية</button>
        </form>
    </div>

    <!-- جدول الأجهزة -->
    <div class="bg-white p-4 rounded shadow">
        <h2 class="font-bold mb-4">الأجهزة المعطلة</h2>
        <?php if(count($laptops) === 0): ?>
            <p>لا توجد أجهزة.</p>
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
                        <th class="border border-gray-300 p-2">عدد التكرار</th>
                        <th class="border border-gray-300 p-2">الحالة</th>
                        <th class="border border-gray-300 p-2">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($laptops as $lap): ?>
                        <tr>
                            <td class="border border-gray-300 p-2"><?= htmlspecialchars($lap['serial_number']) ?></td>
                            <td class="border border-gray-300 p-2"><?= htmlspecialchars($lap['category_name']) ?></td>
                            <td class="border border-gray-300 p-2"><?= htmlspecialchars($lap['branch_name']) ?></td>
                            <td class="border border-gray-300 p-2"><?= htmlspecialchars($lap['entered_by']) ?></td>
                            <td class="border border-gray-300 p-2"><?= htmlspecialchars($lap['assigned_to']) ?></td>
                            <td class="border border-gray-300 p-2"><?= htmlspecialchars($lap['problem_type']) ?></td>
                            <td class="border border-gray-300 p-2"><?= htmlspecialchars($lap['repeat_problem_count']) ?></td>
                            <td class="border border-gray-300 p-2"><?= htmlspecialchars($lap['status'] ?? 'تحت الإصلاح') ?></td>
                            <td class="border border-gray-300 p-2 text-sm">
                                <a href="laptop_chat.php?laptop_id=<?= $lap['laptop_id'] ?>" class="text-blue-500 hover:underline">الدردشة</a> |
                                <a href="operations.php?laptop_id=<?= $lap['laptop_id'] ?>" class="text-green-500 hover:underline">العمليات</a> |
                                <a href="locks.php?laptop_id=<?= $lap['laptop_id'] ?>" class="text-red-500 hover:underline">إغلاق</a> |
                                <a href="complaints.php?laptop_id=<?= $lap['laptop_id'] ?>" class="text-indigo-500 hover:underline">الشكاوى</a>
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
