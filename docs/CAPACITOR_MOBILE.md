# غلاف الموبايل (Capacitor) — Laravel + Inertia + Vue

## 1) المعمارية الموصى بها

- **نفس الكود**: تطبيق أندرويد/آيفون هو **WebView أصلي** يحمّل **نفس خادم Laravel** الذي يشغّل الويب؛ لا يُعاد كتابة Vue أو المنطق.
- **مساران للأصول**:
  - **`resources/capacitor/www/index.html`**: نقطة دخول مطلوبة من Capacitor؛ يُولَّد عبر `scripts/capacitor-prepare-webdir.mjs` ويوجّه فورًا إلى خادمك إذا وُجد `CAPACITOR_SERVER_URL`.
  - **`public/build/**`**: حزم Vite المعتادة؛ تُحمَّل **من الخادم** مع كل صفحة Inertia بعد التوجيه (كما في المتصفح).
- **`capacitor.config.ts` → `server.url`**: عند ضبط `CAPACITOR_SERVER_URL` يُنسَخ إلى إعدادات الأصلية بحيث يفتح الـ WebView الجلسة على نطاق الإنتاج مباشرة (متسق مع الجلسات وملفات تعريف الارتباط).
- **الإضافات الحالية**: `@capacitor/app` (روابط الفتح)، `@capacitor/status-bar`، `@capacitor/splash-screen`، `@capacitor/network`، `@capacitor/push-notifications` (تهيئة لاحقة فقط).

## 2) OAuth (Meta) والربط العميق بأمان

**ما يعمل اليوم بدون تغيير الخادم**

- التدفق الحالي: `redirect()->away('https://www.facebook.com/...')` ثم **callback HTTPS على نفس نطاق التطبيق** — داخل WebView هذا يعادل التصفح في نفس الموقع؛ الجلسة Laravel (`session`) تبقى صالحة إذا كان النطاق والكوكيز متطابقَين مع إعدادات الإنتاج.
- تأكد في لوحة Meta أن **`redirect_uri`** مسموح وهو **HTTPS** على نفس host المستخدم في `APP_URL` / `CAPACITOR_SERVER_URL`.

**إن ظهر حظر Meta على WebView المضمّن**

- احتمال الإنتاج: سياسات Meta قد تفرض فتح **متصفح النظام** (Chrome Custom Tabs / SFSafariViewController). الحل الأقل مخاطرة على المنظومة: إضافة `@capacitor/browser` لاحقًا لفتح رابط OAuth خارج WebView ثم العودة عبر **Android App Links / iOS Universal Links** إلى مسار callback على خادمك (بدون تغيير منطق Laravel، فقط طريقة فتح الرابط).

**الربط العميق (موصى به للإنتاج)**

- في `android/app/src/main/AndroidManifest.xml` تمت إضافة قالب **App Links**؛ استبدل `YOUR_PRODUCTION_HOST` بنطاقك ثم انشر `assetlinks.json` وأكمل التحقق.
- في الواجهة الأمامية، المستمع `App.addListener('appUrlOpen')` في `resources/js/capacitor/init-native.ts` يوجّه روابط `https` المناسبة إلى `window.location` داخل WebView.

## 3) مخاطر الإنتاج (يجب مراجعتها قبل الإطلاق)

| الخطر | التخفيف |
|--------|---------|
| كوكيز الجلسة لا تُحفظ في WebView | `SESSION_DOMAIN` مناسب، `SameSite`/`Secure` في HTTPS، `APP_URL` يطابق النطاق الفعلي |
| حظر OAuth داخل WebView | مراقبة أخطاء Meta؛ خطة B: `@capacitor/browser` + App Links |
| شهادة SSL / سلسلة غير موثوقة | تجنّب الشهادات الذاتية على الإنتاج؛ أندرويد يقطع الطلبات |
| Mixed content HTTP من HTTPS | الإبقاء على `allowMixedContent: false` |
| Reverb / WebSockets خلف بروكسي | ضبط `VITE_REVERB_*` ليشير إلى Host الصحيح من جهاز المستخدم وليس `localhost` |
| تحديثات أصول الويب | بعد كل `npm run build` على الخادم، يكفي أن يحمّل التطبيق الصفحات من الخادم؛ لتغييرات الأصلية فقط شغّل `npm run cap:sync` عند الحاجة |

## 4) أوامر العمل اليومية

```bash
# بناء الواجهات للخادم (كما تعود دائمًا)
npm run build

# تجهيز www + مزامنة المنصات (عيّن عنوان الإنتاج)
CAPACITOR_SERVER_URL=https://your-domain.tld npm run cap:sync

# آيفون (على جهاز فيه Xcode + CocoaPods)
CAPACITOR_SERVER_URL=https://your-domain.tld npm run cap:sync:ios

# المنصتان معًا عندما تكون الأدوات مكتملة
CAPACITOR_SERVER_URL=https://your-domain.tld npm run cap:sync:all

# أندرويد (يتطلب Android Studio + JDK)
npm run cap:open:android
```

