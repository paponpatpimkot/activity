<?php
// ========================================================================
// ไฟล์: student_history.php (เนื้อหาสำหรับ include)
// หน้าที่: แสดงสรุปชั่วโมง, ประวัติการจอง, ประวัติการเข้าร่วมกิจกรรมของนักศึกษา
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php ($mysqli), และ Authorization check ---
// --- ได้ทำไปแล้วในไฟล์ Controller หลัก และมีการกำหนดตัวแปร $_SESSION['user_id'], $_SESSION['role_id'] ---

// if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 3) { exit('Unauthorized'); }

$student_user_id = $_SESSION['user_id'];
$message = ''; // สำหรับแสดงข้อความแจ้งเตือน

// --- Handle Cancel Booking Request ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking_id'])) {
    $booking_id_to_cancel = filter_input(INPUT_POST, 'cancel_booking_id', FILTER_VALIDATE_INT);

    if ($booking_id_to_cancel) {
        // ตรวจสอบว่าเป็น Booking ของนักศึกษาคนนี้จริง และกิจกรรมยังไม่เริ่ม
        $sql_check_cancel = "SELECT b.id, a.start_datetime, a.name
                             FROM activity_bookings b
                             JOIN activities a ON b.activity_id = a.id
                             WHERE b.id = ? AND b.student_user_id = ? AND b.status = 'booked' AND a.start_datetime > NOW()";
        $stmt_check_cancel = $mysqli->prepare($sql_check_cancel);
        $stmt_check_cancel->bind_param('ii', $booking_id_to_cancel, $student_user_id);
        $stmt_check_cancel->execute();
        $result_check_cancel = $stmt_check_cancel->get_result();

        if ($booking_to_cancel = $result_check_cancel->fetch_assoc()) {
            // สามารถยกเลิกได้
            $sql_update_cancel = "UPDATE activity_bookings SET status = 'cancelled' WHERE id = ?";
            $stmt_update_cancel = $mysqli->prepare($sql_update_cancel);
            $stmt_update_cancel->bind_param('i', $booking_id_to_cancel);
            if ($stmt_update_cancel->execute()) {
                 $_SESSION['form_message'] = '<p class="alert alert-success text-white">ยกเลิกการจองกิจกรรม "' . htmlspecialchars($booking_to_cancel['name']) . '" สำเร็จแล้ว</p>';
            } else {
                 $_SESSION['form_message'] = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการยกเลิกการจอง: ' . htmlspecialchars($stmt_update_cancel->error) . '</p>';
            }
            $stmt_update_cancel->close();
        } else {
             $_SESSION['form_message'] = '<p class="alert alert-warning text-white">ไม่สามารถยกเลิกการจองได้ (อาจจะเลยเวลา หรือข้อมูลไม่ถูกต้อง)</p>';
        }
        $stmt_check_cancel->close();

        // Redirect กลับมาหน้าเดิมเพื่อแสดง message และ refresh รายการ
        header('Location: index.php?page=student_history'); // หรือหน้าที่เหมาะสม
        exit;
    }
}


// --- Fetch Student Data ---
$student_info = null;
$required_hours = 0;
$sql_student = "SELECT required_hours FROM students WHERE user_id = ?";
$stmt_student = $mysqli->prepare($sql_student);
if ($stmt_student) {
    $stmt_student->bind_param('i', $student_user_id);
    $stmt_student->execute();
    $result_student = $stmt_student->get_result();
    if ($student_info = $result_student->fetch_assoc()) {
        $required_hours = $student_info['required_hours'];
    } else {
         $message .= '<p class="alert alert-danger text-white">ไม่พบข้อมูลนักศึกษา</p>';
    }
    $stmt_student->close();
} else {
     $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงข้อมูลนักศึกษา: ' . htmlspecialchars($mysqli->error) . '</p>';
}


// --- Calculate Earned Hours (จาก Attendance) ---
$total_earned_hours = 0.0;
$sql_earned = "SELECT SUM(hours_earned) as total_earned
               FROM activity_attendance
               WHERE student_user_id = ? AND attendance_status = 'attended'"; // นับเฉพาะที่เข้าร่วมจริง
