<?php
// ========================================================================
// ไฟล์: edit_profile.php (เนื้อหาสำหรับ include)
// หน้าที่: ฟอร์มให้ผู้ใช้แก้ไขข้อมูลส่วนตัวและเปลี่ยนรหัสผ่าน
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php ($mysqli) ---
// --- ได้ทำไปแล้วในไฟล์ Controller หลัก และมีการกำหนด $_SESSION['user_id'] ---
// --- หน้านี้ใช้ได้กับทุก Role ที่ Login อยู่ ---

// if (!isset($_SESSION['user_id'])) { exit('Unauthorized'); }

$user_id = $_SESSION['user_id'];
$page_title = "แก้ไขข้อมูลส่วนตัว";
$message = ''; // สำหรับแสดงข้อความแจ้งเตือน

// --- ดึงข้อมูล User ปัจจุบัน ---
$current_user_data = null;
$username = '';
$first_name = '';
$last_name = '';
$email = '';

$sql_fetch = "SELECT username, first_name, last_name, email FROM users WHERE id = ?";
$stmt_fetch = $mysqli->prepare($sql_fetch);
if ($stmt_fetch) {
    $stmt_fetch->bind_param('i', $user_id);
    $stmt_fetch->execute();
    $result_fetch = $stmt_fetch->get_result();
    if ($result_fetch->num_rows === 1) {
        $current_user_data = $result_fetch->fetch_assoc();
        $username = $current_user_data['username'];
        $first_name = $current_user_data['first_name'];
        $last_name = $current_user_data['last_name'];
        $email = $current_user_data['email'] ?? '';
    } else {
        // ไม่ควรเกิดขึ้นถ้า User Login อยู่ แต่ดักไว้ก่อน
        $message = '<p class="alert alert-danger text-white">ไม่พบข้อมูลผู้ใช้งานปัจจุบัน</p>';
        // อาจจะ redirect ไปหน้า logout
    }
    $stmt_fetch->close();
} else {
    $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงข้อมูลผู้ใช้</p>';
}


// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับค่าจากฟอร์ม
    $new_first_name = trim($_POST['first_name']);
    $new_last_name = trim($_POST['last_name']);
    $new_email_input = trim($_POST['email']);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_new_password = $_POST['confirm_new_password'] ?? '';

    $errors = [];
    $update_fields = [];
    $params = [];
    $types = "";

    // --- Validate Basic Info ---
    if (empty($new_first_name)) $errors[] = "กรุณากรอกชื่อจริง";
    if (empty($new_last_name)) $errors[] = "กรุณากรอกนามสกุล";

    // Validate Email และตรวจสอบซ้ำ (ถ้ามีการเปลี่ยนแปลง)
    $new_email_to_save = null;
    if (!empty($new_email_input)) {
        $filtered_email = filter_var($new_email_input, FILTER_VALIDATE_EMAIL);
        if ($filtered_email === false) {
            $errors[] = "รูปแบบ Email ไม่ถูกต้อง";
        } else {
            $new_email_to_save = $filtered_email;
            // Check if email changed and if the new one is duplicate
            if ($new_email_to_save !== $email) { // เช็คเฉพาะกรณีมีการเปลี่ยน Email
                $sql_check_email = "SELECT id FROM users WHERE email = ? AND id != ?";
                $stmt_check_email = $mysqli->prepare($sql_check_email);
                if ($stmt_check_email) {
                    $stmt_check_email->bind_param('si', $new_email_to_save, $user_id);
                    $stmt_check_email->execute();
                    if ($stmt_check_email->get_result()->num_rows > 0) {
                        $errors[] = "Email นี้มีผู้ใช้งานอื่นแล้ว";
                    }
                    $stmt_check_email->close();
                } else {
                    $errors[] = "เกิดข้อผิดพลาดในการตรวจสอบ Email";
                }
            }
        }
    } else {
        $new_email_to_save = null; // ถ้ากรอกว่างมา ให้เป็น NULL
    }

    // --- Validate Password Change (ถ้ามีการกรอกรหัสใหม่) ---
    $update_password = false;
    if (!empty($new_password)) {
        if (empty($current_password)) {
            $errors[] = "กรุณากรอกรหัสผ่านปัจจุบันเพื่อยืนยันการเปลี่ยนแปลง";
        } elseif (empty($confirm_new_password)) {
            $errors[] = "กรุณายืนยันรหัสผ่านใหม่";
        } elseif ($new_password !== $confirm_new_password) {
            $errors[] = "รหัสผ่านใหม่และการยืนยันไม่ตรงกัน";
        } else {
            // ตรวจสอบรหัสผ่านปัจจุบัน
            $sql_check_pass = "SELECT password FROM users WHERE id = ?";
            $stmt_check_pass = $mysqli->prepare($sql_check_pass);
            if ($stmt_check_pass) {
                $stmt_check_pass->bind_param('i', $user_id);
                $stmt_check_pass->execute();
                $result_pass = $stmt_check_pass->get_result();
                if ($user_pass_data = $result_pass->fetch_assoc()) {
                    if (password_verify($current_password, $user_pass_data['password'])) {
                        // รหัสผ่านปัจจุบันถูกต้อง, เตรียมอัปเดตรหัสใหม่
                        $update_password = true;
                    } else {
                        $errors[] = "รหัสผ่านปัจจุบันไม่ถูกต้อง";
                    }
                } // else ไม่ควรเกิดขึ้นถ้า user login อยู่
                $stmt_check_pass->close();
            } else {
                $errors[] = "เกิดข้อผิดพลาดในการตรวจสอบรหัสผ่านปัจจุบัน";
            }
        }
    }


    if (!empty($errors)) {
        $message = '<p class="alert alert-danger text-white">' . implode('<br>', $errors) . '</p>';
        // กำหนดค่ากลับไปที่ตัวแปร global เพื่อให้ฟอร์มแสดงค่าเดิมที่กรอกผิด
        $first_name = $new_first_name;
        $last_name = $new_last_name;
        $email = $new_email_input; // แสดงค่าที่ user กรอก แม้จะผิด format
    } else {
        // --- Prepare Update Statement ---
        $update_fields[] = "first_name = ?";
        $params[] = $new_first_name;
        $types .= "s";
        $update_fields[] = "last_name = ?";
        $params[] = $new_last_name;
        $types .= "s";
        $update_fields[] = "email = ?";
        $params[] = $new_email_to_save;
        $types .= "s";

        if ($update_password) {
            $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_fields[] = "password = ?";
            $params[] = $hashed_new_password;
            $types .= "s";
        }

        if (!empty($update_fields)) {
            $sql_update = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE id = ?";
            $params[] = $user_id;
            $types .= "i";

            $stmt_update = $mysqli->prepare($sql_update);
            if ($stmt_update) {
                $stmt_update->bind_param($types, ...$params);
                if ($stmt_update->execute()) {
                    $_SESSION['form_message'] = '<p class="alert alert-success text-white">บันทึกข้อมูลส่วนตัวสำเร็จแล้ว</p>';
                    // อัปเดตข้อมูลใน Session ด้วย (ถ้าจำเป็น)
                    $_SESSION['first_name'] = $new_first_name; // ตัวอย่าง
                    // Redirect กลับมาที่หน้าเดิม หรือหน้า Dashboard
                    header('Location: index.php?page=edit_profile'); // Redirect to self to show message
                    exit;
                } else {
                    $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . htmlspecialchars($stmt_update->error) . '</p>';
                }
                $stmt_update->close();
            } else {
                $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการเตรียมคำสั่งบันทึก</p>';
            }
        } else {
            // กรณีไม่มีอะไรเปลี่ยนแปลงเลย (อาจจะไม่ต้องแจ้งก็ได้)
            $message = '<p class="alert alert-info text-white">ไม่มีข้อมูลที่เปลี่ยนแปลง</p>';
        }
        // กำหนดค่า email ที่จะแสดงในฟอร์มกลับไปเป็นค่าที่ user กรอกเข้ามา
        $email = $new_email_input;
    }
}

