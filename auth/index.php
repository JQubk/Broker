<?php
require $_SERVER['DOCUMENT_ROOT'] . '/local/classes/BrokerCabinet/autoload.php';

use BrokerCabinet\AuthService;

$authService = new AuthService();

$error = '';
$backUrl = !empty($_REQUEST['backurl']) ? (string)$_REQUEST['backurl'] : '/index.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $broker = $authService->current();
    if ($broker !== null) {
        header('Location: ' . $backUrl);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['EMAIL']) ? trim((string)$_POST['EMAIL']) : '';
    $password = isset($_POST['PASSWORD']) ? (string)$_POST['PASSWORD'] : '';

    if ($email === '' || $password === '') {
        $error = 'Please enter email and password';
    } else {
        try {
            $authService->login($email, $password);
            header('Location: ' . $backUrl);
            exit;
        } catch (\RuntimeException $e) {
            $code = $e->getMessage();

            if ($code === 'EMPTY_CREDENTIALS' || $code === 'INVALID_CREDENTIALS') {
                $error = 'Incorrect email or password';
            } else {
                $error = 'Authorization error';
            }
        } catch (\Throwable $e) {
            $error = 'Internal error';
        }
    }
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';

global $APPLICATION;
$APPLICATION->SetTitle('Login');
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-4 text-center">Broker / Partner Login</h5>

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialcharsbx($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="">
                        <?php if (!empty($backUrl)): ?>
                            <input type="hidden" name="backurl" value="<?= htmlspecialcharsbx($backUrl) ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input
                                type="email"
                                name="EMAIL"
                                class="form-control"
                                value="<?= htmlspecialcharsbx($_POST['EMAIL'] ?? '') ?>"
                                required
                                autofocus
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input
                                type="password"
                                name="PASSWORD"
                                class="form-control"
                                required
                            >
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            Sign In
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';