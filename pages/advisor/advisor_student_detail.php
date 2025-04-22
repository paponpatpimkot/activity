<?php
// ========================================================================
// ไฟล์: advisor_student_detail.php (เนื้อหาสำหรับ include)
// หน้าที่: แสดงรายละเอียดชั่วโมงกิจกรรมของนักศึกษาที่เลือก (แก้ไข Query ตรวจสอบสิทธิ์ และดึง required_hours)
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php ($mysqli), และ Authorization check ---
// --- ได้ทำไปแล้วในไฟล์ Controller หลัก และมีการกำหนด $_SESSION['user_id'], $_SESSION['role_id'] ---

// if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) { exit('Unauthorized'); }

$advisor_user_id = $_SESSION['user_id'];
$student_user_id_target = null;
$student_target_info = null; // เก็บข้อมูลนักศึกษาที่เลือก
$message = '';
$required_hours = 0; // กำหนดค่าเริ่มต้น

// --- Get Student User ID from URL ---
if (isset($_GET['student_user_id']) && is_numeric($_GET['student_user_id'])) {
    $student_user_id_target = (int)$_GET['student_user_id'];
} else {
    $_SESSION['form_message'] = '<p class="alert alert-warning text-white">กรุณาเลือกนักศึกษาที่ต้องการดูรายละเอียด</p>';
    header('Location: index.php?page=advisor_dashboard'); // กลับไปหน้า Dashboard ของ Advisor
    exit;
}

// --- Verify Advisor's Permission for this Student (แก้ไข Query) ---
// ตรวจสอบว่านักศึกษาคนนี้อยู่ในกลุ่มที่ Advisor คนนี้เป็นที่ปรึกษาหรือไม่
// และดึง required_hours จากตาราง levels
$sql_verify = "SELECT s.user_id, u.first_name, u.last_name, u.email, s.student_id_number,
                      l.default_required_hours as required_hours -- ดึง required hours จาก levels
               FROM students s
               JOIN users u ON s.user_id = u.id
               JOIN student_groups sg ON s.group_id = sg.id
               JOIN levels l ON sg.level_id = l.id -- Join levels เพื่อเอา required hours
               JOIN group_advisors ga ON sg.id = ga.group_id -- Join ตารางเชื่อมโยง advisor
               WHERE s.user_id = ? AND ga.advisor_user_id = ?"; // เช็ค advisor_user_id จากตารางเชื่อมโยง
$stmt_verify = $mysqli->prepare($sql_verify);
if ($stmt_verify) {
    $stmt_verify->bind_param('ii', $student_user_id_target, $advisor_user_id);
    $stmt_verify->execute();
    $result_verify = $stmt_verify->get_result();
    if ($result_verify->num_rows === 1) {
        $student_target_info = $result_verify->fetch_assoc();
        // กำหนดค่า required_hours จากข้อมูลที่ดึงมา
        $required_hours = $student_target_info['required_hours'] ?? 0;
    } else {
        // ไม่ใช่ Advisor ของนักศึกษาคนนี้
        $_SESSION['form_message'] = '<p class="alert alert-danger text-white">คุณไม่มีสิทธิ์ดูข้อมูลของนักศึกษาคนนี้</p>';
        header('Location: index.php?page=advisor_dashboard');
        exit;
    }
    $stmt_verify->close();
} else {
    // Error preparing statement
    error_log("Advisor Verify Error: " . $mysqli->error);
    $_SESSION['form_message'] = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการตรวจสอบสิทธิ์</p>';
    header('Location: index.php?page=advisor_dashboard');
    exit;
}

// --- ถ้าผ่านการตรวจสอบสิทธิ์ ดำเนินการดึงข้อมูลอื่นๆ ---
$page_title = "รายละเอียดชั่วโมงกิจกรรม - " . htmlspecialchars($student_target_info['first_name'] . ' ' . $student_target_info['last_name']);

// --- Calculate Earned Hours (เหมือน student_history.php) ---
$total_earned_hours = 0.0;
$sql_earned = "SELECT SUM(hours_earned) as total_earned
               FROM activity_attendance
               WHERE student_user_id = ?";
$stmt_earned = $mysqli->prepare($sql_earned);
if ($stmt_earned) {
    $stmt_earned->bind_param('i', $student_user_id_target);
    $stmt_earned->execute();
    $result_earned = $stmt_earned->get_result();
    if ($row_earned = $result_earned->fetch_assoc()) {
        $total_earned_hours = $row_earned['total_earned'] ?? 0.0;
    }
    $stmt_earned->close();
} else {
    $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการคำนวณชั่วโมงสะสม: ' . htmlspecialchars($mysqli->error) . '</p>';
}

