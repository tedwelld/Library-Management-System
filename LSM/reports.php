

<?php
require_once 'auth.php';
requireAuth();

$db = getDB();

// Initialize variables
$memberHistory = [];
$selectedMemberId = null;

// Handle member history request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['view_history'])) {
    $selectedMemberId = $_POST['member_id'] ?? '';
    
    try {
        $stmt = $db->prepare("
            SELECT l.id AS loan_id, l.copy_id, l.checkout_date, l.due_date, l.return_date,
                   b.title AS book_title, bc.status AS copy_status,
                   f.amount AS fine_amount, f.status AS fine_status
            FROM loans l
            JOIN book_copies bc ON l.copy_id = bc.copy_id
            JOIN books b ON bc.isbn = b.isbn
            LEFT JOIN fines f ON f.loan_id = l.id
            WHERE l.member_id = ?
            ORDER BY l.checkout_date DESC
        ");
        $stmt->execute([$selectedMemberId]);
        $memberHistory = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = "Error fetching member history: " . $e->getMessage();
    }
}

// Fetch active loans for report
$stmt = $db->query("
    SELECT l.id, l.copy_id, l.checkout_date, l.due_date,
           m.full_name AS member_name, b.title AS book_title,
           CASE WHEN l.due_date < NOW() THEN 1 ELSE 0 END AS is_overdue
    FROM loans l
    JOIN members m ON l.member_id = m.id
    JOIN book_copies bc ON l.copy_id = bc.copy_id
    JOIN books b ON bc.isbn = b.isbn
    WHERE l.return_date IS NULL
    ORDER BY l.due_date
");
$activeLoansReport = $stmt->fetchAll();

// Fetch all members for dropdown
$stmt = $db->query("SELECT id, full_name FROM members ORDER BY full_name");
$allMembers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Management - Reports</title>

     <style>
        /* Previous styles from fines.php */
        /* Add reports-specific styles */

        body {
            font-family: Arial, sans-serif;
            
            margin: 0;
            padding: 0;
            background-image: url('IMG-20220402-WA0038.jpg');
        }
        .report-section {
            margin-bottom: 3rem;
        }
        .report-title {
            border-bottom: 2px solid #3498db;
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .report-table th, .report-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .report-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .overdue-row {
            background-color: #fff3f3;
        }
        .history-form {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
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
                <li><a href="fines.php">Fines</a></li>
                <li><a href="reports.php" class="active">Reports</a></li>
            </ul>
        </div>
        <div class="main-content">
            <div class="header">
                <h1>Reports</h1>
                <form action="logout.php" method="post">
                    <button type="submit" class="logout-btn">Logout</button>
                </form>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <div class="report-section">
                <h2 class="report-title">Active Loans Report</h2>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Loan ID</th>
                            <th>Book Title</th>
                            <th>Member</th>
                            <th>Checkout Date</th>
                            <th>Due Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activeLoansReport as $loan): ?>
                            <tr class="<?= $loan['is_overdue'] ? 'overdue-row' : '' ?>">
                                <td><?= htmlspecialchars($loan['id']) ?></td>
                                <td><?= htmlspecialchars($loan['book_title']) ?></td>
                                <td><?= htmlspecialchars($loan['member_name']) ?></td>
                                <td><?= htmlspecialchars($loan['checkout_date']) ?></td>
                                <td><?= htmlspecialchars($loan['due_date']) ?></td>
                                <td>
                                    <?= $loan['is_overdue'] ? 'OVERDUE' : 'Active' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="report-section">
                <h2 class="report-title">Member History</h2>
                <div class="history-form">
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="member_id">Select Member</label>
                                <select id="member_id" name="member_id" required>
                                    <option value="">-- Select a member --</option>
                                    <?php foreach ($allMembers as $member): ?>
                                        <option value="<?= htmlspecialchars($member['id']) ?>" 
                                            <?= $selectedMemberId == $member['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($member['full_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" name="view_history" class="submit-btn">View History</button>
                    </form>
                </div>
                
                <?php if (!empty($memberHistory)): ?>
                    <h3>Transaction History for <?= htmlspecialchars($allMembers[array_search($selectedMemberId, array_column($allMembers, 'id'))]['full_name']) ?></h3>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Loan ID</th>
                                <th>Book Title</th>
                                <th>Copy ID</th>
                                <th>Checkout Date</th>
                                <th>Due Date</th>
                                <th>Return Date</th>
                                <th>Fine Amount</th>
                                <th>Fine Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($memberHistory as $transaction): ?>
                                <tr>
                                    <td><?= htmlspecialchars($transaction['loan_id']) ?></td>
                                    <td><?= htmlspecialchars($transaction['book_title']) ?></td>
                                    <td><?= htmlspecialchars($transaction['copy_id']) ?></td>
                                    <td><?= htmlspecialchars($transaction['checkout_date']) ?></td>
                                    <td><?= htmlspecialchars($transaction['due_date']) ?></td>
                                    <td><?= htmlspecialchars($transaction['return_date'] ?? 'Not returned') ?></td>
                                    <td>
                                        <?= isset($transaction['fine_amount']) ? 
                                            '$' . number_format($transaction['fine_amount'], 2) : 
                                            '--' ?>
                                    </td>
                                    <td>
                                        <?= isset($transaction['fine_status']) ? 
                                            htmlspecialchars($transaction['fine_status']) : 
                                            '--' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>