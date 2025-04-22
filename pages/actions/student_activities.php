<?php
// ========================================================================
// ไฟล์: student_activities.php (เนื้อหาสำหรับ include)
// หน้าที่: แสดงรายการกิจกรรมที่นักศึกษาสามารถจองได้ และจัดการการจอง
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php ($mysqli), และ Authorization check ---
// --- ได้ทำไปแล้วในไฟล์ Controller หลัก และมีการกำหนดตัวแปร $_SESSION['user_id'], $_SESSION['role_id'] ---
// --- ต้องดึง major_id ของนักศึกษามาเก็บใน Session หรือ Query ใหม่ที่นี่ ---

// if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 3) { exit('Unauthorized'); }

$student_user_id = $_SESSION['user_id'];
$message = ''; // สำหรับแสดงข้อความแจ้งเตือน
$student_major_id = null;

// --- ดึง Major ID ของนักศึกษา (แก้ไข Query ให้ถูกต้อง) ---
// ดึง major_id จาก student_groups โดย JOIN ผ่าน students.group_id
$sql_get_major = "SELECT sg.major_id
                  FROM students st
                  JOIN student_groups sg ON st.group_id = sg.id
                  WHERE st.user_id = ?";
$stmt_get_major = $mysqli->prepare($sql_get_major);
if ($stmt_get_major) {
    $stmt_get_major->bind_param('i', $student_user_id);
    $stmt_get_major->execute();
    $result_get_major = $stmt_get_major->get_result();
    if ($row_major = $result_get_major->fetch_assoc()) {
        $student_major_id = $row_major['major_id'];
    }
    $stmt_get_major->close();
} else {
    // เพิ่มการจัดการ Error กรณี prepare ล้มเหลว
    $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการเตรียมคำสั่งดึงข้อมูลสาขา: ' . htmlspecialchars($mysqli->error) . '</p>';
}


if (is_null($student_major_id) && empty($message)) { // ตรวจสอบ $message ด้วย เผื่อมี error จาก prepare
    // ไม่พบข้อมูลนักศึกษา, กลุ่ม หรือ Major ID -> อาจจะต้องแจ้งข้อผิดพลาดหรือจัดการกรณีนี้
    $message = '<p class="alert alert-danger text-white">ไม่พบข้อมูลสาขาวิชาของนักศึกษา (อาจจะยังไม่ได้กำหนดกลุ่มเรียน)</p>';
    // exit; // หรือแสดงข้อความแล้วหยุด
}


