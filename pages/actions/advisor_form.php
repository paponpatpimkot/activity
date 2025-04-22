<?php
// ========================================================================
// ไฟล์: advisor_form.php (เนื้อหาสำหรับ include)
// หน้าที่: ฟอร์มสำหรับเพิ่ม/แก้ไข รหัสพนักงาน ของ Advisor
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php, และ Authorization check ---
// --- ได้ทำไปแล้วในไฟล์ Controller หลัก ---
// require 'db_connect.php'; // $mysqli should be available
// if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) { exit('Unauthorized'); }

$page_title = "แก้ไขรหัสพนักงาน Advisor";
$form_action = "index.php?page=advisor_form"; // Action ชี้ไปที่ Controller หลัก
$advisor_user_id = null;
$employee_id_number = '';
$message = '';
$user_info = null; // สำหรับแสดงข้อมูล Advisor

// --- Check if User ID is provided for Editing ---
if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $advisor_user_id = (int)$_GET['user_id'];
    $form_action = "index.php?page=advisor_form&user_id=" . $advisor_user_id;

    // --- Fetch user data (to display name/username) ---
    $sql_user = "SELECT username, first_name, last_name FROM users WHERE id = ? AND role_id = 2";
    $stmt_user = $mysqli->prepare($sql_user);
    if ($stmt_user) {
        $stmt_user->bind_param('i', $advisor_user_id);
        $stmt_user->execute();
        $result_user = $stmt_user->get_result();
        if ($result_user->num_rows === 1) {
            $user_info = $result_user->fetch_assoc();
        } else {
            $_SESSION['form_message'] = '<p class="alert alert-danger text-white">ไม่พบข้อมูลผู้ใช้ Advisor ที่ระบุ</p>';
            header('Location: index.php?page=advisors_list');
            exit;
        }
        $stmt_user->close();
    } else {
        $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงข้อมูลผู้ใช้</p>';
        // ไม่ควรให้ดำเนินการต่อถ้าดึงข้อมูล User ไม่ได้
        $advisor_user_id = null; // ป้องกันการทำงานต่อ
    }

    // --- Fetch existing advisor data (employee_id_number) ---
    if ($advisor_user_id) {
        $sql_advisor = "SELECT employee_id_number FROM advisors WHERE user_id = ?";
        $stmt_advisor = $mysqli->prepare($sql_advisor);
        if ($stmt_advisor) {
            $stmt_advisor->bind_param('i', $advisor_user_id);
            $stmt_advisor->execute();
            $result_advisor = $stmt_advisor->get_result();
            if ($advisor_data = $result_advisor->fetch_assoc()) {
                $employee_id_number = $advisor_data['employee_id_number'] ?? '';
            }
            // ไม่ต้องแจ้ง Error ถ้าไม่เจอ record ใน advisors เพราะเราจะใช้ INSERT ON DUPLICATE KEY UPDATE
            $stmt_advisor->close();
        } else {
            $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงข้อมูล Advisor</p>';
        }
    }
} else {
    // ถ้าไม่มี user_id ส่งมา อาจจะ redirect กลับ หรือแสดงข้อความว่าต้องเลือก Advisor ก่อน
    $_SESSION['form_message'] = '<p class="alert alert-warning text-white">กรุณาเลือก Advisor ที่ต้องการแก้ไขจากรายการ</p>';
    header('Location: index.php?page=advisors_list');
    exit;
}


