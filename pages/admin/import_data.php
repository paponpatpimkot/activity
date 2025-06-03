<?php
// ========================================================================
// ไฟล์: import_data.php (เนื้อหาสำหรับ include)
// หน้าที่: ฟอร์มสำหรับอัปโหลด CSV และประมวลผลการ Import (แก้ไข EmployeeIDNumber Staff)
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php ($mysqli), และ Authorization check ---
// require 'db_connect.php';
// if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) { exit('Unauthorized'); }

$page_title = "Import ข้อมูลจาก CSV";
$message = '';
$error_details = [];

// --- ดึงค่า Enum ที่เป็นไปได้สำหรับ 'type' ของ activity_units ---
$possible_unit_types = [];
$sql_enum_unit = "SHOW COLUMNS FROM activity_units LIKE 'type'";
$result_enum_unit = $mysqli->query($sql_enum_unit);
if ($result_enum_unit && $row_enum_unit = $result_enum_unit->fetch_assoc()) {
    preg_match("/^enum\(\'(.*)\'\)$/", $row_enum_unit['Type'], $matches_unit);
    if (isset($matches_unit[1])) { $possible_unit_types = explode("','", $matches_unit[1]); }
}
if ($result_enum_unit) { $result_enum_unit->free(); }

// --- ดึง Role ID สำหรับ 'staff' ---
$staff_role_id = null;
$sql_staff_role_query = "SELECT id FROM roles WHERE name = 'staff' LIMIT 1";
$result_staff_role_query = $mysqli->query($sql_staff_role_query);
if ($result_staff_role_query && $row_staff_role = $result_staff_role_query->fetch_assoc()) {
    $staff_role_id = $row_staff_role['id'];
} else {
    error_log("Critical: Role 'staff' not found in roles table. MySQL Error: " . $mysqli->error);
}
if ($result_staff_role_query) { $result_staff_role_query->free(); }


// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file']) && isset($_POST['import_type'])) {
    $import_type = $_POST['import_type'];
    $file = $_FILES['csv_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) { $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการอัปโหลดไฟล์: Error Code ' . $file['error'] . '</p>'; }
    elseif ($file['size'] == 0) { $message = '<p class="alert alert-danger text-white">ไฟล์ที่อัปโหลดว่างเปล่า</p>'; }
    elseif (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') { $message = '<p class="alert alert-danger text-white">กรุณาอัปโหลดไฟล์นามสกุล .csv เท่านั้น</p>'; }
    else {
        $temp_file_path = $file['tmp_name'];
        $success_count = 0; $error_count = 0; $row_number = 1;
        $handle = fopen($temp_file_path, "r");

        if ($handle === FALSE) { $message = '<p class="alert alert-danger text-white">ไม่สามารถเปิดไฟล์ CSV ที่อัปโหลดได้</p>'; }
        else {
            $mysqli->begin_transaction();
            try {
                $header_row = fgetcsv($handle, 0, ","); $row_number++;
                if ($header_row === FALSE) throw new Exception("ไม่สามารถอ่าน Header Row จากไฟล์ CSV ได้");
                $normalized_headers = array_map('strtolower', array_map('trim', $header_row));

                // --- Majors Import ---
                if ($import_type === 'majors') { /* ... (โค้ดเดิม) ... */ }
                // --- Student Groups Import ---
                elseif ($import_type === 'student_groups') { /* ... (โค้ดเดิม) ... */ }
                // --- Users Import ---
                elseif ($import_type === 'users') { /* ... (โค้ดเดิม) ... */ }
                // --- Students Import ---
                elseif ($import_type === 'students') { /* ... (โค้ดเดิม) ... */ }
                // --- Activity Units Import ---
                elseif ($import_type === 'activity_units') { /* ... (โค้ดเดิม) ... */ }

                // --- *** แก้ไข Logic สำหรับ Staff *** ---
                elseif ($import_type === 'staff') {
                    if (is_null($staff_role_id)) {
                        throw new Exception("ไม่สามารถดำเนินการได้: ไม่พบ Role 'staff' ในระบบ");
                    }
                    $expected_headers = ['username', 'password', 'firstname', 'lastname', 'email', 'employeeidnumber'];
                    $min_columns = 6; // Username, Password, FirstName, LastName, Email, EmployeeIDNumber
                    if (count($normalized_headers) < $min_columns || array_values(array_slice($normalized_headers,0,$min_columns)) !== $expected_headers) {
                         throw new Exception("Header ในไฟล์ CSV Staff ไม่ตรง (ควรเป็น Username,Password,FirstName,LastName,Email,EmployeeIDNumber)");
                    }

                    $sql_insert_user = "INSERT INTO users (username, password, first_name, last_name, email, role_id) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt_insert_user = $mysqli->prepare($sql_insert_user); if (!$stmt_insert_user) throw new Exception("Prepare Insert User failed: " . $mysqli->error);
                    $sql_insert_staff_table = "INSERT INTO staff (user_id, employee_id_number) VALUES (?, ?)";
                    $stmt_insert_staff_table = $mysqli->prepare($sql_insert_staff_table); if (!$stmt_insert_staff_table) throw new Exception("Prepare Insert Staff failed: " . $mysqli->error);
                    $sql_check_user = "SELECT id FROM users WHERE username = ?";
                    $stmt_check_user = $mysqli->prepare($sql_check_user); if (!$stmt_check_user) throw new Exception("Prepare Check Username failed: " . $mysqli->error);
                    $sql_check_email = "SELECT id FROM users WHERE email = ? AND email IS NOT NULL AND email != ''";
                    $stmt_check_email = $mysqli->prepare($sql_check_email); if (!$stmt_check_email) throw new Exception("Prepare Check Email failed: " . $mysqli->error);
                    $sql_check_emp_id = "SELECT user_id FROM staff WHERE employee_id_number = ? AND employee_id_number IS NOT NULL AND employee_id_number != ''"; // เช็คเฉพาะที่ไม่ใช่ NULL/Empty
                    $stmt_check_emp_id = $mysqli->prepare($sql_check_emp_id); if (!$stmt_check_emp_id) throw new Exception("Prepare Check Employee ID failed: " . $mysqli->error);

                    while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
                        if (count($data) < $min_columns) { $error_count++; $error_details[] = "แถวที่ $row_number: ข้อมูล Staff ไม่ครบ $min_columns คอลัมน์"; $row_number++; continue; }

                        $username = trim($data[0]);
                        $password = trim($data[1]);
                        $first_name = trim($data[2]);
                        $last_name = trim($data[3]);
                        $email_input = trim($data[4]);
                        $employee_id_number_input = trim($data[5]); // รับค่า EmployeeIDNumber

                        $valid_row = true;
                        $email_to_save = null;
                        // *** EmployeeIDNumber สามารถเป็น NULL ได้ ***
                        $employee_id_number_to_save = !empty($employee_id_number_input) ? $employee_id_number_input : null;
                        $current_errors = [];

                        if (empty($username)) { $current_errors[] = "Username ว่างเปล่า"; $valid_row = false; }
                        if (empty($password)) { $current_errors[] = "Password ว่างเปล่า"; $valid_row = false; }
                        if (empty($first_name)) { $current_errors[] = "FirstName ว่างเปล่า"; $valid_row = false; }
                        if (empty($last_name)) { $current_errors[] = "LastName ว่างเปล่า"; $valid_row = false; }
                        // EmployeeIDNumber ไม่บังคับกรอกแล้ว
                        // if (empty($employee_id_number_to_save) && $employee_id_number_input !== '') { $current_errors[] = "EmployeeIDNumber ว่างเปล่า"; $valid_row = false; }


                        if (!empty($email_input)) {
                            if (filter_var($email_input, FILTER_VALIDATE_EMAIL)) { $email_to_save = $email_input; }
                            else { $current_errors[] = "รูปแบบ Email ไม่ถูกต้อง"; $valid_row = false; }
                        }

                        if (!$valid_row) { $error_count++; $error_details[] = "แถวที่ $row_number ($username): " . implode(', ', $current_errors); $row_number++; continue; }

                        $stmt_check_user->bind_param('s', $username); $stmt_check_user->execute();
                        if ($stmt_check_user->get_result()->num_rows > 0) { $error_count++; $error_details[] = "แถวที่ $row_number: Username '$username' ซ้ำ"; $row_number++; continue; }

                        if (!is_null($email_to_save)) {
                            $stmt_check_email->bind_param('s', $email_to_save); $stmt_check_email->execute();
                            if ($stmt_check_email->get_result()->num_rows > 0) { $error_count++; $error_details[] = "แถวที่ $row_number: Email '$email_to_save' ซ้ำ"; $row_number++; continue; }
                        }

                        // *** ตรวจสอบ Employee ID ซ้ำเฉพาะเมื่อมีการกรอกค่า และไม่เป็น NULL ***
                        if (!is_null($employee_id_number_to_save)) {
                            $stmt_check_emp_id->bind_param('s', $employee_id_number_to_save); $stmt_check_emp_id->execute();
                            if ($stmt_check_emp_id->get_result()->num_rows > 0) { $error_count++; $error_details[] = "แถวที่ $row_number: Employee ID '$employee_id_number_to_save' ซ้ำ"; $row_number++; continue; }
                        }

                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt_insert_user->bind_param('sssssi', $username, $hashed_password, $first_name, $last_name, $email_to_save, $staff_role_id);
                        if ($stmt_insert_user->execute()) {
                            $new_user_id = $mysqli->insert_id;
                            // Insert into staff table, employee_id_number can be NULL
                            $stmt_insert_staff_table->bind_param('is', $new_user_id, $employee_id_number_to_save); // ส่ง $employee_id_number_to_save ซึ่งอาจเป็น NULL
                            if ($stmt_insert_staff_table->execute()) {
                                $success_count++;
                            } else {
                                $error_count++; $error_details[] = "แถวที่ $row_number: ไม่สามารถเพิ่มข้อมูล Staff สำหรับ '$username' ได้ - " . $stmt_insert_staff_table->error;
                            }
                        } else { $error_count++; $error_details[] = "แถวที่ $row_number: ไม่สามารถเพิ่ม User '$username' ได้ - " . $stmt_insert_user->error; }
                        $row_number++;
                    }
                    if($stmt_insert_user) $stmt_insert_user->close(); if($stmt_insert_staff_table) $stmt_insert_staff_table->close();
                    if($stmt_check_user) $stmt_check_user->close(); if($stmt_check_email) $stmt_check_email->close(); if($stmt_check_emp_id) $stmt_check_emp_id->close();
                }
                // --- สิ้นสุด Logic Staff ---
                else {
                     throw new Exception("ประเภทข้อมูลที่เลือกยังไม่รองรับการ Import");
                }

                // --- Final Commit/Rollback ---
                if ($error_count > 0) {
                    $mysqli->rollback();
                    $message = '<p class="alert alert-warning text-white">Import (' . ucfirst(str_replace('_', ' ', $import_type)) . ') ล้มเหลว มีข้อผิดพลาด ' . $error_count . ' รายการ</p>';
                } else {
                    $mysqli->commit();
                    $message = '<p class="alert alert-success text-white">Import ข้อมูล ' . ucfirst(str_replace('_', ' ', $import_type)) . ' สำเร็จ ' . $success_count . ' รายการ</p>';
                }

            } catch (Exception $e) {
                $mysqli->rollback();
                $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดร้ายแรงระหว่าง Import (' . ucfirst(str_replace('_', ' ', $import_type)) . '): ' . $e->getMessage() . '</p>';
                // Close any potentially open statements
                 if (isset($stmt_insert) && $stmt_insert) $stmt_insert->close();
                 if (isset($stmt_check) && $stmt_check) $stmt_check->close();
                 if (isset($stmt_insert_group) && $stmt_insert_group) $stmt_insert_group->close();
                 if (isset($stmt_insert_advisor) && $stmt_insert_advisor) $stmt_insert_advisor->close();
                 // if (isset($stmt_delete_advisor) && $stmt_delete_advisor) $stmt_delete_advisor->close(); // Not used in this block's try
                 if (isset($stmt_check_code) && $stmt_check_code) $stmt_check_code->close();
                 if (isset($stmt_get_level) && $stmt_get_level) $stmt_get_level->close();
                 if (isset($stmt_get_major) && $stmt_get_major) $stmt_get_major->close();
                 if (isset($stmt_get_advisor) && $stmt_get_advisor) $stmt_get_advisor->close();
                 if (isset($stmt_insert_user) && $stmt_insert_user) $stmt_insert_user->close();
                 if (isset($stmt_insert_staff_table) && $stmt_insert_staff_table) $stmt_insert_staff_table->close();
                 if (isset($stmt_check_user) && $stmt_check_user) $stmt_check_user->close();
                 if (isset($stmt_check_email) && $stmt_check_email) $stmt_check_email->close();
                 if (isset($stmt_check_emp_id) && $stmt_check_emp_id) $stmt_check_emp_id->close();
                 if (isset($stmt_get_role) && $stmt_get_role) $stmt_get_role->close();
                 if (isset($stmt_get_user) && $stmt_get_user) $stmt_get_user->close();
                 if (isset($stmt_get_group) && $stmt_get_group) $stmt_get_group->close();
                 if (isset($stmt_check_std_id) && $stmt_check_std_id) $stmt_check_std_id->close();
                 if (isset($stmt_check_user_id) && $stmt_check_user_id) $stmt_check_user_id->close();

            } finally {
                 if (isset($handle) && is_resource($handle)) { fclose($handle); }
            }
        } // end file handle check

    } // end basic file checks
} // end if POST

