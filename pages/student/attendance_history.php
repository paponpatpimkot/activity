<?php
// ========================================================================
// ไฟล์: booking_history.php (เนื้อหาสำหรับ include)
// หน้าที่: ประวัติการจอง
// ========================================================================

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

     <div class="row mt-4">
        <div class="col-12">
            <div class="card my-4">
                <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div class="bg-gradient-primary shadow-secondary border-radius-lg pt-4 pb-3">
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
