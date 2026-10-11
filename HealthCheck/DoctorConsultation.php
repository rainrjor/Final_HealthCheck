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
   HANDLE APPROVE/DECLINE ACTIONS
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['booking_id'])) {
        $bookingId = intval($_POST['booking_id']);
        $action = $_POST['action'];

        if ($action === 'approve') {
            // Get doctor's Gmeet link
            $stmtDoctor = $conn->prepare("SELECT Gmeet_Link FROM Doctor WHERE Doctor_ID = ?");
            $stmtDoctor->bind_param("i", $doctorId);
            $stmtDoctor->execute();
            $doctorResult = $stmtDoctor->get_result();
            $doctorData = $doctorResult->fetch_assoc();
            $gmeetLink = $doctorData['Gmeet_Link'];
            $stmtDoctor->close();
            
            // Get consultation ID from booking
            $stmtConsult = $conn->prepare("SELECT Consultation_ID FROM Booking WHERE Booking_ID = ?");
            $stmtConsult->bind_param("i", $bookingId);
            $stmtConsult->execute();
            $consultResult = $stmtConsult->get_result();
            $consultData = $consultResult->fetch_assoc();
            $consultationId = $consultData['Consultation_ID'];
            $stmtConsult->close();
            
            // Update Booking status
            $stmt = $conn->prepare("UPDATE Booking SET Booking_Status = 'Approved' WHERE Booking_ID = ?");
            $stmt->bind_param("i", $bookingId);
            $stmt->execute();
            $stmt->close();
            
            // Update Consultation with doctor's Gmeet link
            $stmtUpdate = $conn->prepare("UPDATE Consultation SET Gmeet_Link = ? WHERE Consultation_ID = ?");
            $stmtUpdate->bind_param("si", $gmeetLink, $consultationId);
            $stmtUpdate->execute();
            $stmtUpdate->close();
            
        } elseif ($action === 'decline') {
            $stmt = $conn->prepare("UPDATE Booking SET Booking_Status = 'Cancelled' WHERE Booking_ID = ?");
            $stmt->bind_param("i", $bookingId);
            $stmt->execute();
        } elseif ($action === 'schedule') {
            $appointmentDate = $_POST['appointment_date'];
            $appointmentTime = $_POST['appointment_time'];
            
            $stmt = $conn->prepare("
                UPDATE Booking 
                SET Appointment_Date = ?, Appointment_Time = ?
                WHERE Booking_ID = ?
            ");
            $stmt->bind_param("ssi", $appointmentDate, $appointmentTime, $bookingId);
            $stmt->execute();
        }

        header("Location: DoctorConsultation.php");
        exit();
    }
}

/* ===============================
   FETCH ALL CONSULTATIONS
================================ */
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

$sql = "
    SELECT 
        b.Booking_ID,
        b.Booking_Status,
        b.Appointment_Date,
        b.Appointment_Time,
        c.Consultation_ID,
        c.Consultation_Reason,
        c.Gmeet_Link,
        p.Patient_ID,
        p.Patient_FirstName,
        p.Patient_LastName,
        p.Patient_Sex,
        p.Patient_Email,
        p.Patient_PhoneNumber,
        p.Patient_Medical_History,
        YEAR(CURDATE()) - YEAR(STR_TO_DATE(SUBSTRING_INDEX(p.Patient_Medical_History, 'Age:', -1), 'Age: %d')) as Age
    FROM Booking b
    INNER JOIN Consultation c ON b.Consultation_ID = c.Consultation_ID
    INNER JOIN Patient p ON c.Patient_ID = p.Patient_ID
    WHERE c.Doctor_ID = ?
";

if ($filter === 'pending') {
    $sql .= " AND b.Booking_Status = 'Pending'";
} elseif ($filter === 'approved') {
    $sql .= " AND b.Booking_Status = 'Approved'";
} elseif ($filter === 'cancelled') {
    $sql .= " AND b.Booking_Status = 'Cancelled'";
}

