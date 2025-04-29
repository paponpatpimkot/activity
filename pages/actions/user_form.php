<?php
// ========================================================================
// ไฟล์: user_form.php (เนื้อหาสำหรับ include)
// หน้าที่: ฟอร์มสำหรับเพิ่ม หรือ แก้ไข ข้อมูลผู้ใช้งาน
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php, และ Authorization check ---
// --- ได้ทำไปแล้วในไฟล์ Controller หลัก ---
// require 'db_connect.php'; // $mysqli should be available
// if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) { exit('Unauthorized'); }

$page_title = "เพิ่มผู้ใช้งานใหม่";
$form_action = "index.php?page=user_form"; // Action ชี้ไปที่ Controller หลัก
$user_id_edit = null; // User ID ที่จะแก้ไข
$username = '';
$first_name = '';
$last_name = '';
$email = ''; // เปลี่ยนค่าเริ่มต้นเป็น empty string แทน NULL
$role_id = '';
$message = '';
$is_edit_mode = false;

// --- ดึงข้อมูล Roles สำหรับ Dropdown ---
$roles = [];
$sql_roles = "SELECT id, name FROM roles ORDER BY name ASC";
$result_roles = $mysqli->query($sql_roles);
if ($result_roles) {
    while ($row = $result_roles->fetch_assoc()) {
        $roles[] = $row;
    }
    $result_roles->free();
}

// --- Check if Editing ---
if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $is_edit_mode = true;
    $user_id_edit = (int)$_GET['user_id'];
    $page_title = "แก้ไขข้อมูลผู้ใช้งาน";
    $form_action = "index.php?page=user_form&user_id=" . $user_id_edit;

    // --- Fetch existing user data ---
    $sql_edit = "SELECT username, first_name, last_name, email, role_id FROM users WHERE id = ?";
    $stmt_edit = $mysqli->prepare($sql_edit);
    if ($stmt_edit) {
        $stmt_edit->bind_param('i', $user_id_edit);
        $stmt_edit->execute();
        $result_edit = $stmt_edit->get_result();
        if ($result_edit->num_rows === 1) {
            $user_data = $result_edit->fetch_assoc();
            $username = $user_data['username'];
            $first_name = $user_data['first_name'];
            $last_name = $user_data['last_name'];
            $email = $user_data['email'] ?? ''; // ถ้า email เป็น NULL ใน DB ให้ใช้ empty string แทน
            $role_id = $user_data['role_id'];
        } else {
            $_SESSION['form_message'] = '<p class="alert alert-danger text-white">ไม่พบข้อมูลผู้ใช้งานที่ต้องการแก้ไข</p>';
            header('Location: index.php?page=users_list');
            exit;
        }
        $stmt_edit->close();
    } else {
        $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการเตรียมคำสั่งดึงข้อมูลผู้ใช้</p>';
    }
}

