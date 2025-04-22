<?php
// ========================================================================
// ไฟล์: unit_form.php (เนื้อหาสำหรับ include)
// หน้าที่: ฟอร์มสำหรับเพิ่ม หรือ แก้ไข หน่วยงานกิจกรรม (ปรับ UI)
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php ($mysqli), และ Authorization check ---
// --- ได้ทำไปแล้วในไฟล์ Controller หลัก ---
// if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) { exit('Unauthorized'); }

// --- กำหนดค่าเริ่มต้น ---
$is_edit_mode = isset($_GET['id']) && is_numeric($_GET['id']);
$unit_id = $is_edit_mode ? (int)$_GET['id'] : null;
$page_title = $is_edit_mode ? "แก้ไขหน่วยงานกิจกรรม" : "เพิ่มหน่วยงานกิจกรรมใหม่";
$form_action = "index.php?page=unit_form" . ($is_edit_mode ? "&id=" . $unit_id : "");

// ค่าเริ่มต้นสำหรับฟอร์ม
$unit_name = '';
$unit_type = '';
$message = $message ?? ''; // รับ $message จาก Controller ถ้ามี Error ตอน POST

// ดึงค่า Enum ที่เป็นไปได้สำหรับ 'type' จากฐานข้อมูล
$possible_types = [];
$sql_enum = "SHOW COLUMNS FROM activity_units LIKE 'type'";
$result_enum = $mysqli->query($sql_enum);
if ($result_enum && $row_enum = $result_enum->fetch_assoc()) {
    preg_match("/^enum\(\'(.*)\'\)$/", $row_enum['Type'], $matches);
    if (isset($matches[1])) {
        $possible_types = explode("','", $matches[1]);
    }
}
$result_enum->free();


// --- ดึงข้อมูลเดิมถ้าเป็นการแก้ไข (ทำเฉพาะตอนโหลดหน้า ไม่ใช่ตอน POST กลับมาพร้อม Error) ---
if ($is_edit_mode && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $sql_edit = "SELECT name, type FROM activity_units WHERE id = ?";
    $stmt_edit = $mysqli->prepare($sql_edit);
    if ($stmt_edit) {
        $stmt_edit->bind_param('i', $unit_id);
        $stmt_edit->execute();
        $result_edit = $stmt_edit->get_result();
        if ($result_edit->num_rows === 1) {
            $unit_data = $result_edit->fetch_assoc();
            $unit_name = $unit_data['name'];
            $unit_type = $unit_data['type'];
        } else {
            $_SESSION['form_message'] = '<p class="alert alert-danger text-white">ไม่พบข้อมูลหน่วยงานที่ต้องการแก้ไข</p>';
            header('Location: index.php?page=units_list');
            exit;
        }
        $stmt_edit->close();
    } else {
        $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงข้อมูลเดิม</p>';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ถ้า POST กลับมาพร้อม Error ให้ใช้ค่าจาก POST
    $unit_name = $_POST['unit_name'] ?? $unit_name;
    $unit_type = $_POST['unit_type'] ?? $unit_type;
}

// --- Handle Form Submission (Add or Edit) ---
// (ใช้ ob_start() ใน Controller หลัก)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $unit_name = trim($_POST['unit_name']);
    $unit_type = trim($_POST['unit_type']);
    $current_unit_id = $is_edit_mode ? $unit_id : 0;

    // --- Validate Input ---
    $errors = [];
    if (empty($unit_name)) $errors[] = "กรุณากรอกชื่อหน่วยงาน";
    if (empty($unit_type) || !in_array($unit_type, $possible_types)) $errors[] = "กรุณาเลือกประเภทหน่วยงานที่ถูกต้อง";

    // --- Check for duplicate name (optional) ---
    if (empty($errors)) {
        $duplicate_check_sql = "SELECT id FROM activity_units WHERE name = ? AND id != ?";
        $stmt_check = $mysqli->prepare($duplicate_check_sql);
        $stmt_check->bind_param('si', $unit_name, $current_unit_id);
        $stmt_check->execute();
        $is_duplicate = $stmt_check->get_result()->num_rows > 0;
        $stmt_check->close();
        if ($is_duplicate) {
            $errors[] = "ชื่อหน่วยงานนี้มีอยู่แล้ว";
        }
    }

    if (!empty($errors)) {
        $message = '<p class="alert alert-danger text-white">' . implode('<br>', $errors) . '</p>';
    } else {
        // --- Process Add or Edit ---
        if ($is_edit_mode && $unit_id !== null) {
            // --- Update ---
            $sql = "UPDATE activity_units SET name = ?, type = ? WHERE id = ?";
            $stmt = $mysqli->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('ssi', $unit_name, $unit_type, $unit_id);
                if ($stmt->execute()) {
                    $_SESSION['form_message'] = '<p class="alert alert-success text-white">แก้ไขข้อมูลหน่วยงานสำเร็จแล้ว</p>';
                    header('Location: index.php?page=units_list'); // Redirect
                    exit;
                } else {
                    $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการแก้ไขข้อมูล: ' . htmlspecialchars($stmt->error) . '</p>';
                }
                $stmt->close();
            } else {
                $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการเตรียมคำสั่งแก้ไข</p>';
            }
        } else {
            // --- Insert ---
            $sql = "INSERT INTO activity_units (name, type) VALUES (?, ?)";
            $stmt = $mysqli->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('ss', $unit_name, $unit_type);
                if ($stmt->execute()) {
                    $_SESSION['form_message'] = '<p class="alert alert-success text-white">เพิ่มหน่วยงานใหม่สำเร็จแล้ว</p>';
                    header('Location: index.php?page=units_list'); // Redirect
                    exit;
                } else {
                    $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการเพิ่มข้อมูล: ' . htmlspecialchars($stmt->error) . '</p>';
                }
                $stmt->close();
            } else {
                $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการเตรียมคำสั่งเพิ่ม</p>';
            }
        }
    }
    // ถ้า Error ให้แสดงค่าที่กรอกค้างไว้
    // $unit_name, $unit_type ถูกตั้งค่าจาก POST แล้ว
}

