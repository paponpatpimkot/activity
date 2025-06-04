<?php
// ========================================================================
// ไฟล์: student_activities.php (เนื้อหาสำหรับ include)
// หน้าที่: แสดงรายการกิจกรรมที่นักศึกษาสามารถจองได้ และจัดการการจอง
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php ($mysqli), และ Authorization check ---
// --- ได้ทำไปแล้วในไฟล์ Controller หลัก และมีการกำหนดตัวแปร $_SESSION['user_id'], $_SESSION['role_id'] ---
// --- และ Controller ได้ require_once '../includes/functions.php' (ที่ควรจะมี format_datetime_th()) แล้ว ---

$student_user_id = $_SESSION['user_id'] ?? null;
$message = '';
$student_major_id = null;

// --- ดึง Major ID ของนักศึกษา ---
if ($student_user_id) {
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
        $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการเตรียมคำสั่งดึงข้อมูลสาขา: ' . htmlspecialchars($mysqli->error) . '</p>';
    }
} else {
    $message = '<p class="alert alert-danger text-white">ไม่พบข้อมูลผู้ใช้ปัจจุบัน</p>';
}

if (is_null($student_major_id) && empty($message) && $student_user_id) {
    $message = '<p class="alert alert-danger text-white">ไม่พบข้อมูลสาขาวิชาของนักศึกษา (อาจจะยังไม่ได้กำหนดกลุ่มเรียน)</p>';
}

// --- Handle Booking Request ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_activity_id']) && !is_null($student_major_id)) {
    $activity_id_to_book = filter_input(INPUT_POST, 'book_activity_id', FILTER_VALIDATE_INT);
    if ($activity_id_to_book) {
        $can_book = true;
        $booking_error = '';
        // ... (โค้ดส่วน Booking Logic ทั้งหมดเหมือนเดิม) ...
        // 1. Check activity details and time
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

            // 2. Check if already booked
            $sql_check_booked = "SELECT id FROM activity_bookings WHERE student_user_id = ? AND activity_id = ? AND status = 'booked'";
            $stmt_check_booked = $mysqli->prepare($sql_check_booked);
            $stmt_check_booked->bind_param('ii', $student_user_id, $activity_id_to_book);
            $stmt_check_booked->execute();
            if ($stmt_check_booked->get_result()->num_rows > 0) {
                $can_book = false;
                $booking_error = 'คุณได้จองกิจกรรมนี้ไปแล้ว';
            }
            $stmt_check_booked->close();

            // 3. Check for time overlap
            if ($can_book) {
                $sql_check_overlap = "SELECT a.name, a.start_datetime, a.end_datetime
                                      FROM activity_bookings ab JOIN activities a ON ab.activity_id = a.id
                                      WHERE ab.student_user_id = ? AND ab.status = 'booked' AND ab.activity_id != ?
                                      AND ((? < a.end_datetime) AND (? > a.start_datetime))";
                $stmt_check_overlap = $mysqli->prepare($sql_check_overlap);
                $start_str = $start_dt_to_book->format('Y-m-d H:i:s');
                $end_str = $end_dt_to_book->format('Y-m-d H:i:s');
                $stmt_check_overlap->bind_param('iiss', $student_user_id, $activity_id_to_book, $start_str, $end_str);
                $stmt_check_overlap->execute();
                $result_overlap = $stmt_check_overlap->get_result();
                if ($overlapped_activity = $result_overlap->fetch_assoc()) {
                    $can_book = false;
                    $start_overlap_th = function_exists('format_datetime_th') ? format_datetime_th($overlapped_activity['start_datetime']) : $overlapped_activity['start_datetime'];
                    $end_overlap_th = function_exists('format_datetime_th') ? format_datetime_th($overlapped_activity['end_datetime']) : $overlapped_activity['end_datetime'];
                    $booking_error = 'เวลาจัดกิจกรรมทับซ้อนกับกิจกรรม "' . htmlspecialchars($overlapped_activity['name']) . '" (' . $start_overlap_th . ' - ' . $end_overlap_th . ') ที่คุณจองไว้แล้ว';
                }
                $stmt_check_overlap->close();
            }

            // 4. Check max participants
            if ($can_book && !is_null($activity_info['max_participants'])) {
                $sql_count_booked = "SELECT COUNT(*) as current_bookings FROM activity_bookings WHERE activity_id = ? AND status = 'booked'";
                $stmt_count_booked = $mysqli->prepare($sql_count_booked);
                $stmt_count_booked->bind_param('i', $activity_id_to_book);
                $stmt_count_booked->execute();
                $count_data = $stmt_count_booked->get_result()->fetch_assoc();
                $stmt_count_booked->close();
                if ($count_data['current_bookings'] >= $activity_info['max_participants']) {
                    $can_book = false;
                    $booking_error = 'กิจกรรมนี้มีผู้จองเต็มจำนวนแล้ว';
                }
            }

            // 5. Check major eligibility
            if ($can_book) {
                $sql_check_no_major_limit = "SELECT 1 FROM activity_eligible_majors WHERE activity_id = ? LIMIT 1";
                $stmt_check_no_limit = $mysqli->prepare($sql_check_no_major_limit);
                $stmt_check_no_limit->bind_param('i', $activity_id_to_book);
                $stmt_check_no_limit->execute();
                $has_limit = $stmt_check_no_limit->get_result()->num_rows > 0;
                $stmt_check_no_limit->close();

                $eligible = !$has_limit;
                if ($has_limit) {
                    $sql_check_major = "SELECT 1 FROM activity_eligible_majors WHERE activity_id = ? AND major_id = ? LIMIT 1";
                    $stmt_check_major = $mysqli->prepare($sql_check_major);
                    $stmt_check_major->bind_param('ii', $activity_id_to_book, $student_major_id);
                    $stmt_check_major->execute();
                    if ($stmt_check_major->get_result()->num_rows > 0) {
                        $eligible = true;
                    }
                    $stmt_check_major->close();
                }
                if (!$eligible) {
                    $can_book = false;
                    $booking_error = 'คุณไม่มีสิทธิ์เข้าร่วมกิจกรรมนี้ (สาขาวิชาไม่ตรงตามกำหนด)';
                }
            }

            // --- Process Booking ---
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
                $currentPage = $_GET['page'] ?? 'student_activities';
                header('Location: index.php?page=' . urlencode($currentPage));
                exit;
            } else {
                $message = '<p class="alert alert-danger text-white">' . $booking_error . '</p>';
            }
        }
    } else {
        $message = '<p class="alert alert-warning text-white">ข้อมูลกิจกรรมไม่ถูกต้อง</p>';
    }
}

