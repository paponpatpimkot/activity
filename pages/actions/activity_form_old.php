<?php
// ========================================================================
// ไฟล์: activity_form.php (เนื้อหาสำหรับ include)
// หน้าที่: ฟอร์มสำหรับเพิ่ม หรือ แก้ไข กิจกรรม
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php, และ Authorization check ---
// --- ได้ทำไปแล้วในไฟล์ Controller หลัก ---
// require 'db_connect.php'; // $mysqli should be available
// if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) { exit('Unauthorized'); }

$page_title = "เพิ่มกิจกรรมใหม่";
$form_action = "index.php?page=activity_form"; // Action ชี้ไปที่ Controller หลัก
$activity_id = null;
$activity_name = '';
$description = '';
$start_datetime = '';
$end_datetime = '';
$location = '';
$organizer_unit_id = '';
$hours_organizer = 0.0;
$hours_participant = 0.0;
$penalty_hours = 0.0;
$max_participants = ''; // ใช้ empty string เพื่อให้ placeholder ทำงาน
$message = ''; // Message จะถูก set โดย Controller หลักถ้ามี Error จาก POST
$is_edit_mode = false;

// --- ดึงข้อมูลสำหรับ Dropdowns ---
// Activity Units
$units = [];
$sql_units = "SELECT id, name FROM activity_units ORDER BY name ASC";
$result_units = $mysqli->query($sql_units);
if ($result_units) {
    while ($row = $result_units->fetch_assoc()) {
        $units[] = $row;
    }
    $result_units->free();
}

// --- Check if Editing ---
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $is_edit_mode = true;
    $activity_id = (int)$_GET['id'];
    $page_title = "แก้ไขกิจกรรม";
    $form_action = "index.php?page=activity_form&id=" . $activity_id;

    // --- Fetch existing activity data ---
    $sql_edit = "SELECT * FROM activities WHERE id = ?";
    $stmt_edit = $mysqli->prepare($sql_edit);
    if ($stmt_edit) {
        $stmt_edit->bind_param('i', $activity_id);
        $stmt_edit->execute();
        $result_edit = $stmt_edit->get_result();
        if ($result_edit->num_rows === 1) {
            $activity_data = $result_edit->fetch_assoc();
            $activity_name = $activity_data['name'];
            $description = $activity_data['description'];
            // Format datetime for datetime-local input
            $start_datetime = !empty($activity_data['start_datetime']) ? date('Y-m-d\TH:i', strtotime($activity_data['start_datetime'])) : '';
            $end_datetime = !empty($activity_data['end_datetime']) ? date('Y-m-d\TH:i', strtotime($activity_data['end_datetime'])) : '';
            $location = $activity_data['location'];
            $organizer_unit_id = $activity_data['organizer_unit_id'];
            $hours_organizer = $activity_data['hours_organizer'];
            $hours_participant = $activity_data['hours_participant'];
            $penalty_hours = $activity_data['penalty_hours'];
            $max_participants = $activity_data['max_participants']; // อาจเป็น NULL
        } else {
            // ถ้าใช้ ob_start() อาจจะตั้งค่า message ใน session แล้ว redirect
            $_SESSION['form_message'] = '<p class="alert alert-danger text-white">ไม่พบข้อมูลกิจกรรมที่ต้องการแก้ไข</p>';
            header('Location: index.php?page=activities_list');
            exit;
        }
        $stmt_edit->close();
    } else {
        // ถ้าใช้ ob_start() อาจจะตั้งค่า message ใน session แล้ว redirect
        $_SESSION['form_message'] = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการเตรียมคำสั่งดึงข้อมูลกิจกรรม</p>';
        header('Location: index.php?page=activities_list');
        exit;
        // $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการเตรียมคำสั่งดึงข้อมูลกิจกรรม</p>';
    }
}