$stmt_earned = $mysqli->prepare($sql_earned);
if ($stmt_earned) {
    $stmt_earned->bind_param('i', $student_user_id);
    $stmt_earned->execute();
    $result_earned = $stmt_earned->get_result();
    if ($row_earned = $result_earned->fetch_assoc()) {
        $total_earned_hours = $row_earned['total_earned'] ?? 0.0;
    }
    $stmt_earned->close();
} else {
     $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการคำนวณชั่วโมงสะสม: ' . htmlspecialchars($mysqli->error) . '</p>';
}

// --- Calculate Booked Hours (จาก Bookings ที่ยังไม่ Cancelled) ---
$total_booked_hours = 0.0;
$sql_booked = "SELECT SUM(a.hours_participant) as total_booked
               FROM activity_bookings b
               JOIN activities a ON b.activity_id = a.id
               WHERE b.student_user_id = ? AND b.status = 'booked'"; // นับเฉพาะที่จองไว้
$stmt_booked = $mysqli->prepare($sql_booked);
if ($stmt_booked) {
    $stmt_booked->bind_param('i', $student_user_id);
    $stmt_booked->execute();
    $result_booked = $stmt_booked->get_result();
    if ($row_booked = $result_booked->fetch_assoc()) {
        $total_booked_hours = $row_booked['total_booked'] ?? 0.0;
    }
    $stmt_booked->close();
} else {
     $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการคำนวณชั่วโมงที่จอง: ' . htmlspecialchars($mysqli->error) . '</p>';
}


// --- Calculate Remaining Hours ---
$remaining_hours = $required_hours - $total_earned_hours;
$remaining_hours = max(0, $remaining_hours); // ทำให้ไม่ติดลบ

// --- Calculate Percentages ---
$earned_percentage = 0;
$remaining_percentage = 0;
if ($required_hours > 0) {
    $earned_percentage = round(($total_earned_hours / $required_hours) * 100);
    $earned_percentage = min(100, $earned_percentage);
    $remaining_percentage = 100 - $earned_percentage;
} else {
     $remaining_percentage = ($total_earned_hours <= 0) ? 0 : 100;
}


// --- Fetch Booking History ---
$booking_history = [];
$sql_bookings = "SELECT b.id as booking_id, a.name, a.start_datetime, b.status, b.booking_time
                 FROM activity_bookings b
                 JOIN activities a ON b.activity_id = a.id
                 WHERE b.student_user_id = ?
                 ORDER BY b.booking_time DESC";
$stmt_bookings = $mysqli->prepare($sql_bookings);
if ($stmt_bookings) {
     $stmt_bookings->bind_param('i', $student_user_id);
     $stmt_bookings->execute();
     $result_bookings = $stmt_bookings->get_result();
     while ($row = $result_bookings->fetch_assoc()) {
         $booking_history[] = $row;
     }
     $stmt_bookings->close();
} else {
      $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงประวัติการจอง: ' . htmlspecialchars($mysqli->error) . '</p>';
}

// --- Fetch Attendance History ---
$attendance_history = [];
$sql_attendance = "SELECT a.name, a.start_datetime, att.attendance_status, att.hours_earned
                   FROM activity_attendance att
                   JOIN activities a ON att.activity_id = a.id
                   WHERE att.student_user_id = ?
                   ORDER BY a.start_datetime DESC";
