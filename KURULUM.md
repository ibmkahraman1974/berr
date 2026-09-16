# bisanat — Yönetim Paneli Kurulumu (cPanel)

Bu paket, bisanat sitesine **sunucu tarafı içerik yönetimi** ekler. Artık galeri
görselleri, başlık/açıklamalar, sayfa metinleri ve kategoriler; şifreli bir web
panelinden yönetilir ve **yaptığınız değişiklikler anında tüm ziyaretçilere yansır**.
Eskiden olduğu gibi JSON’u indirip koda gömmeniz gerekmez.

---

## 1. Paket içeriği

| Dosya          | Görevi |
|----------------|--------|
| `index.html`   | Sitenin kendisi (içeriği `icerik.json`’dan okur) |
| `yonetim.php`  | Yönetim paneli (şifreli giriş + düzenleme) |
| `api.php`      | Panelin kullandığı arka uç (kayıt, yükleme, taşıma, silme) |
| `config.php`   | Ayarlar — **panel şifresi burada** |
| `icerik.json`  | Tüm içerik verisi (tek dosya “veritabanı”) |
| `.htaccess`    | Güvenlik (yedek dosyalarını gizler) |
| `assets/`      | **Görsellerin bulunduğu klasör** (bu pakette yok — kendi görselleriniz sunucuda kalır) |

> **Önemli:** `assets/` klasörü bu pakete dahil değildir; sunucunuzdaki mevcut
> görselleriniz olduğu yerde kalır. Aşağıdaki dosyaları mevcut sitenizin **kök
> dizinine** (görsellerin yanına) yükleyin.

---

## 2. Yükleme adımları

1. cPanel → **Dosya Yöneticisi**’ni açın, sitenizin kök klasörüne girin
   (genelde `public_html` veya alan adınızın klasörü — mevcut `index.html`’in
   ve `assets/` klasörünün olduğu yer).
2. **Önce mevcut `index.html`’in yedeğini alın** (adını `index_yedek.html` yapın).
3. Bu paketteki şu dosyaları oraya yükleyin:
   `index.html`, `yonetim.php`, `api.php`, `config.php`, `icerik.json`, `.htaccess`
4. `assets/` klasörünüze **dokunmayın** — görseller orada durmaya devam etsin.

Bu kadar. Panel kullanıma hazır.

---

## 3. Şifreyi değiştirin (yayına almadan önce mutlaka)

`config.php` dosyasını cPanel’de **Düzenle** ile açın:

```php
'password_plain' => 'bisanat2025',   // BURAYI kendi şifrenizle değiştirin
```

Daha güvenli yöntem (önerilir) — hash’li şifre:

1. cPanel’de **Terminal** varsa şunu çalıştırın:
   `php -r "echo password_hash('YENI_SIFRENIZ', PASSWORD_DEFAULT), PHP_EOL;"`
2. Çıktıyı `'password_hash' => '...'` alanına yapıştırın ve
   `'password_plain'` değerini boş bırakın (`''`).

---

## 4. Panele giriş

Tarayıcıdan:  **`https://siteadresiniz.com/yonetim.php`**

Şifrenizi girin. Panelde dört sekme var:

- **Galeri Görselleri** — Kategori seçin; görsel ekleyin/silin, başlık ve açıklama
  yazın, `▲▼` ile sıralayın, “Kategoriye taşı” ile başka bölüme aktarın.
- **Sayfa Metinleri** — Hero, istatistikler, biyografiler, iletişim, footer metinleri.
- **Sanatta Bir Yaklaşım** — Öne çıkan kartların başlık/açıklamaları.
- **Kategoriler** — Kategori adı ve giriş metinleri; `▲▼` ile kategori sırası.

Her değişiklikten sonra sağ alttaki **Kaydet** düğmesine basın
(kısayol: `Ctrl/Cmd + S`). Kaydedince değişiklik canlı siteye geçer.

