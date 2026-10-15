<?php

declare(strict_types=1);

require_login();
if (!has_permission('customers_view')) {
    http_response_code(403);
    echo '<div class="alert danger">غير مصرح لك بالوصول لهذه الصفحة.</div>';
    return;
}

$tab = trim((string)($_GET['tab'] ?? 'ratings'));
$allowedTabs = ['ratings', 'queue', 'settings'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'ratings';
}

$stats = get_survey_stats();

$defaultSurveyMsg = "عميلنا العزيز أستاذ/ة {name} 🌹\nشكراً لزيارتك لفرعنا *{branch_name}* وتسوقك معنا اليوم! 🛍️✨\nراحتك ورضاك هي أولويتنا دائماً، ويهمنا جداً معرفة رأيك في خدمتنا:\n\nهل كانت تجربة الشراء والخدمة في الفرع مرضية لك؟\n\n🟢 راضٍ عن الخدمة 👍:\n{satisfied_link}\n\n🔴 غير راضٍ عن الخدمة 👎:\n{unsatisfied_link}\n\nأسرة {shop_name} تتمنى لك يوماً سعيداً ومعطراً! 🌟";

$surveyEnabled = setting_value('whatsapp_survey_enabled', '1') !== '0';
$delayMinutes = max(1, (int)setting_value('whatsapp_survey_delay_minutes', '10'));
$surveyMessage = (string)setting_value('whatsapp_survey_message', $defaultSurveyMsg);
if (empty(trim($surveyMessage))) {
    $surveyMessage = $defaultSurveyMsg;
}
$shopName = (string)setting_value('shop_name', 'حمزة للعطور');

// فلتر التقييمات
$ratingFilter = trim((string)($_GET['rating'] ?? ''));

// جلب البيانات حسب التبويب
$ratedRows = ($tab === 'ratings') ? get_survey_queue_rows(100, null, $ratingFilter ?: null) : [];
// فقط من قام بالتقييم للتبويب الأول
if ($tab === 'ratings' && empty($ratingFilter)) {
    $ratedRows = array_filter($ratedRows, fn($r) => !empty($r['rating']));
}

$queueRows = ($tab === 'queue') ? get_survey_queue_rows(80, 'pending') : [];

function format_delay_label(int $mins): string {
    if ($mins === 1) return 'دقيقة واحدة (1)';
    if ($mins < 60) return "{$mins} دقيقة";
    if ($mins === 60) return 'ساعة واحدة (60 دقيقة)';
    $hours = round($mins / 60, 1);
    return "{$hours} ساعة ({$mins} دقيقة)";
}
?>

<!-- Page Header Hero -->
<section class="page-head hero" style="margin-bottom: 20px;">
    <div>
        <p class="eyebrow">إدارة جودة الخدمة ورضا العملاء</p>
        <h2>⭐ استبيان وتقييم الخدمة بعد الفاتورة</h2>
        <p>إرسال رسالة تفاعلية تلقائية للعميل عبر الواتساب بعد خروجه من الكاشير لقياس مستوى رضاه عن الفرع بالمدة التي تحددها.</p>
    </div>
    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <a class="btn <?= $tab === 'ratings' ? 'primary' : '' ?>" href="index.php?r=customer_surveys&tab=ratings">📊 سجل التقييمات والآراء (<?= $stats['total_rated'] ?>)</a>
        <a class="btn <?= $tab === 'queue' ? 'primary' : '' ?>" href="index.php?r=customer_surveys&tab=queue">⏳ طابور الانتظار (<?= $stats['pending'] ?>)</a>
        <a class="btn <?= $tab === 'settings' ? 'primary' : '' ?>" href="index.php?r=customer_surveys&tab=settings">⚙️ ضبط وقت التأخير والرسالة</a>
    </div>
</section>

