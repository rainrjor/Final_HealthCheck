<?php
session_start();

/* ===============================
   AUTH CHECK
================================ */
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

/* ===============================
   DATABASE CONNECTION
================================ */
$conn = new mysqli("localhost", "root", "", "HealthDB");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* ===============================
   FETCH STATISTICS
================================ */
// Total Patients
$patientCount = $conn->query("SELECT COUNT(*) as total FROM Patient")->fetch_assoc()['total'];

// Total Doctors
$doctorCount = $conn->query("SELECT COUNT(*) as total FROM Doctor")->fetch_assoc()['total'];

// Total Appointments
$appointmentCount = $conn->query("SELECT COUNT(*) as total FROM Booking")->fetch_assoc()['total'];

// Total Users
$totalUsers = $patientCount + $doctorCount;

// Recent Activity
$recentActivity = $conn->query("
    SELECT 
        CONCAT(p.Patient_FirstName, ' ', p.Patient_LastName) as user_name,
        'Patient' as user_type,
        'Booked Appointment' as action,
        b.Appointment_Date as action_date
    FROM Booking b
    JOIN Consultation c ON b.Consultation_ID = c.Consultation_ID
    JOIN Patient p ON c.Patient_ID = p.Patient_ID
    ORDER BY b.Appointment_Date DESC
    LIMIT 5
");

// Specialization stats
$specializationStats = $conn->query("
    SELECT s.Services_Type, COUNT(d.Doctor_ID) as doctor_count
    FROM Specialization s
    LEFT JOIN Doctor d ON s.Specialization_ID = d.Specialization_ID
    GROUP BY s.Specialization_ID
");

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HealthCheck Admin Dashboard</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Arial', sans-serif;
    }

    html, body {
      height: 100%;
      overflow: hidden;
    }

    body {
      display: flex;
      height: 100vh;
      overflow: hidden;
    }

    /* LEFT DASHBOARD */
    .LeftDashboard {
      width: 20%;
      height: 100vh;
      background-color: #272727;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 0px;
      color: white;
      position: relative;
      overflow: hidden;
    }

    .Logo {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      margin-top: 20px;
      margin-bottom: 40px;
      width: 100%;
    }

    .Logo img {
      width: 50px;
      height: 50px;
      object-fit: contain;
    }

    .Logo h1 {
      font-size: 35px;
      font-weight: bold;
      color: #ffffff;
    }

    .DashboardControls {
      margin-top: 5px;
      display: flex;
      flex-direction: column;
      width: 100%;
      height: calc(100% - 120px);
      overflow: hidden;
    }

    .MainButtons {
      flex-grow: 1;
      overflow: hidden;
    }

    .LowerButtons {
      margin-top: auto;
      padding-top: 20px;
      border-top: 1px solid #444;
      width: 100%;
    }

    .DashboardControls button {
      padding: 12px;
      margin: 8px 0;
      border: none;
      border-radius: 5px;
      background-color: #272727;
      color: white;
      font-size: 20px;
      cursor: pointer;
      text-align: left;
      padding-left: 20px;
      transition: background 0.3s;
      width: 100%;
      display: flex;
      align-items: center;
    }

    .DashboardControls button:hover {
      background-color: #00aaff !important;
      color: white;
    }

    .DashboardControls button.active {
      background-color: #00aaff;
      color: white;
    }

    .button-icon {
      margin-right: 10px;
      font-size: 20px;
    }

    .WelcomeBanner {
      background: linear-gradient(135deg, #00aaff 0%, #0066ff 100%);
      padding: 20px;
      color: white;
      box-shadow: 0 5px 15px rgba(0, 106, 255, 0.2);
    }

    .WelcomeBanner h1 {
      font-size: 42px;
      line-height: 1.2;
      margin-left: 10px;
    }

    .RightContent {
      overflow-y: auto;
      width: 80%;
      height: 100vh;
      background-color: #f5f7fa;
      display: flex;
      flex-direction: column;
    }

    .ManagementContainer {
      padding: 20px;
      flex-grow: 1;
      overflow-y: auto;
    }

    .ManagementHeader {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }

    .ManagementHeader h2 {
      font-size: 28px;
      color: #333;
    }

    .ManagementTable {
      width: 100%;
      border-collapse: collapse;
      background-color: white;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .ManagementTable th,
    .ManagementTable td {
      padding: 15px;
      text-align: left;
      border-bottom: 1px solid #eee;
    }

    .ManagementTable th {
      background-color: #f8f9fa;
      font-weight: 600;
      color: #555;
    }

    .ManagementTable tr:hover {
      background-color: #f8f9fa;
    }

    .StatsContainer {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 20px;
      margin-bottom: 20px;
    }

    .StatCard {
      background: white;
      border-radius: 8px;
      padding: 20px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    .StatNumber {
      font-size: 36px;
      font-weight: bold;
      color: #0066ff;
      margin-bottom: 5px;
    }

    .StatLabel {
      font-size: 14px;
      color: #666;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .AnalyticsContainer {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      margin-bottom: 20px;
    }

    .AnalyticsCard {
      background: white;
      border-radius: 8px;
      padding: 20px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .AnalyticsCard h3 {
      font-size: 18px;
      margin-bottom: 15px;
      color: #333;
    }

    .SpecializationItem {
      display: flex;
      justify-content: space-between;
      padding: 10px 0;
      border-bottom: 1px solid #eee;
    }

    .SpecializationItem:last-child {
      border-bottom: none;
    }

    .SpecializationName {
      font-weight: 600;
      color: #333;
    }

    .SpecializationCount {
      color: #0066ff;
      font-weight: 600;
    }
  </style>
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
        <button class="nav-btn active" data-page="overview"><span class="button-icon">📊</span> Overview</button>
        <button class="nav-btn" data-page="patients"><span class="button-icon">👥</span> Patients</button>
        <button class="nav-btn" data-page="doctors"><span class="button-icon">👨‍⚕️</span> Doctors</button>
      </div>

      <div class="LowerButtons">
        <button class="nav-btn" data-page="logout"><span class="button-icon">🚪</span> Log Out</button>
      </div>
    </div>
  </div>

  <!-- RIGHT CONTENT -->
  <div class="RightContent">
    <div class="WelcomeBanner">
      <h1>Admin Dashboard</h1>
    </div>

    <div class="ManagementContainer">
      <div class="StatsContainer">
        <div class="StatCard">
          <div class="StatNumber"><?= $patientCount ?></div>
          <div class="StatLabel">Total Patients</div>
        </div>
        <div class="StatCard">
          <div class="StatNumber"><?= $doctorCount ?></div>
          <div class="StatLabel">Total Doctors</div>
        </div>
        <div class="StatCard">
          <div class="StatNumber"><?= $appointmentCount ?></div>
          <div class="StatLabel">Total Appointments</div>
        </div>
        <div class="StatCard">
          <div class="StatNumber"><?= $totalUsers ?></div>
          <div class="StatLabel">Total Users</div>
        </div>
      </div>

      <div class="AnalyticsContainer">
        <div class="AnalyticsCard">
          <h3>Specialization Distribution</h3>
          <?php while ($spec = $specializationStats->fetch_assoc()): ?>
            <div class="SpecializationItem">
              <span class="SpecializationName"><?= htmlspecialchars($spec['Services_Type']) ?></span>
              <span class="SpecializationCount"><?= $spec['doctor_count'] ?> doctors</span>
            </div>
          <?php endwhile; ?>
        </div>
      </div>

      <div class="ManagementHeader">
        <h2>Recent Activity</h2>
      </div>

      <table class="ManagementTable">
        <thead>
          <tr>
            <th>User</th>
            <th>Type</th>
            <th>Action</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($activity = $recentActivity->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($activity['user_name']) ?></td>
              <td><?= htmlspecialchars($activity['user_type']) ?></td>
              <td><?= htmlspecialchars($activity['action']) ?></td>
              <td><?= date('M d, Y', strtotime($activity['action_date'])) ?></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <script>
    document.querySelectorAll('.nav-btn').forEach(btn => {
      btn.addEventListener('click', function () {
        const page = this.getAttribute('data-page');
        
        const routes = {
          overview: 'AdminMain.php',
          patients: 'AdminPatients.php',
          doctors: 'AdminDoctors.php'
        };

        if (page === 'logout') {
          if (confirm('Are you sure you want to log out?')) {
            window.location.href = 'login.php';
          }
        } else if (routes[page]) {
          window.location.href = routes[page];
        }
      });
    });
  </script>
</body>
</html>