<?php
// ========================================================================
// ไฟล์: advisor_attendance_record.php (เนื้อหาสำหรับ include)
// หน้าที่: แสดงรายชื่อนักศึกษาในที่ปรึกษาที่จองกิจกรรม และบันทึกสถานะการเข้าร่วม (มี Nav Pills)
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php ($mysqli), Authorization check (Role Advisor) ---
// --- และ require_once 'includes/functions.php' ได้ทำไปแล้วใน Controller หลัก ---

// if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) { exit('Unauthorized'); }

$advisor_user_id = $_SESSION['user_id']; // ID ของ Advisor ที่ Login อยู่
$page_title = "บันทึกการเข้าร่วมกิจกรรม (Advisor)";
$message_record = ''; // ใช้ชื่อตัวแปรไม่ซ้ำกับหน้า select
$activity_id_record = null;
$activity_info_record = null;
$advisees_by_group = []; // เก็บนักศึกษาในที่ปรึกษา แยกตามกลุ่ม
$attendance_data_record = [];
$can_checkin = false; // ตรวจสอบสิทธิ์เช็คชื่อ

// --- Get Activity ID from URL ---
if (isset($_GET['activity_id']) && is_numeric($_GET['activity_id'])) {
    $activity_id_record = (int)$_GET['activity_id'];
} else {
    $_SESSION['form_message'] = '<p class="alert alert-warning text-white">กรุณาเลือกกิจกรรม</p>';
    header('Location: index.php?page=advisor_attendance_select_activity');
    exit;
}

// --- Fetch Activity Info และตรวจสอบสิทธิ์การเช็คชื่อของ Advisor ---
// *** แก้ไข SQL: เอา allow_late_checkin_days ออกจาก SELECT list ก่อน ***
$sql_act_info = "SELECT id, name, start_datetime, hours_participant, penalty_hours, attendance_recorder_type, end_datetime
                 FROM activities WHERE id = ?";
$stmt_act_info = $mysqli->prepare($sql_act_info);
if ($stmt_act_info) {
    $stmt_act_info->bind_param('i', $activity_id_record);
    $stmt_act_info->execute();
    $result_act_info = $stmt_act_info->get_result();
    if ($result_act_info->num_rows === 1) {
        $activity_info_record = $result_act_info->fetch_assoc();
        $page_title .= ' - ' . htmlspecialchars($activity_info_record['name']);

        if ($activity_info_record['attendance_recorder_type'] !== 'advisor') {
            $_SESSION['form_message'] = '<p class="alert alert-danger text-white">กิจกรรมนี้ไม่ได้กำหนดให้ครูที่ปรึกษาเป็นผู้เช็คชื่อ</p>';
            header('Location: index.php?page=advisor_attendance_select_activity');
            exit;
        }
        // *** แก้ไข: ตรวจสอบช่วงเวลาเช็คชื่อแบบง่ายไปก่อน (ไม่มี allow_late_checkin_days) ***
        $end_dt = new DateTime($activity_info_record['end_datetime']);
        // $allowed_checkin_days = $activity_info_record['allow_late_checkin_days'] ?? 0; // Comment out
        $last_checkin_date = clone $end_dt;
        // $last_checkin_date->modify("+" . $allowed_checkin_days . " day"); // Comment out
        $last_checkin_date->modify("+1 day"); // Default ให้เช็คได้ถึง 1 วันหลังจบกิจกรรม (ตัวอย่าง)
        $last_checkin_date->setTime(23, 59, 59);

        $now = new DateTime();
        $start_dt_for_check = new DateTime($activity_info_record['start_datetime']);

        if ($now >= $start_dt_for_check && $now <= $last_checkin_date) {
            $can_checkin = true;
        } else {
            // $_SESSION['form_message'] = '<p class="alert alert-warning text-white">ไม่สามารถเช็คชื่อได้: กิจกรรมยังไม่เริ่ม หรือ เลยกำหนดเวลาเช็คชื่อแล้ว</p>';
            // ยังไม่ redirect แต่จะ disable ปุ่ม submit แทน
            $message_record = '<p class="alert alert-warning text-white">ไม่สามารถบันทึกการเช็คชื่อได้: กิจกรรมยังไม่เริ่ม หรือ เลยกำหนดเวลาเช็คชื่อแล้ว</p>';
        }

    } else {
         $_SESSION['form_message'] = '<p class="alert alert-danger text-white">ไม่พบข้อมูลกิจกรรมที่ระบุ</p>';
         header('Location: index.php?page=advisor_attendance_select_activity');
         exit;
    }
    $stmt_act_info->close();
} else {
    $message_record .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงข้อมูลกิจกรรม: ' . htmlspecialchars($mysqli->error) . '</p>';
}


