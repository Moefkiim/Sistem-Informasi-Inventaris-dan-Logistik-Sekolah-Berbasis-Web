<?php

namespace Tests\Support;

/**
 * Matriks otorisasi seluruh route aplikasi berdasarkan matriks akses di AGENTS.md.
 *
 * Nilai akses berupa:
 * - 'guest' : dapat diakses publik tanpa login (mis. login, health check, root redirect).
 * - 'any-auth' : dapat diakses oleh semua pengguna yang telah terautentikasi dan aktif.
 * - array<string> : daftar role yang diizinkan (mis. ['kajur'], ['sarpras'], ['kepala_sekolah']).
 */
class RouteAccessMatrix
{
    /**
     * Peta akses per nama route atau URI (untuk route tanpa nama).
     *
     * @var array<string, string|array<int, string>>
     */
    public const MATRIX = [
        // Rute Guest / Publik / Internal
        'login' => 'guest',
        'root' => 'guest', // URI '/' (RootRedirectController)
        'up' => 'guest',   // Health check Laravel
        'storage.local' => 'guest',
        'storage.local.upload' => 'guest',

        // Rute Autentikasi Umum (Seluruh Peran yang login)
        'logout' => 'any-auth',
        'profile.show' => 'any-auth',
        'profile.password' => 'any-auth',
        'home' => 'any-auth',
        'notifications.index' => 'any-auth',
        'notifications.open' => 'any-auth',
        'notifications.read_all' => 'any-auth',
        'reports.index' => 'any-auth',
        'reports.pdf' => 'any-auth',
        'reports.excel' => 'any-auth',
        'documents.index' => 'any-auth',
        'documents.store' => 'any-auth',
        'documents.download' => 'any-auth',
        'documents.destroy' => 'any-auth',

        // Area Kajur
        'kajur.submissions.index' => ['kajur'],
        'kajur.submissions.create' => ['kajur'],
        'kajur.submissions.store' => ['kajur'],
        'kajur.submissions.show' => ['kajur'],
        'kajur.submissions.edit' => ['kajur'],
        'kajur.submissions.update' => ['kajur'],
        'kajur.submissions.cancel' => ['kajur'],
        'kajur.submissions.submitDraft' => ['kajur'],
        'kajur.submissions.revise' => ['kajur'],
        'kajur.inventory.index' => ['kajur'],
        'kajur.inventory.show' => ['kajur'],
        'kajur.area' => ['kajur'],

        // Area Sarpras
        'sarpras.inventory.index' => ['sarpras'],
        'sarpras.inventory.create' => ['sarpras'],
        'sarpras.inventory.store' => ['sarpras'],
        'sarpras.inventory.import.create' => ['sarpras'],
        'sarpras.inventory.import.template' => ['sarpras'],
        'sarpras.inventory.import.preview' => ['sarpras'],
        'sarpras.inventory.import.confirm' => ['sarpras'],
        'sarpras.inventory.import.errors' => ['sarpras'],
        'sarpras.inventory.show' => ['sarpras'],
        'sarpras.inventory.updateLocation' => ['sarpras'],
        'sarpras.inventory.updateCondition' => ['sarpras'],
        'sarpras.inventory.updateStatus' => ['sarpras'],
        'sarpras.submissions.index' => ['sarpras'],
        'sarpras.submissions.show' => ['sarpras'],
        'sarpras.submissions.process' => ['sarpras'],
        'sarpras.realizations.index' => ['sarpras'],
        'sarpras.submissions.realize_form' => ['sarpras'],
        'sarpras.submissions.realize' => ['sarpras'],
        'sarpras.logistics.incoming' => ['sarpras'],
        'sarpras.logistics.incoming.store' => ['sarpras'],
        'sarpras.logistics.outgoing' => ['sarpras'],
        'sarpras.logistics.outgoing.store' => ['sarpras'],
        'sarpras.logistics.distributions' => ['sarpras'],
        'sarpras.logistics.distributions.store' => ['sarpras'],
        'sarpras.locations.index' => ['sarpras'],
        'sarpras.locations.store' => ['sarpras'],
        'sarpras.locations.destroy' => ['sarpras'],
        'sarpras.users.index' => ['sarpras'],
        'sarpras.users.store' => ['sarpras'],
        'sarpras.users.update' => ['sarpras'],
        'sarpras.users.reset_password' => ['sarpras'],
        'sarpras.users.toggle' => ['sarpras'],
        'sarpras.loans.index' => ['sarpras'],
        'sarpras.loans.create' => ['sarpras'],
        'sarpras.loans.store' => ['sarpras'],
        'sarpras.loans.show' => ['sarpras'],
        'sarpras.loans.approve' => ['sarpras'],
        'sarpras.loans.return' => ['sarpras'],
        'sarpras.loans.reject' => ['sarpras'],
        'sarpras.activity_logs.index' => ['sarpras'],
        'sarpras.area' => ['sarpras'],

        // Area Kepala Sekolah
        'kepala_sekolah.approval.index' => ['kepala_sekolah'],
        'kepala_sekolah.approval.show' => ['kepala_sekolah'],
        'kepala_sekolah.approval.decide' => ['kepala_sekolah'],
        'kepala_sekolah.area' => ['kepala_sekolah'],
    ];

    /**
     * Dapatkan deklarasi akses untuk route yang diberikan.
     *
     * @return string|array<int, string>|null
     */
    public static function getAccessForRoute(\Illuminate\Routing\Route $route): string|array|null
    {
        $name = $route->getName();
        if ($name && array_key_exists($name, self::MATRIX)) {
            return self::MATRIX[$name];
        }

        $uri = trim($route->uri(), '/');
        if ($uri === '' && array_key_exists('root', self::MATRIX)) {
            return self::MATRIX['root'];
        }

        if (array_key_exists($uri, self::MATRIX)) {
            return self::MATRIX[$uri];
        }

        return null;
    }
}
