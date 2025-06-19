


<?php
require_once 'auth.php';
requireAuth();

$db = getDB();

// Handle fine payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_fine'])) {
    $fineId = $_POST['fine_id'] ?? '';
    
    try {
        $stmt = $db->prepare("
            UPDATE fines 
            SET paid_date = NOW(), status = 'Paid' 
            WHERE id = ?
        ");
        $stmt->execute([$fineId]);
        $success = "Fine marked as paid!";
    } catch (PDOException $e) {
        $error = "Error paying fine: " . $e->getMessage();
    }
}

// Calculate fines for overdue loans
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calculate_fines'])) {
    try {
        // Find overdue loans without fines
        $stmt = $db->query("
            SELECT l.id, l.due_date, DATEDIFF(NOW(), l.due_date) AS days_overdue
            FROM loans l
            LEFT JOIN fines f ON f.loan_id = l.id
            WHERE l.return_date IS NULL 
            AND l.due_date < NOW()
            AND f.id IS NULL
        ");
        $overdueLoans = $stmt->fetchAll();
        
        $finesCreated = 0;
        
        foreach ($overdueLoans as $loan) {
            if ($loan['days_overdue'] > 0) {
                $amount = $loan['days_overdue'] * 1.00; // $1 per day
                
                $stmt = $db->prepare("
                    INSERT INTO fines (loan_id, amount)
                    VALUES (?, ?)
                ");
                $stmt->execute([$loan['id'], $amount]);
                $finesCreated++;
            }
        }
        
        $success = "Created fines for $finesCreated overdue loans";
    } catch (PDOException $e) {
        $error = "Error calculating fines: " . $e->getMessage();
    }
}

// Fetch unpaid fines with loan and member info
$stmt = $db->query("
    SELECT f.id, f.amount, f.issued_date, f.status,
           l.id AS loan_id, l.copy_id, l.due_date,
           m.full_name AS member_name, b.title AS book_title
    FROM fines f
    JOIN loans l ON f.loan_id = l.id
    JOIN members m ON l.member_id = m.id
    JOIN book_copies bc ON l.copy_id = bc.copy_id
    JOIN books b ON bc.isbn = b.isbn
    WHERE f.status = 'Unpaid'
    ORDER BY f.issued_date
");
$unpaidFines = $stmt->fetchAll();

// Fetch paid fines
$stmt = $db->query("
    SELECT f.id, f.amount, f.issued_date, f.paid_date, f.status,
           l.id AS loan_id, l.copy_id, l.due_date,
           m.full_name AS member_name, b.title AS book_title
    FROM fines f
    JOIN loans l ON f.loan_id = l.id
    JOIN members m ON l.member_id = m.id
    JOIN book_copies bc ON l.copy_id = bc.copy_id
    JOIN books b ON bc.isbn = b.isbn
    WHERE f.status = 'Paid'
    ORDER BY f.paid_date DESC
    LIMIT 50
");
$paidFines = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Management - Fines</title>
    <style>
        /* Previous styles from loans.php */
        /* Add fines-specific styles */
        .fines-actions {
            margin-bottom: 2rem;
        }
        .fines-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .fines-table th, .fines-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .fines-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .status-unpaid {
            color: red;
            font-weight: bold;
        }
        .status-paid {
            color: green;
        }
        .pay-btn {
            background-color: #2ecc71;
            color: white;
        }
        .pay-btn:hover {
            background-color: #27ae60;
        }
        .calculate-btn {
            background-color: #3498db;
            color: white;
        }
        .calculate-btn:hover {
            background-color: #2980b9;
        }
         ul {
    float: right;
    list-style-type: none;
    margin-top: 30px;
    min-height: 400px;
    margin-right: 60px;
    font-size: 17px;
}
ul li {
    display: inline-block;
}
ul li a {
    text-decoration: none;
    padding: 5px 20px;
    color: blue;
    border: 1px solid transparent;
    transition: 0.5 ease;
}

ul li a:hover {
    color: rgb(146, 7, 146);
    background-color: white;
}

ul li :active a {
    background-color: white;
    color: black;
}
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <h2>Library Admin</h2>
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="members.php">Members</a></li>
                <li><a href="books.php">Books</a></li>
                <li><a href="loans.php">Loans</a></li>
                <li><a href="fines.php" class="active">Fines</a></li>
                <li><a href="reports.php">Reports</a></li>
            </ul>
        </div>
        <div class="main-content">
            <div class="header">
                <h1>Fine Management</h1>
                <form action="logout.php" method="post">
                    <button type="submit" class="logout-btn">Logout</button>
                </form>
            </div>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <div class="fines-actions">
                <form method="POST" style="display: inline;">
                    <button type="submit" name="calculate_fines" class="action-btn calculate-btn">
                        Calculate Fines for Overdue Loans
                    </button>
                </form>
            </div>
            
            <h2>Unpaid Fines</h2>
            <table class="fines-table">
                <thead>
                    <tr>
                        <th>Fine ID</th>
                        <th>Member</th>
                        <th>Book Title</th>
                        <th>Copy ID</th>
                        <th>Amount</th>
                        <th>Issued Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($unpaidFines as $fine): ?>
                        <tr>
                            <td><?= htmlspecialchars($fine['id']) ?></td>
                            <td><?= htmlspecialchars($fine['member_name']) ?></td>
                            <td><?= htmlspecialchars($fine['book_title']) ?></td>
                            <td><?= htmlspecialchars($fine['copy_id']) ?></td>
                            <td>$<?= number_format($fine['amount'], 2) ?></td>
                            <td><?= htmlspecialchars($fine['issued_date']) ?></td>
                            <td class="status-<?= strtolower($fine['status']) ?>">
                                <?= htmlspecialchars($fine['status']) ?>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="fine_id" value="<?= $fine['id'] ?>">
                                    <button type="submit" name="pay_fine" class="action-btn pay-btn">
                                        Mark as Paid
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <h2>Recently Paid Fines</h2>
            <table class="fines-table">
                <thead>
                    <tr>
                        <th>Fine ID</th>
                        <th>Member</th>
                        <th>Book Title</th>
                        <th>Copy ID</th>
                        <th>Amount</th>
                        <th>Issued Date</th>
                        <th>Paid Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paidFines as $fine): ?>
                        <tr>
                            <td><?= htmlspecialchars($fine['id']) ?></td>
                            <td><?= htmlspecialchars($fine['member_name']) ?></td>
                            <td><?= htmlspecialchars($fine['book_title']) ?></td>
                            <td><?= htmlspecialchars($fine['copy_id']) ?></td>
                            <td>$<?= number_format($fine['amount'], 2) ?></td>
                            <td><?= htmlspecialchars($fine['issued_date']) ?></td>
                            <td><?= htmlspecialchars($fine['paid_date']) ?></td>
                            <td class="status-<?= strtolower($fine['status']) ?>">
                                <?= htmlspecialchars($fine['status']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>