<?php
// add_broken_laptop.php (محدث لدعم default category "لابتوبات" + device_category_number)
session_start();
require 'db.php'; // تأكد أن هذا الملف يعرف $pdo

// صلاحية الوصول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
if (!in_array($_SESSION['permissions'], ['technician','admin','manager'])) {
    echo "ليس لديك صلاحية للوصول لهذه الصفحة.";
    exit;
}

$errors = [];
$success = false;

// -- تأكد من وجود صنف "لابتوبات" واحصل على id (إن لم يوجد أنشئه)
try {
    // استخدم معاملة بسيطة لضمان عدم تكرار الإنشاء في حالات التزامن
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT category_id FROM categories WHERE category_name = ? LIMIT 1");
    $stmt->execute(['لابتوبات']);
    $default_cat_id = $stmt->fetchColumn();

    if (!$default_cat_id) {
        $ins = $pdo->prepare("INSERT INTO categories (category_name) VALUES (?)");
        $ins->execute(['لابتوبات']);
        $default_cat_id = $pdo->lastInsertId();
    }
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    $errors[] = "خطأ أثناء التحقق من الصنف الافتراضي: " . $e->getMessage();
    $default_cat_id = null;
}

// جلب قائمة المستخدمين فقط (للتعيين إذا رغبت)
$users = $pdo->query("SELECT user_id, username, permissions FROM users ORDER BY username")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // استلام الحقول مع تنظيف أساسي
    // category_id نملأ بالقيمة الافتراضي دائماً (مخفية في الفورم)
    $category_id = intval($_POST['category_id'] ?? $default_cat_id);
    $device_category_number = trim($_POST['device_category_number'] ?? ''); // الحقل الجديد الذي طلبته
    $serial_number = trim($_POST['serial_number'] ?? '');
    $specs = trim($_POST['specs'] ?? '');
    $with_charger = isset($_POST['with_charger']) ? 1 : 0;
    $problems_count = max(0, intval($_POST['problems_count'] ?? 0));
    $branch_name = trim($_POST['branch_name'] ?? '');
    $problem_details = trim($_POST['problem_details'] ?? '');
    $entered_by = intval($_SESSION['user_id']);
    $assigned_user_id = ($_POST['assigned_user_id'] ?? '') !== '' ? intval($_POST['assigned_user_id']) : null;
    $problem_type = trim($_POST['problem_type'] ?? '');
    $transfer_ref = trim($_POST['transfer_ref'] ?? '');
    $status = trim($_POST['status'] ?? 'entered');
    $repeat_problem_count = max(0, intval($_POST['repeat_problem_count'] ?? 0));

    // تحقق من الحقول المطلوبة
    if ($serial_number === '') $errors[] = "الرجاء إدخال الرقم التسلسلي.";
    if ($problem_details === '') $errors[] = "الرجاء كتابة تفاصيل المشكلة.";
    if ($problems_count <= 0) $problems_count = 1; // افتراضي

    // تجهيز رفع صورة الشكوى (اختياري)
    $uploaded_image_path = null;
    if (!empty($_FILES['complaint_image']['name'])) {
        $file = $_FILES['complaint_image'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "حدث خطأ عند رفع الصورة.";
        } else {
            $allowed = ['image/jpeg','image/png','image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime, $allowed)) {
                $errors[] = "نوع الصورة غير مسموح. استخدم JPG أو PNG أو WEBP.";
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $errors[] = "حجم الصورة كبير جداً. الحد الأقصى 2 ميغابايت.";
            } else {
                $uploadDir = __DIR__ . '/uploads/complaints/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $newName = 'compl_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                $dest = $uploadDir . $newName;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $uploaded_image_path = 'uploads/complaints/' . $newName;
                } else {
                    $errors[] = "فشل حفظ الصورة على السيرفر.";
                }
            }
        }
    }

    // إذا لا أخطاء ننفذ الإدخال بترانزاكشن
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // تأكد أن عمود device_category_number موجود (تدرج أمان إضافي)
            // (تخطي تنفيذ ALTER هنا؛ من الأفضل تنفيذ ALTER عبر SQL منفصل قبل تشغيل هذا الملف)
            // تنفيذ الإدخال: نضيف device_category_number كحقل جديد
            $ins = $pdo->prepare("INSERT INTO broken_laptops
                (category_id, device_category_number, serial_number, specs, with_charger, problems_count, branch_name, problem_details, entered_by_user_id, assigned_user_id, problem_type, transfer_ref, status, repeat_problem_count)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([
                $category_id,
                $device_category_number !== '' ? $device_category_number : null,
                $serial_number,
                $specs,
                $with_charger,
                $problems_count,
                $branch_name,
                $problem_details,
                $entered_by,
                $assigned_user_id,
                $problem_type,
                $transfer_ref,
                $status,
                $repeat_problem_count
            ]);

            $laptop_id = $pdo->lastInsertId();

            // إذا رفعت صورة شكوى، أدخلها في جدول complaints مرتبط بالجهاز
            if ($uploaded_image_path !== null) {
                $cstm = $pdo->prepare("INSERT INTO complaints (laptop_id, problem_title, problem_details, image_path, user_id) VALUES (?, ?, ?, ?, ?)");
                $cstm->execute([$laptop_id, 'مشكلة مصورة عند الإدخال', $problem_details, $uploaded_image_path, $entered_by]);
            } else {
                $cstm = $pdo->prepare("INSERT INTO complaints (laptop_id, problem_title, problem_details, user_id) VALUES (?, ?, ?, ?)");
                $cstm->execute([$laptop_id, 'مشكلة أولية', $problem_details, $entered_by]);
            }

            // عملية تسجيل أولية في operations كسجل
            $log = $pdo->prepare("INSERT INTO operations (laptop_id, user_id, repair_result, remaining_problems_count, details) VALUES (?, ?, ?, ?, ?)");
            $log->execute([$laptop_id, $entered_by, 'تم إدخال الجهاز والمشكلة', $problems_count, 'تم إدخال الجهاز بواسطة المستخدم']);

            $pdo->commit();
            $success = true;
            header("Location: broken_laptops.php?msg=created");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "خطأ في قاعدة البيانات: " . $e->getMessage();
        }
    }
}