<!-- KPI Stats Bar -->
<section class="cards" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 22px;">
    <div class="dashboard-card-link card-sales" style="cursor: default;">
        <span class="card-icon">📈</span>
        <span class="card-label">معدل الرضا العام</span>
        <strong class="card-value" style="color: #10b981;">
            <?= $stats['satisfaction_rate'] ?>%
        </strong>
    </div>

    <div class="dashboard-card-link card-customers-today" style="cursor: default;">
        <span class="card-icon">🟢</span>
        <span class="card-label">عملاء راضون</span>
        <strong class="card-value" style="color: #10b981;"><?= $stats['satisfied'] ?></strong>
    </div>

    <div class="dashboard-card-link card-debts" style="cursor: default;">
        <span class="card-icon">🔴</span>
        <span class="card-label">عملاء غير راضين</span>
        <strong class="card-value" style="color: <?= $stats['unsatisfied'] > 0 ? '#ef4444' : 'var(--muted)' ?>;"><?= $stats['unsatisfied'] ?></strong>
    </div>

    <div class="dashboard-card-link card-invoices" style="cursor: default;">
        <span class="card-icon">⏰</span>
        <span class="card-label">وقت الإرسال بعد الفاتورة</span>
        <strong class="card-value" style="font-size: 1.1rem; color: #6366f1;">
            <?= format_delay_label($delayMinutes) ?>
        </strong>
    </div>

    <div class="dashboard-card-link card-stock" style="cursor: default;">
        <span class="card-icon">🤖</span>
        <span class="card-label">حالة الخدمة</span>
        <strong class="card-value" style="font-size: 1.05rem; color: <?= $surveyEnabled ? '#10b981' : '#dc2626' ?>;">
            <?= $surveyEnabled ? 'مفعلة وتعمل ✅' : 'معطلة ⏸️' ?>
        </strong>
    </div>
</section>

