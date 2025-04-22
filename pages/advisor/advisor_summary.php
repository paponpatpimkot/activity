<?php
// ========================================================================
// ไฟล์: advisor_summary.php (เนื้อหาสำหรับ include)
// หน้าที่: สรุปข้อมูลชั่วโมงกิจกรรมของนักศึกษาในที่ปรึกษา แยกตามกลุ่มเรียน (ปรับปรุง Query และ Nav Pills)
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php ($mysqli), และ Authorization check ---
// --- ได้ทำไปแล้วในไฟล์ Controller หลัก และมีการกำหนด $_SESSION['user_id'], $_SESSION['role_id'] ---
// --- Controller ควรตรวจสอบว่า role_id == 2 ---

// if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) { exit('Unauthorized'); }

$advisor_user_id = $_SESSION['user_id'];
$page_title = "สรุปข้อมูลนักศึกษาในที่ปรึกษา";
$message = ''; // สำหรับแสดงข้อความแจ้งเตือน

// --- 1. Fetch Advisor's Groups (แก้ไข Query) ---
$groups = [];
// ดึงกลุ่มจากตารางเชื่อมโยง group_advisors
$sql_groups = "SELECT DISTINCT sg.id, sg.group_name
               FROM group_advisors ga
               JOIN student_groups sg ON ga.group_id = sg.id
               WHERE ga.advisor_user_id = ?
               ORDER BY sg.group_name ASC";
$stmt_groups = $mysqli->prepare($sql_groups);
if ($stmt_groups) {
    $stmt_groups->bind_param('i', $advisor_user_id);
    $stmt_groups->execute();
    $result_groups = $stmt_groups->get_result();
    while ($row = $result_groups->fetch_assoc()) {
        $groups[$row['id']] = $row; // ใช้ group_id เป็น key
    }
    $stmt_groups->close();
} else {
    $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงข้อมูลกลุ่มเรียน: ' . htmlspecialchars($mysqli->error) . '</p>';
}

// --- 2. Fetch All Advisees and Calculate Hours (ปรับปรุง Query และ Logic) ---
$all_advisees_data = [];
$group_ids = array_keys($groups);

if (!empty($group_ids)) {
    // --- 2.1 ดึงข้อมูลชั่วโมงสะสมทั้งหมดของนักศึกษาในกลุ่มเหล่านี้ทีเดียว ---
    $all_earned_hours = [];
    $placeholders_earned = implode(',', array_fill(0, count($group_ids), '?'));
    $types_earned = str_repeat('i', count($group_ids));
    $sql_all_earned = "SELECT aa.student_user_id, SUM(aa.hours_earned) as total_earned
                       FROM activity_attendance aa
                       JOIN students s ON aa.student_user_id = s.user_id
                       WHERE s.group_id IN ($placeholders_earned)
                       GROUP BY aa.student_user_id";
    $stmt_all_earned = $mysqli->prepare($sql_all_earned);
    if ($stmt_all_earned) {
        $stmt_all_earned->bind_param($types_earned, ...$group_ids);
        $stmt_all_earned->execute();
        $result_all_earned = $stmt_all_earned->get_result();
        while ($row_earned = $result_all_earned->fetch_assoc()) {
            $all_earned_hours[$row_earned['student_user_id']] = $row_earned['total_earned'];
        }
        $stmt_all_earned->close();
    } else {
        $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงข้อมูลชั่วโมงสะสม: ' . htmlspecialchars($mysqli->error) . '</p>';
    }


    // --- 2.2 ดึงข้อมูลนักศึกษา พร้อม required_hours จาก levels ---
    $placeholders_advisees = implode(',', array_fill(0, count($group_ids), '?'));
    $types_advisees = str_repeat('i', count($group_ids));
    // แก้ไข Query ให้ดึง required_hours จาก levels
    $sql_advisees = "SELECT
                        s.user_id, s.student_id_number, s.group_id,
                        u.first_name, u.last_name,
                        l.default_required_hours as required_hours
                     FROM students s
                     JOIN users u ON s.user_id = u.id
                     JOIN student_groups sg ON s.group_id = sg.id -- Join groups
                     JOIN levels l ON sg.level_id = l.id -- Join levels
                     WHERE s.group_id IN ($placeholders_advisees)
                     ORDER BY s.student_id_number ASC";

    $stmt_advisees = $mysqli->prepare($sql_advisees);
    if ($stmt_advisees) {
        $stmt_advisees->bind_param($types_advisees, ...$group_ids);
        $stmt_advisees->execute();
        $result_advisees = $stmt_advisees->get_result();
        while ($row = $result_advisees->fetch_assoc()) {
            // --- 3. Calculate Hours using pre-fetched data ---
            $student_user_id = $row['user_id'];
            // ใช้ข้อมูลชั่วโมงสะสมที่ดึงมาแล้ว
            $total_earned_hours = $all_earned_hours[$student_user_id] ?? 0.0;
            // ใช้ required_hours ที่ดึงมาแล้ว
            $required_hours = $row['required_hours'] ?? 0;
            $remaining_hours = max(0, $required_hours - $total_earned_hours);

            // เก็บข้อมูลที่คำนวณแล้ว
            $row['total_earned_hours'] = $total_earned_hours;
            $row['remaining_hours'] = $remaining_hours;

            // จัดกลุ่มข้อมูลตาม group_id
            $all_advisees_data[$row['group_id']][] = $row;
        }
        $stmt_advisees->close();
    } else {
        $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงข้อมูลนักศึกษา: ' . htmlspecialchars($mysqli->error) . '</p>';
    }
}