// --- Fetch Available Activities Data ---
$activities_list = [];
if (!is_null($student_major_id)) {
    $sql = "SELECT
                a.id, a.name, a.start_datetime, a.end_datetime, a.location,
                a.hours_participant, a.max_participants, au.name as organizer_unit_name,
                (SELECT COUNT(*) FROM activity_bookings WHERE activity_id = a.id AND status = 'booked') as current_bookings,
                EXISTS(SELECT 1 FROM activity_bookings WHERE student_user_id = ? AND activity_id = a.id AND status = 'booked') as already_booked
            FROM activities a
            LEFT JOIN activity_units au ON a.organizer_unit_id = au.id
            WHERE
                a.start_datetime > NOW()
                AND (
                    NOT EXISTS (SELECT 1 FROM activity_eligible_majors WHERE activity_id = a.id)
                    OR
                    EXISTS (SELECT 1 FROM activity_eligible_majors WHERE activity_id = a.id AND major_id = ?)
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
                if ($row['remaining_slots'] < 0) {
                    $row['remaining_slots'] = 0;
                }
            }
            $activities_list[] = $row;
        }
        $stmt_list->close();
    } else {
        if(empty($message)) {
            $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงข้อมูลกิจกรรม: ' . htmlspecialchars($mysqli->error) . '</p>';
        }
    }
}

