


<?php
require_once 'auth.php';
requireAuth();

$db = getDB();

// Handle member creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_member'])) {
    $fullName = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    
    try {
        $stmt = $db->prepare("INSERT INTO members (full_name, email, phone) VALUES (?, ?, ?)");
        $stmt->execute([$fullName, $email, $phone]);
        $success = "Member added successfully!";
    } catch (PDOException $e) {
        $error = "Error adding member: " . $e->getMessage();
    }
}

// Handle member status change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    $memberId = $_POST['member_id'] ?? '';
    $status = $_POST['status'] ?? '';
    
    try {
        // Check if member has active loans or unpaid fines
        if ($status === 'Inactive') {
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM loans 
                WHERE member_id = ? AND return_date IS NULL
            ");
            $stmt->execute([$memberId]);
            $activeLoans = $stmt->fetchColumn();
            
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM fines f
                JOIN loans l ON f.loan_id = l.id
                WHERE l.member_id = ? AND f.status = 'Unpaid'
            ");
            $stmt->execute([$memberId]);
            $unpaidFines = $stmt->fetchColumn();
            
            if ($activeLoans > 0 || $unpaidFines > 0) {
                $error = "Cannot inactivate member with active loans or unpaid fines";
            } else {
                $stmt = $db->prepare("UPDATE members SET status = ? WHERE id = ?");
                $stmt->execute([$status, $memberId]);
                $success = "Member status updated!";
            }
        } else {
            $stmt = $db->prepare("UPDATE members SET status = ? WHERE id = ?");
            $stmt->execute([$status, $memberId]);
            $success = "Member status updated!";
        }
    } catch (PDOException $e) {
        $error = "Error updating member status: " . $e->getMessage();
    }
}

// Fetch all members
$stmt = $db->query("SELECT * FROM members ORDER BY id DESC");
$members = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Management - Members</title>
    <style>
        /* Previous styles from dashboard.php */
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
        
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <h2>Library Admin</h2>
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="members.php" class="active">Members</a></li>
                <li><a href="books.php">Books</a></li>
                <li><a href="loans.php">Loans</a></li>
                <li><a href="fines.php">Fines</a></li>
                <li><a href="reports.php">Reports</a></li>
            </ul>
        </div>
        <div class="main-content">
            <div class="header">
                <h1>Member Management</h1>
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
            
            <div class="add-member-form">
                <h2>Add New Member</h2>
                <form method="POST">
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="tel" id="phone" name="phone" required>
                    </div>
                    <button type="submit" name="add_member" class="submit-btn">Add Member</button>
                </form>
            </div>
            
            <h2>All Members</h2>
            <table class="members-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Membership Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $member): ?>
                        <tr>
                            <td><?= htmlspecialchars($member['id']) ?></td>
                            <td><?= htmlspecialchars($member['full_name']) ?></td>
                            <td><?= htmlspecialchars($member['email']) ?></td>
                            <td><?= htmlspecialchars($member['phone']) ?></td>
                            <td><?= htmlspecialchars($member['membership_date']) ?></td>
                            <td class="status-<?= strtolower($member['status']) ?>">
                                <?= htmlspecialchars($member['status']) ?>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="member_id" value="<?= $member['id'] ?>">
                                    <input type="hidden" name="status" value="<?= $member['status'] === 'Active' ? 'Inactive' : 'Active' ?>">
                                    <button type="submit" name="change_status" class="action-btn toggle-status">
                                        <?= $member['status'] === 'Active' ? 'Deactivate' : 'Activate' ?>
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