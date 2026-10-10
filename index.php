<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once('./layout/header.php');


$mode = $_GET['mode'] ?? 'home';


if ($mode === 'signin') {

    require_once('./login.php');
    exit();
}


if (empty($_SESSION['user_id'])) {

    header("Location: index.php?mode=signin");
    exit();
}


$user_id = $_SESSION['user_id'];


// Handle Category Update
if (isset($_POST['updateCategoryBtn'])) {

    $id = $_POST['id'] ?? '';
    $name = trim($_POST['name'] ?? '');

    if (empty($id) || empty($name)) {
        die("Category ID and name are required.");
    }

    // Get the current category image
    $stmt = $db->prepare("SELECT image FROM categories WHERE id = ?");
    $stmt->execute([$id]);

    $category = $stmt->fetch(PDO::FETCH_OBJ);

    if (!$category) {
        die("Category not found.");
    }

    // Keep the old image by default
    $imageName = $category->image;

    // If a new image was selected
    if (
        isset($_FILES['image']) &&
        $_FILES['image']['error'] === UPLOAD_ERR_OK
    ) {

        $tmpName = $_FILES['image']['tmp_name'];
        $originalName = $_FILES['image']['name'];

        // Get extension
        $extension = strtolower(
            pathinfo($originalName, PATHINFO_EXTENSION)
        );

        // Allowed image types
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($extension, $allowedExtensions)) {
            die("Invalid image type.");
        }

        // Create a unique filename
        $imageName = uniqid('category_', true) . '.' . $extension;

        // Destination
        $uploadPath = __DIR__ . '/images/' . $imageName;

        // Move uploaded image
        if (!move_uploaded_file($tmpName, $uploadPath)) {
            die("Failed to upload image.");
        }

        // Delete old image if it exists
        if (
            !empty($category->image) &&
            file_exists(__DIR__ . '/images/' . $category->image)
        ) {
            unlink(__DIR__ . '/images/' . $category->image);
        }
    }

    // Update database
    $stmt = $db->prepare("
        UPDATE categories
        SET name = ?, image = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $name,
        $imageName,
        $id
    ]);

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

#category details
$categoryId = $_GET['category_id'] ?? null;
$category = null;

if ($categoryId) {
    $stmt = $db->prepare("SELECT * FROM categories WHERE id = :id");
    $stmt->execute([':id' => $categoryId]);
    $category = $stmt->fetch(PDO::FETCH_OBJ);
}

# Fetch all categories
$stmt = $db->prepare("SELECT * FROM categories");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_OBJ);

# Calculate total books/quantity across all categories
$totalCount = array_sum(array_column($categories, 'quantity'));

# Get books

// GET CURRENT LOGGED-IN USER
$user_id = $_SESSION['user_id'];