if (isset($_SESSION['form_message'])) {
    $message = $_SESSION['form_message'];
    unset($_SESSION['form_message']);
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
                        <div class="alert alert-dismissible text-white fade show mx-4 <?php
                            $alert_type_class = 'alert-danger bg-gradient-danger';
                            if (strpos($message, 'alert-success') !== false || strpos($message, 'สำเร็จ') !== false) {
                                $alert_type_class = 'alert-success bg-gradient-success';
                            } elseif (strpos($message, 'alert-warning') !== false || strpos($message, 'เตือน') !== false) {
                                $alert_type_class = 'alert-warning bg-gradient-warning';
                            }
                            echo $alert_type_class;
                        ?>" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close p-3" data-bs-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php if (is_null($student_major_id) && empty($message) && $student_user_id) : ?>
                        <p class="text-center text-danger p-4">ไม่สามารถแสดงรายการกิจกรรมได้เนื่องจากไม่พบข้อมูลสาขา (กรุณาติดต่อผู้ดูแลระบบเพื่อกำหนดกลุ่มเรียน)</p>
                    <?php elseif (!empty($activities_list)) : ?>
                        <div class="px-3"> <?php foreach ($activities_list as $activity) : ?>
                                <div class="card mb-3 shadow-xs border"> <div class="card-body py-3 px-3">
                                        <div class="row">
                                            <div class="col-12 col-md-8"> <h5 class="mb-1 font-weight-bold text-dark"><?php echo htmlspecialchars($activity['name']); ?></h5>
                                                <p class="text-sm text-muted mb-1">
                                                    <i class="material-symbols-rounded opacity-7 me-1" style="font-size: 1.0rem; vertical-align: middle;">corporate_fare</i>
                                                    <?php echo htmlspecialchars($activity['organizer_unit_name'] ?? 'N/A'); ?>
                                                </p>
                                                <p class="text-sm mb-0">
                                                    <i class="material-symbols-rounded opacity-7 me-1" style="font-size: 1.0rem; vertical-align: middle;">calendar_today</i>
                                                    <span class="fw-bold">เริ่ม:</span> <?php echo function_exists('format_datetime_th') ? format_datetime_th($activity['start_datetime']) : $activity['start_datetime']; ?>
                                                </p>
                                                <p class="text-sm mb-2">
                                                    <i class="material-symbols-rounded opacity-7 me-1" style="font-size: 1.0rem; vertical-align: middle;">event</i>
                                                    <span class="fw-bold">สิ้นสุด:</span> <?php echo function_exists('format_datetime_th') ? format_datetime_th($activity['end_datetime']) : $activity['end_datetime']; ?>
                                                </p>
                                            </div>
                                            <div class="col-12 col-md-4 d-flex flex-column justify-content-center align-items-md-end mt-2 mt-md-0"> <?php if ($activity['already_booked']): ?>
                                                    <span class="badge bg-gradient-secondary px-3 py-2 fs-6 w-100 w-md-auto">จองแล้ว</span>
                                                <?php elseif (!is_null($activity['remaining_slots']) && $activity['remaining_slots'] <= 0): ?>
                                                    <button class="btn btn-outline-secondary btn-sm mb-0 w-100 w-md-auto" disabled>เต็ม</button>
                                                <?php else: ?>
                                                    <form action="index.php?page=<?php echo htmlspecialchars($_GET['page'] ?? 'student_activities'); ?>" method="post" class="mb-0 w-100 w-md-auto">
                                                        <input type="hidden" name="book_activity_id" value="<?php echo $activity['id']; ?>">
                                                        <button type="submit" class="btn btn-success bg-gradient-success btn-sm mb-0 w-100 w-md-auto">
                                                            <i class="material-symbols-rounded me-1" style="font-size: 1.0rem; vertical-align: middle;">add_circle</i>จองเลย
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <hr class="horizontal dark my-2">
                                        <div class="row text-sm">
                                            <div class="col-12 col-sm-6 col-md-4 mb-2 mb-md-0">
                                                <i class="material-symbols-rounded opacity-7 me-1" style="font-size: 1.0rem; vertical-align: middle;">location_on</i>
                                                <strong>สถานที่:</strong> <?php echo htmlspecialchars($activity['location'] ?? '-'); ?>
                                            </div>
                                            <div class="col-6 col-sm-3 col-md-2 mb-2 mb-md-0">
                                                <i class="material-symbols-rounded opacity-7 me-1" style="font-size: 1.0rem; vertical-align: middle;">hourglass_empty</i>
                                                <strong>ชม.:</strong> <span class="badge badge-sm bg-gradient-info"><?php echo number_format($activity['hours_participant'] ?? 0, 0); ?></span>
                                            </div>
                                            <div class="col-6 col-sm-3 col-md-3">
                                                <i class="material-symbols-rounded opacity-7 me-1" style="font-size: 1.0rem; vertical-align: middle;">people</i>
                                                <strong>ที่นั่ง:</strong>
                                                <?php
                                                if (is_null($activity['remaining_slots'])) {
                                                    echo '<span class="badge badge-sm bg-gradient-success">ไม่จำกัด</span>';
                                                } elseif ($activity['remaining_slots'] > 0) {
                                                    echo '<span class="badge badge-sm bg-gradient-primary">' . htmlspecialchars($activity['remaining_slots']) . '</span>';
                                                } else {
                                                    echo '<span class="badge badge-sm bg-gradient-danger">เต็ม</span>';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif (empty($message) && (is_null($student_major_id) || empty($activities_list))) : ?>
                        <p class="text-center p-4">ยังไม่มีกิจกรรมที่สามารถจองได้ในขณะนี้ หรือคุณอาจจะยังไม่ได้ถูกกำหนดกลุ่มเรียน</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>