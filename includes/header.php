<?php
// includes/header.php
ob_start(); 
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../auth/login.php");
    exit();
}

$profile_link = '';
if ($_SESSION['role'] === 'admin') {
    $profile_link = '../admin/index.php'; 
} elseif ($_SESSION['role'] === 'nasabah') {
    $profile_link = '../nasabah/index.php'; 
}

$display_name = htmlspecialchars($_SESSION['nama_nasabah'] ?? $_SESSION['username']);
$role_display = ucfirst($_SESSION['role']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Sampah - <?php echo $role_display; ?> Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    
     <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
        }
        /* CSS agar konten utama mengalir dengan benar di sebelah sidebar pada desktop */
        #page-content-wrapper {
            width: 100%;
        }
        @media (min-width: 768px) {
            #page-content-wrapper {
                margin-left: 16rem; /* 64 * 0.25rem = 16rem (w-64) */
                width: calc(100% - 16rem);
            }
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex">
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin'): ?>
        <div id="sidebar-wrapper" class="fixed inset-y-0 left-0 bg-gray-200 text-purple-700 w-64 p-4 space-y-6 transform -translate-x-full md:translate-x-0 transition-transform duration-200 ease-in-out z-50">
            <div class="flex items-center justify-between border-b border-purple-700 pb-4">
                <h2 class="text-lg font-bold">Superadmin</h2>
            </div>
           <nav class="flex flex-col space-y-2">
        <a href="../superadmin/index.php" class="py-2.5 px-4 rounded-lg transition-colors duration-200 hover:purple-700 hover:text-purple <?php echo ($current_page == 'dashboard') ? 'bg-purple-700 text-white' : 'text-purple-600'; ?>">
            <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
        </a>
        <a href="../superadmin/manage_tps.php" class="py-2.5 px-4 rounded-lg transition-colors duration-200 hover:bg-purple-700 hover:text-purple <?php echo ($current_page == 'manage_tps') ? 'bg-purple-700 text-white' : 'text-purple-600'; ?>">
            <i class="fas fa-warehouse mr-2"></i> Manajemen Bank Sampah
        </a>
        <a href="../superadmin/manage_nasabah.php" class="py-2.5 px-4 rounded-lg transition-colors duration-200 hover:bg-purple-700 hover:text-purple <?php echo ($current_page == 'manage_nasabah') ? 'bg-purple-700 text-white' : 'text-purple-600'; ?>">
            <i class="fas fa-users mr-2"></i> Manajemen Nasabah
        </a>
        <a href="../superadmin/manage_superadmin.php" class="py-2.5 px-4 rounded-lg transition-colors duration-200 hover:bg-purple-700 hover:text-purple <?php echo ($current_page == 'manage_superadmin') ? 'bg-purple-700 text-white' : 'text-purple-600'; ?>">
            <i class="fas fa-user-shield mr-2"></i> Manajemen Superadmin
        </a>
         <a href="../superadmin/manage_admin.php" class="py-2.5 px-4 rounded-lg transition-colors duration-200 hover:bg-purple-700 hover:text-purple <?php echo ($current_page == 'manage_superadmin') ? 'bg-grapurpley-700 text-white' : 'text-purple-600'; ?>">
            <i class="fas fa-user-shield mr-2"></i> Manajemen Admin
        </a>
        <a href="../superadmin/laporan.php" class="py-2.5 px-4 rounded-lg transition-colors duration-200 hover:bg-purple-700 hover:text-purple <?php echo ($current_page == 'laporan') ? 'bg-purple-700 text-white' : 'text-purple-600'; ?>">
            <i class="fas fa-chart-line mr-2"></i> Laporan Global
        </a>
        <a href="../auth/logout.php" class="py-2.5 px-4 rounded-lg transition-colors duration-200 hover:bg-red-600 hover:text-white text-red-400 mt-auto">
            <i class="fas fa-sign-out-alt mr-2"></i> Logout
        </a>
    </nav>
        </div>
        <?php endif; ?>

        <?php
        if ($_SESSION['role'] == 'admin') {
            include 'sidebar_admin.php';
        } elseif ($_SESSION['role'] == 'nasabah') {
            include 'sidebar_nasabah.php';
        }
        ?>

        <div id="mobile-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden md:hidden"></div>

        <div id="page-content-wrapper" class="flex-1 min-h-screen flex flex-col transition-all duration-300">
            <nav class="bg-white shadow-sm p-4 flex justify-between items-center border-b border-gray-200 h-16 sticky top-0 z-30">
                <button id="sidebarToggle" class="text-gray-500 hover:text-gray-700 md:hidden focus:outline-none p-2">
                    <i class="fas fa-bars text-xl"></i>
                </button>

                <a class="text-lg font-bold text-purple-700 hidden md:block ml-4" href="index.php">Bank Sampah</a>
                
                <div class="flex items-center space-x-4 ml-auto">
                    <a href="<?php echo htmlspecialchars($profile_link); ?>" class="flex items-center text-gray-700 hover:text-gray-900 transition-colors duration-200">
                        <span class="font-medium hidden sm:block"><?php echo $display_name; ?></span>
                        <span class="text-sm text-gray-500 ml-2 hidden sm:block">(<?php echo $role_display; ?>)</span>
                         <i class="fas fa-user-circle text-2xl sm:hidden"></i>
                    </a>
                    
                    <a href="../auth/logout.php" class="bg-red-500 text-white px-3 py-2 rounded-md hover:bg-red-600 transition-colors duration-200 text-sm font-semibold">
                        Logout
                    </a>
                </div>
            </nav>

            <main class="flex-grow p-4 md:p-6 bg-gray-100 overflow-x-hidden">