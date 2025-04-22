<?php
// ========================================================================
// ไฟล์: major_form.php (เนื้อหาสำหรับ include)
// หน้าที่: ฟอร์มสำหรับเพิ่ม หรือ แก้ไข สาขาวิชา (เพิ่ม Major Code, ปรับ UI, แก้ไข Undefined variable, เปิดใช้งาน POST Handling)
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php ($mysqli), และ Authorization check ---
// --- ได้ทำไปแล้วในไฟล์ Controller หลัก ---
// --- Controller ควรจัดการ POST request และส่งตัวแปร $message, $major_code, $major_name, $is_edit_mode, $major_id (ถ้ามี) มาให้ ---
// --- แต่เนื่องจากใช้ ob_start() เราจะเก็บ POST handling ไว้ที่นี่ ---

// if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) { exit('Unauthorized'); }

// --- กำหนดค่าเริ่มต้น ---
$is_edit_mode = isset($_GET['id']) && is_numeric($_GET['id']);
$major_id = $is_edit_mode ? (int)$_GET['id'] : null;
$page_title = $is_edit_mode ? "แก้ไขสาขาวิชา" : "เพิ่มสาขาวิชาใหม่";
$form_action = "index.php?page=major_form" . ($is_edit_mode ? "&id=" . $major_id : "");

// กำหนดค่าเริ่มต้นสำหรับแสดงผลในฟอร์ม
$major_code = '';
$major_name = '';
$message = ''; // Message สำหรับแสดงผลในหน้านี้เท่านั้น (ถ้าเกิด Error ตอน POST)

// --- ดึงข้อมูลเดิมถ้าเป็นการแก้ไข (ทำเฉพาะตอนโหลดหน้า ไม่ใช่ตอน POST กลับมา) ---
if ($is_edit_mode && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $sql_edit = "SELECT major_code, name FROM majors WHERE id = ?";
    $stmt_edit = $mysqli->prepare($sql_edit);
    if ($stmt_edit) {
        $stmt_edit->bind_param('i', $major_id);
        $stmt_edit->execute();
        $result_edit = $stmt_edit->get_result();
        if ($result_edit->num_rows === 1) {
            $major_data = $result_edit->fetch_assoc();
            $major_code = $major_data['major_code'];
            $major_name = $major_data['name'];
        } else {
            $_SESSION['form_message'] = '<p class="alert alert-danger text-white">ไม่พบข้อมูลสาขาวิชาที่ต้องการแก้ไข</p>';
            header('Location: index.php?page=majors_list');
            exit;
        }
        $stmt_edit->close();
    } else {
        $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงข้อมูลเดิม</p>';
    }
}

