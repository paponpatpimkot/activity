<?php
// ========================================================================
// ไฟล์: advisor_attendance_select_activity.php (เนื้อหาสำหรับ include)
// หน้าที่: แสดงรายการกิจกรรมให้ Advisor เลือกเพื่อเช็คชื่อนักศึกษาในที่ปรึกษา
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php ($mysqli), Authorization check (Role Advisor) ---
// --- และ require_once 'includes/functions.php' ได้ทำไปแล้วใน Controller หลัก ---
// if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) { exit('Unauthorized'); }

$advisor_user_id = $_SESSION['user_id'];
$page_title = "เลือกกิจกรรมเพื่อเช็คชื่อนักศึกษาในที่ปรึกษา";
$message = '';

// --- จัดการ Message จาก Session (ถ้ามีการ redirect มา) ---
if (isset($_SESSION['form_message'])) {
    $message = $_SESSION['form_message'];
    unset($_SESSION['form_message']);
}

// --- Fetch Activities relevant to this advisor's students ---
$activities_list = [];
// Query กิจกรรมที่มีนักศึกษาในที่ปรึกษาของ Advisor คนนี้จองไว้ และถึงเวลาเช็คชื่อได้
$sql = "SELECT DISTINCT
            a.id,
            a.name,
            a.start_datetime,
            a.location,
            au.name as organizer_unit_name
        FROM activities a
        JOIN activity_bookings ab ON a.id = ab.activity_id
        JOIN students s ON ab.student_user_id = s.user_id
        JOIN student_groups sg ON s.group_id = sg.id
        JOIN group_advisors ga ON sg.id = ga.group_id
        LEFT JOIN activity_units au ON a.organizer_unit_id = au.id
        WHERE ga.advisor_user_id = ?
          AND ab.status = 'booked'
          AND (
                a.start_datetime <= NOW() -- กิจกรรมที่เริ่มแล้ว หรือกำลังเริ่ม
                OR a.end_datetime BETWEEN DATE_SUB(NOW(), INTERVAL 1 DAY) AND NOW() -- หรือเพิ่งจบไปไม่นาน
              )
          AND NOT EXISTS ( -- *อาจจะเพิ่มเงื่อนไขนี้ ถ้าต้องการแสดงเฉพาะกิจกรรมที่ยังเช็คชื่อนักศึกษาในที่ปรึกษาคนนี้ไม่ครบ*
                SELECT 1 FROM activity_attendance aa
                WHERE aa.activity_id = a.id
                AND aa.student_user_id = s.user_id
          )
        ORDER BY a.start_datetime DESC";

$stmt = $mysqli->prepare($sql);
if ($stmt) {
    $stmt->bind_param('i', $advisor_user_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $activities_list[] = $row;
        }
        $result->free();
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
                                            <td>
                                                <div class="d-flex px-2 py-1">
                                                    <div class="d-flex flex-column justify-content-center">
                                                        <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($activity['name']); ?></h6>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0"><?php echo htmlspecialchars($activity['organizer_unit_name'] ?? 'N/A'); ?></p>
                                            </td>
                                            <td class="align-middle text-center text-sm">
                                                <span class="text-secondary text-xs font-weight-bold"><?php echo format_datetime_th($activity['start_datetime'], true); ?></span>
                                            </td>
                                            <td class="align-middle text-center">
                                                <a href="index.php?page=advisor_attendance_record&activity_id=<?php echo $activity['id']; ?>" class="btn btn-primary btn-sm bg-gradient-primary mb-0">
                                                    เช็คชื่อนักศึกษา
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="4" class="text-center p-3">ไม่พบกิจกรรมที่สามารถเช็คชื่อนักศึกษาในที่ปรึกษาได้ในขณะนี้</td>
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
