<?php
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Database connection
$db = new mysqli('localhost', 'root', '', 'volunteer_management_system');

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Set timezone and get current date/time
date_default_timezone_set('Asia/Kolkata'); // Set to Indian time zone
$current_date = date('Y-m-d');
$current_time = date('H:i:s');

// Get volunteer ID from URL with validation
$volunteer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($volunteer_id <= 0) {
    die("Invalid volunteer ID specified");
}
// Get volunteer details with error handling
$stmt = $db->prepare("SELECT * FROM volunteers WHERE volunteer_id = ?");
if (!$stmt) {
    die("Database error: " . $db->error);
}
$stmt->bind_param("i", $volunteer_id);
if (!$stmt->execute()) {
    die("Error executing query: " . $stmt->error);
}
$result = $stmt->get_result();
$volunteer = $result->fetch_assoc();

if (!$volunteer) {
    $check_exists = $db->query("SELECT COUNT(*) as count FROM volunteers")->fetch_assoc();
    die("Volunteer not found. Possible reasons:<br>
        1. ID $volunteer_id doesn't exist in database<br>
        2. Database contains {$check_exists['count']} volunteers total");
}

// Get volunteer skills
$skills_stmt = $db->prepare("SELECT s.skill_id, s.skill_name FROM volunteer_skills vs JOIN skills s ON vs.skill_id = s.skill_id WHERE vs.volunteer_id = ?");
$skills_stmt->bind_param("i", $volunteer_id);
$skills_stmt->execute();
$skills_result = $skills_stmt->get_result();
$volunteer_skills = [];
while ($skill = $skills_result->fetch_assoc()) {
    $volunteer_skills[] = $skill;
}

// Handle attendance confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_attendance'])) {
  $event_id = $_POST['event_id'];
  $attended = $_POST['confirm_attendance'] === 'yes' ? 1 : 0;
  
  $update_stmt = $db->prepare("UPDATE volunteer_participation SET attended = ? WHERE volunteer_id = ? AND event_id = ?");
  $update_stmt->bind_param("iii", $attended, $volunteer_id, $event_id);
  
  if ($update_stmt->execute()) {
      $_SESSION['success_message'] = $attended ? "Attendance confirmed successfully!" : "Volunteer marked as not attended!";
  } else {
      $_SESSION['error_message'] = "Error updating attendance: " . $db->error;
  }
  
  header("Location: view_volunteer.php?id=$volunteer_id");
  exit();
}

// Handle certificate generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_certificate'])) {
  $event_id = $_POST['event_id'];
  
  // Check if certificate already generated
  $check_stmt = $db->prepare("SELECT certificate_generated FROM volunteer_participation WHERE volunteer_id = ? AND event_id = ?");
  $check_stmt->bind_param("ii", $volunteer_id, $event_id);
  $check_stmt->execute();
  $cert_result = $check_stmt->get_result()->fetch_assoc();
  
  if ($cert_result['certificate_generated']) {
      $_SESSION['error_message'] = "Certificate already generated for this event!";
  } else {
      // Generate certificate (in a real app, you would generate a PDF here)
      $update_stmt = $db->prepare("UPDATE volunteer_participation SET certificate_generated = 1 WHERE volunteer_id = ? AND event_id = ?");
      $update_stmt->bind_param("ii", $volunteer_id, $event_id);
      
      if ($update_stmt->execute()) {
          $_SESSION['success_message'] = "Certificate generated successfully!";
      } else {
          $_SESSION['error_message'] = "Error generating certificate: " . $db->error;
      }
  }
  
  header("Location: view_volunteer.php?id=$volunteer_id");
  exit();
}

// Handle hours update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_hours'])) {
    $event_id = $_POST['event_id'];
    $hours_worked = floatval($_POST['hours_worked']);
    
    $update_stmt = $db->prepare("UPDATE volunteer_participation SET hours_worked = ? WHERE volunteer_id = ? AND event_id = ?");
    $update_stmt->bind_param("dii", $hours_worked, $volunteer_id, $event_id);
    
    if ($update_stmt->execute()) {
        $_SESSION['success_message'] = "Hours updated successfully!";
    } else {
        $_SESSION['error_message'] = "Error updating hours: " . $db->error;
    }
    
    header("Location: view_volunteer.php?id=$volunteer_id");
    exit();
}

