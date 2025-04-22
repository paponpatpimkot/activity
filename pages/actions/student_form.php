<?php
// ========================================================================
// ไฟล์: student_form.php (เนื้อหาสำหรับ include)
// หน้าที่: ฟอร์มสำหรับเพิ่ม หรือ แก้ไข ข้อมูลนักศึกษา
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php, และ Authorization check ---
// --- ได้ทำไปแล้วในไฟล์ Controller หลัก ---
// require 'db_connect.php'; // $mysqli should be available
// if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) { exit('Unauthorized'); }

$page_title = "เพิ่มข้อมูลนักศึกษา";
$form_action = "admin.php?page=student_form"; // Action ชี้ไปที่ Controller หลัก
$student_user_id = null; // User ID ของนักศึกษาที่จะแก้ไข
$student_id_number = '';
$group_id = '';
$message = '';
$is_edit_mode = false;

// --- ข้อมูล User (สำหรับแสดงผลในโหมด Edit) ---
$user_info = null;

// --- ดึงข้อมูลสำหรับ Dropdowns ---
// Student Groups
$groups = [];
$sql_groups = "SELECT id, group_name FROM student_groups ORDER BY group_name ASC";
$result_groups = $mysqli->query($sql_groups);
if ($result_groups) {
    while ($row = $result_groups->fetch_assoc()) {
        $groups[] = $row;
    }
    $result_groups->free();
}

// Users with role 'student' (role_id = 3) who are NOT already in the students table
// (สำหรับ Dropdown ตอน Add เท่านั้น)
$available_users = [];
$sql_users = "SELECT u.id, u.username, u.first_name, u.last_name
              FROM users u
              LEFT JOIN students s ON u.id = s.user_id
              WHERE u.role_id = 3 AND s.user_id IS NULL
              ORDER BY u.first_name, u.last_name";
$result_users = $mysqli->query($sql_users);
if ($result_users) {
     while ($row = $result_users->fetch_assoc()) {
        $available_users[] = $row;
    }
    $result_users->free();
}


// --- Check if Editing ---
if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $is_edit_mode = true;
    $student_user_id = (int)$_GET['user_id'];
    $page_title = "แก้ไขข้อมูลนักศึกษา";
    $form_action = "admin.php?page=student_form&user_id=" . $student_user_id; // Action สำหรับฟอร์มแก้ไข

    // --- Fetch existing student data ---
    $sql_edit = "SELECT s.student_id_number, s.group_id, u.username, u.first_name, u.last_name, u.email
                 FROM students s
                 JOIN users u ON s.user_id = u.id
                 WHERE s.user_id = ?";
    $stmt_edit = $mysqli->prepare($sql_edit);
    if ($stmt_edit) {
        $stmt_edit->bind_param('i', $student_user_id);
        $stmt_edit->execute();
        $result_edit = $stmt_edit->get_result();
        if ($result_edit->num_rows === 1) {
            $student_data = $result_edit->fetch_assoc();
            $student_id_number = $student_data['student_id_number'];
            $group_id = $student_data['group_id'];
            $user_info = $student_data; // เก็บข้อมูล user ไว้แสดงผล
        } else {
            // ไม่พบข้อมูลนักศึกษาสำหรับ user_id นี้ (อาจจะยังไม่ได้ Add ในตาราง students)
             $_SESSION['form_message'] = '<p class="alert alert-danger text-white">ไม่พบข้อมูลนักศึกษาสำหรับ User ID นี้</p>';
             // Redirect กลับไปหน้า List หรือหน้าที่เหมาะสม
             header('Location: admin.php?page=students_list');
             exit;
        }
        $stmt_edit->close();
    } else {
         // จัดการ Error
         $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการเตรียมคำสั่งดึงข้อมูลนักศึกษา</p>';
         // อาจจะแสดงข้อความ หรือ Redirect
    }
}

