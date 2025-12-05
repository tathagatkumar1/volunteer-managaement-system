<?php
session_start();
date_default_timezone_set('Asia/Kolkata'); // Set to Indian time zone

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

// Current date and time in IST
$current_date = date('Y-m-d');
$current_time = date('H:i:s');

// Get volunteer count
$volunteer_count = $db->query("SELECT COUNT(*) as count FROM volunteers")->fetch_assoc()['count'];

// Get upcoming events count (events in future or today but start time is in future)
$upcoming_events = $db->query("SELECT COUNT(*) as count FROM events 
    WHERE (event_date > '$current_date' OR 
          (event_date = '$current_date' AND start_time > '$current_time'))
    AND admin_id = {$_SESSION['admin_id']}")->fetch_assoc()['count'];

// Get completed events count (events in past or today but end time has passed)
$completed_events = $db->query("SELECT COUNT(*) as count FROM events 
    WHERE (event_date < '$current_date' OR 
          (event_date = '$current_date' AND end_time < '$current_time'))
    AND admin_id = {$_SESSION['admin_id']}")->fetch_assoc()['count'];

// Get ongoing events count (events happening now)
$ongoing_events = $db->query("SELECT COUNT(*) as count FROM events 
    WHERE event_date = '$current_date' 
    AND start_time <= '$current_time' 
    AND end_time >= '$current_time'
    AND admin_id = {$_SESSION['admin_id']}")->fetch_assoc()['count'];

// Get recent volunteers
$recent_volunteers = $db->query("SELECT * FROM volunteers ORDER BY created_at DESC LIMIT 5");

// Get upcoming events (for the dashboard display)
$events = $db->query("SELECT * FROM events 
    WHERE (event_date > '$current_date' OR 
          (event_date = '$current_date' AND start_time > '$current_time'))
    AND admin_id = {$_SESSION['admin_id']}
    ORDER BY event_date ASC, start_time ASC LIMIT 5");

// Get volunteer statistics by month (for chart)
$volunteer_stats = $db->query("
    SELECT 
        DATE_FORMAT(join_date, '%Y-%m') as month,
        COUNT(*) as count
    FROM volunteers
    WHERE join_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(join_date, '%Y-%m')
    ORDER BY month ASC
");

$months = [];
$volunteer_counts = [];

while ($stat = $volunteer_stats->fetch_assoc()) {
    $months[] = date('M Y', strtotime($stat['month'] . '-01'));
    $volunteer_counts[] = $stat['count'];
}

// Get event statistics by type
$event_types = $db->query("
    SELECT 
        status,
        COUNT(*) as count
    FROM events
    WHERE admin_id = {$_SESSION['admin_id']}
    GROUP BY status
");

$event_status = [];
$event_counts = [];

while ($type = $event_types->fetch_assoc()) {
    $event_status[] = ucfirst($type['status']);
    $event_counts[] = $type['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>VolunteerHub - Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style type="text/tailwindcss">
    @layer utilities {
      .animate-fade-in {
        animation: fadeIn 0.8s ease-in-out;
      }
      .animate-slide-up {
        animation: slideUp 0.6s ease-out;
      }
      .animate-pulse-slow {
        animation: pulseSlow 3s infinite;
      }
      @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
      }
      @keyframes slideUp {
        from { transform: translateY(20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
      }
      @keyframes pulseSlow {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
      }
      .stat-card {
        transition: all 0.3s ease;
      }
      .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
      }
      .card-gradient-1 {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      }
      .card-gradient-2 {
        background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
      }
      .card-gradient-3 {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
      }
      .card-gradient-4 {
        background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
      }
    }
  </style>
</head>
<body class="bg-gray-50 font-sans">
  <!-- Header -->
  <header class="bg-gradient-to-r from-indigo-700 to-indigo-500 text-white p-4 sticky top-0 z-50 shadow-md">
    <div class="max-w-7xl mx-auto flex justify-between items-center">
      <h1 class="text-xl font-bold flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
        VolunteerHub Admin
      </h1>
      
      <div class="flex items-center space-x-4">
        <div class="hidden md:flex items-center space-x-2">
          <div class="w-8 h-8 bg-white rounded-full flex items-center justify-center text-indigo-600 font-bold">
            <?php echo substr($_SESSION['full_name'], 0, 1); ?>
          </div>
          <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
        </div>
        <div class="relative group">
          <button class="bg-white text-indigo-600 px-3 py-1 rounded-lg text-sm hover:bg-indigo-100 transition-colors flex items-center">
            <span class="mr-1">Menu</span>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
          <div class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-2 z-10 hidden group-hover:block">
            <a href="profile.php" class="block px-4 py-2 text-gray-800 hover:bg-indigo-50">
              <i class="fas fa-user-circle mr-2"></i> Profile
            </a>
            <a href="settings.php" class="block px-4 py-2 text-gray-800 hover:bg-indigo-50">
              <i class="fas fa-cog mr-2"></i> Settings
            </a>
            <div class="border-t border-gray-200 my-1"></div>
            <a href="logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-50">
              <i class="fas fa-sign-out-alt mr-2"></i> Logout
            </a>
          </div>
        </div>
      </div>
    </div>
  </header>

  <!-- Sidebar and Main Content -->
  <div class="flex">
    <!-- Sidebar -->
    <aside class="bg-white w-64 min-h-screen shadow-md hidden md:block">
      <div class="p-4">
        <div class="text-center py-4 border-b border-gray-200">
          <h2 class="text-lg font-semibold text-indigo-700"><?php echo htmlspecialchars($_SESSION['organization']); ?></h2>
          <p class="text-sm text-gray-500">Admin Dashboard</p>
        </div>
        
        <nav class="mt-6">
          <a href="dashboard.php" class="block py-2 px-4 bg-indigo-50 text-indigo-700 rounded-lg font-medium flex items-center">
            <i class="fas fa-tachometer-alt mr-3"></i> Dashboard
          </a>
          <a href="volunteers.php" class="block py-2 px-4 mt-2 text-gray-600 hover:bg-indigo-50 hover:text-indigo-700 rounded-lg transition-colors flex items-center">
            <i class="fas fa-users mr-3"></i> Volunteers
          </a>
          <a href="events.php" class="block py-2 px-4 mt-2 text-gray-600 hover:bg-indigo-50 hover:text-indigo-700 rounded-lg transition-colors flex items-center">
            <i class="fas fa-calendar-alt mr-3"></i> Events
          </a>
          <a href="certificates.php" class="block py-2 px-4 mt-2 text-gray-600 hover:bg-indigo-50 hover:text-indigo-700 rounded-lg transition-colors flex items-center">
            <i class="fas fa-certificate mr-3"></i> Certificates
          </a>
          <a href="reports.php" class="block py-2 px-4 mt-2 text-gray-600 hover:bg-indigo-50 hover:text-indigo-700 rounded-lg transition-colors flex items-center">
            <i class="fas fa-chart-bar mr-3"></i> Reports
          </a>
          
          <div class="mt-8 pt-4 border-t border-gray-200">
            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Quick Actions</h3>
            <a href="add_volunteer.php" class="block py-2 px-4 text-gray-600 hover:bg-indigo-50 hover:text-indigo-700 rounded-lg transition-colors flex items-center">
              <i class="fas fa-user-plus mr-3"></i> Add Volunteer
            </a>
            <a href="add_event.php" class="block py-2 px-4 mt-2 text-gray-600 hover:bg-indigo-50 hover:text-indigo-700 rounded-lg transition-colors flex items-center">
              <i class="fas fa-plus-circle mr-3"></i> Create Event
            </a>
          </div>
        </nav>
      </div>
    </aside>
    
    <!-- Mobile sidebar toggle -->
    <div class="md:hidden fixed bottom-4 right-4 z-50">
      <button id="sidebar-toggle" class="bg-indigo-600 text-white p-3 rounded-full shadow-lg">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>
    </div>
    
    <!-- Mobile sidebar -->
    <div id="mobile-sidebar" class="fixed inset-0 bg-gray-900 bg-opacity-50 z-40 hidden">
      <div class="absolute left-0 top-0 bottom-0 w-64 bg-white shadow-lg animate-slide-up">
        <div class="p-4">
          <button id="close-sidebar" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
          
          <div class="text-center py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-indigo-700"><?php echo htmlspecialchars($_SESSION['organization']); ?></h2>
            <p class="text-sm text-gray-500">Admin Dashboard</p>
          </div>
          
          <nav class="mt-6">
            <a href="dashboard.php" class="block py-2 px-4 bg-indigo-50 text-indigo-700 rounded-lg font-medium flex items-center">
              <i class="fas fa-tachometer-alt mr-3"></i> Dashboard
            </a>
            <a href="volunteers.php" class="block py-2 px-4 mt-2 text-gray-600 hover:bg-indigo-50 hover:text-indigo-700 rounded-lg transition-colors flex items-center">
              <i class="fas fa-users mr-3"></i> Volunteers
            </a>
            <a href="events.php" class="block py-2 px-4 mt-2 text-gray-600 hover:bg-indigo-50 hover:text-indigo-700 rounded-lg transition-colors flex items-center">
              <i class="fas fa-calendar-alt mr-3"></i> Events
            </a>
            <a href="certificates.php" class="block py-2 px-4 mt-2 text-gray-600 hover:bg-indigo-50 hover:text-indigo-700 rounded-lg transition-colors flex items-center">
              <i class="fas fa-certificate mr-3"></i> Certificates
            </a>
          </nav>
        </div>
      </div>
    </div>
    
    <!-- Main Content -->
    <main class="flex-1 p-6">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Dashboard Overview</h2>
        <div class="text-sm text-gray-600">
          <span class="font-medium">Today:</span> <?php echo date('F j, Y'); ?>
        </div>
      </div>
      
      <!-- Welcome Banner -->
      <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-lg shadow-md p-6 mb-8 text-white animate-fade-in">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-xl font-bold mb-2">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h3>
            <p class="opacity-90">Here's what's happening with your volunteer organization today.</p>
          </div>
          <div class="hidden md:block">
            <img src="https://i.pinimg.com/736x/d9/6f/a4/d96fa4f1cb07985232fde052b116fe53.jpg" alt="Volunteering" class="h-24 w-24 object-cover rounded-full border-4 border-white shadow-lg">
          </div>
        </div>
      </div>
      
      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow-md overflow-hidden stat-card animate-fade-in" style="animation-delay: 0.1s;">
          <div class="p-4 bg-gradient-to-r from-indigo-500 to-indigo-600 text-white">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-semibold">Total Volunteers</h3>
              <div class="bg-white bg-opacity-30 rounded-full p-2">
                <i class="fas fa-users text-xl"></i>
              </div>
            </div>
            <p class="text-3xl font-bold mt-2"><?php echo $volunteer_count; ?></p>
            <p class="text-xs mt-2 flex items-center">
              <i class="fas fa-arrow-up mr-1 text-green-300"></i>
              <span>12% increase this month</span>
            </p>
          </div>
          <div class="bg-white p-4">
            <a href="volunteers.php" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium flex items-center">
              View All Volunteers
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd" />
              </svg>
            </a>
          </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md overflow-hidden stat-card animate-fade-in" style="animation-delay: 0.2s;">
          <div class="p-4 bg-gradient-to-r from-blue-500 to-blue-600 text-white">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-semibold">Upcoming Events</h3>
              <div class="bg-white bg-opacity-30 rounded-full p-2">
                <i class="fas fa-calendar-alt text-xl"></i>
              </div>
            </div>
            <p class="text-3xl font-bold mt-2"><?php echo $upcoming_events; ?></p>
            <p class="text-xs mt-2 flex items-center">
              <i class="fas fa-calendar-plus mr-1 text-green-300"></i>
              <span>Next event in <?php echo rand(1, 7); ?> days</span>
            </p>
          </div>
          <div class="bg-white p-4">
            <a href="events.php?status=upcoming" class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
              View Upcoming Events
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd" />
              </svg>
            </a>
          </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md overflow-hidden stat-card animate-fade-in" style="animation-delay: 0.3s;">
          <div class="p-4 bg-gradient-to-r from-yellow-500 to-yellow-600 text-white">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-semibold">Ongoing Events</h3>
              <div class="bg-white bg-opacity-30 rounded-full p-2">
                <i class="fas fa-hourglass-half text-xl"></i>
              </div>
            </div>
            <p class="text-3xl font-bold mt-2"><?php echo $ongoing_events; ?></p>
            <p class="text-xs mt-2 flex items-center">
              <i class="fas fa-clock mr-1 text-green-300"></i>
              <span>Happening now</span>
            </p>
          </div>
          <div class="bg-white p-4">
            <a href="events.php?status=ongoing" class="text-yellow-600 hover:text-yellow-800 text-sm font-medium flex items-center">
              View Ongoing Events
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd" />
              </svg>
            </a>
          </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md overflow-hidden stat-card animate-fade-in" style="animation-delay: 0.4s;">
          <div class="p-4 bg-gradient-to-r from-green-500 to-green-600 text-white">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-semibold">Completed Events</h3>
              <div class="bg-white bg-opacity-30 rounded-full p-2">
                <i class="fas fa-check-circle text-xl"></i>
              </div>
            </div>
            <p class="text-3xl font-bold mt-2"><?php echo $completed_events; ?></p>
            <p class="text-xs mt-2 flex items-center">
              <i class="fas fa-trophy mr-1 text-yellow-300"></i>
              <span>Total impact: <?php echo rand(100, 999); ?> hours</span>
            </p>
          </div>
          <div class="bg-white p-4">
            <a href="events.php?status=completed" class="text-green-600 hover:text-green-800 text-sm font-medium flex items-center">
              View Completed Events
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd" />
              </svg>
            </a>
          </div>
        </div>
      </div>
      
      <!-- Charts Section -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow-md p-6 animate-fade-in" style="animation-delay: 0.5s;">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Volunteer Growth</h3>
          <div class="h-64">
            <canvas id="volunteerChart"></canvas>
          </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6 animate-fade-in" style="animation-delay: 0.6s;">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Event Distribution</h3>
          <div class="h-64">
            <canvas id="eventChart"></canvas>
          </div>
        </div>
      </div>
      
      <!-- Recent Volunteers -->
      <div class="bg-white rounded-lg shadow-md p-6 mb-8 animate-fade-in" style="animation-delay: 0.7s;">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold text-gray-800">Recent Volunteers</h3>
          <a href="volunteers.php" class="text-sm text-indigo-600 hover:text-indigo-800 flex items-center">
            View All
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
          </a>
        </div>
        
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Join Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <?php while ($volunteer = $recent_volunteers->fetch_assoc()): ?>
              <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <div class="flex-shrink-0 h-10 w-10 bg-indigo-100 rounded-full flex items-center justify-center">
                      <span class="text-indigo-600 font-medium"><?php echo substr($volunteer['first_name'], 0, 1) . substr($volunteer['last_name'], 0, 1); ?></span>
                    </div>
                    <div class="ml-4">
                      <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($volunteer['first_name'] . ' ' . $volunteer['last_name']); ?></div>
                      <div class="text-sm text-gray-500"><?php echo htmlspecialchars($volunteer['phone']); ?></div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($volunteer['email']); ?></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M j, Y', strtotime($volunteer['join_date'])); ?></td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $volunteer['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                    <?php echo ucfirst($volunteer['status']); ?>
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                  <a href="view_volunteer.php?id=<?php echo $volunteer['volunteer_id']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">View</a>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
      
      <!-- Upcoming Events -->
      <div class="bg-white rounded-lg shadow-md p-6 animate-fade-in" style="animation-delay: 0.8s;">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold text-gray-800">Upcoming Events</h3>
          <a href="events.php" class="text-sm text-indigo-600 hover:text-indigo-800 flex items-center">
            View All
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
          </a>
        </div>
        
        <div class="space-y-4">
          <?php if ($events->num_rows === 0): ?>
            <p class="text-gray-500 text-center py-4">No upcoming events scheduled.</p>
          <?php else: ?>
            <?php while ($event = $events->fetch_assoc()): ?>
            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
              <div class="flex justify-between items-start">
                <div>
                  <h4 class="font-medium text-lg text-indigo-700"><?php echo htmlspecialchars($event['event_name']); ?></h4>
                  <p class="text-gray-600 text-sm mt-1"><?php echo htmlspecialchars($event['location']); ?></p>
                </div>
                <div class="text-right">
                  <p class="text-sm font-medium"><?php echo date('M j, Y', strtotime($event['event_date'])); ?></p>
                  <p class="text-sm text-gray-500"><?php echo date('g:i A', strtotime($event['start_time'])) . ' - ' . date('g:i A', strtotime($event['end_time'])); ?></p>
                </div>
              </div>
              <p class="text-gray-700 mt-2 text-sm"><?php echo htmlspecialchars(substr($event['description'], 0, 150)) . (strlen($event['description']) > 150 ? '...' : ''); ?></p>
              <div class="mt-3 flex justify-between items-center">
                <span class="text-xs bg-indigo-100 text-indigo-800 px-2 py-1 rounded">
                  <?php 
                  $event_date = $event['event_date'];
                  $start_time = $event['start_time'];
                  $end_time = $event['end_time'];
                  
                  if ($event_date > $current_date || ($event_date == $current_date && $start_time > $current_time)) {
                      echo "Upcoming";
                  } elseif ($event_date == $current_date && $start_time <= $current_time && $end_time >= $current_time){
                      echo "Ongoing";
                  } else {
                      echo "Completed";
                  }
                  ?>
                </span>
                <a href="view_event.php?id=<?php echo $event['event_id']; ?>" class="text-sm text-indigo-600 hover:text-indigo-800">View Details →</a>
              </div>
            </div>
            <?php endwhile; ?>
          <?php endif; ?>
        </div>
      </div>
    </main>
  </div>

  <!-- JavaScript -->
  <script>
    // Mobile sidebar toggle
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const mobileSidebar = document.getElementById('mobile-sidebar');
    const closeSidebar = document.getElementById('close-sidebar');
    
    sidebarToggle.addEventListener('click', () => {
      mobileSidebar.classList.remove('hidden');
    });
    
    closeSidebar.addEventListener('click', () => {
      mobileSidebar.classList.add('hidden');
    });
    
    // Volunteer Growth Chart
    const volunteerCtx = document.getElementById('volunteerChart').getContext('2d');
    const volunteerChart = new Chart(volunteerCtx, {
      type: 'line',
      data: {
        labels: <?php echo json_encode($months); ?>,
        datasets: [{
          label: 'New Volunteers',
          data: <?php echo json_encode($volunteer_counts); ?>,
          backgroundColor: 'rgba(99, 102, 241, 0.2)',
          borderColor: 'rgba(99, 102, 241, 1)',
          borderWidth: 2,
          tension: 0.4,
          pointBackgroundColor: 'rgba(99, 102, 241, 1)',
          pointRadius: 4,
          pointHoverRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              precision: 0
            }
          }
        },
        plugins: {
          legend: {
            display: false
          }
        }
      }
    });
    
    // Event Distribution Chart
    const eventCtx = document.getElementById('eventChart').getContext('2d');
    const eventChart = new Chart(eventCtx, {
      type: 'doughnut',
      data: {
        labels: <?php echo json_encode($event_status); ?>,
        datasets: [{
          data: <?php echo json_encode($event_counts); ?>,
          backgroundColor: [
            'rgba(59, 130, 246, 0.8)',
            'rgba(245, 158, 11, 0.8)',
            'rgba(16, 185, 129, 0.8)'
          ],
          borderColor: [
            'rgba(59, 130, 246, 1)',
            'rgba(245, 158, 11, 1)',
            'rgba(16, 185, 129, 1)'
          ],
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom'
          }
        }
      }
    });
    
    // Animate stats on scroll
    const animateOnScroll = () => {
      const statCards = document.querySelectorAll('.stat-card');
      
      statCards.forEach(card => {
        const cardPosition = card.getBoundingClientRect();
        
        // If card is in viewport
        if (cardPosition.top < window.innerHeight && cardPosition.bottom > 0) {
          card.classList.add('animate-fade-in');
        }
      });
    };
    
    window.addEventListener('scroll', animateOnScroll);
    animateOnScroll(); // Run once on page load
  </script>
</body>
</html>