<?php
// filepath: c:\xampp\htdocs\asset_dfm\maintenance.php
require_once __DIR__ . '/dist/config/config.php';

// Jika maintenance dimatikan, tendang kembali ke index
if (MAINTENANCE_MODE == 0) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Sedang Maintenance - DF GROUP</title>
    <!-- Use Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            color: #333;
        }

        .maintenance-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 3rem 4rem;
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 600px;
            width: 90%;
            position: relative;
            z-index: 10;
        }

        /* Animated Background Elements */
        .bg-shapes {
            position: absolute;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            overflow: hidden;
            z-index: 1;
            pointer-events: none;
        }

        .shape {
            position: absolute;
            filter: blur(50px);
            opacity: 0.6;
            animation: float 10s infinite ease-in-out alternate;
        }

        .shape-1 {
            width: 300px;
            height: 300px;
            background: #ff9a9e;
            border-radius: 50%;
            top: -100px;
            left: -100px;
        }

        .shape-2 {
            width: 400px;
            height: 400px;
            background: #fecfef;
            border-radius: 50%;
            bottom: -150px;
            right: -100px;
            animation-delay: -5s;
        }

        .shape-3 {
            width: 250px;
            height: 250px;
            background: #a1c4fd;
            border-radius: 50%;
            top: 40%;
            left: 60%;
            animation-duration: 15s;
        }

        @keyframes float {
            0% { transform: translateY(0) scale(1); }
            100% { transform: translateY(-50px) scale(1.1); }
        }

        /* Icon Animation */
        .icon-container {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto 2rem;
        }

        .gear {
            position: absolute;
            color: #ff7e5f;
        }

        .gear-large {
            font-size: 5rem;
            top: 0;
            left: 10px;
            animation: spin 6s linear infinite;
        }

        .gear-small {
            font-size: 3rem;
            bottom: 10px;
            right: 10px;
            color: #2b5876;
            animation: spin-reverse 4s linear infinite;
        }

        @keyframes spin {
            100% { transform: rotate(360deg); }
        }

        @keyframes spin-reverse {
            100% { transform: rotate(-360deg); }
        }

        /* Content Styling */
        h1 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #1a2a6c;
            background: -webkit-linear-gradient(#1a2a6c, #b21f1f);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        p {
            font-size: 1.1rem;
            line-height: 1.6;
            color: #666;
            margin-bottom: 2rem;
            font-weight: 300;
        }

        .brand-logo {
            margin-bottom: 1.5rem;
        }

        .brand-logo img {
            max-width: 150px;
        }

        .contact-info {
            background: #f8f9fa;
            border-left: 4px solid #ff7e5f;
            padding: 1rem;
            border-radius: 0 8px 8px 0;
            text-align: left;
            margin-top: 2rem;
        }

        .contact-info h4 {
            color: #333;
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }

        .contact-info p {
            font-size: 0.9rem;
            margin-bottom: 0;
        }

        .contact-info i {
            color: #ff7e5f;
            width: 20px;
        }

        /* Progress Line */
        .progress-container {
            width: 100%;
            height: 4px;
            background: #e9ecef;
            margin-top: 2rem;
            border-radius: 2px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            width: 50%;
            background: linear-gradient(90deg, #ff9a9e 0%, #fecfef 99%, #fecfef 100%);
            animation: loading 2s ease-in-out infinite alternate;
        }

        @keyframes loading {
            0% { width: 0; transform: translateX(0); }
            100% { width: 50%; transform: translateX(100%); }
        }

        @media (max-width: 768px) {
            .maintenance-container {
                padding: 2rem 1.5rem;
            }
            h1 {
                font-size: 1.8rem;
            }
            p {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>

    <!-- Animated Background -->
    <div class="bg-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
    </div>

    <!-- Main Content -->
    <div class="maintenance-container">
        
        <!-- Logo (Ganti src nya dengan path logo DF Group jika ada) -->
        <div class="brand-logo">
            <img src="https://i.imgur.com/uNGdWHi.png" alt="DF GROUP">
        </div>

        <!-- Animated Gears -->
        <div class="icon-container">
            <i class="fas fa-cog gear gear-large"></i>
            <i class="fas fa-cog gear gear-small"></i>
        </div>

        <h1>Pembaruan Sistem</h1>
        <p>Mohon maaf, Asset & Budget Management System saat ini sedang dalam pemeliharaan rutin untuk meningkatkan performa dan fitur baru.</p>
        
        <p><strong>Kami akan segera kembali online dalam waktu dekat.</strong></p>

        <!-- Progress Animation -->
        <div class="progress-container">
            <div class="progress-bar"></div>
        </div>

        <!-- Contact Section -->
        <div class="contact-info">
            <h4><i class="fas fa-headset"></i> Butuh Bantuan Mendesak?</h4>
            <p>Silakan hubungi tim IT Support kami.</p>
        </div>

    </div>

</body>
</html>