// --- Handle Form Submission (Add or Edit) ---
// *** ส่วนนี้จะทำงานเมื่อถูก include ใน Controller และ Controller ตรวจสอบว่าเป็น POST request สำหรับหน้านี้ ***
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // รับค่าจากฟอร์ม
    $activity_name = trim($_POST['activity_name']);
    $description = trim($_POST['description']);
    $start_datetime_input = trim($_POST['start_datetime']);
    $end_datetime_input = trim($_POST['end_datetime']);
    $location = trim($_POST['location']);
    $organizer_unit_id_input = filter_input(INPUT_POST, 'organizer_unit_id', FILTER_VALIDATE_INT);
    $hours_organizer_input = filter_input(INPUT_POST, 'hours_organizer', FILTER_VALIDATE_FLOAT);
    $hours_participant_input = filter_input(INPUT_POST, 'hours_participant', FILTER_VALIDATE_FLOAT);
    $penalty_hours_input = filter_input(INPUT_POST, 'penalty_hours', FILTER_VALIDATE_FLOAT);
    $max_participants_input = trim($_POST['max_participants']);
    $max_participants_save = ($max_participants_input === '' || !is_numeric($max_participants_input)) ? null : (int)$max_participants_input;


    // --- Validate Input ---
    $errors = [];
    if (empty($activity_name)) $errors[] = "กรุณากรอกชื่อกิจกรรม";
    if (empty($start_datetime_input)) $errors[] = "กรุณาระบุวันเวลาเริ่มต้น";
    if (empty($end_datetime_input)) $errors[] = "กรุณาระบุวันเวลาสิ้นสุด";
    if (empty($organizer_unit_id_input)) $errors[] = "กรุณาเลือกหน่วยงานผู้จัด";
    if ($hours_organizer_input === false || $hours_organizer_input < 0) $errors[] = "กรุณากรอกชั่วโมง (ผู้จัด) เป็นตัวเลขทศนิยม >= 0";
    if ($hours_participant_input === false || $hours_participant_input < 0) $errors[] = "กรุณากรอกชั่วโมง (ผู้เข้าร่วม) เป็นตัวเลขทศนิยม >= 0";
    if ($penalty_hours_input === false || $penalty_hours_input < 0) $errors[] = "กรุณากรอกชั่วโมง (หัก) เป็นตัวเลขทศนิยม >= 0";
    if (!is_null($max_participants_save) && $max_participants_save < 0) $errors[] = "จำนวนรับสูงสุดต้องเป็นตัวเลข >= 0 หรือเว้นว่าง";

    // Validate datetime logic
    $start_ts = strtotime($start_datetime_input);
    $end_ts = strtotime($end_datetime_input);
    $start_datetime_db = null;
    $end_datetime_db = null;

    if ($start_ts === false || $end_ts === false) {
        $errors[] = "รูปแบบวันเวลาไม่ถูกต้อง";
    } elseif ($end_ts < $start_ts) {
        $errors[] = "วันเวลาสิ้นสุดต้องอยู่หลังหรือตรงกับวันเวลาเริ่มต้น";
    } else {
        // Format datetime for database only if valid
        $start_datetime_db = date('Y-m-d H:i:s', $start_ts);
        $end_datetime_db = date('Y-m-d H:i:s', $end_ts);
    }


    if (!empty($errors)) {
        $message = '<p class="alert alert-danger text-white">' . implode('<br>', $errors) . '</p>';
        // กำหนดค่ากลับไปที่ตัวแปร global เพื่อให้ฟอร์มแสดงค่าเดิมที่กรอกผิด
        $activity_name = $_POST['activity_name']; // ใช้ค่าจาก POST โดยตรง
        $description = $_POST['description'];
        $start_datetime = $start_datetime_input; // ใช้ค่า input เดิม
        $end_datetime = $end_datetime_input;
        $location = $_POST['location'];
        $organizer_unit_id = $organizer_unit_id_input;
        $hours_organizer = $_POST['hours_organizer'];
        $hours_participant = $_POST['hours_participant'];
        $penalty_hours = $_POST['penalty_hours'];
        $max_participants = $_POST['max_participants'];
    } else {
        // --- Process Add or Edit ---
        if ($is_edit_mode && $activity_id !== null) {
            // --- Update ---
            $sql = "UPDATE activities SET
                        name = ?, description = ?, start_datetime = ?, end_datetime = ?, location = ?,
                        organizer_unit_id = ?, hours_organizer = ?, hours_participant = ?,
                        penalty_hours = ?, max_participants = ?
                    WHERE id = ?";
            $stmt = $mysqli->prepare($sql);
            if ($stmt) {
                // Bind parameters (ใช้ type ที่เหมาะสม หรือ 's' ทั้งหมดก็ได้)
                // s = string, i = integer, d = double/float, b = blob
                // สำหรับ max_participants ที่เป็น NULL ต้องจัดการเป็นพิเศษ หรือตั้งค่า default ใน DB
                // การ bind NULL โดยตรงอาจต้องเช็คเวอร์ชัน PHP/MySQLi
                // วิธีที่ง่ายคือเตรียมค่า NULL ใน PHP แล้ว bind เป็น string หรือ integer ตามเหมาะสม
                // ในที่นี้จะ bind เป็น integer (i) ถ้าไม่ใช่ NULL หรือ bind เป็น NULL ถ้าเป็น NULL
                // *** การ bind NULL โดยตรงกับ bind_param อาจไม่เสถียรในบางเวอร์ชัน ***
                // *** วิธีที่ปลอดภัยกว่าคือใช้ execute() กับ array หรือ PDO ***
                // *** หรือตั้งค่า default NULL ใน DB และส่ง NULL จาก PHP ***

                // ตัวอย่างการ bind โดยใช้ type ที่ถูกต้อง (อาจต้องปรับตามเวอร์ชัน)
                $stmt->bind_param(
                    'sssssidddii',
                    $activity_name,
                    $description,
                    $start_datetime_db,
                    $end_datetime_db,
                    $location,
                    $organizer_unit_id_input,
                    $hours_organizer_input,
                    $hours_participant_input,
                    $penalty_hours_input,
                    $max_participants_save,
                    $activity_id
                );


                if ($stmt->execute()) {
                    // *** จัดการ activity_eligible_majors ที่นี่ (ถ้าทำ) ***
                    $_SESSION['form_message'] = '<p class="alert alert-success text-white">แก้ไขข้อมูลกิจกรรมสำเร็จแล้ว</p>';
                    header('Location: index.php?page=activities_list'); // Redirect
                    exit;
                } else {
                    $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการแก้ไขข้อมูล: ' . htmlspecialchars($stmt->error) . '</p>';
                }
                $stmt->close();
            } else {
                $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการเตรียมคำสั่งแก้ไข: ' . htmlspecialchars($mysqli->error) . '</p>';
            }
            // กำหนดค่ากลับไปที่ตัวแปร global เพื่อให้ฟอร์มแสดงค่าเดิมที่กรอกผิด
            $activity_name = $_POST['activity_name'];
            $description = $_POST['description'];
            $start_datetime = $start_datetime_input;
            $end_datetime = $end_datetime_input;
            $location = $_POST['location'];
            $organizer_unit_id = $organizer_unit_id_input;
            $hours_organizer = $_POST['hours_organizer'];
            $hours_participant = $_POST['hours_participant'];
            $penalty_hours = $_POST['penalty_hours'];
            $max_participants = $_POST['max_participants'];
        } else {
            // --- Insert ---
            $sql = "INSERT INTO activities (name, description, start_datetime, end_datetime, location,
                        organizer_unit_id, hours_organizer, hours_participant, penalty_hours, max_participants, created_by_user_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $mysqli->prepare($sql);
            if ($stmt) {
                $created_by = $_SESSION['user_id']; // ID ของ Admin ที่สร้าง
                $stmt->bind_param(
                    'sssssidddii',
                    $activity_name,
                    $description,
                    $start_datetime_db,
                    $end_datetime_db,
                    $location,
                    $organizer_unit_id_input,
                    $hours_organizer_input,
                    $hours_participant_input,
                    $penalty_hours_input,
                    $max_participants_save,
                    $created_by
                );
                if ($stmt->execute()) {
                    $new_activity_id = $mysqli->insert_id;
                    // *** จัดการ activity_eligible_majors ที่นี่ (ถ้าทำ) ***
                    $_SESSION['form_message'] = '<p class="alert alert-success text-white">เพิ่มกิจกรรมใหม่สำเร็จแล้ว</p>';
                    header('Location: index.php?page=activities_list'); // Redirect
                    exit;
                } else {
                    $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการเพิ่มข้อมูล: ' . htmlspecialchars($stmt->error) . '</p>';
                }
                $stmt->close();
            } else {
                $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการเตรียมคำสั่งเพิ่ม: ' . htmlspecialchars($mysqli->error) . '</p>';
            }
            // กำหนดค่ากลับไปที่ตัวแปร global เพื่อให้ฟอร์มแสดงค่าเดิมที่กรอกผิด
            $activity_name = $_POST['activity_name'];
            $description = $_POST['description'];
            $start_datetime = $start_datetime_input;
            $end_datetime = $end_datetime_input;
            $location = $_POST['location'];
            $organizer_unit_id = $organizer_unit_id_input;
            $hours_organizer = $_POST['hours_organizer'];
            $hours_participant = $_POST['hours_participant'];
            $penalty_hours = $_POST['penalty_hours'];
            $max_participants = $_POST['max_participants'];
        }
    }
}


