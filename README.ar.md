# pyarabic

**العربية** | [English](README.md)

الكود المصدري لموقع **[pyarabic.com](https://pyarabic.com/)**: نسخة ووردبريس
عربية مبنيّة على القالب الأب [Astra](https://wordpress.org/themes/astra/) وإضافة
[Yoast SEO Free](https://wordpress.org/plugins/wordpress-seo/)، مع قالب فرعي
مخصَّص يُضيف Google AdSense وتحسينات الأداء وحزمة من وحدات SEO التقنية مصمَّمة
للمحتوى العربي.

> الهدف من هذا المستودع: إبقاء كلّ التخصيصات في مكان واحد خارج القالب الأب
> Astra، حتى لا تُمحى عند تحديثه. كلّ شيء قابل للتفعيل/التعطيل عبر فلاتر
> ووردبريس بدون لمس كود القالب.

## بنية المستودع

```
.
├── astra-child/          القالب الفرعي لووردبريس (المُنتَج القابل للنشر)
│   ├── style.css         رأس القالب + CSS مخصَّص
│   ├── functions.php     يُحمِّل الستايلات، يطبع AdSense، يُشغِّل وحدات SEO
│   ├── README.md         شرح المزايا ومرجع الفلاتر الكامل
│   ├── inc/seo/          ملف PHP لكلّ ميزة (نمط loader)
│   └── assets/critical/  ملفات Critical CSS لكلّ قالب صفحة
├── header.php            نسخة نظيفة من header.php للقالب الأب (مرجعية)
├── robots.txt            ملف robots الفعلي للجذر
├── phpcs.xml.dist        إعداد معايير ووردبريس البرمجية
├── CHANGELOG.md          سجلّ التغييرات (Keep a Changelog)
├── CONTRIBUTING.md       سير العمل، المعايير البرمجية، آلية الإصدار
├── CODE_OF_CONDUCT.md    Contributor Covenant 2.1
├── SECURITY.md           سياسة الإبلاغ عن الثغرات
├── LICENSE               GPL-2.0-or-later
└── .github/              قوالب الـIssues و PRs، CODEOWNERS، CI
```



## بداية سريعة

التعليمات الكاملة موجودة في
[`astra-child/README.md`](astra-child/README.md). الخطوات المختصرة:

1. ثبِّت القالب الأب **Astra** على موقع ووردبريس.
2. انسخ مجلد `astra-child/` إلى `wp-content/themes/astra-child/`.
3. فعِّل **Astra Child** من **المظهر → القوالب**.
4. (اختياري) ارفع `robots.txt` من الجذر إلى الجذر العام للموقع، واستبدل
   `wp-content/themes/astra/header.php` بالنسخة النظيفة من جذر المستودع.

## ماذا يوجد أين

| الملف / المجلد                                             | الغرض                                                            |
| ---------------------------------------------------------- | ---------------------------------------------------------------- |
| [`astra-child/README.md`](astra-child/README.md)           | شرح كلّ وحدة SEO ومرجع الفلاتر الكامل                            |
| [`CHANGELOG.md`](CHANGELOG.md)                             | سجلّ التغييرات والإصدارات                                        |
| [`CONTRIBUTING.md`](CONTRIBUTING.md)                       | الفروع، المعايير البرمجية، الاختبار، آلية الإصدار                |
| [`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md)                 | معايير المجتمع                                                   |
| [`SECURITY.md`](SECURITY.md)                               | كيفية الإبلاغ عن مشكلة أمنية                                     |
| [`LICENSE`](LICENSE)                                       | GPL-2.0-or-later (موروث من القالب الأب Astra)                    |
| [`.github/`](.github/)                                     | قوالب PR و Issues و CODEOWNERS و workflow الـlint                |
| [`phpcs.xml.dist`](phpcs.xml.dist)                         | إعداد PHP_CodeSniffer (معايير ووردبريس) المُستخدَم في CI ومحليًا |



## وحدات SEO باختصار

يحوي القالب الفرعي ست عشرة وحدة SEO قابلة للتفعيل/التعطيل تحت
`astra-child/inc/seo/`. كلّها مفعَّلة افتراضيًا، ويمكن إيقاف أيّ منها عبر فلتر
`astra_child_seo_module_<slug>` بدون تعديل القالب. التفاصيل الكاملة وكلّ الفلاتر
في [`astra-child/README.md`](astra-child/README.md).

| الوحدة             | ما تفعله                                                                            |
| ------------------ | ----------------------------------------------------------------------------------- |
| `performance`      | `preconnect` / `dns-prefetch` لـAdSense وGoogle Fonts، تعطيل الإيموجي، تأجيل JS    |
| `arabic`           | فرض locale العربي `ar_AR` للـOG، فاصل breadcrumb مناسب لـRTL، fallback لـhreflang |
| `yoast-tweaks`     | خفض أولوية metabox الخاص بـYoast، صور OG وTwitter handle افتراضية                 |
| `schema-extras`    | شورتكود `[faq]` / `[faq_item]` يُصدر FAQPage JSON-LD                                |
| `images`           | fallback تلقائي لنص alt من عنوان المرفق أو المقال                                  |
| `robots`           | فلتر `robots.txt` (sitemap، حظر السكرابرز، تعطيل نقاط النهاية الفقيرة)             |
| `critical-css`     | تضمين Critical CSS للـabove-the-fold، تأجيل البقية                                 |
| `meta-description` | تنظيف وصف Yoast، fallback ذكي مدرك للعربية، اقتطاع `mb_*` آمن                     |
| `breadcrumbs`      | إصلاح BreadcrumbList الخاص بـYoast، إخفاء microdata المكرَّر من Astra              |
| `article-schema`   | ترقية Article إلى NewsArticle، صور بدقّات متعددة، `speakable`، `wordCount`          |
| `internal-linking` | صفحة إعدادات لـauto-linking للكلمات + ذيل "مقالات ذات صلة" تلقائي                 |
| `early-hints`      | `<link rel=preload>` + `Link:` headers (Cloudflare يُرقّيها إلى HTTP 103)            |
| `query-strings`    | إزالة `?ver=` من روابط CSS/JS المحلية حتى يحفظها CDN                              |
| `toc`              | فهرس محتويات تلقائي للمقالات الطويلة (قابل للطيّ، بدون JS)                         |
| `serp-ctr`         | robots `max-image-preview:large`، ختم السنة على العناوين الدائمة، إشارة وقت قراءة |
| `reading-time-badge` | شارة "⏱ N دقيقة قراءة" في الواجهة الأمامية، تستخدم القيمة المخزَّنة من `serp-ctr` |



## المتطلبات

- ووردبريس 6.0 أو أحدث
- PHP 7.4 أو أحدث (يُوصى بـ8.1+)
- القالب الأب [Astra](https://wordpress.org/themes/astra/)
- إضافة [Yoast SEO Free](https://wordpress.org/plugins/wordpress-seo/) — وحدات
  SEO مكمِّلة لـYoast وليست بديلًا عنه

## التطوير

لا يوجد build step. حرِّر PHP / CSS، ثم أعد تحميل المتصفح. لسير العمل الكامل
(الفروع، المعايير البرمجية، الاختبار، انضباط `CHANGELOG.md`، آلية الإصدار)
اقرأ [`CONTRIBUTING.md`](CONTRIBUTING.md).

يعمل lint بمعايير ووردبريس البرمجية على كلّ pull request. لتشغيله محليًا قبل
فتح الـPR:

```bash
composer global require --dev wp-coding-standards/wpcs:^3
phpcs                       # يستخدم phpcs.xml.dist في جذر المستودع
```

## الإبلاغ عن المشاكل

- **خطأ برمجي** ← افتح [Bug report](.github/ISSUE_TEMPLATE/bug_report.yml).
- **اقتراح ميزة** ← افتح
  [Feature request](.github/ISSUE_TEMPLATE/feature_request.yml) **قبل** كتابة
  أيّ كود حتى نتفق على النطاق.
- **مشكلة أمنية** ← راجع [`SECURITY.md`](SECURITY.md). **لا تفتح** issue عام
  لأيّ مشكلة أمنية.

## الترخيص

موزَّع تحت رخصة **GNU General Public License v2.0 أو أحدث**، الموروثة من
القالب الأب Astra. النص الكامل في [`LICENSE`](LICENSE).
