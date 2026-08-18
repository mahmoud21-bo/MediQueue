# MediQueue Backend

الـ Backend الخاص بمنصة MediQueue الطبية، مبني كـ REST API باستخدام Laravel 13. يدعم حاليًا مصادقة المرضى والأطباء ومدير المنصة، وملفاتهم الشخصية، ومراجعة واعتماد طلبات الأطباء.

## المتطلبات

- PHP 8.3 أو أحدث
- Composer
- MariaDB 11.8 أو أحدث

## تشغيل المشروع محليًا

```bash
composer setup
composer run dev
```

أو شغّله يدويًا:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

## فحص الـ API

بعد تشغيل السيرفر:

```text
GET /api/v1/health
```

الاستجابة المتوقعة:

```json
{
  "application": "MediQueue",
  "status": "ok",
  "api_version": "v1"
}
```

## الاختبارات

أنشئ قاعدة MariaDB باسم `mediqueue_test` وامنح مستخدم قاعدة البيانات صلاحية الوصول إليها. تم ضبط PHPUnit صراحةً على `DB_CONNECTION=mariadb` و`DB_DATABASE=mediqueue_test`، ولا تستخدم الاختبارات SQLite.

```bash
composer test
```

## المصادقة والصلاحيات

- Laravel Sanctum لإصدار وإلغاء رموز API.
- رموز تطبيق الهاتف لا تنتهي زمنيًا، وتبقى صالحة حتى إلغائها. ينشئ كل تسجيل دخول جلسة مستقلة باسم الجهاز، ويمكن عرض الجلسات وإلغاء جهاز محدد من `/api/v1/auth/sessions`.
- Spatie Laravel Permission للأدوار: `patient` و`doctor` و`receptionist` و`clinic_owner` و`platform_admin`.
- لا يقبل التسجيل دورًا من العميل؛ تعيّنه خدمة التسجيل المناسبة.
- الطبيب الجديد يبدأ بحالة `pending`. العمليات الطبية المستقبلية يجب أن تستخدم middleware باسم `doctor.approved`.
- تعطيل المستخدم أو تغيير كلمة مروره يلغي جميع رموزه، كما يمنع middleware باسم `active` الحسابات المعطلة من استخدام رمز قديم.
- مسارات المصادقة والطلبات والاستجابات موثقة في [AUTH_API.md](AUTH_API.md).

## مدير المنصة ومراجعة الأطباء

- لا يوجد مسار عام لتسجيل مدير المنصة. ينشئ `AdminSeeder` الحساب فقط من متغيرات `ADMIN_*` المحلية، ويتجاوز الإنشاء إن كانت كلها فارغة.
- مسارات طلبات الأطباء محمية بـ `auth:sanctum` ودور `platform_admin` وسياسة `DoctorApplicationPolicy`.
- تدعم القائمة البحث والفلاتر والترتيب والصفحات، مع إحصائيات منفصلة.
- تستخدم عمليتا القبول والرفض transaction وقفل الصف، وتسجلان audit وترسلان database notification.
- تحفظ وثائق التحقق على private storage ولا يمكن عرضها أو تنزيلها إلا من مسار المدير المصرح.
- التوثيق الكامل موجود في [ADMIN_DOCTOR_VERIFICATION_API.md](ADMIN_DOCTOR_VERIFICATION_API.md).

## Postman

استورد المجموعة والبيئة من:

- `docs/postman/MediQueue.postman_collection.json`
- `docs/postman/MediQueue.local.postman_environment.json`

تحفظ طلبات تسجيل الدخول الرموز تلقائيًا في `patient_token` و`doctor_token` و`admin_token`. الملفات لا تحتوي على رموز أو أسرار حقيقية.

## النطاق الحالي

يشمل هذا الإصدار Laravel backend APIs فقط: المصادقة، الملفات الشخصية، ومراجعة طلبات الأطباء بواسطة مدير المنصة. لم يتم إنشاء أو تعديل مشروع Flutter، ولا يشمل النطاق Dashboard الطبيب أو إدارة المواعيد أو إنشاء السجلات الطبية.
