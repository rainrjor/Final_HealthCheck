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

$message = '';
$messageType = '';

/* ===============================
   HANDLE FORM SUBMISSIONS
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Add/Edit Doctor
    if (isset($_POST['action']) && $_POST['action'] === 'save_doctor') {
        $doctorId = isset($_POST['doctor_id']) ? intval($_POST['doctor_id']) : null;
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $password = $_POST['password'];
        $license = trim($_POST['license']);
        $experience = intval($_POST['experience']);
        $specializationId = intval($_POST['specialization_id']);
        $description = trim($_POST['description']);
        $gmeetLink = trim($_POST['gmeet']);
        
        if ($doctorId) {
            // Update existing doctor
            $stmt = $conn->prepare("
                UPDATE Doctor 
                SET Doctor_FirstName = ?,
                    Doctor_LastName = ?,
                    Doctor_Email = ?,
                    Doctor_Password = ?,
                    Doctor_MedicalLicenseNumber = ?,
                    Doctor_YearsExperience = ?,
                    Specialization_ID = ?,
                    Doctor_Description = ?,
                    Gmeet_Link = ?
                WHERE Doctor_ID = ?
            ");
            $stmt->bind_param("ssssiisssi", $firstName, $lastName, $email, $password, $license, $experience, $specializationId, $description, $gmeetLink, $doctorId);
            
            if ($stmt->execute()) {
                $message = 'Doctor updated successfully!';
                $messageType = 'success';
            } else {
                $message = 'Error updating doctor.';
                $messageType = 'error';
            }
        } else {
            // Add new doctor
            $stmt = $conn->prepare("
                INSERT INTO Doctor (Doctor_FirstName, Doctor_LastName, Doctor_Email, Doctor_Password, Doctor_MedicalLicenseNumber, Doctor_YearsExperience, Specialization_ID, Doctor_Description, Gmeet_Link, Doctor_Status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')
            ");
            $stmt->bind_param("sssssiiss", $firstName, $lastName, $email, $password, $license, $experience, $specializationId, $description, $gmeetLink);
            
            if ($stmt->execute()) {
                $message = 'Doctor added successfully!';
                $messageType = 'success';
            } else {
                $message = 'Error adding doctor.';
                $messageType = 'error';
            }
        }
        $stmt->close();
    }
    
    // Deactivate Doctor (Soft Delete)
    if (isset($_POST['action']) && $_POST['action'] === 'deactivate_doctor') {
        $doctorId = intval($_POST['doctor_id']);
        
        $stmt = $conn->prepare("UPDATE Doctor SET Doctor_Status = 'Inactive' WHERE Doctor_ID = ?");
        $stmt->bind_param("i", $doctorId);
        
        if ($stmt->execute()) {
            $message = 'Doctor deactivated successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error deactivating doctor.';
            $messageType = 'error';
        }
        $stmt->close();
    }
    
    // Activate Doctor
    if (isset($_POST['action']) && $_POST['action'] === 'activate_doctor') {
        $doctorId = intval($_POST['doctor_id']);
        
        $stmt = $conn->prepare("UPDATE Doctor SET Doctor_Status = 'Active' WHERE Doctor_ID = ?");
        $stmt->bind_param("i", $doctorId);
        
        if ($stmt->execute()) {
            $message = 'Doctor activated successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error activating doctor.';
            $messageType = 'error';
        }
        $stmt->close();
    }
}

/* ===============================
   GET FILTER
================================ */
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'active';

/* ===============================
   FETCH DOCTORS BASED ON FILTER
================================ */
$whereClause = $filter === 'all' ? '' : ($filter === 'inactive' ? "WHERE d.Doctor_Status = 'Inactive'" : "WHERE d.Doctor_Status = 'Active'");

