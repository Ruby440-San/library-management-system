<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once('./layout/header.php');

$mode = $_GET['mode'] ?? 'signup'; 
$error = '';
$success = '';

//SIGN UP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signupBtn'])) {

    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($name) && !empty($email) && !empty($password)) {

        $stmt = $db->prepare(
            "SELECT id FROM users WHERE email = ?"
        );

        $stmt->execute([$email]);

        if ($stmt->fetch()) {

            $error = "Email is already registered.";

        } else {

            $stmt = $db->prepare(
                "INSERT INTO users (name, email, password)
                 VALUES (?, ?, ?)"
            );

            if ($stmt->execute([
                $name,
                $email,
                $password
            ])) {

                $success = "Account created successfully! Please sign in.";

                $mode = 'signin';

            } else {

                $error = "Something went wrong. Please try again.";
            }
        }

    } else {

        $error = "Please fill in all fields.";
    }
} 


//SIGN IN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signinBtn'])) {

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($email) && !empty($password)) {

        $stmt = $db->prepare(
            "SELECT * FROM users
             WHERE email = ?
             AND password = ?"
        );

        $stmt->execute([
            $email,
            $password
        ]);

        $user = $stmt->fetch(PDO::FETCH_OBJ);

        if ($user) {

            $_SESSION['user_id']   = $user->id;
            $_SESSION['user_name'] = $user->name;
            $_SESSION['user_email'] = $user->email;

            header("Location: index.php");
            exit;

        } else {

            $error = "Invalid email or password.";
        }

    } else {

        $error = "Please fill in all fields.";
    }
}
?>

<script src="https://cdn.tailwindcss.com"></script>
<body>
  <div class="bg-[#f7f6f1] border p-5 min-h-screen flex flex-col justify-center items-center">

    <div class="flex justify-center items-center gap-2 mb-5">
      <a href="" class="p-2 bg-black text-white rounded-md">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-book-open-icon lucide-book-open"><path d="M12 5v16"/><path d="M20.001 19A2 2 0 0022 17V5a2 2 0 00-1.999-2L16 3.002A5 5 0 0012 5a5 5 0 00-4-2H4a2 2 0 00-2 2v12a2 2 0 001.999 2H8a5 5 0 014 2 5 5 0 014-2z"/></svg>
      </a>
      <h2 class="font-bold text-xl font-['Fraunces']">MyLIB</h2>
    </div>

    <div class="w-[450px] bg-white rounded-xl shadow p-5 pt-0 relative overflow-hidden">

      <div class="absolute top-0 left-0 right-0 flex h-2 overflow-hidden">
        <span class="flex-1 bg-amber-400"></span>
        <span class="flex-1 bg-teal-400"></span>
        <span class="flex-1 bg-orange-400"></span>
        <span class="flex-1 bg-purple-400"></span>
        <span class="flex-1 bg-rose-400"></span>
      </div>

      <!-- Mode Toggle Buttons -->
      <div class="w-[300px] h-9 bg-gray-100 rounded-lg p-1 mx-auto my-8 border border-gray-300 flex justify-between items-center gap-2">
        <a href="?mode=signin" class="w-1/2 h-7 text-sm font-semibold p-1 text-center rounded-md <?= $mode === 'signin' ? 'bg-black text-white' : 'text-gray-600' ?>">Sign in</a>
        <a href="?mode=signup" class="w-1/2 h-7 text-sm font-semibold p-1 text-center rounded-md <?= $mode === 'signup' ? 'bg-black text-white' : 'text-gray-600' ?>">Sign up</a>
      </div>

      <!-- Feedback Messages -->
      <?php if ($error): ?>
        <div class="mb-4 text-xs text-red-600 bg-red-100 p-2 rounded text-center"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="mb-4 text-xs text-green-600 bg-green-100 p-2 rounded text-center"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <!-- SIGN IN FORM -->
      <div class="<?= $mode === 'signup' ? 'hidden' : '' ?>">
        <div class="mx-auto text-center mb-4">
          <h2 class="font-['Fraunces'] font-bold text-2xl tracking-wide mb-1">Welcome back</h2>
          <h6 class="text-[13px] text-zinc-400">Sign in to open your private library.</h6>
        </div>

        <form action="?mode=signin" method="POST" class="px-5 pb-5">
          <div class="mb-3">
            <label class="text-[13px] font-medium block mb-1">Email</label>
            <input type="email" name="email" required class="border border-gray-300 rounded-lg px-3 py-1.5 bg-gray-100 w-full text-sm" placeholder="name@gmail.com">
          </div>
            
          <div class="mb-3">
            <label class="text-[13px] font-medium block mb-1">Password</label>
            <input type="password" name="password" required class="border border-gray-300 rounded-lg px-3 py-1.5 bg-gray-100 w-full text-sm" placeholder="Enter your password">
          </div>

          <button type="submit" name="signinBtn" class="w-full h-9 bg-black text-white text-center font-semibold text-sm rounded-lg border border-gray-300 block mb-4 hover:bg-gray-800">Sign in</button>

          <div class="flex justify-center items-center gap-1">
            <h5 class="text-xs text-zinc-400">New to MyLIB?</h5>
            <a href="?mode=signup" class="underline text-xs font-medium">Create an account</a>
          </div>
        </form>
      </div>

      <!-- SIGN UP FORM -->
      <div class="<?= $mode === 'signin' ? 'hidden' : '' ?>">
        <div class="mx-auto text-center mb-4">
          <h2 class="font-['Fraunces'] font-bold text-2xl tracking-wide mb-1">Create your library</h2>
          <h6 class="text-[13px] text-zinc-400">Free, private, and yours alone.</h6>
        </div>

        <form action="?mode=signup" method="POST" class="px-5 pb-5">
          <div class="mb-3">
            <label class="text-[13px] font-medium block mb-1">Full name</label>
            <input type="text" name="name" required class="border border-gray-300 rounded-lg px-3 py-1.5 bg-gray-100 w-full text-sm" placeholder="Your name">
          </div>
            
          <div class="mb-3">
            <label class="text-[13px] font-medium block mb-1">Email</label>
            <input type="email" name="email" required class="border border-gray-300 rounded-lg px-3 py-1.5 bg-gray-100 w-full text-sm" placeholder="name@gmail.com">
          </div>

          <div class="mb-3">
            <label class="text-[13px] font-medium block mb-1">Password</label>
            <input type="password" name="password" required class="border border-gray-300 rounded-lg px-3 py-1.5 bg-gray-100 w-full text-sm" placeholder="Enter your password">
          </div>

          <button type="submit" name="signupBtn" class="w-full h-9 bg-black text-white text-center font-semibold text-sm rounded-lg border border-gray-300 block mb-4 hover:bg-gray-800">Create account</button>

          <div class="flex justify-center items-center gap-1">
            <h5 class="text-xs text-zinc-400">Already have a shelf?</h5>
            <a href="?mode=signin" class="underline text-xs font-medium">Sign in instead</a>
          </div>
        </form>
      </div>

    </div>
  </div>
</body>