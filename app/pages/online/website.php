<?php $ordersCount = pdo()->query('SELECT COUNT(*) FROM online_orders')->fetchColumn(); ?>
<section class="page-head"><h2>صفحات الموقع</h2><p>واجهة تعريفية تشغيلية للبراند وتجهيز المتجر للربط مع النظام.</p></section>
<section class="cards"><article><span>طلبات أونلاين</span><strong><?= e($ordersCount) ?></strong></article><article><span>حالات الطلب</span><strong>جديد، تجهيز، شحن، تسليم</strong></article><article><span>مخزون الأونلاين</span><strong>موقع مستقل</strong></article></section>
<section class="split">
    <div class="panel"><h3>الصفحة الرئيسية</h3><p>قسم hero للبراند، منتجات مميزة، دعوة للشراء، وروابط الفروع والتواصل.</p></div>
    <div class="panel"><h3>من نحن والتواصل</h3><p>وصف البراند، أرقام التواصل، واتساب، وروابط السوشيال.</p></div>
    <div class="panel"><h3>الفروع</h3><p>ام خنان والمنوات مع العنوان، مواعيد العمل، وموقع الخريطة.</p></div>
    <div class="panel"><h3>المتجر</h3><p>عرض المنتجات من جدول المنتجات، فلترة حسب النوع، وسلة شراء كمرحلة تالية.</p></div>
</section>