$sql .= " ORDER BY b.Booking_ID DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $doctorId);
$stmt->execute();
$consultations = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HealthCheck Dashboard - Consultations</title>
  <link rel="stylesheet" href="DoctorConsultationStyle.css">
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
        <button class="nav-btn" data-page="home">
          <span class="button-icon">🏠</span> Home
        </button>
        <button class="nav-btn active" data-page="consultations">
          <span class="button-icon">🩺</span> Consultations
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

    <div class="ConsultationManagement">
      <div class="ConsultationHeader">
        <h2>My Consultations</h2>
        <div class="StatusFilter">
          <a href="?filter=all" class="<?= $filter === 'all' ? 'active' : '' ?>">All</a>
          <a href="?filter=pending" class="<?= $filter === 'pending' ? 'active' : '' ?>">Pending</a>
          <a href="?filter=approved" class="<?= $filter === 'approved' ? 'active' : '' ?>">Approved</a>
          <a href="?filter=cancelled" class="<?= $filter === 'cancelled' ? 'active' : '' ?>">Cancelled</a>
        </div>
      </div>

      <table class="ConsultationTable">
        <thead>
          <tr>
            <th>Booking ID</th>
            <th>Patient's Name</th>
            <th>Sex</th>
            <th>Contact</th>
            <th>Reason</th>
            <th>Status</th>
            <th>Scheduled Time</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($consultations->num_rows > 0): ?>
            <?php while ($row = $consultations->fetch_assoc()): 
              $fullName = $row['Patient_FirstName'] . " " . $row['Patient_LastName'];
              $status = strtolower($row['Booking_Status']);
              $statusClass = $status === 'approved' ? 'approved' : ($status === 'cancelled' ? 'declined' : 'pending');
              
              $scheduledTime = '-';
              if ($row['Appointment_Date'] && $row['Appointment_Time']) {
                $date = date('D, d M', strtotime($row['Appointment_Date']));
                $time = date('g:i A', strtotime($row['Appointment_Time']));
                $scheduledTime = $date . ', ' . $time;
              }
            ?>
            <tr data-booking-id="<?= $row['Booking_ID'] ?>"
                data-patient-name="<?= htmlspecialchars($fullName) ?>"
                data-patient-sex="<?= htmlspecialchars($row['Patient_Sex']) ?>"
                data-patient-email="<?= htmlspecialchars($row['Patient_Email']) ?>"
                data-patient-phone="<?= htmlspecialchars($row['Patient_PhoneNumber']) ?>"
                data-medical-history="<?= htmlspecialchars($row['Patient_Medical_History']) ?>"
                data-reason="<?= htmlspecialchars($row['Consultation_Reason']) ?>"
                data-appointment-date="<?= $row['Appointment_Date'] ?>"
                data-appointment-time="<?= $row['Appointment_Time'] ?>">
              <td><?= $row['Booking_ID'] ?></td>
              <td><?= htmlspecialchars($fullName) ?></td>
              <td><?= htmlspecialchars($row['Patient_Sex'][0]) ?></td>
              <td><?= htmlspecialchars($row['Patient_PhoneNumber']) ?></td>
              <td><?= htmlspecialchars(substr($row['Consultation_Reason'], 0, 30)) ?>...</td>
              <td><span class="StatusBadge <?= $statusClass ?>"><?= strtoupper($row['Booking_Status']) ?></span></td>
              <td><?= $scheduledTime ?></td>
              <td class="ActionButtons">
                <button class="ViewButton" onclick="viewPatient(this)">View</button>
                <?php if ($status === 'pending'): ?>
                  <button class="ApproveButton" onclick="approveBooking(<?= $row['Booking_ID'] ?>)">Approve</button>
                  <button class="DeclineButton" onclick="declineBooking(<?= $row['Booking_ID'] ?>)">Decline</button>
                <?php elseif ($status === 'approved'): ?>
                  <button class="ScheduleButton" onclick="scheduleConsultation(this)">Schedule</button>
                <?php elseif ($status === 'cancelled'): ?>
                  <button class="ApproveButton" onclick="approveBooking(<?= $row['Booking_ID'] ?>)">Approve</button>
                <?php endif; ?>
              </td>
            </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="8" style="text-align: center; padding: 40px;">No consultations found.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- PATIENT DETAILS MODAL -->
  <div class="Modal" id="patientModal">
    <div class="ModalContent">
      <div class="ModalHeader">
        <h3>Patient Details</h3>
        <button class="CloseButton" onclick="closeModal('patientModal')">&times;</button>
      </div>

      <div class="PatientInfo">
        <div class="InfoItem">
          <label>Booking ID</label>
          <span id="modalBookingId">-</span>
        </div>
        <div class="InfoItem">
          <label>Patient Name</label>
          <span id="modalPatientName">-</span>
        </div>
        <div class="InfoItem">
          <label>Sex</label>
          <span id="modalSex">-</span>
        </div>
        <div class="InfoItem">
          <label>Contact</label>
          <span id="modalContact">-</span>
        </div>
        <div class="InfoItem">
          <label>Email</label>
          <span id="modalEmail">-</span>
        </div>
        <div class="InfoItem">
          <label>Appointment Date</label>
          <span id="modalDate">-</span>
        </div>
        <div class="InfoItem">
          <label>Appointment Time</label>
          <span id="modalTime">-</span>
        </div>
      </div>

      <div class="MedicalHistory">
        <h4>Medical History</h4>
        <textarea id="modalMedicalHistory" readonly></textarea>
      </div>

      <div class="MedicalHistory">
        <h4>Consultation Reason</h4>
        <textarea id="modalSymptoms" readonly></textarea>
      </div>

      <div class="ModalActions">
        <button class="CancelButton" onclick="closeModal('patientModal')">Close</button>
      </div>
    </div>
  </div>

  <!-- SCHEDULE CONSULTATION MODAL -->
  <div class="Modal ScheduleModal" id="scheduleModal">
    <div class="ModalContent">
      <div class="ModalHeader">
        <h3>Schedule Consultation</h3>
        <button class="CloseButton" onclick="closeModal('scheduleModal')">&times;</button>
      </div>

      <form method="POST" id="scheduleForm">
        <input type="hidden" name="action" value="schedule">
        <input type="hidden" name="booking_id" id="scheduleBookingId">

        <div class="ScheduleForm">
          <div class="FormGroup">
            <label for="schedulePatientName">Patient Name</label>
            <input type="text" id="schedulePatientName" readonly>
          </div>

          <div class="DateTimeGroup">
            <div class="FormGroup">
              <label for="consultationDate">Date</label>
              <input type="date" name="appointment_date" id="consultationDate" required>
            </div>

            <div class="FormGroup">
              <label for="consultationTime">Time</label>
              <input type="time" name="appointment_time" id="consultationTime" required>
            </div>
          </div>
        </div>

        <div class="ScheduleActions">
          <button type="button" class="CancelButton" onclick="closeModal('scheduleModal')">Cancel</button>
          <button type="submit" class="ConfirmButton">Confirm Schedule</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    // Navigation
    document.querySelectorAll('.nav-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const page = btn.dataset.page;
        
        const routes = {
          home: 'DoctorMain.php',
          consultations: 'DoctorConsultation.php',
          appointments: 'DoctorAppointment.php',
          account: 'DoctorProfile.php'
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

    // Set minimum date to today
    document.getElementById('consultationDate').min = new Date().toISOString().split('T')[0];

    function closeModal(modalId) {
      document.getElementById(modalId).style.display = 'none';
    }

    function viewPatient(btn) {
      const row = btn.closest('tr');
      
      document.getElementById('modalBookingId').textContent = row.dataset.bookingId;
      document.getElementById('modalPatientName').textContent = row.dataset.patientName;
      document.getElementById('modalSex').textContent = row.dataset.patientSex;
      document.getElementById('modalContact').textContent = row.dataset.patientPhone;
      document.getElementById('modalEmail').textContent = row.dataset.patientEmail;
      document.getElementById('modalDate').textContent = row.dataset.appointmentDate || '-';
      document.getElementById('modalTime').textContent = row.dataset.appointmentTime || '-';
      document.getElementById('modalMedicalHistory').value = row.dataset.medicalHistory || 'No medical history available';
      document.getElementById('modalSymptoms').value = row.dataset.reason || 'No reason provided';
      
      document.getElementById('patientModal').style.display = 'flex';
    }

    function scheduleConsultation(btn) {
      const row = btn.closest('tr');
      
      document.getElementById('scheduleBookingId').value = row.dataset.bookingId;
      document.getElementById('schedulePatientName').value = row.dataset.patientName;
      
      document.getElementById('scheduleModal').style.display = 'flex';
    }

    function approveBooking(bookingId) {
      if (confirm('Are you sure you want to approve this consultation?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
          <input type="hidden" name="action" value="approve">
          <input type="hidden" name="booking_id" value="${bookingId}">
        `;
        document.body.appendChild(form);
        form.submit();
      }
    }

    function declineBooking(bookingId) {
      if (confirm('Are you sure you want to decline this consultation?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
          <input type="hidden" name="action" value="decline">
          <input type="hidden" name="booking_id" value="${bookingId}">
        `;
        document.body.appendChild(form);
        form.submit();
      }
    }

    // Close modal when clicking outside
    window.addEventListener('click', (e) => {
      if (e.target.classList.contains('Modal')) {
        e.target.style.display = 'none';
      }
    });
  </script>

  <style>
    .StatusFilter a {
      padding: 8px 16px;
      border: none;
      background: #f0f0f0;
      cursor: pointer;
      border-radius: 5px;
      margin: 0 5px;
      text-decoration: none;
      color: #333;
      display: inline-block;
    }

    .StatusFilter a.active {
      background: #00aaff;
      color: white;
    }

    .StatusFilter a:hover {
      background: #0099ee;
      color: white;
    }
  </style>
</body>

</html>

<?php $conn->close(); ?>