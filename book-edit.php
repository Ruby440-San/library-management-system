<?php

session_start();
require_once('./layout/header.php');


$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header('Location: login.php');
    exit;
}


if (isset($_POST['signOutBtn'])) {

    session_destroy();

    header('Location: login.php');
    exit;
}


$editBookId = $_GET['id'] ?? $_GET['book_id'] ?? null;

$editBookId = filter_var(
    $editBookId,
    FILTER_VALIDATE_INT
);

if (!$editBookId) {

    header('Location: index.php');
    exit;
}


$stmtBook = $db->prepare("
    SELECT *
    FROM books
    WHERE id = :id
    AND user_id = :user_id
    LIMIT 1
");

$stmtBook->execute([
    ':id' => $editBookId,
    ':user_id' => $user_id
]);

$editBook = $stmtBook->fetch(PDO::FETCH_OBJ);

if (!$editBook) {

    header('Location: index.php');
    exit;
}


if (isset($_POST['bookEditBtn'])) {

    $title = trim($_POST['title'] ?? '');

    $author = trim($_POST['author'] ?? '');

    $genre = trim($_POST['genre'] ?? '');

    $status = trim($_POST['status'] ?? '');

    $stars = trim($_POST['stars'] ?? '');

    $source = trim($_POST['source'] ?? '');

    $notes = trim($_POST['notes'] ?? '');


    if ($title === '' || $author === '') {

        header(
            "Location: book-edit.php?id=" .
            $editBookId .
            "&error=required"
        );

        exit;
    }


    //OLD VALUES

    $oldGenre = $editBook->genre ?? '';

    $oldImage = $editBook->image ?? '';

    

    $imageName = $oldImage;

    $newUploadedImage = false;

    if (
        isset($_FILES['image']) &&
        $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {

            header(
                "Location: book-edit.php?id=" .
                $editBookId .
                "&error=image"
            );

            exit;
        }


        $imageTmpName = $_FILES['image']['tmp_name'];

        $originalName = $_FILES['image']['name'];


        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        $mimeType = finfo_file(
            $finfo,
            $imageTmpName
        );

        finfo_close($finfo);


        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png'
        ];


        if (!isset($allowedTypes[$mimeType])) {

            header(
                "Location: book-edit.php?id=" .
                $editBookId .
                "&error=image_type"
            );

            exit;
        }


        if (!is_dir('images')) {

            mkdir(
                'images',
                0755,
                true
            );
        }

        $extension = $allowedTypes[$mimeType];

        $newImageName =
            uniqid('book_', true) .
            '.' .
            $extension;


        $uploadPath =
            'images/' .
            $newImageName;

        if (
            move_uploaded_file(
                $imageTmpName,
                $uploadPath
            )
        ) {

            $imageName = $newImageName;

            $newUploadedImage = true;

        } else {

            header(
                "Location: book-edit.php?id=" .
                $editBookId .
                "&error=image_upload"
            );

            exit;
        }
    }

    try {

        $db->beginTransaction();


        $stmtUpdate = $db->prepare("
            UPDATE books
            SET
                title = :title,
                author = :author,
                genre = :genre,
                image = :image,
                status = :status,
                stars = :stars,
                notes = :notes,
                source = :source
            WHERE id = :id
            AND user_id = :user_id
        ");


        $stmtUpdate->execute([

            ':title' => $title,

            ':author' => $author,

            ':genre' => $genre,

            ':image' => $imageName,

            ':status' => $status,

            ':stars' => $stars,

            ':notes' => $notes,

            ':source' => $source,

            ':id' => $editBookId,

            ':user_id' => $user_id
        ]);


        if (
            strcasecmp(
                trim($oldGenre),
                trim($genre)
            ) !== 0
        ) {

            if ($oldGenre !== '') {

                $stmtDecrease = $db->prepare("
                    UPDATE categories
                    SET quantity = GREATEST(0, quantity - 1)
                    WHERE LOWER(TRIM(name)) =
                          LOWER(TRIM(:oldGenre))
                    AND user_id = :user_id
                ");


                $stmtDecrease->execute([

                    ':oldGenre' => $oldGenre,

                    ':user_id' => $user_id

                ]);
            }



            if ($genre !== '') {

                $stmtIncrease = $db->prepare("
                    UPDATE categories
                    SET quantity = quantity + 1
                    WHERE LOWER(TRIM(name)) =
                          LOWER(TRIM(:genre))
                    AND user_id = :user_id
                ");


                $stmtIncrease->execute([

                    ':genre' => $genre,

                    ':user_id' => $user_id

                ]);
            }
        }


        $db->commit();

        if (
            $newUploadedImage &&
            !empty($oldImage) &&
            $oldImage !== $imageName &&
            file_exists('images/' . $oldImage)
        ) {

            unlink('images/' . $oldImage);
        }


        header(
            'Location: index.php?updated=1'
        );

        exit;


    } catch (Exception $e) {

        if ($db->inTransaction()) {

            $db->rollBack();
        }

        if (
            $newUploadedImage &&
            !empty($imageName) &&
            $imageName !== $oldImage &&
            file_exists('images/' . $imageName)
        ) {

            unlink('images/' . $imageName);
        }


        header(
            "Location: book-edit.php?id=" .
            $editBookId .
            "&error=update"
        );

        exit;
    }
}

$sqlCategories = "
    SELECT
        c.id,
        c.name,
        c.image,
        COUNT(b.id) AS quantity

    FROM categories c

    LEFT JOIN books b
        ON LOWER(TRIM(b.genre)) =
           LOWER(TRIM(c.name))
        AND b.user_id = :book_user_id

    WHERE c.user_id = :category_user_id

    GROUP BY
        c.id,
        c.name,
        c.image

    ORDER BY c.name ASC
";

$stmtCategories = $db->prepare($sqlCategories);

$stmtCategories->execute([

    ':book_user_id' => $user_id,

    ':category_user_id' => $user_id
]);

$categories = $stmtCategories->fetchAll(PDO::FETCH_OBJ);


$stmtTotal = $db->prepare("
    SELECT COUNT(*)
    FROM books
    WHERE user_id = :user_id
");

$stmtTotal->execute([
    ':user_id' => $user_id
]);

$totalBooks = $stmtTotal->fetchColumn();

$stmtNew = $db->prepare("
    SELECT COUNT(*)
    FROM books
    WHERE status = :status
    AND user_id = :user_id
");

$stmtNew->execute([

    ':status' => 'New',

    ':user_id' => $user_id
]);

$newCount = $stmtNew->fetchColumn();

$stmtCompleted = $db->prepare("
    SELECT COUNT(*)
    FROM books
    WHERE status = :status
    AND user_id = :user_id
");

$stmtCompleted->execute([

    ':status' => 'Completed',

    ':user_id' => $user_id
]);

$completedCount = $stmtCompleted->fetchColumn();

$stmtReading = $db->prepare("
    SELECT COUNT(*)
    FROM books
    WHERE status = :status
    AND user_id = :user_id
");

$stmtReading->execute([

    ':status' => 'Reading',

    ':user_id' => $user_id
]);

$readingCount = $stmtReading->fetchColumn();


$userName = $_SESSION['user_name'] ?? 'No User';

require_once('./layout/navbar.php');

?>
<body>
  <form method="post" enctype="multipart/form-data">
   <div class="container mx-auto p-5 shadow shadow-gray-300 rounded-md w-283 my-5">
      <?php if (!empty($errorMessage)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 text-sm">
          <?= htmlspecialchars($errorMessage) ?>
        </div>
      <?php endif; ?>

      <div>
        <h2 class="text-lg font-bold">Edit Book</h2>
        <span class="text-sm text-zinc-500">Update book information below.</span>
      </div>

      <div class="grid grid-cols-2 gap-2 mb-3 mt-4">
        <div>
          <label class="text-[#2C241B] block mb-2 text-sm">Title *</label>
          <input type="text" name="title" value="<?= htmlspecialchars($editBook->title ?? '') ?>" class="border border-gray-200 px-3 py-1 w-full rounded bg-gray-100 outline-none placeholder:text-[13px]">
        </div>

        <div>
          <label class="text-[#2C241B] block mb-2 text-sm">Author *</label>
          <input type="text" name="author" value="<?= htmlspecialchars($editBook->author ?? '') ?>" class="border border-gray-200 px-3 py-1 w-full rounded bg-gray-100 outline-none placeholder:text-[13px]">
        </div>

        <div>
          <label class="text-[#2C241B] block mb-2 text-sm">Category/Genre</label>
          <select name="genre" class="border border-gray-200 px-3 py-1 h-8 w-full rounded bg-gray-100 text-[13px] text-gray-500">
            <?php $genre = $editBook->genre ?? ''; ?>
            <option value="Science Fiction" <?= $genre === 'Science Fiction' ? 'selected' : '' ?>>Science Fiction</option>
            <option value="Fantasy" <?= $genre === 'Fantasy' ? 'selected' : '' ?>>Fantasy</option>
            <option value="Romance" <?= $genre === 'Romance' ? 'selected' : '' ?>>Romance</option>
            <option value="Mystery" <?= $genre === 'Mystery' ? 'selected' : '' ?>>Mystery</option>
            <option value="Thriller" <?= $genre === 'Thriller' ? 'selected' : '' ?>>Thriller</option>
            <option value="Non-Fiction" <?= $genre === 'Non-Fiction' ? 'selected' : '' ?>>Non-Fiction</option>
            <option value="Classic Fiction" <?= $genre === 'Classic Fiction' ? 'selected' : '' ?>>Classic Fiction</option>
          </select>
        </div>

        <div>
          <label class="text-[#2C241B] block mb-2 text-sm">Cover Image File</label>
          <input type="file" name="image" class="border border-gray-200 px-3 py-1 w-full rounded bg-gray-100 outline-none text-[13px]">
          <?php if (!empty($editBook->image)): ?>
            <span class="text-xs text-gray-500 mt-1 block">Current Image: <?= htmlspecialchars($editBook->image) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3 mb-3">
        <div>
          <label class="text-[#2C241B] block mb-2 text-sm">Status</label>
          <select name="status" class="border border-gray-200 px-3 py-1 h-8 w-full rounded bg-gray-100 text-[13px] text-gray-500">
            <?php $status = $editBook->status ?? ''; ?>
            <option value="New" <?= $status === 'New' ? 'selected' : '' ?>>New</option>
            <option value="Reading" <?= $status === 'Reading' ? 'selected' : '' ?>>Reading</option>
            <option value="Completed" <?= $status === 'Completed' ? 'selected' : '' ?>>Completed</option>
          </select>
        </div>

        <div>
          <label class="text-[#2C241B] block mb-2 text-sm">Rating</label>
          <select name="stars" class="border border-gray-200 px-3 py-1 h-8 w-full rounded bg-gray-100 text-[13px] text-gray-500">
            <?php $stars = $editBook->stars ?? ''; ?>
            <option value="">Select rating</option>
            <option value="(1)" <?= $stars === '(1)' ? 'selected' : '' ?>>(1)</option>
            <option value="(2)" <?= $stars === '(2)' ? 'selected' : '' ?>>(2)</option>
            <option value="(3)" <?= $stars === '(3)' ? 'selected' : '' ?>>(3)</option>
            <option value="(4)" <?= $stars === '(4)' ? 'selected' : '' ?>>(4)</option>
            <option value="(5)" <?= $stars === '(5)' ? 'selected' : '' ?>>(5)</option>
          </select>
        </div>

        <div>
          <label class="text-[#2C241B] block mb-2 text-sm">Source</label>
          <textarea class="resize-none border border-gray-200 bg-gray-100 rounded w-full px-3 py-2 outline-none placeholder:text-[13px]" name="source" rows="3"><?= htmlspecialchars($editBook->source ?? '') ?></textarea>
        </div>

        <div>
          <label class="text-[#2C241B] block mb-2 text-sm">Notes</label>
          <textarea class="resize-none border border-gray-200 bg-gray-100 rounded w-full px-3 py-2 outline-none placeholder:text-[13px]" name="notes" placeholder="Add your personal notes about this book..." rows="3"><?= htmlspecialchars($editBook->notes ?? '') ?></textarea>
        </div>

      </div>

      

      <!-- Cleaned up modal-action without nested <form> -->
      <div class="modal-action mt-4 flex justify-end gap-2">
        <a href="index.php" class="btn text-xs px-4 py-2 bg-gray-200 rounded">Cancel</a>
        <button type="submit" name="bookEditBtn" class="btn bg-black text-white text-xs px-4 py-2 rounded">
          Update
        </button>
      </div>
   </div>
  </form>
</body>