// --- Handle Form Submission (Add or Edit) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // รับค่าจากฟอร์ม
    $posted_user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT); // สำหรับ Add mode
    $student_id_number = trim($_POST['student_id_number']);
    $group_id = filter_input(INPUT_POST, 'group_id', FILTER_VALIDATE_INT);

    // --- Validate Input ---
    $errors = [];
    if ($is_edit_mode) {
        $current_user_id = $student_user_id; // ใช้ user_id จาก GET parameter ตอน edit
    } else {
        $current_user_id = $posted_user_id; // ใช้ user_id จาก POST ตอน add
        if (empty($current_user_id)) $errors[] = "กรุณาเลือกบัญชีผู้ใช้";
    }

    if (empty($student_id_number)) $errors[] = "กรุณากรอกรหัสนักศึกษา";
    if (empty($group_id)) $errors[] = "กรุณาเลือกกลุ่มเรียน";

    // --- เพิ่มการตรวจสอบว่า student_id_number ซ้ำหรือไม่ ---
    if(empty($errors)){
        $sql_check_sid = "SELECT user_id FROM students WHERE student_id_number = ? AND user_id != ?";
        $stmt_check_sid = $mysqli->prepare($sql_check_sid);
        // ถ้าเป็น Edit mode ให้เช็ค ID อื่นๆ, ถ้าเป็น Add mode ให้เช็คทั้งหมด (user_id != 0)
        $check_user_id = $is_edit_mode ? $current_user_id : 0;
        $stmt_check_sid->bind_param('si', $student_id_number, $check_user_id);
        $stmt_check_sid->execute();
        $result_check_sid = $stmt_check_sid->get_result();
        if($result_check_sid->num_rows > 0){
            $errors[] = "รหัสนักศึกษานี้มีผู้ใช้งานอื่นแล้ว";
        }
        $stmt_check_sid->close();
    }


    if (!empty($errors)) {
        $message = '<p class="alert alert-danger text-white">' . implode('<br>', $errors) . '</p>';
        // ถ้าเป็น Edit mode ต้องดึงข้อมูล User มาแสดงผลอีกครั้ง
        if ($is_edit_mode && $student_user_id) {
             $sql_user = "SELECT username, first_name, last_name, email FROM users WHERE id = ?";
             $stmt_user = $mysqli->prepare($sql_user);
             $stmt_user->bind_param('i', $student_user_id);
             $stmt_user->execute();
             $result_user = $stmt_user->get_result();
             $user_info = $result_user->fetch_assoc();
             $stmt_user->close();
        }

    } else {
        // --- ดึงข้อมูล academic_level จาก group_id เพื่อกำหนด required_hours ---
        $required_hours = 0; // Default
        $sql_get_level = "SELECT academic_level FROM student_groups WHERE id = ?";
        $stmt_get_level = $mysqli->prepare($sql_get_level);
        $stmt_get_level->bind_param('i', $group_id);
        $stmt_get_level->execute();
        $result_level = $stmt_get_level->get_result();
        if ($row_level = $result_level->fetch_assoc()) {
            if ($row_level['academic_level'] === 'ปวช.') {
                $required_hours = 36;
            } elseif ($row_level['academic_level'] === 'ปวส.') {
                $required_hours = 30;
            }
        }
        $stmt_get_level->close();

        // --- Process Add or Edit ---
        if ($is_edit_mode && $student_user_id !== null) {
            // --- Update ---
            $sql = "UPDATE students SET student_id_number = ?, group_id = ?, required_hours = ? WHERE user_id = ?";
            $stmt = $mysqli->prepare($sql);
            if ($stmt) {
                // Types: s=string, i=integer, i=integer, i=integer
                $stmt->bind_param('siii', $student_id_number, $group_id, $required_hours, $student_user_id);
                if ($stmt->execute()) {
                    $_SESSION['form_message'] = '<p class="alert alert-success text-white">แก้ไขข้อมูลนักศึกษาสำเร็จแล้ว</p>';
                    header('Location: admin.php?page=students_list'); // Redirect
                    exit;
                } else {
                    $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการแก้ไขข้อมูล: ' . htmlspecialchars($stmt->error) . '</p>';
                }
                $stmt->close();
            } else {
                 $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการเตรียมคำสั่งแก้ไข</p>';
            }
             // ดึงข้อมูล User มาแสดงผลอีกครั้งหลังพยายาม Update
             $sql_user = "SELECT username, first_name, last_name, email FROM users WHERE id = ?";
             $stmt_user = $mysqli->prepare($sql_user);
             $stmt_user->bind_param('i', $student_user_id);
             $stmt_user->execute();
             $result_user = $stmt_user->get_result();
             $user_info = $result_user->fetch_assoc();
             $stmt_user->close();

        } else {
            // --- Insert ---
            // ตรวจสอบก่อนว่า user_id นี้ยังไม่มีใน students table (เผื่อกรณีเข้าหน้า Add โดยตรง)
             $sql_check_exist = "SELECT user_id FROM students WHERE user_id = ?";
             $stmt_check_exist = $mysqli->prepare($sql_check_exist);
             $stmt_check_exist->bind_param('i', $current_user_id);
             $stmt_check_exist->execute();
             $result_check_exist = $stmt_check_exist->get_result();
             if($result_check_exist->num_rows > 0){
                 $message = '<p class="alert alert-danger text-white">บัญชีผู้ใช้นี้ถูกกำหนดข้อมูลนักศึกษาแล้ว</p>';
             } else {
                 $sql = "INSERT INTO students (user_id, student_id_number, group_id, required_hours) VALUES (?, ?, ?, ?)";
                 $stmt = $mysqli->prepare($sql);
                 if ($stmt) {
                    // Types: i=integer, s=string, i=integer, i=integer
                    $stmt->bind_param('isii', $current_user_id, $student_id_number, $group_id, $required_hours);
                    if ($stmt->execute()) {
                         $_SESSION['form_message'] = '<p class="alert alert-success text-white">เพิ่มข้อมูลนักศึกษาใหม่สำเร็จแล้ว</p>';
                         header('Location: admin.php?page=students_list'); // Redirect
                         exit;
                    } else {
                        $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการเพิ่มข้อมูล: ' . htmlspecialchars($stmt->error) . '</p>';
                    }
                    $stmt->close();
                 } else {
                    $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการเตรียมคำสั่งเพิ่ม</p>';
                 }
             }
             $stmt_check_exist->close();
        }
    }
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
                        <div class="alert alert-dismissible text-white fade show <?php echo strpos($message, 'success') !== false ? 'alert-success bg-gradient-success' : 'alert-danger bg-gradient-danger'; ?>" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close p-3" data-bs-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <form role="form" action="<?php echo $form_action; ?>" method="post">
                        <?php if ($is_edit_mode && $user_info): ?>
                            <div class="input-group input-group-static mb-4">
                                <label>ชื่อผู้ใช้ (Username)</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_info['username']); ?>" disabled>
                            </div>
                             <div class="input-group input-group-static mb-4">
                                <label>ชื่อ-สกุล</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']); ?>" disabled>
                            </div>
                             <div class="input-group input-group-static mb-4">
                                <label>Email</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($user_info['email'] ?? ''); ?>" disabled>
                            </div>
                            <input type="hidden" name="user_id" value="<?php echo $student_user_id; ?>">
                        <?php else: // Add Mode ?>
                            <div class="input-group input-group-static mb-4">
                                 <label for="user_id" class="ms-0">เลือกบัญชีผู้ใช้ (นักศึกษา)</label>
                                 <select class="form-control" id="user_id" name="user_id" required>
                                    <option value="">-- เลือกผู้ใช้ --</option>
                                    <?php foreach ($available_users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>" <?php echo ($posted_user_id == $user['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['username'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php if(empty($available_users)): ?>
                                        <option value="" disabled>-- ไม่มีบัญชีนักศึกษาที่ยังไม่ได้กำหนดข้อมูล --</option>
                                    <?php endif; ?>
                                </select>
                                <small class="text-muted">เฉพาะผู้ใช้ที่มี Role เป็น Student และยังไม่มีข้อมูลในตาราง Students</small>
                                <small class="text-muted">หากไม่มี ให้ไปสร้าง User ใหม่ก่อน</small>
                             </div>
                        <?php endif; ?>

                        <div class="input-group input-group-outline my-3 <?php echo !empty($student_id_number) ? 'is-filled' : ''; ?>">
                            <label class="form-label">รหัสนักศึกษา</label>
                            <input type="text" id="student_id_number" name="student_id_number" class="form-control" value="<?php echo htmlspecialchars($student_id_number); ?>" required>
                        </div>

                        <div class="input-group input-group-static mb-4">
                             <label for="group_id" class="ms-0">กลุ่มเรียน</label>
                             <select class="form-control" id="group_id" name="group_id" required>
                                <option value="">-- เลือกกลุ่มเรียน --</option>
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?php echo $group['id']; ?>" <?php echo ($group_id == $group['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($group['group_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                             </select>
                        </div>

                         <div class="input-group input-group-static mb-4">
                            <label>ชั่วโมงที่ต้องสะสม (อัตโนมัติ)</label>
                            <input type="text" class="form-control" value="จะถูกกำหนดตามระดับชั้นของกลุ่ม" disabled>
                        </div>


                        <div class="text-center">
                             <button type="submit" class="btn bg-gradient-primary w-100 my-4 mb-2"><?php echo $is_edit_mode ? 'บันทึกการแก้ไข' : 'เพิ่มข้อมูลนักศึกษา'; ?></button>
                             <a href="admin.php?page=students_list" class="btn btn-outline-secondary w-100 mb-0">ยกเลิก</a>
                        </div>
                    </form>
                </div> </div> </div> </div> </div> 