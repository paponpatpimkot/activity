<?php
// ========================================================================
// ไฟล์: import_data.php (เนื้อหาสำหรับ include)
// หน้าที่: ฟอร์มสำหรับอัปโหลด CSV และประมวลผลการ Import (เพิ่ม Activity Units)
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php ($mysqli), และ Authorization check ---
// --- ได้ทำไปแล้วในไฟล์ Controller หลัก ---
// require 'db_connect.php'; // $mysqli should be available
// if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) { exit('Unauthorized'); }

$page_title = "Import ข้อมูลจาก CSV";
$message = ''; // สำหรับแสดงข้อความแจ้งเตือน (Success/Error)
$error_details = []; // สำหรับเก็บรายละเอียด Error รายแถว

// --- ดึงค่า Enum ที่เป็นไปได้สำหรับ 'type' ของ activity_units ---
$possible_unit_types = [];
$sql_enum = "SHOW COLUMNS FROM activity_units LIKE 'type'";
$result_enum = $mysqli->query($sql_enum);
if ($result_enum && $row_enum = $result_enum->fetch_assoc()) {
    // Extract ENUM values using regex
    preg_match("/^enum\(\'(.*)\'\)$/", $row_enum['Type'], $matches);
    if (isset($matches[1])) {
        $possible_unit_types = explode("','", $matches[1]);
    }
}
// Ensure freeing result even if preg_match fails
if ($result_enum) {
    $result_enum->free();
}


// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file']) && isset($_POST['import_type'])) {

    $import_type = $_POST['import_type'];
    $file = $_FILES['csv_file'];

    // --- Basic File Upload Checks ---
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการอัปโหลดไฟล์: Error Code ' . $file['error'] . '</p>';
    } elseif ($file['size'] == 0) {
        $message = '<p class="alert alert-danger text-white">ไฟล์ที่อัปโหลดว่างเปล่า</p>';
    } elseif (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
        $message = '<p class="alert alert-danger text-white">กรุณาอัปโหลดไฟล์นามสกุล .csv เท่านั้น</p>';
    } else {
        // --- Process CSV based on import type ---
        $temp_file_path = $file['tmp_name'];
        $success_count = 0;
        $error_count = 0;
        $row_number = 1; // Start counting from row 1 (for error reporting)

        // Set locale to handle UTF-8 correctly with fgetcsv, if needed
        // setlocale(LC_ALL, 'en_US.UTF-8'); // Or appropriate locale

        $handle = fopen($temp_file_path, "r");

        if ($handle === FALSE) {
             $message = '<p class="alert alert-danger text-white">ไม่สามารถเปิดไฟล์ CSV ที่อัปโหลดได้</p>';
        } else {
            // --- Process based on type ---
            $mysqli->begin_transaction(); // Start transaction for each import type
            try {
                // Read and validate header row first
                $header_row = fgetcsv($handle, 0, ","); // Use 0 for max length to read entire line
                $row_number++;
                if ($header_row === FALSE) {
                    throw new Exception("ไม่สามารถอ่าน Header Row จากไฟล์ CSV ได้");
                }
                 // Normalize headers (lowercase, trim)
                $normalized_headers = array_map('strtolower', array_map('trim', $header_row));

                // --- Majors Import ---
                if ($import_type === 'majors') {
                    $expected_headers = ['majorcode', 'majorname'];
                    if (count($normalized_headers) < 2 || $normalized_headers[0] !== $expected_headers[0] || $normalized_headers[1] !== $expected_headers[1]) {
                        throw new Exception("Header ในไฟล์ CSV Majors ไม่ตรง (ควรเป็น MajorCode, MajorName)");
                    }

                    $sql_insert = "INSERT INTO majors (major_code, name) VALUES (?, ?)";
                    $stmt_insert = $mysqli->prepare($sql_insert); if (!$stmt_insert) throw new Exception("Prepare Insert Majors failed: " . $mysqli->error);
                    $sql_check = "SELECT id FROM majors WHERE major_code = ?";
                    $stmt_check = $mysqli->prepare($sql_check); if (!$stmt_check) throw new Exception("Prepare Check Majors failed: " . $mysqli->error);

                    while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
                        if (count($data) < 2 || !isset($data[0]) || !isset($data[1])) { $error_count++; $error_details[] = "แถวที่ $row_number: ข้อมูล Majors ไม่ครบถ้วน"; $row_number++; continue; }
                        $major_code = trim($data[0]); $major_name = trim($data[1]);
                        if (empty($major_code)) { $error_count++; $error_details[] = "แถวที่ $row_number: รหัสสาขาว่างเปล่า"; $row_number++; continue; }
                        if (empty($major_name)) { $error_count++; $error_details[] = "แถวที่ $row_number: ชื่อสาขาว่างเปล่า (รหัส $major_code)"; $row_number++; continue; }
                        $stmt_check->bind_param('s', $major_code); $stmt_check->execute();
                        if ($stmt_check->get_result()->num_rows > 0) { $error_count++; $error_details[] = "แถวที่ $row_number: รหัสสาขา '$major_code' มีอยู่แล้ว"; $row_number++; continue; }
                        $stmt_insert->bind_param('ss', $major_code, $major_name);
                        if ($stmt_insert->execute()) { $success_count++; } else { $error_count++; $error_details[] = "แถวที่ $row_number: ไม่สามารถเพิ่ม '$major_name' (รหัส $major_code) - " . $stmt_insert->error; }
                        $row_number++;
                    }
                    $stmt_insert->close(); $stmt_check->close();
                }
                // --- Student Groups Import ---
                elseif ($import_type === 'student_groups') {
                    $expected_headers = ['groupcode', 'groupname', 'levelcode', 'majorcode', 'advisorusernames'];
                    if (count($normalized_headers) < 5 || array_values(array_slice($normalized_headers,0,5)) !== $expected_headers) { // Check only expected headers
                        throw new Exception("Header ในไฟล์ CSV Groups ไม่ตรง (ควรเป็น GroupCode, GroupName, LevelCode, MajorCode, AdvisorUsernames)");
                    }

                    $sql_insert_group = "INSERT INTO student_groups (group_code, group_name, level_id, major_id) VALUES (?, ?, ?, ?)";
                    $stmt_insert_group = $mysqli->prepare($sql_insert_group); if (!$stmt_insert_group) throw new Exception("Prepare Insert Group failed: " . $mysqli->error);
                    $sql_insert_advisor = "INSERT INTO group_advisors (group_id, advisor_user_id) VALUES (?, ?)";
                    $stmt_insert_advisor = $mysqli->prepare($sql_insert_advisor); if (!$stmt_insert_advisor) throw new Exception("Prepare Insert Advisor failed: " . $mysqli->error);
                    // No need for delete advisor statement here, handled during update/sync logic if needed elsewhere
                    $sql_check_code = "SELECT id FROM student_groups WHERE group_code = ?";
                    $stmt_check_code = $mysqli->prepare($sql_check_code); if (!$stmt_check_code) throw new Exception("Prepare Check Group Code failed: " . $mysqli->error);
                    $sql_get_level = "SELECT id FROM levels WHERE level_code = ?";
                    $stmt_get_level = $mysqli->prepare($sql_get_level); if (!$stmt_get_level) throw new Exception("Prepare Get Level ID failed: " . $mysqli->error);
                    $sql_get_major = "SELECT id FROM majors WHERE major_code = ?";
                    $stmt_get_major = $mysqli->prepare($sql_get_major); if (!$stmt_get_major) throw new Exception("Prepare Get Major ID failed: " . $mysqli->error);
                    $sql_get_advisor = "SELECT id FROM users WHERE username = ? AND role_id = 2";
                    $stmt_get_advisor = $mysqli->prepare($sql_get_advisor); if (!$stmt_get_advisor) throw new Exception("Prepare Get Advisor ID failed: " . $mysqli->error);

                    while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
                        if (count($data) < 5) { $error_count++; $error_details[] = "แถวที่ $row_number: ข้อมูล Groups ไม่ครบ 5 คอลัมน์"; $row_number++; continue; }
                        $group_code = trim($data[0]); $group_name = trim($data[1]); $level_code = trim($data[2]); $major_code = trim($data[3]); $advisor_usernames_str = trim($data[4]);
                        $valid_row = true; $level_id = null; $major_id = null; $found_advisor_ids = []; $errors = [];

                        if (empty($group_code)) { $errors[] = "รหัสกลุ่มว่างเปล่า"; $valid_row = false; }
                        if (empty($group_name)) { $errors[] = "ชื่อกลุ่มว่างเปล่า"; $valid_row = false; }
                        if (empty($level_code)) { $errors[] = "รหัสระดับชั้นว่างเปล่า"; $valid_row = false; }
                        if (empty($major_code)) { $errors[] = "รหัสสาขาว่างเปล่า"; $valid_row = false; }
                        if (!$valid_row) { $error_count++; $error_details[] = "แถวที่ $row_number: ข้อมูล Groups จำเป็นไม่ครบถ้วน (" . implode(', ', $errors) . ")"; $row_number++; continue; }

                        $stmt_check_code->bind_param('s', $group_code); $stmt_check_code->execute();
                        if ($stmt_check_code->get_result()->num_rows > 0) { $error_count++; $error_details[] = "แถวที่ $row_number: รหัสกลุ่ม '$group_code' ซ้ำ"; $row_number++; continue; }

                        $stmt_get_level->bind_param('s', $level_code); $stmt_get_level->execute(); $result_level = $stmt_get_level->get_result();
                        if ($row_level = $result_level->fetch_assoc()) { $level_id = $row_level['id']; } else { $error_count++; $error_details[] = "แถวที่ $row_number: ไม่พบรหัสระดับชั้น '$level_code'"; $valid_row = false; }
                        $stmt_get_major->bind_param('s', $major_code); $stmt_get_major->execute(); $result_major = $stmt_get_major->get_result();
                        if ($row_major = $result_major->fetch_assoc()) { $major_id = $row_major['id']; } else { $error_count++; $error_details[] = "แถวที่ $row_number: ไม่พบรหัสสาขา '$major_code'"; $valid_row = false; }

                        if (!empty($advisor_usernames_str)) {
                            $advisor_usernames = array_map('trim', explode(',', $advisor_usernames_str));
                            foreach ($advisor_usernames as $adv_user) {
                                if (empty($adv_user)) continue;
                                $stmt_get_advisor->bind_param('s', $adv_user); $stmt_get_advisor->execute(); $result_advisor = $stmt_get_advisor->get_result();
                                if ($row_advisor = $result_advisor->fetch_assoc()) { $found_advisor_ids[] = $row_advisor['id']; }
                                else { $error_count++; $error_details[] = "แถวที่ $row_number: ไม่พบ Advisor Username '$adv_user'"; $valid_row = false; }
                            }
                            $found_advisor_ids = array_unique($found_advisor_ids);
                        }
                        if (!$valid_row) { $row_number++; continue; }

                        $stmt_insert_group->bind_param('ssii', $group_code, $group_name, $level_id, $major_id);
                        if ($stmt_insert_group->execute()) {
                            $new_group_id = $mysqli->insert_id;
                            $advisor_insert_error = false;
                            if (!empty($found_advisor_ids)) {
                                foreach ($found_advisor_ids as $advisor_id) {
                                    $stmt_insert_advisor->bind_param('ii', $new_group_id, $advisor_id);
                                    if (!$stmt_insert_advisor->execute()) { $error_count++; $error_details[] = "แถวที่ $row_number: ไม่สามารถเพิ่ม Advisor ID $advisor_id สำหรับกลุ่ม '$group_name' ได้ - " . $stmt_insert_advisor->error; $advisor_insert_error = true; }
                                }
                            }
                            if (!$advisor_insert_error) { $success_count++; } else { $error_count++; }
                        } else { $error_count++; $error_details[] = "แถวที่ $row_number: ไม่สามารถเพิ่มกลุ่ม '$group_name' (รหัส $group_code) ได้ - " . $stmt_insert_group->error; }
                        $row_number++;
                    }
                    $stmt_insert_group->close(); $stmt_insert_advisor->close(); $stmt_check_code->close(); $stmt_get_level->close(); $stmt_get_major->close(); $stmt_get_advisor->close();
                }
                // --- Users Import ---
                elseif ($import_type === 'users') {
                    $expected_headers = ['username', 'password', 'firstname', 'lastname', 'email', 'rolename'];
                    $min_columns = 6;
                    if (count($normalized_headers) < $min_columns || array_slice($normalized_headers, 0, $min_columns) !== $expected_headers) {
                         throw new Exception("Header ในไฟล์ CSV Users ไม่ตรง (ควรเป็น Username,Password,FirstName,LastName,Email,RoleName)");
                    }

                    $sql_insert = "INSERT INTO users (username, password, first_name, last_name, email, role_id) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt_insert = $mysqli->prepare($sql_insert); if (!$stmt_insert) throw new Exception("Prepare Insert User failed: " . $mysqli->error);
                    $sql_check_user = "SELECT id FROM users WHERE username = ?";
                    $stmt_check_user = $mysqli->prepare($sql_check_user); if (!$stmt_check_user) throw new Exception("Prepare Check Username failed: " . $mysqli->error);
                    $sql_check_email = "SELECT id FROM users WHERE email = ?";
                    $stmt_check_email = $mysqli->prepare($sql_check_email); if (!$stmt_check_email) throw new Exception("Prepare Check Email failed: " . $mysqli->error);
                    $sql_get_role = "SELECT id FROM roles WHERE name = ?";
                    $stmt_get_role = $mysqli->prepare($sql_get_role); if (!$stmt_get_role) throw new Exception("Prepare Get Role ID failed: " . $mysqli->error);

                    while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
                        if (count($data) < $min_columns) { $error_count++; $error_details[] = "แถวที่ $row_number: ข้อมูล Users ไม่ครบ $min_columns คอลัมน์"; $row_number++; continue; }
                        $username = trim($data[0]); $password = trim($data[1]); $first_name = trim($data[2]); $last_name = trim($data[3]); $email_input = trim($data[4]); $role_name = strtolower(trim($data[5]));
                        $valid_row = true; $role_id = null; $email_to_save = null; $errors = [];

                        if (empty($username)) { $errors[] = "Username ว่างเปล่า"; $valid_row = false; }
                        if (empty($password)) { $errors[] = "Password ว่างเปล่า"; $valid_row = false; }
                        if (empty($first_name)) { $errors[] = "FirstName ว่างเปล่า"; $valid_row = false; }
                        if (empty($last_name)) { $errors[] = "LastName ว่างเปล่า"; $valid_row = false; }
                        if (empty($role_name)) { $errors[] = "RoleName ว่างเปล่า"; $valid_row = false; }
                        if (!empty($email_input)) { if (filter_var($email_input, FILTER_VALIDATE_EMAIL)) { $email_to_save = $email_input; } else { $errors[] = "รูปแบบ Email ไม่ถูกต้อง"; $valid_row = false; } }
                        if (!$valid_row) { $error_count++; $error_details[] = "แถวที่ $row_number ($username): ข้อมูลจำเป็นไม่ครบถ้วน/ผิดรูปแบบ (" . implode(', ', $errors) . ")"; $row_number++; continue; }

                        $stmt_check_user->bind_param('s', $username); $stmt_check_user->execute();
                        if ($stmt_check_user->get_result()->num_rows > 0) { $error_count++; $error_details[] = "แถวที่ $row_number: Username '$username' ซ้ำ"; $row_number++; continue; }
                        if (!is_null($email_to_save)) { $stmt_check_email->bind_param('s', $email_to_save); $stmt_check_email->execute(); if ($stmt_check_email->get_result()->num_rows > 0) { $error_count++; $error_details[] = "แถวที่ $row_number: Email '$email_to_save' ซ้ำ"; $row_number++; continue; } }

                        $stmt_get_role->bind_param('s', $role_name); $stmt_get_role->execute(); $result_role = $stmt_get_role->get_result();
                        if ($row_role = $result_role->fetch_assoc()) { $role_id = $row_role['id']; } else { $error_count++; $error_details[] = "แถวที่ $row_number: ไม่พบ RoleName '$role_name'"; $row_number++; continue; }

                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt_insert->bind_param('sssssi', $username, $hashed_password, $first_name, $last_name, $email_to_save, $role_id);
                        if ($stmt_insert->execute()) { $success_count++; } else { $error_count++; $error_details[] = "แถวที่ $row_number: ไม่สามารถเพิ่ม User '$username' ได้ - " . $stmt_insert->error; }
                        $row_number++;
                    }
                    $stmt_insert->close(); $stmt_check_user->close(); $stmt_check_email->close(); $stmt_get_role->close();
                }
                 // --- Students Import ---
                elseif ($import_type === 'students') {
                    $expected_headers = ['username', 'studentidnumber', 'groupcode'];
                    $min_columns = 3;
                    if (count($normalized_headers) < $min_columns || array_slice($normalized_headers, 0, $min_columns) !== $expected_headers) {
                        throw new Exception("Header ในไฟล์ CSV Students ไม่ตรง (ควรเป็น Username,StudentIDNumber,GroupCode)");
                    }

                    $sql_insert = "INSERT INTO students (user_id, student_id_number, group_id) VALUES (?, ?, ?)";
                    $stmt_insert = $mysqli->prepare($sql_insert); if (!$stmt_insert) throw new Exception("Prepare Insert Student failed: " . $mysqli->error);
                    $sql_get_user = "SELECT id FROM users WHERE username = ?";
                    $stmt_get_user = $mysqli->prepare($sql_get_user); if (!$stmt_get_user) throw new Exception("Prepare Get User ID failed: " . $mysqli->error);
                    $sql_get_group = "SELECT id FROM student_groups WHERE group_code = ?";
                    $stmt_get_group = $mysqli->prepare($sql_get_group); if (!$stmt_get_group) throw new Exception("Prepare Get Group ID failed: " . $mysqli->error);
                    $sql_check_std_id = "SELECT user_id FROM students WHERE student_id_number = ?";
                    $stmt_check_std_id = $mysqli->prepare($sql_check_std_id); if (!$stmt_check_std_id) throw new Exception("Prepare Check Student ID failed: " . $mysqli->error);
                    $sql_check_user_id = "SELECT user_id FROM students WHERE user_id = ?";
                    $stmt_check_user_id = $mysqli->prepare($sql_check_user_id); if (!$stmt_check_user_id) throw new Exception("Prepare Check User ID failed: " . $mysqli->error);

                    while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
                        if (count($data) < $min_columns) { $error_count++; $error_details[] = "แถวที่ $row_number: ข้อมูล Students ไม่ครบ $min_columns คอลัมน์"; $row_number++; continue; }
                        $username = trim($data[0]); $student_id_number = trim($data[1]); $group_code = trim($data[2]);
                        $valid_row = true; $user_id = null; $group_id = null; $errors = [];

                        if (empty($username)) { $errors[] = "Username ว่างเปล่า"; $valid_row = false; }
                        if (empty($student_id_number)) { $errors[] = "StudentIDNumber ว่างเปล่า"; $valid_row = false; }
                        if (empty($group_code)) { $errors[] = "GroupCode ว่างเปล่า"; $valid_row = false; }
                        if (!$valid_row) { $error_count++; $error_details[] = "แถวที่ $row_number ($username): ข้อมูลจำเป็นไม่ครบถ้วน (" . implode(', ', $errors) . ")"; $row_number++; continue; }

                        $stmt_get_user->bind_param('s', $username); $stmt_get_user->execute(); $result_user = $stmt_get_user->get_result();
                        if ($row_user = $result_user->fetch_assoc()) { $user_id = $row_user['id']; } else { $error_count++; $error_details[] = "แถวที่ $row_number: ไม่พบ Username '$username' ในระบบ"; $valid_row = false; }
                        $stmt_get_group->bind_param('s', $group_code); $stmt_get_group->execute(); $result_group = $stmt_get_group->get_result();
                        if ($row_group = $result_group->fetch_assoc()) { $group_id = $row_group['id']; } else { $error_count++; $error_details[] = "แถวที่ $row_number: ไม่พบ GroupCode '$group_code' ในระบบ"; $valid_row = false; }
                        $stmt_check_std_id->bind_param('s', $student_id_number); $stmt_check_std_id->execute();
                        if ($stmt_check_std_id->get_result()->num_rows > 0) { $error_count++; $error_details[] = "แถวที่ $row_number: รหัสนักศึกษา '$student_id_number' ซ้ำซ้อน"; $valid_row = false; }
                        if ($user_id) { $stmt_check_user_id->bind_param('i', $user_id); $stmt_check_user_id->execute(); if ($stmt_check_user_id->get_result()->num_rows > 0) { $error_count++; $error_details[] = "แถวที่ $row_number: ผู้ใช้งาน '$username' (ID: $user_id) มีข้อมูลนักศึกษาอยู่แล้ว"; $valid_row = false; } }
                        if (!$valid_row) { $row_number++; continue; }

                        $stmt_insert->bind_param('isi', $user_id, $student_id_number, $group_id);
                        if ($stmt_insert->execute()) { $success_count++; } else { $error_count++; $error_details[] = "แถวที่ $row_number: ไม่สามารถเพิ่มข้อมูลนักศึกษาสำหรับ '$username' ได้ - " . $stmt_insert->error; }
                        $row_number++;
                    }
                    $stmt_insert->close(); $stmt_get_user->close(); $stmt_get_group->close(); $stmt_check_std_id->close(); $stmt_check_user_id->close();
                }
                // --- Activity Units Import ---
                elseif ($import_type === 'activity_units') {
                    $expected_headers = ['unitname', 'unittype'];
                    $min_columns = 2;
                    if (count($normalized_headers) < $min_columns || array_slice($normalized_headers, 0, $min_columns) !== $expected_headers) {
                        throw new Exception("Header ในไฟล์ CSV Activity Units ไม่ตรง (ควรเป็น UnitName, UnitType)");
                    }

                    $sql_insert = "INSERT INTO activity_units (name, type) VALUES (?, ?)";
                    $stmt_insert = $mysqli->prepare($sql_insert); if (!$stmt_insert) throw new Exception("Prepare Insert Unit failed: " . $mysqli->error);
                    $sql_check = "SELECT id FROM activity_units WHERE name = ?";
                    $stmt_check = $mysqli->prepare($sql_check); if (!$stmt_check) throw new Exception("Prepare Check Unit Name failed: " . $mysqli->error);

                    while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
                        if (count($data) < $min_columns) { $error_count++; $error_details[] = "แถวที่ $row_number: ข้อมูล Activity Units ไม่ครบ $min_columns คอลัมน์"; $row_number++; continue; }
                        $unit_name = trim($data[0]); $unit_type = trim($data[1]);
                        $valid_row = true; $errors = [];

                        if (empty($unit_name)) { $errors[] = "UnitName ว่างเปล่า"; $valid_row = false; }
                        if (empty($unit_type)) { $errors[] = "UnitType ว่างเปล่า"; $valid_row = false; }
                        elseif (!in_array($unit_type, $possible_unit_types)) { $errors[] = "UnitType '$unit_type' ไม่ถูกต้อง (ต้องเป็น: " . implode('/', $possible_unit_types) . ")"; $valid_row = false; }
                        if (!$valid_row) { $error_count++; $error_details[] = "แถวที่ $row_number ($unit_name): ข้อมูลจำเป็นไม่ครบถ้วน/ผิดรูปแบบ (" . implode(', ', $errors) . ")"; $row_number++; continue; }

                        $stmt_check->bind_param('s', $unit_name); $stmt_check->execute();
                        if ($stmt_check->get_result()->num_rows > 0) { $error_count++; $error_details[] = "แถวที่ $row_number: ชื่อหน่วยงาน '$unit_name' ซ้ำ"; $row_number++; continue; }

                        $stmt_insert->bind_param('ss', $unit_name, $unit_type);
                        if ($stmt_insert->execute()) { $success_count++; } else { $error_count++; $error_details[] = "แถวที่ $row_number: ไม่สามารถเพิ่มหน่วยงาน '$unit_name' ได้ - " . $stmt_insert->error; }
                        $row_number++;
                    }
                     $stmt_insert->close(); $stmt_check->close();
                }
                 else {
                     throw new Exception("ประเภทข้อมูลที่เลือกยังไม่รองรับการ Import");
                 }

                // --- Final Commit/Rollback ---
                if ($error_count > 0) {
                    $mysqli->rollback();
                    $message = '<p class="alert alert-warning text-white">Import (' . ucfirst($import_type) . ') ล้มเหลว มีข้อผิดพลาด ' . $error_count . ' รายการ</p>';
                } else {
                    $mysqli->commit();
                    $message = '<p class="alert alert-success text-white">Import ข้อมูล ' . ucfirst($import_type) . ' สำเร็จ ' . $success_count . ' รายการ</p>';
                }

            } catch (Exception $e) {
                $mysqli->rollback();
                $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดร้ายแรงระหว่าง Import (' . ucfirst($import_type) . '): ' . $e->getMessage() . '</p>';
                // Close any potentially open statements from the specific block
                 if (isset($stmt_insert) && $stmt_insert) $stmt_insert->close();
                 if (isset($stmt_check) && $stmt_check) $stmt_check->close();
                 if (isset($stmt_insert_group) && $stmt_insert_group) $stmt_insert_group->close();
                 if (isset($stmt_insert_advisor) && $stmt_insert_advisor) $stmt_insert_advisor->close();
                 if (isset($stmt_delete_advisor) && $stmt_delete_advisor) $stmt_delete_advisor->close();
                 if (isset($stmt_check_code) && $stmt_check_code) $stmt_check_code->close();
                 if (isset($stmt_get_level) && $stmt_get_level) $stmt_get_level->close();
                 if (isset($stmt_get_major) && $stmt_get_major) $stmt_get_major->close();
                 if (isset($stmt_get_advisor) && $stmt_get_advisor) $stmt_get_advisor->close();
                 if (isset($stmt_check_user) && $stmt_check_user) $stmt_check_user->close();
                 if (isset($stmt_check_email) && $stmt_check_email) $stmt_check_email->close();
                 if (isset($stmt_get_role) && $stmt_get_role) $stmt_get_role->close();
                 if (isset($stmt_get_user) && $stmt_get_user) $stmt_get_user->close();
                 if (isset($stmt_get_group) && $stmt_get_group) $stmt_get_group->close();
                 if (isset($stmt_check_std_id) && $stmt_check_std_id) $stmt_check_std_id->close();
                 if (isset($stmt_check_user_id) && $stmt_check_user_id) $stmt_check_user_id->close();

            } finally {
                 if (isset($handle) && is_resource($handle)) {
                    fclose($handle);
                 }
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
                                <option value="advisors" disabled>ข้อมูล Advisor - เร็วๆ นี้</option>
                                <option value="staff" disabled>ข้อมูล Staff - เร็วๆ นี้</option>
                                </select>
                        </div>

                        <div class="input-group input-group-outline my-3">
                             <label class="form-label visually-hidden">เลือกไฟล์ CSV</label> <input type="file" id="csv_file" name="csv_file" class="form-control" accept=".csv" required>
                         </div>
                         <small class="text-muted d-block mb-1">รองรับเฉพาะไฟล์ .csv ที่เข้ารหัสแบบ UTF-8</small>

                         <div id="template-info-majors" class="template-info mt-2" style="display: none;"> <small class="text-muted d-block mb-2">รูปแบบไฟล์ Majors: Header 'MajorCode,MajorName', แถวถัดไปคือรหัสสาขาและชื่อสาขา</small>
                            <a href="download_template.php?type=majors" target="_blank" class="btn btn-sm btn-outline-secondary mb-3">
                                <i class="material-symbols-rounded me-1" style="font-size: 1em; vertical-align: text-bottom;">download</i>
                                ดาวน์โหลดเทมเพลต Majors
                            </a>
                         </div>
                         <div id="template-info-student_groups" class="template-info mt-2" style="display: none;">
                              <small class="text-muted d-block mb-2">รูปแบบไฟล์ Groups: Header 'GroupCode,GroupName,LevelCode,MajorCode,AdvisorUsernames' (Username คั่นด้วยคอมม่าถ้ามีหลายคน)</small>
                              <a href="download_template.php?type=student_groups" target="_blank" class="btn btn-sm btn-outline-secondary mb-3">
                                <i class="material-symbols-rounded me-1" style="font-size: 1em; vertical-align: text-bottom;">download</i>
                                ดาวน์โหลดเทมเพลต Groups
                             </a>
                         </div>
                         <div id="template-info-users" class="template-info mt-2" style="display: none;">
                              <small class="text-muted d-block mb-2">รูปแบบไฟล์ Users: Header 'Username,Password,FirstName,LastName,Email,RoleName' (RoleName: admin, advisor, student, staff)</small>
                              <strong class="text-danger d-block mb-2">คำเตือน: ควรใช้รหัสผ่านชั่วคราวในไฟล์ CSV เท่านั้น!</strong>
                              <a href="download_template.php?type=users" target="_blank" class="btn btn-sm btn-outline-secondary mb-3">
                                <i class="material-symbols-rounded me-1" style="font-size: 1em; vertical-align: text-bottom;">download</i>
                                ดาวน์โหลดเทมเพลต Users
                             </a>
                         </div>
                          <div id="template-info-students" class="template-info mt-2" style="display: none;">
                              <small class="text-muted d-block mb-2">รูปแบบไฟล์ Students: Header 'Username,StudentIDNumber,GroupCode' (Username และ GroupCode ต้องมีอยู่แล้วในระบบ)</small>
                              <a href="download_template.php?type=students" target="_blank" class="btn btn-sm btn-outline-secondary mb-3">
                                <i class="material-symbols-rounded me-1" style="font-size: 1em; vertical-align: text-bottom;">download</i>
                                ดาวน์โหลดเทมเพลต Students
                             </a>
                         </div>
                         <div id="template-info-activity_units" class="template-info mt-2" style="display: none;">
                              <small class="text-muted d-block mb-2">รูปแบบไฟล์ Units: Header 'UnitName,UnitType' (UnitType ต้องเป็นค่าที่กำหนดไว้ เช่น Internal, External)</small>
                              <a href="download_template.php?type=activity_units" target="_blank" class="btn btn-sm btn-outline-secondary mb-3">
                                <i class="material-symbols-rounded me-1" style="font-size: 1em; vertical-align: text-bottom;">download</i>
                                ดาวน์โหลดเทมเพลต Units
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
    // Initial call to show info if a type is pre-selected or on page load
    document.getElementById('import_type').dispatchEvent(new Event('change'));
</script>
