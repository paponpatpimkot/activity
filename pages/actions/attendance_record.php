<?php
// ========================================================================
// ไฟล์: attendance_record.php (เนื้อหาสำหรับ include)
// หน้าที่: แสดงรายชื่อนักศึกษาที่จอง และบันทึกสถานะการเข้าร่วม (ปรับปรุง Layout ตาราง)
// (ไม่มีการเปลี่ยนแปลงในไฟล์นี้)
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php ($mysqli), และ Authorization check (Admin or Staff) ---
// --- ได้ทำไปแล้วในไฟล์ Controller หลัก ---
// require 'db_connect.php'; // $mysqli should be available
// if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 4])) { exit('Unauthorized'); }

$page_title = "บันทึกการเข้าร่วมกิจกรรม";
$message = '';
$activity_id = null;
$activity_info = null;
$students_booked = [];
$attendance_data = []; // เก็บข้อมูล attendance ที่มีอยู่แล้ว key คือ user_id

// --- Get Activity ID from URL ---
if (isset($_GET['activity_id']) && is_numeric($_GET['activity_id'])) {
    $activity_id = (int)$_GET['activity_id'];
} else {
    $_SESSION['form_message'] = '<p class="alert alert-warning text-white">กรุณาเลือกกิจกรรมที่ต้องการเช็คชื่อ</p>';
    header('Location: index.php?page=attendance_select_activity');
    exit;
}

// --- Fetch Activity Info (รวม penalty_hours) ---
$sql_activity = "SELECT id, name, start_datetime, hours_participant, penalty_hours
                 FROM activities WHERE id = ?"; // เพิ่ม penalty_hours
$stmt_activity = $mysqli->prepare($sql_activity);
if ($stmt_activity) {
    $stmt_activity->bind_param('i', $activity_id);
    $stmt_activity->execute();
    $result_activity = $stmt_activity->get_result();
    if ($result_activity->num_rows === 1) {
        $activity_info = $result_activity->fetch_assoc();
        $page_title .= ' - ' . htmlspecialchars($activity_info['name']);
    } else {
        $_SESSION['form_message'] = '<p class="alert alert-danger text-white">ไม่พบข้อมูลกิจกรรมที่ระบุ</p>';
        header('Location: index.php?page=attendance_select_activity');
        exit;
    }
    $stmt_activity->close();
} else {
    $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงข้อมูลกิจกรรม: ' . htmlspecialchars($mysqli->error) . '</p>';
}


