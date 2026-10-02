<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$user = requireLogin();
$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    case 'GET':
        if (!empty($_GET['id'])) {
            $stmt = $pdo->prepare(
                'SELECT b.*, a.author_name, p.publisher_name, c.category_name
                 FROM books b
                 LEFT JOIN authors a ON a.author_id = b.author_id
                 LEFT JOIN publishers p ON p.publisher_id = b.publisher_id
                 LEFT JOIN categories c ON c.category_id = b.category_id
                 WHERE b.book_id = :id'
            );
            $stmt->execute(['id' => (int) $_GET['id']]);
            $book = $stmt->fetch();
            if (!$book) jsonError('Book not found.', 404);
            jsonResponse(true, $book);
        }

        // List + search + pagination
        $search   = trim($_GET['search'] ?? '');
        $category = $_GET['category_id'] ?? '';
        $status   = $_GET['status'] ?? '';
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $perPage  = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
        $offset   = ($page - 1) * $perPage;

        $where = [];
        $params = [];
        if ($search !== '') {
            $where[] = '(b.title LIKE :s1 OR b.isbn LIKE :s2 OR a.author_name LIKE :s3)';
            $params['s1'] = "%$search%";
            $params['s2'] = "%$search%";
            $params['s3'] = "%$search%";
        }
        if ($category !== '') {
            $where[] = 'b.category_id = :cat';
            $params['cat'] = (int) $category;
        }
        if ($status !== '') {
            $where[] = 'b.status = :status';
            $params['status'] = $status;
        }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM books b LEFT JOIN authors a ON a.author_id = b.author_id $whereSql"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT b.*, a.author_name, p.publisher_name, c.category_name
             FROM books b
             LEFT JOIN authors a ON a.author_id = b.author_id
             LEFT JOIN publishers p ON p.publisher_id = b.publisher_id
             LEFT JOIN categories c ON c.category_id = b.category_id
             $whereSql
             ORDER BY b.book_id DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) $stmt->bindValue(":$k", $v);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        jsonResponse(true, [
            'items' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ]);
        break;

    case 'POST':
        requireRole(['Admin', 'Manager']);
        requireCsrfForWrites();
        $input = sanitizeInput(getJsonInput());
        $errors = validateBookInput($input);
        if ($errors) jsonError(implode(' ', $errors));

        $stmt = $pdo->prepare(
            'INSERT INTO books (isbn, title, author_id, publisher_id, category_id, language, edition,
                publication_year, purchase_price, selling_price, quantity, reorder_level, shelf_location, status)
             VALUES (:isbn,:title,:author_id,:publisher_id,:category_id,:language,:edition,
                :pub_year,:purchase_price,:selling_price,:quantity,:reorder_level,:shelf,:status)'
        );
        try {
            $stmt->execute(bookParams($input));
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') jsonError('This ISBN already exists.', 409);
            throw $e;
        }
        jsonResponse(true, ['book_id' => $pdo->lastInsertId()], 'Book created successfully.', 201);
        break;

    case 'PUT':
        requireRole(['Admin', 'Manager']);
        requireCsrfForWrites();
        $input = sanitizeInput(getJsonInput());
        $id = (int) ($input['book_id'] ?? 0);
        if (!$id) jsonError('book_id is required.');
        $errors = validateBookInput($input);
        if ($errors) jsonError(implode(' ', $errors));

        $stmt = $pdo->prepare(
            'UPDATE books SET isbn=:isbn, title=:title, author_id=:author_id, publisher_id=:publisher_id,
                category_id=:category_id, language=:language, edition=:edition, publication_year=:pub_year,
                purchase_price=:purchase_price, selling_price=:selling_price, quantity=:quantity,
                reorder_level=:reorder_level, shelf_location=:shelf, status=:status
             WHERE book_id=:id'
        );
        $params = bookParams($input);
        $params['id'] = $id;
        try {
            $stmt->execute($params);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') jsonError('This ISBN already exists.', 409);
            throw $e;
        }
        jsonResponse(true, null, 'Book updated successfully.');
        break;

    case 'DELETE':
        requireRole(['Admin', 'Manager']);
        requireCsrfForWrites();
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) jsonError('id is required.');
        $stmt = $pdo->prepare('DELETE FROM books WHERE book_id = :id');
        $stmt->execute(['id' => $id]);
        jsonResponse(true, null, 'Book deleted successfully.');
        break;

    default:
        jsonError('Method not allowed', 405);
}

function validateBookInput(array $input): array
{
    $errors = [];
    if (empty($input['isbn'])) $errors[] = 'ISBN is required.';
    if (empty($input['title'])) $errors[] = 'Title is required.';
    if (!isset($input['selling_price']) || !is_numeric($input['selling_price']) || $input['selling_price'] < 0) {
        $errors[] = 'Valid selling price is required.';
    }
    if (!isset($input['purchase_price']) || !is_numeric($input['purchase_price']) || $input['purchase_price'] < 0) {
        $errors[] = 'Valid purchase price is required.';
    }
    if (!isset($input['quantity']) || !is_numeric($input['quantity']) || $input['quantity'] < 0) {
        $errors[] = 'Valid quantity is required.';
    }
    $allowedStatus = ['Available', 'Out of Stock', 'Discontinued'];
    if (!empty($input['status']) && !in_array($input['status'], $allowedStatus, true)) {
        $errors[] = 'Invalid status value.';
    }
    return $errors;
}

function bookParams(array $input): array
{
    return [
        'isbn' => $input['isbn'],
        'title' => $input['title'],
        'author_id' => !empty($input['author_id']) ? (int) $input['author_id'] : null,
        'publisher_id' => !empty($input['publisher_id']) ? (int) $input['publisher_id'] : null,
        'category_id' => !empty($input['category_id']) ? (int) $input['category_id'] : null,
        'language' => $input['language'] ?? 'English',
        'edition' => $input['edition'] ?? null,
        'pub_year' => !empty($input['publication_year']) ? (int) $input['publication_year'] : null,
        'purchase_price' => (float) $input['purchase_price'],
        'selling_price' => (float) $input['selling_price'],
        'quantity' => (int) $input['quantity'],
        'reorder_level' => (int) ($input['reorder_level'] ?? 5),
        'shelf' => $input['shelf_location'] ?? null,
        'status' => $input['status'] ?? 'Available',
    ];
}