?>
<!doctype html>
<html lang="ar">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>إضافة لابتوب معطّل</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-b from-gray-100 to-gray-200 min-h-screen p-4">
  <div class="max-w-md mx-auto">
    <div class="bg-white p-5 rounded-2xl shadow">
      <h1 class="text-2xl font-bold mb-4 text-center">إضافة لابتوب معطّل</h1>

      <?php if (!empty($errors)): ?>
        <div class="bg-red-100 text-red-700 p-3 rounded mb-4">
          <ul class="list-disc list-inside">
            <?php foreach ($errors as $err): ?>
              <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data" class="space-y-3">
        <!-- Hidden category (افتراضي "لابتوبات") -->
        <input type="hidden" name="category_id" value="<?= (int)$default_cat_id ?>">

        <!-- الحقل الجديد: رقم صنف الجهاز في النظام -->
        <label class="text-sm font-medium">رقم صنف الجهاز في النظام (اختياري)</label>
        <input name="device_category_number" class="w-full p-2 border rounded" value="<?= htmlspecialchars($_POST['device_category_number'] ?? '') ?>" placeholder="مثال: CAT-2025-001">

        <label class="text-sm font-medium">الرقم التسلسلي *</label>
        <input name="serial_number" required class="w-full p-2 border rounded" value="<?= htmlspecialchars($_POST['serial_number'] ?? '') ?>">

        <label class="text-sm font-medium">المواصفات</label>
        <textarea name="specs" class="w-full p-2 border rounded" rows="3"><?= htmlspecialchars($_POST['specs'] ?? '') ?></textarea>

        <div class="flex items-center gap-3">
          <label class="flex items-center gap-2"><input type="checkbox" name="with_charger" <?= isset($_POST['with_charger']) ? 'checked' : '' ?>> مع الشاحن</label>
          <input name="branch_name" placeholder="اسم الفرع" class="flex-1 p-2 border rounded" value="<?= htmlspecialchars($_POST['branch_name'] ?? '') ?>">
        </div>

        <label class="text-sm font-medium">عدد المشاكل</label>
        <input name="problems_count" type="number" min="1" class="w-full p-2 border rounded" value="<?= htmlspecialchars($_POST['problems_count'] ?? 1) ?>">

        <label class="text-sm font-medium">تفاصيل المشكلة *</label>
        <textarea name="problem_details" required class="w-full p-2 border rounded" rows="4"><?= htmlspecialchars($_POST['problem_details'] ?? '') ?></textarea>

        <label class="text-sm font-medium">رفع صورة الشكوى (اختياري)</label>
        <input type="file" name="complaint_image" accept="image/*" class="w-full">

        <label class="text-sm font-medium">تعيين لمستخدم (اختياري)</label>
        <select name="assigned_user_id" class="w-full p-2 border rounded">
          <option value="">-- لا تعيين --</option>
          <?php foreach($users as $u): ?>
            <option value="<?= $u['user_id'] ?>" <?= (isset($_POST['assigned_user_id']) && $_POST['assigned_user_id']==$u['user_id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($u['username'] . ' (' . $u['permissions'] . ')') ?>
            </option>
          <?php endforeach; ?>
        </select>

        <label class="text-sm font-medium">نوع المشكلة (اختياري)</label>
        <input name="problem_type" class="w-full p-2 border rounded" value="<?= htmlspecialchars($_POST['problem_type'] ?? '') ?>">

        <label class="text-sm font-medium">مرجع التحويل (اختياري)</label>
        <input name="transfer_ref" class="w-full p-2 border rounded" value="<?= htmlspecialchars($_POST['transfer_ref'] ?? '') ?>">

        <label class="text-sm font-medium">الحالة</label>
        <select name="status" class="w-full p-2 border rounded">
          <?php
            $states = ['entered'=>'ادخالي (entered)','review_pending'=>'قيد المراجعة (review_pending)','assigned'=>'مكلف (assigned)','in_repair'=>'قيد الإصلاح (in_repair)','returned_for_review'=>'مرجع للمراجعة (returned_for_review)','locked'=>'مغلق (locked)'];
            foreach($states as $k=>$v):
          ?>
            <option value="<?= $k ?>" <?= (($_POST['status'] ?? 'entered') === $k) ? 'selected' : '' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>

        <label class="text-sm font-medium">عدد تكرار المشكلة (اختياري)</label>
        <input name="repeat_problem_count" type="number" min="0" class="w-full p-2 border rounded" value="<?= htmlspecialchars($_POST['repeat_problem_count'] ?? 0) ?>">

        <div class="pt-2">
          <button type="submit" class="w-full bg-blue-600 text-white p-3 rounded-lg">إضافة الجهاز</button>
        </div>
      </form>
    </div>

    <p class="text-center text-gray-500 text-sm mt-4">تمت مراعاة واجهة الجوال — حقول كبيرة وأزرار سهلة اللمس.</p>
  </div>
</body>
</html>