$stmt_attendance = $mysqli->prepare($sql_attendance);
 if ($stmt_attendance) {
     $stmt_attendance->bind_param('i', $student_user_id);
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

// --- Function to format datetime (ปรับปรุง) ---
if (!function_exists('format_datetime_th')) {
    /**
     * แปลง datetime string เป็นรูปแบบภาษาไทย
     * @param string|null $datetime_str วันที่เวลาในรูปแบบที่ DateTime() รู้จัก
     * @param bool $include_time ต้องการแสดงเวลาด้วยหรือไม่ (true/false)
     * @return string วันที่เวลาภาษาไทย หรือ '-' ถ้าข้อมูลผิดพลาด
     */
    function format_datetime_th($datetime_str, $include_time = false) {
        if (empty($datetime_str)) return '-';
        try {
            $dt = new DateTime($datetime_str);
            $thai_months_short = [
                1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
                5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
                9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
            ];
            $day = $dt->format('d');
            $month_num = (int)$dt->format('n');
            $thai_month = $thai_months_short[$month_num] ?? '?';
            $buddhist_year = $dt->format('Y') + 543;

            $formatted_date = $day . ' ' . $thai_month . ' ' . $buddhist_year;

            if ($include_time) {
                $formatted_date .= ' ' . $dt->format('H:i'); // เพิ่มเวลาถ้าต้องการ
            }

            return $formatted_date;
        } catch (Exception $e) {
            error_log("Error formatting date: " . $e->getMessage()); // Log error for debugging
            return '-'; // คืนค่า '-' ถ้าเกิดข้อผิดพลาด
        }
    }
}

// Function to format status (ตัวอย่าง)
function format_booking_status($status) {
    if ($status === 'booked') return '<span class="badge badge-sm bg-gradient-primary">จองแล้ว</span>';
    if ($status === 'cancelled') return '<span class="badge badge-sm bg-gradient-secondary">ยกเลิกแล้ว</span>';
    return '<span class="badge badge-sm bg-gradient-light text-dark">' . htmlspecialchars($status) . '</span>';
}
function format_attendance_status($status) {
     switch ($status) {
        case 'attended': return '<span class="badge badge-sm bg-gradient-success">เข้าร่วม</span>';
        case 'absent': return '<span class="badge badge-sm bg-gradient-danger">ไม่เข้าร่วม</span>';
        case 'absent_with_penalty': return '<span class="badge badge-sm bg-gradient-danger">ไม่เข้าร่วม (หักชม.)</span>';
        case 'excused': return '<span class="badge badge-sm bg-gradient-warning text-dark">ลา</span>';
        default: return '<span class="badge badge-sm bg-gradient-light text-dark">' . htmlspecialchars($status) . '</span>';
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


    <div class="row">
        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
          <div class="card">
            <div class="card-header p-3 pt-2">
              <div class="icon icon-lg icon-shape bg-gradient-info shadow-info text-center border-radius-xl mt-n4 position-absolute">
                <i class="material-icons opacity-10">event_available</i>
              </div>
              <div class="text-end pt-1">
                <p class="text-sm mb-0 text-capitalize">ชั่วโมงสะสม</p>
                <h4 class="mb-0"><?php echo number_format($total_earned_hours, 1); ?></h4>
              </div>
            </div>
            <hr class="dark horizontal my-0">
            <div class="card-footer p-3">
               <p class="mb-0">
                   <span class="text-<?php echo ($earned_percentage >= 50) ? 'success' : 'secondary'; ?> text-sm font-weight-bolder">
                       <?php echo $earned_percentage; ?>%
                   </span>
                   ของชั่วโมงที่ต้องเก็บ
               </p>
            </div>
          </div>
        </div>
        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
          <div class="card">
            <div class="card-header p-3 pt-2">
              <div class="icon icon-lg icon-shape bg-gradient-primary shadow-primary text-center border-radius-xl mt-n4 position-absolute">
                <i class="material-icons opacity-10">schedule</i>
              </div>
              <div class="text-end pt-1">
                <p class="text-sm mb-0 text-capitalize">ชั่วโมงที่ต้องเก็บ</p>
                <h4 class="mb-0"><?php echo number_format($required_hours, 1); ?></h4>
              </div>
            </div>
             <hr class="dark horizontal my-0">
             <div class="card-footer p-3" style="visibility: hidden;"> <p class="mb-0">&nbsp;</p>
             </div>
          </div>
        </div>
         <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
          <div class="card">
            <div class="card-header p-3 pt-2">
              <div class="icon icon-lg icon-shape bg-gradient-warning shadow-warning text-center border-radius-xl mt-n4 position-absolute">
                <i class="material-icons opacity-10">hourglass_empty</i>
              </div>
              <div class="text-end pt-1">
                <p class="text-sm mb-0 text-capitalize">ชั่วโมงที่ยังขาด</p>
                <h4 class="mb-0"><?php echo number_format($remaining_hours, 1); ?></h4>
              </div>
            </div>
             <hr class="dark horizontal my-0">
             <div class="card-footer p-3">
               <p class="mb-0">
                   <span class="text-<?php echo ($remaining_percentage <= 50) ? 'warning' : 'danger'; ?> text-sm font-weight-bolder">
                       <?php echo $remaining_percentage; ?>%
                   </span>
                   ที่ยังต้องเก็บเพิ่ม
               </p>
             </div>
          </div>
        </div>
         <div class="col-xl-3 col-sm-6">
          <div class="card">
            <div class="card-header p-3 pt-2">
              <div class="icon icon-lg icon-shape bg-gradient-secondary shadow-secondary text-center border-radius-xl mt-n4 position-absolute">
                <i class="material-icons opacity-10">bookmark_added</i>
              </div>
              <div class="text-end pt-1">
                <p class="text-sm mb-0 text-capitalize">ชั่วโมงที่จองไว้</p>
                <h4 class="mb-0"><?php echo number_format($total_booked_hours, 1); ?></h4>
              </div>
            </div>
             <hr class="dark horizontal my-0">
             <div class="card-footer p-3" style="visibility: hidden;"> <p class="mb-0">&nbsp;</p>
             </div>
          </div>
        </div>
      </div>


    <div class="row mt-4">
        <div class="col-12">
            <div class="card my-4">
                <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div class="bg-gradient-primary shadow-primary border-radius-lg pt-4 pb-3">
                        <h6 class="text-white text-capitalize ps-3">ประวัติการจองกิจกรรม</h6>
                    </div>
                </div>
                <div class="card-body px-0 pb-2">
                     <div class="table-responsive p-0">
                        <table class="table align-items-center mb-0">
                            <thead>
                                <tr>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ชื่อกิจกรรม</th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">วันที่เริ่มกิจกรรม</th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">เวลาที่จอง</th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">สถานะ</th>
                                    <th class="text-secondary opacity-7"></th>
                                </tr>
                            </thead>
                             <tbody>
                                <?php if (!empty($booking_history)) : ?>
                                    <?php foreach ($booking_history as $booking) : ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex px-2 py-1">
                                                    <div class="d-flex flex-column justify-content-center">
                                                        <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($booking['name']); ?></h6>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="align-middle text-center text-sm">
                                                <span class="text-secondary text-xs font-weight-bold"><?php echo format_datetime_th($booking['start_datetime'], true); ?></span>
                                            </td>
                                            <td class="align-middle text-center text-sm">
                                                 <span class="text-secondary text-xs font-weight-bold"><?php echo format_datetime_th($booking['booking_time'], true); ?></span>
                                            </td>
                                             <td class="align-middle text-center text-sm">
                                                <?php echo format_booking_status($booking['status']); ?>
                                            </td>
                                            <td class="align-middle text-center">
                                                <?php
                                                // แสดงปุ่มยกเลิกเฉพาะรายการที่ 'booked' และกิจกรรมยังไม่เริ่ม
                                                $can_cancel = false;
                                                if ($booking['status'] === 'booked') {
                                                    try {
                                                        $start_dt = new DateTime($booking['start_datetime']);
                                                        if ($start_dt > new DateTime()) {
                                                            $can_cancel = true;
                                                        }
                                                    } catch (Exception $e) {}
                                                }
                                                ?>
                                                <?php if ($can_cancel): ?>
                                                    <form action="index.php?page=student_history" method="post" style="display: inline;" onsubmit="return confirm('คุณต้องการยกเลิกการจองกิจกรรมนี้ใช่หรือไม่?');">
                                                        <input type="hidden" name="cancel_booking_id" value="<?php echo $booking['booking_id']; ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm bg-gradient-danger mb-0">ยกเลิกจอง</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="5" class="text-center">ยังไม่มีประวัติการจองกิจกรรม</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

     <div class="row mt-4">
        <div class="col-12">
            <div class="card my-4">
                <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div class="bg-gradient-secondary shadow-secondary border-radius-lg pt-4 pb-3">
                        <h6 class="text-white text-capitalize ps-3">ประวัติการเข้าร่วมกิจกรรม</h6>
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
                                                    $hour_class = 'text-success'; // สีเขียวสำหรับค่าบวก
                                                    if ($hours < 0) {
                                                        $hour_class = 'text-danger'; // สีแดงสำหรับค่าลบ
                                                    } elseif ($hours == 0 && $attendance['attendance_status'] !== 'attended') {
                                                         $hour_class = 'text-secondary'; // สีเทาสำหรับ 0 (กรณีไม่เข้า)
                                                    }
                                                 ?>
                                                <span class="text-sm font-weight-bold <?php echo $hour_class; ?>"><?php echo number_format($hours, 1); ?></span>
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