<?php if ($tab === 'ratings'): ?>
    <!-- TAB 1: RATINGS LIST -->
    <section class="panel">
        <div class="toolbar" style="justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
            <div>
                <h3 style="margin: 0;">💬 تقييمات العملاء وآرائهم</h3>
                <p class="muted" style="margin: 4px 0 0;">سجل استجابات العملاء الفورية ومعرفة أسباب عدم الرضا لمعالجتها فوراً.</p>
            </div>

            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <a class="btn small <?= empty($ratingFilter) ? 'primary' : '' ?>" href="index.php?r=customer_surveys&tab=ratings">الكل (<?= $stats['total_rated'] ?>)</a>
                <a class="btn small <?= $ratingFilter === 'satisfied' ? 'primary' : '' ?>" href="index.php?r=customer_surveys&tab=ratings&rating=satisfied" style="color: #10b981;">🟢 الراضون فقط (<?= $stats['satisfied'] ?>)</a>
                <a class="btn small <?= $ratingFilter === 'unsatisfied' ? 'primary' : '' ?>" href="index.php?r=customer_surveys&tab=ratings&rating=unsatisfied" style="color: #ef4444;">🔴 غير الراضين (<?= $stats['unsatisfied'] ?>)</a>
            </div>
        </div>

        <?php if (empty($ratedRows)): ?>
            <div style="text-align: center; padding: 45px 20px; background: rgba(0,0,0,0.02); border-radius: 12px; border: 1px dashed var(--line);">
                <div style="font-size: 42px; margin-bottom: 12px;">🌟</div>
                <h4 style="margin: 0 0 6px 0;">لا توجد تقييمات مسجلة بعد</h4>
                <p class="muted" style="max-width: 480px; margin: 0 auto;">
                    عندما يتم إنشاء فواتير جديدة في الكاشير، سيرسل النظام رسالة الاستبيان للعملاء تلقائياً وستظهر تقييماتهم هنا فوراً.
                </p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>العميل</th>
                            <th>رقم الهاتف</th>
                            <th>الفرع</th>
                            <th>الفاتورة</th>
                            <th>التقييم</th>
                            <th>ملاحظات وشكوى العميل</th>
                            <th>تاريخ التقييم</th>
                            <th style="text-align: center;">إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ratedRows as $row): 
                            $isSat = ($row['rating'] === 'satisfied');
                            $rowData = [
                                'customer_name' => $row['customer_name'] ?? 'زبون عابر',
                                'customer_id' => !empty($row['customer_id']) ? (int)$row['customer_id'] : null,
                                'phone' => $row['phone'] ?? '',
                                'location_name' => $row['location_name'] ?? '-',
                                'invoice_id' => (int)$row['invoice_id'],
                                'invoice_number' => $row['invoice_number'] ?? ('#' . $row['invoice_id']),
                                'invoice_total' => !empty($row['invoice_total']) ? number_format((float)$row['invoice_total'], 2) : '',
                                'rating' => $row['rating'] ?? '',
                                'is_satisfied' => $isSat,
                                'feedback_notes' => trim((string)($row['feedback_notes'] ?? '')),
                                'date' => !empty($row['rated_at']) ? date('d/m/Y h:i A', strtotime($row['rated_at'])) : date('d/m/Y h:i A', strtotime($row['created_at'])),
                            ];
                            $rowJson = htmlspecialchars(json_encode($rowData, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                        ?>
                            <tr class="review-row-clickable" style="<?= !$isSat ? 'background: rgba(239, 68, 68, 0.05);' : '' ?> cursor: pointer;" onclick="openReviewModal(<?= $rowJson ?>)">
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span class="cust-name-badge" title="اضغط هنا لعرض رأي العميل بالكامل على الشاشة">
                                            <span style="font-size: 16px;">👤</span>
                                            <strong style="color: var(--gold, #C9A84C); font-size: 15px; text-decoration: underline; text-underline-offset: 4px;"><?= e($row['customer_name'] ?? 'زبون عابر') ?></strong>
                                            <span style="font-size: 11px; background: rgba(201,168,76,0.15); color: var(--gold, #C9A84C); padding: 2px 7px; border-radius: 4px; border: 1px solid rgba(201,168,76,0.3); font-weight: 600;">👁️ عرض الرأي</span>
                                        </span>
                                    </div>
                                    <?php if (!empty($row['customer_id'])): ?>
                                        <a href="index.php?r=customer_view&id=<?= e($row['customer_id']) ?>" onclick="event.stopPropagation();" style="font-size: 11px; color: #94a3b8; text-decoration: underline; display: inline-block; margin-top: 4px;">
                                            بروفايل العميل
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span dir="ltr" style="font-family: monospace; font-weight: 600;"><?= e($row['phone']) ?></span>
                                    <a href="https://wa.me/2<?= preg_replace('/\D/', '', $row['phone']) ?>" onclick="event.stopPropagation();" target="_blank" title="محادثة واتساب مباشرة" style="text-decoration: none; margin-right: 4px;">💬</a>
                                </td>
                                <td><?= e($row['location_name'] ?? '-') ?></td>
                                <td>
                                    <a href="index.php?r=invoice_view&id=<?= e($row['invoice_id']) ?>" onclick="event.stopPropagation();" title="عرض الفاتورة">
                                        <?= e($row['invoice_number'] ?? '#' . $row['invoice_id']) ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if ($isSat): ?>
                                        <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; font-weight: 700;">
                                            🟢 راضٍ جداً 👍
                                        </span>
                                    <?php else: ?>
                                        <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; font-weight: 700;">
                                            🔴 غير راضٍ 👎
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($row['feedback_notes'])): ?>
                                        <div style="max-width: 320px; font-size: 13px; line-height: 1.4; color: <?= !$isSat ? '#f87171' : 'var(--text)' ?>; background: rgba(0,0,0,0.03); padding: 6px 10px; border-radius: 6px;">
                                            <?= e(mb_strimwidth($row['feedback_notes'], 0, 80, '...')) ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="muted" style="font-size: 12px;">بدون ملاحظات إضافية</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= !empty($row['rated_at']) ? date('d/m/Y h:i A', strtotime($row['rated_at'])) : date('d/m/Y h:i A', strtotime($row['created_at'])) ?>
                                </td>
                                <td style="text-align: center; white-space: nowrap;">
                                    <button type="button" class="btn small" onclick="event.stopPropagation(); openReviewModal(<?= $rowJson ?>)" style="background: rgba(201, 168, 76, 0.2); color: var(--gold, #C9A84C); border: 1px solid var(--gold, #C9A84C); font-weight: bold; margin-left: 4px;">
                                        👁️ عرض الرأي
                                    </button>
                                    <a class="btn small" onclick="event.stopPropagation();" href="https://wa.me/2<?= preg_replace('/\D/', '', $row['phone']) ?>?text=<?= urlencode('أهلاً بك أستاذ ' . ($row['customer_name'] ?? '') . '، نتواصل معك بخصوص زيارتك لفرع ' . ($row['location_name'] ?? 'حمزة للعطور')) ?>" target="_blank" style="background: #25D366; color: #fff; text-decoration: none;">
                                        واتساب
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

<?php elseif ($tab === 'queue'): ?>
    <!-- TAB 2: SCHEDULED QUEUE -->
    <section class="panel">
        <div class="toolbar" style="justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
            <div>
                <h3 style="margin: 0;">⏳ طابور رسائل الاستبيان المجدولة</h3>
                <p class="muted" style="margin: 4px 0 0;">
                    رسائل الفواتير الصادرة حديثاً التي تنتظر انقضاء فترة التأخير (<?= format_delay_label($delayMinutes) ?>) لإرسالها للعميل تلقائياً.
                </p>
            </div>

            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <?php if (!empty($queueRows)): ?>
                    <form method="post" onsubmit="return confirm('هل تريد إرسال جميع الرسائل الجاهزة في الطابور فوراً عبر الواتساب؟');" style="margin: 0;">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="process_queue_now">
                        <button type="submit" class="btn primary" style="background: #16a34a; border-color: #16a34a; font-weight: 700;">
                            🚀 إرسال كل الرسائل الجاهزة الآن
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($queueRows)): ?>
            <div style="text-align: center; padding: 40px 20px; background: rgba(0,0,0,0.02); border-radius: 12px;">
                <div style="font-size: 38px; margin-bottom: 8px;">☕</div>
                <h4 style="margin: 0 0 4px 0;">طابور الانتظار فارغ حالياً</h4>
                <p class="muted" style="margin: 0;">لا توجد أي فواتير بانتظار الإرسال في هذه اللحظة.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>الفاتورة</th>
                            <th>العميل</th>
                            <th>الهاتف</th>
                            <th>الفرع</th>
                            <th>وقت الفاتورة</th>
                            <th>موعد الإرسال المجدول</th>
                            <th>الحالة</th>
                            <th style="text-align: center;">إجراء فوري</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($queueRows as $q): 
                            $isDue = strtotime($q['scheduled_at']) <= time();
                        ?>
                            <tr>
                                <td>
                                    <a href="index.php?r=invoice_view&id=<?= e($q['invoice_id']) ?>">
                                        <?= e($q['invoice_number'] ?? '#' . $q['invoice_id']) ?>
                                    </a>
                                </td>
                                <td><strong><?= e($q['customer_name'] ?? 'زبون عابر') ?></strong></td>
                                <td dir="ltr" style="font-family: monospace;"><?= e($q['phone']) ?></td>
                                <td><?= e($q['location_name'] ?? '-') ?></td>
                                <td><?= date('h:i A', strtotime($q['created_at'])) ?></td>
                                <td>
                                    <strong><?= date('h:i A', strtotime($q['scheduled_at'])) ?></strong>
                                    <?php if ($isDue): ?>
                                        <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; font-size: 11px;">حان موعدها</span>
                                    <?php else: ?>
                                        <span class="badge" style="background: rgba(234, 179, 8, 0.15); color: #b45309; font-size: 11px;">قيد الانتظار</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge" style="background: rgba(99, 102, 241, 0.15); color: #6366f1;">
                                        ⏳ بانتظار الكرون
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <div style="display: inline-flex; gap: 6px;">
                                        <form method="post" style="margin: 0;">
                                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="send_now">
                                            <input type="hidden" name="queue_id" value="<?= e($q['id']) ?>">
                                            <button type="submit" class="btn small primary" style="background: #16a34a; border-color: #16a34a;">
                                                إرسال الآن 🚀
                                            </button>
                                        </form>

                                        <form method="post" onsubmit="return confirm('هل تريد إلغاء إرسال هذا الاستبيان؟');" style="margin: 0;">
                                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="queue_id" value="<?= e($q['id']) ?>">
                                            <button type="submit" class="btn small danger">إلغاء</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

