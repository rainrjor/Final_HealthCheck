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
   GET DOCTOR ID FROM URL OR SESSION
================================ */
// Try to get from URL first, then from POST (localStorage redirect)
$doctorId = isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : 0;

// If no doctor_id in URL, check if it's being passed from localStorage redirect
if ($doctorId <= 0 && isset($_POST['doctor_id_from_storage'])) {
    $doctorId = intval($_POST['doctor_id_from_storage']);
}

if ($doctorId <= 0) {
    // Show a page that will read from localStorage and redirect
    ?>
    <!DOCTYPE html>
    <html>
    <head><title>Loading...</title></head>
    <body>
        <p>Loading doctor information...</p>
        <script>
            const doctorId = localStorage.getItem('doctor_id');
            if (doctorId) {
                window.location.href = 'ServicesBooking.php?doctor_id=' + doctorId;
            } else {
                alert('No doctor selected. Please select a doctor first.');
                window.location.href = 'Services.php';
            }
        </script>
    </body>
    </html>
    <?php
    exit();
}

/* ===============================
   FETCH PATIENT INFO
================================ */
$stmt = $conn->prepare("
    SELECT 
        Patient_FirstName,
        Patient_LastName,
        Patient_Email,
        Patient_PhoneNumber,
        Patient_Sex
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
   FETCH DOCTOR INFO
================================ */
$stmt = $conn->prepare("
    SELECT 
        d.Doctor_ID,
        CONCAT(d.Doctor_FirstName, ' ', d.Doctor_LastName) AS DoctorName,
        d.Doctor_Description,
        d.Start_Time,
        d.End_Time,
        d.Day_of_Week,
        s.Services_Type AS Specialization
    FROM Doctor d
    LEFT JOIN Specialization s ON d.Specialization_ID = s.Specialization_ID
    WHERE d.Doctor_ID = ?
");
$stmt->bind_param("i", $doctorId);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();

if (!$doctor) {
    die("Doctor not found.");
}

/* ===============================
   HANDLE CONFIRM BOOKING
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_booking'])) {

    $selectedDoctorId = intval($_POST['doctor_id']);
    $reason = trim($_POST['reason']);

    if ($selectedDoctorId <= 0 || empty($reason)) {
        die("Invalid booking data.");
    }

    // Insert Consultation
    $stmt = $conn->prepare("
        INSERT INTO Consultation
        (Patient_ID, Doctor_ID, Consultation_Reason, Consultation_Status, Consultation_Duration)
        VALUES (?, ?, ?, 'Pending', '00:30:00')
    ");
    $stmt->bind_param("iis", $patientId, $selectedDoctorId, $reason);
    $stmt->execute();

    $consultationId = $stmt->insert_id;

    // Insert Booking
    $stmt = $conn->prepare("
        INSERT INTO Booking (Consultation_ID, Booking_Status)
        VALUES (?, 'Pending')
    ");
    $stmt->bind_param("i", $consultationId);
    $stmt->execute();

    header("Location: Appointments.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HealthCheck Dashboard - Book Appointment</title>
<link rel="stylesheet" href="ServicesBookingStyle.css">
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
<div class="AppointmentContent">
      <!-- Doctor Information Section -->
      <div class="DoctorHeader">
        <button class="BackButton" id="backButton">
          <span class="button-icon">←</span> Back
        </button>

        <div class="DoctorInfo">
          <h2><?= htmlspecialchars($doctor['Specialization']) ?></h2>

          <div class="doctorimagename">
           <!-- <img src="assets/Johndoe.jpg" alt="Doctor Image"> 
           comment out image for now 
           --> 
            <h3><?= htmlspecialchars($doctor['DoctorName']) ?></h3>
          </div>

          <div class="DoctorDescription">
            <p><?= htmlspecialchars($doctor['Doctor_Description']) ?></p>
          </div>

          <div class="DoctorSchedule">
            <div class="ScheduleItem">
              <span class="label">Availability</span>
              <span class="value"><?= htmlspecialchars($doctor['Day_of_Week']) ?></span>
            </div>
            <div class="ScheduleItem">
              <span class="label">Hours</span>
              <span class="value">
                <?= date('g a', strtotime($doctor['Start_Time'])) ?> - <?= date('g a', strtotime($doctor['End_Time'])) ?>
              </span>
            </div>
            <div class="ScheduleItem">
              <span class="label">Duration</span>
              <span class="value">30 mins</span>
            </div>
          </div>
        </div>
      </div>

  <form method="POST">
    <input type="hidden" name="doctor_id" value="<?= $doctor['Doctor_ID'] ?>">
    <input type="hidden" name="confirm_booking" value="1">

    <div class="BookingForm">
      <div class="FormSection">
        <h3>Consultation Details</h3>
        <div class="FormGroup">
          <label>Reason for consultation</label>
          <textarea name="reason" id="consultationReason" required></textarea>
        </div>
      </div>

      <button type="button" class="BookNowButton" id="bookNowButton">Book Now</button>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmationModal" class="modal">
      <div class="modal-content">
        <div class="modal-header">
          <h2>Confirm Appointment</h2>
          <button type="button" class="close-button">&times;</button>
        </div>
        <div class="modal-body">
          <div class="appointment-summary">
            <div class="summary-item">
              <span class="summary-label">Doctor:</span>
              <span class="summary-value"><?= htmlspecialchars($doctor['DoctorName']) ?></span>
            </div>
            <div class="summary-item">
              <span class="summary-label">Specialization:</span>
              <span class="summary-value"><?= htmlspecialchars($doctor['Specialization']) ?></span>
            </div>
            <div class="summary-item">
              <span class="summary-label">Patient:</span>
              <span class="summary-value"><?= htmlspecialchars($patient['Patient_FirstName'] . " " . $patient['Patient_LastName']) ?></span>
            </div>
            <div class="summary-item">
              <span class="summary-label">Consultation Reason:</span>
              <span class="summary-value" id="modalConsultationReason"></span>
            </div>
          </div>
        </div>
        <div class="modal-buttons">
          <button type="button" class="modal-button cancel">Cancel</button>
          <button type="submit" class="modal-button confirm">Confirm Booking</button>
        </div>
      </div>
    </div>
  </form>
</div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {

  const modal = document.getElementById("confirmationModal");
  const reasonInput = document.getElementById("consultationReason");
  const modalReason = document.getElementById("modalConsultationReason");

  // Book Now button
  document.getElementById("bookNowButton").onclick = () => {
    if (!reasonInput.value.trim()) {
      alert("Please enter consultation reason.");
      return;
    }
    modalReason.textContent = reasonInput.value;
    modal.style.display = "block";
  };

  // Close modal
  document.querySelector(".close-button").onclick =
  document.querySelector(".modal-button.cancel").onclick = () => {
    modal.style.display = "none";
  };

  // Back button
  document.getElementById("backButton").onclick = () => {
    window.history.back();
  };

  // Navigation
  const navButtons = document.querySelectorAll('.nav-btn');
  navButtons.forEach(button => {
    button.addEventListener('click', function () {
      const page = this.getAttribute('data-page');
      switch (page) {
        case 'home': window.location.href = "Home.php"; break;
        case 'services': window.location.href = "Services.php"; break;
        case 'status': window.location.href = "Status.php"; break;
        case 'appointments': window.location.href = "Appointments.php"; break;
        case 'account': window.location.href = "Account.php"; break;
        case 'logout':
          if(confirm("Are you sure you want to log out?")) window.location.href = "Login.php";
          break;
      }
    });
  });

});
</script>

</body>
</html>

<?php $conn->close(); ?>