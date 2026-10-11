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
  <title>HealthCheck Dashboard - Services</title>
  <link rel="stylesheet" href="ServicesStyle.css">
  
</head>

<body>
  <div class="LeftDashboard">
    <div class="Logo">
      <img src="assets/Logo-Notitle.png" alt="Logo">
      <h1>HealthCheck</h1>
    </div>

    <div class="DashboardControls">
      <div class="MainButtons">
        <button class="nav-btn" data-page="home"><span class="button-icon">🏠</span> Home</button>
        <button class="nav-btn active" data-page="services"><span class="button-icon">🩺</span> Services</button>
       
        <button class="nav-btn" data-page="appointments"><span class="button-icon">📅</span> Appointments</button>
        <button class="nav-btn" data-page="account"><span class="button-icon">👤</span> Account</button>
      </div>

      <div class="LowerButtons">

        <button class="nav-btn" data-page="logout"><span class="button-icon">🚪</span> Log Out</button>
      </div>
    </div>
  </div>

  <div class="RightContent">
   <div class="WelcomeBanner">
     <h1>Welcome back,<br><?= htmlspecialchars($userName) ?></h1>
    </div>

    <div class="ServicesContent">
      <div class="ServicesHeader">
        <h2>Our Medical Services</h2>
        <p>We offer a comprehensive range of healthcare services provided by experienced specialists. Book your
          consultation today.</p>
      </div>

      <div class="ServicesGrid">
        <!-- Service 1 -->
        <div class="ServiceCard" data-service="generalphysician">
          <div class="ServiceIcon">👨‍⚕️</div>
          <h3>General Physician Consultation</h3>
          <p>Comprehensive health assessments and treatment for common medical conditions.</p>
          <ul class="ServiceFeatures">
            <li>Routine check-ups</li>
            <li>Diagnosis and treatment</li>
            <li>Preventive care</li>
          </ul>
          <button class="BookButton">Book Consultation</button>
        </div>

        <!-- Service 2 -->
        <div class="ServiceCard" data-service="pediatrics">
          <div class="ServiceIcon">👶</div>
          <h3>Pediatrics Consultation</h3>
          <p>Specialized healthcare for infants, children, and adolescents.</p>
          <ul class="ServiceFeatures">
            <li>Child wellness exams</li>
            <li>Common illness</li>
            <li>Growth monitoring</li>
          </ul>
          <button class="BookButton">Book Consultation</button>
        </div>

        <!-- Service 3 -->
        <div class="ServiceCard" data-service="dermatologist">
          <div class="ServiceIcon">🔬</div>
          <h3>Dermatologist Consultation</h3>
          <p>Expert care for skin, hair, and nail conditions.</p>
          <ul class="ServiceFeatures">
            <li>Skin conditions assessment</li>
            <li>Acne treatment</li>
            <li>Cosmetic dermatology</li>
          </ul>
          <button class="BookButton">Book Consultation</button>
        </div>


      </div>
    </div>
  </div>
  </div>

  <script>
    // Wait until DOM is loaded
    document.addEventListener("DOMContentLoaded", () => {
      // Get all navigation buttons
      const navButtons = document.querySelectorAll('.nav-btn');
      const bookButtons = document.querySelectorAll('.BookButton');

      // Service to HTML file mapping
      const serviceFileMap = {
        'generalphysician': 'Generalphysician_Doctors.php',
        'pediatrics': 'GeneralPediatrics_Doctors.php',
        'dermatologist': 'GeneralDermatologist_Doctors.php',
        'obgyne': 'obgyne.html',
        'familyphysician': 'familyphysician.html',
        'psychological': 'psychological.html',
        'lifestylemedicine': 'lifestylemedicine.html'
      };

      // Navigation function for sidebar
      function navigateToPage(page) {
        switch (page) {
          case 'home':
            window.location.href = "Home.php";
            break;
          case 'services':
            // Already on services page, do nothing
            console.log('Already on Services page');
            break;
       
          case 'appointments':
            window.location.href = "Appointments.php  ";
            break;
          case 'account':
            window.location.href = "Account.php";
            break;
          case 'call-center':
            // For call center, use tel: link
            if (confirm('Call HealthCheck Support at 1-800-HEALTHY?')) {
              window.location.href = "tel:1-800-HEALTHY";
            }
            break;
          case 'help':
            window.location.href = "Help.html";
            break;
          case 'logout':
            // Logout functionality
            if (confirm('Are you sure you want to log out?')) {
              window.location.href = "Login.php";
            }
            break;
          default:
            console.log('Unknown page:', page);
        }
      }

      // Navigation function for service booking
      function bookService(serviceCard) {
        const serviceType = serviceCard.getAttribute('data-service');
        const serviceName = serviceCard.querySelector('h3').textContent;
        const htmlFile = serviceFileMap[serviceType];

        if (htmlFile) {
          {
            window.location.href = htmlFile;
          }
        } else {
          alert(`Service page for ${serviceName} is not available.`);
        }
      }

      // Add event listeners to all navigation buttons
      navButtons.forEach(button => {
        button.addEventListener('click', function () {
          const page = this.getAttribute('data-page');
          navigateToPage(page);
        });
      });

      // Add event listeners to Book Consultation buttons
      bookButtons.forEach(button => {
        button.addEventListener('click', function () {
          const serviceCard = this.closest('.ServiceCard');
          bookService(serviceCard);
        });
      });

      // Update active state based on current page
      function updateActiveState() {
        navButtons.forEach(button => {
          button.classList.remove('active');
        });

        // Set current page as active (Services page in this case)
        const currentPageButton = document.querySelector('.nav-btn[data-page="services"]');
        if (currentPageButton) {
          currentPageButton.classList.add('active');
        }
      }

      // Initialize active state
      updateActiveState();

      console.log('HealthCheck Services Page initialized successfully');
    });
  </script>
</body>

</html>