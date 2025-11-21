<?php
require $_SERVER['DOCUMENT_ROOT'] . '/include/broker_handler.php';

$error      = '';
$errorDebug = '';
$backUrl    = !empty($_REQUEST['backurl']) ? (string)$_REQUEST['backurl'] : '/index.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $broker = broker_current();
    if ($broker !== null) {
        header('Location: ' . $backUrl);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = isset($_POST['EMAIL']) ? trim((string)$_POST['EMAIL']) : '';
    $password = isset($_POST['PASSWORD']) ? (string)$_POST['PASSWORD'] : '';

    if ($email === '' || $password === '') {
        $error = 'Please enter email and password.';
    } else {
        try {
            broker_login($email, $password);
            header('Location: ' . $backUrl);
            exit;
        } catch (\RuntimeException $e) {
            $code = $e->getMessage();

            if ($code === 'EMPTY_CREDENTIALS' || $code === 'INVALID_CREDENTIALS') {
                $error = 'Incorrect email or password.';
            } else {
                $error      = 'Authorization error. Please contact administrator.';
                $errorDebug = $code;
            }
        } catch (\Throwable $e) {
            $error      = 'Internal authorization error.';
            $errorDebug = $e->getMessage();
        }
    }
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';
global $APPLICATION;
$APPLICATION->SetTitle('Broker cabinet login');
?>


<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-4 text-center">Broker / Partner login</h5>

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($error) ?>
                            <?php if ($errorDebug !== ''): ?>
                                <br>
                                <small class="text-muted">
                                    <?= htmlspecialchars($errorDebug) ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="">
                        <?php if (!empty($backUrl)): ?>
                            <input 
                                type="hidden" 
                                name="backurl"
                                value="<?=htmlspecialchars($backUrl)?>"
                            >
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Login</label>
                            <input
                                type="email"
                                name="EMAIL"
                                class="form-control"
                                value="<?=htmlspecialchars($_POST['EMAIL'] ?? '')?>"
                                required
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
                            Sign in
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php'; ?>