// Get registered events (upcoming/ongoing) with event duration info
$registered_events = $db->query("
    SELECT e.event_id, e.event_name, e.event_date, e.start_time, e.end_time, 
           vp.hours_worked, vp.certificate_generated, vp.attended,
           TIMESTAMPDIFF(HOUR, CONCAT(e.event_date, ' ', e.start_time), CONCAT(e.event_date, ' ', e.end_time)) as event_duration_hours,
           CASE 
               WHEN e.event_date > '$current_date' OR (e.event_date = '$current_date' AND e.start_time > '$current_time') THEN 'upcoming'
               WHEN e.event_date = '$current_date' AND e.start_time <= '$current_time' AND e.end_time >= '$current_time' THEN 'ongoing'
               ELSE 'completed'
           END as event_status
    FROM volunteer_participation vp
    JOIN events e ON vp.event_id = e.event_id
    WHERE vp.volunteer_id = $volunteer_id 
    AND (e.event_date > '$current_date' 
         OR (e.event_date = '$current_date' AND e.end_time > '$current_time')
         OR (e.event_date = '$current_date' AND e.start_time <= '$current_time' AND e.end_time >= '$current_time'))
    ORDER BY e.event_date ASC
");

// Get completed events (where attendance has been confirmed)
$completed_events = $db->query("
    SELECT e.event_id, e.event_name, e.event_date, e.start_time, e.end_time,
           vp.hours_worked, vp.certificate_generated,
           TIMESTAMPDIFF(HOUR, CONCAT(e.event_date, ' ', e.start_time), CONCAT(e.event_date, ' ', e.end_time)) as event_duration_hours
    FROM volunteer_participation vp
    JOIN events e ON vp.event_id = e.event_id
    WHERE vp.volunteer_id = $volunteer_id 
    AND ((e.event_date < '$current_date') 
         OR (e.event_date = '$current_date' AND e.end_time < '$current_time'))
    AND vp.attended = 1
    ORDER BY e.event_date DESC
");

// Get missed events (where attendance was marked as not attended)
$missed_events = $db->query("
    SELECT e.event_id, e.event_name, e.event_date, e.start_time, e.end_time,
           vp.hours_worked, vp.certificate_generated,
           TIMESTAMPDIFF(HOUR, CONCAT(e.event_date, ' ', e.start_time), CONCAT(e.event_date, ' ', e.end_time)) as event_duration_hours
    FROM volunteer_participation vp
    JOIN events e ON vp.event_id = e.event_id
    WHERE vp.volunteer_id = $volunteer_id 
    AND ((e.event_date < '$current_date') 
         OR (e.event_date = '$current_date' AND e.end_time < '$current_time'))
    AND vp.attended = 0
    ORDER BY e.event_date DESC
");

// Get events that need attendance confirmation (completed but not confirmed)
$pending_confirmation = $db->query("
    SELECT e.event_id, e.event_name, e.event_date, e.start_time, e.end_time,
           vp.hours_worked, vp.certificate_generated, vp.attended,
           TIMESTAMPDIFF(HOUR, CONCAT(e.event_date, ' ', e.start_time), CONCAT(e.event_date, ' ', e.end_time)) as event_duration_hours
    FROM volunteer_participation vp
    JOIN events e ON vp.event_id = e.event_id
    WHERE vp.volunteer_id = $volunteer_id 
    AND ((e.event_date < '$current_date') 
         OR (e.event_date = '$current_date' AND e.end_time < '$current_time'))
    AND vp.attended IS NULL
    ORDER BY e.event_date DESC
");
// Calculate total hours
$total_hours_result = $db->query("SELECT SUM(hours_worked) as total FROM volunteer_participation WHERE volunteer_id = $volunteer_id AND attended = 1");
if (!$total_hours_result) {
    die("Error calculating total hours: " . $db->error);
}
$total_hours = $total_hours_result->fetch_assoc()['total'];

// Calculate total events
$total_events = $registered_events->num_rows + $completed_events->num_rows + $missed_events->num_rows + $pending_confirmation->num_rows;

// Get certificates count
$cert_count = $db->query("SELECT COUNT(*) as count FROM volunteer_participation WHERE volunteer_id = $volunteer_id AND certificate_generated = 1")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>View Volunteer | VolunteerHub</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .event-card {
        transition: all 0.3s ease;
    }
    .event-card:hover {
        transform: translateY(-5px);
    }
    .tab-content {
        display: none;
    }
    .tab-content.active {
        display: block;
    }
    .skill-badge {
        transition: all 0.3s ease;
    }
    .skill-badge:hover {
        transform: scale(1.05);
    }
  </style>
</head>
<body class="bg-gray-50 min-h-screen">
  <!-- Header -->
  <header class="bg-gradient-to-r from-blue-700 to-blue-500 text-white p-4 sticky top-0 z-50 shadow-md">
    <div class="max-w-7xl mx-auto flex justify-between items-center">
      <h1 class="text-xl font-bold flex items-center">
        <i class="fas fa-users mr-2"></i>
        VolunteerHub Admin
      </h1>
      
      <div class="flex items-center space-x-4">
        <span class="hidden md:inline"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
        <a href="logout.php" class="bg-white text-blue-600 px-3 py-1 rounded-lg text-sm hover:bg-blue-50 transition-colors">
          <i class="fas fa-sign-out-alt mr-1"></i> Logout
        </a>
      </div>
    </div>
  </header>

  <!-- Sidebar and Main Content -->
  <div class="flex">
    <!-- Sidebar -->
    <aside class="bg-white w-64 min-h-screen shadow-md hidden md:block">
      <div class="p-4">
        <div class="text-center py-4 border-b border-gray-200">
          <h2 class="text-lg font-semibold text-blue-700"><?php echo htmlspecialchars($_SESSION['organization']); ?></h2>
          <p class="text-sm text-gray-500">Admin Dashboard</p>
        </div>
        
        <nav class="mt-6">
          <a href="dashboard.php" class="flex items-center py-2 px-4 text-gray-600 hover:bg-blue-50 hover:text-blue-700 rounded-lg transition-colors">
            <i class="fas fa-tachometer-alt w-5 mr-2"></i> Dashboard
          </a>
          <a href="volunteers.php" class="flex items-center py-2 px-4 mt-2 bg-blue-50 text-blue-700 rounded-lg font-medium">
            <i class="fas fa-users w-5 mr-2"></i> Volunteers
          </a>
          <a href="events.php" class="flex items-center py-2 px-4 mt-2 text-gray-600 hover:bg-blue-50 hover:text-blue-700 rounded-lg transition-colors">
            <i class="fas fa-calendar-alt w-5 mr-2"></i> Events
          </a>
          <a href="certificates.php" class="flex items-center py-2 px-4 mt-2 text-gray-600 hover:bg-blue-50 hover:text-blue-700 rounded-lg transition-colors">
            <i class="fas fa-certificate w-5 mr-2"></i> Certificates
          </a>
          <a href="reports.php" class="flex items-center py-2 px-4 mt-2 text-gray-600 hover:bg-blue-50 hover:text-blue-700 rounded-lg transition-colors">
            <i class="fas fa-chart-bar w-5 mr-2"></i> Reports
          </a>
        </nav>
      </div>
    </aside>
    
    <!-- Mobile Sidebar Toggle -->
    <div class="md:hidden fixed bottom-4 right-4 z-40">
      <button id="sidebar-toggle" class="bg-blue-600 text-white p-3 rounded-full shadow-lg">
        <i class="fas fa-bars"></i>
      </button>
    </div>
    
    <!-- Mobile Sidebar -->
    <div id="mobile-sidebar" class="fixed inset-0 bg-gray-900 bg-opacity-50 z-40 hidden md:hidden">
      <div class="absolute left-0 top-0 bottom-0 w-64 bg-white shadow-lg transform transition-transform duration-300 ease-in-out">
        <div class="p-4">
          <button id="close-sidebar" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700">
            <i class="fas fa-times"></i>
          </button>
          
          <div class="text-center py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-blue-700"><?php echo htmlspecialchars($_SESSION['organization']); ?></h2>
            <p class="text-sm text-gray-500">Admin Dashboard</p>
          </div>
          
          <nav class="mt-6">
            <a href="dashboard.php" class="flex items-center py-2 px-4 text-gray-600 hover:bg-blue-50 hover:text-blue-700 rounded-lg transition-colors">
              <i class="fas fa-tachometer-alt w-5 mr-2"></i> Dashboard
            </a>
            <a href="volunteers.php" class="flex items-center py-2 px-4 mt-2 bg-blue-50 text-blue-700 rounded-lg font-medium">
              <i class="fas fa-users w-5 mr-2"></i> Volunteers
            </a>
            <a href="events.php" class="flex items-center py-2 px-4 mt-2 text-gray-600 hover:bg-blue-50 hover:text-blue-700 rounded-lg transition-colors">
              <i class="fas fa-calendar-alt w-5 mr-2"></i> Events
            </a>
            <a href="certificates.php" class="flex items-center py-2 px-4 mt-2 text-gray-600 hover:bg-blue-50 hover:text-blue-700 rounded-lg transition-colors">
              <i class="fas fa-certificate w-5 mr-2"></i> Certificates
            </a>
            <a href="reports.php" class="flex items-center py-2 px-4 mt-2 text-gray-600 hover:bg-blue-50 hover:text-blue-700 rounded-lg transition-colors">
              <i class="fas fa-chart-bar w-5 mr-2"></i> Reports
            </a>
          </nav>
        </div>
      </div>
    </div>
    
    <!-- Main Content -->
    <main class="flex-1 p-6">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800 flex items-center">
          <i class="fas fa-user text-blue-600 mr-2"></i>
          Volunteer Profile
        </h2>
        <div class="flex space-x-2">
          <a href="edit_volunteer.php?id=<?php echo $volunteer_id; ?>" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors flex items-center">
            <i class="fas fa-edit mr-1"></i> Edit Profile
          </a>
          <a href="volunteers.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors flex items-center">
            <i class="fas fa-arrow-left mr-1"></i> Back
          </a>
        </div>
      </div>
      
      <!-- Success/Error messages -->
      <?php if (isset($_SESSION['success_message'])): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-md flex items-center">
          <i class="fas fa-check-circle mr-2"></i>
          <p><?php echo htmlspecialchars($_SESSION['success_message']); 
                unset($_SESSION['success_message']); ?></p>
        </div>
      <?php endif; ?>
      
      <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-md flex items-center">
          <i class="fas fa-exclamation-circle mr-2"></i>
          <p><?php echo htmlspecialchars($_SESSION['error_message']); 
                unset($_SESSION['error_message']); ?></p>
        </div>
      <?php endif; ?>
      
      <!-- Volunteer Profile -->
      <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="bg-blue-600 text-white p-4">
          <h3 class="text-xl font-semibold">Personal Information</h3>
        </div>
        
        <div class="p-6">
          <div class="flex flex-col md:flex-row gap-6">
            <div class="flex-shrink-0 flex flex-col items-center">
              <div class="h-32 w-32 bg-blue-100 rounded-full flex items-center justify-center text-4xl font-bold text-blue-600 mb-3">
                <?php echo substr($volunteer['first_name'], 0, 1) . substr($volunteer['last_name'], 0, 1); ?>
              </div>
              <span class="px-3 py-1 rounded-full <?php echo $volunteer['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?> text-sm font-medium">
                <?php echo ucfirst($volunteer['status']); ?>
              </span>
            </div>
            <div class="flex-1">
              <h3 class="text-xl font-semibold text-gray-800 mb-2">
                <?php echo htmlspecialchars($volunteer['first_name'] . ' ' . $volunteer['last_name']); ?>
              </h3>
              <p class="text-gray-600 mb-4">
                <i class="fas fa-calendar-alt text-blue-500 mr-1"></i> 
                Joined: <?php echo date('M j, Y', strtotime($volunteer['join_date'])); ?>
              </p>
              
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                  <h4 class="font-medium text-gray-700 flex items-center mb-2">
                    <i class="fas fa-address-card text-blue-500 mr-1"></i> Contact Information
                  </h4>
                  <div class="space-y-2 text-gray-600">
                    <p class="flex items-center">
                      <i class="fas fa-envelope w-5 text-gray-400 mr-2"></i>
                      <?php echo htmlspecialchars($volunteer['email']); ?>
                    </p>
                    <p class="flex items-center">
                      <i class="fas fa-phone w-5 text-gray-400 mr-2"></i>
                      <?php echo htmlspecialchars($volunteer['phone'] ?: 'Not provided'); ?>
                    </p>
                    <p class="flex items-start">
                      <i class="fas fa-map-marker-alt w-5 text-gray-400 mr-2 mt-1"></i>
                      <span><?php echo htmlspecialchars($volunteer['address'] ?: 'Not provided'); ?></span>
                    </p>
                  </div>
                </div>
                <div>
                  <h4 class="font-medium text-gray-700 flex items-center mb-2">
                    <i class="fas fa-tools text-blue-500 mr-1"></i> Skills
                  </h4>
                  <div class="flex flex-wrap gap-2">
                    <?php if (empty($volunteer_skills)): ?>
                      <p class="text-gray-500">No skills listed</p>
                    <?php else: ?>
                      <?php foreach ($volunteer_skills as $skill): ?>
                        <span class="skill-badge inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                          <?php echo htmlspecialchars($skill['skill_name']); ?>
                        </span>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </div>
                  
                  <h4 class="font-medium text-gray-700 flex items-center mt-4 mb-2">
                    <i class="fas fa-heart text-blue-500 mr-1"></i> Interests
                  </h4>
                  <p class="text-gray-600">
                    <?php echo htmlspecialchars($volunteer['interests'] ?: 'No interests listed'); ?>
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Volunteer Statistics -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-md p-4 text-center">
          <div class="bg-blue-100 w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3">
            <i class="fas fa-calendar-check text-blue-600 text-xl"></i>
          </div>
          <h4 class="text-gray-500 mb-1">Total Events</h4>
          <p class="text-2xl font-bold text-blue-600"><?php echo $total_events; ?></p>
        </div>
        <div class="bg-white rounded-lg shadow-md p-4 text-center">
          <div class="bg-blue-100 w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3">
            <i class="fas fa-clock text-blue-600 text-xl"></i>
          </div>
          <h4 class="text-gray-500 mb-1">Total Hours</h4>
          <p class="text-2xl font-bold text-blue-600"><?php echo $total_hours ?: '0'; ?></p>
        </div>
        <div class="bg-white rounded-lg shadow-md p-4 text-center">
          <div class="bg-blue-100 w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3">
            <i class="fas fa-certificate text-blue-600 text-xl"></i>
          </div>
          <h4 class="text-gray-500 mb-1">Certificates</h4>
          <p class="text-2xl font-bold text-blue-600"><?php echo $cert_count; ?></p>
        </div>
      </div>
      
      <!-- Volunteer Activity Chart -->
      <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="bg-blue-600 text-white p-4">
          <h3 class="text-xl font-semibold">Activity Overview</h3>
        </div>
        <div class="p-6">
          <canvas id="activityChart" height="200"></canvas>
        </div>
      </div>
      
      <!-- Tabs Navigation -->
      <div class="mb-6">
        <div class="border-b border-gray-200">
          <nav class="flex -mb-px">
            <button class="tab-button text-blue-600 border-b-2 border-blue-600 py-4 px-6 font-medium focus:outline-none" data-tab="pending">
              <i class="fas fa-hourglass-half mr-1"></i> Pending Confirmation
              <?php if ($pending_confirmation && $pending_confirmation->num_rows > 0): ?>
                <span class="ml-1 bg-red-100 text-red-800 px-2 py-0.5 rounded-full text-xs"><?php echo $pending_confirmation->num_rows; ?></span>
              <?php endif; ?>
            </button>
            <button class="tab-button text-gray-500 hover:text-gray-700 py-4 px-6 font-medium focus:outline-none" data-tab="upcoming">
              <i class="fas fa-calendar-alt mr-1"></i> Upcoming Events
              <?php if ($registered_events && $registered_events->num_rows > 0): ?>
                <span class="ml-1 bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full text-xs"><?php echo $registered_events->num_rows; ?></span>
              <?php endif; ?>
            </button>
            <button class="tab-button text-gray-500 hover:text-gray-700 py-4 px-6 font-medium focus:outline-none" data-tab="completed">
              <i class="fas fa-check-circle mr-1"></i> Completed Events
              <?php if ($completed_events && $completed_events->num_rows > 0): ?>
                <span class="ml-1 bg-green-100 text-green-800 px-2 py-0.5 rounded-full text-xs"><?php echo $completed_events->num_rows; ?></span>
              <?php endif; ?>
            </button>
            <button class="tab-button text-gray-500 hover:text-gray-700 py-4 px-6 font-medium focus:outline-none" data-tab="missed">
              <i class="fas fa-times-circle mr-1"></i> Missed Events
              <?php if ($missed_events && $missed_events->num_rows > 0): ?>
                <span class="ml-1 bg-gray-100 text-gray-800 px-2 py-0.5 rounded-full text-xs"><?php echo $missed_events->num_rows; ?></span>
              <?php endif; ?>
            </button>
          </nav>
        </div>
      </div>
      
      <!-- Pending Attendance Confirmation -->
      <div id="pending-tab" class="tab-content active">
        <?php if ($pending_confirmation && $pending_confirmation->num_rows > 0): ?>
          <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                </div>
                <div class="ml-3">
                  <p class="text-sm text-yellow-700">
                    These events need attendance confirmation. Please confirm if the volunteer attended these events.
                  </p>
                </div>
              </div>
            </div>
            <div class="p-6">
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php while ($event = $pending_confirmation->fetch_assoc()): ?>
                  <div class="event-card bg-white border rounded-lg shadow-sm overflow-hidden">
                    <div class="bg-yellow-50 p-3 border-b">
                      <h4 class="font-medium text-gray-800"><?php echo htmlspecialchars($event['event_name']); ?></h4>
                      <p class="text-sm text-gray-500">
                        <i class="fas fa-calendar-day mr-1"></i> 
                        <?php echo date('M j, Y', strtotime($event['event_date'])); ?>
                      </p>
                      <p class="text-sm text-gray-500">
                        <i class="fas fa-clock mr-1"></i>
                        <?php echo date('g:i A', strtotime($event['start_time'])) . ' - ' . date('g:i A', strtotime($event['end_time'])); ?>
                      </p>
                    </div>
                    <div class="p-4">
                      <p class="text-sm text-gray-600 mb-3">
                        <span class="font-medium">Duration:</span> <?php echo $event['event_duration_hours']; ?> hours
                      </p>
                      <form method="POST" class="flex flex-col space-y-2">
                        <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                        <button type="submit" name="confirm_attendance" value="yes" class="bg-green-500 text-white px-3 py-2 rounded hover:bg-green-600 flex items-center justify-center">
                          <i class="fas fa-check mr-1"></i> Attended
                        </button>
                        <button type="submit" name="confirm_attendance" value="no" class="bg-red-500 text-white px-3 py-2 rounded hover:bg-red-600 flex items-center justify-center">
                          <i class="fas fa-times mr-1"></i> Did Not Attend
                        </button>
                      </form>
                    </div>
                  </div>
                <?php endwhile; ?>
              </div>
            </div>
          </div>
        <?php else: ?>
          <div class="bg-white rounded-lg shadow-md p-8 text-center">
            <div class="text-gray-400 mb-4">
              <i class="fas fa-check-circle text-5xl"></i>
            </div>
            <h3 class="text-xl font-medium text-gray-700 mb-2">No Pending Confirmations</h3>
            <p class="text-gray-500">All event attendances have been confirmed for this volunteer.</p>
          </div>
        <?php endif; ?>
      </div>
      
      <!-- Upcoming Events -->
      <div id="upcoming-tab" class="tab-content hidden">
        <?php if ($registered_events && $registered_events->num_rows > 0): ?>
          <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
            <div class="p-6">
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php while ($event = $registered_events->fetch_assoc()): ?>
                  <div class="event-card bg-white border rounded-lg shadow-sm overflow-hidden">
                    <div class="bg-blue-50 p-3 border-b">
                      <div class="flex justify-between items-start">
                        <h4 class="font-medium text-gray-800"><?php echo htmlspecialchars($event['event_name']); ?></h4>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium <?php 
                          echo $event['event_status'] === 'ongoing' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800'; 
                        ?>">
                          <?php echo ucfirst($event['event_status']); ?>
                        </span>
                      </div>
                      <p class="text-sm text-gray-500">
                        <i class="fas fa-calendar-day mr-1"></i> 
                        <?php echo date('M j, Y', strtotime($event['event_date'])); ?>
                      </p>
                      <p class="text-sm text-gray-500">
                        <i class="fas fa-clock mr-1"></i>
                        <?php echo date('g:i A', strtotime($event['start_time'])) . ' - ' . date('g:i A', strtotime($event['end_time'])); ?>
                      </p>
                    </div>
                    <div class="p-4">
                      <p class="text-sm text-gray-600 mb-3">
                        <span class="font-medium">Duration:</span> <?php echo $event['event_duration_hours']; ?> hours
                      </p>
                      <a href="view_event.php?id=<?php echo $event['event_id']; ?>" class="text-blue-600 hover:text-blue-800 text-sm flex items-center">
                        <i class="fas fa-eye mr-1"></i> View Event Details
                      </a>
                    </div>
                  </div>
                <?php endwhile; ?>
              </div>
            </div>
          </div>
        <?php else: ?>
          <div class="bg-white rounded-lg shadow-md p-8 text-center">
            <div class="text-gray-400 mb-4">
              <i class="fas fa-calendar-alt text-5xl"></i>
            </div>
            <h3 class="text-xl font-medium text-gray-700 mb-2">No Upcoming Events</h3>
            <p class="text-gray-500">This volunteer is not registered for any upcoming events.</p>
          </div>
        <?php endif; ?>
      </div>
      
      <!-- Completed Events -->
      <div id="completed-tab" class="tab-content hidden">
        <?php if ($completed_events && $completed_events->num_rows > 0): ?>
          <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
            <div class="p-6">
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php while ($event = $completed_events->fetch_assoc()): ?>
                  <div class="event-card bg-white border rounded-lg shadow-sm overflow-hidden">
                    <div class="bg-green-50 p-3 border-b">
                      <div class="flex justify-between items-start">
                        <h4 class="font-medium text-gray-800"><?php echo htmlspecialchars($event['event_name']); ?></h4>
                        <?php if ($event['certificate_generated']): ?>
                          <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            <i class="fas fa-certificate mr-1"></i> Certified
                          </span>
                        <?php endif; ?>
                      </div>
                      <p class="text-sm text-gray-500">
                        <i class="fas fa-calendar-day mr-1"></i> 
                        <?php echo date('M j, Y', strtotime($event['event_date'])); ?>
                      </p>
                      <p class="text-sm text-gray-500">
                        <i class="fas fa-clock mr-1"></i>
                        <?php echo date('g:i A', strtotime($event['start_time'])) . ' - ' . date('g:i A', strtotime($event['end_time'])); ?>
                      </p>
                    </div>
                    <div class="p-4">
                      <div class="flex justify-between items-center mb-3">
                        <p class="text-sm text-gray-600">
                          <span class="font-medium">Duration:</span> <?php echo $event['event_duration_hours']; ?> hours
                        </p>
                        <p class="text-sm text-gray-600">
                          <span class="font-medium">Hours:</span> <?php echo $event['hours_worked']; ?>
                        </p>
                      </div>
                      
                      <!-- Update Hours Form -->
                      <form method="POST" class="mb-3">
                        <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                        <div class="flex items-center space-x-2">
                          <input type="number" name="hours_worked" value="<?php echo $event['hours_worked']; ?>" min="0" step="0.5" 
                                 class="w-20 px-2 py-1 border border-gray-300 rounded text-sm">
                          <button type="submit" name="update_hours" class="bg-blue-500 text-white px-2 py-1 rounded text-sm hover:bg-blue-600">
                            Update
                          </button>
                        </div>
                      </form>
                      
                      <div class="flex justify-between items-center">
                        <a href="view_event.php?id=<?php echo $event['event_id']; ?>" class="text-blue-600 hover:text-blue-800 text-sm flex items-center">
                          <i class="fas fa-eye mr-1"></i> View Event
                        </a>
                        
                        <?php if (!$event['certificate_generated']): ?>
                          <form method="POST">
                            <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                            <button type="submit" name="generate_certificate" class="text-sm bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 flex items-center">
                              <i class="fas fa-certificate mr-1"></i> Generate Certificate
                            </button>
                          </form>
                        <?php else: ?>
                          <a href="view_certificate.php?volunteer_id=<?php echo $volunteer_id; ?>&event_id=<?php echo $event['event_id']; ?>" target="_blank" class="text-sm bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700 flex items-center">
                            <i class="fas fa-eye mr-1"></i> View Certificate
                          </a>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                <?php endwhile; ?>
              </div>
            </div>
          </div>
        <?php else: ?>
          <div class="bg-white rounded-lg shadow-md p-8 text-center">
            <div class="text-gray-400 mb-4">
              <i class="fas fa-calendar-check text-5xl"></i>
            </div>
            <h3 class="text-xl font-medium text-gray-700 mb-2">No Completed Events</h3>
            <p class="text-gray-500">This volunteer has not completed any events yet.</p>
          </div>
        <?php endif; ?>
      </div>
      
      <!-- Missed Events -->
      <div id="missed-tab" class="tab-content hidden">
        <?php if ($missed_events && $missed_events->num_rows > 0): ?>
          <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
            <div class="p-6">
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php while ($event = $missed_events->fetch_assoc()): ?>
                  <div class="event-card bg-white border rounded-lg shadow-sm overflow-hidden">
                    <div class="bg-red-50 p-3 border-b">
                      <h4 class="font-medium text-gray-800"><?php echo htmlspecialchars($event['event_name']); ?></h4>
                      <p class="text-sm text-gray-500">
                        <i class="fas fa-calendar-day mr-1"></i> 
                        <?php echo date('M j, Y', strtotime($event['event_date'])); ?>
                      </p>
                      <p class="text-sm text-gray-500">
                        <i class="fas fa-clock mr-1"></i>
                        <?php echo date('g:i A', strtotime($event['start_time'])) . ' - ' . date('g:i A', strtotime($event['end_time'])); ?>
                      </p>
                    </div>
                    <div class="p-4">
                      <p class="text-sm text-gray-600 mb-3">
                        <span class="font-medium">Duration:</span> <?php echo $event['event_duration_hours']; ?> hours
                      </p>
                      <div class="flex items-center">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 mr-2">
                          <i class="fas fa-times-circle mr-1"></i> Missed
                        </span>
                        <a href="view_event.php?id=<?php echo $event['event_id']; ?>" class="text-blue-600 hover:text-blue-800 text-sm flex items-center">
                          <i class="fas fa-eye mr-1"></i> View Event
                        </a>
                      </div>
                    </div>
                  </div>
                <?php endwhile; ?>
              </div>
            </div>
          </div>
        <?php else: ?>
          <div class="bg-white rounded-lg shadow-md p-8 text-center">
            <div class="text-gray-400 mb-4">
              <i class="fas fa-calendar-times text-5xl"></i>
            </div>
            <h3 class="text-xl font-medium text-gray-700 mb-2">No Missed Events</h3>
            <p class="text-gray-500">This volunteer has not missed any events.</p>
          </div>
        <?php endif; ?>
      </div>
    </main>
  </div>

  <script>
    $(document).ready(function() {
      // Mobile sidebar toggle
      $('#sidebar-toggle').click(function() {
        $('#mobile-sidebar').removeClass('hidden');
        $('#mobile-sidebar > div').removeClass('-translate-x-full');
      });
      
      $('#close-sidebar').click(function() {
        $('#mobile-sidebar').addClass('hidden');
        $('#mobile-sidebar > div').addClass('-translate-x-full');
      });
      
      // Tab navigation
      $('.tab-button').click(function() {
        const tabId = $(this).data('tab');
        
        // Update active tab button
        $('.tab-button').removeClass('text-blue-600 border-b-2 border-blue-600').addClass('text-gray-500 hover:text-gray-700');
        $(this).removeClass('text-gray-500 hover:text-gray-700').addClass('text-blue-600 border-b-2 border-blue-600');
        
        // Show active tab content
        $('.tab-content').removeClass('active').addClass('hidden');
        $('#' + tabId + '-tab').removeClass('hidden').addClass('active');
      });
      
      // Activity Chart
      const ctx = document.getElementById('activityChart').getContext('2d');
      const activityChart = new Chart(ctx, {
        type: 'bar',
        data: {
          labels: ['Upcoming', 'Completed', 'Missed', 'Pending'],
          datasets: [{
            label: 'Events',
            data: [
              <?php echo $registered_events ? $registered_events->num_rows : 0; ?>,
              <?php echo $completed_events ? $completed_events->num_rows : 0; ?>,
              <?php echo $missed_events ? $missed_events->num_rows : 0; ?>,
              <?php echo $pending_confirmation ? $pending_confirmation->num_rows : 0; ?>
            ],
            backgroundColor: [
              'rgba(59, 130, 246, 0.5)',
              'rgba(16, 185, 129, 0.5)',
              'rgba(239, 68, 68, 0.5)',
              'rgba(245, 158, 11, 0.5)'
            ],
            borderColor: [
              'rgba(59, 130, 246, 1)',
              'rgba(16, 185, 129, 1)',
              'rgba(239, 68, 68, 1)',
              'rgba(245, 158, 11, 1)'
            ],
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: {
              display: false
            },
            title: {
              display: true,
              text: 'Event Participation'
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                precision: 0
              }
            }
          }
        }
      });
    });
  </script>
</body>
</html>