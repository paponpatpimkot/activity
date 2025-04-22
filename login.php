<?php
session_start(); // เริ่มต้น Session ก่อนเสมอ

// ถ้าผู้ใช้ล็อกอินอยู่แล้ว ให้ redirect ไปหน้า dashboard ตาม role
if (isset($_SESSION['user_id'])) {
    $role_id = $_SESSION['role_id'];
    if ($role_id == 1) {
        header('Location: pages/admin/index.php');
    } elseif ($role_id == 2) {
        header('Location: pages/advisor/index.php');
    } elseif ($role_id == 3) {
        header('Location: pages/student/index.php');
    } elseif ($role_id == 4) {
        header('Location: pages/staff/index.php');
    } else {
        // ถ้า Role ไม่ถูกต้อง อาจจะ Destroy Session แล้วส่งไป Login ใหม่
        session_unset();
        session_destroy();
        header('Location: login.php?error=invalid_role');
    }
    exit;
}

$error_message = '';
// ตรวจสอบว่ามีข้อความแจ้งเตือนจากการล็อกอินไม่สำเร็จหรือไม่
if (isset($_GET['error'])) {
    if ($_GET['error'] == 1) {
        $error_message = '<p style="color: red;">ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง!</p>';
    } elseif ($_GET['error'] == 2) {
         $error_message = '<p style="color: red;">เกิดข้อผิดพลาดในการประมวลผลข้อมูล</p>';
    } elseif ($_GET['error'] == 'invalid_role') {
         $error_message = '<p style="color: red;">บทบาทผู้ใช้ไม่ถูกต้อง</p>';
    } elseif ($_GET['error'] == 'unauthorized') {
         $error_message = '<p style="color: red;">คุณไม่มีสิทธิ์เข้าถึงหน้านี้</p>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <link rel="apple-touch-icon" sizes="76x76" href="../assets/img/apple-icon.png">
  <link rel="icon" type="image/png" href="../assets/img/favicon.png">
  <title>
    Students Activity System
  </title>
  <!--     Fonts and icons     -->
  <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Kanit" />
  <!-- Nucleo Icons -->
  <link href="assets/css/nucleo-icons.css" rel="stylesheet" />
  <link href="assets/css/nucleo-svg.css" rel="stylesheet" />
  <!-- Font Awesome Icons -->
  <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
  <!-- Material Icons -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
  <!-- CSS Files -->
  <link id="pagestyle" href="assets/css/material-dashboard.css?v=3.2.0" rel="stylesheet" />
  <style>
    * {
      font-family: Kanit;
    }
  </style>
</head>

<body class="bg-gray-200">
  <main class="main-content  mt-0">
    <div class="page-header align-items-start min-vh-100">
      <span class="mask bg-gradient-dark opacity-6"></span>
      <div class="container my-auto">
        <div class="row">
          <div class="col-lg-4 col-md-8 col-12 mx-auto">
            <div class="card z-index-0 fadeIn3 fadeInBottom">
              <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                <div class="bg-gradient-primary shadow-dark border-radius-lg py-2 pe-1">
                  <h4 class="text-white text-center my-2">ระบบบันทึกชั่วโมงกิจกรรม</h4>
                </div>
              </div>
              <div class="card-body">
              <?php if (!empty($error_message)) echo "<div class='error-message'>{$error_message}</div>"; ?>
                <form role="form" class="text-start" method="POST" action="handle_login.php">
                  <div class="input-group input-group-outline my-3">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" name="username">
                  </div>
                  <div class="input-group input-group-outline mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" class="form-control" name="password">
                  </div>

                  <div class="text-center">
                    <button type="submit" class="btn bg-gradient-info w-100 my-4 mb-2">Sign in</button>
                  </div>
                </form>
              </div>
            </div>
            <ul class="text-sm mt-3 text-light">
              <div class="row">
                <div class="col">
                  <li>สำหรับนักศึกษา
                    <div>Username=รหัสประจำตัวนักศึกษา </div>
                    <div>Password=รหัสประจำตัวประชาชน </div>
                  </li>
                </div>
                <div class="col">
                  <li class="mt-2">สำหรับครูที่ปรึกษาและเจ้าหน้าที่
                    <div>Username=รหัสประจำตัวประชาชน </div>
                    <div>Password=รหัสประจำตัวประชาชน </div>
                  </li>
                </div>
              </div>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </main>
  <!--   Core JS Files   -->
  <script src="assets/js/core/popper.min.js"></script>
  <script src="assets/js/core/bootstrap.min.js"></script>
  <script src="assets/js/plugins/perfect-scrollbar.min.js"></script>
  <script src="assets/js/plugins/smooth-scrollbar.min.js"></script>
  <script>
    var win = navigator.platform.indexOf('Win') > -1;
    if (win && document.querySelector('#sidenav-scrollbar')) {
      var options = {
        damping: '0.5'
      }
      Scrollbar.init(document.querySelector('#sidenav-scrollbar'), options);
    }
  </script>
  <!-- Github buttons -->
  <script async defer src="https://buttons.github.io/buttons.js"></script>
  <!-- Control Center for Material Dashboard: parallax effects, scripts for the example pages etc -->
  <script src="assets/js/material-dashboard.min.js?v=3.2.0"></script>
</body>

</html>