// --- Handle Form Submission (Save Attendance - ปรับปรุง) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activity_id']) && $_POST['activity_id'] == $activity_id && isset($_POST['attendance_status']) && $activity_info) { // ตรวจสอบ $activity_info ด้วย
    $attendance_statuses = $_POST['attendance_status']; // Array [user_id => status]
    $recorded_by = $_SESSION['user_id'];
    $errors_saving = [];
    $success_count = 0;

    $mysqli->begin_transaction();
    try {
        $sql_upsert = "INSERT INTO activity_attendance
                       (student_user_id, activity_id, attendance_status, hours_earned, recorded_by_user_id, created_at, updated_at)
                       VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                       ON DUPLICATE KEY UPDATE
                       attendance_status = VALUES(attendance_status),
                       hours_earned = VALUES(hours_earned),
                       recorded_by_user_id = VALUES(recorded_by_user_id),
                       updated_at = NOW()";
        $stmt_upsert = $mysqli->prepare($sql_upsert);

        if ($stmt_upsert === false) {
            throw new Exception("Prepare statement failed: " . $mysqli->error);
        }

        foreach ($attendance_statuses as $student_user_id => $status) {
            $student_user_id = (int)$student_user_id;
            $status = trim($status);

            // Validate status (เหลือแค่ attended, absent)
            $valid_statuses = ['attended', 'absent'];
            if (!in_array($status, $valid_statuses)) {
                $status = 'absent'; // ถ้าค่าไม่ถูกต้อง ให้เป็น absent
            }

            // คำนวณ hours_earned (บวก หรือ ลบ) - ใช้ floatval
            $hours_earned = 0.0; // ใช้ float
            if ($status === 'attended') {
                // ใช้ floatval เพื่อให้แน่ใจว่าเป็น float
                $hours_earned = floatval($activity_info['hours_participant'] ?? 0.0);
            } elseif ($status === 'absent') {
                // บันทึกค่าติดลบของ penalty_hours - ใช้ floatval
                $penalty = $activity_info['penalty_hours'] ?? 0.0;
                $hours_earned = -abs(floatval($penalty)); // ทำให้เป็นลบเสมอ
            }

            // Bind parameters and execute - เปลี่ยน type ของ hours_earned เป็น 'd'
            // Types: i=int, i=int, s=string, d=double, i=int
            $stmt_upsert->bind_param("iisdi", $student_user_id, $activity_id, $status, $hours_earned, $recorded_by);
            if ($stmt_upsert->execute()) {
                $success_count++;
            } else {
                $errors_saving[] = "User ID $student_user_id: " . $stmt_upsert->error;
            }
        }
        $stmt_upsert->close();

        if (empty($errors_saving)) {
            $mysqli->commit();
            $_SESSION['form_message'] = '<p class="alert alert-success text-white">บันทึกข้อมูลการเข้าร่วม ' . $success_count . ' รายการ สำเร็จแล้ว</p>';
        } else {
            $mysqli->rollback();
            // เก็บค่าที่ user กรอกล่าสุดไว้ใน session เพื่อแสดงผลในฟอร์มอีกครั้ง
            $_SESSION['last_attendance_input'] = $attendance_statuses;
            $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการบันทึกข้อมูลบางรายการ:<br>' . implode('<br>', $errors_saving) . '</p>';
        }

        // Redirect กลับไปหน้าเลือกกิจกรรม
        if (empty($message) || !empty($_SESSION['form_message'])) {
            header('Location: index.php?page=attendance_select_activity');
            exit;
        }
        // ถ้ามี $message จาก error ตอน save จะไม่ redirect แต่จะแสดง error ในหน้าเดิม


    } catch (Exception $e) {
        $mysqli->rollback();
        // เก็บค่าที่ user กรอกล่าสุดไว้ใน session
        if (isset($attendance_statuses)) {
            $_SESSION['last_attendance_input'] = $attendance_statuses;
        }
        $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดร้ายแรง: ' . $e->getMessage() . '</p>';
    }
} // end if POST


// --- Fetch Booked Students ---
if ($activity_id) {
    $sql_booked = "SELECT u.id as user_id, u.username, u.first_name, u.last_name, s.student_id_number
                   FROM activity_bookings b
                   JOIN users u ON b.student_user_id = u.id
                   JOIN students s ON u.id = s.user_id
                   WHERE b.activity_id = ? AND b.status = 'booked'
                   ORDER BY s.student_id_number ASC"; // เรียงตามรหัสนักศึกษา
    $stmt_booked = $mysqli->prepare($sql_booked);
    if ($stmt_booked) {
        $stmt_booked->bind_param('i', $activity_id);
        $stmt_booked->execute();
        $result_booked = $stmt_booked->get_result();
        while ($row = $result_booked->fetch_assoc()) {
            $students_booked[] = $row;
        }
        $stmt_booked->close();
    } else {
        $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงรายชื่อผู้จอง: ' . htmlspecialchars($mysqli->error) . '</p>';
    }

    // --- Fetch Existing Attendance Data ---
    $sql_att = "SELECT student_user_id, attendance_status FROM activity_attendance WHERE activity_id = ?";
    $stmt_att = $mysqli->prepare($sql_att);
    if ($stmt_att) {
        $stmt_att->bind_param('i', $activity_id);
        $stmt_att->execute();
        $result_att = $stmt_att->get_result();
        while ($row = $result_att->fetch_assoc()) {
            $attendance_data[$row['student_user_id']] = $row['attendance_status'];
        }
        $stmt_att->close();
    } else {
        $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงข้อมูลการเข้าร่วมเดิม: ' . htmlspecialchars($mysqli->error) . '</p>';
    }
}

// --- ดึงข้อมูลที่กรอกล่าสุดจาก Session ถ้ามี (กรณีเกิด Error ตอน Save) ---
$last_input = $_SESSION['last_attendance_input'] ?? null;
unset($_SESSION['last_attendance_input']); // เคลียร์ค่าหลังใช้


// --- จัดการ Message จาก Session ---
// if (isset($_SESSION['form_message'])) {
//     $message = $_SESSION['form_message'];
//     unset($_SESSION['form_message']);
// }