// --- Display message from Controller (if any validation error occurred) ---
// $message variable should be set by the controller if there were errors during POST processing

?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-lg-10 col-md-12 mx-auto">
            <div class="card my-4">
                <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div class="bg-gradient-success shadow-success border-radius-lg pt-4 pb-3">
                        <h6 class="text-white text-capitalize ps-3"><?php echo $page_title; ?></h6>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($message)) : ?>
                        <div class="alert alert-dismissible text-white fade show <?php echo (strpos($message, 'success') !== false || strpos($message, 'สำเร็จ') !== false) ? 'alert-success bg-gradient-success' : ((strpos($message, 'warning') !== false || strpos($message, 'เตือน') !== false) ? 'alert-warning bg-gradient-warning' : 'alert-danger bg-gradient-danger'); ?>" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close p-3" data-bs-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <form role="form" class="text-start" action="<?php echo $form_action; ?>" method="post">
                        <div class="input-group input-group-outline my-3 <?php echo !empty($activity_name) ? 'is-filled' : ''; ?>">
                            <label class="form-label">ชื่อกิจกรรม</label>
                            <input type="text" id="activity_name" name="activity_name" class="form-control" value="<?php echo htmlspecialchars($activity_name); ?>" required>
                        </div>

                        <div class="input-group input-group-outline my-3 <?php echo !empty($description) ? 'is-filled' : ''; ?>">
                            <label class="form-label">รายละเอียดกิจกรรม</label>
                            <textarea id="description" name="description" class="form-control" rows="3"><?php echo htmlspecialchars($description); ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="input-group input-group-static my-3">
                                    <label>วันเวลาเริ่มต้น</label>
                                    <input type="datetime-local" id="start_datetime" name="start_datetime" class="form-control" value="<?php echo htmlspecialchars($start_datetime); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="input-group input-group-static my-3">
                                    <label>วันเวลาสิ้นสุด</label>
                                    <input type="datetime-local" id="end_datetime" name="end_datetime" class="form-control" value="<?php echo htmlspecialchars($end_datetime); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="input-group input-group-outline my-3 <?php echo !empty($location) ? 'is-filled' : ''; ?>">
                            <label class="form-label">สถานที่จัด</label>
                            <input type="text" id="location" name="location" class="form-control" value="<?php echo htmlspecialchars($location); ?>">
                        </div>

                        <div class="input-group input-group-static mb-4">
                            <label for="organizer_unit_id" class="ms-0">หน่วยงานผู้จัด</label>
                            <select class="form-control" id="organizer_unit_id" name="organizer_unit_id" required>
                                <option value="">-- เลือกหน่วยงาน --</option>
                                <?php foreach ($units as $unit): ?>
                                    <option value="<?php echo $unit['id']; ?>" <?php echo ($organizer_unit_id == $unit['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($unit['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <hr class="dark horizontal my-3">
                        <p class="text-sm font-weight-bold">จำนวนชั่วโมง</p>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="input-group input-group-outline my-3 <?php echo ($hours_organizer > 0 || $hours_organizer === '0.0' || (isset($_POST['hours_organizer']) && $_POST['hours_organizer'] != '')) ? 'is-filled' : ''; ?>">
                                    <label class="form-label">สำหรับผู้จัด</label>
                                    <input type="number" step="0.1" min="0" id="hours_organizer" name="hours_organizer" class="form-control" value="<?php echo htmlspecialchars($hours_organizer); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="input-group input-group-outline my-3 <?php echo ($hours_participant > 0 || $hours_participant === '0.0' || (isset($_POST['hours_participant']) && $_POST['hours_participant'] != '')) ? 'is-filled' : ''; ?>">
                                    <label class="form-label">สำหรับผู้เข้าร่วม</label>
                                    <input type="number" step="0.1" min="0" id="hours_participant" name="hours_participant" class="form-control" value="<?php echo htmlspecialchars($hours_participant); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="input-group input-group-outline my-3 <?php echo ($penalty_hours > 0 || $penalty_hours === '0.0' || (isset($_POST['penalty_hours']) && $_POST['penalty_hours'] != '')) ? 'is-filled' : ''; ?>">
                                    <label class="form-label">ชั่วโมงหัก (กรณีไม่เข้าร่วม)</label>
                                    <input type="number" step="0.1" min="0" id="penalty_hours" name="penalty_hours" class="form-control" value="<?php echo htmlspecialchars($penalty_hours); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="input-group input-group-outline my-3 <?php echo (!is_null($max_participants) && $max_participants !== '') ? 'is-filled' : ''; ?>">
                            <label class="form-label">จำนวนรับสูงสุด (เว้นว่าง = ไม่จำกัด)</label>
                            <input type="number" min="0" id="max_participants" name="max_participants" class="form-control" value="<?php echo htmlspecialchars($max_participants ?? ''); ?>">
                        </div>

                        <div class="my-4">
                            <p class="text-sm font-weight-bold">สาขาที่เข้าร่วมได้:</p>
                            <p class="text-xs text-warning">ส่วนนี้จะถูกพัฒนาเพิ่มเติมในภายหลัง</p>
                        </div>


                        <div class="text-center">
                            <button type="submit" class="btn bg-gradient-primary w-100 my-4 mb-2"><?php echo $is_edit_mode ? 'บันทึกการแก้ไข' : 'เพิ่มกิจกรรม'; ?></button>
                            <a href="index.php?page=activities_list" class="btn btn-outline-secondary w-100 mb-0">ยกเลิก</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>