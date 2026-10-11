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
   HANDLE FORM SUBMISSIONS
================================ */
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'update_profile') {
            // Get form data
            $firstName = trim($_POST['first_name']);
            $lastName = trim($_POST['last_name']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone']);
            $licenseNumber = trim($_POST['license_number']);
            $yearsExperience = intval($_POST['years_experience']);
            $specializationId = intval($_POST['specialization_id']);
            $description = trim($_POST['description']);
            $startTime = $_POST['start_time'];
            $endTime = $_POST['end_time'];
            $gmeetLink = trim($_POST['gmeet_link']);
            
            // Get selected days
            $daysOfWeek = isset($_POST['days']) ? $_POST['days'] : [];
            $dayString = implode(', ', array_map('ucfirst', $daysOfWeek));
            
            // Update Doctor table
            $stmt = $conn->prepare("
                UPDATE Doctor 
                SET Doctor_FirstName = ?,
                    Doctor_LastName = ?,
                    Doctor_Email = ?,
                    Doctor_MedicalLicenseNumber = ?,
                    Doctor_YearsExperience = ?,
                    Specialization_ID = ?,
                    Doctor_Description = ?,
                    Start_Time = ?,
                    End_Time = ?,
                    Day_of_Week = ?,
                    Gmeet_Link = ?
                WHERE Doctor_ID = ?
            ");
            
            $stmt->bind_param("sssiissssssi", 
                $firstName, $lastName, $email, $licenseNumber, 
                $yearsExperience, $specializationId, $description,
                $startTime, $endTime, $dayString, $gmeetLink, $doctorId
            );
            
            if ($stmt->execute()) {
                // Update session name if changed
                $_SESSION['user_name'] = "Dr. " . $firstName . " " . $lastName;
                $userName = $_SESSION['user_name'];
                $message = 'Profile updated successfully!';
                $messageType = 'success';
            } else {
                $message = 'Error updating profile: ' . $stmt->error;
                $messageType = 'error';
            }
            $stmt->close();
        } 
        
        elseif ($action === 'change_password') {
            $currentPassword = $_POST['current_password'];
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];
            
            // Verify current password
            $stmt = $conn->prepare("SELECT Doctor_Password FROM Doctor WHERE Doctor_ID = ?");
            $stmt->bind_param("i", $doctorId);
            $stmt->execute();
            $result = $stmt->get_result();
            $doctor = $result->fetch_assoc();
            
            if ($doctor && $doctor['Doctor_Password'] === $currentPassword) {
                if ($newPassword === $confirmPassword) {
                    if (strlen($newPassword) >= 6) {
                        $stmt = $conn->prepare("UPDATE Doctor SET Doctor_Password = ? WHERE Doctor_ID = ?");
                        $stmt->bind_param("si", $newPassword, $doctorId);
                        
                        if ($stmt->execute()) {
                            $message = 'Password changed successfully!';
                            $messageType = 'success';
                        } else {
                            $message = 'Error changing password.';
                            $messageType = 'error';
                        }
                    } else {
                        $message = 'New password must be at least 6 characters.';
                        $messageType = 'error';
                    }
                } else {
                    $message = 'New passwords do not match.';
                    $messageType = 'error';
                }
            } else {
                $message = 'Current password is incorrect.';
                $messageType = 'error';
            }
            $stmt->close();
        }
    }
}

