<?php
// ========================================================================
// ไฟล์: advisors_list.php (เนื้อหาสำหรับ include)
// หน้าที่: แสดงรายการ Advisor, ค้นหา, แบ่งหน้า, และลิงก์แก้ไข
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php ($mysqli), และ Authorization check ---
// --- ได้ทำไปแล้วในไฟล์ Controller หลัก ---
// require 'db_connect.php'; // $mysqli should be available
// if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) { exit('Unauthorized'); }

$page_title = "จัดการข้อมูล Advisor";
$message = ''; // สำหรับแสดงข้อความแจ้งเตือน

// --- Pagination Configuration ---
$items_per_page = 50;
$current_page = isset($_GET['p']) && is_numeric($_GET['p']) ? (int)$_GET['p'] : 1;
if ($current_page < 1) { $current_page = 1; }
$offset = ($current_page - 1) * $items_per_page;

// --- Search Parameters ---
$search_name = isset($_GET['search_name']) ? trim($_GET['search_name']) : '';
$search_group_code = isset($_GET['search_gcode']) ? trim($_GET['search_gcode']) : '';

// --- Build WHERE clause for Search ---
$where_clauses = ["u.role_id = 2"]; // Base condition: must be an advisor
$params = [];
$types = "";

if (!empty($search_name)) {
    $where_clauses[] = "(u.first_name LIKE ? OR u.last_name LIKE ?)";
    $search_name_param = "%" . $mysqli->real_escape_string($search_name) . "%";
    $params[] = $search_name_param;
    $params[] = $search_name_param;
    $types .= "ss";
}
if (!empty($search_group_code)) {
    // Need to check if the advisor advises a group with this code
    // Using EXISTS is generally efficient for this type of check
    $where_clauses[] = "EXISTS (SELECT 1 FROM group_advisors ga_search
                               JOIN student_groups sg_search ON ga_search.group_id = sg_search.id
                               WHERE ga_search.advisor_user_id = u.id AND sg_search.group_code LIKE ?)";
    $params[] = "%" . $mysqli->real_escape_string($search_group_code) . "%";
    $types .= "s";
}

$where_sql = " WHERE " . implode(" AND ", $where_clauses);

// --- Count Total Matching Advisors ---
$total_items = 0;
$sql_count = "SELECT COUNT(DISTINCT u.id) as total
              FROM users u
              LEFT JOIN advisors a ON u.id = a.user_id"
             . $where_sql; // Use the constructed WHERE clause

$stmt_count = $mysqli->prepare($sql_count);
if ($stmt_count) {
    if (!empty($types)) {
        $stmt_count->bind_param($types, ...$params);
    }
    $stmt_count->execute();
    $total_items = $stmt_count->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt_count->close();
} else {
     $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการนับจำนวน Advisor: ' . htmlspecialchars($mysqli->error) . '</p>';
}
$total_pages = ceil($total_items / $items_per_page);
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $items_per_page;
}


// --- Fetch Paginated and Searched Advisors Data ---
$advisors_data = [];
$sql = "SELECT
            u.id as user_id, u.username, u.first_name, u.last_name, u.email,
            a.employee_id_number,
            GROUP_CONCAT(DISTINCT sg.group_code ORDER BY sg.group_code SEPARATOR ', ') as advised_groups
        FROM users u
        LEFT JOIN advisors a ON u.id = a.user_id
        LEFT JOIN group_advisors ga ON u.id = ga.advisor_user_id
        LEFT JOIN student_groups sg ON ga.group_id = sg.id"
       . $where_sql . // Use the constructed WHERE clause
       " GROUP BY u.id, u.username, u.first_name, u.last_name, u.email, a.employee_id_number
         ORDER BY u.first_name ASC, u.last_name ASC
         LIMIT ? OFFSET ?"; // Add LIMIT and OFFSET

// Add LIMIT and OFFSET parameters
$list_params = $params; // Use search params
$list_params[] = $items_per_page;
$list_types = $types . "i"; // Add type for LIMIT
$list_params[] = $offset;
$list_types .= "i"; // Add type for OFFSET

