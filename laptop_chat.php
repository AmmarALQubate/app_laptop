<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$laptop_id = (int)($_GET['laptop_id'] ?? 0);
if ($laptop_id <= 0) die("جهاز غير صالح");

// جلب بيانات الجهاز
$stmt = $pdo->prepare("SELECT * FROM broken_laptops b 
    LEFT JOIN categories c ON b.category_id=c.category_id
    WHERE b.laptop_id=?");
$stmt->execute([$laptop_id]);
$laptop = $stmt->fetch();
if (!$laptop) die("الجهاز غير موجود");

// إرسال رسالة جديدة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['message'])) {
    $stmt = $pdo->prepare("INSERT INTO laptop_discussions (laptop_id, user_id, message) VALUES (?, ?, ?)");
    $stmt->execute([$laptop_id, $_SESSION['user_id'], $_POST['message']]);
    header("Location: laptop_chat.php?id=$laptop_id");
    exit;
}

// جلب المحادثات
$stmt = $pdo->prepare("
    SELECT d.*, u.username 
    FROM laptop_discussions d 
    JOIN users u ON d.user_id = u.user_id
    WHERE d.laptop_id=?
    ORDER BY d.created_at ASC
");
$stmt->execute([$laptop_id]);
$messages = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>مناقشة الجهاز #<?= $laptop['laptop_id'] ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">
<div class="max-w-3xl mx-auto w-full flex-1 flex flex-col">

  <!-- بيانات الجهاز -->
  <div class="bg-white p-4 rounded shadow mt-4">
      <h1 class="text-lg font-bold">جهاز #<?= $laptop['laptop_id'] ?> - <?= htmlspecialchars($laptop['serial_number']) ?></h1>
      <p>المواصفات: <?= htmlspecialchars($laptop['specs']) ?></p>
      <p>نوع الجهاز: <?= htmlspecialchars($laptop['category_name'] ?? 'لابتوب') ?></p>
      <p>الحالة: <?= htmlspecialchars($laptop['status']) ?></p>
      <p>تفاصيل المشكلة: <?= nl2br(htmlspecialchars($laptop['problem_details'])) ?></p>
  </div>

  <!-- المحادثة -->
  <div class="flex-1 overflow-y-auto bg-gray-50 p-4 mt-4 rounded shadow flex flex-col gap-2 h-[60vh]">
      <?php foreach ($messages as $m): ?>
        <div class="flex <?= $m['user_id']==$_SESSION['user_id']?'justify-end':'justify-start' ?>">
            <div class="max-w-xs p-2 rounded-lg <?= $m['user_id']==$_SESSION['user_id']?'bg-blue-500 text-white':'bg-gray-200 text-gray-800' ?>">
                <p class="text-sm"><?= htmlspecialchars($m['message']) ?></p>
                <span class="text-xs block mt-1 text-gray-500"><?= htmlspecialchars($m['username']) ?> • <?= $m['created_at'] ?></span>
            </div>
        </div>
      <?php endforeach; ?>
  </div>

  <!-- إرسال رسالة -->
  <form method="POST" class="mt-2 flex gap-2">
      <input type="text" name="message" placeholder="اكتب رسالة..." class="flex-1 p-2 border rounded" required>
      <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">إرسال</button>
  </form>

  <div class="mt-4">
      <a href="broken_laptops.php" class="text-blue-600 underline text-sm">العودة لقائمة الأجهزة</a>
  </div>

</div>
</body>
</html>
