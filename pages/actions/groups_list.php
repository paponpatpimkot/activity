<?php
// ========================================================================
// ไฟล์: groups_list.php (เนื้อหาสำหรับ include)
// หน้าที่: แสดงรายการกลุ่มเรียน, ค้นหา, แบ่งหน้า, จัดการลบ, และลิงก์ไปยังหน้าเพิ่ม/แก้ไข
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php ($mysqli), และ Authorization check ---
// --- ได้ทำไปแล้วในไฟล์ Controller หลัก ---
// require 'db_connect.php'; // $mysqli should be available
// if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) { exit('Unauthorized'); }

$page_title = "จัดการกลุ่มเรียน";
$message = ''; // สำหรับแสดงข้อความแจ้งเตือน

// --- Pagination Configuration ---
$items_per_page = 50; // จำนวนรายการต่อหน้า
$current_page = isset($_GET['p']) && is_numeric($_GET['p']) ? (int)$_GET['p'] : 1;
if ($current_page < 1) {
    $current_page = 1;
}
$offset = ($current_page - 1) * $items_per_page;

// --- Search Term ---
$search_code = isset($_GET['search_code']) ? trim($_GET['search_code']) : '';
$search_param = "%" . $search_code . "%"; // สำหรับ LIKE query

// --- Handle Delete Request ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = (int)$_POST['delete_id'];
    $can_delete = true;
    $error_detail = '';

    // Check students
    $check_sql = "SELECT COUNT(*) as count FROM students WHERE group_id = ?";
    $stmt_check = $mysqli->prepare($check_sql);
    $stmt_check->bind_param('i', $delete_id);
    $stmt_check->execute();
    $row_check = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();
    if ($row_check['count'] > 0) { $can_delete = false; $error_detail .= ' มีนักศึกษาสังกัดอยู่'; }

    if (!$can_delete) {
         $_SESSION['form_message'] = '<p class="alert alert-danger text-white">ไม่สามารถลบกลุ่มเรียนนี้ได้ เนื่องจาก:' . $error_detail . '</p>';
    } else {
        $sql = "DELETE FROM student_groups WHERE id = ?";
        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $delete_id);
            if ($stmt->execute()) {
                $_SESSION['form_message'] = '<p class="alert alert-success text-white">ลบกลุ่มเรียนสำเร็จแล้ว</p>';
            } else {
                $_SESSION['form_message'] = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการลบข้อมูล: ' . htmlspecialchars($stmt->error) . '</p>';
            }
            $stmt->close();
        } else {
             $_SESSION['form_message'] = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการเตรียมคำสั่งลบ: ' . htmlspecialchars($mysqli->error) . '</p>';
        }
    }
    // Redirect กลับมาหน้าเดิมพร้อม search term และ page (ถ้ามี)
    $redirect_url = "index.php?page=groups_list";
    if (!empty($search_code)) $redirect_url .= "&search_code=" . urlencode($search_code);
    if ($current_page > 1) $redirect_url .= "&p=" . $current_page;
    header('Location: ' . $redirect_url);
    exit;
}

// --- Count Total Matching Groups (for Pagination) ---
$total_items = 0;
$sql_count = "SELECT COUNT(DISTINCT sg.id) as total
              FROM student_groups sg
              LEFT JOIN levels l ON sg.level_id = l.id
              LEFT JOIN majors m ON sg.major_id = m.id
              LEFT JOIN group_advisors ga ON sg.id = ga.group_id
              LEFT JOIN users u ON ga.advisor_user_id = u.id AND u.role_id = 2";
$count_params = [];
$count_types = "";

if (!empty($search_code)) {
    $sql_count .= " WHERE sg.group_code LIKE ?";
    $count_params[] = $search_param;
    $count_types .= "s";
}

$stmt_count = $mysqli->prepare($sql_count);
if ($stmt_count) {
    if (!empty($count_types)) {
        $stmt_count->bind_param($count_types, ...$count_params);
    }
    $stmt_count->execute();
    $total_items = $stmt_count->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt_count->close();
} else {
     $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการนับจำนวนกลุ่มเรียน: ' . htmlspecialchars($mysqli->error) . '</p>';
}
$total_pages = ceil($total_items / $items_per_page);
if ($current_page > $total_pages && $total_pages > 0) {
    // ถ้าหน้าปัจจุบันเกินจำนวนหน้าที่มี ให้ไปหน้าสุดท้าย
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $items_per_page;
}


