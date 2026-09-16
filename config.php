<?php
/* ============================================================
   bisanat — Yönetim Paneli Ayarları
   ------------------------------------------------------------
   Bu dosya panelin tüm ayarlarını tutar. Yayına almadan önce
   MUTLAKA panel şifresini değiştirin.

   ŞİFRE DEĞİŞTİRME (iki yol):
   1) Kolay yol — düz şifre:
      'password_plain' değerini kendi şifrenizle değiştirin.
   2) Güvenli yol — hash'li şifre (önerilir):
      Bir kez şu komutu çalıştırıp çıktıyı 'password_hash' alanına
      yapıştırın, ardından 'password_plain' değerini boş bırakın:
        php -r "echo password_hash('YENI_SIFRENIZ', PASSWORD_DEFAULT), PHP_EOL;"
      ('password_hash' doluysa 'password_plain' yok sayılır.)
   ============================================================ */

return [
    // --- Panel girişi ---
    'password_hash'  => '',                 // password_hash(...) çıktısı (doluysa bu kullanılır)
    'password_plain' => 'bisanat2025',      // hash boşsa bu geçerli — YAYINDAN ÖNCE DEĞİŞTİRİN

    // --- Dosya yolları (normalde değiştirmenize gerek yok) ---
    'data_file'      => __DIR__ . '/icerik.json',   // içerik veritabanı (tek dosya)
    'assets_dir'     => __DIR__ . '/assets',        // görsel kök klasörü -> assets/{kategori}/

    // --- Görsel yükleme kuralları ---
    'max_upload_mb'  => 12,                          // tek görsel için üst sınır (MB)
    'allowed_ext'    => ['webp', 'jpg', 'jpeg', 'png'],

    // --- Oturum ---
    'session_name'   => 'bisanat_admin',
];
