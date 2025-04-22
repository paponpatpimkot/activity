<?php
// ========================================================================
// ไฟล์: advisors_list.php (เนื้อหาสำหรับ include)
// หน้าที่: แสดงรายการ Advisor และ Employee ID (ถ้ามี)
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php, และ Authorization check ---
// --- ได้ทำไปแล้วในไฟล์ Controller หลัก ---
// require 'db_connect.php'; // $mysqli should be available
// if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) { exit('Unauthorized'); }

$page_title = "จัดการข้อมูล Advisor";
$message = ''; // สำหรับแสดงข้อความแจ้งเตือน

// --- Fetch Advisors Data ---
$advisors_data = [];
// ดึงข้อมูล User ที่มี role_id = 2 และ JOIN กับตาราง advisors
$sql = "SELECT
            u.id as user_id,
            u.username,
            u.first_name,
            u.last_name,
            u.email,
            a.employee_id_number
        FROM users u
        LEFT JOIN advisors a ON u.id = a.user_id
        WHERE u.role_id = 2
        ORDER BY u.first_name ASC, u.last_name ASC";

$result = $mysqli->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $advisors_data[] = $row;
    }
    $result->free();
} else {
    $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงข้อมูล Advisor: ' . htmlspecialchars($mysqli->error) . '</p>';
}

// --- จัดการ Message จาก Session (ถ้ามีการ redirect มา) ---
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
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ชื่อ-สกุล</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Username</th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">รหัสพนักงาน</th>
                                    <th class="text-secondary opacity-7"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($advisors_data)) : ?>
                                    <?php foreach ($advisors_data as $advisor) : ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex px-2 py-1">
                                                    <div class="d-flex flex-column justify-content-center">
                                                        <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($advisor['first_name'] . ' ' . $advisor['last_name']); ?></h6>
                                                        <p class="text-xs text-secondary mb-0"><?php echo htmlspecialchars($advisor['email'] ?? 'N/A'); ?></p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0"><?php echo htmlspecialchars($advisor['username']); ?></p>
                                            </td>
                                            <td class="align-middle text-center text-sm">
                                                <span class="text-secondary text-xs font-weight-bold"><?php echo htmlspecialchars($advisor['employee_id_number'] ?? '-'); ?></span>
                                            </td>
                                            <td class="align-middle">
                                                <a href="index.php?page=advisor_form&user_id=<?php echo $advisor['user_id']; ?>" class="btn btn-warning btn-sm mb-0" data-toggle="tooltip" data-original-title="แก้ไขรหัสพนักงาน">
                                                    <i class="material-icons text-sm">edit</i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="4" class="text-center">ยังไม่มีข้อมูล Advisor</td>
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