// --- Fetch Paginated and Searched Student Groups Data ---
$groups_data = [];
$sql = "SELECT
            sg.id, sg.group_code, sg.group_name,
            l.level_name,
            m.name as major_name,
            GROUP_CONCAT(DISTINCT CONCAT(u.first_name, ' ', u.last_name) SEPARATOR ', ') as advisor_names
        FROM student_groups sg
        LEFT JOIN levels l ON sg.level_id = l.id
        LEFT JOIN majors m ON sg.major_id = m.id
        LEFT JOIN group_advisors ga ON sg.id = ga.group_id
        LEFT JOIN users u ON ga.advisor_user_id = u.id AND u.role_id = 2";

$list_params = [];
$list_types = "";

if (!empty($search_code)) {
    $sql .= " WHERE sg.group_code LIKE ?";
    $list_params[] = $search_param;
    $list_types .= "s";
}

$sql .= " GROUP BY sg.id, sg.group_code, sg.group_name, l.level_name, m.name
          ORDER BY sg.group_code ASC
          LIMIT ? OFFSET ?"; // เพิ่ม LIMIT และ OFFSET

$list_params[] = $items_per_page;
$list_types .= "i";
$list_params[] = $offset;
$list_types .= "i";

$stmt_list = $mysqli->prepare($sql);
if ($stmt_list) {
    if (!empty($list_types)) {
        $stmt_list->bind_param($list_types, ...$list_params);
    }
    if ($stmt_list->execute()) {
        $result = $stmt_list->get_result();
        while ($row = $result->fetch_assoc()) {
            $groups_data[] = $row;
        }
        $result->free();
    } else {
         $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงข้อมูลกลุ่มเรียน: ' . htmlspecialchars($stmt_list->error) . '</p>';
    }
    $stmt_list->close();
} else {
     $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการ Prepare Query กลุ่มเรียน: ' . htmlspecialchars($mysqli->error) . '</p>';
}

