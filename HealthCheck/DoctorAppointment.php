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
   HANDLE ACTIONS (Cancel, Reschedule, Complete)
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['booking_id'])) {
        $bookingId = intval($_POST['booking_id']);
        $action = $_POST['action'];

        // Verify this booking belongs to this doctor
        $verifyStmt = $conn->prepare("
            SELECT c.Doctor_ID 
            FROM Booking b
            INNER JOIN Consultation c ON b.Consultation_ID = c.Consultation_ID
            WHERE b.Booking_ID = ?
        ");
        $verifyStmt->bind_param("i", $bookingId);
        $verifyStmt->execute();
        $verifyResult = $verifyStmt->get_result();
        
        if ($verifyResult->num_rows > 0) {
            $row = $verifyResult->fetch_assoc();
            
            if ($row['Doctor_ID'] == $doctorId) {
                if ($action === 'cancel') {
                    // Cancel the appointment
                    $stmt = $conn->prepare("UPDATE Booking SET Booking_Status = 'Cancelled' WHERE Booking_ID = ?");
                    $stmt->bind_param("i", $bookingId);
                    $stmt->execute();
                    
                    // Also update consultation status
                    $stmt = $conn->prepare("
                        UPDATE Consultation c
                        INNER JOIN Booking b ON c.Consultation_ID = b.Consultation_ID
                        SET c.Consultation_Status = 'Cancelled'
                        WHERE b.Booking_ID = ?
                    ");
                    $stmt->bind_param("i", $bookingId);
                    $stmt->execute();
                    
                } elseif ($action === 'reschedule') {
                    $newDate = $_POST['appointment_date'];
                    $newTime = $_POST['appointment_time'];
                    
                    // Validate date is not in the past
                    if (strtotime($newDate) >= strtotime(date('Y-m-d'))) {
                        $stmt = $conn->prepare("
                            UPDATE Booking 
                            SET Appointment_Date = ?, 
                                Appointment_Time = ?,
                                Booking_Status = 'Approved'
                            WHERE Booking_ID = ?
                        ");
                        $stmt->bind_param("ssi", $newDate, $newTime, $bookingId);
                        $stmt->execute();
                        
                        // Reset consultation status if it was cancelled
                        $stmt = $conn->prepare("
                            UPDATE Consultation c
                            INNER JOIN Booking b ON c.Consultation_ID = b.Consultation_ID
                            SET c.Consultation_Status = 'Pending'
                            WHERE b.Booking_ID = ? AND c.Consultation_Status = 'Cancelled'
                        ");
                        $stmt->bind_param("i", $bookingId);
                        $stmt->execute();
                    }
                    
                } elseif ($action === 'complete') {
                    // Mark consultation as completed but keep booking status as Approved
                    $stmt = $conn->prepare("
                        UPDATE Consultation c
                        INNER JOIN Booking b ON c.Consultation_ID = b.Consultation_ID
                        SET c.Consultation_Status = 'Completed'
                        WHERE b.Booking_ID = ?
                    ");
                    $stmt->bind_param("i", $bookingId);
                    $stmt->execute();
                    
                    // Keep Booking_Status as 'Approved' - DO NOT change it
                }
            }
        }
        
        header("Location: DoctorAppointment.php" . (isset($_GET['filter']) ? "?filter=" . $_GET['filter'] : ""));
        exit();
    }
}

/* ===============================
   GET FILTER PARAMETER
================================ */
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

/* ===============================
   FETCH APPOINTMENTS WITH STATISTICS
================================ */
// Base query for appointments
$sql = "
    SELECT 
        b.Booking_ID,
        b.Appointment_Date,
        b.Appointment_Time,
        b.Booking_Status,
        p.Patient_FirstName,
        p.Patient_LastName,
        c.Consultation_ID,
        c.Gmeet_Link as Consultation_Gmeet,
        c.Consultation_Status,
        d.Gmeet_Link as Doctor_Gmeet
    FROM Booking b
    INNER JOIN Consultation c ON b.Consultation_ID = c.Consultation_ID
    INNER JOIN Patient p ON c.Patient_ID = p.Patient_ID
    INNER JOIN Doctor d ON c.Doctor_ID = d.Doctor_ID
    WHERE c.Doctor_ID = ? 
        AND b.Appointment_Date IS NOT NULL
        AND b.Appointment_Time IS NOT NULL
";

// Add filter conditions
if ($filter === 'upcoming') {
    $sql .= " AND b.Appointment_Date >= CURDATE() 
              AND b.Booking_Status = 'Approved'
              AND c.Consultation_Status != 'Completed' 
              AND c.Consultation_Status != 'Cancelled'";
} elseif ($filter === 'completed') {
    $sql .= " AND c.Consultation_Status = 'Completed'";
} elseif ($filter === 'cancelled') {
    $sql .= " AND (b.Booking_Status = 'Cancelled' OR c.Consultation_Status = 'Cancelled')";
} else {
    // For 'all' filter, exclude cancelled ones
    $sql .= " AND b.Booking_Status != 'Cancelled' AND c.Consultation_Status != 'Cancelled'";
}

$sql .= " ORDER BY b.Appointment_Date DESC, b.Appointment_Time DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $doctorId);
$stmt->execute();
$appointments = $stmt->get_result();

/* ===============================
   CALCULATE STATISTICS
================================ */
// Total appointments (approved only, not cancelled)
$totalStmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM Booking b
    INNER JOIN Consultation c ON b.Consultation_ID = c.Consultation_ID
    WHERE c.Doctor_ID = ? 
        AND b.Booking_Status = 'Approved'
        AND b.Appointment_Date IS NOT NULL
        AND c.Consultation_Status != 'Cancelled'
");
$totalStmt->bind_param("i", $doctorId);
$totalStmt->execute();
$totalCount = $totalStmt->get_result()->fetch_assoc()['total'];

// Upcoming appointments
$upcomingStmt = $conn->prepare("
    SELECT COUNT(*) as upcoming
    FROM Booking b
    INNER JOIN Consultation c ON b.Consultation_ID = c.Consultation_ID
    WHERE c.Doctor_ID = ? 
        AND b.Booking_Status = 'Approved'
        AND b.Appointment_Date >= CURDATE()
        AND c.Consultation_Status != 'Completed'
        AND c.Consultation_Status != 'Cancelled'
");
$upcomingStmt->bind_param("i", $doctorId);
$upcomingStmt->execute();
$upcomingCount = $upcomingStmt->get_result()->fetch_assoc()['upcoming'];

// Completed appointments
$completedStmt = $conn->prepare("
    SELECT COUNT(*) as completed
    FROM Booking b
    INNER JOIN Consultation c ON b.Consultation_ID = c.Consultation_ID
    WHERE c.Doctor_ID = ? 
        AND c.Consultation_Status = 'Completed'
");
$completedStmt->bind_param("i", $doctorId);
$completedStmt->execute();
$completedCount = $completedStmt->get_result()->fetch_assoc()['completed'];

// Cancelled appointments
$cancelledStmt = $conn->prepare("
    SELECT COUNT(*) as cancelled
    FROM Booking b
    INNER JOIN Consultation c ON b.Consultation_ID = c.Consultation_ID
    WHERE c.Doctor_ID = ? 
        AND (b.Booking_Status = 'Cancelled' OR c.Consultation_Status = 'Cancelled')
        AND b.Appointment_Date IS NOT NULL
");
$cancelledStmt->bind_param("i", $doctorId);
$cancelledStmt->execute();
$cancelledCount = $cancelledStmt->get_result()->fetch_assoc()['cancelled'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HealthCheck - Appointments</title>
  <link rel="stylesheet" href="DoctorAppointmentStyle.css">
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
        <button class="nav-btn" data-page="consultations"><span class="button-icon">🩺</span> Consultations</button>
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
      <h1>Welcome back,<br><?= htmlspecialchars($userName) ?></h1>
    </div>

    <div class="ConsultationStatus">
      <div class="StatusHeader">
        <h2>My Appointments</h2>
        <div class="StatusFilter">
          <a href="?filter=all" class="<?= $filter === 'all' ? 'active' : '' ?>">All</a>
          <a href="?filter=upcoming" class="<?= $filter === 'upcoming' ? 'active' : '' ?>">Upcoming</a>
          <a href="?filter=completed" class="<?= $filter === 'completed' ? 'active' : '' ?>">Completed</a>
          <a href="?filter=cancelled" class="<?= $filter === 'cancelled' ? 'active' : '' ?>">Cancelled</a>
        </div>
      </div>

      <div class="StatsContainer">
        <div class="StatCard">
          <div class="StatNumber"><?= $totalCount ?></div>
          <div class="StatLabel">Total Appointments</div>
        </div>
        <div class="StatCard">
          <div class="StatNumber"><?= $upcomingCount ?></div>
          <div class="StatLabel">Upcoming</div>
        </div>
        <div class="StatCard">
          <div class="StatNumber"><?= $completedCount ?></div>
          <div class="StatLabel">Completed</div>
        </div>
        <div class="StatCard">
          <div class="StatNumber"><?= $cancelledCount ?></div>
          <div class="StatLabel">Cancelled</div>
        </div>
      </div>

      <table class="StatusTable">
        <thead>
          <tr>
            <th>Booking ID</th>
            <th>Patient's Name</th>
            <th>Date</th>
            <th>Time</th>
            <th>Booking Status</th>
            <th>Consultation Status</th>
            <th>Gmeet Link</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($appointments->num_rows > 0): ?>
            <?php while ($row = $appointments->fetch_assoc()): 
              $fullName = $row['Patient_FirstName'] . " " . $row['Patient_LastName'];
              
              // Determine status
              $appointmentDateTime = strtotime($row['Appointment_Date'] . ' ' . $row['Appointment_Time']);
              $currentDateTime = time();
              $consultationStatus = $row['Consultation_Status'];
              $bookingStatus = $row['Booking_Status'];
              
              if ($consultationStatus === 'Completed') {
                  $displayStatus = 'completed';
                  $statusText = 'Completed';
              } elseif ($consultationStatus === 'Cancelled' || $bookingStatus === 'Cancelled') {
                  $displayStatus = 'cancelled';
                  $statusText = 'Cancelled';
              } elseif ($appointmentDateTime > $currentDateTime) {
                  $displayStatus = 'upcoming';
                  $statusText = 'Upcoming';
              } else {
                  $displayStatus = 'ongoing';
                  $statusText = 'Ongoing';
              }
              
              // Format date and time
              $formattedDate = date('m/d/Y', strtotime($row['Appointment_Date']));
              $formattedTime = date('g:i A', strtotime($row['Appointment_Time']));
              
              // Get Google Meet link (prefer consultation-specific, fallback to doctor's)
              $gmeetLink = !empty($row['Consultation_Gmeet']) ? $row['Consultation_Gmeet'] : $row['Doctor_Gmeet'];
              $meetCode = basename(parse_url($gmeetLink, PHP_URL_PATH));
              if (empty($meetCode)) {
                  $meetCode = $gmeetLink; // If not a full URL, display as is
              }
              
              // Check if can join (within 30 minutes before appointment and up to 2 hours after)
              $timeDiff = $appointmentDateTime - $currentDateTime;
              $canJoin = ($displayStatus === 'upcoming' || $displayStatus === 'ongoing') && $timeDiff <= 1800 && $timeDiff >= -7200;
            ?>
            <tr data-booking-id="<?= $row['Booking_ID'] ?>"
                data-patient-name="<?= htmlspecialchars($fullName) ?>"
                data-appointment-date="<?= $row['Appointment_Date'] ?>"
                data-appointment-time="<?= $row['Appointment_Time'] ?>"
                data-gmeet-link="<?= htmlspecialchars($gmeetLink) ?>">
              <td><?= $row['Booking_ID'] ?></td>
              <td><?= htmlspecialchars($fullName) ?></td>
              <td><?= $formattedDate ?></td>
              <td><?= $formattedTime ?></td>
              <td><span class="StatusBadge approved"><?= strtoupper($bookingStatus) ?></span></td>
              <td><span class="StatusBadge <?= $displayStatus ?>"><?= strtoupper($statusText) ?></span></td>
              <td>
                <?php if (!empty($gmeetLink)): ?>
                  <a href="<?= htmlspecialchars($gmeetLink) ?>" target="_blank" class="GmeetLink">🔗 <?= htmlspecialchars($meetCode) ?></a>
                <?php else: ?>
                  <span class="GmeetLink">No link</span>
                <?php endif; ?>
              </td>
              <td class="ActionButtons">
                <?php if ($displayStatus === 'upcoming' || $displayStatus === 'ongoing'): ?>
                  <?php if ($canJoin): ?>
                    <button class="JoinButton" onclick="joinMeeting('<?= htmlspecialchars($gmeetLink) ?>')">Join</button>
                  <?php else: ?>
                    <button class="JoinButton" disabled style="opacity: 0.5; cursor: not-allowed;">Join</button>
                  <?php endif; ?>
                  <button class="CompleteButton" onclick="markAsComplete(<?= $row['Booking_ID'] ?>)">Mark Complete</button>
                  <button class="RescheduleButton" onclick="showRescheduleModal(this)">Reschedule</button>
                  <button class="CancelButton" onclick="cancelAppointment(<?= $row['Booking_ID'] ?>)">Cancel</button>
                <?php elseif ($displayStatus === 'completed'): ?>
                  <span style="color: #28a745; font-weight: bold;">✓ Consultation Completed</span>
                <?php elseif ($displayStatus === 'cancelled'): ?>
                  <button class="RescheduleButton" onclick="showRescheduleModal(this)">Reschedule</button>
                <?php endif; ?>
              </td>
            </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="8" style="text-align: center; padding: 40px;">No appointments found.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- RESCHEDULE MODAL -->
  <div class="Modal" id="rescheduleModal">
    <div class="ModalContent">
      <div class="ModalHeader">
        <h3>Reschedule Appointment</h3>
        <button class="CloseButton" onclick="closeModal('rescheduleModal')">&times;</button>
      </div>

      <form method="POST" id="rescheduleForm">
        <input type="hidden" name="action" value="reschedule">
        <input type="hidden" name="booking_id" id="rescheduleBookingId">

        <div class="RescheduleForm">
          <div class="FormGroup">
            <label for="reschedulePatientName">Patient Name</label>
            <input type="text" id="reschedulePatientName" readonly>
          </div>

          <div class="FormGroup">
            <label for="rescheduleDate">New Date</label>
            <input type="date" name="appointment_date" id="rescheduleDate" required>
          </div>

          <div class="FormGroup">
            <label for="rescheduleTime">New Time</label>
            <input type="time" name="appointment_time" id="rescheduleTime" required>
          </div>
        </div>

        <div class="ModalActions">
          <button type="button" class="CancelButton" onclick="closeModal('rescheduleModal')">Cancel</button>
          <button type="submit" class="ConfirmButton">Confirm Reschedule</button>
        </div>
      </form>
    </div>
  </div>

  <!-- CONFIRMATION MODAL -->
  <div class="Modal" id="confirmationModal">
    <div class="ModalContent">
      <div class="ModalHeader">
        <h3>Confirm Action</h3>
        <button class="CloseButton" onclick="closeModal('confirmationModal')">&times;</button>
      </div>

      <p id="modalMessage">Are you sure you want to proceed with this action?</p>

      <div class="ModalActions">
        <button class="CancelButton" onclick="closeModal('confirmationModal')">Cancel</button>
        <button class="ConfirmButton" id="confirmActionBtn">Confirm</button>
      </div>
    </div>
  </div>

  <script>
    // Set minimum date to today
    document.getElementById('rescheduleDate').min = new Date().toISOString().split('T')[0];

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

    function closeModal(modalId) {
      document.getElementById(modalId).style.display = 'none';
    }

    function joinMeeting(gmeetLink) {
      if (gmeetLink && gmeetLink !== '') {
        window.open(gmeetLink, '_blank');
      } else {
        alert('No Google Meet link available for this appointment.');
      }
    }

    function showRescheduleModal(btn) {
      const row = btn.closest('tr');
      
      document.getElementById('rescheduleBookingId').value = row.dataset.bookingId;
      document.getElementById('reschedulePatientName').value = row.dataset.patientName;
      document.getElementById('rescheduleDate').value = row.dataset.appointmentDate;
      document.getElementById('rescheduleTime').value = row.dataset.appointmentTime;
      
      document.getElementById('rescheduleModal').style.display = 'flex';
    }

    function cancelAppointment(bookingId) {
      document.getElementById('modalMessage').textContent = 'Are you sure you want to cancel this appointment? This action cannot be undone.';
      document.getElementById('confirmationModal').style.display = 'flex';
      
      document.getElementById('confirmActionBtn').onclick = function() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
          <input type="hidden" name="action" value="cancel">
          <input type="hidden" name="booking_id" value="${bookingId}">
        `;
        document.body.appendChild(form);
        form.submit();
      };
    }

    function markAsComplete(bookingId) {
      document.getElementById('modalMessage').textContent = 'Mark this consultation as completed?';
      document.getElementById('confirmationModal').style.display = 'flex';
      
      document.getElementById('confirmActionBtn').onclick = function() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
          <input type="hidden" name="action" value="complete">
          <input type="hidden" name="booking_id" value="${bookingId}">
        `;
        document.body.appendChild(form);
        form.submit();
      };
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

    .StatsContainer {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin: 20px 0;
    }

    .StatCard {
      background: white;
      padding: 20px;
      border-radius: 10px;
      text-align: center;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .StatNumber {
      font-size: 32px;
      font-weight: bold;
      color: #00aaff;
    }

    .StatLabel {
      font-size: 14px;
      color: #666;
      margin-top: 5px;
    }

    .RescheduleForm {
      padding: 20px 0;
    }

    .FormGroup {
      margin-bottom: 15px;
    }

    .FormGroup label {
      display: block;
      margin-bottom: 5px;
      font-weight: 500;
    }

    .FormGroup input {
      width: 100%;
      padding: 10px;
      border: 1px solid #ddd;
      border-radius: 5px;
    }

    .StatusBadge.ongoing {
      background: #ffc107;
      color: white;
      padding: 5px 10px;
      border-radius: 5px;
      font-weight: bold;
    }

    .StatusBadge.approved {
      background: #28a745;
      color: white;
      padding: 5px 10px;
      border-radius: 5px;
      font-weight: bold;
    }

    .CompleteButton {
      background: #28a745;
      color: white;
      padding: 8px 15px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-weight: bold;
      margin: 0 3px;
    }

    .CompleteButton:hover {
      background: #218838;
    }
  </style>
</body>

</html>

<?php $conn->close(); ?>