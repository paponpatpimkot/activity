<?php
// ไฟล์ controller หลัก (index.php) - ปรับปรุงโครงสร้าง

ob_start(); // เริ่ม Output Buffering
session_start();
require 'db_connect.php'; // ตรวจสอบ Path ให้ถูกต้อง
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) { 
  header('Location: ../../login.php?error=unauthorized');
  exit;
}

$message = ''; // ตัวแปรสำหรับเก็บข้อความแจ้งเตือน (อาจจะใช้ Session แทนก็ได้)
$page = $_GET['page'] ?? 'advisor_summary'; // กำหนดหน้า default

?>

<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <link rel="apple-touch-icon" sizes="76x76" href="../../assets/img/apple-icon.png">
  <link rel="icon" type="image/png" href="../../assets/img/tatc_logo.gif">
  <title>
    ระบบบันทึกชั่วโมงกิจกรรม - <?php echo htmlspecialchars($page_title ?? 'Advisor'); // แสดง Title ของหน้า 
                                ?>
  </title>
  <!--     Fonts and icons     -->
  <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Kanit:300,400,500,600,700,900" />
  <!-- Nucleo Icons -->
  <link href="../../assets/css/nucleo-icons.css" rel="stylesheet" />
  <link href="../../assets/css/nucleo-svg.css" rel="stylesheet" />
  <!-- Font Awesome Icons -->
  <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
  <!-- Material Icons -->
  <link rel="stylesheet"
    href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
  <!-- CSS Files -->
  <link id="pagestyle" href="../../assets/css/material-dashboard.css?v=3.2.0" rel="stylesheet" />
  <link rel="stylesheet" href="../../assets/css/custom.css">
  <style>
  * {
    font-family: 'Kanit', sans-serif;
    /* ใช้ Kanit */
  }

  /* อาจจะเพิ่ม CSS อื่นๆ ที่นี่ */
  .table th,
  .table td {
    white-space: nowrap;
    /* ป้องกันการตัดข้อความในตาราง */
  }

  .alert .btn-close {
    /* ทำให้ปุ่มปิด Alert เห็นชัดขึ้น */
    filter: invert(1) grayscale(100%) brightness(200%);
  }

  .navbar-vertical.navbar-expand-xs .navbar-collapse {
    display: block;
    overflow: auto;
    height: calc(100vh - 100px);
  }
  </style>
</head>