// --- Calculate Remaining Hours ---
$remaining_hours = $required_hours - $total_earned_hours;
$remaining_hours = max(0, $remaining_hours);

// --- Calculate Percentages ---
$earned_percentage = 0;
$remaining_percentage = 0;
if ($required_hours > 0) {
    $earned_percentage = round(($total_earned_hours / $required_hours) * 100);
    $earned_percentage = min(100, $earned_percentage);
    $remaining_percentage = 100 - $earned_percentage;
} else {
    $remaining_percentage = ($total_earned_hours <= 0) ? 0 : 100;
    $earned_percentage = ($total_earned_hours > 0) ? 100 : 0;
}

// --- Fetch Attendance History (เหมือน student_history.php) ---
$attendance_history = [];
$sql_attendance = "SELECT a.name, a.start_datetime, att.attendance_status, att.hours_earned
                   FROM activity_attendance att
                   JOIN activities a ON att.activity_id = a.id
                   WHERE att.student_user_id = ?
                   ORDER BY a.start_datetime DESC";
$stmt_attendance = $mysqli->prepare($sql_attendance);
if ($stmt_attendance) {
    $stmt_attendance->bind_param('i', $student_user_id_target);
    $stmt_attendance->execute();
    $result_attendance = $stmt_attendance->get_result();
    while ($row = $result_attendance->fetch_assoc()) {
        $attendance_history[] = $row;
    }
    $stmt_attendance->close();
} else {
    $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงประวัติการเข้าร่วม: ' . htmlspecialchars($mysqli->error) . '</p>';
}

// --- จัดการ Message จาก Session ---
if (isset($_SESSION['form_message'])) {
    $message = $_SESSION['form_message'];
    unset($_SESSION['form_message']);
}