<?php elseif ($tab === 'settings'): ?>
    <!-- TAB 3: SETTINGS -->
    <section class="panel" style="max-width: 900px; margin: 0 auto;">
        <div class="toolbar" style="margin-bottom: 20px; border-bottom: 1px solid var(--line); padding-bottom: 12px;">
            <div>
                <h3 style="margin: 0;">⚙️ إعدادات استبيان الرضا ووقت التأخير بعد الفاتورة</h3>
                <p class="muted" style="margin: 4px 0 0;">حدد مدة التأخير المطلوبة بعد خروج الفاتورة من الكاشير وتخصيص نص الرسالة والأزرار.</p>
            </div>
        </div>

        <form method="post" class="grid-form" style="grid-template-columns: 1fr;">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_settings">

            <!-- Card 1: Toggle & Delay Minutes -->
            <div style="background: var(--field-bg, rgba(0,0,0,0.02)); border: 1px solid var(--line); border-radius: 12px; padding: 18px; margin-bottom: 16px;">
                <h4 style="margin: 0 0 14px 0; display: flex; align-items: center; gap: 8px;">
                    <span>⏱️ توقيت الإرسال بعد خروج الفاتورة</span>
                </h4>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; align-items: start;">
                    <label style="cursor: pointer; display: flex; align-items: center; gap: 10px; padding: 12px; background: var(--surface); border: 1px solid var(--line); border-radius: 8px;">
                        <input type="checkbox" name="whatsapp_survey_enabled" value="1" <?= $surveyEnabled ? 'checked' : '' ?> style="width: 18px; height: 18px;">
                        <div>
                            <strong>تفعيل رسالة قياس رضا العميل بعد الفاتورة</strong>
                            <div class="muted" style="font-size: 12px;">تُرسل تلقائياً للعميل المسجل بعد عملية البيع في الكاشير.</div>
                        </div>
                    </label>

                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <label for="delayInput">
                            <strong>فترة التأخير بعد الفاتورة (بالدقائق):</strong>
                        </label>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <input type="number" name="whatsapp_survey_delay_minutes" id="delayInput" value="<?= e($delayMinutes) ?>" min="1" max="1440" required style="font-size: 18px; font-weight: bold; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--line); background: var(--surface); color: var(--ink); width: 140px;" oninput="updateDelayPreview(this.value)">
                            <span style="font-weight: 600; font-size: 15px;">دقيقة بعد الفاتورة</span>
                        </div>

                        <div style="display: flex; gap: 6px; flex-wrap: wrap; align-items: center; margin-top: 4px;">
                            <span class="muted" style="font-size: 11px;">خيارات سريعة:</span>
                            <button type="button" class="btn small" onclick="setDelayVal(1)">1 دقيقة</button>
                            <button type="button" class="btn small" onclick="setDelayVal(5)">5 دقائق</button>
                            <button type="button" class="btn small" onclick="setDelayVal(10)">10 دقائق</button>
                            <button type="button" class="btn small" onclick="setDelayVal(15)">15 دقيقة</button>
                            <button type="button" class="btn small" onclick="setDelayVal(30)">30 دقيقة</button>
                            <button type="button" class="btn small" onclick="setDelayVal(60)">ساعة (60 د)</button>
                            <button type="button" class="btn small" onclick="setDelayVal(120)">ساعتين (120 د)</button>
                        </div>
                        <small class="muted" style="font-size: 12px; line-height: 1.4;">
                            💡 مثال: إذا اخترت <strong>10 دقائق</strong>، فور قيام الكاشير بحفظ الفاتورة، ينتظر النظام 10 دقائق ثم يرسل رسالة التقييم للعميل.
                        </small>
                    </div>
                </div>
            </div>

            <!-- Card 2: Message Template -->
            <div style="background: var(--field-bg, rgba(0,0,0,0.02)); border: 1px solid var(--line); border-radius: 12px; padding: 18px; margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 8px;">
                    <h4 style="margin: 0; display: flex; align-items: center; gap: 8px;">
                        <span>✍️ قالب رسالة تقييم الخدمة</span>
                    </h4>
                    <button type="button" class="btn small" onclick="resetToDefaultSurveyMessage()">استعادة النص الافتراضي</button>
                </div>

                <div style="margin-bottom: 10px; font-size: 13px;">
                    <span>المتغيرات المتاحة للاستخدام في النص (اضغط للإدراج):</span>
                    <div style="display: flex; gap: 6px; margin-top: 6px; flex-wrap: wrap;">
                        <button type="button" class="badge" onclick="insertSurveyVar('{name}')" style="cursor: pointer; background: rgba(99, 102, 241, 0.15); color: #6366f1; border: none; padding: 4px 10px;">{name} : اسم العميل</button>
                        <button type="button" class="badge" onclick="insertSurveyVar('{branch_name}')" style="cursor: pointer; background: rgba(16, 185, 129, 0.15); color: #10b981; border: none; padding: 4px 10px;">{branch_name} : اسم الفرع</button>
                        <button type="button" class="badge" onclick="insertSurveyVar('{invoice_number}')" style="cursor: pointer; background: rgba(234, 179, 8, 0.15); color: #b45309; border: none; padding: 4px 10px;">{invoice_number} : رقم الفاتورة</button>
                        <button type="button" class="badge" onclick="insertSurveyVar('{shop_name}')" style="cursor: pointer; background: rgba(197, 160, 89, 0.15); color: #c5a059; border: none; padding: 4px 10px;">{shop_name} : اسم المتجر (<?= e($shopName) ?>)</button>
                    </div>
                </div>

                <label style="display: block;">
                    <textarea name="whatsapp_survey_message" id="surveyMessageTextarea" rows="8" style="width: 100%; font-size: 14px; line-height: 1.6; border-radius: 8px;" oninput="updateLiveSurveyPreview()"><?= e($surveyMessage) ?></textarea>
                </label>

                <!-- Live WhatsApp Preview Box -->
                <div style="margin-top: 14px; background: #e5ddd5; border-radius: 12px; padding: 16px; border: 1px solid rgba(0,0,0,0.1);">
                    <div style="font-size: 12px; font-weight: 700; color: #075e54; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                        <span>📱 شكل الرسالة على هاتف العميل عبر الواتساب:</span>
                    </div>
                    <div style="background: #ffffff; border-radius: 8px; padding: 12px 14px; max-width: 480px; box-shadow: 0 1px 3px rgba(0,0,0,0.15); font-size: 14px; line-height: 1.6; color: #111827; white-space: pre-wrap;" id="liveSurveyPreviewBox"></div>
                </div>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <a class="btn" href="index.php?r=customer_surveys&tab=ratings">إلغاء</a>
                <button type="submit" class="btn primary" style="min-width: 160px; font-weight: 700;">💾 حفظ الإعدادات والتأخير</button>
            </div>
        </form>
    </section>