// --- จัดการ Message จาก Session (ถ้ามีการ redirect มา) ---
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
                    <div class="bg-gradient-primary shadow-secondary border-radius-lg pt-4 pb-3">
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

                    <form role="form" class="text-start" action="index.php?page=edit_profile" method="post">

                        <h6 class="text-dark text-sm mt-4 mb-3">ข้อมูลบัญชี</h6>
                        <div class="input-group input-group-outline my-3 is-filled"> <label class="form-label">Username</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($username); ?>" readonly disabled>
                        </div>

                        <hr class="dark horizontal my-3">
                        <h6 class="text-dark text-sm mt-4 mb-3">ข้อมูลส่วนตัว</h6>

                        <div class="input-group input-group-outline mb-3 <?php echo !empty($first_name) ? 'is-filled' : ''; ?>">
                            <label class="form-label">ชื่อจริง</label>
                            <input type="text" id="first_name" name="first_name" class="form-control" value="<?php echo htmlspecialchars($first_name); ?>" required>
                        </div>

                        <div class="input-group input-group-outline mb-3 <?php echo !empty($last_name) ? 'is-filled' : ''; ?>">
                            <label class="form-label">นามสกุล</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" value="<?php echo htmlspecialchars($last_name); ?>" required>
                        </div>

                        <div class="input-group input-group-outline mb-3 <?php echo !empty($email) ? 'is-filled' : ''; ?>">
                            <label class="form-label">Email (ถ้ามี)</label>
                            <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>">
                        </div>

                        <hr class="dark horizontal my-3">
                        <h6 class="text-dark text-sm mt-4 mb-3">เปลี่ยนรหัสผ่าน (กรอกเฉพาะเมื่อต้องการเปลี่ยน)</h6>

                        <div class="input-group input-group-outline mb-3">
                            <label class="form-label">รหัสผ่านปัจจุบัน</label>
                            <input type="password" id="current_password" name="current_password" class="form-control">
                        </div>
                        <div class="input-group input-group-outline mb-3">
                            <label class="form-label">รหัสผ่านใหม่</label>
                            <input type="password" id="new_password" name="new_password" class="form-control">
                        </div>
                        <div class="input-group input-group-outline mb-3">
                            <label class="form-label">ยืนยันรหัสผ่านใหม่</label>
                            <input type="password" id="confirm_new_password" name="confirm_new_password" class="form-control">
                        </div>

                        <div class="text-center">
                            <button type="submit" class="btn bg-gradient-primary w-100 my-4 mb-2">บันทึกการเปลี่ยนแปลง</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>