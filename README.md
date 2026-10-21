# KENDİ KAHRAMANIN — Android mini oyun

Altı karakterden birini seç, ön kamerayla yüzünü çek, yüzün karaktere yerleşsin; altınları topla, engellerden kaç, bayrağa ulaş.

## Teknoloji (ve neden Unity değil)
Capacitor 6 + HTML5 Canvas (vanilla JS, tek dosya: `www/index.html`). Unity yerine bunu seçtim; çünkü geliştirme ortamımda Unity/Android SDK kurulamıyor ve bu yöntem APK'yı ücretsiz araçlarla (Node + Java 17 + Android SDK) veya GitHub Actions ile üretmeyi kolaylaştırıyor. Karakterler kodla çizilen 2D sprite'lardır; yüz dokusu karakterle aynı dönüşüm içinde çizildiği için hareket/zıplama sırasında yerinden kaymaz.

## APK üretme — Yol A: GitHub Actions (bilgisayara hiçbir şey kurmadan)
1. Bu klasörü bir GitHub deposuna `main` dalına yükle.
2. Depoda **Actions → APK derle → Run workflow**.
3. Bitince **Artifacts → kendi-kahramanin-debug-apk** içinden `app-debug.apk` dosyasını indir, telefona kopyala, kur (bilinmeyen kaynaklara izin ver).

## APK üretme — Yol B: Kendi bilgisayarın
Gereksinimler: Node 18+, JDK 17, Android Studio (Android SDK 34).
```
npm install
npx cap add android
node scripts/patch-android.js     # kamera izni, dikey ekran, yedeklemeyi kapatma
npx cap sync android
cd android && ./gradlew assembleDebug
```
Çıktı: `android/app/build/outputs/apk/debug/app-debug.apk`  
Telefonda USB hata ayıklama açıksa: `adb install -r app-debug.apk`

Web dosyasını değiştirince yalnızca `npx cap sync android` + yeniden derleme yeterli.

## Kontroller
Sol/sağ oklar: yürü. ZIPLA: zıpla (basılı tutarsan yükseğe). Slime'ın üstüne zıplarsan etkisiz olur, yandan çarparsan can kaybedersin. 3 can. Çukura düşmek de can götürür. Bilgisayarda test için: ok tuşları / A-D / boşluk.

## Gizlilik
Fotoğraf yalnızca cihazda işlenir; hiçbir ağ isteği yoktur, hesap yoktur. Varsayılan olarak yalnızca bellektedir ve uygulama kapanınca silinir. "Fotoğrafı bu cihazda sakla" kutusunu işaretlersen uygulamanın yerel depolamasına yazılır; Ayarlar veya karakter ekranından silinebilir. Android yedeklemesi kapalıdır.

## Doğrulananlar ve bilinen sınırlamalar
Başsız Chromium'da (telefon boyutunda, sahte kamera akışıyla) denendi: menü → karakter seçimi (seçmeden OYNA kapalı) → kamera → fotoğraf → hizalama → KULLAN → yüz karakterde ve oyunda görünüyor → otomatik oyuncu bölümü bitirip kazanma ekranına ulaştı; konsol hatası yok.

**Henüz doğrulanmayanlar:** gerçek Android cihazda çalıştırma, gerçek ön kamera/izin penceresi, APK derlemesi (bu ortamda Android SDK/Gradle indirilemediği için derlenmedi), ses (başsız testte duyulamaz), dokunmatik his/zorluk ayarı.
- Otomatik yüz bulma yalnızca tarayıcı `FaceDetector` desteği varsa çalışır (Android WebView'de çoğu zaman yok). Yoksa sürükle/yakınlaştır ekranıyla elle hizalanır. MediaPipe/ML Kit modelleri bu ortamdan indirilemediği için eklenmedi.
- Yüz, fotogerçekçi değiştirme yapmaz: oval kırpılmış, kenarı yumuşatılmış fotoğraf kafaya yerleşir; ten rengi uyumu yoktur.
- Müzik ve efektler kodla üretilen basit sentez seslerdir (lisans sorunu yok).
- Uygulama simgesi Capacitor varsayılanıdır; `@capacitor/assets` ile değiştirilebilir.
