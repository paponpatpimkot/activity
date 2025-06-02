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

$message = $message ?? '';
$is_edit_mode = false;

// --- ดึงข้อมูลสำหรับ Dropdowns และ Checkboxes ---
$units = [];
$sql_units = "SELECT id, name FROM activity_units ORDER BY name ASC";
$result_units = $mysqli->query($sql_units);
if ($result_units) { while ($row = $result_units->fetch_assoc()) { $units[] = $row; } $result_units->free(); }

$all_majors_for_form = []; // เปลี่ยนชื่อตัวแปร
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
    if ($_SESSION['role_id'] == 4) { $sql_edit .= " AND created_by_user_id = ?"; }
    $stmt_edit = $mysqli->prepare($sql_edit);

    if ($stmt_edit) {
        if ($_SESSION['role_id'] == 4) { $stmt_edit->bind_param('ii', $activity_id, $_SESSION['user_id']); }
        else { $stmt_edit->bind_param('i', $activity_id); }
        $stmt_edit->execute();
        $result_edit = $stmt_edit->get_result();

        if ($result_edit->num_rows === 1) {
            $activity_data = $result_edit->fetch_assoc();
            $activity_name = $activity_data['name'];
            $description = $activity_data['description'];
            $start_datetime = !empty($activity_data['start_datetime']) ? date('Y-m-d\TH:i', strtotime($activity_data['start_datetime'])) : '';
            $end_datetime = !empty($activity_data['end_datetime']) ? date('Y-m-d\TH:i', strtotime($activity_data['end_datetime'])) : '';
            $location = $activity_data['location'];
            $organizer_unit_id = $activity_data['organizer_unit_id'];
            $hours_organizer = (float)$activity_data['hours_organizer'];
            $hours_participant = (float)$activity_data['hours_participant'];
            $penalty_hours = (float)$activity_data['penalty_hours'];
            $max_participants = $activity_data['max_participants'];
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
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') { // Repopulate form on POST error
    $activity_name = $_POST['activity_name'] ?? ''; $description = $_POST['description'] ?? '';
    $start_datetime = $_POST['start_datetime'] ?? ''; $end_datetime = $_POST['end_datetime'] ?? '';
    $location = $_POST['location'] ?? ''; $organizer_unit_id = $_POST['organizer_unit_id'] ?? '';
    $hours_organizer = $_POST['hours_organizer'] ?? 0.0; $hours_participant = $_POST['hours_participant'] ?? 0.0;
    $penalty_hours = $_POST['penalty_hours'] ?? 0.0; $max_participants = $_POST['max_participants'] ?? '';
    $attendance_recorder_type = $_POST['attendance_recorder_type'] ?? 'system';
    $selected_staff_recorder_ids = $_POST['specific_recorders'] ?? [];
    if (!empty($selected_staff_recorder_ids)) {
        // ... (โค้ด refetch staff data เหมือนเดิม) ...
    }
    // *** Repopulate selected eligible majors on POST error ***
    $submitted_eligible_majors = $_POST['eligible_majors'] ?? []; // array of major_ids
    $submitted_major_quotas = $_POST['major_participants'] ?? []; // array of [major_id => quota]
    foreach($submitted_eligible_majors as $m_id) {
        $selected_eligible_majors[$m_id] = $submitted_major_quotas[$m_id] ?? null;
    }
}

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... (รับค่าฟอร์มอื่นๆ เหมือนเดิม) ...
    $activity_name = trim($_POST['activity_name']); $description = trim($_POST['description']);
    $start_datetime_input = trim($_POST['start_datetime']); $end_datetime_input = trim($_POST['end_datetime']);
    $location = trim($_POST['location']);
    $organizer_unit_id_input = filter_input(INPUT_POST, 'organizer_unit_id', FILTER_VALIDATE_INT);
    $hours_organizer_input = filter_input(INPUT_POST, 'hours_organizer', FILTER_VALIDATE_FLOAT);
    $hours_participant_input = filter_input(INPUT_POST, 'hours_participant', FILTER_VALIDATE_FLOAT);
    $penalty_hours_input = filter_input(INPUT_POST, 'penalty_hours', FILTER_VALIDATE_FLOAT);
    $max_participants_input = trim($_POST['max_participants']);
    $max_participants_save = ($max_participants_input === '' || !is_numeric($max_participants_input)) ? null : (int)$max_participants_input;
    $attendance_recorder_type_input = $_POST['attendance_recorder_type'] ?? 'system';
    $submitted_staff_ids = $_POST['specific_recorders'] ?? [];

    // *** รับค่า Eligible Majors และ Quotas ***
    $submitted_eligible_majors = $_POST['eligible_majors'] ?? []; // Array of selected major IDs
    $submitted_major_quotas = $_POST['major_participants'] ?? []; // Associative array [major_id => quota]

    $errors = [];
    // ... (Validation เดิม) ...
    if (empty($activity_name)) $errors[] = "กรุณากรอกชื่อกิจกรรม";
    // ... (validation อื่นๆ) ...

    // --- Validate Eligible Majors and Quotas ---
    $validated_eligible_majors_data = []; // จะเก็บ [major_id => quota (null or int)]
    $total_major_quotas = 0;
    if (!empty($submitted_eligible_majors) && is_array($submitted_eligible_majors)) {
        foreach ($submitted_eligible_majors as $m_id) {
            if (!filter_var($m_id, FILTER_VALIDATE_INT)) {
                $errors[] = "รหัสสาขาที่เลือกไม่ถูกต้อง";
                break;
            }
            $quota_input = $submitted_major_quotas[$m_id] ?? '';
            $quota_for_major = null;
            if ($quota_input !== '') { // ถ้ามีการกรอกโควต้า
                if (!is_numeric($quota_input) || (int)$quota_input < 0) {
                    $errors[] = "จำนวนรับสำหรับสาขา " . htmlspecialchars($m_id) . " ต้องเป็นตัวเลขไม่ติดลบ";
                } else {
                    $quota_for_major = (int)$quota_input;
                    $total_major_quotas += $quota_for_major;
                }
            }
            $validated_eligible_majors_data[(int)$m_id] = $quota_for_major;
        }
    }

    // ตรวจสอบว่าผลรวมโควต้าสาขาไม่เกินโควต้ารวมของกิจกรรม (ถ้ามีการกำหนดโควต้ารวม)
    if (!is_null($max_participants_save) && $total_major_quotas > $max_participants_save) {
        $errors[] = "ผลรวมจำนวนรับของแต่ละสาขา ($total_major_quotas) เกินจำนวนรับสูงสุดของกิจกรรม ($max_participants_save)";
    }


    if (!empty($errors)) {
        $message = '<p class="alert alert-danger text-white">' . implode('<br>', $errors) . '</p>';
        // Repopulate selected majors and quotas
        $selected_eligible_majors = $validated_eligible_majors_data;
    } else {
        $mysqli->begin_transaction();
        try {
            $target_activity_id = null;
            if ($is_edit_mode && $activity_id !== null) {
                // ... (โค้ด UPDATE activities เหมือนเดิม) ...
                $can_edit = ($_SESSION['role_id'] == 1);
                if ($_SESSION['role_id'] == 4) { /* Check owner */ }
                if(!$can_edit) throw new Exception("คุณไม่มีสิทธิ์แก้ไขกิจกรรมนี้");
                $sql = "UPDATE activities SET name = ?, description = ?, start_datetime = ?, end_datetime = ?, location = ?, organizer_unit_id = ?, hours_organizer = ?, hours_participant = ?, penalty_hours = ?, max_participants = ?, attendance_recorder_type = ? WHERE id = ?";
                $stmt = $mysqli->prepare($sql); if (!$stmt) throw new Exception("Prepare Update Error: " . $mysqli->error);
                $stmt->bind_param('sssssidddisi', $activity_name, $description, $start_datetime_db, $end_datetime_db, $location, $organizer_unit_id_input, $hours_organizer_input, $hours_participant_input, $penalty_hours_input, $max_participants_save, $attendance_recorder_type_input, $activity_id);
                if (!$stmt->execute()) throw new Exception("Execute Update Error: " . $stmt->error); $stmt->close();
                $target_activity_id = $activity_id;
                $_SESSION['form_message'] = '<p class="alert alert-success text-white">แก้ไขข้อมูลกิจกรรมสำเร็จแล้ว</p>';
            } else { // Insert mode
                // ... (โค้ด INSERT activities เหมือนเดิม) ...
                $sql = "INSERT INTO activities (name, description, start_datetime, end_datetime, location, organizer_unit_id, hours_organizer, hours_participant, penalty_hours, max_participants, created_by_user_id, attendance_recorder_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $mysqli->prepare($sql); if (!$stmt) throw new Exception("Prepare Insert Error: " . $mysqli->error);
                $created_by = $_SESSION['user_id'];
                $stmt->bind_param('sssssidddiis', $activity_name, $description, $start_datetime_db, $end_datetime_db, $location, $organizer_unit_id_input, $hours_organizer_input, $hours_participant_input, $penalty_hours_input, $max_participants_save, $created_by, $attendance_recorder_type_input);
                if (!$stmt->execute()) throw new Exception("Execute Insert Error: " . $stmt->error); $target_activity_id = $mysqli->insert_id; $stmt->close();
            }

            // --- Synchronize Specific Staff Recorders (เหมือนเดิม) ---
            if ($target_activity_id && $attendance_recorder_type_input === 'system') {
                // ... (โค้ดจัดการ activity_specific_recorders) ...
            } elseif ($target_activity_id && $attendance_recorder_type_input === 'advisor') {
                // ... (โค้ดลบ activity_specific_recorders) ...
            }

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
                        // Bind quota as integer or NULL
                        $stmt_insert_major->bind_param('iis', $target_activity_id, $m_id, $quota); // ใช้ 's' สำหรับ quota ถ้าอาจเป็น NULL
                        if (!$stmt_insert_major->execute()) {
                            error_log("Error inserting eligible major ID $m_id for activity ID $target_activity_id: " . $stmt_insert_major->error);
                        }
                    }
                    $stmt_insert_major->close();
                }
            }
            // *** End Synchronize Eligible Majors ***

            $mysqli->commit();
            if (!$is_edit_mode) { $_SESSION['form_message'] = '<p class="alert alert-success text-white">เพิ่มกิจกรรมใหม่สำเร็จแล้ว</p>';}
            header('Location: index.php?page=activities_list'); exit;
        } catch (Exception $e) {
            $mysqli->rollback();
            $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage() . '</p>';
            // Repopulate selected majors and quotas on error
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
                        <h6 class="text-white text-capitalize ps-3"><?php echo $page_title; ?></h6>
                    </div>
                </div>
                <div class="card-body">
                     <?php if (!empty($message)) : ?>
                        <div class="alert alert-dismissible text-white fade show <?php echo (strpos($message, 'success') !== false || strpos($message, 'สำเร็จ') !== false) ? 'alert-success bg-gradient-success' : ((strpos($message, 'warning') !== false || strpos($message, 'เตือน') !== false) ? 'alert-warning bg-gradient-warning' : 'alert-danger bg-gradient-danger'); ?>" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close p-3" data-bs-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                    <?php endif; ?>

                    <form role="form" class="text-start" action="<?php echo $form_action; ?>" method="post" id="activityForm">
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
                             <div class="col-md-4"><div class="input-group input-group-outline my-3 <?php echo ($hours_organizer > 0 || $hours_organizer === 0.0 || (isset($_POST['hours_organizer']) && $_POST['hours_organizer'] != '')) ? 'is-filled' : ''; ?>"><label class="form-label">สำหรับผู้จัด</label><input type="number" step="0.1" min="0" id="hours_organizer" name="hours_organizer" class="form-control" value="<?php echo htmlspecialchars($hours_organizer); ?>" required></div></div>
                              <div class="col-md-4"><div class="input-group input-group-outline my-3 <?php echo ($hours_participant > 0 || $hours_participant === 0.0 || (isset($_POST['hours_participant']) && $_POST['hours_participant'] != '')) ? 'is-filled' : ''; ?>"><label class="form-label">สำหรับผู้เข้าร่วม</label><input type="number" step="0.1" min="0" id="hours_participant" name="hours_participant" class="form-control" value="<?php echo htmlspecialchars($hours_participant); ?>" required></div></div>
                              <div class="col-md-4"><div class="input-group input-group-outline my-3 <?php echo ($penalty_hours > 0 || $penalty_hours === 0.0 || (isset($_POST['penalty_hours']) && $_POST['penalty_hours'] != '')) ? 'is-filled' : ''; ?>"><label class="form-label">ชั่วโมงหัก (กรณีไม่เข้าร่วม)</label><input type="number" step="0.1" min="0" id="penalty_hours" name="penalty_hours" class="form-control" value="<?php echo htmlspecialchars($penalty_hours); ?>" required></div></div>
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
                                     <span class="badge bg-gradient-secondary me-1 mb-1" data-staff-id="<?php echo $staff['staff_user_id']; ?>"><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?><i class="material-symbols-rounded text-sm cursor-pointer ms-1" onclick="removeStaffRecorder(this, <?php echo $staff['staff_user_id']; ?>)">close</i><input type="hidden" name="specific_recorders[]" value="<?php echo $staff['staff_user_id']; ?>"></span>
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
                                        <div class="input-group input-group-outline mt-1 major-quota-input-group" style="<?php echo $is_checked ? '' : 'display:none;'; ?>">
                                            <label class="form-label"><small> จำนวนรับสาขานี้ หากไม่ระบุหมายถึงไม่จำกัด</small></label>
                                            <input type="number" min="0"
                                                   name="major_participants[<?php echo $major_id_val; ?>]"
                                                   class="form-control major-quota-input"
                                                   value="<?php echo htmlspecialchars($quota_val); ?>"
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
                </div> </div> </div> </div> </div>
<script>
    // JavaScript to show/hide specific staff recorder section
    const recorderTypeRadios = document.querySelectorAll('input[name="attendance_recorder_type"]');
    const specificStaffContainer = document.getElementById('specific_staff_recorder_container');

    function toggleSpecificStaffContainer() {
        if (document.getElementById('recorder_system').checked) {
            specificStaffContainer.style.display = 'block';
        } else {
            specificStaffContainer.style.display = 'none';
            document.getElementById('selected-staff-recorders').innerHTML = '';
            const staffSearchInput = document.getElementById('staff-recorder-search');
            if(staffSearchInput) staffSearchInput.value = '';
            document.getElementById('staff-recorder-search-results').innerHTML = '';
        }
    }
    recorderTypeRadios.forEach(radio => {
        radio.addEventListener('change', toggleSpecificStaffContainer);
    });
    toggleSpecificStaffContainer();

    // JavaScript for Staff Recorder Search
    const staffSearchInput = document.getElementById('staff-recorder-search');
    const staffSearchResultsContainer = document.getElementById('staff-recorder-search-results');
    const selectedStaffContainer = document.getElementById('selected-staff-recorders');
    let staffSearchTimeout;

    function addStaffRecorder(id, name) {
        if (selectedStaffContainer.querySelector(`input[name="specific_recorders[]"][value="${id}"]`)) {
            staffSearchInput.value = ''; staffSearchResultsContainer.innerHTML = ''; return;
        }
        const badge = document.createElement('span');
        badge.className = 'badge bg-gradient-secondary me-1 mb-1'; badge.dataset.staffId = id;
        badge.innerHTML = `${name} <i class="material-symbols-rounded text-sm cursor-pointer ms-1" onclick="removeStaffRecorder(this, ${id})">close</i> <input type="hidden" name="specific_recorders[]" value="${id}">`;
        selectedStaffContainer.appendChild(badge);
        staffSearchInput.value = ''; staffSearchResultsContainer.innerHTML = '';
    }

    function removeStaffRecorder(element, id) {
        const badge = element.closest('.badge'); if (badge) { badge.remove(); }
    }

    if(staffSearchInput) {
        staffSearchInput.addEventListener('keyup', function() {
            clearTimeout(staffSearchTimeout); const searchTerm = this.value.trim();
            if (searchTerm.length < 1) { staffSearchResultsContainer.innerHTML = ''; return; }
            staffSearchTimeout = setTimeout(() => {
                fetch(`../actions/search_staff.php?term=${encodeURIComponent(searchTerm)}`) // ปรับ Path ให้ถูกต้อง
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
    }
    document.addEventListener('click', function(event) {
        if (staffSearchInput && !staffSearchInput.contains(event.target) && staffSearchResultsContainer && !staffSearchResultsContainer.contains(event.target)) {
            staffSearchResultsContainer.innerHTML = '';
        }
    });

    // *** JavaScript for Eligible Majors Quota ***
    const eligibleMajorsCheckboxes = document.querySelectorAll('.eligible-major-checkbox');
    const maxParticipantsInput = document.getElementById('max_participants');
    const quotaWarningDiv = document.getElementById('quota-warning');

    function toggleMajorQuotaInput(checkbox) {
        const quotaInputGroup = checkbox.closest('.col-md-6, .col-lg-4').querySelector('.major-quota-input-group');
        if (quotaInputGroup) {
            quotaInputGroup.style.display = checkbox.checked ? 'block' : 'none';
            if (!checkbox.checked) {
                const input = quotaInputGroup.querySelector('.major-quota-input');
                if (input) input.value = ''; // Clear quota if unchecked
            }
        }
        validateMajorQuotas();
    }

    function validateMajorQuotas() {
        let totalMajorQuota = 0;
        let hasSpecificQuota = false;
        document.querySelectorAll('.eligible-major-checkbox:checked').forEach(checkbox => {
            const quotaInputGroup = checkbox.closest('.col-md-6, .col-lg-4').querySelector('.major-quota-input-group');
            const input = quotaInputGroup.querySelector('.major-quota-input');
            if (input && input.value !== '') {
                const quota = parseInt(input.value, 10);
                if (!isNaN(quota) && quota >= 0) {
                    totalMajorQuota += quota;
                    hasSpecificQuota = true;
                } else if (input.value !== '') { // If input is not empty but not a valid number
                     quotaWarningDiv.textContent = 'จำนวนรับของสาขาต้องเป็นตัวเลขไม่ติดลบ';
                     quotaWarningDiv.style.display = 'block';
                     return; // Stop further validation if one input is invalid
                }
            }
        });

        const overallMax = maxParticipantsInput.value !== '' ? parseInt(maxParticipantsInput.value, 10) : null;

        if (hasSpecificQuota && overallMax !== null && totalMajorQuota > overallMax) {
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
        // Initial state
        // toggleMajorQuotaInput(checkbox); // No need, handled by PHP value now
    });

    document.querySelectorAll('.major-quota-input').forEach(input => {
        input.addEventListener('input', validateMajorQuotas);
    });
    if(maxParticipantsInput) {
        maxParticipantsInput.addEventListener('input', validateMajorQuotas);
    }
     // Initial validation on page load if editing
    if (document.querySelector('.eligible-major-checkbox:checked')) {
        validateMajorQuotas();
    }

</script>
