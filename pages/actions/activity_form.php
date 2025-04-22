<?php
// ========================================================================
// ไฟล์: activity_form.php (เนื้อหาสำหรับ include)
// หน้าที่: ฟอร์มสำหรับเพิ่ม หรือ แก้ไข กิจกรรม (เพิ่มส่วนจัดการสาขา)
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php ($mysqli), และ Authorization check (Admin or Staff) ---
// --- ได้ทำไปแล้วในไฟล์ Controller หลัก ---
// require 'db_connect.php'; // $mysqli should be available
// if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 4])) { exit('Unauthorized'); }

$page_title = "เพิ่มกิจกรรมใหม่";
$form_action = "index.php?page=activity_form"; // Action ชี้ไปที่ Controller หลัก
$activity_id = null;
$activity_name = '';
$description = '';
$start_datetime = '';
$end_datetime = '';
$location = '';
$organizer_unit_id = '';
$hours_organizer = 0.0;
$hours_participant = 0.0;
$penalty_hours = 0.0;
$max_participants = '';
$message = ''; // Message จะถูก set โดย Controller หลักถ้ามี Error จาก POST
$is_edit_mode = false;
$selected_major_ids = []; // เก็บ ID ของสาขาที่ถูกเลือกไว้ (สำหรับ Edit mode)

// --- ดึงข้อมูลสำหรับ Dropdowns ---
// Activity Units
$units = [];
$sql_units = "SELECT id, name FROM activity_units ORDER BY name ASC";
$result_units = $mysqli->query($sql_units);
if ($result_units) {
    while ($row = $result_units->fetch_assoc()) {
        $units[] = $row;
    }
    $result_units->free();
}

// --- ดึงข้อมูลสาขาทั้งหมดสำหรับ Checkbox ---
$all_majors = [];
$sql_all_majors = "SELECT id, name FROM majors ORDER BY name ASC";
$result_all_majors = $mysqli->query($sql_all_majors);
if ($result_all_majors) {
    while ($row = $result_all_majors->fetch_assoc()) {
        $all_majors[] = $row;
    }
    $result_all_majors->free();
}


// --- Check if Editing ---
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $is_edit_mode = true;
    $activity_id = (int)$_GET['id'];
    $page_title = "แก้ไขกิจกรรม";
    $form_action = "index.php?page=activity_form&id=" . $activity_id;

    // --- Fetch existing activity data ---
    $sql_edit = "SELECT * FROM activities WHERE id = ?";
    if ($_SESSION['role_id'] == 4) {
        $sql_edit .= " AND created_by_user_id = ?";
    }
    $stmt_edit = $mysqli->prepare($sql_edit);

    if ($stmt_edit) {
        if ($_SESSION['role_id'] == 4) {
            $stmt_edit->bind_param('ii', $activity_id, $_SESSION['user_id']);
        } else {
            $stmt_edit->bind_param('i', $activity_id);
        }
        $stmt_edit->execute();
        $result_edit = $stmt_edit->get_result();

        if ($result_edit->num_rows === 1) {
            $activity_data = $result_edit->fetch_assoc();
            // ... (กำหนดค่าตัวแปรอื่นๆ เหมือนเดิม) ...
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

            // --- Fetch selected major IDs for this activity ---
            $sql_selected_majors = "SELECT major_id FROM activity_eligible_majors WHERE activity_id = ?";
            $stmt_selected = $mysqli->prepare($sql_selected_majors);
            if ($stmt_selected) {
                $stmt_selected->bind_param('i', $activity_id);
                $stmt_selected->execute();
                $result_selected = $stmt_selected->get_result();
                while ($row_selected = $result_selected->fetch_assoc()) {
                    $selected_major_ids[] = $row_selected['major_id'];
                }
                $stmt_selected->close();
            } else {
                $message .= '<p class="alert alert-warning text-white">เกิดข้อผิดพลาดในการดึงข้อมูลสาขาที่เลือก</p>';
            }
        } else {
            $_SESSION['form_message'] = '<p class="alert alert-danger text-white">ไม่พบข้อมูลกิจกรรมที่ต้องการแก้ไข หรือคุณไม่มีสิทธิ์แก้ไขกิจกรรมนี้</p>';
            header('Location: index.php?page=activities_list');
            exit;
        }
        $stmt_edit->close();
    } else {
        $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการเตรียมคำสั่งดึงข้อมูลกิจกรรม</p>';
    }
}

