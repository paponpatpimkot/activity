<?php
// ========================================================================
// ไฟล์: activity_form.php (เนื้อหาสำหรับ include)
// หน้าที่: ฟอร์มสำหรับเพิ่ม หรือ แก้ไข กิจกรรม (เพิ่มส่วนจัดการสาขาและโควต้า)
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php ($mysqli), และ Authorization check (Admin or Staff) ---
// --- ได้ทำไปแล้วในไฟล์ Controller หลัก ---
// require 'db_connect.php'; // $mysqli should be available
// if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 4])) { exit('Unauthorized'); }

$page_title = "เพิ่มกิจกรรมใหม่";
$form_action = "index.php?page=activity_form";
$activity_id = null;
$activity_name = ''; $description = ''; $start_datetime = ''; $end_datetime = '';
$location = ''; $organizer_unit_id = ''; $hours_organizer = 0.0; $hours_participant = 0.0;
$penalty_hours = 0.0; $max_participants = '';
$attendance_recorder_type = 'system';
$selected_staff_recorder_ids = [];
$selected_staff_recorders_data = [];
$selected_eligible_majors = []; // เก็บข้อมูลสาขาที่เลือกพร้อมโควต้า [major_id => max_participants_for_major]

$message = $message ?? ''; // Use existing message if passed from controller
$is_edit_mode = false;

// --- ดึงข้อมูลสำหรับ Dropdowns และ Checkboxes ---
$units = [];
$sql_units = "SELECT id, name FROM activity_units ORDER BY name ASC";
$result_units = $mysqli->query($sql_units);
if ($result_units) { while ($row = $result_units->fetch_assoc()) { $units[] = $row; } $result_units->free(); }

$all_majors_for_form = [];
$sql_all_majors = "SELECT id, name, major_code FROM majors ORDER BY name ASC";
$result_all_majors = $mysqli->query($sql_all_majors);
if ($result_all_majors) { while ($row = $result_all_majors->fetch_assoc()) { $all_majors_for_form[] = $row; } $result_all_majors->free(); }


