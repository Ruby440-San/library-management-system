<?php
//Sign Out
if (isset($_POST['signOutBtn'])) {

    session_destroy();
    header('location:login.php');
    exit;
}



$userName = $_SESSION['user_name'] ?? 'No User';
$user_id = $_SESSION['user_id'] ?? null;


//get short name
$userInitials = strtoupper(
    substr($userName, 0, 1)
);


$totalBooks = $totalBooks ?? 0;
$completedCount = $completedCount ?? 0;
$readingCount = $readingCount ?? 0;


$bookId = $bookId ?? null;
$book = $book ?? null;



function getInitials($name)
{
    $words = explode(' ', trim($name));

    $initials = '';

    foreach ($words as $w) {

        if (!empty($w)) {

            $initials .= strtoupper($w[0]);
        }
    }

    return substr($initials, 0, 2);
}
$userInitials = getInitials($userName);

// CREATE CATEGORY
if (isset($_POST['addCategoryBtn'])) {

    $name = $_POST['name'] ?? '';
    $quantity = $_POST['quantity'] ?? 0;

    $imageName = '';

    // FILE UPLOAD
    if (
        isset($_FILES['image']) &&
        $_FILES['image']['error'] === UPLOAD_ERR_OK
    ) {
        $originalName = $_FILES['image']['name'];
        $imageTmpName = $_FILES['image']['tmp_name'];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $imageTmpName);
        finfo_close($finfo);

        if (in_array($mimeType, [
            'image/png',
            'image/jpeg',
            'image/jpg'
        ])) {

            $newImageName = uniqid() . '_' . basename($originalName);

            if (!is_dir('images')) {
                mkdir('images', 0755, true);
            }

            if (move_uploaded_file(
                $imageTmpName,
                "images/$newImageName"
            )) {
                $imageName = $newImageName;
            }
        }
    }

    $stmt = $db->prepare("
        INSERT INTO categories
        (user_id, name, image, quantity)
        VALUES
        (:user_id, :name, :image, :quantity)
    ");

    $stmt->execute([
        ':user_id' => $user_id,
        ':name' => $name,
        ':image' => $imageName,
        ':quantity' => $quantity
    ]);

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// FETCH CATEGORIES
// ONLY FOR CURRENT USER
$sql = "SELECT c.id, c.name, c.image, COUNT(b.id) AS quantity 
        FROM categories c 
        LEFT JOIN books b ON LOWER(TRIM(b.genre)) = LOWER(TRIM(c.name)) AND b.user_id = :book_user_id
        WHERE c.user_id = :category_user_id 
        GROUP BY c.id, c.name, c.image 
        ORDER BY c.name ASC";

$stmtCategories = $db->prepare($sql);
$stmtCategories->execute([

    ':book_user_id' => $user_id,

    ':category_user_id' => $user_id

]);
$categories = $stmtCategories->fetchAll(PDO::FETCH_OBJ);

// TOTAL BOOKS
// ONLY CURRENT USER
$stmtTotal = $db->prepare("SELECT COUNT(*) FROM books WHERE user_id = :user_id");
$stmtTotal->execute([':user_id' => $user_id]);
$totalBooks = $stmtTotal->fetchColumn();

$stmtNew = $db->prepare("

    SELECT COUNT(*)

    FROM books

    WHERE status = :status

    AND user_id = :user_id

");
$stmtNew->execute([':status' => 'New',':user_id' => $user_id]);
$newCount = $stmtNew->fetchColumn();

$stmtCompleted = $db->prepare("

    SELECT COUNT(*)

    FROM books

    WHERE status = :status

    AND user_id = :user_id

");
$stmtCompleted->execute([':status' => 'Completed',':user_id' => $user_id]);
$completedCount = $stmtCompleted->fetchColumn();


$stmtActiveBooks = $db->prepare("

    SELECT COUNT(b.id)

    FROM books b

    INNER JOIN categories c

        ON LOWER(TRIM(b.genre)) =
           LOWER(TRIM(c.name))

        AND c.user_id = :category_user_id

    WHERE b.user_id = :book_user_id

");

$stmtActiveBooks->execute([':category_user_id' => $user_id,':book_user_id' => $user_id]);
$totalBooks = $stmtActiveBooks->fetchColumn();


// READING BOOKS
// ONLY CURRENT USER

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


// CREATE / UPDATE BOOK

if (isset($_POST['addNewBtn'])) {

    $title = $_POST['title'] ?? '';

    $author = $_POST['author'] ?? '';

    $genre = $_POST['genre'] ?? '';

    $status = $_POST['status'] ?? '';

    $stars = $_POST['stars'] ?? '';

    $notes = $_POST['notes'] ?? '';

    $created_at = date('Y-m-d');

    $source = $_POST['source'];


    // FILE UPLOAD
    $imageName = $book->image ?? '';
    if (
        isset($_FILES['image']) &&
        $_FILES['image']['error'] === UPLOAD_ERR_OK
    ) {
        $originalName = $_FILES['image']['name'];

        $imageTmpName = $_FILES['image']['tmp_name'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $imageTmpName);
        finfo_close($finfo);

        if (
            in_array(
                $mimeType,
                [
                    'image/png',
                    'image/jpg',
                    'image/jpeg'
                ]
            )
        ) {
            $newImageName =
                uniqid() . '_' . basename($originalName);


            if (!is_dir('images')) {

                mkdir('images', 0755, true);

            }

            if (
                move_uploaded_file(
                    $imageTmpName,
                    "images/$newImageName"
                )
            ) {



                // Delete old image if replacing it

                if (
                    $bookId &&
                    !empty($book->image) &&
                    file_exists("images/$book->image")
                ) {

                    unlink("images/$book->image");

                }



                $imageName = $newImageName;

            }

        }

    }

    // UPDATE EXISTING BOOK

    if ($bookId) {
        $oldGenre = $book->genre ?? '';

        $stmt = $db->prepare("

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
        $stmt->execute([

            ':title' => $title,

            ':author' => $author,

            ':genre' => $genre,

            ':image' => $imageName,

            ':status' => $status,

            ':stars' => $stars,

            ':notes' => $notes,

            ':source' => $source,

            ':id' => $bookId,

            ':user_id' => $user_id

        ]);

        // IF GENRE CHANGED
        if ($oldGenre !== $genre) {

            // DECREASE OLD CATEGORY
            // ONLY CURRENT USER

            if (!empty($oldGenre)) {



                $stmtDec = $db->prepare("

                    UPDATE categories

                    SET quantity =
                        GREATEST(0, quantity - 1)

                    WHERE LOWER(TRIM(name)) =
                          LOWER(TRIM(:oldGenre))

                    AND user_id = :user_id

                ");



                $stmtDec->execute([

                    ':oldGenre' => $oldGenre,

                    ':user_id' => $user_id

                ]);

            }
            // INCREASE NEW CATEGORY
            // ONLY CURRENT USER

            if (!empty($genre)) {



                $stmtInc = $db->prepare("

                    UPDATE categories

                    SET quantity = quantity + 1

                    WHERE LOWER(TRIM(name)) =
                          LOWER(TRIM(:genre))

                    AND user_id = :user_id

                ");
                $stmtInc->execute([

                    ':genre' => $genre,

                    ':user_id' => $user_id

                ]);

            }

        }
    } else {

        // INSERT NEW BOOK

        $stmt = $db->prepare("INSERT INTO books (title, author, genre, image, status, stars, notes, user_id, created_at,source) VALUES(:title, :author, :genre, :image, :status, :stars, :notes, :user_id, :created_at, :source)");
        $stmt->execute([
        ':title' => $title,
        ':author' => $author,
        ':genre' => $genre,
        ':image' => $imageName,
        ':status' => $status,
        ':stars' => $stars,
        ':notes' => $notes,
        ':user_id' => $user_id,
        ':created_at' => date('Y-m-d H:i:s'),
        ':source' => $source,
    ]);
        // INCREASE CATEGORY QUANTITY
        // ONLY CURRENT USER

        if (!empty($genre)) {

            $stmtCategory = $db->prepare("

                UPDATE categories

                SET quantity = quantity + 1

                WHERE LOWER(TRIM(name)) =
                      LOWER(TRIM(:genre))

                AND user_id = :user_id

            ");



            $stmtCategory->execute([

                ':genre' => $genre,

                ':user_id' => $user_id

            ]);

        }

    }

    header("Location: " . $_SERVER['PHP_SELF']);

    exit;

}

?>

<nav class="container mx-auto my-6 px-5 py-3 shadow shadow-gray-300 w-283 rounded-lg">
  <div class="flex justify-between items-center">
    <div class="flex items-center">
      <div class="w-20 h-15 hover:scale-107 duration-150">
        <a href="index.php">
          <img src="images/navlogo.jpg" alt="Logo" class="w-full h-full object-cover overflow-hidden">
        </a>
      </div>

      <div>
        <a href="index.php">
          <h1 class="text-2xl font-bold">MyLIB</h1>
          <span class="text-sm text-zinc-500 font-semibold">My Library</span>
        </a>
      </div>

      <div class="ml-5">
        <h4 class="text-zinc-500 mb-2">Organize, discover, and cherish your personal book collection</h4>
        <div class="flex items-center gap-2">
          <a class="flex justify-between border border-gray-400 rounded-md h-5 px-2 py-0.5 bg-gray-300 w-20 text-xs hover:bg-gray-200 hover:scale-105 duration-150">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-book"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H19a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H6.5a1 1 0 0 1 0-5H20"/></svg>
            <?= $totalBooks ?> Total
          </a>
          <a class="flex justify-between border border-purple-300 rounded-md h-5 px-2 py-0.5 bg-purple-100 w-20 text-purple-600 text-xs hover:bg-purple-200 hover:scale-105 duration-150">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-newspaper-icon lucide-newspaper"><path d="M15 18h-5"/><path d="M18 14h-8"/><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-4 0v-9a2 2 0 0 1 2-2h2"/><rect width="8" height="4" x="10" y="6" rx="1"/></svg>
            <?= $newCount ?> New
          </a>
          <a class="flex justify-between border border-green-300 rounded-md h-5 px-2 py-0.5 bg-green-100 w-28 text-green-600 text-xs hover:bg-green-200 hover:scale-105 duration-150">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trending-up"><path d="M16 7h6v6"/><path d="m22 7-8.5 8.5-5-5L2 17"/></svg>
            <?= $completedCount ?> Completed
          </a>
          <a class="flex justify-between border border-orange-300 rounded-md h-5 px-2 py-0.5 bg-orange-50 w-35 text-orange-600 text-xs hover:bg-orange-200 hover:scale-105 duration-150">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-heart"><path d="M2 9.5a5.5 5.5 0 0 1 9.591-3.676.56.56 0 0 0 .818 0A5.49 5.49 0 0 1 22 9.5c0 2.29-1.5 4-3 5.5l-5.492 5.313a2 2 0 0 1-3 .019L5 15c-1.5-1.5-3-3.2-3-5.5"/></svg>
            <?= $readingCount ?> Currently reading
          </a>
        </div>
      </div>
    </div>

    <div class="flex gap-2">
      <!-- Category Open Button -->
      <button type="button" class="btn bg-black w-6 h-8 text-[13px] rounded-md text-white hover:scale-105 duration-150" onclick="add_category_modal.showModal()" title="Add new category">
        <span class="flex items-center gap-3">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-list-sort-ascending"><path d="M3 19h18"/><path d="M15 12H3"/><path d="M9 5H3"/></svg>
        </span>
      </button>

      <!-- Category Modal -->
      <dialog id="add_category_modal" class="modal">
        <div class="modal-box w-11/12 max-w-xl">
          <h3 class="text-lg font-semibold mb-2">Add New Category</h3>
          <form method="post" enctype="multipart/form-data">
            <div class="grid grid-cols-2 gap-2 mb-3">
              <div>
                <label class="text-[#2C241B] block mb-2 text-sm">Name</label>
                <input type="text" name="name" class="border border-gray-200 px-3 py-1 w-full rounded bg-gray-100 outline-none placeholder:text-[13px]" placeholder="Enter name..">
              </div>
              <div>
                <label class="text-[#2C241B] block mb-2 text-sm">Image</label>
                <input type="file" name="image" class="border border-gray-200 px-3 py-1 w-full rounded bg-gray-100 outline-none text-[13px]
           file:text-[12px] file:border-0 file:bg-gray-200 file:px-2 file:py-1 file:rounded">
              </div>
              <!-- <div>
                <label class="text-[#2C241B] block mb-2 text-sm">Quantity</label>
                <input type="number" name="quantity" class="border border-gray-200 px-3 py-1 w-full rounded bg-gray-100 outline-none placeholder:text-[13px]" placeholder="Enter quantity" required>
              </div> -->
            </div>
            <div class="modal-action">
              <button type="button" class="btn text-xs" onclick="add_category_modal.close()">Cancel</button>
              <button type="submit" name="addCategoryBtn" class="btn bg-black text-white text-xs">Add Category</button>
            </div>
          </form>
        </div>
      </dialog>

      <!-- Book Open Button -->
      <button type="button" class="btn group bg-black h-8 w-40 text-[13px] rounded-md text-white hover:scale-108 hover:shadow-2xl hover:shadow-gray-900 duration-300" onclick="add_book_modal.showModal()">
        <span class="flex items-center gap-3">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus duration-300 transition-transform group-hover:rotate-180"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
          <?= $bookId ? 'Add New Book' : 'Add New Book' ?>
        </span>
      </button>
      

      <!-- Book Modal -->
      <dialog id="add_book_modal" class="modal">
        <div class="modal-box w-11/12 max-w-xl min-h-120">
          <h3 class="text-lg font-semibold mb-2"><?= $bookId ? 'Add New Book' : 'Add New Book' ?></h3>
          <form method="post" enctype="multipart/form-data">
            <div class="grid grid-cols-2 gap-2 mb-3">
              <div>
                <label class="text-[#2C241B] block mb-2 text-sm">Title *</label>
                <input type="text" name="title" value="<?= htmlspecialchars($book->title ?? '') ?>" class="border border-gray-200 px-3 py-1 w-full rounded bg-gray-100 outline-none placeholder:text-[13px]" placeholder="Enter book title" required>
              </div>

              <div>
                <label class="text-[#2C241B] block mb-2 text-sm">Author *</label>
                <input type="text" name="author" value="<?= htmlspecialchars($book->author ?? '') ?>" class="border border-gray-200 px-3 py-1 w-full rounded bg-gray-100 outline-none placeholder:text-[13px]" placeholder="Enter author name" required>
              </div>
<!-- .......................................................................... -->
                <div>
                  <label class="text-[#2C241B] block mb-2 text-sm">Category</label>
                    <select name="genre" id="edit_category_select" class="border border-gray-200 px-3 py-2 w-full rounded bg-gray-100 outline-none text-[13px] text-gray-600">

                        <option value="">Choose category</option>

                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat->name) ?>"
                                <?= ($book->genre ?? '') === $cat->name ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat->name) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

              <div>
                <label class="text-[#2C241B] block mb-2 text-sm">Cover Image File</label>
                <input type="file" name="image" class="border border-gray-200 px-3 py-1 w-full rounded bg-gray-100 outline-none text-[13px]">
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3 mb-3">
              <div>
                <label class="text-[#2C241B] block mb-2 text-sm">Status</label>
                <select name="status" class="border border-gray-200 px-3 py-1 h-8 w-full rounded bg-gray-100 text-[13px] text-gray-500">
                  <?php $selectedStatus = $book->status ?? ''; ?>
                  <option value="New" <?= $selectedStatus === 'New' ? 'selected' : '' ?>>New</option>
                  <option value="Reading" <?= $selectedStatus === 'Reading' ? 'selected' : '' ?>>Reading</option>
                  <option value="Completed" <?= $selectedStatus === 'Completed' ? 'selected' : '' ?>>Completed</option>
                </select>
              </div>

              <div>
                <label class="text-[#2C241B] block mb-2 text-sm">Rating</label>
                <select name="stars" class="border border-gray-200 px-3 py-1 h-8 w-full rounded bg-gray-100 text-[13px] text-gray-500">
                  <?php $selectedStars = $book->stars ?? ''; ?>
                  <option value="">Select rating</option>
                  <option value="(1)" <?= $selectedStars === '(1)' ? 'selected' : '' ?>>(1)</option>
                  <option value="(2)" <?= $selectedStars === '(2) ' ? 'selected' : '' ?>>(2) </option>
                  <option value="(3)" <?= $selectedStars === '(3) ' ? 'selected' : '' ?>>(3) </option>
                  <option value="(4)" <?= $selectedStars === '(4) ' ? 'selected' : '' ?>>(4) </option>
                  <option value="(5)" <?= $selectedStars === '(5) ' ? 'selected' : '' ?>>(5) </option>
                </select>
              </div>

              <div>
                <label class="text-[#2C241B] block mb-2 text-sm">Source</label>
                <textarea class="resize-none border border-gray-200 bg-gray-100 rounded w-full px-3 py-2 outline-none placeholder:text-[13px]" name="source" placeholder="Enter a link" rows="3"><?= htmlspecialchars($book->source ?? '') ?></textarea>
              </div>

              <div>
                <label class="text-[#2C241B] block mb-2 text-sm">Notes</label>
                <textarea class="resize-none border border-gray-200 bg-gray-100 rounded w-full px-3 py-2 outline-none placeholder:text-[13px]" name="notes" placeholder="Add your personal notes about this book..." rows="3"><?= htmlspecialchars($book->notes ?? '') ?></textarea>
              </div>
            </div>

            <div class="modal-action">
              <button type="button" class="btn text-xs" onclick="add_book_modal.close()">Cancel</button>
              <button type="submit" name="addNewBtn" class="btn bg-black text-white text-xs"><?= $bookId ? 'Update Book' : 'Add Book' ?></button>
            </div>
          </form>
        </div>
      </dialog>

      <!-- Profile Dropdown -->
      <div class="dropdown dropdown-end">
        <div tabindex="0" role="button" class="inline-flex items-center gap-2 border border-gray-300 bg-white rounded-full p-1 pr-3 shadow-sm cursor-pointer hover:bg-gray-50">
          <div class="w-7 h-7 rounded-full bg-black text-white text-xs font-bold flex items-center justify-center">
            <?= htmlspecialchars($userInitials) ?>
          </div>
          <span class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($userName) ?></span>
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-500">
            <path d="m6 9 6 6 6-6"/>
          </svg>
        </div>

        <!-- Dropdown Menu -->
        <div tabindex="0" class="dropdown-content menu z-50 mt-2 p-1 shadow-xl bg-white border border-gray-100 rounded-2xl w-60 text-sm">
          
          <div class="flex items-center">
            

          <!-- Sign Out-->
            <form action="" method="post">
                <button name="signOutBtn" class="flex justify-between items-center gap-2 px-2 py-2 rounded-lg text-rose-700 hover:bg-rose-50 font-medium">
                  <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                  Logout
                </button>
              </form>
          </div>
        </div>
    </div>
  </div>
</nav>