// --- Handle Form Submission (Add or Edit) ---
// *** เปิดใช้งานส่วนนี้อีกครั้ง ***
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $major_code = trim($_POST['major_code']);
    $major_name = trim($_POST['major_name']);
    // ถ้าเป็นโหมดแก้ไข ให้ดึง major_id จาก hidden input หรือ session/state อื่นๆ
    // ในตัวอย่างนี้ จะดึงจาก $major_id ที่ตั้งค่าไว้ตอน GET ถ้าเป็น edit mode
    $current_major_id = $is_edit_mode ? $major_id : 0;


    // --- Validate Input ---
    $errors = [];
    if (empty($major_code)) {
        $errors[] = "กรุณากรอกรหัสสาขาวิชา";
    }
    if (empty($major_name)) {
        $errors[] = "กรุณากรอกชื่อสาขาวิชา";
    }

    if (!empty($errors)) {
        $message = '<p class="alert alert-danger text-white">' . implode('<br>', $errors) . '</p>';
    } else {
        // --- Check for duplicate major_code (สำคัญ: major_code ต้องไม่ซ้ำ) ---
        $duplicate_check_sql = "SELECT id FROM majors WHERE major_code = ? AND id != ?";
        $stmt_check = $mysqli->prepare($duplicate_check_sql);
        $stmt_check->bind_param('si', $major_code, $current_major_id); // ใช้ $current_major_id
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $is_duplicate = $result_check->num_rows > 0;
        $stmt_check->close();

        if ($is_duplicate) {
            // ใช้ htmlspecialchars เพื่อความปลอดภัย
            $message = '<p class="alert alert-danger text-white">รหัสสาขาวิชา \'' . htmlspecialchars($major_code) . '\' นี้มีอยู่แล้ว</p>';
        } else {
            // --- Process Add or Edit ---
            if ($is_edit_mode && $major_id !== null) {
                // --- Update ---
                $sql = "UPDATE majors SET major_code = ?, name = ? WHERE id = ?"; // เพิ่ม major_code
                $stmt = $mysqli->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param('ssi', $major_code, $major_name, $major_id); // เปลี่ยน type เป็น ssi
                    if ($stmt->execute()) {
                        $_SESSION['form_message'] = '<p class="alert alert-success text-white">แก้ไขข้อมูลสาขาวิชาสำเร็จแล้ว</p>';
                        header('Location: index.php?page=majors_list'); // Redirect หลังสำเร็จ
                        exit;
                    } else {
                        $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการแก้ไขข้อมูล: ' . htmlspecialchars($stmt->error) . '</p>';
                    }
                    $stmt->close();
                } else {
                    $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการเตรียมคำสั่งแก้ไข: ' . htmlspecialchars($mysqli->error) . '</p>';
                }
            } else {
                // --- Insert ---
                $sql = "INSERT INTO majors (major_code, name) VALUES (?, ?)"; // เพิ่ม major_code
                $stmt = $mysqli->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param('ss', $major_code, $major_name); // เปลี่ยน type เป็น ss
                    if ($stmt->execute()) {
                        $_SESSION['form_message'] = '<p class="alert alert-success text-white">เพิ่มสาขาวิชาใหม่สำเร็จแล้ว</p>';
                        header('Location: index.php?page=majors_list'); // Redirect หลังสำเร็จ
                        exit;
                    } else {
                        $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการเพิ่มข้อมูล: ' . htmlspecialchars($stmt->error) . '</p>';
                    }
                    $stmt->close();
                } else {
                    $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการเตรียมคำสั่งเพิ่ม: ' . htmlspecialchars($mysqli->error) . '</p>';
                }
            }
        }
    }
    // ถ้าเกิด Error ให้คงค่าที่กรอกไว้ในฟอร์ม (ค่า $major_code, $major_name จะเป็นค่าจาก $_POST อยู่แล้ว)
}


// --- จัดการ Message จาก Session (ควรทำใน Controller หลัก) ---
// if (isset($_SESSION['form_message'])) {
//     $message = $_SESSION['form_message'];
//     unset($_SESSION['form_message']);
// }

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
                        <div class="input-group input-group-outline my-3 <?php echo !empty($major_code) ? 'is-filled' : ''; ?>">
                            <label class="form-label">รหัสสาขาวิชา</label>
                            <input type="text" id="major_code" name="major_code" class="form-control" value="<?php echo htmlspecialchars($major_code); ?>" required maxlength="10">
                        </div>
                        <small class="d-block text-muted mb-2">รหัสตามที่วิทยาลัยกำหนด และต้องไม่ซ้ำกัน</small>

                        <div class="input-group input-group-outline my-3 <?php echo !empty($major_name) ? 'is-filled' : ''; ?>">
                            <label class="form-label">ชื่อสาขาวิชา</label>
                            <input type="text" id="major_name" name="major_name" class="form-control" value="<?php echo htmlspecialchars($major_name); ?>" required>
                        </div>
                        <small class="d-block text-muted mb-4">ชื่อสาขาสามารถซ้ำกันได้ระหว่างระดับชั้น</small>


                        <div class="text-center">
                            <button type="submit" class="btn bg-gradient-primary w-100 my-4 mb-2"><?php echo $is_edit_mode ? 'บันทึกการแก้ไข' : 'เพิ่มสาขาวิชา'; ?></button>
                            <a href="index.php?page=majors_list" class="btn btn-outline-secondary w-100 mb-0">ยกเลิก</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>