// --- Handle Form Submission (Save Attendance) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activity_id']) && $_POST['activity_id'] == $activity_id_record && isset($_POST['attendance_status']) && $activity_info_record && $can_checkin) {
    $attendance_statuses = $_POST['attendance_status'];
    $recorded_by = $advisor_user_id;
    $errors_saving = [];
    $success_count = 0;

    $mysqli->begin_transaction();
    try {
        $sql_upsert = "INSERT INTO activity_attendance (student_user_id, activity_id, attendance_status, hours_earned, recorded_by_user_id, created_at, updated_at)
                       VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                       ON DUPLICATE KEY UPDATE
                       attendance_status = VALUES(attendance_status), hours_earned = VALUES(hours_earned),
                       recorded_by_user_id = VALUES(recorded_by_user_id), updated_at = NOW()";
        $stmt_upsert = $mysqli->prepare($sql_upsert);
        if (!$stmt_upsert) throw new Exception("Prepare statement failed: " . $mysqli->error);

        foreach ($attendance_statuses as $student_user_id_from_form => $status_array) {
            $status = '';
            if(is_array($status_array)){ foreach($status_array as $s_val){ if(!empty($s_val)){ $status = $s_val; break; }}}
            else { $status = $status_array; }

            $student_user_id_from_form = (int)$student_user_id_from_form;
            $status = trim($status);
            $valid_statuses = ['attended', 'absent'];
            if (!in_array($status, $valid_statuses)) { $status = 'absent'; }

            $sql_verify_advisee = "SELECT s.user_id FROM students s
                                   JOIN student_groups sg ON s.group_id = sg.id
                                   JOIN group_advisors ga ON sg.id = ga.group_id
                                   JOIN activity_bookings ab ON s.user_id = ab.student_user_id
                                   WHERE s.user_id = ? AND ga.advisor_user_id = ? AND ab.activity_id = ? AND ab.status = 'booked'";
            $stmt_verify_advisee = $mysqli->prepare($sql_verify_advisee);
            $stmt_verify_advisee->bind_param('iii', $student_user_id_from_form, $advisor_user_id, $activity_id_record);
            $stmt_verify_advisee->execute();
            $is_valid_advisee_booking = $stmt_verify_advisee->get_result()->num_rows > 0;
            $stmt_verify_advisee->close();

            if (!$is_valid_advisee_booking) {
                $errors_saving[] = "User ID $student_user_id_from_form: ไม่ใช่นักศึกษาในที่ปรึกษา หรือ ไม่ได้จองกิจกรรมนี้";
                continue;
            }

            $hours_earned = 0.0;
            if ($status === 'attended') { $hours_earned = floatval($activity_info_record['hours_participant'] ?? 0.0); }
            elseif ($status === 'absent') { $penalty = $activity_info_record['penalty_hours'] ?? 0.0; $hours_earned = -abs(floatval($penalty)); }

            $stmt_upsert->bind_param("iisdi", $student_user_id_from_form, $activity_id_record, $status, $hours_earned, $recorded_by);
            if ($stmt_upsert->execute()) { $success_count++; }
            else { $errors_saving[] = "User ID $student_user_id_from_form: " . $stmt_upsert->error; }
        }
        $stmt_upsert->close();

        if (empty($errors_saving)) {
            $mysqli->commit();
            $_SESSION['form_message'] = '<p class="alert alert-success text-white">บันทึกข้อมูลการเข้าร่วม ' . $success_count . ' รายการ สำเร็จแล้ว</p>';
        } else {
            $mysqli->rollback();
            $_SESSION['last_attendance_input'] = $attendance_statuses;
            $message_record = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการบันทึกข้อมูลบางรายการ:<br>' . implode('<br>', $errors_saving) . '</p>';
        }
        if(empty($message_record) || !empty($_SESSION['form_message'])){
             header('Location: index.php?page=advisor_attendance_select_activity');
             exit;
        }
    } catch (Exception $e) {
        $mysqli->rollback();
        if(isset($attendance_statuses)) { $_SESSION['last_attendance_input'] = $attendance_statuses; }
        $message_record = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดร้ายแรง: ' . $e->getMessage() . '</p>';
    }
}