// --- Handle Booking Request ---
// (ส่วนนี้เหมือนเดิม)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_activity_id']) && !is_null($student_major_id)) {
    $activity_id_to_book = filter_input(INPUT_POST, 'book_activity_id', FILTER_VALIDATE_INT);

    if ($activity_id_to_book) {
        // --- ทำการตรวจสอบเงื่อนไขก่อนจอง ---
        $can_book = true;
        $booking_error = '';

        // 1. ตรวจสอบข้อมูลกิจกรรม และเวลา
        $sql_check_activity = "SELECT name, start_datetime, end_datetime, max_participants FROM activities WHERE id = ? AND start_datetime > NOW()";
        $stmt_check_activity = $mysqli->prepare($sql_check_activity);
        $stmt_check_activity->bind_param('i', $activity_id_to_book);
        $stmt_check_activity->execute();
        $result_check_activity = $stmt_check_activity->get_result();
        $activity_info = $result_check_activity->fetch_assoc();
        $stmt_check_activity->close();

        if (!$activity_info) {
            $can_book = false;
            $booking_error = 'ไม่พบกิจกรรมที่ต้องการจอง หรือกิจกรรมได้เริ่มไปแล้ว';
        } else {
            $start_dt_to_book = new DateTime($activity_info['start_datetime']);
            $end_dt_to_book = new DateTime($activity_info['end_datetime']);

            // 2. ตรวจสอบว่าจองกิจกรรมนี้ไปแล้วหรือยัง
            $sql_check_booked = "SELECT id FROM activity_bookings WHERE student_user_id = ? AND activity_id = ? AND status = 'booked'";
            $stmt_check_booked = $mysqli->prepare($sql_check_booked);
            $stmt_check_booked->bind_param('ii', $student_user_id, $activity_id_to_book);
            $stmt_check_booked->execute();
            if ($stmt_check_booked->get_result()->num_rows > 0) {
                $can_book = false;
                $booking_error = 'คุณได้จองกิจกรรมนี้ไปแล้ว';
            }
            $stmt_check_booked->close();

            // 3. ตรวจสอบเวลาทับซ้อนกับกิจกรรมอื่นที่จองไว้แล้ว
            if ($can_book) {
                $sql_check_overlap = "SELECT a.name, a.start_datetime, a.end_datetime
                                      FROM activity_bookings ab
                                      JOIN activities a ON ab.activity_id = a.id
                                      WHERE ab.student_user_id = ? AND ab.status = 'booked'
                                      AND ab.activity_id != ? -- ไม่ต้องเช็คกับกิจกรรมตัวเอง (เผื่อกรณีแก้ไขการจองในอนาคต)
                                      AND (
                                          (? < a.end_datetime) AND (? > a.start_datetime) -- เวลาใหม่ ทับซ้อนกับ เวลาเดิม
                                      )";
                $stmt_check_overlap = $mysqli->prepare($sql_check_overlap);
                // Bind ค่า datetime เป็น string
                $start_str = $start_dt_to_book->format('Y-m-d H:i:s');
                $end_str = $end_dt_to_book->format('Y-m-d H:i:s');
                // Parameters: student_id, activity_id_to_book, start_new, end_new
                $stmt_check_overlap->bind_param('iiss', $student_user_id, $activity_id_to_book, $start_str, $end_str);
                $stmt_check_overlap->execute();
                $result_overlap = $stmt_check_overlap->get_result();
                if ($overlapped_activity = $result_overlap->fetch_assoc()) {
                    $can_book = false;
                    // ปรับปรุงข้อความ Error ให้ชัดเจนขึ้น
                    $start_overlap_th = format_datetime_th($overlapped_activity['start_datetime']);
                    $end_overlap_th = format_datetime_th($overlapped_activity['end_datetime']);
                    $booking_error = 'เวลาจัดกิจกรรมทับซ้อนกับกิจกรรม "' . htmlspecialchars($overlapped_activity['name']) . '" (' . $start_overlap_th . ' - ' . $end_overlap_th . ') ที่คุณจองไว้แล้ว';
                }
                $stmt_check_overlap->close();
            }

            // 4. ตรวจสอบจำนวนผู้เข้าร่วมสูงสุด (ถ้ากำหนดไว้)
            if ($can_book && !is_null($activity_info['max_participants'])) {
                $sql_count_booked = "SELECT COUNT(*) as current_bookings FROM activity_bookings WHERE activity_id = ? AND status = 'booked'";
                $stmt_count_booked = $mysqli->prepare($sql_count_booked);
                $stmt_count_booked->bind_param('i', $activity_id_to_book);
                $stmt_count_booked->execute();
                $result_count_booked = $stmt_count_booked->get_result();
                $count_data = $result_count_booked->fetch_assoc();
                $stmt_count_booked->close();

                if ($count_data['current_bookings'] >= $activity_info['max_participants']) {
                    $can_book = false;
                    $booking_error = 'กิจกรรมนี้มีผู้จองเต็มจำนวนแล้ว';
                }
            }

            // 5. ตรวจสอบสิทธิ์การเข้าร่วมตามสาขา (เผื่อกรณีเข้าหน้านี้โดยตรง)
            if ($can_book) {
                $sql_check_major = "SELECT COUNT(*) as count
                                     FROM activity_eligible_majors aem
                                     WHERE aem.activity_id = ? AND aem.major_id = ?";
                $sql_check_no_major_limit = "SELECT COUNT(*) as count FROM activity_eligible_majors WHERE activity_id = ?";

                $stmt_check_no_limit = $mysqli->prepare($sql_check_no_major_limit);
                $stmt_check_no_limit->bind_param('i', $activity_id_to_book);
                $stmt_check_no_limit->execute();
                $has_limit = $stmt_check_no_limit->get_result()->fetch_assoc()['count'] > 0;
                $stmt_check_no_limit->close();

                $eligible = !$has_limit;

                if ($has_limit) {
                    $stmt_check_major = $mysqli->prepare($sql_check_major);
                    $stmt_check_major->bind_param('ii', $activity_id_to_book, $student_major_id);
                    $stmt_check_major->execute();
                    if ($stmt_check_major->get_result()->fetch_assoc()['count'] > 0) {
                        $eligible = true;
                    }
                    $stmt_check_major->close();
                }

                if (!$eligible) {
                    $can_book = false;
                    $booking_error = 'คุณไม่มีสิทธิ์เข้าร่วมกิจกรรมนี้ (สาขาวิชาไม่ตรงตามกำหนด)';
                }
            }


            // --- ทำการจองถ้าผ่านทุกเงื่อนไข ---
            if ($can_book) {
                $sql_insert_booking = "INSERT INTO activity_bookings (student_user_id, activity_id, status, booking_time) VALUES (?, ?, 'booked', NOW())";
                $stmt_insert_booking = $mysqli->prepare($sql_insert_booking);
                if ($stmt_insert_booking) {
                    $stmt_insert_booking->bind_param('ii', $student_user_id, $activity_id_to_book);
                    if ($stmt_insert_booking->execute()) {
                        $_SESSION['form_message'] = '<p class="alert alert-success text-white">จองกิจกรรม "' . htmlspecialchars($activity_info['name']) . '" สำเร็จแล้ว</p>';
                    } else {
                        $_SESSION['form_message'] = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการบันทึกการจอง: ' . htmlspecialchars($stmt_insert_booking->error) . '</p>';
                    }
                    $stmt_insert_booking->close();
                } else {
                    $_SESSION['form_message'] = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการเตรียมคำสั่งจอง</p>';
                }
                header('Location: index.php?page=student_activities');
                exit;
            } else {
                $message = '<p class="alert alert-danger text-white">' . $booking_error . '</p>';
            }
        } // end if activity_info found
    } else {
        $message = '<p class="alert alert-warning text-white">ข้อมูลกิจกรรมไม่ถูกต้อง</p>';
    }
} // end if POST


