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

// Initialize variables
$errors = [];
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $skills = trim($_POST['skills']);
    $interests = trim($_POST['interests']);
    $status = $_POST['status'];
    $join_date = date('Y-m-d'); // Current date as join date

    // Validate inputs
    if (empty($first_name)) {
        $errors['first_name'] = 'First name is required';
    }
    if (empty($last_name)) {
        $errors['last_name'] = 'Last name is required';
    }
    
    // Email validation
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    } else {
        // Check if email already exists
        $stmt = $db->prepare("SELECT email FROM volunteers WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors['email'] = 'Email already exists';
        }
        $stmt->close();
    }
    
    // Phone validation (10 digits, optional)
    if (!empty($phone) && !preg_match('/^[0-9]{10}$/', $phone)) {
        $errors['phone'] = 'Phone number must be 10 digits';
    }
    
    // Skills validation (mandatory)
    if (empty($skills)) {
        $errors['skills'] = 'Skills are required';
    }

    // If no errors, insert into database
    if (empty($errors)) {
        $stmt = $db->prepare("INSERT INTO volunteers (first_name, last_name, email, phone, address, skills, interests, status, join_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssss", $first_name, $last_name, $email, $phone, $address, $skills, $interests, $status, $join_date);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Volunteer added successfully!';
            header("Location: volunteers.php");
            exit();
        } else {
            $errors['database'] = 'Error adding volunteer: ' . $db->error;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>VolunteerHub - Add New Volunteer</title>
  <script src="https://cdn.tailwindcss.com"></script>
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
          <a href="volunteers.php" class="block py-2 px-4 mt-2 bg-indigo-50 text-indigo-700 rounded-lg font-medium">Volunteers</a>
          <a href="events.php" class="block py-2 px-4 mt-2 text-gray-600 hover:bg-indigo-50 hover:text-indigo-700 rounded-lg transition-colors">Events</a>
          <a href="certificates.php" class="block py-2 px-4 mt-2 text-gray-600 hover:bg-indigo-50 hover:text-indigo-700 rounded-lg transition-colors">Certificates</a>
          <a href="reports.php" class="block py-2 px-4 mt-2 text-gray-600 hover:bg-indigo-50 hover:text-indigo-700 rounded-lg transition-colors">Reports</a>
        </nav>
      </div>
    </aside>
    
    <!-- Main Content -->
    <main class="flex-1 p-6">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Add New Volunteer</h2>
        <a href="volunteers.php" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
          Back to Volunteers
        </a>
      </div>
      
      <!-- Error messages -->
      <?php if (!empty($errors['database'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
          <?php echo htmlspecialchars($errors['database']); ?>
        </div>
      <?php endif; ?>
      
      <!-- Add Volunteer Form -->
      <form method="POST" class="bg-white rounded-lg shadow-md p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- First Name -->
          <div>
            <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name ?? ''); ?>" 
                   class="w-full px-4 py-2 border <?php echo isset($errors['first_name']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <?php if (isset($errors['first_name'])): ?>
              <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['first_name']); ?></p>
            <?php endif; ?>
          </div>
          
          <!-- Last Name -->
          <div>
            <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($last_name ?? ''); ?>" 
                   class="w-full px-4 py-2 border <?php echo isset($errors['last_name']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <?php if (isset($errors['last_name'])): ?>
              <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['last_name']); ?></p>
            <?php endif; ?>
          </div>
          
          <!-- Email -->
          <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" 
                   class="w-full px-4 py-2 border <?php echo isset($errors['email']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <?php if (isset($errors['email'])): ?>
              <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['email']); ?></p>
            <?php endif; ?>
          </div>
          
          <!-- Phone -->
          <div>
            <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
            <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($phone ?? ''); ?>" 
                   class="w-full px-4 py-2 border <?php echo isset($errors['phone']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                   placeholder="10 digit number">
            <?php if (isset($errors['phone'])): ?>
              <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['phone']); ?></p>
            <?php endif; ?>
          </div>
          
          <!-- Address -->
          <div class="md:col-span-2">
            <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
            <textarea id="address" name="address" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"><?php echo htmlspecialchars($address ?? ''); ?></textarea>
          </div>
          
          <!-- Skills -->
          <div>
            <label for="skills" class="block text-sm font-medium text-gray-700 mb-1">Skills *</label>
            <textarea id="skills" name="skills" rows="3" class="w-full px-4 py-2 border <?php echo isset($errors['skills']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"><?php echo htmlspecialchars($skills ?? ''); ?></textarea>
            <p class="mt-1 text-sm text-gray-500">Comma separated list of skills</p>
            <?php if (isset($errors['skills'])): ?>
              <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['skills']); ?></p>
            <?php endif; ?>
          </div>
          
          <!-- Interests -->
          <div>
            <label for="interests" class="block text-sm font-medium text-gray-700 mb-1">Interests</label>
            <textarea id="interests" name="interests" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"><?php echo htmlspecialchars($interests ?? ''); ?></textarea>
            <p class="mt-1 text-sm text-gray-500">Comma separated list of interests</p>
          </div>
          
          <!-- Status -->
          <div>
            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status *</label>
            <select id="status" name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
              <option value="active" <?php echo ($status ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
              <option value="inactive" <?php echo ($status ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
          </div>
        </div>
        
        <div class="mt-6">
          <button type="submit" class="bg-indigo-600 text-white px-6 py-3 rounded-lg hover:bg-indigo-700 transition-colors font-medium">
            Add Volunteer
          </button>
        </div>
      </form>
    </main>
  </div>
</body>
</html>