// --- Handle Form Submission (Add or Edit) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // รับค่าจากฟอร์ม
    $username = trim($_POST['username']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    // $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL); // ใช้ filter_var แทน
    $role_id = filter_input(INPUT_POST, 'role_id', FILTER_VALIDATE_INT);
    $password = $_POST['password'] ?? null;
    $confirm_password = $_POST['confirm_password'] ?? null;

    // --- จัดการ Email และ Validate ---
    $email_input = trim($_POST['email']);
    $email_to_save = null; // ค่าที่จะบันทึกลง DB (NULL ถ้าไม่กรอก หรือ รูปแบบผิด)
    $email_display = $email_input; // ค่าที่จะแสดงในฟอร์มถ้าเกิด Error
    if (!empty($email_input)) {
        $filtered_email = filter_var($email_input, FILTER_VALIDATE_EMAIL);
        if ($filtered_email === false) {
            $errors[] = "รูปแบบ Email ไม่ถูกต้อง";
        } else {
            $email_to_save = $filtered_email; // ใช้ค่าที่ผ่าน filter
        }
    } else {
        $email_to_save = null; // ถ้าไม่กรอก ให้เป็น NULL
    }


    // --- Validate Input ---
    $errors = []; // เริ่มต้น array error ใหม่
    if (empty($username)) $errors[] = "กรุณากรอก Username";
    if (empty($first_name)) $errors[] = "กรุณากรอกชื่อจริง";
    if (empty($last_name)) $errors[] = "กรุณากรอกนามสกุล";
    // เช็ค error จาก email validation ด้านบน
    if (!empty($email_input) && filter_var($email_input, FILTER_VALIDATE_EMAIL) === false) {
        // ไม่ต้อง add error ซ้ำ ถ้าจัดการไปแล้ว
    }
    if (empty($role_id)) $errors[] = "กรุณาเลือกบทบาท (Role)";

    // Validate Password (เฉพาะตอน Add หรือตอนต้องการเปลี่ยนใน Edit mode)
    if (!$is_edit_mode) { // Add mode requires password
        if (empty($password)) $errors[] = "กรุณากรอกรหัสผ่าน";
        if ($password !== $confirm_password) $errors[] = "รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน";
    } else { // Edit mode, password change is optional
        if (!empty($password) && $password !== $confirm_password) {
            $errors[] = "รหัสผ่านใหม่และการยืนยันรหัสผ่านไม่ตรงกัน";
        }
    }

    // --- Check for duplicate username/email ---
    if (empty($errors)) {
        // Check Username
        $sql_check_user = "SELECT id FROM users WHERE username = ? AND id != ?";
        $check_id_user = $is_edit_mode ? $user_id_edit : 0;
        $stmt_check_user = $mysqli->prepare($sql_check_user);
        $stmt_check_user->bind_param('si', $username, $check_id_user);
        $stmt_check_user->execute();
        if ($stmt_check_user->get_result()->num_rows > 0) {
            $errors[] = "Username นี้มีผู้ใช้งานแล้ว";
        }
        $stmt_check_user->close();

        // Check Email (only if $email_to_save is not null)
        if (!is_null($email_to_save)) {
            $sql_check_email = "SELECT id FROM users WHERE email = ? AND id != ?";
            $check_id_email = $is_edit_mode ? $user_id_edit : 0;
            $stmt_check_email = $mysqli->prepare($sql_check_email);
            // *** ใช้ $email_to_save ที่เป็นค่าที่ถูกต้องหรือ NULL แล้ว ***
            $stmt_check_email->bind_param('si', $email_to_save, $check_id_email);
            $stmt_check_email->execute();
            if ($stmt_check_email->get_result()->num_rows > 0) {
                $errors[] = "Email นี้มีผู้ใช้งานแล้ว";
            }
            $stmt_check_email->close();
        }
    }


    if (!empty($errors)) {
        $message = '<p class="alert alert-danger text-white">' . implode('<br>', $errors) . '</p>';
        // กำหนดค่า email ที่จะแสดงในฟอร์มกลับไปเป็นค่าที่ user กรอกเข้ามา
        $email = $email_display;
    } else {
        // --- Process Add or Edit ---
        if ($is_edit_mode && $user_id_edit !== null) {
            // --- Update ---
            $update_fields = [];
            $params = [];
            $types = "";

            $update_fields[] = "first_name = ?";
            $params[] = $first_name;
            $types .= "s";
            $update_fields[] = "last_name = ?";
            $params[] = $last_name;
            $types .= "s";
            $update_fields[] = "email = ?";
            $params[] = $email_to_save;
            $types .= "s"; // ใช้ค่าที่เตรียมไว้ (อาจเป็น NULL)
            $update_fields[] = "role_id = ?";
            $params[] = $role_id;
            $types .= "i";

            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $update_fields[] = "password = ?";
                $params[] = $hashed_password;
                $types .= "s";
            }

            $sql = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE id = ?";
            $params[] = $user_id_edit;
            $types .= "i";

            $stmt = $mysqli->prepare($sql);
            if ($stmt) {
                // *** ส่ง $params ที่เป็น array เข้า bind_param โดยตรง ***
                $stmt->bind_param($types, ...$params);
                if ($stmt->execute()) {
                    $_SESSION['form_message'] = '<p class="alert alert-success text-white">แก้ไขข้อมูลผู้ใช้งานสำเร็จแล้ว (หากมีการเปลี่ยน Role กรุณาตรวจสอบข้อมูล Advisor/Staff ที่เกี่ยวข้อง)</p>';
                    header('Location: index.php?page=users_list');
                    exit;
                } else {
                    $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการแก้ไขข้อมูล: ' . htmlspecialchars($stmt->error) . '</p>';
                }
                $stmt->close();
            } else {
                $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการเตรียมคำสั่งแก้ไข</p>';
            }
            // กำหนดค่า email ที่จะแสดงในฟอร์มกลับไปเป็นค่าที่ user กรอกเข้ามา
            $email = $email_display;
        } else {
            // --- Insert ---
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (username, password, first_name, last_name, email, role_id) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $mysqli->prepare($sql);
            if ($stmt) {
                // *** ใช้ $email_to_save ที่เป็นค่าที่เตรียมไว้ (อาจเป็น NULL) ***
                $stmt->bind_param('sssssi', $username, $hashed_password, $first_name, $last_name, $email_to_save, $role_id);
                if ($stmt->execute()) {
                    $new_user_id = $mysqli->insert_id;

                    $role_specific_message = '';
                    if ($role_id == 2) {
                        $role_specific_message = ' (กรุณาเพิ่มข้อมูล Advisor สำหรับผู้ใช้นี้ ถ้าจำเป็น)';
                    } elseif ($role_id == 4) {
                        $role_specific_message = ' (กรุณาเพิ่มข้อมูล Staff สำหรับผู้ใช้นี้ ถ้าจำเป็น)';
                    }

                    $_SESSION['form_message'] ='<p class="alert alert-success text-white">เพิ่มผู้ใช้งานใหม่สำเร็จแล้ว'.$role_specific_message .'</p>';
                    header('Location: index.php?page=users_list');
                    exit;
                } else {
                    $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการเพิ่มข้อมูล: ' . htmlspecialchars($stmt->error) . '</p>';
                }
                $stmt->close();
            } else {
                $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการเตรียมคำสั่งเพิ่ม</p>';
            }
            // กำหนดค่า email ที่จะแสดงในฟอร์มกลับไปเป็นค่าที่ user กรอกเข้ามา
            $email = $email_display;
        }
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
          <div class="bg-gradient-primary shadow-primary border-radius-lg pt-4 pb-3">
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


          <form role="form" class="text-start" action="<?php echo $form_action; ?>" method="post">
            <div class="input-group input-group-outline my-3 <?php echo !empty($username) ? 'is-filled' : ''; ?>">
              <label class="form-label">Username</label>
              <input type="text" id="username" name="username" class="form-control"
                value="<?php echo htmlspecialchars($username); ?>" required
                <?php echo $is_edit_mode ? 'readonly' : ''; ?>>
              <?php if ($is_edit_mode): ?>
              <small class="text-muted w-100">ไม่สามารถแก้ไข Username ได้</small>
              <?php endif; ?>
            </div>

            <div class="input-group input-group-outline mb-3">
              <label
                class="form-label"><?php echo $is_edit_mode ? 'รหัสผ่านใหม่ (หากต้องการเปลี่ยน)' : 'รหัสผ่าน'; ?></label>
              <input type="password" id="password" name="password" class="form-control"
                <?php echo !$is_edit_mode ? 'required' : ''; ?>>
            </div>
            <div class="input-group input-group-outline mb-3">
              <label class="form-label"><?php echo $is_edit_mode ? 'ยืนยันรหัสผ่านใหม่' : 'ยืนยันรหัสผ่าน'; ?></label>
              <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                <?php echo !$is_edit_mode ? 'required' : ''; ?>>
              <?php if ($is_edit_mode): ?>
              <small class="text-muted w-100">เว้นว่างไว้หากไม่ต้องการเปลี่ยนรหัสผ่าน</small>
              <?php endif; ?>
            </div>

            <hr class="dark horizontal my-3">

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

            <div class="input-group input-group-static mb-4">
              <label for="role_id" class="ms-0">บทบาท (Role)</label>
              <select class="form-control" id="role_id" name="role_id" required>
                <option value="">-- เลือกบทบาท --</option>
                <?php foreach ($roles as $role): ?>
                <option value="<?php echo $role['id']; ?>" <?php echo ($role_id == $role['id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars(ucfirst($role['name'])); // ทำให้ตัวแรกเป็นตัวใหญ่ 
                                        ?>
                </option>
                <?php endforeach; ?>
              </select>
              <small class="text-muted w-100">การเปลี่ยน Role อาจต้องมีการจัดการข้อมูล Advisor/Staff/Student
                เพิ่มเติม</small>
            </div>


            <div class="text-center">
              <button type="submit"
                class="btn bg-gradient-primary w-100 my-4 mb-2"><?php echo $is_edit_mode ? 'บันทึกการแก้ไข' : 'เพิ่มผู้ใช้งาน'; ?></button>
              <a href="index.php?page=users_list" class="btn btn-outline-secondary w-100 mb-0">ยกเลิก</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>