<body class="g-sidenav-show bg-gray-100">
  <?php //include 'aside.php'; ?>
  <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg ">
    <?php include 'navbar.php'; ?>
    <div class="container-fluid py-2">
      <?php
      // --- ส่วน Include เนื้อหาหลัก ---
      $pages_directory = '../actions/'; 
      $file_to_include = '';
      $page_title = ''; // Reset page title

      switch (strtolower($page)) { // ใช้ strtolower เพื่อไม่สนตัวพิมพ์เล็ก/ใหญ่
        case 'advisor_summary':
          $page_title = 'หน้าสรุปสำหรับครูที่ปรึกษา';
          $file_to_include = 'advisor_summary.php'; // ไฟล์เนื้อหา Dashboard
          break;        
        case 'advisor_student_detail':          
          $file_to_include = 'advisor_student_detail.php';
          break;      
        case 'edit_profile':
          $page_title = 'แก้ไขข้อมูลส่วนตัว';
          $file_to_include = $pages_directory . 'edit_profile.php'; // สมมติว่าไฟล์อยู่ใน pages
          break;  
        default:
          $page_title = 'ไม่พบหน้า';
          $file_to_include = '404.php'; // หน้า 404
          break;
      }

      // --- ทำการ Include ไฟล์เนื้อหา ---
      if (file_exists($file_to_include)) {
        // ส่งต่อตัวแปรที่จำเป็น เช่น $mysqli, $message ไปยังไฟล์ include
        include $file_to_include;
      } else {
        echo '<div class="alert alert-danger text-white mx-4" role="alert">เกิดข้อผิดพลาด: ไม่พบไฟล์หน้าที่ต้องการ (' . htmlspecialchars($file_to_include, ENT_QUOTES, 'UTF-8') . ')</div>';
        // อาจจะ include หน้า 404 สำรองอีกที
        $fallback_404 = $pages_directory . '404.php';
        if (file_exists($fallback_404)) {
          include $fallback_404;
        }
      }
      ?>

      <?php include '../footer.php'; // Include Footer (ตรวจสอบ Path) 
      ?>
    </div>
  </main>

  <!--   Core JS Files   -->
  <script src="../../assets/js/core/popper.min.js"></script>
  <script src="../../assets/js/core/bootstrap.min.js"></script>
  <script src="../../assets/js/plugins/perfect-scrollbar.min.js"></script>
  <script src="../../assets/js/plugins/smooth-scrollbar.min.js"></script>
  <script src="../../assets/js/plugins/chartjs.min.js"></script>
  <script>
  var ctx = document.getElementById("chart-bars").getContext("2d");

  new Chart(ctx, {
    type: "bar",
    data: {
      labels: ["M", "T", "W", "T", "F", "S", "S"],
      datasets: [{
        label: "Views",
        tension: 0.4,
        borderWidth: 0,
        borderRadius: 4,
        borderSkipped: false,
        backgroundColor: "#43A047",
        data: [50, 45, 22, 28, 50, 60, 76],
        barThickness: 'flex'
      }, ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false,
        }
      },
      interaction: {
        intersect: false,
        mode: 'index',
      },
      scales: {
        y: {
          grid: {
            drawBorder: false,
            display: true,
            drawOnChartArea: true,
            drawTicks: false,
            borderDash: [5, 5],
            color: '#e5e5e5'
          },
          ticks: {
            suggestedMin: 0,
            suggestedMax: 500,
            beginAtZero: true,
            padding: 10,
            font: {
              size: 14,
              lineHeight: 2
            },
            color: "#737373"
          },
        },
        x: {
          grid: {
            drawBorder: false,
            display: false,
            drawOnChartArea: false,
            drawTicks: false,
            borderDash: [5, 5]
          },
          ticks: {
            display: true,
            color: '#737373',
            padding: 10,
            font: {
              size: 14,
              lineHeight: 2
            },
          }
        },
      },
    },
  });


  var ctx2 = document.getElementById("chart-line").getContext("2d");

  new Chart(ctx2, {
    type: "line",
    data: {
      labels: ["J", "F", "M", "A", "M", "J", "J", "A", "S", "O", "N", "D"],
      datasets: [{
        label: "Sales",
        tension: 0,
        borderWidth: 2,
        pointRadius: 3,
        pointBackgroundColor: "#43A047",
        pointBorderColor: "transparent",
        borderColor: "#43A047",
        backgroundColor: "transparent",
        fill: true,
        data: [120, 230, 130, 440, 250, 360, 270, 180, 90, 300, 310, 220],
        maxBarThickness: 6

      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false,
        },
        tooltip: {
          callbacks: {
            title: function(context) {
              const fullMonths = ["January", "February", "March", "April", "May", "June", "July", "August",
                "September", "October", "November", "December"
              ];
              return fullMonths[context[0].dataIndex];
            }
          }
        }
      },
      interaction: {
        intersect: false,
        mode: 'index',
      },
      scales: {
        y: {
          grid: {
            drawBorder: false,
            display: true,
            drawOnChartArea: true,
            drawTicks: false,
            borderDash: [4, 4],
            color: '#e5e5e5'
          },
          ticks: {
            display: true,
            color: '#737373',
            padding: 10,
            font: {
              size: 12,
              lineHeight: 2
            },
          }
        },
        x: {
          grid: {
            drawBorder: false,
            display: false,
            drawOnChartArea: false,
            drawTicks: false,
            borderDash: [5, 5]
          },
          ticks: {
            display: true,
            color: '#737373',
            padding: 10,
            font: {
              size: 12,
              lineHeight: 2
            },
          }
        },
      },
    },
  });

  var ctx3 = document.getElementById("chart-line-tasks").getContext("2d");

  new Chart(ctx3, {
    type: "line",
    data: {
      labels: ["Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
      datasets: [{
        label: "Tasks",
        tension: 0,
        borderWidth: 2,
        pointRadius: 3,
        pointBackgroundColor: "#43A047",
        pointBorderColor: "transparent",
        borderColor: "#43A047",
        backgroundColor: "transparent",
        fill: true,
        data: [50, 40, 300, 220, 500, 250, 400, 230, 500],
        maxBarThickness: 6

      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false,
        }
      },
      interaction: {
        intersect: false,
        mode: 'index',
      },
      scales: {
        y: {
          grid: {
            drawBorder: false,
            display: true,
            drawOnChartArea: true,
            drawTicks: false,
            borderDash: [4, 4],
            color: '#e5e5e5'
          },
          ticks: {
            display: true,
            padding: 10,
            color: '#737373',
            font: {
              size: 14,
              lineHeight: 2
            },
          }
        },
        x: {
          grid: {
            drawBorder: false,
            display: false,
            drawOnChartArea: false,
            drawTicks: false,
            borderDash: [4, 4]
          },
          ticks: {
            display: true,
            color: '#737373',
            padding: 10,
            font: {
              size: 14,
              lineHeight: 2
            },
          }
        },
      },
    },
  });
  </script>
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
  <script src="../../assets/js/material-dashboard.min.js?v=3.2.0"></script>
</body>

</html>
<?php
// 7. สิ้นสุด Output Buffering และส่ง Output ทั้งหมดออกไป
ob_end_flush();
?>