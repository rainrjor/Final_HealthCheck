<?php
session_start();
include 'config.php'; // your database connection

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $birthdate = trim($_POST['birthdate']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);
    $gender = trim($_POST['gender']);
    
    // Validate required fields
    if (empty($firstName) || empty($lastName) || empty($birthdate) || empty($email) || empty($phone) || empty($password) || empty($gender)) {
        $error = "Please fill out all fields.";
    } 
    // Validate email format
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    }
    // Validate phone number (10-15 digits)
    elseif (!preg_match('/^[0-9]{10,15}$/', $phone)) {
        $error = "Please enter a valid phone number (10-15 digits).";
    }
    // Validate password length
    elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    }
    // Validate gender
    elseif (!in_array($gender, ['Male', 'Female', 'Other'])) {
        $error = "Please select a valid gender.";
    }
    else {
        // Check if email already exists in Patient table
        $checkStmt = $conn->prepare("SELECT Patient_ID FROM Patient WHERE Patient_Email = ?");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $error = "Email already exists. Please use a different email.";
        } else {
            // Generate unique user_id (max 10 characters as per schema)
            // Get the highest user number
            $result = $conn->query("SELECT User_ID FROM Users WHERE User_ID LIKE 'U%' ORDER BY User_ID DESC LIMIT 1");
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $lastId = intval(substr($row['User_ID'], 1));
                $newId = $lastId + 1;
            } else {
                $newId = 6; // Start from U6 (U1-U5 already exist)
            }
            $user_id = 'U' . $newId;
            
            // Set default user role as R1 (Patient)
            $user_role = 'R1';
            
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Insert into Users table
                $stmt1 = $conn->prepare("INSERT INTO Users (User_ID, Role_ID) VALUES (?, ?)");
                $stmt1->bind_param("ss", $user_id, $user_role);
                $stmt1->execute();
                $stmt1->close();
                
                // Insert into Patient table with gender
                $stmt2 = $conn->prepare("INSERT INTO Patient (User_ID, Patient_FirstName, Patient_LastName, Patient_Email, Patient_Password, Patient_PhoneNumber, Patient_Sex) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt2->bind_param("sssssss", $user_id, $firstName, $lastName, $email, $password, $phone, $gender);
                $stmt2->execute();
                $patientId = $conn->insert_id;
                $stmt2->close();
                
                // Commit transaction
                $conn->commit();
                
                // Create session for the new user
                $_SESSION['user_id'] = $user_id;
                $_SESSION['patient_id'] = $patientId;
                $_SESSION['user_role'] = $user_role;
                $_SESSION['first_name'] = $firstName;
                
                $success = "Account created successfully! Redirecting...";
                // Redirect after 1 second
                header("refresh:1;url=Login.php");
                
            } catch (Exception $e) {
                // Rollback on error
                $conn->rollback();
                $error = "Error creating account. Please try again.";
            }
        }
        $checkStmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Signup Page</title>
  <link rel="stylesheet" href="SignupStyle.css">
</head>

<body>

  <!-- Left: Signup form -->
  <div class="left">
    <div class="signup-box">
      <h2>Create your account</h2>
      <form method="POST">
        <input type="text" name="first_name" placeholder="First Name" required>
        <input type="text" name="last_name" placeholder="Last Name" required>
        <input type="text" name="birthdate" placeholder="Birthdate" onfocus="(this.type='date')" required>
        
        <!-- Gender Dropdown -->
        <select name="gender" required>
          <option value="" disabled selected>Select Gender</option>
          <option value="Male">Male</option>
          <option value="Female">Female</option>
          <option value="Other">Other</option>
        </select>

        <input type="email" name="email" placeholder="Email" required>
        <input type="tel" name="phone" placeholder="Phone Number" required>
        <input type="password" name="password" placeholder="Password" required>

        <?php if($error != ''): ?>
          <p class="error" style="color: red; margin-top: 10px;"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        
        <?php if($success != ''): ?>
          <p class="success" style="color: green; margin-top: 10px;"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>

        <button type="submit" class="signup-btn">SIGN UP</button>
      </form>

      <div class="social-login">
        <p>Already have an account?</p>
      </div>

      <button type="button" class="login-btn" onclick="window.location.href='Login.php'">🔑 LOGIN</button>
    </div>
  </div>

  <!-- Right: Background image -->
  <div class="right">
    <!-- Decorative image or illustration -->
  </div>

</body>

</html>