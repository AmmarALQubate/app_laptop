<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// جلب معرف الجهاز من الرابط
if (!isset($_GET['laptop_id'])) {
    die("معرف الجهاز غير موجود!");
}
$laptop_id = $_GET['laptop_id'];

// إضافة قفل جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lock_type = $_POST['lock_type'];
    $solution_percentage = $_POST['solution_percentage'];
    $more_description = $_POST['more_description'];
    $final_status = $_POST['final_status'];

    $stmt = $pdo->prepare("INSERT INTO locks (laptop_id, user_id, lock_type, solution_percentage, more_description, final_status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$laptop_id, $user_id, $lock_type, $solution_percentage, $more_description, $final_status]);

    // تحديث حالة الجهاز في broken_laptops
    $stmt = $pdo->prepare("UPDATE broken_laptops SET status=? WHERE laptop_id=?");
    $stmt->execute([$final_status, $laptop_id]);

    header("Location: locks.php?laptop_id=$laptop_id&msg=added");
    exit;
}

// جلب بيانات الجهاز
$stmt = $pdo->prepare("SELECT * FROM broken_laptops WHERE laptop_id=?");
$stmt->execute([$laptop_id]);
$laptop = $stmt->fetch();

// جلب الأقفال السابقة
$stmt = $pdo->prepare("SELECT l.*, u.username FROM locks l LEFT JOIN users u ON l.user_id=u.user_id WHERE l.laptop_id=? ORDER BY l.lock_date DESC");
$stmt->execute([$laptop_id]);
$locks = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>إغلاق الجهاز - <?= htmlspecialchars($laptop['serial_number']) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-4">
<div class="max-w-3xl mx-auto">

    <h1 class="text-xl font-bold mb-4">إغلاق الجهاز: <?= htmlspecialchars($laptop['serial_number']) ?></h1>

    <?php if(isset($_GET['msg']) && $_GET['msg']=='added'): ?>
        <div class="bg-green-100 text-green-800 p-2 rounded mb-3">تم تسجيل القفل بنجاح!</div>
    <?php endif; ?>

    <!-- نموذج إضافة قفل -->
    <div class="bg-white p-4 rounded shadow mb-6">
        <h2 class="font-bold mb-2">تسجيل إغلاق جديد</h2>
        <form method="POST">
            <label>نوع القفل</label>
            <select name="lock_type" class="w-full p-2 border rounded mb-3" required>
                <option value="">اختر نوع القفل</option>
                <option value="نهائي">نهائي</option>
                <option value="مؤقت">مؤقت</option>
                <option value="تحويل">تحويل</option>
            </select>

            <label>نسبة حل المشكلة (%)</label>
            <input type="number" name="solution_percentage" class="w-full p-2 border rounded mb-3" min="0" max="100" required>

            <label>شرح إضافي</label>
            <textarea name="more_description" class="w-full p-2 border rounded mb-3"></textarea>

            <label>الحالة النهائية</label>
            <select name="final_status" class="w-full p-2 border rounded mb-3" required>
                <option value="">اختر الحالة النهائية</option>
                <option value="مغلق">مغلق</option>
                <option value="تحتاج إعادة صيانة">تحتاج إعادة صيانة</option>
                <option value="محول">محول</option>
            </select>

            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">تسجيل القفل</button>
        </form>
    </div>

    <!-- الأقفال السابقة -->
    <div class="bg-white p-4 rounded shadow">
        <h2 class="font-bold mb-2">الأقفال السابقة</h2>
        <?php if(count($locks) === 0): ?>
            <p>لا توجد أقفال سابقة.</p>
        <?php else: ?>
            <?php foreach($locks as $lock): ?>
                <div class="border-b py-2 mb-2">
                    <p><strong><?= htmlspecialchars($lock['username']) ?></strong> - <?= $lock['lock_date'] ?></p>
                    <p><strong>نوع القفل:</strong> <?= htmlspecialchars($lock['lock_type']) ?></p>
                    <p><strong>نسبة الحل:</strong> <?= htmlspecialchars($lock['solution_percentage']) ?>%</p>
                    <p><strong>الحالة النهائية:</strong> <?= htmlspecialchars($lock['final_status']) ?></p>
                    <p><strong>شرح إضافي:</strong> <?= nl2br(htmlspecialchars($lock['more_description'])) ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