// Function to format datetime (ถ้ายังไม่มี)
if (!function_exists('format_datetime_th')) {
    function format_datetime_th($datetime_str, $include_time = false)
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
// Function to format status (ถ้ายังไม่มี)
if (!function_exists('format_attendance_status')) {
    function format_attendance_status($status)
    {
        switch ($status) {
            case 'attended':
                return '<span class="badge badge-sm bg-gradient-success">เข้าร่วม</span>';
            case 'absent':
                return '<span class="badge badge-sm bg-gradient-danger">ไม่เข้าร่วม</span>';
            default:
                return '<span class="badge badge-sm bg-gradient-light text-dark">' . htmlspecialchars($status) . '</span>';
        }
    }
}

?>

<div class="container-fluid py-4">

    <?php if (!empty($message)) : ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-dismissible text-white fade show <?php echo (strpos($message, 'success') !== false || strpos($message, 'สำเร็จ') !== false) ? 'alert-success bg-gradient-success' : ((strpos($message, 'warning') !== false || strpos($message, 'เตือน') !== false) ? 'alert-warning bg-gradient-warning' : 'alert-danger bg-gradient-danger'); ?>" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close p-3" data-bs-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="page-header min-height-100 border-radius-xl mt-4" style="background-image: url('https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80');">
        <span class="mask bg-gradient-primary opacity-6"></span>
    </div>
    <div class="card card-body mx-3 mx-md-4 mt-n6">
        <div class="row gx-4 mb-2">
            <div class="col-auto">
                <div class="avatar avatar-xl position-relative bg-gradient-secondary border-radius-lg d-flex align-items-center justify-content-center">
                    <i class="material-symbols-rounded text-white fs-1">account_circle</i>
                </div>
            </div>
            <div class="col-auto my-auto">
                <div class="h-100">
                    <h5 class="mb-1">
                        <?php echo htmlspecialchars($student_target_info['first_name'] . ' ' . $student_target_info['last_name']); ?>
                    </h5>
                    <p class="mb-0 font-weight-normal text-sm">
                        รหัสนักศึกษา: <?php echo htmlspecialchars($student_target_info['student_id_number']); ?>
                    </p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 my-sm-auto ms-sm-auto me-sm-0 mx-auto mt-3 text-end">
                <a href="index.php?page=advisor_summary" class="btn btn-sm btn-outline-secondary mb-0">
                    <i class="material-symbols-rounded text-sm position-relative">arrow_back</i>&nbsp;
                    กลับหน้ารายชื่อ
                </a>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-xl-4 col-sm-6 mb-xl-0 mb-4">
            <div class="card">
                <div class="card-header p-2 ps-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-sm mb-0 text-capitalize">ชั่วโมงสะสม</p>
                            <h4 class="mb-0"><?php echo number_format($total_earned_hours, 0); ?></h4>
                        </div>
                        <div class="icon icon-md icon-shape bg-gradient-info shadow-dark shadow text-center border-radius-lg">
                            <i class="material-symbols-rounded opacity-10">task_alt</i>
                        </div>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-2 ps-3">
                    <p class="mb-0 text-sm">
                        <span class="text-<?php echo ($earned_percentage >= 50) ? 'success' : 'secondary'; ?> font-weight-bolder">
                            <?php echo $earned_percentage; ?>%
                        </span>
                        ของชั่วโมงที่ต้องเก็บ
                    </p>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-sm-6 mb-xl-0 mb-4">
            <div class="card">
                <div class="card-header p-2 ps-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-sm mb-0 text-capitalize">ชั่วโมงที่ต้องเก็บ</p>
                            <h4 class="mb-0"><?php echo number_format($required_hours, 0); ?></h4>
                        </div>
                        <div class="icon icon-md icon-shape bg-gradient-primary shadow-dark shadow text-center border-radius-lg">
                            <i class="material-symbols-rounded opacity-10">schedule</i>
                        </div>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-2 ps-3">
                    <p class="mb-0 text-sm">&nbsp;</p>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-sm-6 mb-xl-0 mb-4">
            <div class="card">
                <div class="card-header p-2 ps-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-sm mb-0 text-capitalize">ชั่วโมงที่ยังขาด</p>
                            <h4 class="mb-0"><?php echo number_format($remaining_hours, 0); ?></h4>
                        </div>
                        <div class="icon icon-md icon-shape bg-gradient-warning shadow-dark shadow text-center border-radius-lg">
                            <i class="material-symbols-rounded opacity-10">hourglass_empty</i>
                        </div>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-2 ps-3">
                    <p class="mb-0 text-sm">
                        <span class="text-<?php echo ($remaining_percentage <= 50) ? 'warning' : 'danger'; ?> font-weight-bolder">
                            <?php echo $remaining_percentage; ?>%
                        </span>
                        ที่ยังต้องเก็บเพิ่ม
                    </p>
                </div>
            </div>
        </div>
    </div>


    <div class="row mt-4">
        <div class="col-12">
            <div class="card my-4">
                <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div class="bg-gradient-secondary shadow-secondary border-radius-lg pt-4 pb-3">
                        <h6 class="text-white text-capitalize ps-3">ประวัติการเข้าร่วมกิจกรรมของนักศึกษา</h6>
                    </div>
                </div>
                <div class="card-body px-0 pb-2">
                    <div class="table-responsive p-0">
                        <table class="table align-items-center mb-0">
                            <thead>
                                <tr>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ชื่อกิจกรรม</th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">วันที่เริ่มกิจกรรม</th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">สถานะการเข้าร่วม</th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ชั่วโมงที่ได้รับ/หัก</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($attendance_history)) : ?>
                                    <?php foreach ($attendance_history as $attendance) : ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex px-2 py-1">
                                                    <div class="d-flex flex-column justify-content-center">
                                                        <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($attendance['name']); ?></h6>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="align-middle text-center text-sm">
                                                <span class="text-secondary text-xs font-weight-bold"><?php echo format_datetime_th($attendance['start_datetime'], true); ?></span>
                                            </td>
                                            <td class="align-middle text-center text-sm">
                                                <?php echo format_attendance_status($attendance['attendance_status']); ?>
                                            </td>
                                            <td class="align-middle text-center text-sm">
                                                <?php
                                                $hours = $attendance['hours_earned'];
                                                $hour_class = 'text-success';
                                                if ($hours < 0) {
                                                    $hour_class = 'text-danger';
                                                } elseif ($hours == 0 && $attendance['attendance_status'] !== 'attended') {
                                                    $hour_class = 'text-secondary';
                                                }
                                                ?>
                                                <span class="text-sm font-weight-bold <?php echo $hour_class; ?>"><?php echo number_format($hours, 0); ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="4" class="text-center">ยังไม่มีประวัติการเข้าร่วมกิจกรรม</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>