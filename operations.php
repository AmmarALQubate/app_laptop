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

// إضافة عملية إصلاح جديدة
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $repair_result = $_POST['repair_result'];
    $remaining_problems_count = $_POST['remaining_problems_count'];
    $details = $_POST['details'];

    // رفع صورة إذا موجودة
    $image_path = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $image_name = 'op_'.$laptop_id.'_'.time().'.'.$ext;
        $target = 'uploads/'.$image_name;
        move_uploaded_file($_FILES['image']['tmp_name'], $target);
        $image_path = $target;
    }

    $stmt = $pdo->prepare("INSERT INTO operations (laptop_id, user_id, repair_result, remaining_problems_count, details, image_path) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$laptop_id, $user_id, $repair_result, $remaining_problems_count, $details, $image_path]);

    header("Location: operations.php?laptop_id=$laptop_id&msg=added");
    exit;
}

// جلب بيانات الجهاز
$stmt = $pdo->prepare("SELECT * FROM broken_laptops WHERE laptop_id=?");
$stmt->execute([$laptop_id]);
$laptop = $stmt->fetch();

// جلب العمليات السابقة
$stmt = $pdo->prepare("SELECT o.*, u.username FROM operations o LEFT JOIN users u ON o.user_id=u.user_id WHERE o.laptop_id=? ORDER BY o.operation_date DESC");
$stmt->execute([$laptop_id]);
$operations = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>إدارة العمليات - <?= htmlspecialchars($laptop['serial_number']) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-4">
<div class="max-w-3xl mx-auto">

    <h1 class="text-xl font-bold mb-4">إدارة العمليات للجهاز: <?= htmlspecialchars($laptop['serial_number']) ?></h1>

    <?php if(isset($_GET['msg']) && $_GET['msg']=='added'): ?>
        <div class="bg-green-100 text-green-800 p-2 rounded mb-3">تم إضافة العملية بنجاح!</div>
    <?php endif; ?>

    <!-- نموذج إضافة عملية -->
    <div class="bg-white p-4 rounded shadow mb-6">
        <h2 class="font-bold mb-2">إضافة عملية إصلاح جديدة</h2>
        <form method="POST" enctype="multipart/form-data">
            <label>نتيجة الإصلاح</label>
            <input type="text" name="repair_result" class="w-full p-2 border rounded mb-3" required>

            <label>عدد المشاكل المتبقية</label>
            <input type="number" name="remaining_problems_count" class="w-full p-2 border rounded mb-3" value="0" required>

            <label>تفاصيل العملية</label>
            <textarea name="details" class="w-full p-2 border rounded mb-3" required></textarea>

            <label>رفع صورة (اختياري)</label>
            <input type="file" name="image" class="w-full mb-3">

            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">تسجيل العملية</button>
        </form>
    </div>

    <!-- العمليات السابقة -->
    <div class="bg-white p-4 rounded shadow">
        <h2 class="font-bold mb-2">العمليات السابقة</h2>
        <?php if(count($operations) === 0): ?>
            <p>لا توجد عمليات بعد.</p>
        <?php else: ?>
            <?php foreach($operations as $op): ?>
                <div class="border-b py-2 mb-2">
                    <p><strong><?= htmlspecialchars($op['username']) ?></strong> - <?= $op['operation_date'] ?></p>
                    <p><strong>نتيجة الإصلاح:</strong> <?= htmlspecialchars($op['repair_result']) ?></p>
                    <p><strong>المتبقي من المشاكل:</strong> <?= $op['remaining_problems_count'] ?></p>
                    <p><strong>تفاصيل:</strong> <?= nl2br(htmlspecialchars($op['details'])) ?></p>
                    <?php if($op['image_path']): ?>
                        <img src="<?= htmlspecialchars($op['image_path']) ?>" class="w-full max-w-xs mt-2 rounded">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
