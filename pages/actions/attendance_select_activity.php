<?php
// ========================================================================
// ไฟล์: attendance_select_activity.php (เนื้อหาสำหรับ include)
// หน้าที่: แสดงรายการกิจกรรมให้ Admin/Staff เลือกเพื่อเช็คชื่อ (ปรับปรุงตาม Role และสิทธิ์ Staff)
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php ($mysqli), Authorization check (Admin or Staff) ---
// --- และ require_once 'includes/functions.php' ได้ทำไปแล้วในไฟล์ Controller หลัก ---
// if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 4])) { exit('Unauthorized'); }

$page_title = "เลือกกิจกรรมเพื่อเช็คชื่อ";
$message = '';
$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['role_id'];

// --- จัดการ Message จาก Session ---
if (isset($_SESSION['form_message'])) {
    $message = $_SESSION['form_message'];
    unset($_SESSION['form_message']);
}

// --- Fetch Activities ---
$activities_list = [];
$sql_base = "SELECT DISTINCT
                a.id, a.name, a.start_datetime, a.end_datetime, a.location,
                au.name as organizer_unit_name,
                a.attendance_recorder_type
            FROM activities a
            LEFT JOIN activity_units au ON a.organizer_unit_id = au.id";
$where_conditions = [];
$params = [];
$types = "";

// เงื่อนไขเวลาพื้นฐาน: กิจกรรมที่เริ่มแล้ว หรือกำลังจะเริ่ม หรือเพิ่งจบไปไม่นาน
$where_conditions[] = "(a.start_datetime <= NOW() OR a.end_datetime BETWEEN DATE_SUB(NOW(), INTERVAL 1 DAY) AND NOW())";

if ($current_user_role == 1) { // Admin
    // Admin สามารถเห็นกิจกรรมทั้งหมดที่สามารถเช็คชื่อได้ (อาจจะรวมถึง type 'advisor' ถ้าต้องการให้ Admin override)
    // สำหรับตอนนี้ Admin จะเห็นกิจกรรมที่ recorder_type เป็น 'system' หรือ 'advisor'
    // หรือถ้า Admin ควรเช็คได้เฉพาะ 'system' ให้แก้เป็น:
    // $where_conditions[] = "a.attendance_recorder_type = 'system'";
} elseif ($current_user_role == 4) { // Staff
    // Staff เห็นกิจกรรมที่:
    // 1. recorder_type = 'system' และ ไม่มีใครถูกระบุใน specific_recorders (Staff ทุกคนเช็คได้)
    // 2. recorder_type = 'system' และ Staff คนนี้ถูกระบุใน specific_recorders
    $where_conditions[] = "a.attendance_recorder_type = 'system'";
    $where_conditions[] = "(
                            NOT EXISTS (SELECT 1 FROM activity_specific_recorders asr_check WHERE asr_check.activity_id = a.id)
                            OR
                            EXISTS (SELECT 1 FROM activity_specific_recorders asr_user WHERE asr_user.activity_id = a.id AND asr_user.staff_user_id = ?)
                          )";
    $params[] = $current_user_id;
    $types .= "i";
} else {
    $activities_list = []; // ไม่ควรมี Role อื่นมาหน้านี้
}

$sql = $sql_base;
if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}
$sql .= " ORDER BY a.start_datetime DESC";

$stmt = $mysqli->prepare($sql);

if ($stmt) {
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            // SQL Query ได้กรองสิทธิ์สำหรับ Staff มาแล้ว ไม่จำเป็นต้องเช็คซ้ำใน PHP Loop
            $activities_list[] = $row;
        }
        if(isset($result)) $result->free();
    } else {
        $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการ Execute Query กิจกรรม: ' . htmlspecialchars($stmt->error) . '</p>';
    }
    $stmt->close();
} else {
     $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการ Prepare Query กิจกรรม: ' . htmlspecialchars($mysqli->error) . '</p>';
}

?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card my-4">
                <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div class="bg-gradient-info shadow-info border-radius-lg pt-4 pb-3">
                        <h6 class="text-white text-capitalize ps-3"><?php echo $page_title; ?></h6>
                    </div>
                </div>
                <div class="card-body px-0 pb-2">
                    <?php if (!empty($message)) : ?>
                        <div class="alert alert-dismissible text-white fade show mx-4 <?php echo (strpos($message, 'success') !== false || strpos($message, 'สำเร็จ') !== false) ? 'alert-success bg-gradient-success' : 'alert-danger bg-gradient-danger'; ?>" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close p-3" data-bs-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                    <?php endif; ?>

                    <div class="table-responsive p-0">
                        <table class="table align-items-center mb-0">
                            <thead>
                                <tr>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ชื่อกิจกรรม</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">หน่วยงานจัด</th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">วันเวลาเริ่ม</th>
                                    <th class="text-secondary opacity-7"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($activities_list)) : ?>
                                    <?php foreach ($activities_list as $activity) : ?>
                                        <tr>
                                            <td><div class="d-flex px-2 py-1"><div class="d-flex flex-column justify-content-center"><h6 class="mb-0 text-sm"><?php echo htmlspecialchars($activity['name']); ?></h6></div></div></td>
                                            <td><p class="text-xs font-weight-bold mb-0"><?php echo htmlspecialchars($activity['organizer_unit_name'] ?? 'N/A'); ?></p></td>
                                            <td class="align-middle text-center text-sm"><span class="text-secondary text-xs font-weight-bold"><?php echo format_datetime_th($activity['start_datetime'], true); ?></span></td>
                                            <td class="align-middle text-center">
                                                <a href="index.php?page=attendance_record&activity_id=<?php echo $activity['id']; ?>" class="btn btn-primary btn-sm bg-gradient-primary mb-0">
                                                    เช็คชื่อ
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr><td colspan="4" class="text-center p-3">ไม่พบกิจกรรมที่สามารถเช็คชื่อได้ในขณะนี้</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>