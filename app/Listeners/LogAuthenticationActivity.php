<?php

namespace App\Listeners;

use App\Support\AuditLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

/**
 * Mencatat login, login gagal, dan logout ke audit log (log_name: auth).
 *
 * Percobaan login yang sudah kena rate limit tidak sampai ke sini, sehingga
 * bot tidak bisa membanjiri audit log dengan "login gagal".
 */
class LogAuthenticationActivity
{
    public function handleLogin(Login $event): void
    {
        activity(AuditLog::AUTH)
            ->event('login')
            ->causedBy($event->user)
            ->performedOn($event->user)
            ->withProperties([...AuditLog::requestContext(), 'remember' => $event->remember])
            ->log('Login');
    }

    public function handleFailed(Failed $event): void
    {
        $logger = activity(AuditLog::AUTH)
            ->event('login_failed')
            ->causedByAnonymous()
            ->withProperties([...AuditLog::requestContext(), 'nik' => $event->credentials['nik'] ?? null]);

        // NIK terdaftar tapi password salah: kaitkan ke user tersebut.
        if ($event->user !== null) {
            $logger->performedOn($event->user);
        }

        $logger->log('Login gagal');
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user === null) {
            return;
        }

        activity(AuditLog::AUTH)
            ->event('logout')
            ->causedBy($event->user)
            ->performedOn($event->user)
            ->withProperties(AuditLog::requestContext())
            ->log('Logout');
    }
}