// SHOW ONLY THIS USER'S BOOKS
$stmt = $db->prepare("
    SELECT *
    FROM books
    WHERE user_id = :user_id
");

$stmt->execute([
    ':user_id' => $user_id
]);

$books = $stmt->fetchAll(PDO::FETCH_OBJ);

// DELETE BOOK

if (isset($_POST['deleteBookBtn'])) {

    $bookId = $_POST['book_id'] ?? null;

    if (!empty($bookId)) {

        // Get the book image

        $selectStmt = $db->prepare("
            SELECT image
            FROM books
            WHERE id = :id
            AND user_id = :user_id
        ");

        $selectStmt->execute([
            ':id' => $bookId,
            ':user_id' => $user_id
        ]);

        $book = $selectStmt->fetch(PDO::FETCH_OBJ);


        
        // Book belongs to this user

        if ($book) {

            // Delete the book
            $stmt = $db->prepare("
                DELETE FROM books
                WHERE id = :id
                AND user_id = :user_id
            ");

            $result = $stmt->execute([
                ':id' => $bookId,
                ':user_id' => $user_id
            ]);


        
            // Delete image

            if ($result) {

                if (
                    !empty($book->image) &&
                    file_exists("images/$book->image")
                ) {

                    unlink("images/$book->image");
                }

                header("Location: index.php");
                exit();
            }
        }
    }
}


?>
<body>
  <?php
  require_once('./layout/navbar.php');
  ?>

    <div class="container mx-auto my-6 px-5 py-3 shadow shadow-gray-300 w-283 rounded-lg">
      
      <div class="max-w-4xl mx-auto">
        <div class="relative">
          
          <!-- Search Icon -->
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute left-42 top-1/2 -translate-y-1/2 text-gray-500 w-5 h-5">
            <path d="m21 21-4.34-4.34"/>
            <circle cx="10" cy="10" r="7"/>
          </svg>

          <!-- Search Input -->
          <input type="text" id="search" placeholder="Discover books by title, author, or genre..." class="w-150 mx-auto block h-8 bg-gray-100 rounded-lg pl-14 pr-4 text-base text-gray-700 placeholder:text-gray-500 outline-none placeholder:text-[13px] focus:border-gray-400 focus:ring-2 focus:ring-gray-200">

        </div>
      </div>
    </div>

    <!-- CATEGORY FILTER CONTAINER -->
    <div class="container mx-auto my-6 p-4 shadow shadow-gray-300 w-283 rounded-lg mb-10">
      <div>
        <div class="flex justify-between items-center">
          <h5 class="flex items-center gap-2 text-[15px] font-medium mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-book-open-icon lucide-book-open text-gray-500"><path d="M12 5v16"/><path d="M20.001 19A2 2 0 0022 17V5a2 2 0 00-1.999-2L16 3.002A5 5 0 0012 5a5 5 0 00-4-2H4a2 2 0 00-2 2v12a2 2 0 001.999 2H8a5 5 0 014 2 5 5 0 014-2z"/></svg>
            Browse by Category
          </h5>

          <!-- Dropdown -->
          <div class="dropdown dropdown-end text-right mb-7">
            <button tabindex="0" class="btn btn-ghost btn-sm">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-ellipsis-icon lucide-ellipsis"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
            </button>

            <!-- Category Dropdown Actions -->
            <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box w-42 p-2 shadow-lg">
              <li>
                <button type="button" class="flex items-center gap-3 text-black text-xs" onclick="edit_category_modal.showModal()">
                  <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-pencil"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/></svg>
                  Edit
                </button>
              </li>
              
            </ul>
          </div>

          <!-- EDIT CATEGORY MODAL -->
          <dialog id="edit_category_modal" class="modal">
            <div class="modal-box w-11/12 max-w-xl">
              <h3 class="text-lg font-semibold mb-5">Edit Category</h3>

              <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="id" id="edit_category_id">

                <div class="grid grid-cols-2 gap-4 mb-6">
                  <div>
                    <label class="text-[#2C241B] block mb-2 text-sm">Category</label>
                      <select name="category_id" id="edit_category_select" onchange="populateCategoryFields(this.value)" class="border border-gray-200 px-3 py-2 w-full rounded bg-gray-100 outline-none text-[13px] text-gray-600">

                      <option value="">Choose category</option>

                      <?php foreach ($categories as $cat): ?>
                          <option value="<?= $cat->id ?>">
                              <?= htmlspecialchars($cat->name) ?>
                          </option>
                      <?php endforeach; ?>
                      </select>
                  </div>

                  <div>
                    <label class="text-[#2C241B] block mb-2 text-sm">Name</label>
                    <input type="text" name="name" id="edit_category_name" class="border border-gray-200 px-3 py-2 w-full rounded bg-gray-100 outline-none text-[13px]">
                  </div>

                  <div>
                    <label class="text-[#2C241B] block mb-2 text-sm">Image</label>
                    <input type="file" name="image" id="edit_category_icon" class="border border-gray-200 px-3 py-2 w-full rounded bg-gray-100 outline-none text-[13px]">
                  </div>

                  <!-- <div>
                    <label class="text-[#2C241B] block mb-2 text-sm">Quantity</label>
                    <input type="number" name="quantity" id="edit_category_quantity" class="border border-gray-200 px-3 py-2 w-full rounded bg-gray-100 outline-none text-[13px]">
                  </div> -->
                </div>

                <div class="modal-action">
                  <button type="button" onclick="edit_category_modal.close()" class="btn text-xs">Cancel</button>
                  <button type="submit" name="updateCategoryBtn" class="btn bg-black text-white text-xs">Update</button>
                </div>
              </form>
            </div>

            <form method="dialog" class="modal-backdrop">
              <button>close</button>
            </form>
          </dialog>
        </div>

        <!-- Category Buttons -->
        <div class="grid grid-cols-7 gap-3 w-280">
          <button type="button" data-category="all" class="category-btn bg-black hover:scale-105 duration-150 text-white border border-gray-200 flex justify-between items-center gap-2 px-2 h-8 w-37 rounded-lg text-[12px] cursor-pointer">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-book-open-icon lucide-book-open"><path d="M12 5v16"/><path d="M20.001 19A2 2 0 0022 17V5a2 2 0 00-1.999-2L16 3.002A5 5 0 0012 5a5 5 0 00-4-2H4a2 2 0 00-2 2v12a2 2 0 001.999 2H8a5 5 0 014 2 5 5 0 014-2z"/></svg>
            <span class="category-name">All Books</span>
            <span class="w-4 h-4 rounded-md bg-gray-50 text-black text-xs flex justify-center items-center shadow shadow-gray-300">
              <?= $totalBooks ?>
            </span>
          </button>

          <?php foreach($categories as $category): ?>
            <button type="button" data-category="<?= htmlspecialchars(strtolower(trim($category->name))) ?>" class="category-btn bg-white hover:scale-105 duration-150 hover:bg-gray-200 text-black border border-gray-200 flex justify-between items-center px-2 h-8 w-37 rounded-lg text-[12px] cursor-pointer">
              <img src="images/<?= htmlspecialchars($category->image) ?>" alt="<?= htmlspecialchars($category->name) ?>" class="w-5 h-5 object-cover rounded-lg">
              <span class="category-name"><?= htmlspecialchars($category->name) ?></span>
              <span class="w-4 h-4 rounded-md bg-gray-50 text-black text-xs flex justify-center items-center shadow shadow-gray-300"><?= $category->quantity ?></span>
            </button>
          <?php endforeach; ?>
        </div>
      </div>
    </div>


      <!-- BOOKS GRID -->
  <div class="container mx-auto my-6 mb-8 w-283">
    <!-- Filter Result Summary -->
      <div id="filter-summary-container" style="display: none;" class="flex flex-col items-center justify-center text-center mt-8 mb-8 w-full">
        <p class="text-sm text-gray-400">
          Found <span id="found-count" class="text-black">0</span> book(s) in <span id="found-category" class="text-black"></span>
        </p>
        <button type="button" id="clear-filters-btn" class="text-xs text-gray-400 underline mt-1 hover:text-black cursor-pointer">
          Clear all filters
        </button>
      </div>

    <div class="grid grid-cols-4 gap-4">
      <?php foreach($books as $book): ?>
        <div class="book-card shadow shadow-gray-300 px-6 py-5 flex flex-col rounded-lg w-66"
            data-search="<?= htmlspecialchars(strtolower($book->title . ' ' . $book->author . ' ' . $book->genre)) ?>"
            data-genre="<?= htmlspecialchars(strtolower(trim($book->genre))) ?>">
          <div class="flex justify-between">
            <a class="px-2 py-1 h-6 bg-black text-white rounded-lg text-[10px]"><?= htmlspecialchars($book->status) ?></a>

            <div class="dropdown dropdown-end text-right mb-7">
              <button tabindex="0" class="btn btn-ghost btn-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-ellipsis-vertical-icon lucide-ellipsis-vertical"><circle cx="12" cy="12" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/></svg>
              </button>

              <form method="post">

                <input type="hidden" name="book_id" value="<?= $book->id ?>">

                <ul tabindex="0"
                    class="dropdown-content menu bg-base-100 rounded-box w-42 p-2 shadow-lg">

                    <!-- EDIT -->
                    <li>
                        <a href="book-edit.php?book_id=<?= (int)$book->id ?>"
                          class="flex items-center gap-3 text-black text-xs">

                            <svg xmlns="http://www.w3.org/2000/svg"
                                width="15"
                                height="15"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round">

                                <path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>

                                <path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"/>

                            </svg>

                            Edit
                        </a>
                    </li>


                    <!-- DELETE -->
                    <li>
                        <button type="submit"
                                name="deleteBookBtn"
                                class="text-error text-xs flex items-center gap-3">

                            <svg xmlns="http://www.w3.org/2000/svg"
                                width="15"
                                height="15"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round">

                                <path d="M10 11v6"/>
                                <path d="M14 11v6"/>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/>
                                <path d="M3 6h18"/>
                                <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>

                            </svg>

                            Delete
                        </button>
                    </li>

                </ul>

            </form>
            </div>
          </div>
      
          <div class="mx-auto w-30 h-40 rounded-lg mb-2">
            <img src="images/<?= htmlspecialchars($book->image) ?>" alt="" class="w-full h-full object-cover rounded-lg">
          </div>

          <div class="mb-4">
            <h1 class="text-[15px] font-semibold"><?= htmlspecialchars($book->title) ?></h1>
            <span class="text-sm text-zinc-400">by <?= htmlspecialchars($book->author) ?></span>
          </div>

          <a href="" class="px-2.5 py-0.5 w-26 shadow border border-gray-300 text-[12px] font-medium rounded-lg mb-3"><?= htmlspecialchars($book->genre) ?></a>

          <div class="flex items-center gap-2 mb-3">
            <div class="flex items-center">
              <?php 
                $rating = (int) filter_var($book->stars ?? '', FILTER_SANITIZE_NUMBER_INT);
                if ($rating > 0): 
                  for ($i = 1; $i <= 5; $i++): 
              ?>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 <?= $i <= $rating ? 'text-yellow-400' : 'text-gray-300' ?>">
                  <path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z" clip-rule="evenodd" />
                </svg>
              <?php 
                  endfor; 
                endif; 
              ?>
            </div>
            
            <?php if ($rating > 0): ?>
              <span class="text-sm text-gray-500"><?= htmlspecialchars($book->stars) ?></span>
            <?php endif; ?>
          </div>

          <span class="text-[12px] text-gray-400 font-medium mb-1">Notes:</span>
          <p class="text-xs mb-8"><?= htmlspecialchars($book->notes) ?></p>

          <div class="flex justify-between items-center">
            <h5 class="text-[11px] text-gray-400">Added <?= htmlspecialchars($book->created_at) ?></h5>
            <div class="flex justify-between items-center gap-2">
              
                <button type="button" class="btn btn-ghost w-7 h-7 rounded-full" popovertarget="my-modal-<?= $book->id ?>" title="Collaborator">
                  <div class="flex justify-center items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-plus-icon lucide-user-plus"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/></svg>
                  </div>
                </button>
                <div class="modal" id="my-modal-<?= $book->id ?>" popover>
                  <div class="modal-box">
                    <h3 class="font-semibold text-lg mb-2">Collaborators</h3>
                      <hr>
                    <div class="mt-5">
                      <div class="w-8 h-8 rounded-full border flex items-center gap-4 mb-5">
                        <img src="images/coll.png" alt="" class="w-full h-full object-cover rounded-full">
                        <div>
                          <h2 class="text-[12px] font-bold tracking-wide">Ruby(Owner)</h2>
                          <h4 class="text-xs text-zinc-500">ruby@gmail.com</h4>
                        </div>
                      </div>
                      <div class="w-8 h-8 rounded-full border flex items-center gap-4">
                        <img src="images/coll.png" alt="" class="w-full h-full object-cover rounded-full">
                        
                          <input type="text" placeholder="Person or email to share with" class="placeholder:text-[12px] text-zinc-500 outline-none flex-1" id="my-modal-<?= $book->id ?>">
                        
                      </div>
                    </div>
                    <div class="modal-action">
                      <button type="button" class="btn w-12 h-6 text-xs" popovertarget="my-modal-<?= $book->id ?>" popovertargetaction="hide">
                          Cancel
                      </button>
                      <button type="button" class="btn w-12 h-6 text-xs bg-black text-white" onclick="saveCollaborator(<?= $book->id ?>)">
                          Save
                      </button>
                    </div>
                  </div>
                </div>




              
              <a href="<?= htmlspecialchars($book->source) ?>" class="underline text-[11px] text-blue-950" target="_blank">Source</a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

    <script>
      // Get categories from PHP
      const categoriesList = <?= json_encode($categories) ?>;


      // Fill the category edit form
      function populateCategoryFields(selectedId) {

        let match = null;

        for (let i = 0; i < categoriesList.length; i++) {

            if (categoriesList[i].id == selectedId) {
                match = categoriesList[i];
                break;
            }
        }

        if (match) {

            document.getElementById('edit_category_id').value = match.id;
            document.getElementById('edit_category_name').value = match.name;

            // DO NOT set the file input value
            document.getElementById('edit_category_icon').value = '';

        } else {

            document.getElementById('edit_category_id').value = '';
            document.getElementById('edit_category_name').value = '';

            // Clear file input
            document.getElementById('edit_category_icon').value = '';
        }
    }

      // Run when page is loaded
      document.addEventListener('DOMContentLoaded', function () {

        const categoryButtons = document.querySelectorAll('.category-btn');
        const bookCards = document.querySelectorAll('.book-card');

        const summaryContainer = document.getElementById('filter-summary-container');
        const foundCount = document.getElementById('found-count');
        const foundCategory = document.getElementById('found-category');
        const clearFiltersBtn = document.getElementById('clear-filters-btn');


        // Hide summary when page first loads
        if (summaryContainer) {
          summaryContainer.style.display = 'none';
        }


        // When a category button is clicked
        categoryButtons.forEach(function (button) {

          button.addEventListener('click', function () {

            const selectedCategory = button.getAttribute('data-category');

            const categoryName = button.querySelector('.category-name');

            let categoryLabel = '';

            if (categoryName) {
              categoryLabel = categoryName.textContent.trim();
            }


            // Change all buttons back to white
            categoryButtons.forEach(function (btn) {

              btn.classList.remove('bg-black');
              btn.classList.remove('text-white');

              btn.classList.add('bg-white');
              btn.classList.add('text-black');

            });


            // Make clicked button black
            button.classList.remove('bg-white');
            button.classList.remove('text-black');

            button.classList.add('bg-black');
            button.classList.add('text-white');


            // Show/hide books
            let visibleCount = 0;


            bookCards.forEach(function (card) {

              const cardGenre = card.getAttribute('data-genre');


              if (selectedCategory == 'all') {

                card.style.display = 'flex';
                visibleCount++;

              } else if (cardGenre == selectedCategory) {

                card.style.display = 'flex';
                visibleCount++;

              } else {

                card.style.display = 'none';

              }

            });


            // Show category information
            if (selectedCategory == 'all') {

              summaryContainer.style.display = 'none';

            } else {

              foundCount.textContent = visibleCount;
              foundCategory.textContent = categoryLabel;

              summaryContainer.style.display = 'flex';

            }

          });

        });


        // Clear filter button
        if (clearFiltersBtn) {

          clearFiltersBtn.addEventListener('click', function () {

            const allBooksButton = document.querySelector(
              '.category-btn[data-category="all"]'
            );

            if (allBooksButton) {
              allBooksButton.click();
            }

          });

        }

      });

    </script>


    <!-- Searchbar -->
    <script>
      const search = document.getElementById("search");
      const books = document.querySelectorAll(".book-card");

      search.addEventListener("input", function() {

          let text = search.value.toLowerCase();

          books.forEach(function(book) {

              let data = book.getAttribute("data-search").toLowerCase();

              if (data.includes(text)) {
                  book.style.display = "block";
              } else {
                  book.style.display = "none";
              }

          });

      });
      </script>
</body>
</html>