**بناء APK تجريبي (debug) للتثبيت المباشر على الهاتف**

```bash
npm run cap:android:apk
```

المخرجات: `android/app/build/outputs/apk/debug/app-debug.apk`  
نسخة مكررة للمشاركة: `dist/khatwat-debug.apk` (المجلد `dist/` في `.gitignore`).

- APK **debug**: توقيع تطوير؛ على الهاتف فعّل «مصادر غير معروفة» أو ثبّت عبر USB/`adb install`.
- على macOS يُستخدم افتراضيًا `JAVA_HOME` الخاص بـ JBR داخل Android Studio.

**محليًا ضد جهاز/محاكي (HTTP)**

- استخدم عنوان شبكتك أو `10.0.2.2` للمحاكي، وفَعّل في بناء أندرويد فقط `usesCleartextTraffic` إن لزم، أو Prefer HTTPS محليًا عبر mkcert.

**آيفون**

- يتطلب Xcode كاملًا: `cd ios/App && pod install` ثم فتح المشروع في Xcode.

## 5) الإشعارات (ويب + أصلي)

- **متصفح / PWA:** كما هو مع Web Push (VAPID) وحقل `notifications.webpush_public_key`.
- **تطبيق Capacitor:** بعد الدخول إلى أي صفحة تستخدم `AuthenticatedLayout` يُطلب إذن الإشعار (ما لم يكن ممنوحًا مسبقًا)، ثم يُستدعى `PushNotifications.register()` ويُرسَل الرمز إلى الخادم (`device_push_tokens`) عبر `POST /device-push-tokens` مع كوكيز الجلسة وCSRF (إعداد axios الافتراضي في `resources/js/bootstrap.js`).
- **إرسال FCM:** اضبط `FIREBASE_CREDENTIALS` على مسار JSON لحساب خدمة Firebase؛ ثم يُرسل الخادم تلقائيًا مع كل استدعاء `WebPushService::sendToUsers` أيضًا إلى أجهزة أندرويد/آيفون المرتبطة بالمستخدم.

### أندرويد: بدون `google-services.json` لن تعمل الإشعارات — وتجنّب تعطّل التطبيق

Gradle يطبّق إضافة Google Services **فقط** إذا وُجد الملف `android/app/google-services.json`. بدونه لا يُهيأ Firebase في العملية الأصلية. استدعاء `PushNotifications.register()` من الويب على جهاز كهذا يؤدي غالبًا إلى توقّف التطبيق («متوقف») بعد الموافقة على الإذن.

لذلك يشارَك مع الواجهة العلم **`notifications.firebase_mobile_push_enabled`** من متغير البيئة **`FIREBASE_MOBILE_PUSH_ENABLED`** (افتراضي **`false`**). لا يُفعل على الخادم إلا بعدما يُضاف **`google-services.json`** إلى مشروع أندرويد ويُعاد بناء APK.

1. أنشئ مشروعًا في [Firebase Console](https://console.firebase.google.com/) وأضف تطبيق Android بـ **`applicationId`** مطابقًا لـ `android/app/build.gradle` (حاليًا `design.khatwat.erp`).
2. حمّل **`google-services.json`** وضعه في **`android/app/`** ثم أعد `npm run cap:sync` وبناء APK من جديد.
3. على الخادم: **`FIREBASE_MOBILE_PUSH_ENABLED=true`** بعد توفر ملف العميل في الـ APK، مع **`FIREBASE_CREDENTIALS`** لإرسال الرسائل من Laravel عبر `NativePushService`.

### إذا لم يُطلب الإذن أو لا يصل التوكن للخادم

- من إعدادات النظام على الهاتف: تحقق من إذن الإشعارات لتطبيق «خارج المخزون».
- إن رُفض الإذن سابقًا قد لا يُعرض الحوار مرة ثانية إلا من الإعدادات.
- افتح **Chrome DevTools للـ WebView** (USB debugging) وراقب رسائل **`[push]`** في الطرفية للأخطاء (`registrationError`، فشل حفظ التوكن بـ 419/401، إلخ).
- **آيفون:** أضف **`GoogleService-Info.plist`** إلى مشروع Xcode، فعّل Push Capability، واضبط صلاحيات الإشعار في التطبيق.

## 6) macOS: Xcode بدل Command Line Tools فقط

إذا ظهر `xcodebuild requires Xcode` بينما Xcode مثبّت، عيّن مجلد المطور (مرّة واحدة):

```bash
sudo xcode-select -s /Applications/Xcode.app/Contents/Developer
```

أو استخدم `npm run cap:sync:ios` كما هو الآن: يمرّر `DEVELOPER_DIR` افتراضيًا إلى تطبيق Xcode.

## 7) إصدارات Node

- تم تثبيت **Capacitor 6** لتعمل مع **Node 18**. للترقية إلى Capacitor 7 استخدم **Node ≥ 20** ثم رفع إصدارات الحزم.
