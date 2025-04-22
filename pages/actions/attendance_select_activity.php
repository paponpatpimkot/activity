<?php
// ========================================================================
// ไฟล์: attendance_select_activity.php (เนื้อหาสำหรับ include)
// หน้าที่: แสดงรายการกิจกรรมให้ Admin/Staff เลือกเพื่อเช็คชื่อ (ปรับปรุงตาม Role)
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php ($mysqli), และ Authorization check (Admin or Staff) ---
// --- ได้ทำไปแล้วในไฟล์ Controller หลัก ---
// require 'db_connect.php'; // $mysqli should be available
// if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 4])) { exit('Unauthorized'); } // Allow Admin(1) or Staff(4)

$page_title = "เลือกกิจกรรมเพื่อเช็คชื่อ";
$message = ''; // สำหรับแสดงข้อความแจ้งเตือน
$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['role_id'];

// --- จัดการ Message จาก Session (ถ้ามีการ redirect มาจากหน้า record) ---
if (isset($_SESSION['form_message'])) {
    $message = $_SESSION['form_message'];
    unset($_SESSION['form_message']); // เคลียร์ message หลังจากแสดงผล
}


// --- Fetch Activities (ปรับปรุง Query ตาม Role) ---
$activities_list = [];
$base_sql = "SELECT
                a.id, a.name, a.start_datetime, a.end_datetime, a.location,
                au.name as organizer_unit_name
            FROM activities a
            LEFT JOIN activity_units au ON a.organizer_unit_id = au.id
            WHERE (
                a.start_datetime <= NOW() -- กิจกรรมที่เริ่มแล้ว หรือกำลังเริ่ม
                OR a.end_datetime BETWEEN DATE_SUB(NOW(), INTERVAL 1 DAY) AND NOW() -- หรือเพิ่งจบไปไม่นาน
            )";

$params = [];
$types = "";

// --- เพิ่มเงื่อนไขสำหรับ Staff ---
if ($current_user_role == 4) { // ถ้าเป็น Staff
    $base_sql .= " AND a.created_by_user_id = ?";
    $params[] = $current_user_id;
    $types .= "i";
}

$sql = $base_sql . " ORDER BY a.start_datetime DESC";

$stmt = $mysqli->prepare($sql);

if ($stmt) {
    // Bind parameter ถ้ามี (สำหรับ Staff)
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }

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


// Function to format datetime (ถ้ายังไม่มี)
if (!function_exists('format_datetime_th')) {
    function format_datetime_th($datetime_str, $include_time = true)
    { // Default ให้แสดงเวลา
        if (empty($datetime_str)) return '-';
        try {
            $dt = new DateTime($datetime_str);
            $thai_months_short = [
                1 => 'ม.ค.',
                2 => 'ก.พ.',
                3 => 'มี.ค.',
                4 => 'เม.ย.',
                5 => 'พ.ค.',
                6 => 'มิ.ย.',
                7 => 'ก.ค.',
                8 => 'ส.ค.',
                9 => 'ก.ย.',
                10 => 'ต.ค.',
                11 => 'พ.ย.',
                12 => 'ธ.ค.'
            ];
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
                    </div>
                </div>
                <div class="card-body px-0 pb-2">

                    <?php if (!empty($message)) : ?>
                        <div class="alert alert-dismissible text-white fade show mx-4 <?php echo (strpos($message, 'success') !== false || strpos($message, 'สำเร็จ') !== false) ? 'alert-success bg-gradient-success' : ((strpos($message, 'warning') !== false || strpos($message, 'เตือน') !== false) ? 'alert-warning bg-gradient-warning' : 'alert-danger bg-gradient-danger'); ?>" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close p-3" data-bs-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <div class="table-responsive p-0">
                        <table class="table align-items-center mb-0">
                            <thead>
                                <tr>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ชื่อกิจกรรม</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">หน่วยงานจัด</th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">วันเวลาเริ่ม</th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">สถานที่</th>
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
                                                <span class="text-secondary text-xs font-weight-bold"><?php echo format_datetime_th($activity['start_datetime']); ?></span>
                                            </td>
                                            <td class="align-middle text-center text-sm">
                                                <span class="text-secondary text-xs font-weight-bold"><?php echo htmlspecialchars($activity['location'] ?? '-'); ?></span>
                                            </td>
                                            <td class="align-middle text-center">
                                                <a href="index.php?page=attendance_record&activity_id=<?php echo $activity['id']; ?>" class="btn btn-primary btn-sm bg-gradient-primary mb-0">
                                                    เช็คชื่อ
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="5" class="text-center">
                                            <?php echo ($current_user_role == 4) ? 'ไม่พบกิจกรรมที่คุณสร้าง หรือกิจกรรมยังไม่ถึงเวลาเช็คชื่อ' : 'ยังไม่มีกิจกรรมที่สามารถเช็คชื่อได้ในขณะนี้'; ?>
                                        </td>
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