// --- Handle Form Submission (Update Employee ID) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['advisor_user_id']) && is_numeric($_POST['advisor_user_id'])) {
    $posted_user_id = (int)$_POST['advisor_user_id'];
    // ตรวจสอบว่า user_id ที่ส่งมาตรงกับที่อยู่ใน URL (ป้องกันการปลอมค่า)
    if ($posted_user_id === $advisor_user_id) {
        $employee_id_number_input = trim($_POST['employee_id_number']) ?: null; // ถ้ากรอกว่าง ให้เป็น NULL

        // --- Check for duplicate employee_id_number (optional but recommended) ---
        $is_duplicate = false;
        if (!is_null($employee_id_number_input)) {
            $sql_check_eid = "SELECT user_id FROM advisors WHERE employee_id_number = ? AND user_id != ?";
            $stmt_check_eid = $mysqli->prepare($sql_check_eid);
            $stmt_check_eid->bind_param('si', $employee_id_number_input, $advisor_user_id);
            $stmt_check_eid->execute();
            if ($stmt_check_eid->get_result()->num_rows > 0) {
                $is_duplicate = true;
                $message = '<p class="alert alert-danger text-white">รหัสพนักงานนี้มีผู้ใช้งานอื่นแล้ว</p>';
            }
            $stmt_check_eid->close();
        }


        if (!$is_duplicate) {
            // --- ใช้ INSERT ... ON DUPLICATE KEY UPDATE ---
            // คำสั่งนี้จะ INSERT ถ้า user_id ยังไม่มีในตาราง advisors
            // หรือจะ UPDATE ถ้า user_id มีอยู่แล้ว (user_id เป็น PK)
            $sql_upsert = "INSERT INTO advisors (user_id, employee_id_number) VALUES (?, ?)
                           ON DUPLICATE KEY UPDATE employee_id_number = VALUES(employee_id_number)";
            $stmt_upsert = $mysqli->prepare($sql_upsert);

            if ($stmt_upsert) {
                $stmt_upsert->bind_param('is', $advisor_user_id, $employee_id_number_input);
                if ($stmt_upsert->execute()) {
                    $_SESSION['form_message'] = '<p class="alert alert-success text-white">บันทึกข้อมูลรหัสพนักงานสำเร็จแล้ว</p>';
                    header('Location: index.php?page=advisors_list'); // Redirect
                    exit;
                } else {
                    $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . htmlspecialchars($stmt_upsert->error) . '</p>';
                }
                $stmt_upsert->close();
            } else {
                $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการเตรียมคำสั่งบันทึกข้อมูล</p>';
            }
        }
        // กำหนดค่า employee_id_number กลับไปแสดงในฟอร์ม
        $employee_id_number = $employee_id_number_input ?? '';
    } else {
        $message = '<p class="alert alert-danger text-white">ข้อมูล User ID ไม่ตรงกัน</p>';
    }
}


// --- Display message from redirect (if any) ---
if (isset($_SESSION['form_message'])) {
    $message = $_SESSION['form_message'];
    unset($_SESSION['form_message']);
}

?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-lg-8 col-md-10 mx-auto">
            <div class="card my-4">
                <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div class="bg-gradient-info shadow-info border-radius-lg pt-4 pb-3">
                        <h6 class="text-white text-capitalize ps-3"><?php echo $page_title; ?></h6>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($message)) : ?>
                        <div class="alert alert-dismissible text-white fade show <?php echo (strpos($message, 'success') !== false || strpos($message, 'สำเร็จ') !== false) ? 'alert-success bg-gradient-success' : ((strpos($message, 'warning') !== false || strpos($message, 'เตือน') !== false) ? 'alert-warning bg-gradient-warning' : 'alert-danger bg-gradient-danger'); ?>" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close p-3" data-bs-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php if ($advisor_user_id && $user_info): // แสดงฟอร์มเมื่อมีข้อมูล User 
                    ?>
                        <form role="form" class="text-start" action="<?php echo $form_action; ?>" method="post">
                            <input type="hidden" name="advisor_user_id" value="<?php echo $advisor_user_id; ?>">

                            <div class="input-group input-group-static mb-4">
                                <label>Username</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_info['username']); ?>" disabled>
                            </div>
                            <div class="input-group input-group-static mb-4">
                                <label>ชื่อ-สกุล</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']); ?>" disabled>
                            </div>

                            <div class="input-group input-group-outline my-3 <?php echo !empty($employee_id_number) ? 'is-filled' : ''; ?>">
                                <label class="form-label">รหัสพนักงาน (ถ้ามี)</label>
                                <input type="text" id="employee_id_number" name="employee_id_number" class="form-control" value="<?php echo htmlspecialchars($employee_id_number); ?>">
                            </div>

                            <div class="text-center">
                                <button type="submit" class="btn bg-gradient-primary w-100 my-4 mb-2">บันทึกข้อมูล</button>
                                <a href="index.php?page=advisors_list" class="btn btn-outline-secondary w-100 mb-0">ยกเลิก</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <p class="text-center">ไม่สามารถโหลดข้อมูล Advisor ได้</p>
                        <div class="text-center">
                            <a href="index.php?page=advisors_list" class="btn btn-outline-secondary w-100 mb-0">กลับไปหน้าหลัก</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>