<?php endif; ?>

<!-- نافذة عرض رأي العميل كاملة (Full View Modal) -->
<div id="customerReviewModal" style="display: none; position: fixed; inset: 0; z-index: 999999; background: rgba(8, 12, 22, 0.94); backdrop-filter: blur(12px); align-items: center; justify-content: center; padding: 16px; box-sizing: border-box;" onclick="if(event.target === this) closeReviewModal();">
    <div style="background: linear-gradient(145deg, #182235, #0f172a); border: 2px solid rgba(201, 168, 76, 0.45); border-radius: 20px; width: 100%; max-width: 950px; max-height: 92vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 25px 70px rgba(0,0,0,0.9), 0 0 50px rgba(201,168,76,0.18); animation: zoomInReview 0.25s cubic-bezier(0.16, 1, 0.3, 1);">
        
        <!-- Modal Header -->
        <div style="padding: 18px 24px; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: space-between; background: rgba(0,0,0,0.3);">
            <div style="display: flex; align-items: center; gap: 12px;">
                <span style="font-size: 28px;">💬</span>
                <div>
                    <h3 style="margin: 0; color: var(--gold, #C9A84C); font-size: 20px; font-weight: 700;">تفاصيل رأي واستبيان العميل</h3>
                    <p class="muted" style="margin: 2px 0 0; font-size: 13px;">مراجعة تقييم العميل ورأيه بالكامل والتواصل المباشر معه</p>
                </div>
            </div>
            <button type="button" onclick="closeReviewModal()" style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.2); color: #fff; width: 40px; height: 40px; border-radius: 50%; font-size: 20px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s;" onmouseover="this.style.background='rgba(239,68,68,0.2)';this.style.color='#ef4444';" onmouseout="this.style.background='rgba(255,255,255,0.08)';this.style.color='#fff';" title="إغلاق (Esc)">
                ✕
            </button>
        </div>

        <!-- Modal Body -->
        <div style="padding: 24px; overflow-y: auto; display: flex; flex-direction: column; gap: 20px;">
            
            <!-- Customer & Invoice Summary Card -->
            <div style="background: rgba(0,0,0,0.35); border-radius: 14px; padding: 18px 20px; border: 1px solid rgba(255,255,255,0.08); display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                <div>
                    <span class="muted" style="font-size: 12px; display: block; margin-bottom: 4px;">👤 العميل</span>
                    <strong id="modalCustName" style="font-size: 20px; color: #fff; display: block;">-</strong>
                    <span id="modalCustLinkWrap" style="font-size: 12px; margin-top: 3px; display: block;"></span>
                </div>
                <div>
                    <span class="muted" style="font-size: 12px; display: block; margin-bottom: 4px;">📞 رقم الهاتف</span>
                    <strong id="modalCustPhone" dir="ltr" style="font-size: 17px; color: #38bdf8; font-family: monospace; display: block;">-</strong>
                </div>
                <div>
                    <span class="muted" style="font-size: 12px; display: block; margin-bottom: 4px;">🏢 الفرع</span>
                    <strong id="modalCustBranch" style="font-size: 16px; color: #e2e8f0; display: block;">-</strong>
                </div>
                <div>
                    <span class="muted" style="font-size: 12px; display: block; margin-bottom: 4px;">🧾 الفاتورة</span>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <strong id="modalCustInvoice" style="font-size: 16px; color: var(--gold, #C9A84C);">-</strong>
                        <span id="modalCustInvoiceTotal" class="badge" style="background: rgba(201,168,76,0.15); color: var(--gold, #C9A84C); font-size: 12px;"></span>
                    </div>
                </div>
                <div>
                    <span class="muted" style="font-size: 12px; display: block; margin-bottom: 4px;">🕒 وقت وتاريخ التقييم</span>
                    <span id="modalCustDate" style="font-size: 14px; color: #cbd5e1; font-weight: 500;">-</span>
                </div>
            </div>

            <!-- Rating Status Banner -->
            <div id="modalRatingBanner" style="padding: 16px 20px; border-radius: 12px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                <div style="display: flex; align-items: center; gap: 14px;">
                    <span id="modalRatingIcon" style="font-size: 34px;">👍</span>
                    <div>
                        <strong id="modalRatingText" style="font-size: 18px; display: block;">-</strong>
                        <span id="modalRatingSub" style="font-size: 13px; opacity: 0.9;">-</span>
                    </div>
                </div>
            </div>

            <!-- Opinion / Feedback Block (The Core) -->
            <div style="background: rgba(15, 23, 42, 0.75); border: 2px dashed rgba(201, 168, 76, 0.45); border-radius: 16px; padding: 26px; position: relative;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 10px;">
                    <span style="font-size: 16px; font-weight: 700; color: var(--gold, #C9A84C); display: flex; align-items: center; gap: 8px;">
                        <span>✍️ رأي وملاحظات العميل:</span>
                    </span>
                    <span style="font-size: 32px; opacity: 0.25; line-height: 1; font-family: Georgia, serif;">❝</span>
                </div>
                <div id="modalCustFeedback" style="font-size: 20px; line-height: 1.85; color: #ffffff; white-space: pre-wrap; font-weight: 500; min-height: 80px;">
                    -
                </div>
            </div>

        </div>

        <!-- Modal Footer Actions -->
        <div style="padding: 18px 24px; background: rgba(0,0,0,0.3); border-top: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a id="modalWhatsAppBtn" href="#" target="_blank" class="btn" style="background: #25D366; color: #fff; font-weight: bold; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; padding: 10px 20px; border-radius: 8px; font-size: 15px;">
                    💬 محادثة واتساب فورية مع العميل
                </a>
                <a id="modalInvoiceBtn" href="#" class="btn" style="background: rgba(255,255,255,0.1); color: #fff; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; padding: 10px 18px; border-radius: 8px;">
                    📄 فتح تفاصيل الفاتورة
                </a>
            </div>
            <button type="button" class="btn" onclick="closeReviewModal()" style="background: rgba(255,255,255,0.08); color: #cbd5e1; padding: 10px 24px; border-radius: 8px; font-weight: 600;">
                إغلاق (Esc)
            </button>
        </div>
    </div>