### Görsel yükleme
“Galeri Görselleri” sekmesinde kategoriyi seçin → **“＋ Yeni görsel yükle”**
alanına dosyayı sürükleyin ya da tıklayıp seçin. Görsel otomatik olarak
`assets/<kategori>/` klasörüne yüklenir; siz sadece başlık ve açıklama yazıp
**Kaydet** dersiniz. (İzinli türler: `webp`, `jpg`, `png` — üst sınır 12 MB.)

---

## 5. Dosya/klasör izinleri (yükleme ve kayıt için şart)

Panelin yazabilmesi için sunucuda şu izinler gerekir (cPanel → Dosya Yöneticisi →
sağ tık → **Permissions**):

| Öğe | İzin |
|-----|------|
| `icerik.json` | **644** (yazılabilir olmalı) |
| `assets/` ve tüm alt klasörleri (`assets/portre/` vb.) | **755** |
| `config.php`, `api.php`, `yonetim.php`, `index.html` | 644 |

Çoğu cPanel sunucusunda bu izinler zaten uygundur. “Kaydedilemedi” veya
“klasör yazılamıyor” hatası alırsanız ilgili klasörün iznini 755 yapın.

---

## 6. Görseller nerede saklanır?

Her kategori kendi klasörünü kullanır: `assets/<kategori-kodu>/<dosya>`.
Kategori kodları:

| Kategori | Klasör (kod) |
|----------|--------------|
| Milli Saraylar | `assets/milli-saraylar/` |
| Çanakkale Savaşları Tarihi Alan | `assets/tarihi-alan/` |
| Etnografya ve Kent Müzeleri | `assets/etnografya/` |
| Arkeoloji Müzeleri | `assets/arkeoloji/` |
| Portreler | `assets/portre/` |
| Çanakkale Deniz Müzesi | `assets/deniz-muzesi/` |
| Tıpkı Benzer Çalışmalar | `assets/tipki-benzer/` |
| Tarih Öncesi İnsanlar | `assets/tarih-oncesi/` |
| Mustafa Kemal Atatürk | `assets/ataturk/` |
| Yurtdışı Çalışmaları | `assets/yurtdisi/` |
| Film, TV, Reklam Setleri | `assets/film-tv/` |

> Logo, hero (arka plan) ve “Sanatta Bir Yaklaşım” kart görselleri panele dahil
> değildir; bunları eskisi gibi cPanel Dosya Yöneticisi’nden `assets/main/` ve
> ilgili klasörlerden değiştirebilirsiniz. Panel bu kartların yalnızca
> **metinlerini** yönetir.

---

## 7. Yedek ve güvenlik

- Her kayıtta bir önceki içerik `icerik.json.bak` olarak otomatik yedeklenir.
- Panel şifresini güçlü seçin ve siteyi **HTTPS** üzerinden kullanın.
- `.htaccess`, yedek (`.bak`) dosyalarına dışarıdan erişimi engeller.
- İçeriği dilerseniz `icerik.json` dosyasını elle düzenleyerek de değiştirebilirsiniz
  (biçimi bozmamaya dikkat edin; düzenlemeden önce bir kopyasını alın).

---

## 8. Sık karşılaşılan durumlar

- **Panel açılmıyor / boş sayfa:** Sunucuda PHP etkin olmalı (cPanel’de standarttır).
  `yonetim.php`’yi doğru klasöre yüklediğinizden emin olun.
- **“Kaydedilemedi”:** `icerik.json` yazılabilir değil → iznini 644/klasörü 755 yapın.
- **Görsel yüklenmiyor:** İlgili `assets/<kategori>/` klasörü yoksa panel oluşturmaya
  çalışır; olmuyorsa klasörü elle açıp 755 izni verin.
- **Site eski içeriği gösteriyor:** Tarayıcı önbelleği — sayfayı `Ctrl+F5` ile yenileyin.
  (Site `icerik.json`’u her açılışta tazeler, panelde Kaydet’e bastığınızdan emin olun.)

---

İyi çalışmalar. Sorun olursa `icerik.json.bak` dosyasını `icerik.json` yaparak
son çalışan içeriğe kolayca dönebilirsiniz.
