<?php
// ========================================================================
// ไฟล์: attendance_record.php (เนื้อหาสำหรับ include)
// หน้าที่: แสดงรายชื่อนักศึกษาที่จอง และบันทึกสถานะการเข้าร่วม (ปรับปรุง Layout ตาราง และ Nav Pills)
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php ($mysqli), Authorization check (Admin or Staff) ---
// --- และ require_once 'includes/functions.php' ได้ทำไปแล้วใน Controller หลัก ---

// if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 4])) { exit('Unauthorized'); }

$page_title = "บันทึกการเข้าร่วมกิจกรรม";
$message = '';
$activity_id = null;
$activity_info = null;
$students_by_group = []; // เปลี่ยนเป็น Array ที่จัดกลุ่มแล้ว
$attendance_data = [];
$current_user_id_for_record = $_SESSION['user_id'];
$current_user_role_for_record = $_SESSION['role_id'];


// --- Get Activity ID from URL ---
if (isset($_GET['activity_id']) && is_numeric($_GET['activity_id'])) {
    $activity_id = (int)$_GET['activity_id'];
} else {
    $_SESSION['form_message'] = '<p class="alert alert-warning text-white">กรุณาเลือกกิจกรรมที่ต้องการเช็คชื่อ</p>';
    header('Location: index.php?page=attendance_select_activity'); // หรือหน้าที่เหมาะสมสำหรับ Role
    exit;
}

// --- Fetch Activity Info ---
$sql_activity = "SELECT id, name, start_datetime, hours_participant, penalty_hours, attendance_recorder_type FROM activities WHERE id = ?";
$stmt_activity = $mysqli->prepare($sql_activity);
if ($stmt_activity) {
    $stmt_activity->bind_param('i', $activity_id);
    $stmt_activity->execute();
    $result_activity = $stmt_activity->get_result();
    if ($result_activity->num_rows === 1) {
        $activity_info = $result_activity->fetch_assoc();
        $page_title .= ' - ' . htmlspecialchars($activity_info['name']);

        // *** Security Check: Staff can only access if assigned or if no one is assigned ***
        if ($current_user_role_for_record == 4 && $activity_info['attendance_recorder_type'] === 'system') {
            $sql_check_specific = "SELECT staff_user_id FROM activity_specific_recorders WHERE activity_id = ?";
            $stmt_specific = $mysqli->prepare($sql_check_specific);
            $stmt_specific->bind_param('i', $activity_id);
            $stmt_specific->execute();
            $result_specific = $stmt_specific->get_result();
            $specific_staff_assigned = [];
            while($r_staff = $result_specific->fetch_assoc()){
                $specific_staff_assigned[] = $r_staff['staff_user_id'];
            }
            $stmt_specific->close();

            if (!empty($specific_staff_assigned) && !in_array($current_user_id_for_record, $specific_staff_assigned)) {
                $_SESSION['form_message'] = '<p class="alert alert-danger text-white">คุณไม่มีสิทธิ์เช็คชื่อสำหรับกิจกรรมนี้</p>';
                header('Location: index.php?page=attendance_select_activity');
                exit;
            }
        } elseif ($current_user_role_for_record == 4 && $activity_info['attendance_recorder_type'] === 'advisor') {
            // Staff cannot checkin if recorder type is advisor
            $_SESSION['form_message'] = '<p class="alert alert-danger text-white">กิจกรรมนี้กำหนดให้ครูที่ปรึกษาเป็นผู้เช็คชื่อ</p>';
            header('Location: index.php?page=attendance_select_activity');
            exit;
        }

    } else {
         $_SESSION['form_message'] = '<p class="alert alert-danger text-white">ไม่พบข้อมูลกิจกรรมที่ระบุ</p>';
         header('Location: index.php?page=attendance_select_activity');
         exit;
    }
    $stmt_activity->close();
} else {
    $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงข้อมูลกิจกรรม: ' . htmlspecialchars($mysqli->error) . '</p>';
}