// --- Fetch Booked Advisees for this activity, grouped by student_group ---
if ($activity_id_record && $activity_info_record) {
    $sql_booked_adv = "SELECT u.id as user_id, u.username, u.first_name, u.last_name, s.student_id_number,
                          sg.id as group_id, sg.group_name, sg.group_code
                       FROM activity_bookings ab
                       JOIN students s ON ab.student_user_id = s.user_id
                       JOIN users u ON s.user_id = u.id
                       JOIN student_groups sg ON s.group_id = sg.id
                       JOIN group_advisors ga ON sg.id = ga.group_id
                       WHERE ab.activity_id = ? AND ab.status = 'booked' AND ga.advisor_user_id = ?
                       ORDER BY sg.group_name ASC, s.student_id_number ASC";
    $stmt_booked_adv = $mysqli->prepare($sql_booked_adv);
    if ($stmt_booked_adv) {
        $stmt_booked_adv->bind_param('ii', $activity_id_record, $advisor_user_id);
        $stmt_booked_adv->execute();
        $result_booked_adv = $stmt_booked_adv->get_result();
        while ($row = $result_booked_adv->fetch_assoc()) {
            $advisees_by_group[$row['group_id']]['group_name'] = $row['group_name'] . ' (' . $row['group_code'] . ')';
            $advisees_by_group[$row['group_id']]['students'][] = $row;
        }
        $stmt_booked_adv->close();
    } else {
        $message_record .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงรายชื่อผู้จอง: ' . htmlspecialchars($mysqli->error) . '</p>';
    }

    $sql_att = "SELECT student_user_id, attendance_status FROM activity_attendance WHERE activity_id = ?";
    $stmt_att = $mysqli->prepare($sql_att);
    if ($stmt_att) {
        $stmt_att->bind_param('i', $activity_id_record); $stmt_att->execute();
        $result_att = $stmt_att->get_result();
        while ($row = $result_att->fetch_assoc()) { $attendance_data_record[$row['student_user_id']] = $row['attendance_status']; }
        $stmt_att->close();
    }
}

$last_input_record = $_SESSION['last_attendance_input'] ?? null;
unset($_SESSION['last_attendance_input']);

if (isset($_SESSION['form_message']) && empty($message_record)) {
    $message_record = $_SESSION['form_message'];
    unset($_SESSION['form_message']);
}
?>