// --- จัดการ Message จาก Session (ถ้ามี) ---
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
                    <div class="bg-gradient-info shadow-info border-radius-lg pt-4 pb-3">
                        <h6 class="text-white text-capitalize ps-3"><?php echo $page_title; ?></h6>
                    </div>
                </div>
                <div class="card-body px-4 pb-2"> <?php if (!empty($message)) : ?>
                        <div class="alert alert-dismissible text-white fade show <?php echo (strpos($message, 'success') !== false || strpos($message, 'สำเร็จ') !== false) ? 'alert-success bg-gradient-success' : ((strpos($message, 'warning') !== false || strpos($message, 'เตือน') !== false) ? 'alert-warning bg-gradient-warning' : 'alert-danger bg-gradient-danger'); ?>" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close p-3" data-bs-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($groups)): ?>
                        <div class="nav-wrapper position-relative end-0">
                            <ul class="nav nav-pills nav-fill p-1" id="pills-tab" role="tablist">
                                <?php $first_group = true; ?>
                                <?php foreach ($groups as $group_id => $group): ?>
                                    <li class="nav-item" role="presentation">
                                        <a class="nav-link mb-0 px-0 py-1 <?php echo $first_group ? 'active' : ''; ?> d-flex align-items-center justify-content-center"
                                            id="pills-group-<?php echo $group_id; ?>-tab"
                                            data-bs-toggle="pill"
                                            href="#pills-group-<?php echo $group_id; ?>"
                                            role="tab"
                                            aria-controls="pills-group-<?php echo $group_id; ?>"
                                            aria-selected="<?php echo $first_group ? 'true' : 'false'; ?>">
                                            <i class="material-symbols-rounded me-1">group</i>
                                            <?php echo htmlspecialchars($group['group_name']); ?>
                                        </a>
                                    </li>
                                    <?php $first_group = false; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="tab-content mt-3" id="pills-tabContent"> <?php $first_group = true; ?>
                            <?php foreach ($groups as $group_id => $group): ?>
                                <div class="tab-pane fade <?php echo $first_group ? 'show active' : ''; ?>" id="pills-group-<?php echo $group_id; ?>" role="tabpanel" aria-labelledby="pills-group-<?php echo $group_id; ?>-tab">

                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0">กลุ่ม: <?php echo htmlspecialchars($group['group_name']); ?></h6>
                                        <a href="export_csv.php?group_id=<?php echo $group_id; ?>" target="_blank" class="btn btn-outline-success btn-sm mb-0">
                                            <i class="material-symbols-rounded me-1" style="font-size: 1.2em; vertical-align: middle;">file_download</i>&nbsp;Export CSV
                                        </a>
                                    </div>

                                    <div class="table-responsive p-0">
                                        <table class="table align-items-center mb-0 table-striped table-hover">
                                            <thead>
                                                <tr>
                                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7" style="width: 5%;">ลำดับ</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7" style="width: 15%;">รหัสนักศึกษา</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">ชื่อ-สกุล</th>
                                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ชม.ที่ต้องเก็บ</th>
                                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ชม.สะสม</th>
                                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ชม.ที่ยังขาด</th>
                                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ดูเพิ่มเติม</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($all_advisees_data[$group_id])):
                                                    $counter = 1;
                                                ?>
                                                    <?php foreach ($all_advisees_data[$group_id] as $student): ?>
                                                        <tr>
                                                            <td class="align-middle text-center">
                                                                <span class="text-secondary text-xs font-weight-bold"><?php echo $counter++; ?></span>
                                                            </td>
                                                            <td>
                                                                <p class="text-xs font-weight-bold mb-0 px-3"><?php echo htmlspecialchars($student['student_id_number']); ?></p>
                                                            </td>
                                                            <td>
                                                                <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h6>
                                                            </td>
                                                            <td class="align-middle text-center text-sm">
                                                                <span class="text-secondary text-xs font-weight-bold"><?php echo number_format($student['required_hours'] ?? 0, 0); ?></span>
                                                            </td>
                                                            <td class="align-middle text-center text-sm">
                                                                <span class="text-info text-xs font-weight-bold"><?php echo number_format($student['total_earned_hours'] ?? 0, 0); ?></span>
                                                            </td>
                                                            <td class="align-middle text-center text-sm">
                                                                <?php
                                                                $rem_hours = $student['remaining_hours'] ?? 0;
                                                                $rem_class = ($rem_hours <= 0) ? 'text-success' : 'text-warning';
                                                                ?>
                                                                <span class="<?php echo $rem_class; ?> text-xs font-weight-bold"><?php echo number_format($rem_hours, 0); ?></span>
                                                            </td>
                                                            <td class="align-middle text-center">
                                                                <a href="index.php?page=advisor_student_detail&student_user_id=<?php echo $student['user_id']; ?>" class="btn btn-info btn-sm bg-gradient-info mb-0 px-2 py-1">
                                                                    <i class="material-symbols-rounded" style="font-size: 1.2em; vertical-align: middle;">visibility</i>
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="7" class="text-center">ไม่พบข้อมูลนักศึกษาในกลุ่มนี้</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <?php $first_group = false; ?>
                            <?php endforeach; ?>
                        </div> <?php else: ?>
                        <p class="text-center p-4">คุณยังไม่มีกลุ่มเรียนในที่ปรึกษา</p>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>