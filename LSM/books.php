

<?php
require_once 'auth.php';
requireAuth();

$db = getDB();

// Handle book metadata creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_book'])) {
    $isbn = $_POST['isbn'] ?? '';
    $title = $_POST['title'] ?? '';
    $author = $_POST['author'] ?? '';
    $publisher = $_POST['publisher'] ?? '';
    $publicationYear = $_POST['publication_year'] ?? '';
    $genre = $_POST['genre'] ?? '';
    
    try {
        $stmt = $db->prepare("
            INSERT INTO books (isbn, title, author, publisher, publication_year, genre) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$isbn, $title, $author, $publisher, $publicationYear, $genre]);
        $success = "Book added successfully!";
    } catch (PDOException $e) {
        $error = "Error adding book: " . $e->getMessage();
    }
}

// Handle book copy addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_copy'])) {
    $isbn = $_POST['copy_isbn'] ?? '';
    $copyId = $_POST['copy_id'] ?? '';
    
    try {
        $stmt = $db->prepare("INSERT INTO book_copies (copy_id, isbn) VALUES (?, ?)");
        $stmt->execute([$copyId, $isbn]);
        $success = "Book copy added successfully!";
    } catch (PDOException $e) {
        $error = "Error adding book copy: " . $e->getMessage();
    }
}

// Handle book metadata deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_book'])) {
    $isbn = $_POST['isbn'] ?? '';
    
    try {
        // Check if any copies exist
        $stmt = $db->prepare("SELECT COUNT(*) FROM book_copies WHERE isbn = ?");
        $stmt->execute([$isbn]);
        $copyCount = $stmt->fetchColumn();
        
        if ($copyCount > 0) {
            $error = "Cannot delete book with existing copies. Delete all copies first.";
        } else {
            $stmt = $db->prepare("DELETE FROM books WHERE isbn = ?");
            $stmt->execute([$isbn]);
            $success = "Book deleted successfully!";
        }
    } catch (PDOException $e) {
        $error = "Error deleting book: " . $e->getMessage();
    }
}

// Handle book copy status change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_copy_status'])) {
    $copyId = $_POST['copy_id'] ?? '';
    $status = $_POST['status'] ?? '';
    
    try {
        $stmt = $db->prepare("UPDATE book_copies SET status = ? WHERE copy_id = ?");
        $stmt->execute([$status, $copyId]);
        $success = "Copy status updated!";
    } catch (PDOException $e) {
        $error = "Error updating copy status: " . $e->getMessage();
    }
}

// Fetch all books
$stmt = $db->query("SELECT * FROM books ORDER BY title");
$books = $stmt->fetchAll();

// Fetch all copies with book info
$stmt = $db->query("
    SELECT bc.copy_id, bc.status, b.isbn, b.title, b.author 
    FROM book_copies bc
    JOIN books b ON bc.isbn = b.isbn
    ORDER BY b.title, bc.copy_id
");
$copies = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Management - Books</title>
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
         ul {
    float: right;
    list-style-type: none;
    margin-top: 30px;
    min-height: 400px;
    margin-right: 60px;
    font-size: 17px;
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
                <li><a href="books.php" class="active">Books</a></li>
                <li><a href="loans.php">Loans</a></li>
                <li><a href="fines.php">Fines</a></li>
                <li><a href="reports.php">Reports</a></li>
            </ul>
        </div>
        <div class="main-content">
            <div class="header">
                <h1>Book Management</h1>
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
            
            <div class="add-book-form">
                <h2>Add New Book</h2>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="isbn">ISBN</label>
                            <input type="text" id="isbn" name="isbn" required>
                        </div>
                        <div class="form-group">
                            <label for="title">Title</label>
                            <input type="text" id="title" name="title" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="author">Author</label>
                            <input type="text" id="author" name="author" required>
                        </div>
                        <div class="form-group">
                            <label for="publisher">Publisher</label>
                            <input type="text" id="publisher" name="publisher" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="publication_year">Publication Year</label>
                            <input type="number" id="publication_year" name="publication_year" required min="1000" max="<?= date('Y') ?>">
                        </div>
                        <div class="form-group">
                            <label for="genre">Genre</label>
                            <input type="text" id="genre" name="genre" required>
                        </div>
                    </div>
                    <button type="submit" name="add_book" class="submit-btn">Add Book</button>
                </form>
            </div>
            
            <div class="add-copy-form">
                <h2>Add Book Copy</h2>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="copy_isbn">Book ISBN</label>
                            <select id="copy_isbn" name="copy_isbn" required>
                                <?php foreach ($books as $book): ?>
                                    <option value="<?= htmlspecialchars($book['isbn']) ?>">
                                        <?= htmlspecialchars($book['isbn'] . ' - ' . $book['title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="copy_id">Copy ID (Barcode)</label>
                            <input type="text" id="copy_id" name="copy_id" required>
                        </div>
                    </div>
                    <button type="submit" name="add_copy" class="submit-btn">Add Copy</button>
                </form>
            </div>
            
            <h2>All Books</h2>
            <table class="books-table">
                <thead>
                    <tr>
                        <th>ISBN</th>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Publisher</th>
                        <th>Year</th>
                        <th>Genre</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($books as $book): ?>
                        <tr>
                            <td><?= htmlspecialchars($book['isbn']) ?></td>
                            <td><?= htmlspecialchars($book['title']) ?></td>
                            <td><?= htmlspecialchars($book['author']) ?></td>
                            <td><?= htmlspecialchars($book['publisher']) ?></td>
                            <td><?= htmlspecialchars($book['publication_year']) ?></td>
                            <td><?= htmlspecialchars($book['genre']) ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="isbn" value="<?= $book['isbn'] ?>">
                                    <button type="submit" name="delete_book" class="action-btn delete-btn" 
                                        onclick="return confirm('Are you sure you want to delete this book?')">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <h2>Book Copies</h2>
            <table class="copies-table">
                <thead>
                    <tr>
                        <th>Copy ID</th>
                        <th>Book Title</th>
                        <th>Author</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($copies as $copy): ?>
                        <tr>
                            <td><?= htmlspecialchars($copy['copy_id']) ?></td>
                            <td><?= htmlspecialchars($copy['title']) ?></td>
                            <td><?= htmlspecialchars($copy['author']) ?></td>
                            <td class="status-<?= strtolower($copy['status']) ?>">
                                <?= htmlspecialchars($copy['status']) ?>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="copy_id" value="<?= $copy['copy_id'] ?>">
                                    <select name="status" onchange="this.form.submit()" style="padding: 0.3rem;">
                                        <option value="Available" <?= $copy['status'] === 'Available' ? 'selected' : '' ?>>Available</option>
                                        <option value="Borrowed" <?= $copy['status'] === 'Borrowed' ? 'selected' : '' ?>>Borrowed</option>
                                        <option value="Lost" <?= $copy['status'] === 'Lost' ? 'selected' : '' ?>>Lost</option>
                                    </select>
                                    <input type="hidden" name="change_copy_status">
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