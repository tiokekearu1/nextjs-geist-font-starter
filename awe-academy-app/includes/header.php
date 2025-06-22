<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AWE Academy - School Management System</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --dark-blue: #002147;
            --royal-blue: #004080;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f8fb;
        }
        
        .navbar {
            background-color: var(--dark-blue);
            padding: 1rem 2rem;
        }
        
        .navbar-brand {
            color: white !important;
            font-weight: 600;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.9) !important;
        }
        
        .nav-link:hover {
            color: white !important;
        }
        
        .btn-primary {
            background-color: var(--royal-blue);
            border-color: var(--royal-blue);
        }
        
        .card {
            border: none;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/index.php">AWE Academy</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <?php if (isAuthenticated()): ?>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/modules/users/index.php">Users</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/modules/students/index.php">Students</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/modules/fees/index.php">Fees</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/modules/supplies/index.php">Supplies</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/modules/reports/index.php">Reports</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <a href="/modules/users/logout.php" class="btn btn-outline-light">Logout</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </nav>
    <div class="container-fluid py-4">