// --- Handle Form Submission (Add or Edit) ---
// *** ส่วนนี้จะทำงานเมื่อถูก include ใน Controller และ Controller ตรวจสอบว่าเป็น POST request สำหรับหน้านี้ ***
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // รับค่าจากฟอร์ม
    $activity_name = trim($_POST['activity_name']);
    $description = trim($_POST['description']);
    $start_datetime_input = trim($_POST['start_datetime']);
    $end_datetime_input = trim($_POST['end_datetime']);
    $location = trim($_POST['location']);
    $organizer_unit_id_input = filter_input(INPUT_POST, 'organizer_unit_id', FILTER_VALIDATE_INT);
    $hours_organizer_input = filter_input(INPUT_POST, 'hours_organizer', FILTER_VALIDATE_FLOAT);
    $hours_participant_input = filter_input(INPUT_POST, 'hours_participant', FILTER_VALIDATE_FLOAT);
    $penalty_hours_input = filter_input(INPUT_POST, 'penalty_hours', FILTER_VALIDATE_FLOAT);
    $max_participants_input = trim($_POST['max_participants']);
    $max_participants_save = ($max_participants_input === '' || !is_numeric($max_participants_input)) ? null : (int)$max_participants_input;
    // รับค่า Major IDs ที่เลือกจาก Checkbox (เป็น Array)
    $submitted_majors = $_POST['eligible_majors'] ?? [];


    // --- Validate Input ---
    $errors = [];
    if (empty($activity_name)) $errors[] = "กรุณากรอกชื่อกิจกรรม";
    if (empty($start_datetime_input)) $errors[] = "กรุณาระบุวันเวลาเริ่มต้น";
    if (empty($end_datetime_input)) $errors[] = "กรุณาระบุวันเวลาสิ้นสุด";
    if (empty($organizer_unit_id_input)) $errors[] = "กรุณาเลือกหน่วยงานผู้จัด";
    if ($hours_organizer_input === false || $hours_organizer_input < 0) $errors[] = "กรุณากรอกชั่วโมง (ผู้จัด) เป็นตัวเลขทศนิยม >= 0";
    if ($hours_participant_input === false || $hours_participant_input < 0) $errors[] = "กรุณากรอกชั่วโมง (ผู้เข้าร่วม) เป็นตัวเลขทศนิยม >= 0";
    if ($penalty_hours_input === false || $penalty_hours_input < 0) $errors[] = "กรุณากรอกชั่วโมง (หัก) เป็นตัวเลขทศนิยม >= 0";
    if (!is_null($max_participants_save) && $max_participants_save < 0) $errors[] = "จำนวนรับสูงสุดต้องเป็นตัวเลข >= 0 หรือเว้นว่าง";

    // Validate datetime logic
    $start_ts = strtotime($start_datetime_input);
    $end_ts = strtotime($end_datetime_input);
    $start_datetime_db = null;
    $end_datetime_db = null;

    if ($start_ts === false || $end_ts === false) {
        $errors[] = "รูปแบบวันเวลาไม่ถูกต้อง";
    } elseif ($end_ts < $start_ts) {
        $errors[] = "วันเวลาสิ้นสุดต้องอยู่หลังหรือตรงกับวันเวลาเริ่มต้น";
    } else {
        $start_datetime_db = date('Y-m-d H:i:s', $start_ts);
        $end_datetime_db = date('Y-m-d H:i:s', $end_ts);
    }

    // Validate submitted major IDs (ensure they are integers)
    $validated_major_ids = [];
    if (is_array($submitted_majors)) {
        foreach ($submitted_majors as $m_id) {
            if (filter_var($m_id, FILTER_VALIDATE_INT)) {
                $validated_major_ids[] = (int)$m_id;
            }
        }
    }


    if (!empty($errors)) {
        $message = '<p class="alert alert-danger text-white">' . implode('<br>', $errors) . '</p>';
        // กำหนดค่ากลับไปที่ตัวแปร global เพื่อให้ฟอร์มแสดงค่าเดิมที่กรอกผิด
        $activity_name = $_POST['activity_name'];
        $description = $_POST['description'];
        $start_datetime = $start_datetime_input;
        $end_datetime = $end_datetime_input;
        $location = $_POST['location'];
        $organizer_unit_id = $organizer_unit_id_input;
        $hours_organizer = $_POST['hours_organizer'];
        $hours_participant = $_POST['hours_participant'];
        $penalty_hours = $_POST['penalty_hours'];
        $max_participants = $_POST['max_participants'];
        $selected_major_ids = $validated_major_ids; // แสดง checkbox ที่เลือกผิดพลาดไว้

    } else {
        // --- Process Add or Edit ---
        $mysqli->begin_transaction(); // เริ่ม Transaction
        try {
            $current_activity_id = null; // สำหรับเก็บ ID ของกิจกรรมที่เพิ่ม/แก้ไข

            if ($is_edit_mode && $activity_id !== null) {
                // --- Update ---
                $can_edit = false;
                if ($_SESSION['role_id'] == 1) {
                    $can_edit = true;
                } else {
                    $sql_check_owner = "SELECT id FROM activities WHERE id = ? AND created_by_user_id = ?";
                    $stmt_check_owner = $mysqli->prepare($sql_check_owner);
                    $stmt_check_owner->bind_param('ii', $activity_id, $_SESSION['user_id']);
                    $stmt_check_owner->execute();
                    if ($stmt_check_owner->get_result()->num_rows === 1) {
                        $can_edit = true;
                    }
                    $stmt_check_owner->close();
                }

                if (!$can_edit) {
                    throw new Exception("คุณไม่มีสิทธิ์แก้ไขกิจกรรมนี้");
                }

                $sql = "UPDATE activities SET
                            name = ?, description = ?, start_datetime = ?, end_datetime = ?, location = ?,
                            organizer_unit_id = ?, hours_organizer = ?, hours_participant = ?,
                            penalty_hours = ?, max_participants = ?
                        WHERE id = ?";
                $stmt = $mysqli->prepare($sql);
                if (!$stmt) throw new Exception("Prepare Update Error: " . $mysqli->error);

                $stmt->bind_param(
                    'sssssidddii',
                    $activity_name,
                    $description,
                    $start_datetime_db,
                    $end_datetime_db,
                    $location,
                    $organizer_unit_id_input,
                    $hours_organizer_input,
                    $hours_participant_input,
                    $penalty_hours_input,
                    $max_participants_save,
                    $activity_id
                );

                if (!$stmt->execute()) throw new Exception("Execute Update Error: " . $stmt->error);
                $stmt->close();
                $current_activity_id = $activity_id; // ใช้ ID เดิมสำหรับจัดการ Major
                $_SESSION['form_message'] = '<p class="alert alert-success text-white">แก้ไขข้อมูลกิจกรรมสำเร็จแล้ว</p>';
            } else {
                // --- Insert ---
                $sql = "INSERT INTO activities (name, description, start_datetime, end_datetime, location,
                            organizer_unit_id, hours_organizer, hours_participant, penalty_hours, max_participants, created_by_user_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $mysqli->prepare($sql);
                if (!$stmt) throw new Exception("Prepare Insert Error: " . $mysqli->error);

                $created_by = $_SESSION['user_id'];
                $stmt->bind_param(
                    'sssssidddii',
                    $activity_name,
                    $description,
                    $start_datetime_db,
                    $end_datetime_db,
                    $location,
                    $organizer_unit_id_input,
                    $hours_organizer_input,
                    $hours_participant_input,
                    $penalty_hours_input,
                    $max_participants_save,
                    $created_by
                );
                if (!$stmt->execute()) throw new Exception("Execute Insert Error: " . $stmt->error);
                $current_activity_id = $mysqli->insert_id; // ใช้ ID ใหม่สำหรับจัดการ Major
                $stmt->close();
                // ไม่ต้องตั้ง message เพราะจะ redirect ทันที
            }

            // --- Synchronize Eligible Majors (Delete old, Insert new) ---
            if ($current_activity_id) {
                // 1. Delete existing entries for this activity
                $sql_delete_majors = "DELETE FROM activity_eligible_majors WHERE activity_id = ?";
                $stmt_delete = $mysqli->prepare($sql_delete_majors);
                if (!$stmt_delete) throw new Exception("Prepare Delete Majors Error: " . $mysqli->error);
                $stmt_delete->bind_param('i', $current_activity_id);
                if (!$stmt_delete->execute()) throw new Exception("Execute Delete Majors Error: " . $stmt_delete->error);
                $stmt_delete->close();

                // 2. Insert new selected entries
                if (!empty($validated_major_ids)) {
                    $sql_insert_major = "INSERT INTO activity_eligible_majors (activity_id, major_id) VALUES (?, ?)";
                    $stmt_insert = $mysqli->prepare($sql_insert_major);
                    if (!$stmt_insert) throw new Exception("Prepare Insert Majors Error: " . $mysqli->error);

                    foreach ($validated_major_ids as $m_id) {
                        $stmt_insert->bind_param('ii', $current_activity_id, $m_id);
                        if (!$stmt_insert->execute()) {
                            // อาจจะแค่ Log error หรือ throw exception ต่อ
                            error_log("Error inserting major ID $m_id for activity ID $current_activity_id: " . $stmt_insert->error);
                        }
                    }
                    $stmt_insert->close();
                }
            }

            // --- Commit Transaction ---
            $mysqli->commit();

            // --- Redirect ---
            header('Location: index.php?page=activities_list');
            exit;
        } catch (Exception $e) {
            $mysqli->rollback(); // Rollback transaction on error
            $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage() . '</p>';
            // กำหนดค่ากลับไปที่ตัวแปร global เพื่อให้ฟอร์มแสดงค่าเดิมที่กรอกผิด
            $activity_name = $_POST['activity_name'];
            $description = $_POST['description'];
            $start_datetime = $start_datetime_input;
            $end_datetime = $end_datetime_input;
            $location = $_POST['location'];
            $organizer_unit_id = $organizer_unit_id_input;
            $hours_organizer = $_POST['hours_organizer'];
            $hours_participant = $_POST['hours_participant'];
            $penalty_hours = $_POST['penalty_hours'];
            $max_participants = $_POST['max_participants'];
            $selected_major_ids = $validated_major_ids; // แสดง checkbox ที่เลือกผิดพลาดไว้
        }
    }
}


