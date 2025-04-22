<?php
require_once 'db_connect.php'; // เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล

// --- ส่วนของการค้นหา ---
$search_term = '';
$sql = "SELECT * FROM activities"; // ดึงข้อมูลทั้งหมดมาเพื่อใช้ใน modal ได้
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_term = mysqli_real_escape_string($conn, $_GET['search']);
    // ค้นหาจากหลายฟิลด์
    $sql .= " WHERE activity_name LIKE '%$search_term%'
                OR activity_type LIKE '%$search_term%'
                OR location LIKE '%$search_term%'
                OR allowed_departments LIKE '%$search_term%'
                OR description LIKE '%$search_term%'";
}
$sql .= " ORDER BY start_date DESC, start_time DESC"; // เรียงลำดับตามวันที่เริ่มต้นล่าสุด

$result = mysqli_query($conn, $sql);

// --- ส่วนของการแสดงข้อความแจ้งเตือน (ถ้ามี) ---
$alert_message = '';
if (isset($_GET['status'])) {
    switch ($_GET['status']) {
        case 'add_success':
            $alert_message = '<div class="alert alert-success alert-dismissible fade show" role="alert">เพิ่มข้อมูลกิจกรรมสำเร็จ!<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            break;
        case 'edit_success':
            $alert_message = '<div class="alert alert-success alert-dismissible fade show" role="alert">แก้ไขข้อมูลกิจกรรมสำเร็จ!<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            break;
        case 'delete_success':
            $alert_message = '<div class="alert alert-success alert-dismissible fade show" role="alert">ลบข้อมูลกิจกรรมสำเร็จ!<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            break;
        case 'error':
            $alert_message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">เกิดข้อผิดพลาดในการดำเนินการ!<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการข้อมูลกิจกรรม</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .table th, .table td {
            vertical-align: middle;
        }
        .table td.actions {
            white-space: nowrap;
            width: 1%;
        }
        /* Style for View Details Modal */
        #viewActivityModal .modal-body dt {
            font-weight: bold;
            min-width: 150px; /* Adjust as needed */
            padding-right: 10px;
        }
        #viewActivityModal .modal-body dl {
            margin-bottom: 0.5rem;
        }
         #viewActivityModal .modal-body dd {
            margin-left: 0; /* Override default */
        }
         #viewActivityModal .description-box {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 10px;
            border-radius: 0.25rem;
            margin-top: 5px;
            white-space: pre-wrap; /* Preserve line breaks in description */
            word-wrap: break-word;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4 text-center">ระบบจัดการข้อมูลกิจกรรม</h1>

        <?php echo $alert_message; // แสดงข้อความแจ้งเตือน ?>

        <div class="d-flex justify-content-between mb-3 flex-wrap gap-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addActivityModal">
                <i class="fas fa-plus"></i> เพิ่มกิจกรรมใหม่
            </button>
            <form class="d-flex" method="GET" action="index.php">
                <input class="form-control me-2" type="search" placeholder="ค้นหา..." aria-label="Search" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
                <button class="btn btn-outline-success" type="submit">ค้นหา</button>
                <?php if (!empty($search_term)): ?>
                    <a href="index.php" class="btn btn-outline-secondary ms-2">ล้างค่า</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>ชื่อกิจกรรม</th>
                        <th>วันที่เริ่ม</th>
                        <th>วันที่สิ้นสุด</th>
                        <th>ชม.(ผู้จัด)</th>
                        <th>ชม.(ผู้เข้าร่วม)</th>
                        <th>จำนวนรับ</th>
                        <th>เปิดจอง</th>
                        <th>อนุมัติ</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && mysqli_num_rows($result) > 0): ?>
                        <?php while($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['activity_name']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($row['start_date'])); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($row['end_date'])); ?></td>
                                <td class="text-end"><?php echo number_format($row['organizer_hours'], 1); ?></td>
                                <td class="text-end"><?php echo number_format($row['participant_hours'], 1); ?></td>
                                <td class="text-end"><?php echo $row['capacity'] !== null ? $row['capacity'] : '-'; ?></td>
                                <td class="text-center"><?php echo $row['is_booking_enabled'] ? '<span class="badge bg-success">เปิด</span>' : '<span class="badge bg-secondary">ปิด</span>'; ?></td>
                                <td class="text-center"><?php echo $row['is_approved'] ? '<span class="badge bg-primary">อนุมัติ</span>' : '<span class="badge bg-warning text-dark">รอ</span>'; ?></td>
                                <td class="actions">
                                    <button type="button" class="btn btn-info btn-sm view-details-btn"
                                            data-bs-toggle="modal"
                                            data-bs-target="#viewActivityModal"
                                            data-id="<?php echo $row['activity_id']; ?>"
                                            title="ดูรายละเอียด">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-warning btn-sm edit-btn"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editActivityModal"
                                            data-id="<?php echo $row['activity_id']; ?>"
                                            title="แก้ไข">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="actions/delete_activity.php?id=<?php echo $row['activity_id']; ?>"
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบกิจกรรมนี้?')"
                                       title="ลบ">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center">ไม่พบข้อมูลกิจกรรม <?php echo !empty($search_term) ? 'ที่ตรงกับคำค้นหา' : ''; ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="addActivityModal" tabindex="-1" aria-labelledby="addActivityModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addActivityModalLabel">เพิ่มกิจกรรมใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addActivityForm" action="actions/add_activity.php" method="POST">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="add_activity_name" class="form-label">ชื่อกิจกรรม <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="add_activity_name" name="activity_name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="add_activity_type" class="form-label">ประเภทกิจกรรม</label>
                                <input type="text" class="form-control" id="add_activity_type" name="activity_type">
                            </div>
                            <div class="col-md-6">
                                <label for="add_start_date" class="form-label">วันที่เริ่ม <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="add_start_date" name="start_date" required>
                            </div>
                            <div class="col-md-6">
                                <label for="add_end_date" class="form-label">วันที่สิ้นสุด <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="add_end_date" name="end_date" required>
                            </div>
                            <div class="col-md-6">
                                <label for="add_start_time" class="form-label">เวลาเริ่ม <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" id="add_start_time" name="start_time" required>
                            </div>
                             <div class="col-md-6">
                                <label for="add_end_time" class="form-label">เวลาสิ้นสุด <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" id="add_end_time" name="end_time" required>
                            </div>
                            <div class="col-md-12">
                                <label for="add_location" class="form-label">สถานที่</label>
                                <input type="text" class="form-control" id="add_location" name="location">
                            </div>
                             <div class="col-md-6">
                                <label for="add_organizer_hours" class="form-label">ชั่วโมง(ผู้จัด) <span class="text-danger">*</span></label>
                                <input type="number" step="0.1" min="0" class="form-control" id="add_organizer_hours" name="organizer_hours" required>
                            </div>
                             <div class="col-md-6">
                                <label for="add_participant_hours" class="form-label">ชั่วโมง(ผู้เข้าร่วม) <span class="text-danger">*</span></label>
                                <input type="number" step="0.1" min="0" class="form-control" id="add_participant_hours" name="participant_hours" required>
                            </div>
                             <div class="col-md-6">
                                <label for="add_capacity" class="form-label">จำนวนรับ (คน)</label>
                                <input type="number" min="0" class="form-control" id="add_capacity" name="capacity">
                            </div>
                             <div class="col-md-6">
                                <label for="add_booking_deadline" class="form-label">ปิดรับสมัคร</label>
                                <input type="datetime-local" class="form-control" id="add_booking_deadline" name="booking_deadline">
                            </div>
                             <div class="col-md-12">
                                <label for="add_allowed_departments" class="form-label">แผนกที่อนุญาต (คั่นด้วยจุลภาค , หรือขึ้นบรรทัดใหม่)</label>
                                <textarea class="form-control" id="add_allowed_departments" name="allowed_departments" rows="3"></textarea>
                            </div>
                             <div class="col-md-12">
                                <label for="add_description" class="form-label">รายละเอียดกิจกรรม</label>
                                <textarea class="form-control" id="add_description" name="description" rows="3"></textarea>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="add_is_booking_enabled" name="is_booking_enabled" value="1">
                                    <label class="form-check-label" for="add_is_booking_enabled">เปิดระบบจอง</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="add_is_approved" name="is_approved" value="1">
                                    <label class="form-check-label" for="add_is_approved">อนุมัติกิจกรรม</label>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                    <button type="submit" form="addActivityForm" class="btn btn-primary">บันทึก</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editActivityModal" tabindex="-1" aria-labelledby="editActivityModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editActivityModalLabel">แก้ไขข้อมูลกิจกรรม</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editActivityForm" action="actions/edit_activity.php" method="POST">
                        <input type="hidden" name="activity_id" id="edit_activity_id">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="edit_activity_name" class="form-label">ชื่อกิจกรรม <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_activity_name" name="activity_name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_activity_type" class="form-label">ประเภทกิจกรรม</label>
                                <input type="text" class="form-control" id="edit_activity_type" name="activity_type">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_start_date" class="form-label">วันที่เริ่ม <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="edit_start_date" name="start_date" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_end_date" class="form-label">วันที่สิ้นสุด <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="edit_end_date" name="end_date" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_start_time" class="form-label">เวลาเริ่ม <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" id="edit_start_time" name="start_time" required>
                            </div>
                             <div class="col-md-6">
                                <label for="edit_end_time" class="form-label">เวลาสิ้นสุด <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" id="edit_end_time" name="end_time" required>
                            </div>
                            <div class="col-md-12">
                                <label for="edit_location" class="form-label">สถานที่</label>
                                <input type="text" class="form-control" id="edit_location" name="location">
                            </div>
                             <div class="col-md-6">
                                <label for="edit_organizer_hours" class="form-label">ชั่วโมง(ผู้จัด) <span class="text-danger">*</span></label>
                                <input type="number" step="0.1" min="0" class="form-control" id="edit_organizer_hours" name="organizer_hours" required>
                            </div>
                             <div class="col-md-6">
                                <label for="edit_participant_hours" class="form-label">ชั่วโมง(ผู้เข้าร่วม) <span class="text-danger">*</span></label>
                                <input type="number" step="0.1" min="0" class="form-control" id="edit_participant_hours" name="participant_hours" required>
                            </div>
                             <div class="col-md-6">
                                <label for="edit_capacity" class="form-label">จำนวนรับ (คน)</label>
                                <input type="number" min="0" class="form-control" id="edit_capacity" name="capacity">
                            </div>
                             <div class="col-md-6">
                                <label for="edit_booking_deadline" class="form-label">ปิดรับสมัคร</label>
                                <input type="datetime-local" class="form-control" id="edit_booking_deadline" name="booking_deadline">
                            </div>
                             <div class="col-md-12">
                                <label for="edit_allowed_departments" class="form-label">แผนกที่อนุญาต (คั่นด้วยจุลภาค , หรือขึ้นบรรทัดใหม่)</label>
                                <textarea class="form-control" id="edit_allowed_departments" name="allowed_departments" rows="3"></textarea>
                            </div>
                             <div class="col-md-12">
                                <label for="edit_description" class="form-label">รายละเอียดกิจกรรม</label>
                                <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="edit_is_booking_enabled" name="is_booking_enabled" value="1">
                                    <label class="form-check-label" for="edit_is_booking_enabled">เปิดระบบจอง</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="edit_is_approved" name="is_approved" value="1">
                                    <label class="form-check-label" for="edit_is_approved">อนุมัติกิจกรรม</label>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                    <button type="submit" form="editActivityForm" class="btn btn-primary">บันทึกการแก้ไข</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="viewActivityModal" tabindex="-1" aria-labelledby="viewActivityModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewActivityModalLabel">รายละเอียดกิจกรรม</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <dl class="row">
                        <dt class="col-sm-4">ID กิจกรรม:</dt>
                        <dd class="col-sm-8" id="view_activity_id">-</dd>

                        <dt class="col-sm-4">ชื่อกิจกรรม:</dt>
                        <dd class="col-sm-8" id="view_activity_name">-</dd>

                        <dt class="col-sm-4">ประเภทกิจกรรม:</dt>
                        <dd class="col-sm-8" id="view_activity_type">-</dd>

                        <dt class="col-sm-4">วันที่เริ่ม:</dt>
                        <dd class="col-sm-8" id="view_start_date">-</dd>

                        <dt class="col-sm-4">วันที่สิ้นสุด:</dt>
                        <dd class="col-sm-8" id="view_end_date">-</dd>

                        <dt class="col-sm-4">เวลาเริ่ม:</dt>
                        <dd class="col-sm-8" id="view_start_time">-</dd>

                        <dt class="col-sm-4">เวลาสิ้นสุด:</dt>
                        <dd class="col-sm-8" id="view_end_time">-</dd>

                        <dt class="col-sm-4">สถานที่:</dt>
                        <dd class="col-sm-8" id="view_location">-</dd>

                        <dt class="col-sm-4">ชั่วโมง(ผู้จัด):</dt>
                        <dd class="col-sm-8" id="view_organizer_hours">-</dd>

                        <dt class="col-sm-4">ชั่วโมง(ผู้เข้าร่วม):</dt>
                        <dd class="col-sm-8" id="view_participant_hours">-</dd>

                        <dt class="col-sm-4">จำนวนรับ (คน):</dt>
                        <dd class="col-sm-8" id="view_capacity">-</dd>

                         <dt class="col-sm-4">แผนกที่อนุญาต:</dt>
                        <dd class="col-sm-8" id="view_allowed_departments">-</dd>

                        <dt class="col-sm-4">ปิดรับสมัคร:</dt>
                        <dd class="col-sm-8" id="view_booking_deadline">-</dd>

                        <dt class="col-sm-4">เปิดระบบจอง:</dt>
                        <dd class="col-sm-8" id="view_is_booking_enabled">-</dd>

                        <dt class="col-sm-4">สถานะอนุมัติ:</dt>
                        <dd class="col-sm-8" id="view_is_approved">-</dd>

                        <dt class="col-sm-4">รายละเอียด:</dt>
                        <dd class="col-sm-8"><div id="view_description" class="description-box">-</div></dd>

                         <dt class="col-sm-4">สร้างเมื่อ:</dt>
                        <dd class="col-sm-8" id="view_created_at">-</dd>

                        <dt class="col-sm-4">แก้ไขล่าสุด:</dt>
                        <dd class="col-sm-8" id="view_updated_at">-</dd>
                    </dl>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                </div>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

    <script>
        // --- Thai Month Abbreviations ---
        const thaiMonths = [
            "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.",
            "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."
        ];

        // Function to format Date string (YYYY-MM-DD) to Thai Format (e.g., 12 พ.ค. 2568)
        function formatThaiDate(dateString) {
            if (!dateString || dateString === '0000-00-00' || dateString.split('-').length !== 3) {
                return '-';
            }
            try {
                const date = new Date(dateString);
                if (isNaN(date.getTime())) { // Check if date is valid
                    return '-';
                }
                const day = date.getDate(); // Day without leading zero
                const monthIndex = date.getMonth(); // 0-11
                const year = date.getFullYear() + 543; // Convert to Buddhist Era (B.E.)
                return `${day} ${thaiMonths[monthIndex]} ${year}`;
            } catch (e) {
                console.error("Error formatting date:", dateString, e);
                return '-'; // Return '-' on error
            }
        }

        // Function to format Time string (HH:MM:SS or HH:MM) to Thai Format (e.g., 17.00 น.)
        function formatThaiTime(timeString) {
            if (!timeString || timeString.split(':').length < 2) return '-';
             try {
                // Extract HH:MM part, ignoring seconds if present
                const [hour, minute] = timeString.split(':');
                 // Ensure hour and minute are two digits (though usually they are from input)
                // const formattedHour = hour.padStart(2, '0');
                // const formattedMinute = minute.padStart(2, '0');
                return `${hour}.${minute} น.`;
             } catch (e) {
                 console.error("Error formatting time:", timeString, e);
                 return '-';
             }
        }

        // Function to format DateTime string (YYYY-MM-DD HH:MM:SS) to Thai Format (e.g., 12 พ.ค. 2568, 17.00 น.)
         function formatThaiDateTime(dateTimeString) {
            if (!dateTimeString || dateTimeString.startsWith('0000-00-00') || !dateTimeString.includes(' ')) {
                 return '-';
            }
             try {
                const [datePart, timePart] = dateTimeString.split(' ');
                const formattedDate = formatThaiDate(datePart);
                const formattedTime = formatThaiTime(timePart);

                // Handle cases where one part might fail formatting
                if (formattedDate === '-' || formattedTime === '-') {
                    return formattedDate !== '-' ? formattedDate : (formattedTime !== '-' ? formattedTime : '-');
                }

                return `${formattedDate}, ${formattedTime}`;
             } catch(e) {
                 console.error("Error formatting datetime:", dateTimeString, e);
                 return '-';
             }
        }

        $(document).ready(function() {

            // --- Script for Edit Modal ---
            $('.edit-btn').on('click', function() {
                var activityId = $(this).data('id');
                var modal = $('#editActivityModal');
                // ... (AJAX call remains the same) ...
                $.ajax({
                    // ... (AJAX settings) ...
                    success: function(response) {
                        // ... (Populating edit form fields remains the same,
                        //      as the form inputs expect standard formats YYYY-MM-DD, HH:MM) ...
                         if(response.error) {
                            alert('Error: ' + response.error);
                            return;
                        }
                        // Populate the edit form (เหมือนเดิม)
                        modal.find('#edit_activity_id').val(response.activity_id);
                        modal.find('#edit_activity_name').val(response.activity_name);
                        modal.find('#edit_activity_type').val(response.activity_type);
                        modal.find('#edit_start_date').val(response.start_date); // Form needs YYYY-MM-DD
                        modal.find('#edit_end_date').val(response.end_date);   // Form needs YYYY-MM-DD
                        modal.find('#edit_start_time').val(response.start_time); // Form needs HH:MM
                        modal.find('#edit_end_time').val(response.end_time);   // Form needs HH:MM
                        modal.find('#edit_location').val(response.location);
                        modal.find('#edit_organizer_hours').val(response.organizer_hours);
                        modal.find('#edit_participant_hours').val(response.participant_hours);
                        modal.find('#edit_capacity').val(response.capacity);
                        modal.find('#edit_allowed_departments').val(response.allowed_departments);

                        if (response.booking_deadline) {
                            // Form input datetime-local needs 'YYYY-MM-DDTHH:MM'
                            var deadline = response.booking_deadline.replace(' ', 'T').substring(0, 16);
                            modal.find('#edit_booking_deadline').val(deadline);
                        } else {
                             modal.find('#edit_booking_deadline').val('');
                        }

                        modal.find('#edit_description').val(response.description);
                        modal.find('#edit_is_booking_enabled').prop('checked', response.is_booking_enabled == 1);
                        modal.find('#edit_is_approved').prop('checked', response.is_approved == 1);
                    },
                    // ... (error handling) ...
                });
            });

            // --- Script for View Details Modal --- (Apply new formatting functions)
            $('.view-details-btn').on('click', function() {
                var activityId = $(this).data('id');
                var modal = $('#viewActivityModal');

                 // Clear previous data
                modal.find('dd').text('-');
                modal.find('.description-box').text('-');

                $.ajax({
                    url: 'actions/fetch_activity.php',
                    type: 'GET',
                    data: { id: activityId },
                    dataType: 'json',
                    success: function(response) {
                        if(response.error) {
                            alert('Error: ' + response.error);
                            return;
                        }

                        // Populate the view modal using Thai formatting functions
                        modal.find('#view_activity_id').text(response.activity_id);
                        modal.find('#view_activity_name').text(response.activity_name || '-');
                        modal.find('#view_activity_type').text(response.activity_type || '-');
                        // *** Use new formatters ***
                        modal.find('#view_start_date').text(formatThaiDate(response.start_date));
                        modal.find('#view_end_date').text(formatThaiDate(response.end_date));
                        modal.find('#view_start_time').text(formatThaiTime(response.start_time));
                        modal.find('#view_end_time').text(formatThaiTime(response.end_time));
                        // *** End Use new formatters ***
                        modal.find('#view_location').text(response.location || '-');
                        modal.find('#view_organizer_hours').text(response.organizer_hours !== null ? parseFloat(response.organizer_hours).toFixed(1) : '-');
                        modal.find('#view_participant_hours').text(response.participant_hours !== null ? parseFloat(response.participant_hours).toFixed(1) : '-');
                        modal.find('#view_capacity').text(response.capacity !== null && response.capacity !== '' ? response.capacity : '-');
                        modal.find('#view_allowed_departments').html(response.allowed_departments ? nl2br(htmlspecialchars(response.allowed_departments)) : '-');
                        // *** Use new formatter ***
                        modal.find('#view_booking_deadline').text(formatThaiDateTime(response.booking_deadline));
                        // *** End Use new formatter ***
                        modal.find('#view_is_booking_enabled').html(response.is_booking_enabled == 1 ? '<span class="badge bg-success">เปิด</span>' : '<span class="badge bg-secondary">ปิด</span>');
                        modal.find('#view_is_approved').html(response.is_approved == 1 ? '<span class="badge bg-primary">อนุมัติ</span>' : '<span class="badge bg-warning text-dark">รออนุมัติ</span>');
                        modal.find('#view_description').html(response.description ? nl2br(htmlspecialchars(response.description)) : '-');
                        // *** Use new formatter ***
                        modal.find('#view_created_at').text(formatThaiDateTime(response.created_at));
                        modal.find('#view_updated_at').text(formatThaiDateTime(response.updated_at));
                         // *** End Use new formatter ***
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error fetching for view: ", status, error);
                        alert('เกิดข้อผิดพลาดในการดึงข้อมูลรายละเอียดกิจกรรม');
                    }
                });
            });

            // ... (rest of the script: modal clear events, helper functions htmlspecialchars/nl2br) ...
             // --- Optional: Clear Add Modal form when closed --- (เหมือนเดิม)
            $('#addActivityModal').on('hidden.bs.modal', function () {
                $(this).find('form')[0].reset();
            });

            // --- Optional: Clear Edit Modal form when closed --- (เหมือนเดิม)
             //$('#editActivityModal').on('hidden.bs.modal', function () {
             //    $(this).find('form')[0].reset();
             //    $(this).find('#edit_activity_id').val('');
             //});

            // --- Optional: Clear View Modal content when closed ---
             $('#viewActivityModal').on('hidden.bs.modal', function () {
                 $(this).find('dd').text('-'); // Reset all dd elements
                 $(this).find('.description-box').text('-');
             });

            // --- Helper function for JS nl2br and htmlspecialchars ---
            function htmlspecialchars(str) {
                if (typeof(str) == "string") {
                    str = str.replace(/&/g, "&amp;"); /* must do &amp; first */
                    str = str.replace(/"/g, "&quot;");
                    str = str.replace(/'/g, "&#039;");
                    str = str.replace(/</g, "&lt;");
                    str = str.replace(/>/g, "&gt;");
                }
                return str;
            }

            function nl2br(str, is_xhtml) {
                var breakTag = (is_xhtml || typeof is_xhtml === 'undefined') ? '<br />' : '<br>';
                return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1' + breakTag + '$2');
            }
        });
    </script>

</body>
</html>
<?php
// ปิดการเชื่อมต่อฐานข้อมูล
if (isset($conn)) {
    mysqli_close($conn);
}
?>