// --- Check if Editing ---
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $is_edit_mode = true;
    $activity_id = (int)$_GET['id'];
    $page_title = "แก้ไขกิจกรรม";
    $form_action = "index.php?page=activity_form&id=" . $activity_id;

    $sql_edit = "SELECT * FROM activities WHERE id = ?";
    // if ($_SESSION['role_id'] == 4) { // Staff can only edit their own activities
    //     $sql_edit .= " AND created_by_user_id = ?";
    // }
    $stmt_edit = $mysqli->prepare($sql_edit);

    if ($stmt_edit) {
        // if ($_SESSION['role_id'] == 4) {
        //     $stmt_edit->bind_param('ii', $activity_id, $_SESSION['user_id']);
        // } else {
            $stmt_edit->bind_param('i', $activity_id);
        // }
        $stmt_edit->execute();
        $result_edit = $stmt_edit->get_result();

        if ($result_edit->num_rows === 1) {
            $activity_data = $result_edit->fetch_assoc();
            // Optional: Check ownership again if staff, even if query already filtered
            // if ($_SESSION['role_id'] == 4 && $activity_data['created_by_user_id'] != $_SESSION['user_id']) {
            //     $_SESSION['form_message'] = '<p class="alert alert-danger text-white">คุณไม่มีสิทธิ์แก้ไขกิจกรรมนี้ (Ownership check failed)</p>';
            //     header('Location: index.php?page=activities_list'); exit;
            // }

            $activity_name = $activity_data['name'];
            $description = $activity_data['description'];
            $start_datetime = !empty($activity_data['start_datetime']) ? date('Y-m-d\TH:i', strtotime($activity_data['start_datetime'])) : '';
            $end_datetime = !empty($activity_data['end_datetime']) ? date('Y-m-d\TH:i', strtotime($activity_data['end_datetime'])) : '';
            $location = $activity_data['location'];
            $organizer_unit_id = $activity_data['organizer_unit_id'];
            $hours_organizer = (float)$activity_data['hours_organizer'];
            $hours_participant = (float)$activity_data['hours_participant'];
            $penalty_hours = (float)$activity_data['penalty_hours'];
            $max_participants = $activity_data['max_participants']; // Keep as is (null or number)
            $attendance_recorder_type = $activity_data['attendance_recorder_type'] ?? 'system';

            // Fetch selected staff recorders
            $sql_selected_staff = "SELECT asr.staff_user_id, u.first_name, u.last_name FROM activity_specific_recorders asr JOIN users u ON asr.staff_user_id = u.id WHERE asr.activity_id = ?";
            $stmt_selected_staff = $mysqli->prepare($sql_selected_staff);
            if ($stmt_selected_staff) {
                $stmt_selected_staff->bind_param('i', $activity_id); $stmt_selected_staff->execute();
                $result_selected_staff = $stmt_selected_staff->get_result();
                while ($row_staff = $result_selected_staff->fetch_assoc()) { $selected_staff_recorder_ids[] = $row_staff['staff_user_id']; $selected_staff_recorders_data[] = $row_staff; }
                $stmt_selected_staff->close();
            }

            // *** Fetch selected eligible majors and their quotas ***
            $sql_eligible = "SELECT major_id, max_participants_for_major FROM activity_eligible_majors WHERE activity_id = ?";
            $stmt_eligible = $mysqli->prepare($sql_eligible);
            if ($stmt_eligible) {
                $stmt_eligible->bind_param('i', $activity_id); $stmt_eligible->execute();
                $result_eligible = $stmt_eligible->get_result();
                while ($row_eligible = $result_eligible->fetch_assoc()) {
                    $selected_eligible_majors[$row_eligible['major_id']] = $row_eligible['max_participants_for_major'];
                }
                $stmt_eligible->close();
            }

        } else {
            $_SESSION['form_message'] = '<p class="alert alert-danger text-white">ไม่พบข้อมูลกิจกรรมที่ต้องการแก้ไข หรือคุณไม่มีสิทธิ์แก้ไขกิจกรรมนี้</p>';
            header('Location: index.php?page=activities_list'); exit;
        }
        $stmt_edit->close();
    } else { $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการเตรียมคำสั่งดึงข้อมูลกิจกรรม</p>'; }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') { // Repopulate form on POST error (values will be overwritten by more specific validation below if errors)
    $activity_name = $_POST['activity_name'] ?? ''; $description = $_POST['description'] ?? '';
    $start_datetime = $_POST['start_datetime'] ?? ''; $end_datetime = $_POST['end_datetime'] ?? ''; // Keep form display values
    $location = $_POST['location'] ?? ''; $organizer_unit_id = $_POST['organizer_unit_id'] ?? '';
    $hours_organizer = $_POST['hours_organizer'] ?? 0.0; $hours_participant = $_POST['hours_participant'] ?? 0.0;
    $penalty_hours = $_POST['penalty_hours'] ?? 0.0; $max_participants = $_POST['max_participants'] ?? '';
    $attendance_recorder_type = $_POST['attendance_recorder_type'] ?? 'system';
    $selected_staff_recorder_ids = $_POST['specific_recorders'] ?? []; // IDs only
    // Repopulate selected staff data if needed (complex, usually done if validation fails and form re-renders)
    if (!empty($selected_staff_recorder_ids) && is_array($selected_staff_recorder_ids)) {
        $placeholders = implode(',', array_fill(0, count($selected_staff_recorder_ids), '?'));
        $types = str_repeat('i', count($selected_staff_recorder_ids));
        $sql_refetch_staff = "SELECT id as staff_user_id, first_name, last_name FROM users WHERE id IN ($placeholders) AND role_id = 4"; // Assuming role_id 4 is Staff
        $stmt_refetch = $mysqli->prepare($sql_refetch_staff);
        if($stmt_refetch){
            $stmt_refetch->bind_param($types, ...$selected_staff_recorder_ids);
            $stmt_refetch->execute();
            $result_refetch = $stmt_refetch->get_result();
            while($row_s = $result_refetch->fetch_assoc()){ $selected_staff_recorders_data[] = $row_s; }
            $stmt_refetch->close();
        }
    }
    // Repopulate selected eligible majors on POST error
    $submitted_eligible_majors_post = $_POST['eligible_majors'] ?? [];
    $submitted_major_quotas_post = $_POST['major_participants'] ?? [];
    foreach($submitted_eligible_majors_post as $m_id_post) {
        if (filter_var($m_id_post, FILTER_VALIDATE_INT)) {
            $selected_eligible_majors[(int)$m_id_post] = $submitted_major_quotas_post[$m_id_post] ?? null;
        }
    }
}

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Assign POST values to working variables for validation and saving
    $activity_name = trim($_POST['activity_name']);
    $description = trim($_POST['description']);
    $start_datetime_input = trim($_POST['start_datetime']);
    $end_datetime_input = trim($_POST['end_datetime']);
    $location = trim($_POST['location']);
    $organizer_unit_id_input = filter_input(INPUT_POST, 'organizer_unit_id', FILTER_VALIDATE_INT);
    $hours_organizer_input = isset($_POST['hours_organizer']) ? filter_var($_POST['hours_organizer'], FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) : 0.0;
    $hours_participant_input = isset($_POST['hours_participant']) ? filter_var($_POST['hours_participant'], FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) : 0.0;
    $penalty_hours_input = isset($_POST['penalty_hours']) ? filter_var($_POST['penalty_hours'], FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) : 0.0;
    $max_participants_input = trim($_POST['max_participants']);
    $max_participants_save = ($max_participants_input === '' || !is_numeric($max_participants_input) || (int)$max_participants_input < 0) ? null : (int)$max_participants_input;

    $attendance_recorder_type_input = $_POST['attendance_recorder_type'] ?? 'system';
    $submitted_staff_ids = $_POST['specific_recorders'] ?? [];

    $submitted_eligible_majors = $_POST['eligible_majors'] ?? [];
    $submitted_major_quotas = $_POST['major_participants'] ?? [];

    // Convert datetime-local input to MySQL DATETIME format
    $start_datetime_db = null;
    if (!empty($start_datetime_input)) {
        $timestamp_start = strtotime($start_datetime_input);
        if ($timestamp_start !== false) {
            $start_datetime_db = date('Y-m-d H:i:s', $timestamp_start);
        }
    }

    $end_datetime_db = null;
    if (!empty($end_datetime_input)) {
        $timestamp_end = strtotime($end_datetime_input);
        if ($timestamp_end !== false) {
            $end_datetime_db = date('Y-m-d H:i:s', $timestamp_end);
        }
    }

    $errors = [];
    if (empty($activity_name)) $errors[] = "กรุณากรอกชื่อกิจกรรม";
    if (empty($start_datetime_db)) $errors[] = "กรุณากรอกวันเวลาเริ่มต้น หรือรูปแบบวันที่ไม่ถูกต้อง";
    if (empty($end_datetime_db)) $errors[] = "กรุณากรอกวันเวลาสิ้นสุด หรือรูปแบบวันที่ไม่ถูกต้อง";
    if (!empty($start_datetime_db) && !empty($end_datetime_db) && strtotime($end_datetime_db) <= strtotime($start_datetime_db)) {
        $errors[] = "วันเวลาสิ้นสุดต้องอยู่หลังวันเวลาเริ่มต้น";
    }
    if ($organizer_unit_id_input === false || $organizer_unit_id_input === null) $errors[] = "กรุณาเลือกหน่วยงานผู้จัด";
    if ($hours_organizer_input === null || $hours_organizer_input < 0) $errors[] = "ชั่วโมงสำหรับผู้จัดต้องเป็นตัวเลขไม่ติดลบ";
    if ($hours_participant_input === null || $hours_participant_input < 0) $errors[] = "ชั่วโมงสำหรับผู้เข้าร่วมต้องเป็นตัวเลขไม่ติดลบ";
    if ($penalty_hours_input === null || $penalty_hours_input < 0) $errors[] = "ชั่วโมงหัก (กรณีไม่เข้าร่วม) ต้องเป็นตัวเลขไม่ติดลบ";


    // --- Validate Eligible Majors and Quotas ---
    $validated_eligible_majors_data = [];
    $total_major_quotas = 0;
    $has_any_major_selected = !empty($submitted_eligible_majors) && is_array($submitted_eligible_majors);

    if ($has_any_major_selected) {
        foreach ($submitted_eligible_majors as $m_id) {
            if (!filter_var($m_id, FILTER_VALIDATE_INT)) {
                $errors[] = "รหัสสาขาที่เลือกไม่ถูกต้อง";
                break;
            }
            $m_id_int = (int)$m_id;
            $quota_input = $submitted_major_quotas[$m_id_int] ?? '';
            $quota_for_major = null;

            if ($quota_input !== '') { // If quota is specified
                if (!is_numeric($quota_input) || (int)$quota_input < 0) {
                    $errors[] = "จำนวนรับสำหรับสาขา (รหัส: " . htmlspecialchars($m_id_int) . ") ต้องเป็นตัวเลขไม่ติดลบ";
                } else {
                    $quota_for_major = (int)$quota_input;
                    $total_major_quotas += $quota_for_major;
                }
            }
            $validated_eligible_majors_data[$m_id_int] = $quota_for_major;
        }
    }

    // Check if sum of major quotas exceeds overall max_participants (if overall max is set)
    if ($has_any_major_selected && !is_null($max_participants_save) && $max_participants_save >= 0) { // Only if overall max_participants is defined
        $all_major_quotas_are_null = true;
        foreach($validated_eligible_majors_data as $quota_val_check) {
            if(!is_null($quota_val_check)) {
                $all_major_quotas_are_null = false;
                break;
            }
        }
        // If at least one major quota is specified, their sum should not exceed overall max_participants
        if (!$all_major_quotas_are_null && $total_major_quotas > $max_participants_save) {
             $errors[] = "ผลรวมจำนวนรับของแต่ละสาขา ($total_major_quotas) เกินจำนวนรับสูงสุดของกิจกรรม ($max_participants_save)";
        }
    }


    if (!empty($errors)) {
        $message = '<p class="alert alert-danger text-white">' . implode('<br>', $errors) . '</p>';
        // Repopulate values for form display after error
        $activity_name = $_POST['activity_name'] ?? ''; $description = $_POST['description'] ?? '';
        $start_datetime = $_POST['start_datetime'] ?? ''; $end_datetime = $_POST['end_datetime'] ?? '';
        $location = $_POST['location'] ?? ''; $organizer_unit_id = $_POST['organizer_unit_id'] ?? '';
        $hours_organizer = $_POST['hours_organizer'] ?? 0.0; $hours_participant = $_POST['hours_participant'] ?? 0.0;
        $penalty_hours = $_POST['penalty_hours'] ?? 0.0; $max_participants = $_POST['max_participants'] ?? '';
        $attendance_recorder_type = $_POST['attendance_recorder_type'] ?? 'system';
        $selected_staff_recorder_ids = $_POST['specific_recorders'] ?? [];
        // Re-fetch staff data for display
        $selected_staff_recorders_data = [];
         if (!empty($selected_staff_recorder_ids) && is_array($selected_staff_recorder_ids)) {
            $placeholders = implode(',', array_fill(0, count($selected_staff_recorder_ids), '?'));
            $types = str_repeat('i', count($selected_staff_recorder_ids));
            $sql_refetch_staff = "SELECT id as staff_user_id, first_name, last_name FROM users WHERE id IN ($placeholders) AND role_id = 4";
            $stmt_refetch = $mysqli->prepare($sql_refetch_staff);
            if($stmt_refetch){
                $stmt_refetch->bind_param($types, ...$selected_staff_recorder_ids);
                $stmt_refetch->execute();
                $result_refetch = $stmt_refetch->get_result();
                while($row_s = $result_refetch->fetch_assoc()){ $selected_staff_recorders_data[] = $row_s; }
                $stmt_refetch->close();
            }
        }
        $selected_eligible_majors = []; // Re-populate for display
        $submitted_eligible_majors_post_err = $_POST['eligible_majors'] ?? [];
        $submitted_major_quotas_post_err = $_POST['major_participants'] ?? [];
        foreach($submitted_eligible_majors_post_err as $m_id_post_err) {
            if (filter_var($m_id_post_err, FILTER_VALIDATE_INT)) {
                 $selected_eligible_majors[(int)$m_id_post_err] = $submitted_major_quotas_post_err[$m_id_post_err] ?? null;
            }
        }

    } else {
        $mysqli->begin_transaction();
        try {
            $target_activity_id = null;
            $current_user_id = $_SESSION['user_id']; // Assuming user_id is stored in session

            if ($is_edit_mode && $activity_id !== null) {
                // Check if user has permission to edit (Admin or owner Staff)
                // This logic might be more complex depending on your exact authorization rules
                $can_edit_check_sql = "SELECT created_by_user_id FROM activities WHERE id = ?";
                $stmt_perm = $mysqli->prepare($can_edit_check_sql);
                $stmt_perm->bind_param('i', $activity_id);
                $stmt_perm->execute();
                $res_perm = $stmt_perm->get_result();
                $activity_owner = $res_perm->fetch_assoc();
                $stmt_perm->close();

                // if ($_SESSION['role_id'] == 4 && $activity_owner['created_by_user_id'] != $current_user_id) {
                //     throw new Exception("คุณไม่มีสิทธิ์แก้ไขกิจกรรมนี้ (Staff not owner)");
                // }


                $sql = "UPDATE activities SET name = ?, description = ?, start_datetime = ?, end_datetime = ?, location = ?, organizer_unit_id = ?, hours_organizer = ?, hours_participant = ?, penalty_hours = ?, max_participants = ?, attendance_recorder_type = ? WHERE id = ?";
                $stmt = $mysqli->prepare($sql); if (!$stmt) throw new Exception("Prepare Update Error: " . $mysqli->error);
                $stmt->bind_param('sssssiddissi', $activity_name, $description, $start_datetime_db, $end_datetime_db, $location, $organizer_unit_id_input, $hours_organizer_input, $hours_participant_input, $penalty_hours_input, $max_participants_save, $attendance_recorder_type_input, $activity_id);
                if (!$stmt->execute()) throw new Exception("Execute Update Error: " . $stmt->error);
                $stmt->close();
                $target_activity_id = $activity_id;
                $_SESSION['form_message'] = '<p class="alert alert-success text-white">แก้ไขข้อมูลกิจกรรมสำเร็จแล้ว</p>';
            } else { // Insert mode
                $sql = "INSERT INTO activities (name, description, start_datetime, end_datetime, location, organizer_unit_id, hours_organizer, hours_participant, penalty_hours, max_participants, created_by_user_id, attendance_recorder_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $mysqli->prepare($sql); if (!$stmt) throw new Exception("Prepare Insert Error: " . $mysqli->error);
                $stmt->bind_param('sssssiddsiis', $activity_name, $description, $start_datetime_db, $end_datetime_db, $location, $organizer_unit_id_input, $hours_organizer_input, $hours_participant_input, $penalty_hours_input, $max_participants_save, $current_user_id, $attendance_recorder_type_input);
                if (!$stmt->execute()) throw new Exception("Execute Insert Error: " . $stmt->error . " (Query: $sql)");
                $target_activity_id = $mysqli->insert_id;
                $stmt->close();
                $_SESSION['form_message'] = '<p class="alert alert-success text-white">เพิ่มกิจกรรมใหม่สำเร็จแล้ว</p>';
            }

            // --- Synchronize Specific Staff Recorders ---
            if ($target_activity_id) {
                // Delete existing staff recorders for this activity
                $sql_delete_staff = "DELETE FROM activity_specific_recorders WHERE activity_id = ?";
                $stmt_delete_staff = $mysqli->prepare($sql_delete_staff);
                if (!$stmt_delete_staff) throw new Exception("Prepare Delete Staff Recorders Error: " . $mysqli->error);
                $stmt_delete_staff->bind_param('i', $target_activity_id);
                if (!$stmt_delete_staff->execute()) throw new Exception("Execute Delete Staff Recorders Error: " . $stmt_delete_staff->error);
                $stmt_delete_staff->close();

                if ($attendance_recorder_type_input === 'system' && !empty($submitted_staff_ids) && is_array($submitted_staff_ids)) {
                    $sql_insert_staff = "INSERT INTO activity_specific_recorders (activity_id, staff_user_id) VALUES (?, ?)";
                    $stmt_insert_staff = $mysqli->prepare($sql_insert_staff);
                    if (!$stmt_insert_staff) throw new Exception("Prepare Insert Staff Recorder Error: " . $mysqli->error);
                    foreach ($submitted_staff_ids as $staff_id) {
                        if (filter_var($staff_id, FILTER_VALIDATE_INT)) {
                            $stmt_insert_staff->bind_param('ii', $target_activity_id, $staff_id);
                            if (!$stmt_insert_staff->execute()) {
                                // Log or handle individual insert error if necessary
                                error_log("Error inserting staff recorder ID $staff_id for activity ID $target_activity_id: " . $stmt_insert_staff->error);
                            }
                        }
                    }
                    $stmt_insert_staff->close();
                }
            }
            // --- End Synchronize Specific Staff Recorders ---

            // *** Synchronize Eligible Majors and their Quotas ***
            if ($target_activity_id) {
                // 1. Delete existing eligible majors for this activity
                $sql_delete_majors = "DELETE FROM activity_eligible_majors WHERE activity_id = ?";
                $stmt_delete_majors = $mysqli->prepare($sql_delete_majors);
                if (!$stmt_delete_majors) throw new Exception("Prepare Delete Eligible Majors Error: " . $mysqli->error);
                $stmt_delete_majors->bind_param('i', $target_activity_id);
                if (!$stmt_delete_majors->execute()) throw new Exception("Execute Delete Eligible Majors Error: " . $stmt_delete_majors->error);
                $stmt_delete_majors->close();

                // 2. Insert new selected eligible majors with their quotas
                if (!empty($validated_eligible_majors_data)) {
                    $sql_insert_major = "INSERT INTO activity_eligible_majors (activity_id, major_id, max_participants_for_major) VALUES (?, ?, ?)";
                    $stmt_insert_major = $mysqli->prepare($sql_insert_major);
                    if (!$stmt_insert_major) throw new Exception("Prepare Insert Eligible Major Error: " . $mysqli->error);

                    foreach ($validated_eligible_majors_data as $m_id => $quota) {
                         // MySQL will treat empty string as 0 for INT, or you can explicitly pass null
                        $quota_to_save = ($quota === '' || is_null($quota)) ? null : (int)$quota;
                        $stmt_insert_major->bind_param('iis', $target_activity_id, $m_id, $quota_to_save);
                        if (!$stmt_insert_major->execute()) {
                            error_log("Error inserting eligible major ID $m_id with quota '$quota_to_save' for activity ID $target_activity_id: " . $stmt_insert_major->error);
                        }
                    }
                    $stmt_insert_major->close();
                }
            }
            // *** End Synchronize Eligible Majors ***

            $mysqli->commit();
            // Success message is already set above
            header('Location: index.php?page=activities_list'); exit;
        } catch (Exception $e) {
            $mysqli->rollback();
            $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage() . '</p>';
            // Repopulate values for form display after error (already done above in main POST check if errors)
            // Ensure $selected_eligible_majors is set from $validated_eligible_majors_data if an error occurs mid-transaction
            $selected_eligible_majors = $validated_eligible_majors_data;
        }
    }
}

