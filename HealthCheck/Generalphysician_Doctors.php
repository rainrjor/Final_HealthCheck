<?php
session_start();

/* ===============================
   AUTH CHECK
================================ */
if (!isset($_SESSION['user_name'])) {
    header("Location: login.php");
    exit();
}

$userName = $_SESSION['user_name'];

/* ===============================
   DATABASE CONNECTION
================================ */
$host = "localhost";
$dbUsername = "root";
$dbPassword = "";
$dbName = "HealthDB";

$conn = new mysqli($host, $dbUsername, $dbPassword, $dbName);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* ===============================
   FETCH GENERAL PHYSICIANS ONLY
================================ */
$sql = "SELECT 
            d.Doctor_ID,
            d.Doctor_FirstName,
            d.Doctor_LastName,
            d.Doctor_YearsExperience,
            d.Day_of_Week,
            d.Start_Time,
            d.End_Time
        FROM Doctor d
        INNER JOIN Specialization s 
            ON d.Specialization_ID = s.Specialization_ID
        WHERE s.Services_Type = 'General Physician'";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>HealthCheck | General Physician</title>
  <link rel="stylesheet" href="Generalphysician_DoctorsStyle.css">
</head>

<body>

<!-- ===============================
     LEFT SIDEBAR
================================ -->
<div class="LeftDashboard">
  <div class="Logo">
    <img src="assets/Logo-Notitle.png" alt="Logo">
    <h1>HealthCheck</h1>
  </div>

  <div class="DashboardControls">
    <div class="MainButtons">
      <button class="nav-btn" data-page="home">🏠 Home</button>
      <button class="nav-btn active" data-page="services">🩺 Services</button>
    
      <button class="nav-btn" data-page="appointments">📅 Appointments</button>
      <button class="nav-btn" data-page="account">👤 Account</button>
    </div>

    <div class="LowerButtons">
      <button class="nav-btn" data-page="logout">🚪 Log Out</button>
    </div>
  </div>
</div>

<!-- ===============================
     RIGHT CONTENT
================================ -->
<div class="RightContent">

  <div class="WelcomeBanner">
    <h1>Welcome back,<br><?= htmlspecialchars($userName) ?></h1>
  </div>

  <div class="ServiceContent">
    <div class="ServiceHeader">
      <h2>General Physician</h2>
      <p>
        From everyday illnesses to long-term conditions, a general physician provides expert care
        for patients of all ages.
      </p>
    </div>

    <!-- ===============================
         DOCTORS LIST
    ================================ -->
    <div class="DoctorsGrid">

      <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($doctor = $result->fetch_assoc()): 
          $doctorId = $doctor['Doctor_ID'];
          $fullName =  $doctor['Doctor_FirstName'] . " " . $doctor['Doctor_LastName'];
          $experience = $doctor['Doctor_YearsExperience'];
          $days = $doctor['Day_of_Week'];
          $start = date("g:i a", strtotime($doctor['Start_Time']));
          $end = date("g:i a", strtotime($doctor['End_Time']));
        ?>

        <div class="DoctorCard">
          <div class="DoctorHeader">
            <img src="assets/def.jpg" alt="<?= $fullName ?>" class="DoctorAvatar">
            <div>
              <h3><?= $fullName ?></h3>
              <p>General Physician</p>
            </div>
          </div>

          <div class="DoctorDetails">
            <p><strong>Experience:</strong> <?= $experience ?> Years</p>
            <p><strong>Availability:</strong> <?= $days ?></p>
            <p><strong>Hours:</strong> <?= $start ?> - <?= $end ?></p>
          </div>

          <!-- IMPORTANT: DOCTOR ID -->
          <button 
            class="BookButton"
            data-doctor-id="<?= $doctorId ?>"
            data-doctor-name="<?= $fullName ?>"
          >
            Book Now
          </button>
        </div>

        <?php endwhile; ?>
      <?php else: ?>
        <p>No General Physicians available.</p>
      <?php endif; ?>

    </div>
  </div>
</div>

<!-- ===============================
     JAVASCRIPT
================================ -->
<script>
/* NAVIGATION */
document.querySelectorAll('.nav-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const page = btn.dataset.page;

    if (page === 'logout') {
      if (confirm('Are you sure you want to log out?')) {
        window.location.href = 'login.php';
      }
      return;
    }

    const routes = {
         home: 'Home.php',
      services: 'Services.php',
      appointments: 'Appointments.php',
      account: 'Account.php'
    };

    if (routes[page]) {
      window.location.href = routes[page];
    }
  });
});

/* BOOKING */
document.querySelectorAll('.BookButton').forEach(btn => {
  btn.addEventListener('click', () => {
    const doctorId = btn.dataset.doctorId;
    const doctorName = btn.dataset.doctorName;

    if (confirm(`Book appointment with ${doctorName}?`)) {
      localStorage.setItem('doctor_id', doctorId);
      localStorage.setItem('doctor_name', doctorName);
      localStorage.setItem('service', 'General Physician');

      window.location.href = 'ServicesBooking.php';
    }
  });
});
</script>

</body>
</html>

<?php $conn->close(); ?>
