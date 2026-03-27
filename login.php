<?php
// PHP code remains the same as before
// Only CSS is being redesigned
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Greenhill Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/master.css">
    <style>
        /* Login Page Specific Styles */
        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(26, 44, 62, 0.9) 0%, rgba(46, 125, 50, 0.9) 100%),
                        url('../../assets/images/school-campus.jpg') center/cover no-repeat fixed;
            position: relative;
        }
        
        .login-wrapper::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('../../assets/images/pattern-overlay.png') repeat;
            opacity: 0.1;
            pointer-events: none;
        }
        
        .login-card {
            background: rgba(255,255,255,0.98);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-xl);
            padding: 40px;
            width: 100%;
            max-width: 450px;
            box-shadow: var(--shadow-2xl);
            animation: fadeInUp 0.6s ease-out;
            position: relative;
            z-index: 1;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-logo {
            width: 80px;
            height: 80px;
            background: var(--gradient-primary);
            border-radius: var(--radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            animation: pulse 2s infinite;
        }
        
        .login-logo i {
            font-size: 40px;
            color: white;
        }
        
        .login-header h2 {
            font-size: 28px;
            font-weight: 700;
            color: var(--gray-dark);
            margin-bottom: 5px;
        }
        
        .login-header p {
            color: var(--gray);
            font-size: 14px;
        }
        
        .input-group-custom {
            position: relative;
            margin-bottom: 25px;
        }
        
        .input-group-custom i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-green);
            font-size: 18px;
            z-index: 1;
        }
        
        .input-group-custom input {
            width: 100%;
            padding: 14px 15px 14px 45px;
            border: 2px solid var(--gray-lighter);
            border-radius: var(--radius-md);
            font-size: 15px;
            transition: all var(--transition-normal);
        }
        
        .input-group-custom input:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 4px rgba(46,125,50,0.1);
            outline: none;
        }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            background: var(--gradient-primary);
            border: none;
            border-radius: var(--radius-md);
            color: white;
            transition: all var(--transition-normal);
            margin-top: 10px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .login-footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid var(--gray-lighter);
        }
        
        .login-footer a {
            color: var(--primary-green);
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-footer a:hover {
            text-decoration: underline;
        }
        
        .school-feature {
            position: absolute;
            bottom: 30px;
            left: 0;
            right: 0;
            text-align: center;
            color: white;
            font-size: 14px;
            z-index: 1;
        }
        
        .school-feature span {
            background: rgba(0,0,0,0.5);
            padding: 8px 20px;
            border-radius: var(--radius-full);
            backdrop-filter: blur(5px);
        }
        
        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(46,125,50,0.4);
            }
            50% {
                transform: scale(1.05);
                box-shadow: 0 0 0 10px rgba(46,125,50,0);
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h2>Welcome Back</h2>
                <p>Sign in to access your dashboard</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="input-group-custom">
                    <i class="fas fa-user"></i>
                    <input type="text" name="username" placeholder="Username or Email" required autofocus>
                </div>
                <div class="input-group-custom">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="Password" required>
                </div>
                <button type="submit" class="btn btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i> Login to Dashboard
                </button>
            </form>
            
            <div class="login-footer">
                <a href="forgot_password.php">Forgot Password?</a>
                <span class="mx-2">|</span>
                <a href="#">Need Help?</a>
            </div>
        </div>
        
        <div class="school-feature">
            <span><i class="fas fa-school me-2"></i> Greenhill Academy - Excellence in Education Since 1994</span>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>