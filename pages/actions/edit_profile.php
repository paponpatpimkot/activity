<?php
// ========================================================================
// ไฟล์: edit_profile.php (เนื้อหาสำหรับ include)
// หน้าที่: ฟอร์มให้ผู้ใช้แก้ไขข้อมูลส่วนตัวและเปลี่ยนรหัสผ่าน (จำกัดสิทธิ์แก้ไข Username)
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php ($mysqli) ---
// --- ได้ทำไปแล้วในไฟล์ Controller หลัก และมีการกำหนด $_SESSION['user_id'], $_SESSION['role_id'] ---

// if (!isset($_SESSION['user_id'])) { exit('Unauthorized'); }

$user_id = $_SESSION['user_id'];
$user_role_id = $_SESSION['role_id']; // ดึง Role ID มาใช้
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
        $message = '<p class="alert alert-danger text-white">ไม่พบข้อมูลผู้ใช้งานปัจจุบัน</p>';
    }
    $stmt_fetch->close();
} else {
     $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงข้อมูลผู้ใช้</p>';
}


// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $current_user_data) {
    // รับค่าจากฟอร์ม
    // *** รับ Username ใหม่ เฉพาะถ้าเป็น Advisor ***
    $new_username = ($user_role_id == 2) ? trim($_POST['username']) : $username; // ถ้าไม่ใช่ Advisor ใช้ค่าเดิม
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
    $username_changed = false;

    // --- Validate Basic Info ---
    if (empty($new_username)) $errors[] = "กรุณากรอก Username"; // ยังคงต้อง Validate เผื่อกรณี Advisor กรอกว่าง
    if (empty($new_first_name)) $errors[] = "กรุณากรอกชื่อจริง";
    if (empty($new_last_name)) $errors[] = "กรุณากรอกนามสกุล";

    // Validate Email
    $new_email_to_save = null;
    if (!empty($new_email_input)) {
        $filtered_email = filter_var($new_email_input, FILTER_VALIDATE_EMAIL);
        if ($filtered_email === false) {
            $errors[] = "รูปแบบ Email ไม่ถูกต้อง";
        } else {
            $new_email_to_save = $filtered_email;
            if ($new_email_to_save !== $email) {
                $sql_check_email = "SELECT id FROM users WHERE email = ? AND id != ?";
                $stmt_check_email = $mysqli->prepare($sql_check_email);
                if ($stmt_check_email) {
                    $stmt_check_email->bind_param('si', $new_email_to_save, $user_id);
                    $stmt_check_email->execute();
                    if ($stmt_check_email->get_result()->num_rows > 0) { $errors[] = "Email นี้มีผู้ใช้งานอื่นแล้ว"; }
                    $stmt_check_email->close();
                } else { $errors[] = "เกิดข้อผิดพลาดในการตรวจสอบ Email"; }
            }
        }
    } else { $new_email_to_save = null; }

    // --- Validate Username Change (เฉพาะ Advisor) ---
    if ($user_role_id == 2 && $new_username !== $username) { // เช็ค Role ก่อน
        $username_changed = true;
        $sql_check_user = "SELECT id FROM users WHERE username = ? AND id != ?";
        $stmt_check_user = $mysqli->prepare($sql_check_user);
        if ($stmt_check_user) {
            $stmt_check_user->bind_param('si', $new_username, $user_id);
            $stmt_check_user->execute();
            if ($stmt_check_user->get_result()->num_rows > 0) {
                $errors[] = "Username '" . htmlspecialchars($new_username) . "' นี้มีผู้ใช้งานแล้ว";
            }
            $stmt_check_user->close();
        } else { $errors[] = "เกิดข้อผิดพลาดในการตรวจสอบ Username"; }
    } elseif ($user_role_id != 2 && $new_username !== $username) {
        // ถ้าไม่ใช่ Advisor แต่พยายามส่ง Username ที่ต่างจากเดิมมา (อาจจะแก้ HTML) ให้ใช้ค่าเดิม
        $new_username = $username;
        $username_changed = false;
    }


    // --- Validate Password Change ---
    $update_password = false;
    if (!empty($new_password)) {
        if (empty($current_password)) { $errors[] = "กรุณากรอกรหัสผ่านปัจจุบันเพื่อยืนยัน"; }
        elseif (empty($confirm_new_password)) { $errors[] = "กรุณายืนยันรหัสผ่านใหม่"; }
        elseif ($new_password !== $confirm_new_password) { $errors[] = "รหัสผ่านใหม่และการยืนยันไม่ตรงกัน"; }
        else {
            $sql_check_pass = "SELECT password FROM users WHERE id = ?";
            $stmt_check_pass = $mysqli->prepare($sql_check_pass);
            if ($stmt_check_pass) {
                $stmt_check_pass->bind_param('i', $user_id); $stmt_check_pass->execute();
                $result_pass = $stmt_check_pass->get_result();
                if ($user_pass_data = $result_pass->fetch_assoc()) {
                    if (password_verify($current_password, $user_pass_data['password'])) {
                        $update_password = true;
                    } else { $errors[] = "รหัสผ่านปัจจุบันไม่ถูกต้อง"; }
                }
                $stmt_check_pass->close();
            } else { $errors[] = "เกิดข้อผิดพลาดในการตรวจสอบรหัสผ่านปัจจุบัน"; }
        }
    }


    if (!empty($errors)) {
        $message = '<p class="alert alert-danger text-white">' . implode('<br>', $errors) . '</p>';
        // แสดงค่าที่กรอกผิดพลาดกลับไปในฟอร์ม
        $username = $new_username; // ใช้ username ที่รับมา (แม้จะผิด)
        $first_name = $new_first_name;
        $last_name = $new_last_name;
        $email = $new_email_input;
    } else {
        // --- Prepare Update Statement ---
        // *** เช็ค Role ก่อนเพิ่ม Username เข้าไปใน Query ***
        if ($username_changed && $user_role_id == 2) { $update_fields[] = "username = ?"; $params[] = $new_username; $types .= "s"; }
        // ตรวจสอบว่ามีการเปลี่ยนแปลงค่าอื่นๆ หรือไม่ ก่อนเพิ่มเข้า Query
        if ($new_first_name !== $current_user_data['first_name']) { $update_fields[] = "first_name = ?"; $params[] = $new_first_name; $types .= "s"; }
        if ($new_last_name !== $current_user_data['last_name']) { $update_fields[] = "last_name = ?"; $params[] = $new_last_name; $types .= "s"; }
        if ($new_email_to_save !== $current_user_data['email']) { $update_fields[] = "email = ?"; $params[] = $new_email_to_save; $types .= "s"; }
        if ($update_password) {
            $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_fields[] = "password = ?"; $params[] = $hashed_new_password; $types .= "s";
        }

        if (!empty($update_fields)) {
            $sql_update = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE id = ?";
            $params[] = $user_id; $types .= "i";

            $stmt_update = $mysqli->prepare($sql_update);
            if ($stmt_update) {
                $stmt_update->bind_param($types, ...$params);
                if ($stmt_update->execute()) {
                    $success_message = "บันทึกข้อมูลส่วนตัวสำเร็จแล้ว";
                    // อัปเดต Session ถ้ามีการเปลี่ยนแปลง
                    if ($username_changed && $user_role_id == 2) { // Update session username เฉพาะถ้าเปลี่ยนจริงและเป็น Advisor
                        $_SESSION['username'] = $new_username;
                        $success_message .= " (Username สำหรับ Login ถูกเปลี่ยนเป็น " . htmlspecialchars($new_username) . ")";
                    }
                    if ($new_first_name !== $current_user_data['first_name']) { $_SESSION['first_name'] = $new_first_name; }
                    if ($new_last_name !== $current_user_data['last_name']) { $_SESSION['last_name'] = $new_last_name; }

                    $_SESSION['form_message'] = '<p class="alert alert-success text-white">' . $success_message . '</p>';
                    header('Location: index.php?page=edit_profile'); // Redirect to self
                    exit;
                } else { $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . htmlspecialchars($stmt_update->error) . '</p>'; }
                $stmt_update->close();
            } else { $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการเตรียมคำสั่งบันทึก</p>'; }
        } else { $message = '<p class="alert alert-info text-white">ไม่มีข้อมูลที่เปลี่ยนแปลง</p>'; }

        // ถ้าไม่มี Error แต่ไม่มีอะไรเปลี่ยน ก็ให้แสดงค่าปัจจุบัน
        if(empty($message)){
             $username = $new_username;
             $first_name = $new_first_name;
             $last_name = $new_last_name;
             $email = $new_email_input; // แสดงค่าที่กรอกล่าสุด
         } else {
             // ถ้ามี error ตอน save ให้แสดงค่าที่กรอกล่าสุด
             $username = $new_username;
             $first_name = $new_first_name;
             $last_name = $new_last_name;
             $email = $new_email_input;
         }
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
          <div class="bg-gradient-secondary shadow-secondary border-radius-lg pt-4 pb-3">
            <h6 class="text-white text-capitalize ps-3"><?php echo $page_title; ?></h6>
          </div>
        </div>
        <div class="card-body">
          <?php if (!empty($message)) : ?>
          <div
            class="alert alert-dismissible text-white fade show <?php echo (strpos($message, 'success') !== false || strpos($message, 'สำเร็จ') !== false) ? 'alert-success bg-gradient-success' : ((strpos($message, 'warning') !== false || strpos($message, 'เตือน') !== false) ? 'alert-warning bg-gradient-warning' : 'alert-danger bg-gradient-danger'); ?>"
            role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close p-3" data-bs-dismiss="alert" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <?php endif; ?>

          <form role="form" class="text-start" action="index.php?page=edit_profile" method="post">

            <h6 class="text-dark text-sm mt-4 mb-3">ข้อมูลบัญชี</h6>
            <div class="input-group input-group-outline my-3 <?php echo !empty($username) ? 'is-filled' : ''; ?>">
              <label class="form-label">Username (สำหรับ Login)</label>
              <input type="text" id="username" name="username" class="form-control"
                value="<?php echo htmlspecialchars($username); ?>" required
                <?php echo ($user_role_id != 2) ? 'readonly disabled' : ''; // ถ้าไม่ใช่ Advisor ให้ readonly ?>>
            </div>
            <?php if ($user_role_id == 2): // แสดงคำแนะนำเฉพาะ Advisor ?>
            <small class="d-block text-muted mb-2">หากแก้ไข Username คุณจะต้องใช้ Username ใหม่ในการ Login
              ครั้งต่อไป</small>
            <?php else: ?>
            <small class="d-block text-muted mb-2">Username ไม่สามารถแก้ไขได้</small>
            <?php endif; ?>


            <hr class="dark horizontal my-3">
            <h6 class="text-dark text-sm mt-4 mb-3">ข้อมูลส่วนตัว</h6>

            <div class="input-group input-group-outline mb-3 <?php echo !empty($first_name) ? 'is-filled' : ''; ?>">
              <label class="form-label">ชื่อจริง</label>
              <input type="text" id="first_name" name="first_name" class="form-control"
                value="<?php echo htmlspecialchars($first_name); ?>" required>
            </div>

            <div class="input-group input-group-outline mb-3 <?php echo !empty($last_name) ? 'is-filled' : ''; ?>">
              <label class="form-label">นามสกุล</label>
              <input type="text" id="last_name" name="last_name" class="form-control"
                value="<?php echo htmlspecialchars($last_name); ?>" required>
            </div>

            <div class="input-group input-group-outline mb-3 <?php echo !empty($email) ? 'is-filled' : ''; ?>">
              <label class="form-label">Email (ถ้ามี)</label>
              <input type="email" id="email" name="email" class="form-control"
                value="<?php echo htmlspecialchars($email); ?>">
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