// --- จัดการ Message จาก Session ---
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
          <div class="bg-gradient-primary shadow-primary border-radius-lg pt-4 pb-3">
            <h6 class="text-white text-capitalize ps-3"><?php echo $page_title; ?></h6>
          </div>
        </div>
        <div class="card-body px-4 pb-2"> <?php if (!empty($message)) : ?>
          <div
            class="alert alert-dismissible text-white fade show <?php echo (strpos($message, 'success') !== false || strpos($message, 'สำเร็จ') !== false) ? 'alert-success bg-gradient-success' : ((strpos($message, 'warning') !== false || strpos($message, 'เตือน') !== false) ? 'alert-warning bg-gradient-warning' : 'alert-danger bg-gradient-danger'); ?>"
            role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close p-3" data-bs-dismiss="alert" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <?php endif; ?>

          <form action="index.php" method="get" class="row g-3 align-items-center mb-4">
            <input type="hidden" name="page" value="groups_list">
            <div class="col-md-4">
              <div class="input-group input-group-outline">
                <label class="form-label">ค้นหารหัสกลุ่ม...</label>
                <input type="text" class="form-control" name="search_code"
                  value="<?php echo htmlspecialchars($search_code); ?>">
              </div>
            </div>
            <div class="col-md-auto">
              <button type="submit" class="btn bg-gradient-primary mb-0">
                <i class="material-symbols-rounded text-sm">search</i>&nbsp;ค้นหา
              </button>
              <?php if (!empty($search_code)): ?>
              <a href="index.php?page=groups_list" class="btn btn-outline-secondary mb-0">ล้างค้นหา</a>
              <?php endif; ?>
            </div>
            <div class="col-md text-md-end"> <a href="index.php?page=group_form"
                class="btn btn-success bg-gradient-success mb-0">
                <i class="material-symbols-rounded text-sm">add</i>&nbsp;&nbsp;เพิ่มกลุ่มเรียนใหม่
              </a>
            </div>
          </form>


          <div class="table-responsive p-0">
            <table class="table align-items-center mb-0">
              <thead>
                <tr>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">รหัสกลุ่ม</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">ชื่อกลุ่ม</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">ระดับชั้นปี</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">สาขาวิชา</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">อาจารย์ที่ปรึกษา
                  </th>
                  <th class="text-secondary opacity-7">จัดการ</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($groups_data)) : ?>
                <?php foreach ($groups_data as $group) : ?>
                <tr>
                  <td>
                    <p class="text-xs font-weight-bold mb-0 px-3"><?php echo htmlspecialchars($group['group_code']); ?>
                    </p>
                  </td>
                  <td>
                    <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($group['group_name']); ?></h6>
                  </td>
                  <td>
                    <p class="text-xs font-weight-bold mb-0">
                      <?php echo htmlspecialchars($group['level_name'] ?? 'N/A'); ?></p>
                  </td>
                  <td>
                    <p class="text-xs font-weight-bold mb-0">
                      <?php echo htmlspecialchars($group['major_name'] ?? 'N/A'); ?></p>
                  </td>
                  <td>
                    <p class="text-xs text-secondary mb-0">
                      <?php echo htmlspecialchars($group['advisor_names'] ?? '-'); ?></p>
                  </td>
                  <td class="align-middle">
                    <a href="index.php?page=group_form&id=<?php echo $group['id']; ?>"
                      class="btn btn-warning btn-sm mb-0" data-bs-toggle="tooltip" data-bs-placement="top"
                      title="แก้ไขข้อมูล">
                      <i class="material-symbols-rounded text-sm">edit</i>
                    </a>
                    <form action="index.php?page=groups_list" method="post" style="display: inline;"
                      onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบกลุ่มเรียนนี้?');">
                      <input type="hidden" name="delete_id" value="<?php echo $group['id']; ?>">
                      <button type="submit" class="btn btn-danger btn-sm mb-0" data-bs-toggle="tooltip"
                        data-bs-placement="top" title="ลบข้อมูล">
                        <i class="material-symbols-rounded text-sm">delete</i>
                      </button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php else : ?>
                <tr>
                  <td colspan="6" class="text-center">
                    <?php echo !empty($search_code) ? 'ไม่พบกลุ่มเรียนที่ตรงกับรหัสที่ค้นหา' : 'ยังไม่มีข้อมูลกลุ่มเรียน'; ?>
                  </td>
                </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <?php if ($total_pages > 1): ?>
          <nav aria-label="Page navigation" class="mt-4 d-flex justify-content-center">
            <ul class="pagination pagination-primary">
              <?php
                          // Previous button
                          $prev_page = $current_page - 1;
                          $search_query_string = !empty($search_code) ? '&search_code=' . urlencode($search_code) : '';
                        ?>
              <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link"
                  href="index.php?page=groups_list<?php echo $search_query_string; ?>&p=<?php echo $prev_page; ?>"
                  aria-label="Previous">
                  <span class="material-symbols-rounded">keyboard_arrow_left</span>
                  <span class="sr-only"></span>
                </a>
              </li>

              <?php
                          // Page numbers
                          // Logic to display limited page numbers (e.g., show 5 pages around current page)
                          $start_page = max(1, $current_page - 2);
                          $end_page = min($total_pages, $current_page + 2);

                          if ($start_page > 1) {
                              echo '<li class="page-item"><a class="page-link" href="index.php?page=groups_list' . $search_query_string . '&p=1">1</a></li>';
                              if ($start_page > 2) {
                                  echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                              }
                          }

                          for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
              <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                <a class="page-link"
                  href="index.php?page=groups_list<?php echo $search_query_string; ?>&p=<?php echo $i; ?>"><?php echo $i; ?></a>
              </li>
              <?php endfor;

                          if ($end_page < $total_pages) {
                              if ($end_page < $total_pages - 1) {
                                  echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                              }
                              echo '<li class="page-item"><a class="page-link" href="index.php?page=groups_list' . $search_query_string . '&p=' . $total_pages . '">' . $total_pages . '</a></li>';
                          }
                        ?>

              <?php
                          // Next button
                          $next_page = $current_page + 1;
                        ?>
              <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                <a class="page-link"
                  href="index.php?page=groups_list<?php echo $search_query_string; ?>&p=<?php echo $next_page; ?>"
                  aria-label="Next">
                  <span class="material-symbols-rounded">keyboard_arrow_right</span>
                  <span class="sr-only"></span>
                </a>
              </li>
            </ul>
          </nav>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>