// --- Fetch Available Activities Data ---
$activities_list = [];
if (!is_null($student_major_id)) {
    // ดึงกิจกรรมที่ยังไม่เริ่ม และ นักศึกษามีสิทธิ์เข้าร่วม (ตามสาขา)
    $sql = "SELECT
                a.id, a.name, a.start_datetime, a.end_datetime, a.location,
                a.hours_participant, a.max_participants, au.name as organizer_unit_name,
                (SELECT COUNT(*) FROM activity_bookings WHERE activity_id = a.id AND status = 'booked') as current_bookings,
                EXISTS(SELECT 1 FROM activity_bookings WHERE student_user_id = ? AND activity_id = a.id AND status = 'booked') as already_booked
            FROM activities a
            LEFT JOIN activity_units au ON a.organizer_unit_id = au.id
            WHERE
                a.start_datetime > NOW() -- กิจกรรมยังไม่เริ่ม
                AND (
                    NOT EXISTS (SELECT 1 FROM activity_eligible_majors WHERE activity_id = a.id) -- ไม่มีกำหนดสาขา = ได้ทุกคน
                    OR
                    EXISTS (SELECT 1 FROM activity_eligible_majors WHERE activity_id = a.id AND major_id = ?) -- กำหนดสาขา และสาขาตรง
                )
            ORDER BY a.start_datetime ASC";

    $stmt_list = $mysqli->prepare($sql);
    if ($stmt_list) {
        $stmt_list->bind_param('ii', $student_user_id, $student_major_id);
        $stmt_list->execute();
        $result = $stmt_list->get_result();
        while ($row = $result->fetch_assoc()) {
            $row['remaining_slots'] = null;
            if (!is_null($row['max_participants'])) {
                $row['remaining_slots'] = $row['max_participants'] - $row['current_bookings'];
                // Ensure remaining slots is not negative
                if ($row['remaining_slots'] < 0) {
                    $row['remaining_slots'] = 0;
                }
            }
            $activities_list[] = $row;
        }
        $stmt_list->close();
    } else {
        $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงข้อมูลกิจกรรม: ' . htmlspecialchars($mysqli->error) . '</p>';
    }
}