// --- Handle Form Submission (Save Attendance) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activity_id']) && $_POST['activity_id'] == $activity_id && isset($_POST['attendance_status']) && $activity_info) {
    $attendance_statuses = $_POST['attendance_status'];
    $recorded_by = $current_user_id_for_record;
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

        foreach ($attendance_statuses as $student_user_id_from_form => $status) {
            $student_user_id_from_form = (int)$student_user_id_from_form;
            $status = trim($status);
            $valid_statuses = ['attended', 'absent'];
            if (!in_array($status, $valid_statuses)) { $status = 'absent'; }

            $hours_earned = 0.0;
            if ($status === 'attended') { $hours_earned = floatval($activity_info['hours_participant'] ?? 0.0); }
            elseif ($status === 'absent') { $penalty = $activity_info['penalty_hours'] ?? 0.0; $hours_earned = -abs(floatval($penalty)); }

            $stmt_upsert->bind_param("iisdi", $student_user_id_from_form, $activity_id, $status, $hours_earned, $recorded_by);
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
            $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการบันทึกข้อมูลบางรายการ:<br>' . implode('<br>', $errors_saving) . '</p>';
        }
        if(empty($message) || !empty($_SESSION['form_message'])){
             header('Location: index.php?page=attendance_select_activity'); // Redirect to selection page
             exit;
        }
    } catch (Exception $e) {
        $mysqli->rollback();
        if(isset($attendance_statuses)) { $_SESSION['last_attendance_input'] = $attendance_statuses; }
        $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดร้ายแรง: ' . $e->getMessage() . '</p>';
    }
}