// Function to format datetime (ถ้ายังไม่มี)
if (!function_exists('format_datetime_th')) {
    function format_datetime_th($datetime_str, $include_time = true)
    {
        if (empty($datetime_str)) return '-';
        try {
            $dt = new DateTime($datetime_str);
            $thai_months_short = [1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'];
            $day = $dt->format('d');
            $month_num = (int)$dt->format('n');
            $thai_month = $thai_months_short[$month_num] ?? '?';
            $buddhist_year = $dt->format('Y') + 543;
            $formatted_date = $day . ' ' . $thai_month . ' ' . $buddhist_year;
            if ($include_time) {
                $formatted_date .= ' ' . $dt->format('H:i');
            }
            return $formatted_date;
        } catch (Exception $e) {
            return '-';
        }
    }
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
                            <p class="text-white ps-3 mb-0 text-sm">วันที่: <?php echo format_datetime_th($activity_info['start_datetime']); ?> | ชม.เข้าร่วม: <?php echo number_format($activity_info['hours_participant'] ?? 0, 0); ?> | ชม.หัก: <?php echo number_format($activity_info['penalty_hours'] ?? 0, 0); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body pb-2">
                    <?php if (!empty($message)) : ?>
                        <div class="alert alert-dismissible text-white fade show <?php echo (strpos($message, 'success') !== false || strpos($message, 'สำเร็จ') !== false) ? 'alert-success bg-gradient-success' : ((strpos($message, 'warning') !== false || strpos($message, 'เตือน') !== false) ? 'alert-warning bg-gradient-warning' : 'alert-danger bg-gradient-danger'); ?>" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close p-3" data-bs-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php if ($activity_id && $activity_info): ?>
                        <form action="index.php?page=attendance_record&activity_id=<?php echo $activity_id; ?>" method="post">
                            <input type="hidden" name="activity_id" value="<?php echo $activity_id; ?>">
                            <div class="table-responsive p-0">
                                <table class="table align-items-center mb-0 table-hover">
                                    <thead>
                                        <tr>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7" style="width: 5%;">ลำดับที่</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7" style="width: 20%;">รหัสนักศึกษา</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">ชื่อ-สกุล</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7" style="width: 10%;">เข้าร่วม</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7" style="width: 10%;">ไม่เข้าร่วม</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($students_booked)):
                                            $counter = 1; // ตัวนับลำดับ
                                        ?>
                                            <?php foreach ($students_booked as $student):
                                                $current_status = $last_input[$student['user_id']] ?? $attendance_data[$student['user_id']] ?? 'absent'; // Default เป็น ไม่เข้าร่วม
                                            ?>
                                                <tr>
                                                    <td class="align-middle text-center">
                                                        <span class="text-secondary text-xs font-weight-bold"><?php echo $counter++; ?></span>
                                                    </td>
                                                    <td>
                                                        <p class="text-xs font-weight-bold mb-0 px-3"><?php echo htmlspecialchars($student['student_id_number']); ?></p>
                                                    </td>
                                                    <td>
                                                        <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h6>
                                                    </td>
                                                    <td class="align-middle text-center text-sm">
                                                        <div class="form-check d-flex justify-content-center ps-0"> <input class="form-check-input" type="radio"
                                                                name="attendance_status[<?php echo $student['user_id']; ?>]"
                                                                id="status_<?php echo $student['user_id']; ?>_attended"
                                                                value="attended"
                                                                <?php echo ($current_status === 'attended') ? 'checked' : ''; ?>>
                                                        </div>
                                                    </td>
                                                    <td class="align-middle text-center text-sm">
                                                        <div class="form-check d-flex justify-content-center ps-0">
                                                            <input class="form-check-input" type="radio"
                                                                name="attendance_status[<?php echo $student['user_id']; ?>]"
                                                                id="status_<?php echo $student['user_id']; ?>_absent"
                                                                value="absent"
                                                                <?php echo ($current_status === 'absent') ? 'checked' : ''; ?>>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center">ไม่มีนักศึกษาจองกิจกรรมนี้</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if (!empty($students_booked)): ?>
                                <div class="text-center mt-4">
                                    <button type="submit" class="btn bg-gradient-primary w-50 my-4 mb-2">บันทึกข้อมูลการเข้าร่วม</button>
                                </div>
                            <?php endif; ?>
                        </form>
                    <?php else: ?>
                        <p class="text-center text-danger">ไม่สามารถโหลดข้อมูลกิจกรรมได้</p>
                        <div class="text-center">
                            <a href="index.php?page=attendance_select_activity" class="btn btn-outline-secondary mb-0">กลับไปเลือกกิจกรรม</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>