?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-lg-8 col-md-10 mx-auto">
            <div class="card my-4">
                <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div class="bg-gradient-primary shadow-primary border-radius-lg pt-4 pb-3">
                        <h6 class="text-white text-capitalize ps-3"><?php echo $page_title; ?></h6>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($message)) : ?>
                        <div class="alert alert-dismissible text-white fade show <?php echo (strpos($message, 'success') !== false || strpos($message, 'สำเร็จ') !== false) ? 'alert-success bg-gradient-success' : 'alert-danger bg-gradient-danger'; ?>" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close p-3" data-bs-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <form role="form" class="text-start" action="<?php echo $form_action; ?>" method="post">
                        <div class="input-group input-group-outline my-3 <?php echo !empty($unit_name) ? 'is-filled' : ''; ?>">
                            <label class="form-label">ชื่อหน่วยงาน</label>
                            <input type="text" id="unit_name" name="unit_name" class="form-control" value="<?php echo htmlspecialchars($unit_name); ?>" required>
                        </div>

                        <div class="input-group input-group-static mb-4">
                            <label for="unit_type" class="ms-0">ประเภท</label>
                            <select class="form-control" id="unit_type" name="unit_type" required>
                                <option value="">-- เลือกประเภท --</option>
                                <?php foreach ($possible_types as $type) : ?>
                                    <option value="<?php echo htmlspecialchars($type); ?>" <?php echo ($unit_type === $type) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type); ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php if (empty($possible_types)): ?>
                                    <option value="" disabled>ไม่สามารถโหลดประเภทได้</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="text-center">
                            <button type="submit" class="btn bg-gradient-primary w-100 my-4 mb-2"><?php echo $is_edit_mode ? 'บันทึกการแก้ไข' : 'เพิ่มหน่วยงาน'; ?></button>
                            <a href="index.php?page=units_list" class="btn btn-outline-secondary w-100 mb-0">ยกเลิก</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>