</div>

<style>
@keyframes zoomInReview {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}
.review-row-clickable {
    cursor: pointer;
    transition: background 0.15s ease, transform 0.1s ease;
}
.review-row-clickable:hover {
    background: rgba(201, 168, 76, 0.08) !important;
}
.cust-name-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 6px;
    border-radius: 6px;
    transition: background 0.15s;
}
.cust-name-badge:hover {
    background: rgba(201, 168, 76, 0.15);
}
</style>

<script>
const defaultSurveyMsgTemplate = <?= json_encode($defaultSurveyMsg, JSON_UNESCAPED_UNICODE) ?>;
const shopBrandName = <?= json_encode($shopName, JSON_UNESCAPED_UNICODE) ?>;

function openReviewModal(data) {
    if (!data) return;
    document.getElementById('modalCustName').textContent = data.customer_name || 'عميل';
    
    const wrap = document.getElementById('modalCustLinkWrap');
    if (data.customer_id) {
        wrap.innerHTML = `<a href="index.php?r=customer_view&id=${data.customer_id}" target="_blank" onclick="event.stopPropagation();" style="color: var(--gold, #C9A84C); text-decoration: underline;">فتح بروفايل العميل ↗</a>`;
    } else {
        wrap.innerHTML = '<span class="muted">زبون عابر</span>';
    }

    document.getElementById('modalCustPhone').textContent = data.phone || '-';
    document.getElementById('modalCustBranch').textContent = data.location_name || '-';
    document.getElementById('modalCustInvoice').textContent = data.invoice_number || '-';
    document.getElementById('modalCustInvoiceTotal').textContent = data.invoice_total ? (data.invoice_total + ' ج.م') : '';
    document.getElementById('modalCustDate').textContent = data.date || '-';

    // Rating Banner
    const banner = document.getElementById('modalRatingBanner');
    const icon = document.getElementById('modalRatingIcon');
    const text = document.getElementById('modalRatingText');
    const sub = document.getElementById('modalRatingSub');

    if (data.is_satisfied) {
        banner.style.background = 'rgba(16, 185, 129, 0.15)';
        banner.style.border = '1px solid #10b981';
        banner.style.color = '#10b981';
        icon.textContent = '🟢 👍';
        text.textContent = 'العميل راضٍ جداً عن الخدمة والمنتج';
        sub.textContent = 'تم تسجيل هذا التقييم الإيجابي عبر رابط الاستبيان التفاعلي';
    } else {
        banner.style.background = 'rgba(239, 68, 68, 0.18)';
        banner.style.border = '1px solid #ef4444';
        banner.style.color = '#ef4444';
        icon.textContent = '🔴 👎';
        text.textContent = 'العميل غير راضٍ عن الخدمة / المنتج';
        sub.textContent = 'يُرجى التواصل السريع مع العميل لحل مشكلته ومعالجة أسباب عدم الرضا';
    }

    // Feedback Notes
    const feedbackEl = document.getElementById('modalCustFeedback');
    if (data.feedback_notes && data.feedback_notes.trim() !== '') {
        feedbackEl.textContent = data.feedback_notes;
        feedbackEl.style.fontStyle = 'normal';
        feedbackEl.style.color = data.is_satisfied ? '#ffffff' : '#fca5a5';
    } else {
        feedbackEl.textContent = 'لم يترك العميل تعليقاً نصياً مكتوباً، واكتفى باختيار التقييم (راضي / غير راضي) في رسالة الاستبيان.';
        feedbackEl.style.fontStyle = 'italic';
        feedbackEl.style.color = '#94a3b8';
    }

    // Action buttons
    const cleanPhone = (data.phone || '').replace(/\D/g, '');
    const waText = encodeURIComponent(`أهلاً بك أستاذ ${data.customer_name || ''}، نتواصل معك من إدارة ${shopBrandName} بخصوص تقييمك لزيارتك لفرع ${data.location_name || ''}...`);
    document.getElementById('modalWhatsAppBtn').href = `https://wa.me/2${cleanPhone}?text=${waText}`;

    document.getElementById('modalInvoiceBtn').href = `index.php?r=invoice_view&id=${data.invoice_id}`;

    const modal = document.getElementById('customerReviewModal');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeReviewModal() {
    const modal = document.getElementById('customerReviewModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeReviewModal();
    }
});