$stmt_list = $mysqli->prepare($sql);
if ($stmt_list) {
    if (!empty($list_types)) {
        $stmt_list->bind_param($list_types, ...$list_params);
    }
    if ($stmt_list->execute()) {
        $result = $stmt_list->get_result();
        while ($row = $result->fetch_assoc()) {
            $advisors_data[] = $row;
        }
        $result->free();
    } else {
         $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงข้อมูล Advisor: ' . htmlspecialchars($stmt_list->error) . '</p>';
    }
    $stmt_list->close();
} else {
     $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการ Prepare Query Advisor: ' . htmlspecialchars($mysqli->error) . '</p>';
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
          <div class="bg-gradient-info shadow-info border-radius-lg pt-4 pb-3">
            <h6 class="text-white text-capitalize ps-3"><?php echo $page_title; ?></h6>
          </div>
        </div>
        <div class="card-body px-4 pb-2">

          <?php if (!empty($message)) : ?>
          <div
            class="alert alert-dismissible text-white fade show <?php echo (strpos($message, 'success') !== false || strpos($message, 'สำเร็จ') !== false) ? 'alert-success bg-gradient-success' : ((strpos($message, 'warning') !== false || strpos($message, 'เตือน') !== false) ? 'alert-warning bg-gradient-warning' : 'alert-danger bg-gradient-danger'); ?>"
            role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close p-3" data-bs-dismiss="alert" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <?php endif; ?>
          <?php if (!empty($page_error)) : ?>
          <div class="alert alert-danger text-white mx-4" role="alert">
            <?php echo $page_error; ?>
          </div>
          <?php endif; ?>

          <form action="index.php" method="get" class="row g-3 align-items-end mb-4">
            <input type="hidden" name="page" value="advisors_list">
            <div class="col-md-4">
              <div class="input-group input-group-static">
                <label for="search_name">ค้นหาชื่อ/นามสกุล</label>
                <input type="text" class="form-control" id="search_name" name="search_name"
                  value="<?php echo htmlspecialchars($search_name); ?>">
              </div>
            </div>
            <div class="col-md-4">
              <div class="input-group input-group-static">
                <label for="search_gcode">ค้นหารหัสกลุ่มเรียน</label>
                <input type="text" class="form-control" id="search_gcode" name="search_gcode"
                  value="<?php echo htmlspecialchars($search_group_code); ?>">
              </div>
            </div>
            <div class="col-md-auto">
              <button type="submit" class="btn bg-gradient-primary mb-0">
                <i class="material-symbols-rounded text-sm">search</i>&nbsp;ค้นหา
              </button>
              <?php if (!empty($search_name) || !empty($search_group_code)): ?>
              <a href="index.php?page=advisors_list" class="btn btn-outline-secondary mb-0">ล้างค้นหา</a>
              <?php endif; ?>
            </div>
          </form>


          <div class="table-responsive p-0">
            <table class="table align-items-center mb-0">
              <thead>
                <tr>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ชื่อ-สกุล</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Username</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">กลุ่มในที่ปรึกษา
                  </th>
                  <th class="text-secondary opacity-7">จัดการ</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($advisors_data)) : ?>
                <?php foreach ($advisors_data as $advisor) : ?>
                <tr>
                  <td>
                    <div class="d-flex px-2 py-1">
                      <div class="d-flex flex-column justify-content-center">
                        <h6 class="mb-0 text-sm">
                          <?php echo htmlspecialchars($advisor['first_name'] . ' ' . $advisor['last_name']); ?></h6>
                        <p class="text-xs text-secondary mb-0">
                          <?php echo htmlspecialchars($advisor['email'] ?? 'N/A'); ?></p>
                      </div>
                    </div>
                  </td>
                  <td>
                    <p class="text-xs font-weight-bold mb-0"><?php echo htmlspecialchars($advisor['username']); ?></p>
                  </td>
                  <td>
                    <p class="text-xs text-secondary mb-0">
                      <?php echo htmlspecialchars($advisor['advised_groups'] ?? '-'); ?></p>
                  </td>
                  <td class="align-middle">
                    <a href="index.php?page=advisor_form&user_id=<?php echo $advisor['user_id']; ?>"
                      class="btn btn-warning btn-sm mb-0" data-bs-toggle="tooltip" data-bs-placement="top"
                      title="แก้ไขรหัสพนักงาน">
                      <i class="material-symbols-rounded text-sm">edit</i>
                    </a>
                    <a href="index.php?page=user_form&user_id=<?php echo $advisor['user_id']; ?>"
                      class="btn btn-info btn-sm mb-0" data-bs-toggle="tooltip" data-bs-placement="top"
                      title="แก้ไขข้อมูลผู้ใช้">
                      <i class="material-symbols-rounded text-sm">person</i>
                    </a>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php else : ?>
                <tr>
                  <td colspan="5" class="text-center">
                    <?php echo (!empty($search_name) || !empty($search_group_code)) ? 'ไม่พบข้อมูล Advisor ที่ตรงกับเงื่อนไข' : 'ยังไม่มีข้อมูล Advisor'; ?>
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
                          // Build query string for pagination links
                          $query_params = [];
                          if (!empty($search_name)) $query_params['search_name'] = $search_name;
                          if (!empty($search_group_code)) $query_params['search_gcode'] = $search_group_code;
                          $base_page_url = "index.php?page=advisors_list&" . http_build_query($query_params);

                          // Previous button
                          $prev_page = $current_page - 1;
                        ?>
              <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo $base_page_url; ?>&p=<?php echo $prev_page; ?>"
                  aria-label="Previous">
                  <span class="material-symbols-rounded">keyboard_arrow_left</span>
                  <span class="sr-only"></span>
                </a>
              </li>

              <?php
                          // Page numbers
                          $start_page = max(1, $current_page - 2);
                          $end_page = min($total_pages, $current_page + 2);

                          if ($start_page > 1) {
                              echo '<li class="page-item"><a class="page-link" href="' . $base_page_url . '&p=1">1</a></li>';
                              if ($start_page > 2) {
                                  echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                              }
                          }

                          for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
              <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                <a class="page-link" href="<?php echo $base_page_url; ?>&p=<?php echo $i; ?>"><?php echo $i; ?></a>
              </li>
              <?php endfor;

                          if ($end_page < $total_pages) {
                              if ($end_page < $total_pages - 1) {
                                  echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                              }
                              echo '<li class="page-item"><a class="page-link" href="' . $base_page_url . '&p=' . $total_pages . '">' . $total_pages . '</a></li>';
                          }
                        ?>

              <?php
                          // Next button
                          $next_page = $current_page + 1;
                        ?>
              <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo $base_page_url; ?>&p=<?php echo $next_page; ?>" aria-label="Next">
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