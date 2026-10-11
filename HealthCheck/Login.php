<?php
session_start();

/* =======================
   DATABASE CONNECTION
======================= */
$host = "localhost";
$dbUsername = "root";
$dbPassword = "";
$dbName = "HealthDB";

$conn = new mysqli($host, $dbUsername, $dbPassword, $dbName);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = "";

/* =======================
   LOGIN LOGIC
======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    /* =======================
       CHECK ADMIN ACCOUNT
    ======================= */
    $stmtAdmin = $conn->prepare(
        "SELECT 
            Admin_ID,
            Admin_FirstName,
            Admin_LastName,
            Admin_Password
        FROM Admin
        WHERE Admin_Email = ?"
    );
    $stmtAdmin->bind_param("s", $email);
    $stmtAdmin->execute();
    $resultAdmin = $stmtAdmin->get_result();

    if ($resultAdmin->num_rows === 1) {
        $user = $resultAdmin->fetch_assoc();

        if ($password === $user['Admin_Password']) {
            $_SESSION['user_type'] = "Admin";
            $_SESSION['user_id'] = $user['Admin_ID'];
            $_SESSION['user_name'] = $user['Admin_FirstName'] . " " . $user['Admin_LastName'];

            header("Location: AdminMain.php");
            exit();
        } else {
            $error = "Incorrect password.";
        }
        $stmtAdmin->close();
    } else {
        /* =======================
           CHECK PATIENT ACCOUNT
        ======================= */
        $stmtPatient = $conn->prepare(
            "SELECT 
                Patient_ID,
                Patient_FirstName,
                Patient_LastName,
                Patient_Password,
                Patient_PhoneNumber,
                Patient_Sex,
                Patient_Height,
                Patient_Weight,
                Patient_Medical_History,
                Patient_Status
            FROM Patient
            WHERE Patient_Email = ?"
        );
        $stmtPatient->bind_param("s", $email);
        $stmtPatient->execute();
        $resultPatient = $stmtPatient->get_result();

        if ($resultPatient->num_rows === 1) {
            $user = $resultPatient->fetch_assoc();

            if ($user['Patient_Status'] !== 'Active') {
                $error = "Your account is inactive. Please contact the administrator.";
            } elseif ($password === $user['Patient_Password']) {
                $_SESSION['user_type'] = "Patient";
                $_SESSION['user_id'] = $user['Patient_ID'];
                $_SESSION['user_name'] = $user['Patient_FirstName'] . " " . $user['Patient_LastName'];
                $_SESSION['user_phone'] = $user['Patient_PhoneNumber'];
                $_SESSION['user_sex'] = $user['Patient_Sex'];
                $_SESSION['user_height'] = $user['Patient_Height'];
                $_SESSION['user_weight'] = $user['Patient_Weight'];
                $_SESSION['user_medical_history'] = $user['Patient_Medical_History'];

                header("Location: Home.php");
                exit();
            } else {
                $error = "Incorrect password.";
            }
            $stmtPatient->close();
        } else {
            /* =======================
               CHECK DOCTOR ACCOUNT
            ======================= */
            $stmtDoctor = $conn->prepare(
                "SELECT 
                    Doctor_ID,
                    Doctor_FirstName,
                    Doctor_LastName,
                    Doctor_Password,
                    Doctor_Status
                FROM Doctor
                WHERE Doctor_Email = ?"
            );

            $stmtDoctor->bind_param("s", $email);
            $stmtDoctor->execute();
            $resultDoctor = $stmtDoctor->get_result();

            if ($resultDoctor->num_rows === 1) {
                $user = $resultDoctor->fetch_assoc();

                if ($user['Doctor_Status'] !== 'Active') {
                    $error = "Your account is inactive. Please contact the administrator.";
                } elseif ($password === $user['Doctor_Password']) {
                    $_SESSION['user_type'] = "Doctor";
                    $_SESSION['user_id'] = $user['Doctor_ID'];
                    $_SESSION['user_name'] = $user['Doctor_FirstName'] . " " . $user['Doctor_LastName'];

                    header("Location: DoctorMain.php");
                    exit();
                } else {
                    $error = "Incorrect password.";
                }
                $stmtDoctor->close();
            } else {
                $error = "No account found with that email.";
            }
        }
    }
}

/* OPTIONAL BUT GOOD PRACTICE */
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Page</title>
<link rel="stylesheet" href="LoginStyle.css">
</head>
<body>

<div class="left">
  <div class="login-box">
    <h2>Welcome to HealthCheck</h2>

    <form method="POST">
      <input type="email" name="email" placeholder="Email" required>
      <input type="password" name="password" placeholder="Password" required>

      <div class="options">
        <label class="remember-me">
          <input type="checkbox"> Remember me
        </label>
        <a href="#">Forgot Password?</a>
      </div>

      <button type="submit" class="login-btn">LOGIN</button>

      <?php if (!empty($error)): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>
    </form>

    <div class="social-login">
      <div class="divider"><span>Or</span></div>
    </div>

    <button type="button" class="signup-btn"
        onclick="window.location.href='signup.php'">
        🌐 SIGN UP
    </button>

  </div>
</div>

<div class="right"></div>

</body>
</html>