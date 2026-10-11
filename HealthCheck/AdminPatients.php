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
    
    // Add/Edit Patient
    if (isset($_POST['action']) && $_POST['action'] === 'save_patient') {
        $patientId = isset($_POST['patient_id']) ? intval($_POST['patient_id']) : null;
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $password = $_POST['password'];
        $sex = $_POST['sex'];
        $height = !empty($_POST['height']) ? floatval($_POST['height']) : null;
        $weight = !empty($_POST['weight']) ? floatval($_POST['weight']) : null;
        
        if ($patientId) {
            // Update existing patient
            $stmt = $conn->prepare("
                UPDATE Patient 
                SET Patient_FirstName = ?,
                    Patient_LastName = ?,
                    Patient_Email = ?,
                    Patient_PhoneNumber = ?,
                    Patient_Password = ?,
                    Patient_Sex = ?,
                    Patient_Height = ?,
                    Patient_Weight = ?
                WHERE Patient_ID = ?
            ");
            $stmt->bind_param("ssssssddi", $firstName, $lastName, $email, $phone, $password, $sex, $height, $weight, $patientId);
            
            if ($stmt->execute()) {
                $message = 'Patient updated successfully!';
                $messageType = 'success';
            } else {
                $message = 'Error updating patient.';
                $messageType = 'error';
            }
        } else {
            // Add new patient
            $stmt = $conn->prepare("
                INSERT INTO Patient (Patient_FirstName, Patient_LastName, Patient_Email, Patient_PhoneNumber, Patient_Password, Patient_Sex, Patient_Height, Patient_Weight, Patient_Status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active')
            ");
            $stmt->bind_param("ssssssdd", $firstName, $lastName, $email, $phone, $password, $sex, $height, $weight);
            
            if ($stmt->execute()) {
                $message = 'Patient added successfully!';
                $messageType = 'success';
            } else {
                $message = 'Error adding patient.';
                $messageType = 'error';
            }
        }
        $stmt->close();
    }
    
    // Deactivate Patient (Soft Delete)
    if (isset($_POST['action']) && $_POST['action'] === 'deactivate_patient') {
        $patientId = intval($_POST['patient_id']);
        
        $stmt = $conn->prepare("UPDATE Patient SET Patient_Status = 'Inactive' WHERE Patient_ID = ?");
        $stmt->bind_param("i", $patientId);
        
        if ($stmt->execute()) {
            $message = 'Patient deactivated successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error deactivating patient.';
            $messageType = 'error';
        }
        $stmt->close();
    }
    
    // Activate Patient
    if (isset($_POST['action']) && $_POST['action'] === 'activate_patient') {
        $patientId = intval($_POST['patient_id']);
        
        $stmt = $conn->prepare("UPDATE Patient SET Patient_Status = 'Active' WHERE Patient_ID = ?");
        $stmt->bind_param("i", $patientId);
        
        if ($stmt->execute()) {
            $message = 'Patient activated successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error activating patient.';
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
   FETCH PATIENTS BASED ON FILTER
================================ */
$whereClause = $filter === 'all' ? '' : ($filter === 'inactive' ? "WHERE Patient_Status = 'Inactive'" : "WHERE Patient_Status = 'Active'");

$patients = $conn->query("
    SELECT 
        Patient_ID,
        Patient_FirstName,
        Patient_LastName,
        Patient_Email,
        Patient_PhoneNumber,
        Patient_Sex,
        Patient_Height,
        Patient_Weight,
        Patient_Password,
        Patient_Status
    FROM Patient
    $whereClause
    ORDER BY Patient_ID DESC
");

// Count statistics
$activeCount = $conn->query("SELECT COUNT(*) as count FROM Patient WHERE Patient_Status = 'Active'")->fetch_assoc()['count'];
$inactiveCount = $conn->query("SELECT COUNT(*) as count FROM Patient WHERE Patient_Status = 'Inactive'")->fetch_assoc()['count'];
$totalCount = $activeCount + $inactiveCount;

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HealthCheck Admin - Patient Management</title>
  <link rel="stylesheet" href="AccountStyle.css">
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
    }

    .LeftDashboard {
      width: 20%;
      height: 100vh;
      background-color: #272727;
      display: flex;
      flex-direction: column;
      align-items: center;
      color: white;
    }

    .Logo {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      margin-top: 20px;
      margin-bottom: 40px;
    }

    .Logo img {
      width: 50px;
      height: 50px;
    }

    .Logo h1 {
      font-size: 35px;
      color: #ffffff;
    }

    .DashboardControls {
      width: 100%;
      flex-grow: 1;
      display: flex;
      flex-direction: column;
    }

    .MainButtons {
      flex-grow: 1;
    }

    .LowerButtons {
      margin-top: auto;
      padding-top: 20px;
      border-top: 1px solid #444;
    }

    .DashboardControls button {
      padding: 12px 20px;
      margin: 8px 0;
      border: none;
      border-radius: 5px;
      background-color: #272727;
      color: white;
      font-size: 20px;
      cursor: pointer;
      text-align: left;
      width: 100%;
      display: flex;
      align-items: center;
      transition: background 0.3s;
    }

    .DashboardControls button:hover,
    .DashboardControls button.active {
      background-color: #00aaff;
    }

    .button-icon {
      margin-right: 10px;
    }

    .WelcomeBanner {
      background: linear-gradient(135deg, #00aaff 0%, #0066ff 100%);
      padding: 20px;
      color: white;
    }

    .WelcomeBanner h1 {
      font-size: 42px;
      margin-left: 10px;
    }

    .RightContent {
      width: 80%;
      height: 100vh;
      background-color: #f5f7fa;
      display: flex;
      flex-direction: column;
      overflow: hidden;
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

    .HeaderActions {
      display: flex;
      gap: 10px;
      align-items: center;
    }

    .AddButton {
      padding: 8px 16px;
      background-color: #0066ff;
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-weight: 600;
    }

    .AddButton:hover {
      background-color: #0052cc;
    }

    .StatusFilter {
      display: flex;
      gap: 8px;
    }

    .StatusFilter a {
      padding: 8px 16px;
      background-color: #f0f0f0;
      color: #333;
      text-decoration: none;
      border-radius: 4px;
      font-weight: 500;
      transition: all 0.3s;
    }

    .StatusFilter a.active {
      background-color: #0066ff;
      color: white;
    }

    .StatusFilter a:hover {
      background-color: #0052cc;
      color: white;
    }

    .StatsContainer {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 15px;
      margin-bottom: 20px;
    }

    .StatCard {
      background: white;
      padding: 15px;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      text-align: center;
    }

    .StatNumber {
      font-size: 28px;
      font-weight: bold;
      color: #0066ff;
    }

    .StatLabel {
      font-size: 12px;
      color: #666;
      margin-top: 5px;
    }

    .SearchBar {
      margin-bottom: 20px;
    }

    .SearchBar input {
      width: 100%;
      padding: 10px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 16px;
    }

    .ManagementTable {
      width: 100%;
      border-collapse: collapse;
      background-color: white;
      border-radius: 8px;
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

    .ActionButtons {
      display: flex;
      gap: 8px;
    }

    .ActionButtons button {
      padding: 6px 12px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 12px;
      color: white;
    }

    .ViewButton { background-color: #0066ff; }
    .EditButton { background-color: #f59e0b; }
    .DeactivateButton { background-color: #c53030; }
    .ActivateButton { background-color: #28a745; }

    .StatusBadge {
      padding: 4px 12px;
      border-radius: 12px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
    }

    .StatusBadge.active {
      background-color: #d4edda;
      color: #155724;
    }

    .StatusBadge.inactive {
      background-color: #f8d7da;
      color: #721c24;
    }

    .Modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
      z-index: 1000;
      justify-content: center;
      align-items: center;
    }

    .ModalContent {
      background-color: white;
      border-radius: 8px;
      width: 80%;
      max-width: 700px;
      max-height: 80%;
      overflow-y: auto;
      padding: 20px;
    }

    .ModalHeader {
      display: flex;
      justify-content: space-between;
      margin-bottom: 20px;
      border-bottom: 1px solid #eee;
      padding-bottom: 10px;
    }

    .ModalHeader h3 {
      font-size: 24px;
    }

    .CloseButton {
      background: none;
      border: none;
      font-size: 24px;
      cursor: pointer;
    }

    .FormGroup {
      margin-bottom: 15px;
    }

    .FormGroup label {
      display: block;
      font-weight: 600;
      margin-bottom: 5px;
    }

    .FormGroup input,
    .FormGroup select {
      width: 100%;
      padding: 10px;
      border: 1px solid #ddd;
      border-radius: 4px;
    }

    .FormRow {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 15px;
    }

    .ModalActions {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      margin-top: 20px;
    }

    .ModalActions button {
      padding: 10px 20px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-weight: 600;
    }

    .CancelButton {
      background-color: #f1f1f1;
      color: #333;
    }

    .SaveButton {
      background-color: #0066ff;
      color: white;
    }

    .message {
      padding: 15px;
      margin: 20px 0;
      border-radius: 5px;
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

    .PatientInfo {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 15px;
    }

    .InfoItem {
      margin-bottom: 10px;
    }

    .InfoItem label {
      font-weight: 600;
      display: block;
      margin-bottom: 5px;
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
        <button class="nav-btn" data-page="overview"><span class="button-icon">📊</span> Overview</button>
        <button class="nav-btn active" data-page="patients"><span class="button-icon">👥</span> Patients</button>
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
      <h1>Admin Dashboard<br>Patient Management</h1>
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
          <div class="StatLabel">Total Patients</div>
        </div>
        <div class="StatCard">
          <div class="StatNumber"><?= $activeCount ?></div>
          <div class="StatLabel">Active Patients</div>
        </div>
        <div class="StatCard">
          <div class="StatNumber"><?= $inactiveCount ?></div>
          <div class="StatLabel">Inactive Patients</div>
        </div>
      </div>

      <div class="ManagementHeader">
        <h2>Manage Patients</h2>
        <div class="HeaderActions">
          <div class="StatusFilter">
            <a href="?filter=active" class="<?= $filter === 'active' ? 'active' : '' ?>">Active</a>
            <a href="?filter=inactive" class="<?= $filter === 'inactive' ? 'active' : '' ?>">Inactive</a>
            <a href="?filter=all" class="<?= $filter === 'all' ? 'active' : '' ?>">All</a>
          </div>
          <button class="AddButton" onclick="openAddModal()">+ Add Patient</button>
        </div>
      </div>

      <div class="SearchBar">
        <input type="text" placeholder="Search patients..." id="patientSearch" onkeyup="searchTable()">
      </div>

      <table class="ManagementTable" id="patientTable">
        <thead>
          <tr>
            <th>Patient ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Sex</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($patient = $patients->fetch_assoc()): ?>
            <tr>
              <td>PAT-<?= str_pad($patient['Patient_ID'], 6, '0', STR_PAD_LEFT) ?></td>
              <td><?= htmlspecialchars($patient['Patient_FirstName'] . ' ' . $patient['Patient_LastName']) ?></td>
              <td><?= htmlspecialchars($patient['Patient_Email']) ?></td>
              <td><?= htmlspecialchars($patient['Patient_PhoneNumber']) ?></td>
              <td><?= htmlspecialchars($patient['Patient_Sex']) ?></td>
              <td>
                <span class="StatusBadge <?= strtolower($patient['Patient_Status']) ?>">
                  <?= $patient['Patient_Status'] ?>
                </span>
              </td>
              <td class="ActionButtons">
                <button class="ViewButton" onclick='viewPatient(<?= json_encode($patient) ?>)'>View</button>
                <button class="EditButton" onclick='editPatient(<?= json_encode($patient) ?>)'>Edit</button>
                <?php if ($patient['Patient_Status'] === 'Active'): ?>
                  <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to deactivate this patient?');">
                    <input type="hidden" name="action" value="deactivate_patient">
                    <input type="hidden" name="patient_id" value="<?= $patient['Patient_ID'] ?>">
                    <button type="submit" class="DeactivateButton">Deactivate</button>
                  </form>
                <?php else: ?>
                  <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to activate this patient?');">
                    <input type="hidden" name="action" value="activate_patient">
                    <input type="hidden" name="patient_id" value="<?= $patient['Patient_ID'] ?>">
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

  <!-- ADD/EDIT PATIENT MODAL -->
  <div id="patientModal" class="Modal">
    <div class="ModalContent">
      <div class="ModalHeader">
        <h3 id="modalTitle">Add Patient</h3>
        <button class="CloseButton" onclick="closeModal()">&times;</button>
      </div>

      <form method="POST" id="patientForm">
        <input type="hidden" name="action" value="save_patient">
        <input type="hidden" name="patient_id" id="patientId">
        
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
            <label>Password</label>
            <input type="password" name="password" id="password" required minlength="6">
          </div>
          <div class="FormGroup">
            <label>Sex</label>
            <select name="sex" id="sex" required>
              <option value="">Select</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Other">Other</option>
            </select>
          </div>
        </div>

        <div class="FormRow">
          <div class="FormGroup">
            <label>Height (cm)</label>
            <input type="number" step="0.01" name="height" id="height">
          </div>
          <div class="FormGroup">
            <label>Weight (kg)</label>
            <input type="number" step="0.01" name="weight" id="weight">
          </div>
        </div>

        <div class="ModalActions">
          <button type="button" class="CancelButton" onclick="closeModal()">Cancel</button>
          <button type="submit" class="SaveButton">Save Patient</button>
        </div>
      </form>
    </div>
  </div>

  <!-- VIEW PATIENT MODAL -->
  <div id="viewModal" class="Modal">
    <div class="ModalContent">
      <div class="ModalHeader">
        <h3>Patient Details</h3>
        <button class="CloseButton" onclick="closeViewModal()">&times;</button>
      </div>

      <div class="PatientInfo">
        <div class="InfoItem">
          <label>Patient ID</label>
          <span id="viewPatientId">-</span>
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
          <label>Phone</label>
          <span id="viewPhone">-</span>
        </div>
        <div class="InfoItem">
          <label>Sex</label>
          <span id="viewSex">-</span>
        </div>
        <div class="InfoItem">
          <label>Height</label>
          <span id="viewHeight">-</span>
        </div>
        <div class="InfoItem">
          <label>Weight</label>
          <span id="viewWeight">-</span>
        </div>
        <div class="InfoItem">
          <label>Status</label>
          <span id="viewStatus">-</span>
        </div>
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
      const input = document.getElementById('patientSearch');
      const filter = input.value.toUpperCase();
      const table = document.getElementById('patientTable');
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

    // View Patient
    function viewPatient(data) {
      document.getElementById('viewPatientId').textContent = 'PAT-' + String(data.Patient_ID).padStart(6, '0');
      document.getElementById('viewName').textContent = data.Patient_FirstName + ' ' + data.Patient_LastName;
      document.getElementById('viewEmail').textContent = data.Patient_Email;
      document.getElementById('viewPhone').textContent = data.Patient_PhoneNumber;
      document.getElementById('viewSex').textContent = data.Patient_Sex;
      document.getElementById('viewHeight').textContent = data.Patient_Height ? data.Patient_Height + ' cm' : '-';
      document.getElementById('viewWeight').textContent = data.Patient_Weight ? data.Patient_Weight + ' kg' : '-';
      document.getElementById('viewStatus').textContent = data.Patient_Status;
      document.getElementById('viewModal').style.display = 'flex';
    }

    function closeViewModal() {
      document.getElementById('viewModal').style.display = 'none';
    }

    // Open Add Modal
    function openAddModal() {
      document.getElementById('modalTitle').textContent = 'Add Patient';
      document.getElementById('patientForm').reset();
      document.getElementById('patientId').value = '';
      document.getElementById('patientModal').style.display = 'flex';
    }

    // Edit Patient
    function editPatient(data) {
      document.getElementById('modalTitle').textContent = 'Edit Patient';
      document.getElementById('patientId').value = data.Patient_ID;
      document.getElementById('firstName').value = data.Patient_FirstName;
      document.getElementById('lastName').value = data.Patient_LastName;
      document.getElementById('email').value = data.Patient_Email;
      document.getElementById('phone').value = data.Patient_PhoneNumber;
      document.getElementById('password').value = data.Patient_Password;
      document.getElementById('sex').value = data.Patient_Sex;
      document.getElementById('height').value = data.Patient_Height || '';
      document.getElementById('weight').value = data.Patient_Weight || '';
      document.getElementById('patientModal').style.display = 'flex';
    }

    function closeModal() {
      document.getElementById('patientModal').style.display = 'none';
    }

    // Close modal on outside click
    window.addEventListener('click', function (event) {
      if (event.target.classList.contains('Modal')) {
        event.target.style.display = 'none';
      }
    });
  </script>
</body>
</html>