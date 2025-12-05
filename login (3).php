<?php
session_start();

// Database connection
$db = new mysqli('localhost', 'root', '', 'volunteer_management_system');

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $stmt = $db->prepare("SELECT admin_id, username, password, full_name, organization FROM admins WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();
        
        // Direct password comparison (not secure for production)
        if ($password === $admin['password']) {
            $_SESSION['admin_id'] = $admin['admin_id'];
            $_SESSION['username'] = $admin['username'];
            $_SESSION['full_name'] = $admin['full_name'];
            $_SESSION['organization'] = $admin['organization'];
            header('Location: dashboard.php');
            exit();
        } else {
            $error = "Invalid username or password";
        }
    } else {
        $error = "Invalid username or password";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>VolunteerHub - Admin Login</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .login-bg {
      background-image: url('https://i.pinimg.com/474x/ff/e3/92/ffe39246ee3ed9f00eeee957b5a8a454.jpg');
      background-size: cover;
      background-position: center;
    }
    .bg-gradient {
      background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    @keyframes pulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.05); }
    }
    .animate-fade-in {
      animation: fadeIn 0.8s ease-out forwards;
    }
    .animate-pulse-slow {
      animation: pulse 3s infinite;
    }
    .form-input-focus {
      transition: all 0.3s ease;
    }
    .form-input-focus:focus {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px -5px rgba(79, 70, 229, 0.2);
    }
  </style>
</head>
<body class="bg-indigo-50 min-h-screen flex items-center justify-center p-4">
  <div class="grid grid-cols-1 md:grid-cols-2 bg-white rounded-xl shadow-2xl overflow-hidden max-w-4xl w-full animate-fade-in">
    <div class="login-bg hidden md:block relative">
      <div class="absolute inset-0 bg-indigo-900 bg-opacity-70 flex flex-col justify-center items-center text-white p-12">
        <h2 class="text-3xl font-bold mb-6">Welcome Back!</h2>
        <p class="text-lg mb-8 text-center">Access your volunteer management dashboard and continue making a difference in your community.</p>
        <img src="https://i.pinimg.com/736x/d9/6f/a4/d96fa4f1cb07985232fde052b116fe53.jpg" alt="Volunteering - A Work of Heart" class="w-48 h-48 object-cover rounded-full border-4 border-white shadow-lg animate-pulse-slow">
        <p class="mt-8 text-sm italic">"Volunteers don't get paid, not because they're worthless, but because they're priceless."</p>
      </div>
    </div>
    
    <div class="p-8 md:p-12">
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-indigo-700 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
          </svg>
          VolunteerHub
        </h1>
        <p class="text-gray-600 mt-2">Admin Dashboard Login</p>
      </div>
      
      <?php if (isset($error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 px-4 py-3 rounded mb-4 animate-fade-in">
          <div class="flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <?php echo htmlspecialchars($error); ?>
          </div>
        </div>
      <?php endif; ?>
      
      <form method="POST" class="space-y-6">
        <div>
          <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
            </div>
            <input type="text" id="username" name="username" required 
                   class="w-full pl-10 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all form-input-focus">
          </div>
        </div>
        
        <div>
          <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
            </div>
            <input type="password" id="password" name="password" required 
                   class="w-full pl-10 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all form-input-focus">
            <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
              <button type="button" id="toggle-password" class="text-gray-400 hover:text-gray-600 focus:outline-none">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" id="eye-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" id="eye-off-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                </svg>
              </button>
            </div>
          </div>
        </div>
        
        <div class="flex items-center justify-between">
          <div class="flex items-center">
            <input id="remember-me" name="remember-me" type="checkbox" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
            <label for="remember-me" class="ml-2 block text-sm text-gray-700">
              Remember me
            </label>
          </div>
          
          <div class="text-sm">
            <a href="forgot_password.php" class="font-medium text-indigo-600 hover:text-indigo-500 transition-colors">
              Forgot password?
            </a>
          </div>
        </div>
        
        <div>
          <button type="submit" class="w-full bg-indigo-600 text-white px-4 py-3 rounded-lg hover:bg-indigo-700 transition-all duration-300 transform hover:-translate-y-1 hover:shadow-lg font-medium flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
            </svg>
            Sign In
          </button>
        </div>
      </form>
      
      <div class="mt-8 text-center">
        <p class="text-sm text-gray-600">Don't have an account?</p>
        <a href="register.php" class="mt-2 inline-block font-medium text-indigo-600 hover:text-indigo-500 transition-colors">
          Register as a new admin
        </a>
      </div>
      
      <div class="mt-6 text-center text-sm text-gray-500">
        <a href="index.html" class="text-indigo-600 hover:text-indigo-800 flex items-center justify-center transition-colors">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
          </svg>
          Back to Homepage
        </a>
      </div>
    </div>
  </div>
  
  <script>
    // Password visibility toggle
    const togglePassword = document.getElementById('toggle-password');
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eye-icon');
    const eyeOffIcon = document.getElementById('eye-off-icon');
    
    togglePassword.addEventListener('click', function() {
      const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
      passwordInput.setAttribute('type', type);
      
      // Toggle icons
      eyeIcon.classList.toggle('hidden');
      eyeOffIcon.classList.toggle('hidden');
    });
    
    // Form animation on load
    document.addEventListener('DOMContentLoaded', function() {
      const formElements = document.querySelectorAll('form > div');
      formElements.forEach((element, index) => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(20px)';
        setTimeout(() => {
          element.style.transition = 'all 0.5s ease';
          element.style.opacity = '1';
          element.style.transform = 'translateY(0)';
        }, 100 * (index + 1));
      });
    });
  </script>
</body>
</html>