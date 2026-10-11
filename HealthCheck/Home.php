<?php
session_start();

// Redirect to login if user is not logged in
if (!isset($_SESSION['user_name'])) {
    header("Location: login.php");
    exit();
}

// Get user info from session
$userName = $_SESSION['user_name'];
$userType = $_SESSION['user_type'];

// Handle logout
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
  <title>HealthCheck Dashboard</title>

  <!-- External CSS -->
  <link rel="stylesheet" href="HomeStyle.css">
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
        <button class="nav-btn" data-page="home" style="background-color:#00aaff;">
          <span class="button-icon">🏠</span> Home
        </button>
        <button class="nav-btn" data-page="services">
          <span class="button-icon">🩺</span> Services
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
      <h1>YOUR HEALTH,<br> IS OUR PRIORITY</h1>
      <p>Book free checkups and consultations anytime, anywhere.</p>
    </div>

    <div class="BTNappointment">
      <button id="bookAppointmentBtn">Book Appointment</button>
    </div>

  </div>

  <!-- JS -->
  <script>
    document.addEventListener("DOMContentLoaded", () => {
      const navButtons = document.querySelectorAll(".nav-btn");
      const bookAppointmentBtn = document.getElementById("bookAppointmentBtn");

      const routes = {
        home: "Home.php",
        services: "Services.php",
       
        appointments: "Appointments.php",
        account: "Account.php",
        logout: "Login.php"
      };

      navButtons.forEach(btn => {
        btn.addEventListener("click", () => {
          const page = btn.dataset.page;

          if (page === "logout") {
            if (confirm("Are you sure you want to log out?")) {
              window.location.href = routes.logout;
            }
            return;
          }

          if (routes[page]) {
            window.location.href = routes[page];
          }
        });
      });

      bookAppointmentBtn.addEventListener("click", () => {
        window.location.href = "Services.php";
      });
    });
  </script>

</body>
</html>
