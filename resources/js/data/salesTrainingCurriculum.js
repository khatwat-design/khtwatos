
export const SLIDES = [
  // ——— الجلسة 1 ———
  {
    id: "s1-cover",
    session: 1,
    variant: "cover",
    sessionTitle: "الجلسة 1",
    sessionSubtitle: "فهم الخدمة والسوق (Product & Market Mastery)",
  },
  {
    id: "s1-objectives",
    session: 1,
    variant: "objectives",
    heading: "الأهداف",
    items: [
      "فهم حقيقي لما تبيعه الوكالة (نتائج وليس خدمات)",
      "ربط كل خدمة بمشكلة واضحة عند العميل",
      "القدرة على شرح القيمة خلال 30 ثانية",
    ],
  },
  {
    id: "s1-split",
    session: 1,
    variant: "split",
    heading: "ماذا نبيع فعليًا؟",
    negativeTitle: "لا نبيع",
    negativeItems: ["إعلانات", "تصميم", "إدارة صفحات"],
    positiveTitle: "نبيع",
    positiveItems: [
      "زيادة مبيعات",
      "تقليل تكلفة الحصول على العميل (CAC)",
      "تحسين معدل التحويل (Conversion Rate)",
    ],
  },
  {
    id: "s1-table",
    session: 1,
    variant: "table",
    heading: "تحويل الخدمات إلى نتائج",
    columns: ["الخدمة", "ماذا تعني للعميل"],
    rows: [
      ["إدارة إعلانات", "زيادة الطلبات"],
      ["تحسين صفحة", "رفع نسبة الشراء"],
      ["محتوى", "بناء ثقة تزيد المبيعات"],
    ],
  },
  {
    id: "s1-cards",
    session: 1,
    variant: "cards",
    heading: "أنواع العملاء",
    cards: [
      { title: "مبتدئ", body: "لا يفهم التسويق" },
      { title: "متوسط", body: "جرب وفشل" },
      { title: "متقدم", body: "يريد تحسين أرقام" },
    ],
  },
  {
    id: "s1-marketing-kpi",
    session: 1,
    variant: "cards",
    heading: "مؤشرات تسويقية لازم فريق المبيعات وAccount Managers يفهموها",
    cards: [
      {
        title: "CAC",
        body: "تكلفة الحصول على عميل جديد = إجمالي الإنفاق ÷ العملاء الجدد. سيناريو Sales: لا تبيع إعلان، بيع نظام يقلل CAC تدريجيا مع الوقت.",
      },
      {
        title: "نسبة التحويل",
        body: "عدد المشترين ÷ الزوار أو الرسائل × 100. سيناريو AM: إذا traffic جيد والتحويل ضعيف، نوجه العميل لتحسين الصفحة أو سكربت الإقناع.",
      },
      {
        title: "سعر الرسالة",
        body: "إجمالي تكلفة الإعلانات ÷ عدد الرسائل. سيناريو AM: CPL منخفض + Conversion منخفض = تسويق جايب تفاعل، لكن مسار الإغلاق يحتاج تطوير.",
      },
      {
        title: "ROAS",
        body: "العائد من الإعلانات ÷ تكلفة الإعلانات. سيناريو Sales: استخدم ROAS لإثبات العائد المالي بدل الكلام العام.",
      },
    ],
  },
  {
    id: "s1-formulas",
    session: 1,
    variant: "table",
    heading: "معادلات + مثال رقمي سريع",
    columns: ["المؤشر", "طريقة الحساب"],
    rows: [
      [
        "CAC",
        "إذا صرفنا 1,000$ وجبنا 20 عميل جديد → CAC = 50$ لكل عميل",
      ],
      [
        "نسبة التحويل",
        "إذا استقبلنا 200 رسالة وتم إغلاق 20 بيع → Conversion Rate = 10%",
      ],
      [
        "سعر الرسالة",
        "إذا صرفنا 600$ واستلمنا 300 رسالة → سعر الرسالة = 2$",
      ],
      [
        "ROAS",
        "إذا صرفنا 500$ وجبنا مبيعات 2,000$ → ROAS = 4x",
      ],
    ],
  },
  {
    id: "s1-funnel",
    session: 1,
    variant: "funnel",
    heading: "شكل الرحلة التسويقية من الإعلان إلى البيع",
    steps: ["مشاهدة الإعلان", "رسالة", "مكالمة/متابعة", "شراء"],
  },
  {
    id: "s1-interpretation",
    session: 1,
    variant: "list",
    heading: "شلون نفسر الأرقام بشكل بيعي داخل اجتماع العميل؟",
    items: [
      "CAC عالي + تحويل منخفض: نقول للعميل «نحتاج تحسين الرسالة التسويقية وصفحة الهبوط قبل زيادة الميزانية».",
      "CPL منخفض + Conversion منخفض: «الإعلانات جايبة اهتمام، الآن نشتغل على سكربت المبيعات وتأهيل العملاء».",
      "ROAS جيد لكن الربح قليل: «لازم نعيد ضبط العرض وسلة المنتج وهامش الربح حتى النمو يكون صحي».",
      "Sales Pitch عملي: «ما نبيع Ads فقط، نبيع نظام نمو قابل للتوسع يخفض CAC ويرفع التحويل».",
    ],
  },
  {
    id: "s1-role-connection",
    session: 1,
    variant: "list",
    heading: "ترابط الأدوار داخل الوكالة (من يرفع شنو؟)",
    items: [
      "Copywriter: يحسن الرسالة والعنوان والعرض -> يقلل CPL ويرفع جودة الـ Lead.",
      "Marketing Team: يزيد الوصول ويختبر الإعلانات -> يرفع حجم الفرص ويثبت مصادر النمو.",
      "Sales Team: يحول الفرصة إلى عقد -> يرفع الإغلاق ويخفض CAC النهائي.",
      "Account Manager: يحافظ على العميل ويزيد القيمة -> يرفع LTV ويحسن الربحية على المدى الطويل.",
    ],
  },
  {
    id: "s1-lab",
    session: 1,
    variant: "simulation",
    heading: "مختبر الأرقام (What-If Simulation)",
    description:
      "حرّك الأرقام وشوف تأثيرها مباشرة على CAC، نسبة التحويل، ROAS، والربح التقريبي. الهدف أن المندوب يفهم العلاقة بين التسويق والإغلاق.",
  },
  {
    id: "s1-marketing-vs-sales",
    session: 1,
    variant: "comparison",
    heading: "التسويق والمبيعات وجهان لنفس العملة",
    marketingTitle: "دور التسويق",
    marketingItems: [
      "يجلب Leads بجودة وسعر رسالة مناسب",
      "يرفع الوعي والثقة قبل التواصل",
      "يحسن CTR وCPC ونسبة تحويل الصفحة",
    ],
    salesTitle: "دور المبيعات",
    salesItems: [
      "تحويل الـ Leads إلى مبيعات فعلية",
      "رفع نسبة الإغلاق وتقليل وقت الرد",
      "توضيح القيمة والاعتراضات وإتمام الصفقة",
    ],
    sharedGoal: "نفس الهدف: نمو مربح ومستدام للعميل",
  },
  {
    id: "s1-ecosystem",
    session: 1,
    variant: "ecosystem",
    heading: "Agency Ecosystem: شغل الفريق كتدفق واحد",
    description:
      "Copywriter/Idea -> Marketing/Ads -> Sales/Closing -> Account Manager/Retention. أي خلل بأي مرحلة يأثر على CAC والتحويل والاحتفاظ.",
  },
  {
    id: "s1-exercise",
    session: 1,
    variant: "exercise",
    heading: "تمارين الجلسة",
    items: [
      "كل موظف يحول 3 خدمات إلى نتائج",
      "حل مثال رقمي: 800$ إنفاق + 400 رسالة + 32 عملية بيع → احسب CAC ونسبة التحويل وسعر الرسالة",
      "لكل عميل، حدد: هل المشكلة بالرسائل؟ بالتحويل؟ أو بالإغلاق؟",
      "صياغة Pitch خلال 30 ثانية: «نحن نساعد [نوع العميل] على تحقيق [نتيجة] بدون [مشكلة]»",
    ],
  },

  // ——— الجلسة 2 ———
  {
    id: "s2-cover",
    session: 2,
    variant: "cover",
    sessionTitle: "الجلسة 2",
    sessionSubtitle: "فهم العميل (Customer Psychology & Discovery)",
  },
  {
    id: "s2-objectives",
    session: 2,
    variant: "objectives",
    heading: "الأهداف",
    items: [
      "تحديد نوع العميل خلال أول 3 دقائق",
      "استخراج المشكلة الحقيقية",
      "بناء حوار ذكي بدل عرض مباشر",
    ],
  },
  {
    id: "s2-cards",
    session: 2,
    variant: "cards",
    heading: "أنواع العملاء",
    cards: [
      { title: "متردد", body: "يحتاج طمأنة" },
      { title: "مستعجل", body: "يحتاج سرعة ووضوح" },
      { title: "بخيل", body: "يحتاج أرقام" },
      { title: "فاهم", body: "يحتاج عمق وتحليل" },
    ],
  },
  {
    id: "s2-motives",
    session: 2,
    variant: "list",
    heading: "أهم 3 دوافع",
    items: ["زيادة الأرباح", "تقليل المخاطرة", "وضوح النتائج"],
  },
  {
    id: "s2-discovery",
    session: 2,
    variant: "list",
    heading: "أسئلة الاكتشاف (Discovery Questions)",
    items: [
      "كم طلب يوميًا؟",
      "كم تريد توصل؟",
      "شنو أكبر مشكلة تواجهك؟",
      "جربت تسويق قبل؟",
      "شنو كان السبب بالفشل؟",
    ],
  },
  {
    id: "s2-exercise",
    session: 2,
    variant: "exercise",
    heading: "تمارين الجلسة",
    items: [
      "Role Play: موظف يسأل فقط (بدون بيع)",
      "تحليل 5 عملاء وتحديد نوعهم",
    ],
  },

  // ——— الجلسة 3 ———
  {
    id: "s3-cover",
    session: 3,
    variant: "cover",
    sessionTitle: "الجلسة 3",
    sessionSubtitle: "هيكل عملية البيع (Sales Framework)",
  },
  {
    id: "s3-objectives",
    session: 3,
    variant: "objectives",
    heading: "الأهداف",
    items: [
      "امتلاك تسلسل واضح للمحادثة",
      "التحكم في الحوار",
    ],
  },
  {
    id: "s3-stages",
    session: 3,
    variant: "stages",
    heading: "مراحل البيع",
    stages: [
      {
        key: "Opening",
        title: "الافتتاح",
        bullets: ["كسر الجليد + إثبات اهتمام"],
        example:
          "«شفت صفحتك، واضح عندك شغل حلو بس أكو فرصة نرفع النتائج»",
      },
      {
        key: "Discovery",
        title: "الاكتشاف",
        bullets: ["جمع معلومات"],
        example: "«كم طلب يوميًا؟»",
      },
      {
        key: "Positioning",
        title: "الموضع",
        bullets: ["ربط المشكلة بالخدمة"],
        example:
          "«المشكلة مو بالإعلان… المشكلة بنسبة التحويل»",
      },
      {
        key: "Closing",
        title: "الإغلاق",
        bullets: ["طلب القرار"],
        example: "«نبدأ تجربة شهر ونقيس النتائج»",
      },
    ],
  },
  {
    id: "s3-mistakes",
    session: 3,
    variant: "list",
    heading: "أخطاء شائعة",
    items: [
      "إرسال عرض بدون فهم",
      "الكلام أكثر من الاستماع",
      "استخدام لغة تقنية مع عميل بسيط",
    ],
  },
  {
    id: "s3-exercise",
    session: 3,
    variant: "exercise",
    heading: "تمارين الجلسة",
    items: [
      "تطبيق كامل للمراحل على سيناريو حقيقي",
      "تسجيل المحادثة وتحليلها",
    ],
  },

  // ——— الجلسة 4 ———
  {
    id: "s4-cover",
    session: 4,
    variant: "cover",
    sessionTitle: "الجلسة 4",
    sessionSubtitle: "مهارات الإقناع (Persuasion Techniques)",
  },
  {
    id: "s4-objectives",
    session: 4,
    variant: "objectives",
    heading: "الأهداف",
    items: ["زيادة نسبة الإغلاق", "بناء ثقة بسرعة"],
  },
  {
    id: "s4-principles",
    session: 4,
    variant: "cards",
    heading: "مبادئ الإقناع",
    cards: [
      { title: "Social Proof", body: "نتائج عملاء سابقين" },
      { title: "Authority", body: "خبرة" },
      { title: "Scarcity", body: "فرصة محدودة" },
      { title: "Clarity", body: "وضوح" },
    ],
  },
  {
    id: "s4-numbers",
    session: 4,
    variant: "split",
    heading: "استخدام الأرقام",
    negativeTitle: "بدل",
    negativeItems: ["«نرفع مبيعاتك»"],
    positiveTitle: "قول",
    positiveItems: ["«نشتغل نوصل من 5 طلبات إلى 15–20 يوميًا»"],
  },
  {
    id: "s4-trust",
    session: 4,
    variant: "list",
    heading: "بناء الثقة",
    items: ["عرض حالات مشابهة", "شرح العملية خطوة بخطوة"],
  },
  {
    id: "s4-exercise",
    session: 4,
    variant: "exercise",
    heading: "تمارين الجلسة",
    items: [
      "كتابة 3 جمل إقناع مختلفة",
      "إعادة صياغة Pitch باستخدام أرقام",
    ],
  },

  // ——— الجلسة 5 ———
  {
    id: "s5-cover",
    session: 5,
    variant: "cover",
    sessionTitle: "الجلسة 5",
    sessionSubtitle: "التعامل مع الاعتراضات (Objection Handling)",
  },
  {
    id: "s5-objectives",
    session: 5,
    variant: "objectives",
    heading: "الأهداف",
    items: [
      "تحويل الاعتراض إلى فرصة",
      "عدم فقدان العميل بسهولة",
    ],
  },
  {
    id: "s5-structure",
    session: 5,
    variant: "list",
    heading: "هيكل الرد",
    items: ["تفهم", "توضيح", "إعادة توجيه"],
  },
  {
    id: "s5-o1",
    session: 5,
    variant: "objections",
    heading: "اعتراض شائع",
    objection: "«ما عندي ميزانية»",
    steps: [
      { label: "تفهم", text: "«تمام»" },
      { label: "توضيح", text: "«التسويق استثمار»" },
      { label: "إعادة توجيه", text: "«كم تخسر بدون تسويق؟»" },
    ],
  },
  {
    id: "s5-o2",
    session: 5,
    variant: "objections",
    heading: "اعتراض شائع",
    objection: "«جربت وما نجح»",
    steps: [
      { label: "تفهم", text: "«هذا طبيعي»" },
      { label: "توضيح", text: "«أكو أخطاء شائعة»" },
      { label: "إعادة توجيه", text: "«خلينا نصلحها»" },
    ],
  },
  {
    id: "s5-o3",
    session: 5,
    variant: "objections",
    heading: "اعتراض شائع",
    objection: "«أفكر»",
    steps: [
      { label: "تفهم", text: "«أكيد»" },
      { label: "توضيح", text: "«شنو الشي مو واضح؟»" },
      { label: "إعادة توجيه", text: "«خلينا نحسمه»" },
    ],
  },
  {
    id: "s5-exercise",
    session: 5,
    variant: "exercise",
    heading: "تمارين الجلسة",
    items: ["Role Play اعتراضات", "تقييم الردود"],
  },

  // ——— الجلسة 6 ———
  {
    id: "s6-cover",
    session: 6,
    variant: "cover",
    sessionTitle: "الجلسة 6",
    sessionSubtitle: "نظام العمل والانضباط (Sales System & KPI)",
  },
  {
    id: "s6-objectives",
    session: 6,
    variant: "objectives",
    heading: "الأهداف",
    items: ["تحويل المبيعات إلى نظام", "قياس الأداء بدقة"],
  },
  {
    id: "s6-kpi",
    session: 6,
    variant: "metrics",
    heading: "المؤشرات (KPI)",
    items: [
      "عدد المحادثات يوميًا",
      "عدد المكالمات",
      "نسبة الإغلاق",
      "وقت الرد",
    ],
  },
  {
    id: "s6-funnel",
    session: 6,
    variant: "funnel",
    heading: "Funnel",
    steps: ["Lead", "Qualification", "Call", "Close"],
  },
  {
    id: "s6-rules",
    session: 6,
    variant: "list",
    heading: "قواعد التشغيل",
    items: [
      "الرد خلال 5 دقائق",
      "طرح 3 أسئلة قبل العرض",
      "تسجيل كل عميل",
    ],
  },
  {
    id: "s6-exercise",
    session: 6,
    variant: "exercise",
    heading: "تمارين الجلسة",
    items: ["بناء Funnel كامل", "تحليل أداء يوم كامل"],
  },

  // ——— الجلسة 7 ———
  {
    id: "s7-cover",
    session: 7,
    variant: "cover",
    sessionTitle: "الجلسة 7",
    sessionSubtitle: "التطبيق العملي (Simulation & Evaluation)",
  },
  {
    id: "s7-objectives",
    session: 7,
    variant: "objectives",
    heading: "الأهداف",
    items: ["اختبار جاهزية الفريق", "تقييم الأداء الحقيقي"],
  },
  {
    id: "s7-scenario",
    session: 7,
    variant: "cards",
    heading: "السيناريو: تاجر ملابس",
    cards: [
      { title: "الوضع الحالي", body: "5 طلبات يوميًا" },
      { title: "الهدف", body: "يريد 20 طلب" },
    ],
  },
  {
    id: "s7-required",
    session: 7,
    variant: "list",
    heading: "المطلوب من الموظف",
    items: [
      "يسأل 5 أسئلة",
      "يحدد المشكلة",
      "يقدم عرض",
      "يحاول الإغلاق",
    ],
  },
  {
    id: "s7-rubric",
    session: 7,
    variant: "table",
    heading: "معايير التقييم",
    columns: ["المعيار", "الوزن"],
    rows: [
      ["الفهم", "30%"],
      ["الإقناع", "30%"],
      ["التحكم بالمحادثة", "20%"],
      ["الإغلاق", "20%"],
    ],
  },
  {
    id: "s7-exercise",
    session: 7,
    variant: "exercise",
    heading: "تمارين الجلسة",
    items: ["تسجيل الجلسات", "مراجعة جماعية", "تحسين الأداء"],
  },

  // ——— الجلسة 8 ———
  {
    id: "s8-cover",
    session: 8,
    variant: "cover",
    sessionTitle: "الجلسة 8",
    sessionSubtitle: "بناء Playbook داخلي",
  },
  {
    id: "s8-objectives",
    session: 8,
    variant: "objectives",
    heading: "الأهداف",
    items: [
      "توحيد أسلوب الفريق",
      "تسريع تدريب الموظفين الجدد",
    ],
  },
  {
    id: "s8-components",
    session: 8,
    variant: "list",
    heading: "مكونات Playbook",
    items: [
      "سكربتات جاهزة",
      "ردود اعتراضات",
      "أمثلة محادثات",
      "أفضل الممارسات",
    ],
  },
  {
    id: "s8-exercise",
    session: 8,
    variant: "exercise",
    heading: "تمارين الجلسة",
    items: [
      "كل موظف يكتب أفضل محادثة له",
      "تجميعها في ملف واحد",
      "اعتماد النسخة النهائية كنظام رسمي",
    ],
  },
];

export const SESSION_COUNT = 8;

export function getSlidesForSession(sessionId) {
  return SLIDES.filter((s) => s.session === sessionId);
}

export function getSessionSummaries() {
  const ids = Array.from({ length: SESSION_COUNT }, (_, i) => i + 1);
  return ids.map((id) => {
    const slides = getSlidesForSession(id);
    const cover = slides.find((s) => s.variant === "cover");
    const subtitle =
      cover && cover.variant === "cover" ? cover.sessionSubtitle : "";
    return {
      id,
      label: `الجلسة ${id}`,
      subtitle,
      slideCount: slides.length,
    };
  });
}
