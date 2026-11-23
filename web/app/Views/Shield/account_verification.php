<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?>Account Verification <?= $this->endSection() ?>

<?= $this->section('main') ?>
    <div class="auth-header">
        <div class="brand-icon"><i class="fa-solid fa-user-shield"></i></div>
        <h1>Account Verification</h1>
        <p>Confirm your identity or activate your new account</p>
    </div>

    <?php if (session('error')) : ?>
        <div class="alert alert-error"><?= session('error') ?></div>
    <?php elseif (session('errors')) : ?>
        <div class="alert alert-error">
            <?php foreach (session('errors') as $error) : ?>
                <?= $error ?><br>
            <?php endforeach ?>
        </div>
    <?php endif ?>

    <?php if (session('message')) : ?>
        <div class="alert alert-success"><?= session('message') ?></div>
    <?php endif ?>

    <!-- Navigation Pills for Verify vs Resend -->
    <ul class="nav nav-pills nav-fill mb-3" id="verifyTabs" role="tablist" style="background: var(--input-bg); padding: 4px; border-radius: var(--radius);">
        <li class="nav-item" role="presentation">
            <button class="nav-link active small py-2 fw-semibold" id="verify-tab" data-bs-toggle="tab" data-bs-target="#verify-pane" type="button" role="tab" aria-controls="verify-pane" aria-selected="true">
                <i class="fa-solid fa-key me-1"></i>Enter Code
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link small py-2 fw-semibold" id="resend-tab" data-bs-toggle="tab" data-bs-target="#resend-pane" type="button" role="tab" aria-controls="resend-pane" aria-selected="false">
                <i class="fa-solid fa-paper-plane me-1"></i>Resend Code
            </button>
        </li>
    </ul>

    <div class="tab-content" id="verifyTabsContent">
        <!-- Tab 1: Enter Verification Code -->
        <div class="tab-pane fade show active" id="verify-pane" role="tabpanel" aria-labelledby="verify-tab" tabindex="0">
            <form action="<?= base_url('auth/a/verify') ?>" method="post">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="verifyEmailInput" class="form-label">Account Email</label>
                    <input type="email" class="form-control" id="verifyEmailInput" name="email" inputmode="email" autocomplete="email" placeholder="you@domain.com" value="<?= old('email', $userEmail ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="floatingTokenInput" class="form-label">6-Digit Verification Code</label>
                    <input type="text" class="form-control text-center fs-4 fw-bold" id="floatingTokenInput" name="token" placeholder="000000" inputmode="numeric"
                        pattern="[0-9]*" maxlength="12" autocomplete="one-time-code" value="<?= old('token') ?>" required style="letter-spacing: 6px;" />
                    <div class="form-text small text-muted text-center mt-1">Enter the activation code delivered to your registered email address.</div>
                </div>

                <button type="submit" class="btn-primary mt-3">
                    <i class="fa-solid fa-circle-check me-2"></i>Verify & Continue
                </button>
            </form>
        </div>

        <!-- Tab 2: Resend Activation Code -->
        <div class="tab-pane fade" id="resend-pane" role="tabpanel" aria-labelledby="resend-tab" tabindex="0">
            <form action="<?= base_url('auth/a/resend') ?>" method="post">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="resendEmailInput" class="form-label">Registered Email</label>
                    <input type="email" class="form-control" id="resendEmailInput" name="email" inputmode="email" autocomplete="email" placeholder="you@domain.com" value="<?= old('email', $userEmail ?? '') ?>" required>
                    <div class="form-text small text-muted mt-1">We'll generate a fresh activation code and dispatch it to your inbox.</div>
                </div>

                <button type="submit" class="btn-primary mt-3">
                    <i class="fa-solid fa-paper-plane me-2"></i>Send New Code
                </button>
            </form>
        </div>
    </div>

    <div class="auth-footer mt-4">
        <p class="mb-2">Already activated? <a href="<?= base_url('login') ?>">Sign In</a></p>
        <p class="mb-0 small"><a href="<?= base_url('register') ?>" class="text-muted text-decoration-none">Create Account</a> &bull; <a href="<?= base_url('magic-link') ?>" class="text-muted text-decoration-none">Magic Link / Forgot Password</a></p>
    </div>
<?= $this->endSection() ?>
