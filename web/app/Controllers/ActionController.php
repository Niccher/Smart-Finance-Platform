<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Authentication\Actions\ActionInterface;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Controllers\ActionController as ShieldActionController;
use CodeIgniter\Shield\Models\UserIdentityModel;
use CodeIgniter\Shield\Models\UserModel;

/**
 * Class ActionController
 *
 * Extends Shield's ActionController to handle both in-session authentication
 * challenges (e.g. 2FA, post-registration email confirmation) and standalone direct visits
 * (e.g. clicking "Account Verification / 2FA" from login or registration pages) without 404s.
 */
class ActionController extends ShieldActionController
{
    protected $helpers = ['setting', 'email', 'text', 'html', 'form'];

    /**
     * Intercept and dispatch the request:
     * - If an official Shield session action is active, delegate directly to Shield.
     * - If accessing standalone/directly, route to standalone handlers.
     *
     * @param string[] $params
     *
     * @return ResponseInterface|string
     */
    public function _remap(string $method, ...$params)
    {
        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        // Grab action instance if one is set in the session.
        $this->action = $authenticator->getAction();

        if (! empty($this->action) && $this->action instanceof ActionInterface) {
            return $this->{$method}(...$params);
        }

        // Standalone access: if user is already logged in, send them to dashboard.
        if (auth()->loggedIn()) {
            return redirect()->to(config('Auth')->loginRedirect())
                ->with('message', 'You are already signed in.');
        }

        return match ($method) {
            'show'   => $this->showStandalone(),
            'verify' => $this->verifyStandalone(),
            'resend' => $this->resendStandalone(),
            'handle' => $this->handleStandalone(),
            default  => redirect()->to(url_to('auth-action-show')),
        };
    }

    /**
     * Display the standalone Account Verification / 2FA screen.
     */
    protected function showStandalone(): string
    {
        $email = (string) ($this->request->getGet('email') ?? session('resend_email') ?? old('email') ?? '');

        return view('App\Views\Shield\account_verification', [
            'userEmail' => $email,
        ]);
    }

    /**
     * Process verification token submission for standalone visits.
     */
    protected function verifyStandalone(): ResponseInterface
    {
        $rules = [
            'email' => 'required|valid_email',
            'token' => 'required|string|min_length[4]|max_length[32]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->to(url_to('auth-action-show'))
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $email = trim((string) $this->request->getPost('email'));
        $token = trim((string) $this->request->getPost('token'));

        /** @var UserModel $userModel */
        $userModel = model(UserModel::class);
        $user      = $userModel->findByCredentials(['email' => $email]);

        if ($user === null) {
            return redirect()->to(url_to('auth-action-show'))
                ->withInput()
                ->with('error', 'No account found matching this email address.');
        }

        if ($user->isActivated()) {
            return redirect()->to(url_to('login'))
                ->with('message', 'This account is already verified. Please sign in with your password.');
        }

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);
        $identity      = $identityModel->where('user_id', $user->id)
            ->whereIn('type', [Session::ID_TYPE_EMAIL_ACTIVATE, Session::ID_TYPE_EMAIL_2FA])
            ->where('secret', $token)
            ->first();

        if ($identity === null) {
            return redirect()->to(url_to('auth-action-show'))
                ->withInput()
                ->with('error', 'Invalid verification code. Please check your code or request a new one below.');
        }

        if (! empty($identity->expires) && Time::now()->isAfter($identity->expires)) {
            return redirect()->to(url_to('auth-action-show'))
                ->withInput()
                ->with('error', 'This verification code has expired. Please request a new code below.');
        }

        // Activate the user
        $user->activate();

        // Delete the used token identity
        $identityModel->deleteIdentitiesByType($user, $identity->type);

        // Sign the user in
        auth('session')->login($user);

        return redirect()->to(config('Auth')->registerRedirect())
            ->with('message', 'Your account has been successfully verified! Welcome to Smart Finance.');
    }

    /**
     * Re-send or issue a fresh verification code to the given email.
     */
    protected function resendStandalone(): ResponseInterface
    {
        $rules = [
            'email' => 'required|valid_email',
        ];

        if (! $this->validate($rules)) {
            return redirect()->to(url_to('auth-action-show'))
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $email = trim((string) $this->request->getPost('email'));

        /** @var UserModel $userModel */
        $userModel = model(UserModel::class);
        $user      = $userModel->findByCredentials(['email' => $email]);

        if ($user === null) {
            return redirect()->to(url_to('auth-action-show'))
                ->withInput()
                ->with('error', 'No account found matching this email address.');
        }

        if ($user->isActivated()) {
            return redirect()->to(url_to('login'))
                ->with('message', 'This account is already verified. Please sign in.');
        }

        helper('text');
        $code = random_string('nozero', 6);

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);
        $identityModel->deleteIdentitiesByType($user, Session::ID_TYPE_EMAIL_ACTIVATE);
        $identityModel->createCodeIdentity(
            $user,
            [
                'type'  => Session::ID_TYPE_EMAIL_ACTIVATE,
                'name'  => 'register',
                'extra' => 'Resent verification code',
            ],
            static fn (): string => $code
        );

        // Attempt email dispatch
        try {
            $emailService = emailer()->setFrom(
                setting('Email.fromEmail') ?: 'noreply@smartfinance.local',
                setting('Email.fromName') ?: 'Smart Finance'
            );
            $emailService->setTo($user->email);
            $emailService->setSubject(lang('Auth.emailActivateSubject') ?: 'Verify your Smart Finance Account');
            $emailService->setMessage(view(setting('Auth.views')['action_email_activate_email'], [
                'code'      => $code,
                'ipAddress' => $this->request->getIPAddress(),
                'userAgent' => (string) $this->request->getUserAgent(),
                'date'      => Time::now()->toDateTimeString(),
            ]));
            $emailService->send(false);
            $emailService->clear();
        } catch (\Throwable $e) {
            log_message('error', 'Failed to dispatch activation email: ' . $e->getMessage());
        }

        log_message('info', "Account activation code for [{$user->email}]: {$code}");

        $msg = (ENVIRONMENT === 'development')
            ? "A new verification code has been dispatched. (Dev code: {$code})"
            : 'A new verification code has been dispatched to your email address.';

        return redirect()->to(url_to('auth-action-show'))
            ->with('message', $msg)
            ->with('resend_email', $email);
    }

    /**
     * Standalone handle proxy (e.g. if POST auth/a/handle is accessed directly).
     */
    protected function handleStandalone(): ResponseInterface
    {
        return $this->resendStandalone();
    }
}
