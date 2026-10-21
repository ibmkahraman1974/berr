// `npx cap add android` sonrası çalıştırılır: kamera izni, dikey ekran, yedeklemeyi kapatma.
const fs = require('fs');
const p = 'android/app/src/main/AndroidManifest.xml';
if (!fs.existsSync(p)) { console.error('Önce "npx cap add android" çalıştır.'); process.exit(1); }
let x = fs.readFileSync(p, 'utf8');
if (!x.includes('android.permission.CAMERA')) {
  x = x.replace(/<application/, '<uses-permission android:name="android.permission.CAMERA" />\n    <uses-feature android:name="android.hardware.camera" android:required="false" />\n    <uses-feature android:name="android.hardware.camera.front" android:required="false" />\n\n    <application');
}
if (!x.includes('screenOrientation')) x = x.replace(/<activity/, '<activity\n            android:screenOrientation="portrait"');
// Yüz fotoğrafı (localStorage) Google yedeğine karışmasın:
x = x.replace(/android:allowBackup="true"/, 'android:allowBackup="false"');
fs.writeFileSync(p, x);
console.log('AndroidManifest.xml yamalandı.');
