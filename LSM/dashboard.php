

<?php
require_once 'auth.php';
requireAuth();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Management - Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f9;
        
        }
        .container {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 250px;
            background-color: #2c3e50;
            color: white;
            padding: 1rem;
        }
        .sidebar h2 {
            text-align: center;
            margin-bottom: 2rem;
        }
        .sidebar ul {
            list-style: none;
            padding: 0;
        }
        .sidebar li {
            margin-bottom: 1rem;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
            display: block;
            padding: 0.5rem;
            border-radius: 4px;
        }
        .sidebar a:hover {
            background-color: #34495e;
        }
        .sidebar a.active {
            background-color: #3498db;
        }
        .main-content {
            flex: 1;
            padding: 2rem;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        .logout-btn {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
        }
        .logout-btn:hover {
            background-color: #c0392b;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-card h3 {
            margin-top: 0;
            color: #555;
        }
        .stat-card p {
            font-size: 2rem;
            margin: 0.5rem 0;
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <h2>Library Admin</h2>
            <ul>
                <li><a href="dashboard.php" class="active">Dashboard</a></li>
                <li><a href="members.php">Members</a></li>
                <li><a href="books.php">Books</a></li>
                <li><a href="loans.php">Loans</a></li>
                <li><a href="fines.php">Fines</a></li>
                <li><a href="reports.php">Reports</a></li>
            </ul>
        </div>
        <div class="main-content">
            <div class="header">
                <h1>Dashboard</h1>
                <form action="logout.php" method="post">
                    <button type="submit" class="logout-btn">Logout</button>
                </form>
            </div>
            
            <div class="stats">
                <?php
                $db = getDB();
                
                // Total members
                $stmt = $db->query("SELECT COUNT(*) FROM members WHERE status = 'Active'");
                $totalMembers = $stmt->fetchColumn();
                
                // Total books
                $stmt = $db->query("SELECT COUNT(*) FROM books");
                $totalBooks = $stmt->fetchColumn();
                
                // Active loans
                $stmt = $db->query("SELECT COUNT(*) FROM loans WHERE return_date IS NULL");
                $activeLoans = $stmt->fetchColumn();
                
                // Overdue loans
                $stmt = $db->query("SELECT COUNT(*) FROM loans WHERE return_date IS NULL AND due_date < NOW()");
                $overdueLoans = $stmt->fetchColumn();
                
                // Unpaid fines
                $stmt = $db->query("SELECT COUNT(*) FROM fines WHERE status = 'Unpaid'");
                $unpaidFines = $stmt->fetchColumn();
                
                // Available copies
                $stmt = $db->query("SELECT COUNT(*) FROM book_copies WHERE status = 'Available'");
                $availableCopies = $stmt->fetchColumn();
                ?>
                
                <div class="stat-card">
                    <h3>Active Members</h3>
                    <p><?= $totalMembers ?></p>
                </div>
                <div class="stat-card">
                    <h3>Total Books</h3>
                    <p><?= $totalBooks ?></p>
                </div>
                <div class="stat-card">
                    <h3>Active Loans</h3>
                    <p><?= $activeLoans ?></p>
                </div>
                <div class="stat-card">
                    <h3>Overdue Loans</h3>
                    <p><?= $overdueLoans ?></p>
                </div>
                <div class="stat-card">
                    <h3>Unpaid Fines</h3>
                    <p><?= $unpaidFines ?></p>
                </div>
                <div class="stat-card">
                    <h3>Available Copies</h3>
                    <p><?= $availableCopies ?></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