function updateLiveSurveyPreview() {
    const textarea = document.getElementById('surveyMessageTextarea');
    const preview = document.getElementById('liveSurveyPreviewBox');
    if (!textarea || !preview) return;

    let text = textarea.value || '';
    text = text.replace(/{name}/g, 'محمد أحمد');
    text = text.replace(/{branch_name}/g, 'فرع المنوات');
    text = text.replace(/{shop_name}/g, shopBrandName);
    text = text.replace(/{invoice_number}/g, '2055');
    text = text.replace(/{satisfied_link}/g, 'https://erp.alulaprint.com/survey.php?t=demo&r=1');
    text = text.replace(/{unsatisfied_link}/g, 'https://erp.alulaprint.com/survey.php?t=demo&r=0');
    preview.textContent = text;
}

function insertSurveyVar(variable) {
    const textarea = document.getElementById('surveyMessageTextarea');
    if (!textarea) return;

    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const current = textarea.value;

    textarea.value = current.substring(0, start) + variable + current.substring(end);
    textarea.focus();
    textarea.selectionStart = textarea.selectionEnd = start + variable.length;
    updateLiveSurveyPreview();
}

function resetToDefaultSurveyMessage() {
    if (confirm('هل أنت متأكد من استعادة نص الرسالة الافتراضي؟')) {
        const textarea = document.getElementById('surveyMessageTextarea');
        if (textarea) {
            textarea.value = defaultSurveyMsgTemplate;
            updateLiveSurveyPreview();
        }
    }
}

function setDelayVal(mins) {
    const input = document.getElementById('delayInput');
    if (input) {
        input.value = mins;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    updateLiveSurveyPreview();
});
</script>