// --- Fetch Booked Students and Group them ---
if ($activity_id && $activity_info) { // Ensure activity_info is loaded
    $sql_booked = "SELECT u.id as user_id, u.username, u.first_name, u.last_name, s.student_id_number,
                          sg.id as group_id, sg.group_name, sg.group_code
                   FROM activity_bookings b
                   JOIN users u ON b.student_user_id = u.id
                   JOIN students s ON u.id = s.user_id
                   JOIN student_groups sg ON s.group_id = sg.id
                   WHERE b.activity_id = ? AND b.status = 'booked' ";

    // If current user is Advisor, only fetch their advisees for this activity
    if ($current_user_role_for_record == 2) {
        $sql_booked .= " AND EXISTS (SELECT 1 FROM group_advisors ga WHERE ga.group_id = sg.id AND ga.advisor_user_id = ?) ";
    }
    $sql_booked .= " ORDER BY sg.group_name ASC, s.student_id_number ASC";

    $stmt_booked = $mysqli->prepare($sql_booked);
    if ($stmt_booked) {
        if ($current_user_role_for_record == 2) {
            $stmt_booked->bind_param('ii', $activity_id, $current_user_id_for_record);
        } else {
            $stmt_booked->bind_param('i', $activity_id);
        }
        $stmt_booked->execute();
        $result_booked = $stmt_booked->get_result();
        while ($row = $result_booked->fetch_assoc()) {
            $students_by_group[$row['group_id']]['group_name'] = $row['group_name'] . ' (' . $row['group_code'] . ')';
            $students_by_group[$row['group_id']]['students'][] = $row;
        }
        $stmt_booked->close();
    } else {
        $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงรายชื่อผู้จอง: ' . htmlspecialchars($mysqli->error) . '</p>';
    }

    // --- Fetch Existing Attendance Data ---
    $sql_att = "SELECT student_user_id, attendance_status FROM activity_attendance WHERE activity_id = ?";
    $stmt_att = $mysqli->prepare($sql_att);
    if ($stmt_att) {
        $stmt_att->bind_param('i', $activity_id); $stmt_att->execute();
        $result_att = $stmt_att->get_result();
        while ($row = $result_att->fetch_assoc()) { $attendance_data[$row['student_user_id']] = $row['attendance_status']; }
        $stmt_att->close();
    } else { $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงข้อมูลการเข้าร่วมเดิม: ' . htmlspecialchars($mysqli->error) . '</p>'; }
}

$last_input = $_SESSION['last_attendance_input'] ?? null;
unset($_SESSION['last_attendance_input']);

if (isset($_SESSION['form_message']) && empty($message)) { // Show session message if no other message is set
    $message = $_SESSION['form_message'];
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
                        <?php if ($activity_info): ?>
                        <p class="text-white ps-3 mb-0 text-sm">วันที่: <?php echo format_datetime_th($activity_info['start_datetime'], true); ?> | ชม.เข้าร่วม: <?php echo number_format($activity_info['hours_participant'] ?? 0, 0); ?> | ชม.หัก: <?php echo number_format($activity_info['penalty_hours'] ?? 0, 0); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body pb-2">
                    <?php if (!empty($message)) : ?>
                        <div class="alert alert-dismissible text-white fade show <?php echo (strpos($message, 'success') !== false || strpos($message, 'สำเร็จ') !== false) ? 'alert-success bg-gradient-success' : ((strpos($message, 'warning') !== false || strpos($message, 'เตือน') !== false) ? 'alert-warning bg-gradient-warning' : 'alert-danger bg-gradient-danger'); ?>" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close p-3" data-bs-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($activity_id && $activity_info && !empty($students_by_group)): ?>
                    <form action="index.php?page=attendance_record&activity_id=<?php echo $activity_id; ?>" method="post">
                        <input type="hidden" name="activity_id" value="<?php echo $activity_id; ?>">

                        <div class="nav-wrapper position-relative end-0">
                            <ul class="nav nav-pills nav-fill p-1" role="tablist">
                                <?php $first_tab = true; ?>
                                <?php foreach ($students_by_group as $gid => $group_data): ?>
                                    <li class="nav-item">
                                        <a class="nav-link mb-0 px-0 py-1 <?php echo $first_tab ? 'active' : ''; ?>" data-bs-toggle="tab" href="#group-<?php echo $gid; ?>" role="tab" aria-controls="group-<?php echo $gid; ?>" aria-selected="<?php echo $first_tab ? 'true' : 'false'; ?>">
                                            <i class="material-symbols-rounded me-1">groups</i>
                                            <?php echo htmlspecialchars($group_data['group_name']); ?>
                                        </a>
                                    </li>
                                <?php $first_tab = false; endforeach; ?>
                            </ul>
                        </div>

                        <div class="tab-content mt-3">
                            <?php $first_tab_content = true; ?>
                            <?php foreach ($students_by_group as $gid => $group_data): ?>
                                <div class="tab-pane fade <?php echo $first_tab_content ? 'show active' : ''; ?>" id="group-<?php echo $gid; ?>" role="tabpanel">
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
                                                <?php if (!empty($group_data['students'])):
                                                      $counter = 1;
                                                ?>
                                                    <?php foreach ($group_data['students'] as $student):
                                                        $current_status = $last_input[$student['user_id']] ?? $attendance_data[$student['user_id']] ?? 'absent';
                                                    ?>
                                                        <tr>
                                                            <td class="align-middle text-center"><span class="text-secondary text-xs font-weight-bold"><?php echo $counter++; ?></span></td>
                                                            <td><p class="text-xs font-weight-bold mb-0 px-3"><?php echo htmlspecialchars($student['student_id_number']); ?></p></td>
                                                            <td><h6 class="mb-0 text-sm"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h6></td>
                                                            <td class="align-middle text-center text-sm">
                                                                <div class="form-check d-flex justify-content-center ps-0">
                                                                    <input class="form-check-input" type="radio" name="attendance_status[<?php echo $student['user_id']; ?>]" id="status_<?php echo $gid . '_' . $student['user_id']; ?>_attended" value="attended" <?php echo ($current_status === 'attended') ? 'checked' : ''; ?>>
                                                                </div>
                                                            </td>
                                                            <td class="align-middle text-center text-sm">
                                                                <div class="form-check d-flex justify-content-center ps-0">
                                                                    <input class="form-check-input" type="radio" name="attendance_status[<?php echo $student['user_id']; ?>]" id="status_<?php echo $gid . '_' . $student['user_id']; ?>_absent" value="absent" <?php echo ($current_status === 'absent') ? 'checked' : ''; ?>>
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
                            <?php $first_tab_content = false; endforeach; ?>
                        </div> <?php if (!empty($students_by_group)): ?>
                        <div class="text-center mt-4">
                            <button type="submit" class="btn bg-gradient-primary w-50 my-4 mb-2">บันทึกข้อมูลการเข้าร่วม</button>
                        </div>
                        <?php endif; ?>
                    </form>
                    <?php elseif ($activity_id && $activity_info && empty($students_by_group)): ?>
                        <p class="text-center p-4">
                            <?php echo ($current_user_role_for_record == 2) ? 'ไม่พบนักศึกษาในที่ปรึกษาของท่านที่จองกิจกรรมนี้' : 'ไม่มีนักศึกษาจองกิจกรรมนี้'; ?>
                        </p>
                         <div class="text-center">
                             <a href="index.php?page=attendance_select_activity" class="btn btn-outline-secondary mb-0">กลับไปเลือกกิจกรรม</a>
                        </div>
                    <?php else: ?>
                         <p class="text-center text-danger p-4">ไม่สามารถโหลดข้อมูลกิจกรรมได้</p>
                         <div class="text-center">
                             <a href="index.php?page=attendance_select_activity" class="btn btn-outline-secondary mb-0">กลับไปเลือกกิจกรรม</a>
                        </div>
                    <?php endif; ?>
                </div> </div> </div> </div> </div>
