<?php
// ========================================================================
// ไฟล์: activities_list.php (เนื้อหาสำหรับ include)
// หน้าที่: แสดงรายการกิจกรรม, จัดการลบ, และลิงก์ไปยังหน้าเพิ่ม/แก้ไข
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php ($mysqli), และ Authorization check ---
// --- ได้ทำไปแล้วในไฟล์ Controller หลัก ---
// require 'db_connect.php'; // $mysqli should be available
// if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 4])) { exit('Unauthorized'); } // Allow Admin(1) or Staff(4)

$page_title = "จัดการกิจกรรม";
$message = ''; // สำหรับแสดงข้อความแจ้งเตือน
$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['role_id'];


// --- Handle Delete Request ---
// *** ส่วนนี้ควรจะย้ายไปประมวลผลใน Controller หลัก (index.php) ถ้าใช้ PRG Pattern ***
// *** แต่ถ้าใช้ ob_start() สามารถเก็บไว้ที่นี่ได้ ***
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = (int)$_POST['delete_id'];

    // *** ตรวจสอบ Foreign Key ก่อนลบ ***
    $can_delete = true;
    $error_detail = '';

    // 1. Check activity_bookings
    $check_sql_booking = "SELECT COUNT(*) as count FROM activity_bookings WHERE activity_id = ?";
    $stmt_check_booking = $mysqli->prepare($check_sql_booking);
    $stmt_check_booking->bind_param('i', $delete_id);
    $stmt_check_booking->execute();
    $result_check_booking = $stmt_check_booking->get_result();
    $row_check_booking = $result_check_booking->fetch_assoc();
    $stmt_check_booking->close();
    if ($row_check_booking['count'] > 0) {
        $can_delete = false;
        $error_detail .= ' มีข้อมูลการจอง';
    }

    // 2. Check activity_attendance
    $check_sql_attendance = "SELECT COUNT(*) as count FROM activity_attendance WHERE activity_id = ?";
    $stmt_check_attendance = $mysqli->prepare($check_sql_attendance);
    $stmt_check_attendance->bind_param('i', $delete_id);
    $stmt_check_attendance->execute();
    $result_check_attendance = $stmt_check_attendance->get_result();
    $row_check_attendance = $result_check_attendance->fetch_assoc();
    $stmt_check_attendance->close();
    if ($row_check_attendance['count'] > 0) {
        $can_delete = false;
        $error_detail .= ($error_detail ? ' และ' : '') . ' มีข้อมูลการเข้าร่วม';
    }

    if (!$can_delete) {
        // ถ้าใช้ ob_start() อาจจะตั้งค่า message ใน session แล้ว redirect กลับมาที่ list
        $_SESSION['form_message'] = '<p class="alert alert-danger text-white">ไม่สามารถลบกิจกรรมนี้ได้ เนื่องจาก:' . $error_detail . '</p>';
        header('Location: index.php?page=activities_list');
        exit;
        // $message = '<p class="alert alert-danger text-white">ไม่สามารถลบกิจกรรมนี้ได้ เนื่องจาก:' . $error_detail . '</p>';
    } else {
        // --- ทำการลบ (ควรอยู่ใน Transaction) ---
        $mysqli->begin_transaction();
        try {
            // ลบข้อมูลจากตารางเชื่อมโยงก่อน (activity_eligible_majors)
            $sql_delete_eligible = "DELETE FROM activity_eligible_majors WHERE activity_id = ?";
            $stmt_delete_eligible = $mysqli->prepare($sql_delete_eligible);
            $stmt_delete_eligible->bind_param('i', $delete_id);
            $stmt_delete_eligible->execute();
            $stmt_delete_eligible->close();

            // ลบจากตาราง activities
            $sql_delete_activity = "DELETE FROM activities WHERE id = ?";
            $stmt_delete_activity = $mysqli->prepare($sql_delete_activity);
            $stmt_delete_activity->bind_param('i', $delete_id);
            $stmt_delete_activity->execute();
            $stmt_delete_activity->close();

            $mysqli->commit();
            $_SESSION['form_message'] = '<p class="alert alert-success text-white">ลบกิจกรรมสำเร็จแล้ว</p>';
            header('Location: index.php?page=activities_list');
            exit;
            // $message = '<p class="alert alert-success text-white">ลบกิจกรรมสำเร็จแล้ว</p>';

        } catch (mysqli_sql_exception $exception) {
            $mysqli->rollback();
            $_SESSION['form_message'] = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการลบกิจกรรม: ' . $exception->getMessage() . '</p>';
            header('Location: index.php?page=activities_list');
            exit;
            // $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการลบกิจกรรม: ' . $exception->getMessage() . '</p>';
        }
    }
}