// --- จัดการ Message จาก Session ---
if (isset($_SESSION['form_message'])) {
    $message = $_SESSION['form_message'];
    unset($_SESSION['form_message']);
}

// Function to format datetime (ถ้ายังไม่มี)
if (!function_exists('format_datetime_th')) {
    function format_datetime_th($datetime_str)
    {
        if (empty($datetime_str)) return '-';
        try {
            $dt = new DateTime($datetime_str);
            return $dt->format('d/m/') . ($dt->format('Y') + 543) . $dt->format(' H:i');
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
                    <div class="bg-gradient-success shadow-success border-radius-lg pt-4 pb-3">
                        <h6 class="text-white text-capitalize ps-3">กิจกรรมที่สามารถจองได้</h6>
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

                    <?php if (is_null($student_major_id) && empty($message)): // Show error only if no other message exists 
                    ?>
                        <p class="text-center text-danger p-4">ไม่สามารถแสดงรายการกิจกรรมได้เนื่องจากไม่พบข้อมูลสาขา (กรุณาติดต่อผู้ดูแลระบบเพื่อกำหนดกลุ่มเรียน)</p>
                    <?php elseif (!empty($activities_list)) : ?>
                        <div class="table-responsive p-0">
                            <table class="table align-items-center mb-0">
                                <thead>
                                    <tr>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ชื่อกิจกรรม</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">หน่วยงานจัด</th>
                                        <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">วันเวลาเริ่ม</th>
                                        <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">วันเวลาสิ้นสุด</th>
                                        <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">สถานที่</th>
                                        <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ชม.</th>
                                        <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ที่นั่งเหลือ</th>
                                        <th class="text-secondary opacity-7"></th>
                                    </tr>
                                </thead>
                                <tbody>
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
                                                <span class="text-secondary text-xs font-weight-bold"><?php echo format_datetime_th($activity['end_datetime']); ?></span>
                                            </td>
                                            <td class="align-middle text-center text-sm">
                                                <span class="text-secondary text-xs font-weight-bold"><?php echo htmlspecialchars($activity['location'] ?? '-'); ?></span>
                                            </td>
                                            <td class="align-middle text-center text-sm">
                                                <span class="badge badge-sm bg-gradient-info"><?php echo htmlspecialchars($activity['hours_participant']); ?></span>
                                            </td>
                                            <td class="align-middle text-center text-sm">
                                                <?php
                                                if (is_null($activity['remaining_slots'])) {
                                                    echo '<span class="badge badge-sm bg-gradient-success">ไม่จำกัด</span>';
                                                } elseif ($activity['remaining_slots'] > 0) {
                                                    echo '<span class="badge badge-sm bg-gradient-primary">' . htmlspecialchars($activity['remaining_slots']) . '</span>';
                                                } else {
                                                    echo '<span class="badge badge-sm bg-gradient-danger">เต็ม</span>';
                                                }
                                                ?>
                                            </td>
                                            <td class="align-middle text-center">
                                                <?php if ($activity['already_booked']): // ใช้ค่า boolean ที่ดึงมา 
                                                ?>
                                                    <span class="badge badge-sm bg-gradient-secondary">จองแล้ว</span>
                                                <?php elseif (!is_null($activity['remaining_slots']) && $activity['remaining_slots'] <= 0): ?>
                                                    <button class="btn btn-secondary btn-sm mb-0 disabled" disabled>เต็ม</button>
                                                <?php else: ?>
                                                    <form action="index.php?page=student_activities" method="post" style="display: inline;">
                                                        <input type="hidden" name="book_activity_id" value="<?php echo $activity['id']; ?>">
                                                        <button type="submit" class="btn btn-success btn-sm bg-gradient-success mb-0">จอง</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php elseif (is_null($student_major_id) && !empty($message)): ?>
                        <?php // Message already displayed 
                        ?>
                    <?php else : ?>
                        <p class="text-center p-4">ยังไม่มีกิจกรรมที่สามารถจองได้ในขณะนี้ หรือคุณอาจจะยังไม่ได้ถูกกำหนดกลุ่มเรียน</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>