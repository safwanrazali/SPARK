<?php

/*
|--------------------------------------------------------------------------
| Akaun Pentadbir Awal — FASA 13
|--------------------------------------------------------------------------
|
| Nilai ini digunakan oleh Database\Seeders\AdminUserSeeder semasa pemasangan
| pertama sahaja. Kata laluan TIDAK disimpan di dalam repositori: ia dibaca
| daripada .env supaya setiap pemasangan mempunyai kelayakan tersendiri.
|
| Pada pelayan pengeluaran, ADMIN_PASSWORD wajib ditetapkan sebelum
| `php artisan db:seed` dijalankan.
|
*/

return [

    'username' => env('ADMIN_USERNAME', 'AdminMpq'),

    'name' => env('ADMIN_NAME', 'Pentadbir Sistem'),

    'email' => env('ADMIN_EMAIL', 'admin@spark.local'),

    'password' => env('ADMIN_PASSWORD'),

];