<div class="container-fluid py-4">
     <div class="row">
        <div class="col-12">
            <div class="card my-4">
                 <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div class="bg-gradient-info shadow-info border-radius-lg pt-4 pb-3">
                        <h6 class="text-white text-capitalize ps-3"><?php echo $page_title; ?></h6>
                        <?php if ($activity_info_record): ?>
                        <p class="text-white ps-3 mb-0 text-sm">วันที่: <?php echo format_datetime_th($activity_info_record['start_datetime'], true); ?> | ชม.เข้าร่วม: <?php echo number_format($activity_info_record['hours_participant'] ?? 0, 0); ?> | ชม.หัก: <?php echo number_format($activity_info_record['penalty_hours'] ?? 0, 0); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body pb-2">
                    <?php if (!empty($message_record)) : ?>
                        <div class="alert alert-dismissible text-white fade show <?php echo (strpos($message_record, 'success') !== false || strpos($message_record, 'สำเร็จ') !== false) ? 'alert-success bg-gradient-success' : ((strpos($message_record, 'warning') !== false || strpos($message_record, 'เตือน') !== false) ? 'alert-warning bg-gradient-warning' : 'alert-danger bg-gradient-danger'); ?>" role="alert">
                            <?php echo $message_record; ?>
                            <button type="button" class="btn-close p-3" data-bs-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($activity_id_record && $activity_info_record && !empty($advisees_by_group)): ?>
                    <form action="index.php?page=advisor_attendance_record&activity_id=<?php echo $activity_id_record; ?>" method="post">
                        <input type="hidden" name="activity_id" value="<?php echo $activity_id_record; ?>">

                        <div class="nav-wrapper position-relative end-0">
                            <ul class="nav nav-pills nav-fill p-1" role="tablist">
                                <?php $first_tab_adv = true; ?>
                                <?php foreach ($advisees_by_group as $gid => $group_data): ?>
                                    <li class="nav-item">
                                        <a class="nav-link mb-0 px-0 py-1 <?php echo $first_tab_adv ? 'active' : ''; ?>" data-bs-toggle="tab" href="#adv-group-<?php echo $gid; ?>" role="tab" aria-controls="adv-group-<?php echo $gid; ?>" aria-selected="<?php echo $first_tab_adv ? 'true' : 'false'; ?>">
                                            <i class="material-symbols-rounded me-1">groups</i>
                                            <?php echo htmlspecialchars($group_data['group_name']); ?>
                                        </a>
                                    </li>
                                <?php $first_tab_adv = false; endforeach; ?>
                            </ul>
                        </div>

                        <div class="tab-content mt-3">
                            <?php $first_tab_content_adv = true; ?>
                            <?php foreach ($advisees_by_group as $gid => $group_data): ?>
                                <div class="tab-pane fade <?php echo $first_tab_content_adv ? 'show active' : ''; ?>" id="adv-group-<?php echo $gid; ?>" role="tabpanel">
                                    <div class="table-responsive p-0">
                                        <table class="table align-items-center mb-0 table-hover">
                                            <thead>
                                                <tr>
                                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7" style="width: 5%;">ลำดับ</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7" style="width: 20%;">รหัสนักศึกษา</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">ชื่อ-สกุล</th>
                                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7" style="width: 10%;">เข้าร่วม</th>
                                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7" style="width: 10%;">ไม่เข้าร่วม</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($group_data['students'])): $counter_adv = 1; ?>
                                                    <?php foreach ($group_data['students'] as $student_adv):
                                                        $current_status_adv = $last_input_record[$student_adv['user_id']] ?? $attendance_data_record[$student_adv['user_id']] ?? 'absent';
                                                    ?>
                                                        <tr>
                                                            <td class="align-middle text-center"><span class="text-secondary text-xs font-weight-bold"><?php echo $counter_adv++; ?></span></td>
                                                            <td><p class="text-xs font-weight-bold mb-0 px-3"><?php echo htmlspecialchars($student_adv['student_id_number']); ?></p></td>
                                                            <td><h6 class="mb-0 text-sm"><?php echo htmlspecialchars($student_adv['first_name'] . ' ' . $student_adv['last_name']); ?></h6></td>
                                                            <td class="align-middle text-center text-sm">
                                                                <div class="form-check d-flex justify-content-center ps-0">
                                                                    <input class="form-check-input" type="radio" name="attendance_status[<?php echo $student_adv['user_id']; ?>]" id="status_adv_<?php echo $gid . '_' . $student_adv['user_id']; ?>_attended" value="attended" <?php echo ($current_status_adv === 'attended') ? 'checked' : ''; ?> <?php echo !$can_checkin ? 'disabled' : ''; ?>>
                                                                </div>
                                                            </td>
                                                            <td class="align-middle text-center text-sm">
                                                                <div class="form-check d-flex justify-content-center ps-0">
                                                                    <input class="form-check-input" type="radio" name="attendance_status[<?php echo $student_adv['user_id']; ?>]" id="status_adv_<?php echo $gid . '_' . $student_adv['user_id']; ?>_absent" value="absent" <?php echo ($current_status_adv === 'absent') ? 'checked' : ''; ?> <?php echo !$can_checkin ? 'disabled' : ''; ?>>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr><td colspan="5" class="text-center p-3">ไม่พบนักศึกษาในกลุ่มนี้ที่จองกิจกรรม</td></tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php $first_tab_content_adv = false; endforeach; ?>
                        </div>
                        <?php if (!empty($advisees_by_group) && $can_checkin): ?>
                        <div class="text-center mt-4">
                            <button type="submit" class="btn bg-gradient-primary w-50 my-4 mb-2">บันทึกข้อมูลการเข้าร่วม</button>
                        </div>
                        <?php elseif (!$can_checkin && !empty($advisees_by_group)): ?>
                            <p class="text-center text-warning mt-4">ไม่สามารถบันทึกการเช็คชื่อได้เนื่องจากเลยระยะเวลาที่กำหนด</p>
                        <?php endif; ?>
                    </form>
                    <?php elseif ($activity_id_record && $activity_info_record && empty($advisees_by_group)): ?>
                        <p class="text-center p-4">
                            <?php echo ($current_user_role_for_record == 2) ? 'ไม่พบนักศึกษาในที่ปรึกษาของท่านที่จองกิจกรรมนี้' : 'ไม่มีนักศึกษาจองกิจกรรมนี้'; ?>
                        </p>
                         <div class="text-center"><a href="index.php?page=advisor_attendance_select_activity" class="btn btn-outline-secondary mb-0">กลับไปเลือกกิจกรรม</a></div>
                    <?php else: ?>
                         <p class="text-center text-danger p-4">ไม่สามารถโหลดข้อมูลกิจกรรมได้</p>
                         <div class="text-center"><a href="index.php?page=advisor_attendance_select_activity" class="btn btn-outline-secondary mb-0">กลับไปเลือกกิจกรรม</a></div>
                    <?php endif; ?>
                </div> </div> </div> </div> </div>