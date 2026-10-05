<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

/*
| Presence channel daftar user online. Semua user yang login bergabung
| (lihat public/js/realtime.js); yang boleh melihat daftarnya diatur
| lewat hak akses menu `online-users.read`.
|
| `page` dan `title` dikirim browser sebagai parameter otorisasi saat join.
*/
Broadcast::channel('online', fn (User $user) => $user->toOnlinePresence(
    app(Request::class)->input('page'),
    app(Request::class)->input('title'),
));
