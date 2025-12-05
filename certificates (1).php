<?php
session_start();
date_default_timezone_set('Asia/Kolkata');

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

// Create certificates table if it doesn't exist
$db->query("
    CREATE TABLE IF NOT EXISTS certificates (
        certificate_id INT AUTO_INCREMENT PRIMARY KEY,
        volunteer_id INT NOT NULL,
        event_id INT NOT NULL,
        certificate_type VARCHAR(100) NOT NULL,
        certificate_content TEXT,
        generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (volunteer_id) REFERENCES volunteers(volunteer_id),
        FOREIGN KEY (event_id) REFERENCES events(event_id)
    )
");

// Get all certificates for the admin's organization
$certificates = $db->query("
    SELECT c.*, v.first_name, v.last_name, e.event_name 
    FROM certificates c
    JOIN volunteers v ON c.volunteer_id = v.volunteer_id
    JOIN events e ON c.event_id = e.event_id
    WHERE e.admin_id = {$_SESSION['admin_id']}
    ORDER BY c.generated_at DESC
");

// Close connection
$db->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>VolunteerHub - Certificates</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    @layer utilities {
      .animate-fade-in {
        animation: fadeIn 0.8s ease-in-out;
      }
      @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
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
        <span class="hidden md:inline"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
        <a href="logout.php" class="bg-white text-indigo-600 px-3 py-1 rounded-lg text-sm hover:bg-indigo-100 transition-colors">
          Logout
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
          <h2 class="text-lg font-semibold text-indigo-700"><?php echo htmlspecialchars($_SESSION['organization']); ?></h2>
          <p class="text-sm text-gray-500">Admin Dashboard</p>
        </div>
        
        <nav class="mt-6">
          <a href="dashboard.php" class="block py-2 px-4 text-gray-600 hover:bg-indigo-50 hover:text-indigo-700 rounded-lg transition-colors">Dashboard</a>
          <a href="volunteers.php" class="block py-2 px-4 mt-2 text-gray-600 hover:bg-indigo-50 hover:text-indigo-700 rounded-lg transition-colors">Volunteers</a>
          <a href="events.php" class="block py-2 px-4 mt-2 text-gray-600 hover:bg-indigo-50 hover:text-indigo-700 rounded-lg transition-colors">Events</a>
          <a href="certificates.php" class="block py-2 px-4 mt-2 bg-indigo-50 text-indigo-700 rounded-lg font-medium">Certificates</a>
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
      <div class="absolute left-0 top-0 bottom-0 w-64 bg-white shadow-lg">
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
            <a href="dashboard.php" class="block py-2 px-4 text-gray-600 hover:bg-indigo-50 hover:text-indigo-700 rounded-lg transition-colors">Dashboard</a>
            <a href="volunteers.php" class="block py-2 px-4 mt-2 text-gray-600 hover:bg-indigo-50 hover:text-indigo-700 rounded-lg transition-colors">Volunteers</a>
            <a href="events.php" class="block py-2 px-4 mt-2 text-gray-600 hover:bg-indigo-50 hover:text-indigo-700 rounded-lg transition-colors">Events</a>
            <a href="certificates.php" class="block py-2 px-4 mt-2 bg-indigo-50 text-indigo-700 rounded-lg font-medium">Certificates</a>
          </nav>
        </div>
      </div>
    </div>
    
    <!-- Main Content -->
    <main class="flex-1 p-6">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Certificates</h2>
        <a href="generate_certificate.php" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors flex items-center">
          <i class="fas fa-plus mr-2"></i> Generate Certificate
        </a>
      </div>
      
      <?php if (isset($_SESSION['success_message'])): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 animate-fade-in">
          <p><?php echo htmlspecialchars($_SESSION['success_message']); 
              unset($_SESSION['success_message']); ?></p>
        </div>
      <?php endif; ?>
      
      <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 animate-fade-in">
          <p><?php echo htmlspecialchars($_SESSION['error_message']); 
              unset($_SESSION['error_message']); ?></p>
        </div>
      <?php endif; ?>
      
      <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Certificate ID</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Volunteer</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Event</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Generated On</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <?php if ($certificates->num_rows > 0): ?>
                <?php while ($cert = $certificates->fetch_assoc()): ?>
                <tr>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($cert['certificate_id']); ?></td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                      <div class="flex-shrink-0 h-10 w-10 bg-indigo-100 rounded-full flex items-center justify-center">
                        <span class="text-indigo-600 font-medium"><?php echo substr($cert['first_name'], 0, 1) . substr($cert['last_name'], 0, 1); ?></span>
                      </div>
                      <div class="ml-4">
                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($cert['first_name'] . ' ' . $cert['last_name']); ?></div>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($cert['event_name']); ?></td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <?php 
                    $type = str_replace('Certificate of ', '', $cert['certificate_type']);
                    echo ucfirst($type);
                    ?>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M j, Y g:i A', strtotime($cert['generated_at'])); ?></td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <a href="view_certificate.php?id=<?php echo $cert['certificate_id']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-4">View</a>
                    <a href="generate_certificate.php?volunteer_id=<?php echo $cert['volunteer_id']; ?>&event_id=<?php echo $cert['event_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-4">Regenerate</a>
                    <a href="delete_certificate.php?id=<?php echo $cert['certificate_id']; ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this certificate?')">Delete</a>
                  </td>
                </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">No certificates found. Generate one to get started.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>

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
  </script>
</body>
</html>