// --- จัดการ Message จาก Session (ถ้ามี) ---
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
                        <div class="alert alert-dismissible text-white fade show <?php echo (strpos($message, 'success') !== false || strpos($message, 'สำเร็จ') !== false) ? 'alert-success bg-gradient-success' : ((strpos($message, 'warning') !== false || strpos($message, 'เตือน') !== false) ? 'alert-warning bg-gradient-warning' : 'alert-danger bg-gradient-danger'); ?>" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close p-3" data-bs-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                     <?php if (!empty($error_details)): ?>
                        <div class="alert alert-light text-dark border-danger border-start border-4" role="alert">
                            <strong class="text-danger">รายละเอียดข้อผิดพลาด:</strong>
                            <ul class="mb-0 ps-4" style="max-height: 200px; overflow-y: auto;"> <?php foreach($error_details as $detail): ?>
                                    <li><?php echo htmlspecialchars($detail); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                     <?php endif; ?>


                    <form role="form" class="text-start" action="index.php?page=import_data" method="post" enctype="multipart/form-data">

                        <div class="input-group input-group-static mb-4">
                             <label for="import_type" class="ms-0">เลือกประเภทข้อมูลที่ต้องการ Import</label>
                             <select class="form-control" id="import_type" name="import_type" required>
                                <option value="">-- เลือกประเภท --</option>
                                <option value="majors">สาขาวิชา (Majors)</option>
                                <option value="student_groups">กลุ่มเรียน (Student Groups)</option>
                                <option value="users">ผู้ใช้งาน (Users)</option>
                                <option value="students">ข้อมูลนักศึกษา (Students)</option>
                                <option value="activity_units">หน่วยกิจกรรม (Activity Units)</option>
                                <option value="staff">ข้อมูลเจ้าหน้าที่ (Staff)</option>
                                <option value="advisors" disabled>ข้อมูล Advisor - เร็วๆ นี้</option>
                                </select>
                        </div>

                        <div class="input-group input-group-outline my-3">
                             <label class="form-label visually-hidden">เลือกไฟล์ CSV</label> <input type="file" id="csv_file" name="csv_file" class="form-control" accept=".csv" required>
                         </div>
                         <small class="text-muted d-block mb-1">รองรับเฉพาะไฟล์ .csv ที่เข้ารหัสแบบ UTF-8</small>

                         <div id="template-info-majors" class="template-info mt-2" style="display: none;"> <small class="text-muted d-block mb-2">รูปแบบไฟล์ Majors: Header 'MajorCode,MajorName'</small>
                            <a href="download_template.php?type=majors" target="_blank" class="btn btn-sm btn-outline-secondary mb-3"><i class="material-symbols-rounded me-1 s1">download</i>เทมเพลต Majors</a></div>
                         <div id="template-info-student_groups" class="template-info mt-2" style="display: none;"><small class="text-muted d-block mb-2">รูปแบบไฟล์ Groups: Header 'GroupCode,GroupName,LevelCode,MajorCode,AdvisorUsernames'</small>
                              <a href="download_template.php?type=student_groups" target="_blank" class="btn btn-sm btn-outline-secondary mb-3"><i class="material-symbols-rounded me-1 s1">download</i>เทมเพลต Groups</a></div>
                         <div id="template-info-users" class="template-info mt-2" style="display: none;"><small class="text-muted d-block mb-2">รูปแบบไฟล์ Users: Header 'Username,Password,FirstName,LastName,Email,RoleName'</small><strong class="text-danger d-block mb-2">คำเตือน: ควรใช้รหัสผ่านชั่วคราว!</strong>
                              <a href="download_template.php?type=users" target="_blank" class="btn btn-sm btn-outline-secondary mb-3"><i class="material-symbols-rounded me-1 s1">download</i>เทมเพลต Users</a></div>
                          <div id="template-info-students" class="template-info mt-2" style="display: none;"><small class="text-muted d-block mb-2">รูปแบบไฟล์ Students: Header 'Username,StudentIDNumber,GroupCode'</small>
                              <a href="download_template.php?type=students" target="_blank" class="btn btn-sm btn-outline-secondary mb-3"><i class="material-symbols-rounded me-1 s1">download</i>เทมเพลต Students</a></div>
                         <div id="template-info-activity_units" class="template-info mt-2" style="display: none;"><small class="text-muted d-block mb-2">รูปแบบไฟล์ Units: Header 'UnitName,UnitType'</small>
                              <a href="download_template.php?type=activity_units" target="_blank" class="btn btn-sm btn-outline-secondary mb-3"><i class="material-symbols-rounded me-1 s1">download</i>เทมเพลต Units</a></div>
                         <div id="template-info-staff" class="template-info mt-2" style="display: none;">
                              <small class="text-muted d-block mb-2">รูปแบบไฟล์ Staff: Header 'Username,Password,FirstName,LastName,Email,EmployeeIDNumber' (Email, EmployeeIDNumber สามารถเว้นว่างได้)</small>
                              <strong class="text-danger d-block mb-2">คำเตือน: ควรใช้รหัสผ่านชั่วคราว!</strong>
                              <a href="download_template.php?type=staff" target="_blank" class="btn btn-sm btn-outline-secondary mb-3">
                                <i class="material-symbols-rounded me-1" style="font-size: 1em; vertical-align: text-bottom;">download</i>
                                ดาวน์โหลดเทมเพลต Staff
                             </a>
                         </div>


                        <div class="text-center">
                             <button type="submit" class="btn bg-gradient-primary w-100 my-4 mb-2">
                                <i class="material-symbols-rounded me-1">upload_file</i>
                                เริ่ม Import ข้อมูล
                             </button>
                        </div>
                    </form>
                </div> </div> </div> </div> </div>

<script>
    document.getElementById('import_type').addEventListener('change', function() {
        document.querySelectorAll('.template-info').forEach(function(el) {
            el.style.display = 'none';
        });
        const selectedType = this.value;
        const templateInfoDiv = document.getElementById('template-info-' + selectedType);
        if (templateInfoDiv) {
            templateInfoDiv.style.display = 'block';
        }
    });
    document.getElementById('import_type').dispatchEvent(new Event('change'));
</script>