?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-lg-10 col-md-12 mx-auto">
            <div class="card my-4">
                <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div class="bg-gradient-success shadow-success border-radius-lg pt-4 pb-3">
                        <h6 class="text-white text-capitalize ps-3"><?php echo htmlspecialchars($page_title); ?></h6>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($message)) : ?>
                        <div class="alert alert-dismissible text-white fade show <?php echo (strpos($message, 'success') !== false || strpos($message, 'สำเร็จ') !== false) ? 'alert-success bg-gradient-success' : ((strpos($message, 'warning') !== false || strpos($message, 'เตือน') !== false) ? 'alert-warning bg-gradient-warning' : 'alert-danger bg-gradient-danger'); ?>" role="alert">
                            <?php echo $message; // Message is already HTML ?>
                            <button type="button" class="btn-close p-3" data-bs-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['form_message'])) : ?>
                         <div class="alert alert-dismissible text-white fade show <?php echo (strpos($_SESSION['form_message'], 'success') !== false || strpos($_SESSION['form_message'], 'สำเร็จ') !== false) ? 'alert-success bg-gradient-success' : 'alert-danger bg-gradient-danger'; ?>" role="alert">
                            <?php echo $_SESSION['form_message']; unset($_SESSION['form_message']); ?>
                            <button type="button" class="btn-close p-3" data-bs-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                    <?php endif; ?>


                    <form role="form" class="text-start" action="<?php echo htmlspecialchars($form_action); ?>" method="post" id="activityForm">
                        <div class="input-group input-group-outline my-3 <?php echo !empty($activity_name) ? 'is-filled' : ''; ?>"><label class="form-label">ชื่อกิจกรรม</label><input type="text" id="activity_name" name="activity_name" class="form-control" value="<?php echo htmlspecialchars($activity_name); ?>" required></div>
                        <div class="input-group input-group-outline my-3 <?php echo !empty($description) ? 'is-filled' : ''; ?>"><label class="form-label">รายละเอียดกิจกรรม</label><textarea id="description" name="description" class="form-control" rows="3"><?php echo htmlspecialchars($description); ?></textarea></div>
                        <div class="row">
                            <div class="col-md-6"><div class="input-group input-group-static my-3"><label>วันเวลาเริ่มต้น</label><input type="datetime-local" id="start_datetime" name="start_datetime" class="form-control" value="<?php echo htmlspecialchars($start_datetime); ?>" required></div></div>
                            <div class="col-md-6"><div class="input-group input-group-static my-3"><label>วันเวลาสิ้นสุด</label><input type="datetime-local" id="end_datetime" name="end_datetime" class="form-control" value="<?php echo htmlspecialchars($end_datetime); ?>" required></div></div>
                        </div>
                        <div class="input-group input-group-outline my-3 <?php echo !empty($location) ? 'is-filled' : ''; ?>"><label class="form-label">สถานที่จัด</label><input type="text" id="location" name="location" class="form-control" value="<?php echo htmlspecialchars($location); ?>"></div>
                        <div class="input-group input-group-static mb-4"><label for="organizer_unit_id" class="ms-0">หน่วยงานผู้จัด</label><select class="form-control" id="organizer_unit_id" name="organizer_unit_id" required><option value="">-- เลือกหน่วยงาน --</option><?php foreach ($units as $unit): ?><option value="<?php echo $unit['id']; ?>" <?php echo ($organizer_unit_id == $unit['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($unit['name']); ?></option><?php endforeach; ?></select></div>
                        <hr class="dark horizontal my-3"><p class="text-sm font-weight-bold">จำนวนชั่วโมง</p>
                        <div class="row">
                            <div class="col-md-4"><div class="input-group input-group-outline my-3 <?php echo ($hours_organizer != '' || (isset($_POST['hours_organizer']) && $_POST['hours_organizer'] != '')) ? 'is-filled' : ''; ?>"><label class="form-label">สำหรับผู้จัด</label><input type="number" step="0.1" min="0" id="hours_organizer" name="hours_organizer" class="form-control" value="<?php echo htmlspecialchars($hours_organizer); ?>" required></div></div>
                            <div class="col-md-4"><div class="input-group input-group-outline my-3 <?php echo ($hours_participant != '' || (isset($_POST['hours_participant']) && $_POST['hours_participant'] != '')) ? 'is-filled' : ''; ?>"><label class="form-label">สำหรับผู้เข้าร่วม</label><input type="number" step="0.1" min="0" id="hours_participant" name="hours_participant" class="form-control" value="<?php echo htmlspecialchars($hours_participant); ?>" required></div></div>
                            <div class="col-md-4"><div class="input-group input-group-outline my-3 <?php echo ($penalty_hours != '' || (isset($_POST['penalty_hours']) && $_POST['penalty_hours'] != '')) ? 'is-filled' : ''; ?>"><label class="form-label">ชั่วโมงหัก (ไม่เข้าร่วม)</label><input type="number" step="0.1" min="0" id="penalty_hours" name="penalty_hours" class="form-control" value="<?php echo htmlspecialchars($penalty_hours); ?>" required></div></div>
                        </div>
                        <div class="input-group input-group-outline my-3 <?php echo (!is_null($max_participants) && $max_participants !== '') ? 'is-filled' : ''; ?>"><label class="form-label">จำนวนรับสูงสุด (รวมทุกสาขา)</label><input type="number" min="0" id="max_participants" name="max_participants" class="form-control" value="<?php echo htmlspecialchars($max_participants ?? ''); ?>"></div>

                        <hr class="dark horizontal my-3">
                        <p class="text-sm font-weight-bold">การเช็คชื่อเข้าร่วม</p>
                        <div class="form-group mb-3">
                            <label class="form-label">ผู้มีสิทธิ์เช็คชื่อ:</label>
                            <div class="form-check mb-1"> <input class="form-check-input" type="radio" name="attendance_recorder_type" id="recorder_system" value="system" <?php echo ($attendance_recorder_type === 'system') ? 'checked' : ''; ?>> <label class="form-check-label" for="recorder_system">Admin / Staff ของระบบ</label></div>
                            <div class="form-check"> <input class="form-check-input" type="radio" name="attendance_recorder_type" id="recorder_advisor" value="advisor" <?php echo ($attendance_recorder_type === 'advisor') ? 'checked' : ''; ?>> <label class="form-check-label" for="recorder_advisor">ครูที่ปรึกษาของกลุ่มนักศึกษาที่เข้าร่วม</label></div>
                        </div>
                        <div id="specific_staff_recorder_container" style="<?php echo ($attendance_recorder_type === 'system') ? '' : 'display:none;'; ?>">
                            <p class="text-sm font-weight-bold">ระบุ Staff ผู้เช็คชื่อ (ถ้ามี, เลือกได้หลายคน):</p>
                            <div class="input-group input-group-outline mb-2"><label class="form-label">ค้นหา Staff (พิมพ์ชื่อ/นามสกุล/Username)</label><input type="text" id="staff-recorder-search" class="form-control"></div>
                            <div id="staff-recorder-search-results" class="list-group mb-3" style="max-height: 150px; overflow-y: auto; border: 1px solid #d2d6da; border-radius: 0.375rem;"></div>
                            <p class="text-xs text-muted">Staff ที่เลือก:</p>
                            <div id="selected-staff-recorders" class="mb-3 d-flex flex-wrap gap-2">
                                <?php foreach($selected_staff_recorders_data as $staff): ?>
                                    <span class="badge bg-gradient-secondary me-1 mb-1" data-staff-id="<?php echo htmlspecialchars($staff['staff_user_id']); ?>"><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?><i class="material-symbols-rounded text-sm cursor-pointer ms-1" onclick="removeStaffRecorder(this, <?php echo htmlspecialchars($staff['staff_user_id']); ?>)">close</i><input type="hidden" name="specific_recorders[]" value="<?php echo htmlspecialchars($staff['staff_user_id']); ?>"></span>
                                <?php endforeach; ?>
                            </div>
                            <small class="text-muted">หากไม่ระบุ Staff, Admin/Staff ทุกคนจะเช็คชื่อกิจกรรมนี้ได้ (ถ้าเลือก "Admin / Staff ของระบบ")</small>
                        </div>

                        <hr class="dark horizontal my-3">
                        <p class="text-sm font-weight-bold">สาขาวิชาที่เข้าร่วมได้ และจำนวนรับ (ถ้ามี)</p>
                        <p class="text-xs text-muted">หากไม่เลือกสาขาใดเลย จะถือว่าเปิดรับทุกสาขา (ตามจำนวนรับสูงสุดของกิจกรรม)</p>
                        <div id="eligible_majors_container" class="row px-1">
                            <?php if (!empty($all_majors_for_form)): ?>
                                <?php foreach ($all_majors_for_form as $major):
                                    $major_id_val = $major['id'];
                                    $is_checked = array_key_exists($major_id_val, $selected_eligible_majors);
                                    $quota_val = $is_checked ? ($selected_eligible_majors[$major_id_val] ?? '') : '';
                                ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input eligible-major-checkbox" type="checkbox"
                                                   name="eligible_majors[]"
                                                   value="<?php echo $major_id_val; ?>"
                                                   id="major_<?php echo $major_id_val; ?>"
                                                   <?php echo $is_checked ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="major_<?php echo $major_id_val; ?>">
                                                <?php echo htmlspecialchars($major['name'] . ' (' . $major['major_code'] . ')'); ?>
                                            </label>
                                        </div>
                                        <div class="input-group input-group-outline mt-1 major-quota-input-group <?php echo $is_checked && $quota_val !== '' ? 'is-filled' : ''; ?>" style="<?php echo $is_checked ? '' : 'display:none;'; ?>">
                                            <label class="form-label"><small>จำนวนรับสาขานี้</small></label>
                                            <input type="number" min="0"
                                                   name="major_participants[<?php echo $major_id_val; ?>]"
                                                   class="form-control major-quota-input"
                                                   value="<?php echo htmlspecialchars($quota_val); ?>"
                                                   placeholder="ไม่จำกัด"
                                                   style="width:100%">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-xs text-warning">ไม่พบข้อมูลสาขาวิชา กรุณาเพิ่มข้อมูลสาขาวิชาก่อน</p>
                            <?php endif; ?>
                        </div>
                        <div id="quota-warning" class="text-danger text-sm mt-2" style="display:none;">ผลรวมจำนวนรับของแต่ละสาขาเกินจำนวนรับสูงสุดของกิจกรรม!</div>
                        <div class="text-center">
                            <button type="submit" class="btn bg-gradient-primary w-100 my-4 mb-2">
                                <?php echo $is_edit_mode ? 'บันทึกการแก้ไข' : 'เพิ่มกิจกรรม'; ?>
                            </button>
                            <a href="index.php?page=activities_list" class="btn btn-outline-secondary w-100 mb-0">ยกเลิก</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    // JavaScript to show/hide specific staff recorder section
    const recorderTypeRadios = document.querySelectorAll('input[name="attendance_recorder_type"]');
    const specificStaffContainer = document.getElementById('specific_staff_recorder_container');

    function toggleSpecificStaffContainer() {
        if (document.getElementById('recorder_system').checked) {
            specificStaffContainer.style.display = 'block';
        } else {
            specificStaffContainer.style.display = 'none';
            // Optionally clear selected staff if switching to advisor
            // document.getElementById('selected-staff-recorders').innerHTML = '';
            // const staffSearchInputEl = document.getElementById('staff-recorder-search');
            // if(staffSearchInputEl) staffSearchInputEl.value = '';
            // document.getElementById('staff-recorder-search-results').innerHTML = '';
        }
    }
    recorderTypeRadios.forEach(radio => {
        radio.addEventListener('change', toggleSpecificStaffContainer);
    });
    // Initial call to set the correct state on page load
    toggleSpecificStaffContainer();

    // JavaScript for Staff Recorder Search
    const staffSearchInput = document.getElementById('staff-recorder-search');
    const staffSearchResultsContainer = document.getElementById('staff-recorder-search-results');
    const selectedStaffContainer = document.getElementById('selected-staff-recorders');
    let staffSearchTimeout;

    function addStaffRecorder(id, name) {
        if (selectedStaffContainer.querySelector(`input[name="specific_recorders[]"][value="${id}"]`)) {
            staffSearchInput.value = ''; staffSearchResultsContainer.innerHTML = ''; return; // Already added
        }
        const badge = document.createElement('span');
        badge.className = 'badge bg-gradient-secondary me-1 mb-1'; badge.dataset.staffId = id;
        badge.innerHTML = `${name} <i class="material-symbols-rounded text-sm cursor-pointer ms-1" onclick="removeStaffRecorder(this, ${id})">close</i> <input type="hidden" name="specific_recorders[]" value="${id}">`;
        selectedStaffContainer.appendChild(badge);
        staffSearchInput.value = ''; staffSearchResultsContainer.innerHTML = '';
        staffSearchInput.parentElement.classList.remove('is-filled'); // Reset label animation
    }

    function removeStaffRecorder(element, id) {
        const badge = element.closest('.badge'); if (badge) { badge.remove(); }
    }

    if(staffSearchInput) {
        staffSearchInput.addEventListener('keyup', function() {
            clearTimeout(staffSearchTimeout); const searchTerm = this.value.trim();
            if (searchTerm.length < 1) { staffSearchResultsContainer.innerHTML = ''; return; }
             this.parentElement.classList.add('is-filled'); // Keep label floated

            staffSearchTimeout = setTimeout(() => {
                fetch(`../actions/search_staff.php?term=${encodeURIComponent(searchTerm)}`) // Verify this path
                    .then(response => {
                        if (!response.ok) { throw new Error('Network error: ' + response.statusText + ' URL: ' + response.url); }
                        return response.json();
                    })
                    .then(data => {
                        staffSearchResultsContainer.innerHTML = '';
                        if (data.length > 0) {
                            data.forEach(staff => {
                                if (!selectedStaffContainer.querySelector(`input[name="specific_recorders[]"][value="${staff.id}"]`)) {
                                    const item = document.createElement('a'); item.href = '#'; item.className = 'list-group-item list-group-item-action py-2'; item.textContent = staff.label;
                                    item.onclick = (e) => { e.preventDefault(); addStaffRecorder(staff.id, staff.label); };
                                    staffSearchResultsContainer.appendChild(item);
                                }
                            });
                        } else { staffSearchResultsContainer.innerHTML = '<span class="list-group-item py-2 text-muted text-sm">ไม่พบข้อมูล Staff</span>'; }
                    })
                    .catch(error => { console.error('Error fetching staff:', error); staffSearchResultsContainer.innerHTML = '<span class="list-group-item py-2 text-danger text-sm">เกิดข้อผิดพลาด: ' + error.message + '</span>'; });
            }, 300);
        });
        // Clear search results when input loses focus and no click on results
        staffSearchInput.addEventListener('blur', function() {
            setTimeout(() => { // Timeout to allow click on search results
                if (!staffSearchResultsContainer.matches(':hover')) {
                     staffSearchResultsContainer.innerHTML = '';
                }
            }, 150);
        });
    }


    // *** JavaScript for Eligible Majors Quota ***
    const eligibleMajorsCheckboxes = document.querySelectorAll('.eligible-major-checkbox');
    const maxParticipantsInput = document.getElementById('max_participants');
    const quotaWarningDiv = document.getElementById('quota-warning');

    function toggleMajorQuotaInput(checkbox) {
        const quotaInputGroup = checkbox.closest('.col-md-6, .col-lg-4').querySelector('.major-quota-input-group');
        const quotaInput = quotaInputGroup.querySelector('.major-quota-input');
        if (quotaInputGroup) {
            if (checkbox.checked) {
                quotaInputGroup.style.display = 'block';
            } else {
                quotaInputGroup.style.display = 'none';
                if (quotaInput) quotaInput.value = ''; // Clear quota if unchecked
                quotaInputGroup.classList.remove('is-filled'); // Reset label animation
            }
        }
        validateMajorQuotas();
    }

    function validateMajorQuotas() {
        let totalMajorQuota = 0;
        let hasSpecificQuotaWithValue = false; // Track if any major has a specific numeric quota
        let errorInQuotaInput = false;

        document.querySelectorAll('.eligible-major-checkbox:checked').forEach(checkbox => {
            const quotaInputGroup = checkbox.closest('.col-md-6, .col-lg-4').querySelector('.major-quota-input-group');
            const input = quotaInputGroup.querySelector('.major-quota-input');
            if (input && input.value !== '') { // Only consider if input has a value
                const quota = parseInt(input.value, 10);
                if (!isNaN(quota) && quota >= 0) {
                    totalMajorQuota += quota;
                    hasSpecificQuotaWithValue = true;
                } else {
                    quotaWarningDiv.textContent = 'จำนวนรับของสาขาต้องเป็นตัวเลขไม่ติดลบ';
                    quotaWarningDiv.style.display = 'block';
                    errorInQuotaInput = true;
                    return; // Exit early if an invalid quota is found
                }
            } else if (input && input.value === '' && checkbox.checked){
                 // If checkbox is checked but quota is empty, it means unlimited for this major
                 // We don't add to totalMajorQuota unless we decide empty means 0 for sum check
            }
        });

        if(errorInQuotaInput) return; // Don't proceed if there was an error in quota format

        const overallMax = maxParticipantsInput.value !== '' ? parseInt(maxParticipantsInput.value, 10) : null;

        if (hasSpecificQuotaWithValue && overallMax !== null && totalMajorQuota > overallMax) {
            quotaWarningDiv.textContent = `ผลรวมจำนวนรับของแต่ละสาขา (${totalMajorQuota}) เกินจำนวนรับสูงสุดของกิจกรรม (${overallMax})!`;
            quotaWarningDiv.style.display = 'block';
        } else {
            quotaWarningDiv.style.display = 'none';
        }
    }

    eligibleMajorsCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            toggleMajorQuotaInput(this);
        });
        // Ensure initial state is correct on page load (especially for edit mode)
        //toggleMajorQuotaInput(checkbox); // Already handled by PHP for checked state and display style
    });

    document.querySelectorAll('.major-quota-input').forEach(input => {
        input.addEventListener('input', function() {
            if (this.value !== '') {
                this.parentElement.classList.add('is-filled');
            } else {
                 this.parentElement.classList.remove('is-filled');
            }
            validateMajorQuotas();
        });
        // Initial check for filled state if value exists on load (for edit mode)
        if (input.value !== '') {
             input.parentElement.classList.add('is-filled');
        }
    });

    if(maxParticipantsInput) {
        maxParticipantsInput.addEventListener('input', validateMajorQuotas);
    }
    // Initial validation on page load if editing or if there are pre-selected majors
    if (document.querySelector('.eligible-major-checkbox:checked')) {
        validateMajorQuotas();
    }

    // Handle form label animations for pre-filled fields on page load (Materialize specific)
    document.addEventListener('DOMContentLoaded', function() {
        const filledInputs = document.querySelectorAll('.form-control');
        filledInputs.forEach(input => {
            if (input.value !== '' && input.parentElement.classList.contains('input-group-outline')) {
                input.parentElement.classList.add('is-filled');
            }
            if(input.type === 'datetime-local' && input.value !== '' && input.parentElement.classList.contains('input-group-static')){
                 // For static labels, 'is-filled' is not usually needed to float label
                 // but helps if other styling depends on it.
            }
        });
         // Specific handling for major quota inputs if pre-filled
        document.querySelectorAll('.major-quota-input').forEach(input => {
            if (input.value !== '' && input.closest('.major-quota-input-group').style.display !== 'none') {
                 input.parentElement.classList.add('is-filled');
            }
        });
    });

</script>