$doctors = $conn->query("
    SELECT 
        d.Doctor_ID,
        d.Doctor_FirstName,
        d.Doctor_LastName,
        d.Doctor_Email,
        d.Doctor_Password,
        d.Doctor_MedicalLicenseNumber,
        d.Doctor_YearsExperience,
        d.Doctor_Description,
        d.Specialization_ID,
        d.Doctor_Status,
        d.Gmeet_Link,
        s.Services_Type
    FROM Doctor d
    LEFT JOIN Specialization s ON d.Specialization_ID = s.Specialization_ID
    $whereClause
    ORDER BY d.Doctor_ID DESC
");

// Fetch specializations for dropdown
$specializations = $conn->query("SELECT Specialization_ID, Services_Type FROM Specialization ORDER BY Services_Type");

// Count statistics
$activeCount = $conn->query("SELECT COUNT(*) as count FROM Doctor WHERE Doctor_Status = 'Active'")->fetch_assoc()['count'];
$inactiveCount = $conn->query("SELECT COUNT(*) as count FROM Doctor WHERE Doctor_Status = 'Inactive'")->fetch_assoc()['count'];
$totalCount = $activeCount + $inactiveCount;

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HealthCheck Admin - Doctor Management</title>
  <link rel="stylesheet" href="AdminDoctorsStyle.css">
  
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
        <button class="nav-btn" data-page="overview"><span class="button-icon">📊</span> Overview</button>
        <button class="nav-btn" data-page="patients"><span class="button-icon">👥</span> Patients</button>
        <button class="nav-btn active" data-page="doctors"><span class="button-icon">👨‍⚕️</span> Doctors</button>
      </div>

      <div class="LowerButtons">
        <button class="nav-btn" data-page="logout"><span class="button-icon">🚪</span> Log Out</button>
      </div>
    </div>
  </div>

  <!-- RIGHT CONTENT -->
  <div class="RightContent">
    <div class="WelcomeBanner">
      <h1>Admin Dashboard<br>Doctor Management</h1>
    </div>

    <div class="ManagementContainer">
      <?php if ($message): ?>
        <div class="message <?= $messageType ?>">
          <?= htmlspecialchars($message) ?>
        </div>
      <?php endif; ?>

      <div class="StatsContainer">
        <div class="StatCard">
          <div class="StatNumber"><?= $totalCount ?></div>
          <div class="StatLabel">Total Doctors</div>
        </div>
        <div class="StatCard">
          <div class="StatNumber"><?= $activeCount ?></div>
          <div class="StatLabel">Active Doctors</div>
        </div>
        <div class="StatCard">
          <div class="StatNumber"><?= $inactiveCount ?></div>
          <div class="StatLabel">Inactive Doctors</div>
        </div>
      </div>

      <div class="ManagementHeader">
        <h2>Manage Doctors</h2>
        <div class="HeaderActions">
          <div class="StatusFilter">
            <a href="?filter=active" class="<?= $filter === 'active' ? 'active' : '' ?>">Active</a>
            <a href="?filter=inactive" class="<?= $filter === 'inactive' ? 'active' : '' ?>">Inactive</a>
            <a href="?filter=all" class="<?= $filter === 'all' ? 'active' : '' ?>">All</a>
          </div>
          <button class="AddButton" onclick="openAddModal()">+ Add Doctor</button>
        </div>
      </div>

      <div class="SearchBar">
        <input type="text" placeholder="Search doctors..." id="doctorSearch" onkeyup="searchTable()">
      </div>

      <table class="ManagementTable" id="doctorTable">
        <thead>
          <tr>
            <th>Doctor ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Specialization</th>
            <th>License No.</th>
            <th>Experience</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($doctor = $doctors->fetch_assoc()): ?>
            <tr>
              <td>DOC-<?= str_pad($doctor['Doctor_ID'], 6, '0', STR_PAD_LEFT) ?></td>
              <td>Dr. <?= htmlspecialchars($doctor['Doctor_FirstName'] . ' ' . $doctor['Doctor_LastName']) ?></td>
              <td><?= htmlspecialchars($doctor['Doctor_Email']) ?></td>
              <td><?= htmlspecialchars($doctor['Services_Type']) ?></td>
              <td><?= htmlspecialchars($doctor['Doctor_MedicalLicenseNumber']) ?></td>
              <td><?= $doctor['Doctor_YearsExperience'] ?> years</td>
              <td>
                <span class="StatusBadge <?= strtolower($doctor['Doctor_Status']) ?>">
                  <?= $doctor['Doctor_Status'] ?>
                </span>
              </td>
              <td class="ActionButtons">
                <button class="ViewButton" onclick='viewDoctor(<?= json_encode($doctor) ?>)'>View</button>
                <button class="EditButton" onclick='editDoctor(<?= json_encode($doctor) ?>)'>Edit</button>
                <?php if ($doctor['Doctor_Status'] === 'Active'): ?>
                  <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to deactivate this doctor?');">
                    <input type="hidden" name="action" value="deactivate_doctor">
                    <input type="hidden" name="doctor_id" value="<?= $doctor['Doctor_ID'] ?>">
                    <button type="submit" class="DeactivateButton">Deactivate</button>
                  </form>
                <?php else: ?>
                  <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to activate this doctor?');">
                    <input type="hidden" name="action" value="activate_doctor">
                    <input type="hidden" name="doctor_id" value="<?= $doctor['Doctor_ID'] ?>">
                    <button type="submit" class="ActivateButton">Activate</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ADD/EDIT DOCTOR MODAL -->
  <div id="doctorModal" class="Modal">
    <div class="ModalContent">
      <div class="ModalHeader">
        <h3 id="modalTitle">Add Doctor</h3>
        <button class="CloseButton" onclick="closeModal()">&times;</button>
      </div>

      <form method="POST" id="doctorForm">
        <input type="hidden" name="action" value="save_doctor">
        <input type="hidden" name="doctor_id" id="doctorId">
        
        <div class="FormRow">
          <div class="FormGroup">
            <label>First Name</label>
            <input type="text" name="first_name" id="firstName" required>
          </div>
          <div class="FormGroup">
            <label>Last Name</label>
            <input type="text" name="last_name" id="lastName" required>
          </div>
        </div>

        <div class="FormRow">
          <div class="FormGroup">
            <label>Email</label>
            <input type="email" name="email" id="email" required>
          </div>
          <div class="FormGroup">
            <label>Phone</label>
            <input type="tel" name="phone" id="phone" required>
          </div>
        </div>

        <div class="FormRow">
          <div class="FormGroup">
            <label>License Number</label>
            <input type="text" name="license" id="license" required>
          </div>
          <div class="FormGroup">
            <label>Years of Experience</label>
            <input type="number" name="experience" id="experience" required min="0">
          </div>
        </div>

        <div class="FormGroup">
          <label>Specialization</label>
          <select name="specialization_id" id="specialization" required>
            <option value="">Select Specialization</option>
            <?php 
            $specializations->data_seek(0);
            while ($spec = $specializations->fetch_assoc()): 
            ?>
              <option value="<?= $spec['Specialization_ID'] ?>"><?= htmlspecialchars($spec['Services_Type']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>

        <div class="FormGroup">
          <label>Professional Description</label>
          <textarea name="description" id="description" placeholder="Enter professional background and expertise..."></textarea>
        </div>

        <div class="FormGroup">
          <label>Google Meet Link</label>
          <input type="url" name="gmeet" id="gmeet" placeholder="https://meet.google.com/xxx-xxxx-xxx" required>
          <small style="color: #666; font-size: 12px;">Enter the full Google Meet URL (e.g., https://meet.google.com/abc-defg-hij)</small>
        </div>

        <div class="FormGroup">
          <label>Password</label>
          <input type="password" name="password" id="password" required minlength="6">
        </div>

        <div class="ModalActions">
          <button type="button" class="CancelButton" onclick="closeModal()">Cancel</button>
          <button type="submit" class="SaveButton">Save Doctor</button>
        </div>
      </form>
    </div>
  </div>

  <!-- VIEW DOCTOR MODAL -->
  <div id="viewModal" class="Modal">
    <div class="ModalContent">
      <div class="ModalHeader">
        <h3>Doctor Details</h3>
        <button class="CloseButton" onclick="closeViewModal()">&times;</button>
      </div>

      <div class="DoctorInfo">
        <div class="InfoItem">
          <label>Doctor ID</label>
          <span id="viewDoctorId">-</span>
        </div>
        <div class="InfoItem">
          <label>Name</label>
          <span id="viewName">-</span>
        </div>
        <div class="InfoItem">
          <label>Email</label>
          <span id="viewEmail">-</span>
        </div>
        <div class="InfoItem">
          <label>Specialization</label>
          <span id="viewSpecialization">-</span>
        </div>
        <div class="InfoItem">
          <label>License Number</label>
          <span id="viewLicense">-</span>
        </div>
        <div class="InfoItem">
          <label>Experience</label>
          <span id="viewExperience">-</span>
        </div>
        <div class="InfoItem">
          <label>Status</label>
          <span id="viewStatus">-</span>
        </div>
        <div class="InfoItem">
          <label>Google Meet Link</label>
          <a id="viewGmeet" href="#" target="_blank" style="color: #0066ff; text-decoration: none;">-</a>
        </div>
      </div>

      <div class="FormGroup">
        <label>Professional Description</label>
        <textarea id="viewDescription" readonly style="background-color: #f9f9f9;"></textarea>
      </div>

      <div class="ModalActions">
        <button class="CancelButton" onclick="closeViewModal()">Close</button>
      </div>
    </div>
  </div>

  <script>
    // Navigation
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

    // Search
    function searchTable() {
      const input = document.getElementById('doctorSearch');
      const filter = input.value.toUpperCase();
      const table = document.getElementById('doctorTable');
      const tr = table.getElementsByTagName('tr');

      for (let i = 1; i < tr.length; i++) {
        let found = false;
        const td = tr[i].getElementsByTagName('td');
        for (let j = 0; j < td.length; j++) {
          if (td[j]) {
            const txtValue = td[j].textContent || td[j].innerText;
            if (txtValue.toUpperCase().indexOf(filter) > -1) {
              found = true;
              break;
            }
          }
        }
        tr[i].style.display = found ? '' : 'none';
      }
    }

    // View Doctor
    function viewDoctor(data) {
      document.getElementById('viewDoctorId').textContent = 'DOC-' + String(data.Doctor_ID).padStart(6, '0');
      document.getElementById('viewName').textContent = 'Dr. ' + data.Doctor_FirstName + ' ' + data.Doctor_LastName;
      document.getElementById('viewEmail').textContent = data.Doctor_Email;
      document.getElementById('viewSpecialization').textContent = data.Services_Type;
      document.getElementById('viewLicense').textContent = data.Doctor_MedicalLicenseNumber;
      document.getElementById('viewExperience').textContent = data.Doctor_YearsExperience + ' years';
      document.getElementById('viewStatus').textContent = data.Doctor_Status;
      document.getElementById('viewDescription').value = data.Doctor_Description || '-';
      
      // Display Google Meet Link
      const gmeetLink = document.getElementById('viewGmeet');
      if (data.Gmeet_Link) {
        gmeetLink.textContent = data.Gmeet_Link;
        gmeetLink.href = data.Gmeet_Link;
      } else {
        gmeetLink.textContent = 'Not provided';
        gmeetLink.href = '#';
      }
      
      document.getElementById('viewModal').style.display = 'flex';
    }

    function closeViewModal() {
      document.getElementById('viewModal').style.display = 'none';
    }

    // Open Add Modal
    function openAddModal() {
      document.getElementById('modalTitle').textContent = 'Add Doctor';
      document.getElementById('doctorForm').reset();
      document.getElementById('doctorId').value = '';
      document.getElementById('doctorModal').style.display = 'flex';
    }

    // Edit Doctor
    function editDoctor(data) {
      document.getElementById('modalTitle').textContent = 'Edit Doctor';
      document.getElementById('doctorId').value = data.Doctor_ID;
      document.getElementById('firstName').value = data.Doctor_FirstName;
      document.getElementById('lastName').value = data.Doctor_LastName;
      document.getElementById('email').value = data.Doctor_Email;
      document.getElementById('license').value = data.Doctor_MedicalLicenseNumber;
      document.getElementById('experience').value = data.Doctor_YearsExperience;
      document.getElementById('specialization').value = data.Specialization_ID;
      document.getElementById('description').value = data.Doctor_Description || '';
      document.getElementById('gmeet').value = data.Gmeet_Link || '';
      document.getElementById('password').value = data.Doctor_Password;
      document.getElementById('doctorModal').style.display = 'flex';
    }

    function closeModal() {
      document.getElementById('doctorModal').style.display = 'none';
    }

    //
  </script>
</body>
</html>