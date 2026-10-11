<?php
session_start();

/* ===============================
   AUTH CHECK
================================ */
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Patient') {
    header("Location: login.php");
    exit();
}

$patientId = $_SESSION['user_id'];

/* ===============================
   DATABASE CONNECTION
================================ */
$conn = new mysqli("localhost", "root", "", "HealthDB");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* ===============================
   FETCH PATIENT INFO
================================ */
$stmt = $conn->prepare("
    SELECT 
        Patient_FirstName,
        Patient_LastName
    FROM Patient
    WHERE Patient_ID = ?
");
$stmt->bind_param("i", $patientId);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

if (!$patient) {
    die("Patient not found.");
}

/* ===============================
   FETCH APPOINTMENTS
================================ */
$stmt = $conn->prepare("
    SELECT 
        b.Booking_ID,
        CONCAT(d.Doctor_FirstName, ' ', d.Doctor_LastName) AS DoctorName,
        s.Services_Type AS DoctorSpecialization,
        b.Appointment_Date,
        b.Appointment_Time,
        b.Booking_Status,
        c.Gmeet_Link
    FROM Booking b
    INNER JOIN Consultation c ON b.Consultation_ID = c.Consultation_ID
    INNER JOIN Doctor d ON c.Doctor_ID = d.Doctor_ID
    INNER JOIN Specialization s ON d.Specialization_ID = s.Specialization_ID
    WHERE c.Patient_ID = ?
    ORDER BY b.Appointment_Date ASC, b.Appointment_Time ASC
");
$stmt->bind_param("i", $patientId);
$stmt->execute();
$appointments = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HealthCheck Dashboard - My Appointments</title>
<link rel="stylesheet" href="AppointmentStyle.css">
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
      <button class="nav-btn" data-page="home"><span class="button-icon">🏠</span> Home</button>
      <button class="nav-btn" data-page="services"><span class="button-icon">🩺</span> Services</button>

      <button class="nav-btn active" data-page="appointments"><span class="button-icon">📅</span> Appointments</button>
      <button class="nav-btn" data-page="account"><span class="button-icon">👤</span> Account</button>
    </div>

    <div class="LowerButtons">
      <button class="nav-btn" data-page="logout"><span class="button-icon">🚪</span> Log Out</button>
    </div>
  </div>
</div>

<!-- RIGHT CONTENT -->
<div class="RightContent">
  <div class="WelcomeBanner">
    <h1>Welcome back,<br><?= htmlspecialchars($patient['Patient_FirstName'] . " " . $patient['Patient_LastName']) ?></h1>
  </div>

  <div class="AppointmentsContent">
    <div class="ContentHeader">
      <h2>My Appointments</h2>
      <p>View your upcoming medical appointments</p>
    </div>

    <div class="AppointmentsTable">
      <table>
        <thead>
          <tr>
            <th>Booking ID</th>
            <th>Doctor's Name</th>
            <th>Specialization</th>
            <th>Date</th>
            <th>Time</th>
            <th>Status</th>
            <th>Gmeet Link</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($appointments->num_rows > 0): ?>
            <?php while($row = $appointments->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($row['Booking_ID']) ?></td>
                <td><?= htmlspecialchars($row['DoctorName']) ?></td>
                <td><?= htmlspecialchars($row['DoctorSpecialization']) ?></td>
                <td><?= $row['Appointment_Date'] ? htmlspecialchars(date("m/d/Y", strtotime($row['Appointment_Date']))) : 'Not scheduled' ?></td>
                <td><?= $row['Appointment_Time'] ? htmlspecialchars(date("h:i A", strtotime($row['Appointment_Time']))) : 'Not scheduled' ?></td>
                <td>
                  <?php
                    $statusClass = strtolower($row['Booking_Status']);
                    $statusIcon = $statusClass === "approved" ? "✓" : ($statusClass === "pending" ? "💬" : "🔴");
                  ?>
                  <span class="Status <?= $statusClass ?>">
                    <span class="StatusIcon"><?= $statusIcon ?></span> <?= ucfirst($row['Booking_Status']) ?>
                  </span>
                </td>
                <td>
                  <?php if (!empty($row['Gmeet_Link'])): ?>
                    <?php
                      // Extract just the meeting code from the full URL
                      $meetCode = str_replace('https://meet.google.com/', '', $row['Gmeet_Link']);
                    ?>
                    <a href="<?= htmlspecialchars($row['Gmeet_Link']) ?>" target="_blank" class="GmeetLink"><?= htmlspecialchars($meetCode) ?></a>
                  <?php else: ?>
                    N/A
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="7" style="text-align:center;">No appointments found.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
  document.addEventListener("DOMContentLoaded", () => {
    const navButtons = document.querySelectorAll('.nav-btn');
    navButtons.forEach(button => {
      button.addEventListener('click', function () {
        const page = this.getAttribute('data-page');
        switch (page) {
          case 'home': window.location.href = "Home.php"; break;
          case 'services': window.location.href = "Services.php"; break;
          case 'status': window.location.href = "Status.php"; break;
          case 'appointments': break;
          case 'account': window.location.href = "Account.php"; break;
          case 'logout':
            if(confirm("Are you sure you want to log out?")) window.location.href = "Login.php";
            break;
        }
      });
    });

    const meetLinks = document.querySelectorAll('.GmeetLink');
    meetLinks.forEach(link => {
      link.addEventListener('click', function (e) {
        e.preventDefault();
        const meetUrl = this.href;
        if(confirm(`Join Google Meet: ${this.textContent}?`)) {
          window.open(meetUrl, '_blank');
        }
      });
    });
  });
</script>
</body>
</html>

<?php $conn->close(); ?>