


<?php
require_once 'auth.php';
requireAuth();

$db = getDB();

// Handle new loan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $memberId = $_POST['member_id'] ?? '';
    $copyId = $_POST['copy_id'] ?? '';
    $dueDate = date('Y-m-d H:i:s', strtotime('+14 days'));
    
    try {
        // Check if member is active
        $stmt = $db->prepare("SELECT status FROM members WHERE id = ?");
        $stmt->execute([$memberId]);
        $memberStatus = $stmt->fetchColumn();
        
        if ($memberStatus !== 'Active') {
            $error = "Cannot checkout to inactive member";
        } else {
            // Check if copy is available
            $stmt = $db->prepare("SELECT status FROM book_copies WHERE copy_id = ?");
            $stmt->execute([$copyId]);
            $copyStatus = $stmt->fetchColumn();
            
            if ($copyStatus !== 'Available') {
                $error = "Selected book copy is not available for checkout";
            } else {
                // Start transaction
                $db->beginTransaction();
                
                try {
                    // Create loan
                    $stmt = $db->prepare("
                        INSERT INTO loans (copy_id, member_id, due_date) 
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$copyId, $memberId, $dueDate]);
                    
                    // Update copy status
                    $stmt = $db->prepare("
                        UPDATE book_copies SET status = 'Borrowed' WHERE copy_id = ?
                    ");
                    $stmt->execute([$copyId]);
                    
                    $db->commit();
                    $success = "Book checked out successfully! Due date: " . date('M j, Y', strtotime($dueDate));
                } catch (Exception $e) {
                    $db->rollBack();
                    throw $e;
                }
            }
        }
    } catch (PDOException $e) {
        $error = "Error checking out book: " . $e->getMessage();
    }
}

// Handle return
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return'])) {
    $loanId = $_POST['loan_id'] ?? '';
    
    try {
        // Check if there are unpaid fines
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM fines 
            WHERE loan_id = ? AND status = 'Unpaid'
        ");
        $stmt->execute([$loanId]);
        $unpaidFines = $stmt->fetchColumn();
        
        if ($unpaidFines > 0) {
            $error = "Cannot return book with unpaid fines. Please pay fines first.";
        } else {
            // Start transaction
            $db->beginTransaction();
            
            try {
                // Get loan info
                $stmt = $db->prepare("
                    SELECT copy_id FROM loans WHERE id = ?
                ");
                $stmt->execute([$loanId]);
                $copyId = $stmt->fetchColumn();
                
                // Update loan with return date
                $stmt = $db->prepare("
                    UPDATE loans SET return_date = NOW() WHERE id = ?
                ");
                $stmt->execute([$loanId]);
                
                // Update copy status
                $stmt = $db->prepare("
                    UPDATE book_copies SET status = 'Available' WHERE copy_id = ?
                ");
                $stmt->execute([$copyId]);
                
                $db->commit();
                $success = "Book returned successfully!";
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
        }
    } catch (PDOException $e) {
        $error = "Error returning book: " . $e->getMessage();
    }
}

// Fetch active members
$stmt = $db->query("SELECT id, full_name FROM members WHERE status = 'Active' ORDER BY full_name");
$activeMembers = $stmt->fetchAll();

// Fetch available copies
$stmt = $db->query("
    SELECT bc.copy_id, b.title 
    FROM book_copies bc
    JOIN books b ON bc.isbn = b.isbn
    WHERE bc.status = 'Available'
    ORDER BY b.title
");
$availableCopies = $stmt->fetchAll();

// Fetch active loans with member and book info
$stmt = $db->query("
    SELECT l.id, l.copy_id, l.checkout_date, l.due_date, l.return_date,
           m.full_name AS member_name, b.title AS book_title,
           (SELECT COUNT(*) FROM fines f WHERE f.loan_id = l.id AND f.status = 'Unpaid') AS unpaid_fines
    FROM loans l
    JOIN members m ON l.member_id = m.id
    JOIN book_copies bc ON l.copy_id = bc.copy_id
    JOIN books b ON bc.isbn = b.isbn
    WHERE l.return_date IS NULL
    ORDER BY l.due_date
");
$activeLoans = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Management - Loans</title>
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
        
        /* Members specific styles */
        .add-member-form {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
        }
        .form-group input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .submit-btn {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
        }
        .submit-btn:hover {
            background-color: #2980b9;
        }
        .members-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .members-table th, .members-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .members-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .status-active {
            color: green;
        }
        .status-inactive {
            color: red;
        }
        .action-btn {
            padding: 0.3rem 0.6rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 0.5rem;
        }
        .toggle-status {
            background-color: #f39c12;
            color: white;
        }
        .toggle-status:hover {
            background-color: #e67e22;
        }
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
        }
        .add-book-form, .add-copy-form {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .form-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .form-row .form-group {
            flex: 1;
        }
        .books-table, .copies-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .books-table th, .books-table td,
        .copies-table th, .copies-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .books-table th, .copies-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .status-available {
            color: green;
        }
        .status-borrowed {
            color: orange;
        }
        .status-lost {
            color: red;
        }
        .delete-btn {
            background-color: #e74c3c;
            color: white;
        }
        .delete-btn:hover {
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
                <li><a href="loans.php" class="active">Loans</a></li>
                <li><a href="fines.php">Fines</a></li>
                <li><a href="reports.php">Reports</a></li>
            </ul>
        </div>
        <div class="main-content">
            <div class="header">
                <h1>Loan Management</h1>
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
            
            <div class="checkout-form">
                <h2>Checkout Book</h2>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="member_id">Member</label>
                            <select id="member_id" name="member_id" required>
                                <?php foreach ($activeMembers as $member): ?>
                                    <option value="<?= htmlspecialchars($member['id']) ?>">
                                        <?= htmlspecialchars($member['full_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="copy_id">Book Copy</label>
                            <select id="copy_id" name="copy_id" required>
                                <?php foreach ($availableCopies as $copy): ?>
                                    <option value="<?= htmlspecialchars($copy['copy_id']) ?>">
                                        <?= htmlspecialchars($copy['title'] . ' (Copy: ' . $copy['copy_id'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" name="checkout" class="submit-btn">Checkout</button>
                </form>
            </div>
            
            <h2>Active Loans</h2>
            <table class="loans-table">
                <thead>
                    <tr>
                        <th>Loan ID</th>
                        <th>Book Title</th>
                        <th>Member</th>
                        <th>Checkout Date</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activeLoans as $loan): ?>
                        <tr>
                            <td><?= htmlspecialchars($loan['id']) ?></td>
                            <td><?= htmlspecialchars($loan['book_title']) ?></td>
                            <td><?= htmlspecialchars($loan['member_name']) ?></td>
                            <td><?= htmlspecialchars($loan['checkout_date']) ?></td>
                            <td class="<?= strtotime($loan['due_date']) < time() ? 'overdue' : '' ?>">
                                <?= htmlspecialchars($loan['due_date']) ?>
                                <?php if (strtotime($loan['due_date']) < time()): ?>
                                    (Overdue)
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $loan['unpaid_fines'] > 0 ? 'Fines Due' : 'Active' ?>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
                                    <button type="submit" name="return" class="action-btn return-btn">
                                        Return
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>