// --- Display message from Controller (if any validation error occurred) ---
// $message variable should be set by the controller if there were errors during POST processing

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
                            <button type="button" class="btn-close p-3" data-bs-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <form role="form" class="text-start" action="<?php echo $form_action; ?>" method="post">
                        <div class="input-group input-group-outline my-3 <?php echo !empty($activity_name) ? 'is-filled' : ''; ?>">
                            <label class="form-label">ชื่อกิจกรรม</label>
                            <input type="text" id="activity_name" name="activity_name" class="form-control" value="<?php echo htmlspecialchars($activity_name); ?>" required>
                        </div>

                        <div class="input-group input-group-outline my-3 <?php echo !empty($description) ? 'is-filled' : ''; ?>">
                            <label class="form-label">รายละเอียดกิจกรรม</label>
                            <textarea id="description" name="description" class="form-control" rows="3"><?php echo htmlspecialchars($description); ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="input-group input-group-static my-3">
                                    <label>วันเวลาเริ่มต้น</label>
                                    <input type="datetime-local" id="start_datetime" name="start_datetime" class="form-control" value="<?php echo htmlspecialchars($start_datetime); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="input-group input-group-static my-3">
                                    <label>วันเวลาสิ้นสุด</label>
                                    <input type="datetime-local" id="end_datetime" name="end_datetime" class="form-control" value="<?php echo htmlspecialchars($end_datetime); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="input-group input-group-outline my-3 <?php echo !empty($location) ? 'is-filled' : ''; ?>">
                            <label class="form-label">สถานที่จัด</label>
                            <input type="text" id="location" name="location" class="form-control" value="<?php echo htmlspecialchars($location); ?>">
                        </div>

                        <div class="input-group input-group-static mb-4">
                            <label for="organizer_unit_id" class="ms-0">หน่วยงานผู้จัด</label>
                            <select class="form-control" id="organizer_unit_id" name="organizer_unit_id" required>
                                <option value="">-- เลือกหน่วยงาน --</option>
                                <?php foreach ($units as $unit): ?>
                                    <option value="<?php echo $unit['id']; ?>" <?php echo ($organizer_unit_id == $unit['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($unit['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <hr class="dark horizontal my-3">
                        <p class="text-sm font-weight-bold">จำนวนชั่วโมง</p>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="input-group input-group-outline my-3 <?php echo ($hours_organizer > 0 || $hours_organizer === 0.0 || (isset($_POST['hours_organizer']) && $_POST['hours_organizer'] != '')) ? 'is-filled' : ''; ?>">
                                    <label class="form-label">สำหรับผู้จัด</label>
                                    <input type="number" step="0.1" min="0" id="hours_organizer" name="hours_organizer" class="form-control" value="<?php echo htmlspecialchars($hours_organizer); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="input-group input-group-outline my-3 <?php echo ($hours_participant > 0 || $hours_participant === 0.0 || (isset($_POST['hours_participant']) && $_POST['hours_participant'] != '')) ? 'is-filled' : ''; ?>">
                                    <label class="form-label">สำหรับผู้เข้าร่วม</label>
                                    <input type="number" step="0.1" min="0" id="hours_participant" name="hours_participant" class="form-control" value="<?php echo htmlspecialchars($hours_participant); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="input-group input-group-outline my-3 <?php echo ($penalty_hours > 0 || $penalty_hours === 0.0 || (isset($_POST['penalty_hours']) && $_POST['penalty_hours'] != '')) ? 'is-filled' : ''; ?>">
                                    <label class="form-label">ชั่วโมงหัก (กรณีไม่เข้าร่วม)</label>
                                    <input type="number" step="0.1" min="0" id="penalty_hours" name="penalty_hours" class="form-control" value="<?php echo htmlspecialchars($penalty_hours); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="input-group input-group-outline my-3 <?php echo (!is_null($max_participants) && $max_participants !== '') ? 'is-filled' : ''; ?>">
                            <label class="form-label">จำนวนรับสูงสุด (เว้นว่าง = ไม่จำกัด)</label>
                            <input type="number" min="0" id="max_participants" name="max_participants" class="form-control" value="<?php echo htmlspecialchars($max_participants ?? ''); ?>">
                        </div>

                        <hr class="dark horizontal my-3">
                        <p class="text-sm font-weight-bold">สาขาวิชาที่เข้าร่วมได้ (เลือกได้มากกว่า 1 สาขา หรือไม่เลือกเลยถ้าเปิดรับทุกสาขา)</p>
                        <div class="row px-1">
                            <?php if (!empty($all_majors)): ?>
                                <?php foreach ($all_majors as $major): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox"
                                                name="eligible_majors[]"
                                                value="<?php echo $major['id']; ?>"
                                                id="major_<?php echo $major['id']; ?>"
                                                <?php echo (in_array($major['id'], $selected_major_ids)) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="major_<?php echo $major['id']; ?>">
                                                <?php echo htmlspecialchars($major['name']); ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-xs text-warning">ไม่พบข้อมูลสาขาวิชา กรุณาเพิ่มข้อมูลสาขาวิชาก่อน</p>
                            <?php endif; ?>
                        </div>
                        <div class="text-center">
                            <button type="submit" class="btn bg-gradient-primary w-100 my-4 mb-2"><?php echo $is_edit_mode ? 'บันทึกการแก้ไข' : 'เพิ่มกิจกรรม'; ?></button>
                            <a href="index.php?page=activities_list" class="btn btn-outline-secondary w-100 mb-0">ยกเลิก</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>