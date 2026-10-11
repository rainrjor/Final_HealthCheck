<?php
session_start();

/* ===============================
   AUTH CHECK
================================ */
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Doctor') {
    header("Location: login.php");
    exit();
}

$doctorId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];

/* ===============================
   DATABASE CONNECTION
================================ */
$conn = new mysqli("localhost", "root", "", "HealthDB");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* ===============================
   GET DASHBOARD STATISTICS
================================ */
// Count pending consultations
$stmt = $conn->prepare("
    SELECT COUNT(*) as pending_count
    FROM Consultation c
    INNER JOIN Booking b ON c.Consultation_ID = b.Consultation_ID
    WHERE c.Doctor_ID = ? AND b.Booking_Status = 'Pending'
");
$stmt->bind_param("i", $doctorId);
$stmt->execute();
$pendingCount = $stmt->get_result()->fetch_assoc()['pending_count'];

// Count approved consultations
$stmt = $conn->prepare("
    SELECT COUNT(*) as approved_count
    FROM Consultation c
    INNER JOIN Booking b ON c.Consultation_ID = b.Consultation_ID
    WHERE c.Doctor_ID = ? AND b.Booking_Status = 'Approved'
");
$stmt->bind_param("i", $doctorId);
$stmt->execute();
$approvedCount = $stmt->get_result()->fetch_assoc()['approved_count'];

/* ===============================
   HANDLE LOGOUT
================================ */
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HealthCheck Dashboard - Doctor</title>
  <link rel="stylesheet" href="DoctorMainStyle.css">
</head>

<body>
  <!-- LEFT SIDEBAR -->
  <div class="LeftDashboard">
    <div class="Logo">
      <img src="assets/Logo-Notitle.png" alt="Logo">
      <h1>HealthCheck</h1>
    </div>

    <div class="DashboardControls">
      <div class="MainButtons">
        <button class="nav-btn active" data-page="home">
          <span class="button-icon">🏠</span> Home
        </button>
        <button class="nav-btn" data-page="consultations">
          <span class="button-icon">🩺</span> Consultations
          <?php if ($pendingCount > 0): ?>
            <span class="notification-badge"><?= $pendingCount ?></span>
          <?php endif; ?>
        </button>
        <button class="nav-btn" data-page="appointments">
          <span class="button-icon">📅</span> Appointments
        </button>
        <button class="nav-btn" data-page="account">
          <span class="button-icon">👤</span> Account
        </button>
      </div>

      <div class="LowerButtons">
        <button class="nav-btn" data-page="logout">
          <span class="button-icon">🚪</span> Log Out
        </button>
      </div>
    </div>
  </div>

  <!-- RIGHT CONTENT -->
  <div class="RightContent">
    <div class="WelcomeBanner">
      <h1>Welcome back,<br><?= htmlspecialchars($userName) ?></h1>
    </div>

    <div class="Slogan">
      <h1>YOUR HEALTH,<br>IS OUR PRIORITY</h1>
      <p>Manage your consultations and appointments efficiently.</p>
    </div>

   

  
  </div>

  <script>
    document.addEventListener("DOMContentLoaded", () => {
      const navButtons = document.querySelectorAll('.nav-btn');

      function navigateToPage(page) {
        switch (page) {
          case 'home':
            console.log('Already on Home page');
            break;
          case 'consultations':
            window.location.href = "DoctorConsultation.php";
            break;
          case 'appointments':
            window.location.href = "DoctorAppointment.php";
            break;
          case 'account':
            window.location.href = "DoctorProfile.php";
            break;
          case 'logout':
            if (confirm('Are you sure you want to log out?')) {
              window.location.href = "login.php";
            }
            break;
          default:
            console.log('Unknown page:', page);
        }
      }

      navButtons.forEach(button => {
        button.addEventListener('click', function () {
          const page = this.getAttribute('data-page');
          navigateToPage(page);
        });
      });

      console.log('HealthCheck Doctor Dashboard initialized successfully');
    });
  </script>

  <style>
    .DashboardStats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin: 30px 0;
      padding: 0 40px;
    }

  

    .StatCard:hover {
      transform: translateY(-5px);
      box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
    }

    .StatIcon {
      font-size: 40px;
      width: 70px;
      height: 70px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f0f8ff;
      border-radius: 50%;
    }

    .StatInfo h3 {
      font-size: 32px;
      margin: 0;
      color: #00aaff;
    }

    .StatInfo p {
      margin: 5px 0 0 0;
      color: #666;
      font-size: 14px;
    }

    .notification-badge {
      background: #ff4444;
      color: white;
      border-radius: 10px;
      padding: 2px 8px;
      font-size: 12px;
      margin-left: 5px;
    }
  </style>
</body>

</html>

<?php $conn->close(); ?>