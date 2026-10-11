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

$message = '';
$messageType = '';

/* ===============================
   HANDLE FORM SUBMISSIONS
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Handle Profile Update
    if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
        $firstName = trim($_POST['firstName']);
        $lastName = trim($_POST['lastName']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $sex = $_POST['sex'];
        $height = !empty($_POST['height']) ? floatval($_POST['height']) : null;
        $weight = !empty($_POST['weight']) ? floatval($_POST['weight']) : null;
        $medicalHistory = trim($_POST['medicalHistory']);
        
        $stmt = $conn->prepare("
            UPDATE Patient 
            SET Patient_FirstName = ?,
                Patient_LastName = ?,
                Patient_Email = ?,
                Patient_PhoneNumber = ?,
                Patient_Sex = ?,
                Patient_Height = ?,
                Patient_Weight = ?,
                Patient_Medical_History = ?
            WHERE Patient_ID = ?
        ");
        $stmt->bind_param("sssssddsi", $firstName, $lastName, $email, $phone, $sex, $height, $weight, $medicalHistory, $patientId);
        
        if ($stmt->execute()) {
            $message = 'Profile updated successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error updating profile: ' . $stmt->error;
            $messageType = 'error';
        }
        $stmt->close();
    }
    
    // Handle Password Change
    if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
        $currentPassword = $_POST['currentPassword'];
        $newPassword = $_POST['newPassword'];
        $confirmPassword = $_POST['confirmPassword'];
        
        // Verify current password
        $stmt = $conn->prepare("SELECT Patient_Password FROM Patient WHERE Patient_ID = ?");
        $stmt->bind_param("i", $patientId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user && $user['Patient_Password'] === $currentPassword) {
            if ($newPassword === $confirmPassword) {
                if (strlen($newPassword) >= 6) {
                    $stmt = $conn->prepare("UPDATE Patient SET Patient_Password = ? WHERE Patient_ID = ?");
                    $stmt->bind_param("si", $newPassword, $patientId);
                    
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

/* ===============================
   FETCH PATIENT DATA
================================ */
$stmt = $conn->prepare("
    SELECT 
        Patient_ID,
        Patient_FirstName,
        Patient_LastName,
        Patient_Email,
        Patient_PhoneNumber,
        Patient_Sex,
        Patient_Height,
        Patient_Weight,
        Patient_Medical_History
    FROM Patient
    WHERE Patient_ID = ?
");
$stmt->bind_param("i", $patientId);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

if (!$patient) {
    die("Patient not found.");
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HealthCheck Dashboard - My Account</title>
  <link rel="stylesheet" href="AccountStyle.css">
  <style>
    .message {
      padding: 15px;
      margin: 20px 0;
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
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0,0,0,0.5);
    }
    .modal-content {
      background-color: white;
      margin: 10% auto;
      padding: 30px;
      border-radius: 10px;
      width: 90%;
      max-width: 500px;
    }
    .modal-content h3 {
      margin-top: 0;
    }
    .modal-content .FormGroup {
      margin-bottom: 15px;
    }
    .close-modal {
      float: right;
      font-size: 28px;
      font-weight: bold;
      cursor: pointer;
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
        <button class="nav-btn" data-page="services"><span class="button-icon">🩺</span> Services</button>
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
      <h1>Welcome back,<br><?= htmlspecialchars($patient['Patient_FirstName'] . " " . $patient['Patient_LastName']) ?></h1>
    </div>

    <div class="AccountContent">
      <div class="ContentHeader">
        <h2>My Account</h2>
      </div>

      <?php if ($message): ?>
        <div class="message <?= $messageType ?>">
          <?= htmlspecialchars($message) ?>
        </div>
      <?php endif; ?>

      <form method="POST" class="AccountForm" id="profileForm">
        <input type="hidden" name="action" value="update_profile">
        
        <!-- Personal Information Section -->
        <div class="FormSection">
          <h3>Personal Information</h3>

          <div class="FormRow">
            <div class="FormGroup">
              <label for="firstName">First Name</label>
              <input type="text" id="firstName" name="firstName" value="<?= htmlspecialchars($patient['Patient_FirstName']) ?>" required>
            </div>

            <div class="FormGroup">
              <label for="lastName">Last Name</label>
              <input type="text" id="lastName" name="lastName" value="<?= htmlspecialchars($patient['Patient_LastName']) ?>" required>
            </div>
          </div>

          <div class="FormRow">
            <div class="FormGroup">
              <label for="email">Email Address</label>
              <input type="email" id="email" name="email" value="<?= htmlspecialchars($patient['Patient_Email']) ?>" required>
            </div>

            <div class="FormGroup">
              <label for="phone">Phone Number</label>
              <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($patient['Patient_PhoneNumber']) ?>" required>
            </div>

            <div class="FormGroup">
              <label for="sex">Sex</label>
              <select id="sex" name="sex" required>
                <option value="Male" <?= $patient['Patient_Sex'] === 'Male' ? 'selected' : '' ?>>Male</option>
                <option value="Female" <?= $patient['Patient_Sex'] === 'Female' ? 'selected' : '' ?>>Female</option>
                <option value="Other" <?= $patient['Patient_Sex'] === 'Other' ? 'selected' : '' ?>>Other</option>
              </select>
            </div>

            <div class="FormGroup">
              <label for="height">Height (cm)</label>
              <input type="number" step="0.01" id="height" name="height" value="<?= $patient['Patient_Height'] ?>" placeholder="Enter your height">
            </div>

            <div class="FormGroup">
              <label for="weight">Weight (kg)</label>
              <input type="number" step="0.01" id="weight" name="weight" value="<?= $patient['Patient_Weight'] ?>" placeholder="Enter your weight">
            </div>
          </div>
        </div>

        <!-- Account Information Section -->
        <div class="FormSection">
          <h3>Account Information</h3>

          <div class="FormGroup">
            <label for="patientId">Patient ID</label>
            <input type="text" id="patientId" value="PAT-<?= str_pad($patient['Patient_ID'], 6, '0', STR_PAD_LEFT) ?>" class="ReadOnlyField" readonly>
          </div>

          <div class="FormGroup">
            <label for="password">Password</label>
            <input type="password" id="password" value="••••••••" readonly>
          </div>
          <div class="ActionButtons">
            <button type="button" class="SaveButton" id="changePasswordBtn">Change Password</button>
          </div>
        </div>

        <!-- Medical Information Section -->
        <div class="FormSection">
          <h3>Medical Information</h3>

          <div class="FormGroup">
            <label for="medicalHistory">Medical History <span class="OptionalLabel">Optional</span></label>
            <textarea id="medicalHistory" name="medicalHistory" placeholder="Provide your medical history"><?= htmlspecialchars($patient['Patient_Medical_History']) ?></textarea>
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

  <!-- Password Change Modal -->
  <div id="passwordModal" class="modal">
    <div class="modal-content">
      <span class="close-modal" id="closeModal">&times;</span>
      <h3>Change Password</h3>
      <form method="POST" id="passwordForm">
        <input type="hidden" name="action" value="change_password">
        
        <div class="FormGroup">
          <label for="currentPassword">Current Password</label>
          <input type="password" id="currentPassword" name="currentPassword" required>
        </div>

        <div class="FormGroup">
          <label for="newPassword">New Password</label>
          <input type="password" id="newPassword" name="newPassword" required minlength="6">
        </div>

        <div class="FormGroup">
          <label for="confirmPassword">Confirm New Password</label>
          <input type="password" id="confirmPassword" name="confirmPassword" required minlength="6">
        </div>

        <div class="ActionButtons">
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
            case 'home': window.location.href = "Home.php"; break;
            case 'services': window.location.href = "Services.php"; break;
            case 'appointments': window.location.href = "Appointments.php"; break;
            case 'account': break;
            case 'logout':
              if (confirm('Are you sure you want to log out?')) {
                window.location.href = "Login.php";
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