<?php
/**
 * GAIMS - Main Header File
 * Include this at the top of all pages
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
$current_module = basename(dirname($_SERVER['PHP_SELF']));

// Get user info if logged in
$logged_in = isset($_SESSION['user_id']);
$username = $_SESSION['username'] ?? '';
$role = $_SESSION['role'] ?? '';

// Site configuration
$site_name = "Greenhill Academy";
$site_title = isset($page_title) ? $page_title . " - " . $site_name : $site_name;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $site_title; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/master.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/responsive.css">
    
    <style>
        /* Additional styles for header */
        .top-bar {
            background: var(--dark);
            color: white;
            padding: 8px 0;
            font-size: 13px;
        }
        .top-bar a {
            color: white;
            text-decoration: none;
        }
        .top-bar a:hover {
            color: var(--primary-gold);
        }
        .navbar {
            background: white;
            padding: 15px 0;
            box-shadow: var(--shadow-sm);
        }
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .navbar-brand img {
            height: 50px;
        }
        .brand-text h1 {
            font-size: 20px;
            margin: 0;
            color: var(--primary-green);
        }
        .brand-text p {
            font-size: 10px;
            margin: 0;
            color: var(--gray);
        }
        .nav-link {
            font-weight: 500;
            color: var(--gray-dark);
        }
        .nav-link:hover, .nav-link.active {
            color: var(--primary-green);
        }
        .btn-apply {
            background: var(--gradient-primary);
            color: white;
            border-radius: 30px;
            padding: 8px 25px;
        }
        .footer {
            background: var(--dark);
            color: var(--gray-light);
            padding: 60px 0 20px;
            margin-top: 50px;
        }
        .footer a {
            color: var(--gray-light);
            text-decoration: none;
        }
        .footer a:hover {
            color: var(--primary-green);
        }
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 45px;
            height: 45px;
            background: var(--primary-green);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
            z-index: 999;
        }
        .back-to-top.active {
            opacity: 1;
            visibility: visible;
        }
    </style>
</head>
<body>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script src="<?php echo SITE_URL; ?>assets/js/main.js"></script>
<script src="<?php echo SITE_URL; ?>assets/js/smooth-scroll.js"></script>
<script src="<?php echo SITE_URL; ?>assets/js/custom.js"></script>

<script>
    AOS.init({
        duration: 1000,
        once: true,
        offset: 100
    });
</script>
<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // Global SweetAlert functions
    window.showSuccess = function(message) {
        Swal.fire({
            title: 'Success!',
            text: message,
            icon: 'success',
            timer: 3000,
            showConfirmButton: false
        });
    };
    
    window.showError = function(message) {
        Swal.fire({
            title: 'Error!',
            text: message,
            icon: 'error',
            confirmButtonColor: '#3085d6'
        });
    };
    
    window.showWarning = function(title, message, callback) {
        Swal.fire({
            title: title,
            text: message,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, proceed!'
        }).then((result) => {
            if (result.isConfirmed && callback) {
                callback();
            }
        });
    };
    
    window.showInfo = function(message) {
        Swal.fire({
            title: 'Info',
            text: message,
            icon: 'info',
            confirmButtonColor: '#3085d6'
        });
    };
</script>
