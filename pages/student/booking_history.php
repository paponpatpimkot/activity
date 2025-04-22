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


// --- Fetch Booking History (แก้ไข Query: ดึงเฉพาะที่ยังไม่เช็คชื่อ) ---
$booking_history = [];
// *** เพิ่มเงื่อนไข NOT EXISTS เพื่อไม่แสดงกิจกรรมที่เช็คชื่อแล้ว ***
$sql_bookings = "SELECT b.id as booking_id, a.name, a.start_datetime, b.status, b.booking_time
                 FROM activity_bookings b
                 JOIN activities a ON b.activity_id = a.id
                 WHERE b.student_user_id = ?
                 AND NOT EXISTS (
                     SELECT 1 FROM activity_attendance att
                     WHERE att.student_user_id = b.student_user_id
                     AND att.activity_id = b.activity_id
                 )
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
</div>