// --- Fetch Activities Data (ปรับปรุง Query ตาม Role) ---
$activities_data = [];
$base_sql = "SELECT
                a.id, a.name, a.start_datetime, a.end_datetime, a.location,
                a.hours_participant, a.max_participants,
                au.name as organizer_unit_name
            FROM activities a
            LEFT JOIN activity_units au ON a.organizer_unit_id = au.id";

$where_clauses = [];
$params = [];
$types = "";

// --- เพิ่มเงื่อนไขสำหรับ Staff ---
if ($current_user_role == 4) { // ถ้าเป็น Staff
    $where_clauses[] = "a.created_by_user_id = ?";
    $params[] = $current_user_id;
    $types .= "i";
}

// --- รวม WHERE clauses ---
if (!empty($where_clauses)) {
    $sql = $base_sql . " WHERE " . implode(" AND ", $where_clauses);
} else {
    $sql = $base_sql;
}

$sql .= " ORDER BY a.start_datetime DESC"; // เรียงตามวันที่เริ่มล่าสุดก่อน

$stmt = $mysqli->prepare($sql);

if ($stmt) {
    // Bind parameter ถ้ามี (สำหรับ Staff)
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $activities_data[] = $row;
        }
        $result->free();
    } else {
        $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการ Execute Query กิจกรรม: ' . htmlspecialchars($stmt->error) . '</p>';
    }
    $stmt->close();
} else {
    $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการ Prepare Query กิจกรรม: ' . htmlspecialchars($mysqli->error) . '</p>';
}


// --- จัดการ Message จาก Session (ถ้ามีการ redirect มา) ---
if (isset($_SESSION['form_message'])) {
    $message = $_SESSION['form_message'];
    unset($_SESSION['form_message']);
}

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
                    <div class="bg-gradient-info shadow-dark border-radius-lg pt-4 pb-3">
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

                    <div class="px-4 mb-3">
                        <a href="index.php?page=activity_form" class="btn btn-success bg-gradient-success mb-0">
                            <i class="material-symbols-rounded text-sm">add_circle</i>&nbsp;&nbsp;เพิ่มกิจกรรมใหม่
                        </a>
                    </div>

                    <div class="table-responsive p-0">
                        <table class="table align-items-center mb-0">
                            <thead>
                                <tr>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ชื่อกิจกรรม</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">หน่วยงานจัด</th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">วันเวลาเริ่ม</th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">วันเวลาสิ้นสุด</th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">สถานที่</th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ชม.(ผู้เข้าร่วม)</th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">จำนวนรับ</th>
                                    <th class="text-secondary opacity-7"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($activities_data)) : ?>
                                    <?php foreach ($activities_data as $activity) : ?>
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
                                                <span class="text-secondary text-xs font-weight-bold"><?php echo format_datetime_th($activity['end_datetime']); ?></span>
                                            </td>
                                            <td class="align-middle text-center text-sm">
                                                <span class="text-secondary text-xs font-weight-bold"><?php echo htmlspecialchars($activity['location'] ?? '-'); ?></span>
                                            </td>
                                            <td class="align-middle text-center text-sm">
                                                <span class="text-secondary text-xs font-weight-bold"><?php echo number_format($activity['hours_participant'] ?? 0, 0); ?></span>
                                            </td>
                                            <td class="align-middle text-center text-sm">
                                                <span class="text-secondary text-xs font-weight-bold"><?php echo is_null($activity['max_participants']) ? 'ไม่จำกัด' : htmlspecialchars($activity['max_participants']); ?></span>
                                            </td>
                                            <td class="align-middle">
                                                <a href="index.php?page=activity_form&id=<?php echo $activity['id']; ?>" class="btn btn-warning btn-sm mb-0" data-toggle="tooltip" data-original-title="แก้ไขข้อมูล">
                                                    <i class="material-icons text-sm">edit</i>
                                                </a>
                                                <form action="index.php?page=activities_list" method="post" style="display: inline;" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบกิจกรรมนี้?');">
                                                    <input type="hidden" name="delete_id" value="<?php echo $activity['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm mb-0" data-toggle="tooltip" data-original-title="ลบข้อมูล">
                                                        <i class="material-icons text-sm">delete</i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="8" class="text-center">
                                            <?php echo ($current_user_role == 4) ? 'ไม่พบกิจกรรมที่คุณสร้าง' : 'ยังไม่มีข้อมูลกิจกรรม'; ?>
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