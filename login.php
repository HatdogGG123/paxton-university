<?php
require_once __DIR__ . '/php/includes/auth.php';
ssis_start_session();

require_once __DIR__ . '/models/User.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$loginToast = $_SESSION['login_toast'] ?? null;
unset($_SESSION['login_toast']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        header('Location: login.php?error=invalid');
        exit;
    }

    $identifier = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $userModel = new User(Database::getConnection());
    try {
        $user = $userModel->authenticate($identifier, $password);
    } catch (DomainException $exception) {
        $_SESSION['login_toast'] = ['type' => 'error', 'message' => $exception->getMessage()];
        header('Location: login.php');
        exit;
    } catch (PDOException $exception) {
        error_log('SSIS login database error: ' . $exception->getMessage());
        header('Location: login.php?error=unavailable');
        exit;
    }

    if ($user === null) {
        $_SESSION['login_toast'] = ['type' => 'error', 'message' => $userModel->getLastLoginFailureMessage()];
        header('Location: login.php');
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['user'] = $user;
    $_SESSION['last_activity'] = time();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    $destinations = [
        'Student' => 'php/student/dashboard.php',
        'Applicant' => 'php/applicant/dashboard.php',
        'Registrar' => 'php/registrar/enrollment.php',
        'Cashier' => 'php/cashier/dashboard.php',
        'Department' => 'php/department/clearance.php',
        'Admin' => 'php/admin/userManagement.php',
    ];

    header('Location: ' . ($destinations[$user['role']] ?? 'index.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<?php include("php/includes/head.php"); ?>
<script src="js/helperFunction.js"></script>

<body class="font-sans antialiased text-brand-dark min-h-screen relative flex flex-col justify-between overflow-x-hidden bg-brand-dark">

    <!-- Background Image and Gradient matching application.php -->
    <div class="absolute inset-0 z-0 pointer-events-none">
        <img src="https://images.pexels.com/photos/28412565/pexels-photo-28412565.jpeg" alt="" class="w-full h-full object-cover opacity-30">
        <div class="absolute inset-0 bg-gradient-to-r from-brand-dark via-brand-dark/80 to-brand-dark/40"></div>
    </div>

    <!-- Top Logo Button -->
    <header class="relative z-10 px-8 py-8 md:px-12">
        <a href="index.php" class="w-10 h-10 rounded-md border border-brand-accent/30 backdrop-blur-md bg-brand-primary/40 flex items-center justify-center shadow-lg hover:border-brand-accent transition">
            <svg class="lucide lucide-arrow-left preview-icon size-5 text-white" xmlns="http://www.w3.org/2000/svg" width="0" height="0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="m12 19-7-7 7-7" />
                <path d="M19 12H5" />
            </svg>
        </a>
    </header>

    <!-- Login Card Container -->
    <main class="relative z-10 px-6 md:px-16 lg:px-24 py-8 flex-1 flex items-center justify-center">
        <div class="w-full max-w-sm">

            <!-- Card Container in Accent Color (#dad7cd) -->
            <div class="bg-brand-accent rounded-xl p-8 shadow-2xl border border-brand-accent/20">

                <!-- Header Text -->
                <div class="text-center space-y-1 mb-8">
                    <h1 class="text-2xl font-bold text-brand-dark tracking-tight">Portal Login</h1>
                    <p class="text-xs text-brand-primary font-medium">Please enter your details</p>
                </div>

                <?php if (isset($_GET['error'])): ?><script>showToast('error', <?= json_encode($_GET['error'] === 'unavailable' ? 'Sign-in is temporarily unavailable. Please try again later.' : 'Invalid username or password.', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);</script><?php endif; ?>

                <div id="login_lockout_countdown" role="status" aria-live="polite" class="hidden mb-6 rounded-md border border-amber-300 bg-amber-50 p-3 text-xs font-semibold text-amber-900"></div>

                <!-- Login Form -->
                <form action="login.php" method="POST" class="space-y-5">

                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>" />
                    <input type="hidden" name="action" value="handleUserLogin" />

                    <!-- Username Field -->
                    <div class="space-y-1.5">
                        <label for="username" class="block text-xs text-brand-primary font-semibold">
                            Username or email
                        </label>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            required
                            placeholder="Student number, username, or email"
                            class="w-full px-4 py-3 rounded-md border border-brand-primary/20 bg-white/70 text-brand-dark text-xs placeholder-brand-primary/50 focus:outline-none focus:bg-white focus:ring-2 focus:ring-brand-primary transition" />
                    </div>

                    <!-- Password Field -->
                    <div class="space-y-1.5">
                        <label for="password" class="block text-xs text-brand-primary font-semibold">
                            Password
                        </label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            placeholder="••••••••••••"
                            class="w-full px-4 py-3 rounded-md border border-brand-primary/20 bg-white/70 text-brand-dark text-xs placeholder-brand-primary/50 focus:outline-none focus:bg-white focus:ring-2 focus:ring-brand-primary transition" />
                    </div>

                    <!-- Forgot Password Link -->
                    <div class="text-right pt-1">
                        <a href="forgotPassword.php" class="text-xs font-semibold text-brand-primary hover:text-brand-dark hover:underline">
                            Forgot password?
                        </a>
                    </div>

                    <button
                        type="submit"
                        id="btn-submit"
                        class="w-full mt-2 bg-brand-primary hover:bg-brand-secondary text-brand-accent font-semibold py-3 px-6 rounded-md transition duration-200 shadow-md text-xs tracking-wide flex items-center justify-center space-x-2">
                        <span>Sign in</span>
                    </button>

                </form>

            </div>
        </div>
    </main>

    <footer class="relative z-10 py-4"></footer>

    <!-- jQuery Submission Script -->
    <script>
        $(document).ready(function() {
            $('form').on('submit', function() {
                const $submitBtn = $('#btn-submit');

                $submitBtn.prop('disabled', true);
                $submitBtn.addClass('opacity-80 cursor-not-allowed');
                $submitBtn.html(`
          <svg class="animate-spin -ml-1 mr-2 h-3.5 w-3.5 text-brand-accent inline-block" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span>Please wait...</span>
        `);
            });
        });
    </script>

<script>
(function ($) {
    if (!$) return;
    $(document).on('submit', 'form[method="post"], form[method="POST"]', function (event) {
        if (event.isDefaultPrevented()) return;
        event.preventDefault();
        var form = this;
        var fields = $(form).serializeArray();
        if (event.originalEvent && event.originalEvent.submitter && event.originalEvent.submitter.name) {
            fields.push({ name: event.originalEvent.submitter.name, value: event.originalEvent.submitter.value });
        }
        $.post('controller/authController.php', $.param(fields), null, 'json')
            .done(function (response) {
                if (response && response.status === 'success') {
                    var redirect = response.data && response.data.redirect;
                    if (redirect) window.location.assign(redirect);
                    else showToast('success', response.message || 'Signed in successfully.');
                } else {
                    var message = response && response.message ? response.message : 'The action could not be completed.';
                    showToast('error', message, 10000);
                }
            })
                .fail(function (xhr) {
                    var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'The action could not be completed. Please try again.';
                    showToast('error', message, 10000);
                    var retryAfter = Number(xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.retry_after);
                    if (retryAfter > 0) startLoginLockoutCountdown(retryAfter);
                    if (form.closest('body') && window.location.pathname.toLowerCase().endsWith('/login.php')) {
                    var button = document.getElementById('btn-submit');
                    if (button) { button.disabled = false; button.classList.remove('opacity-80', 'cursor-not-allowed'); button.innerHTML = 'Sign In'; }
                }
            });
    });
    var nativeSubmit = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function () {
        if ((this.method || 'get').toLowerCase() === 'post') { this.requestSubmit(); return; }
        nativeSubmit.call(this);
    };
    })(window.jQuery);

    function startLoginLockoutCountdown(seconds) {
        var panel = document.getElementById('login_lockout_countdown');
        if (!panel) return;
        panel.classList.remove('hidden');
        var remaining = Math.max(1, Math.floor(seconds));
        function render() {
            var minutes = Math.floor(remaining / 60);
            var secondsLeft = remaining % 60;
            panel.textContent = remaining > 0
                ? 'Login is temporarily locked. You can try again in ' + minutes + ':' + String(secondsLeft).padStart(2, '0') + '.'
                : 'The login lockout has ended. You can try again now.';
            if (remaining <= 0) {
                window.clearInterval(timer);
                return;
            }
            remaining--;
        }
        render();
        var timer = window.setInterval(render, 1000);
    }
</script>
<?php if (is_array($loginToast)): ?><script>showToast(<?= json_encode($loginToast['type'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>, <?= json_encode($loginToast['message'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>, 10000);</script><?php endif; ?>
</body>

</html>