/* ===============================
   FETCH DOCTOR DATA
================================ */
$stmt = $conn->prepare("
    SELECT 
        d.Doctor_ID,
        d.Doctor_FirstName,
        d.Doctor_LastName,
        d.Doctor_Email,
        d.Doctor_MedicalLicenseNumber,
        d.Doctor_YearsExperience,
        d.Specialization_ID,
        d.Doctor_Description,
        d.Start_Time,
        d.End_Time,
        d.Day_of_Week,
        d.Gmeet_Link,
        s.Services_Type
    FROM Doctor d
    LEFT JOIN Specialization s ON d.Specialization_ID = s.Specialization_ID
    WHERE d.Doctor_ID = ?
");
$stmt->bind_param("i", $doctorId);
$stmt->execute();
$doctorData = $stmt->get_result()->fetch_assoc();

if (!$doctorData) {
    die("Doctor not found.");
}

// Parse days of week
$selectedDays = [];
if (!empty($doctorData['Day_of_Week'])) {
    $days = explode(',', $doctorData['Day_of_Week']);
    foreach ($days as $day) {
        $selectedDays[] = strtolower(trim($day));
    }
}

// Get all specializations for dropdown
$specializationsQuery = "SELECT Specialization_ID, Services_Type FROM Specialization ORDER BY Services_Type";
$specializations = $conn->query($specializationsQuery);

// Get doctor's initials for avatar
$initials = strtoupper(substr($doctorData['Doctor_FirstName'], 0, 1) . substr($doctorData['Doctor_LastName'], 0, 1));

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HealthCheck Dashboard - Doctor Account</title>
  <link rel="stylesheet" href="DoctorProfileStyle.css">
  <style>
    .message {
      padding: 15px;
      margin: 20px 40px;
      border-radius: 5px;
      font-weight: bold;
    }
    .message.success {
      background-color: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }
    .message.error {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
    .Modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0,0,0,0.5);
    }
    .ModalContent {
      background-color: white;
      margin: 10% auto;
      padding: 30px;
      border-radius: 10px;
      width: 90%;
      max-width: 500px;
    }
    .ModalHeader {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }
    .ModalHeader h3 {
      margin: 0;
    }
    .CloseButton {
      background: none;
      border: none;
      font-size: 28px;
      font-weight: bold;
      cursor: pointer;
      color: #999;
    }
    .CloseButton:hover {
      color: #333;
    }
    .PasswordForm {
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
    .FormGroup input,
    .FormGroup select,
    .FormGroup textarea {
      width: 100%;
      padding: 10px;
      border: 1px solid #ddd;
      border-radius: 5px;
      font-family: inherit;
    }
    .ReadOnlyField {
      background: #f5f5f5;
      cursor: not-allowed;
    }
    .TimeRange {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .TimeRange input {
      flex: 1;
    }
    .DaysSelector {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
      gap: 10px;
    }
    .DayCheckbox {
      display: flex;
      align-items: center;
      gap: 5px;
      cursor: pointer;
    }
    .FormRow {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }
    .ModalActions {
      display: flex;
      gap: 10px;
      justify-content: flex-end;
      margin-top: 20px;
    }
    @media (max-width: 768px) {
      .FormRow {
        grid-template-columns: 1fr;
      }
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
        <button class="nav-btn" data-page="home"><span class="button-icon">🏠</span> Home</button>
        <button class="nav-btn" data-page="consultations"><span class="button-icon">🩺</span> Consultations</button>
        <button class="nav-btn" data-page="appointments"><span class="button-icon">📅</span> Appointments</button>
        <button class="nav-btn active" data-page="account" style="background-color: #00aaff;"><span class="button-icon">👤</span> Account</button>
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

    <div class="AccountContent">
      <div class="ContentHeader">
        <h2>Doctor Account</h2>
      </div>

      <?php if ($message): ?>
        <div class="message <?= $messageType ?>">
          <?= htmlspecialchars($message) ?>
        </div>
      <?php endif; ?>

      <!-- Doctor Profile Header -->
      <div class="DoctorProfileHeader">
        <div class="DoctorAvatar"><?= $initials ?></div>
        <div class="DoctorInfo">
          <h3><?= htmlspecialchars($userName) ?></h3>
          <p><?= htmlspecialchars($doctorData['Services_Type']) ?></p>
          <span class="DoctorBadge">Verified Doctor</span>
        </div>
      </div>

      <form method="POST" class="AccountForm" id="profileForm">
        <input type="hidden" name="action" value="update_profile">
        
        <!-- Personal Information Section -->
        <div class="FormSection">
          <h3>Personal Information</h3>

          <div class="FormRow">
            <div class="FormGroup">
              <label for="firstName">First Name</label>
              <input type="text" id="firstName" name="first_name" value="<?= htmlspecialchars($doctorData['Doctor_FirstName']) ?>" required>
            </div>

            <div class="FormGroup">
              <label for="lastName">Last Name</label>
              <input type="text" id="lastName" name="last_name" value="<?= htmlspecialchars($doctorData['Doctor_LastName']) ?>" required>
            </div>
          </div>

          <div class="FormRow">
            <div class="FormGroup">
              <label for="email">Email Address</label>
              <input type="email" id="email" name="email" value="<?= htmlspecialchars($doctorData['Doctor_Email']) ?>" required>
            </div>

            <div class="FormGroup">
              <label for="phone">Phone Number</label>
              <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($doctorData['Doctor_Email']) ?>" placeholder="09XX XXX XXXX">
            </div>
          </div>

          <div class="FormRow">
            <div class="FormGroup">
              <label for="licenseNumber">Medical License Number</label>
              <input type="text" id="licenseNumber" name="license_number" value="<?= htmlspecialchars($doctorData['Doctor_MedicalLicenseNumber']) ?>" required>
            </div>

            <div class="FormGroup">
              <label for="yearsExperience">Years of Experience</label>
              <input type="number" id="yearsExperience" name="years_experience" value="<?= htmlspecialchars($doctorData['Doctor_YearsExperience']) ?>" min="0" required>
            </div>
          </div>
        </div>

        <!-- Professional Information Section -->
        <div class="FormSection">
          <h3>Professional Information</h3>

          <div class="FormGroup">
            <label for="specialization">Primary Specialization</label>
            <select id="specialization" name="specialization_id" required>
              <?php while ($spec = $specializations->fetch_assoc()): ?>
                <option value="<?= $spec['Specialization_ID'] ?>" <?= $spec['Specialization_ID'] == $doctorData['Specialization_ID'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($spec['Services_Type']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>

          <div class="FormGroup">
            <label for="description">Professional Description</label>
            <textarea id="description" name="description" rows="5" placeholder="Describe your medical expertise, approach to patient care, and any special qualifications"><?= htmlspecialchars($doctorData['Doctor_Description']) ?></textarea>
          </div>
        </div>

        <!-- Availability Section -->
        <div class="FormSection">
          <h3>Availability & Schedule</h3>

          <div class="FormGroup">
            <label>Available Days</label>
            <div class="DaysSelector">
              <label class="DayCheckbox">
                <input type="checkbox" name="days[]" value="monday" <?= in_array('monday', $selectedDays) ? 'checked' : '' ?>> Monday
              </label>
              <label class="DayCheckbox">
                <input type="checkbox" name="days[]" value="tuesday" <?= in_array('tuesday', $selectedDays) ? 'checked' : '' ?>> Tuesday
              </label>
              <label class="DayCheckbox">
                <input type="checkbox" name="days[]" value="wednesday" <?= in_array('wednesday', $selectedDays) ? 'checked' : '' ?>> Wednesday
              </label>
              <label class="DayCheckbox">
                <input type="checkbox" name="days[]" value="thursday" <?= in_array('thursday', $selectedDays) ? 'checked' : '' ?>> Thursday
              </label>
              <label class="DayCheckbox">
                <input type="checkbox" name="days[]" value="friday" <?= in_array('friday', $selectedDays) ? 'checked' : '' ?>> Friday
              </label>
              <label class="DayCheckbox">
                <input type="checkbox" name="days[]" value="saturday" <?= in_array('saturday', $selectedDays) ? 'checked' : '' ?>> Saturday
              </label>
              <label class="DayCheckbox">
                <input type="checkbox" name="days[]" value="sunday" <?= in_array('sunday', $selectedDays) ? 'checked' : '' ?>> Sunday
              </label>
            </div>
          </div>

          <div class="FormGroup">
            <label>Consultation Hours</label>
            <div class="TimeRange">
              <input type="time" id="startTime" name="start_time" value="<?= htmlspecialchars($doctorData['Start_Time']) ?>" required>
              <span>to</span>
              <input type="time" id="endTime" name="end_time" value="<?= htmlspecialchars($doctorData['End_Time']) ?>" required>
            </div>
          </div>

          <div class="FormRow">
            <div class="FormGroup">
              <label for="googlelink">Google Meet Link</label>
              <input type="text" id="googlelink" name="gmeet_link" value="<?= htmlspecialchars($doctorData['Gmeet_Link']) ?>" placeholder="https://meet.google.com/xxx-xxxx-xxx">
            </div>
          </div>
        </div>

        <!-- Account Security Section -->
        <div class="FormSection">
          <h3>Account Security</h3>

          <div class="FormGroup">
            <label for="doctorId">Doctor ID</label>
            <input type="text" id="doctorId" value="DOC-<?= str_pad($doctorData['Doctor_ID'], 6, '0', STR_PAD_LEFT) ?>" class="ReadOnlyField" readonly>
          </div>

          <div class="FormGroup">
            <label for="password">Password</label>
            <input type="password" id="password" value="••••••••" class="ReadOnlyField" readonly>
          </div>

          <div class="ActionButtons">
            <button type="button" class="SaveButton" id="changePasswordBtn">Change Password</button>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="ActionButtons">
          <button type="submit" class="SaveButton">Save Changes</button>
          <button type="button" class="CancelButton" id="cancelBtn">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <!-- PASSWORD CHANGE MODAL -->
  <div id="passwordModal" class="Modal">
    <div class="ModalContent">
      <div class="ModalHeader">
        <h3>Change Password</h3>
        <span class="CloseButton" id="closeModal">&times;</span>
      </div>

      <form method="POST" id="passwordForm">
        <input type="hidden" name="action" value="change_password">
        
        <div class="PasswordForm">
          <div class="FormGroup">
            <label for="currentPassword">Current Password</label>
            <input type="password" id="currentPassword" name="current_password" required>
          </div>

          <div class="FormGroup">
            <label for="newPassword">New Password</label>
            <input type="password" id="newPassword" name="new_password" minlength="6" required>
          </div>

          <div class="FormGroup">
            <label for="confirmPassword">Confirm New Password</label>
            <input type="password" id="confirmPassword" name="confirm_password" minlength="6" required>
          </div>
        </div>

        <div class="ModalActions">
          <button type="submit" class="SaveButton">Update Password</button>
          <button type="button" class="CancelButton" id="cancelPasswordBtn">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    document.addEventListener("DOMContentLoaded", () => {
      // Navigation
      const navButtons = document.querySelectorAll('.nav-btn');
      navButtons.forEach(button => {
        button.addEventListener('click', function () {
          const page = this.getAttribute('data-page');
          switch (page) {
            case 'home': window.location.href = "DoctorMain.php"; break;
            case 'consultations': window.location.href = "DoctorConsultation.php"; break;
            case 'appointments': window.location.href = "DoctorAppointment.php"; break;
            case 'account': break;
            case 'logout':
              if (confirm('Are you sure you want to log out?')) {
                window.location.href = "login.php";
              }
              break;
          }
        });
      });

      // Cancel button
      document.getElementById('cancelBtn').addEventListener('click', function() {
        if (confirm('Are you sure you want to cancel? Any unsaved changes will be lost.')) {
          window.location.reload();
        }
      });

      // Password modal
      const modal = document.getElementById('passwordModal');
      const changePasswordBtn = document.getElementById('changePasswordBtn');
      const closeModal = document.getElementById('closeModal');
      const cancelPasswordBtn = document.getElementById('cancelPasswordBtn');

      changePasswordBtn.addEventListener('click', () => {
        modal.style.display = 'block';
      });

      closeModal.addEventListener('click', () => {
        modal.style.display = 'none';
      });

      cancelPasswordBtn.addEventListener('click', () => {
        modal.style.display = 'none';
      });

      window.addEventListener('click', (e) => {
        if (e.target === modal) {
          modal.style.display = 'none';
        }
      });

      // Form submission confirmation
      document.getElementById('profileForm').addEventListener('submit', function(e) {
        if (!confirm('Are you sure you want to save these changes?')) {
          e.preventDefault();
        }
      });

      // Password form validation
      document.getElementById('passwordForm').addEventListener('submit', function(e) {
        const newPass = document.getElementById('newPassword').value;
        const confirmPass = document.getElementById('confirmPassword').value;
        
        if (newPass !== confirmPass) {
          e.preventDefault();
          alert('New passwords do not match!');
        }
      });
    });
  </script>
</body>
</html>