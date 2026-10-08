<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

$today   = new DateTimeImmutable('today');
$weekArg = $_GET['week'] ?? null;
$monday  = ny_week_start(is_string($weekArg) ? $weekArg : null);
$sunday  = $monday->modify('+6 days');

$prevWeek = $monday->modify('-7 days')->format('Y-m-d');
$nextWeek = $monday->modify('+7 days')->format('Y-m-d');

$pdo  = ny_db();
$s    = ny_settings_all();
$iban = trim((string)($s['bank_iban'] ?? ''));
$classes = $pdo->query(
    'SELECT * FROM ny_classes WHERE active = 1 ORDER BY day_of_week, start_time'
)->fetchAll();

$weekStart = $monday->format('Y-m-d');
$weekEnd   = $sunday->format('Y-m-d');
$countsStmt = $pdo->prepare(
    "SELECT class_id, class_date, COUNT(*) AS n
       FROM ny_reservations
      WHERE status = 'booked' AND class_date BETWEEN ? AND ?
      GROUP BY class_id, class_date"
);
$countsStmt->execute([$weekStart, $weekEnd]);
$counts = [];
foreach ($countsStmt as $r) {
    $counts[$r['class_id'] . '|' . $r['class_date']] = (int)$r['n'];
}

$mine = [];
$user = ny_current_user();
$userBalance = $user ? ny_user_balance_kc((int)$user['id']) : 0;

// Surname of the signed-in attendee, appended to the SPAYD MSG so the
// payment arrives on the studio's account tagged with the person's name.
$userSurname = '';
if ($user) {
    $parts = preg_split('/\s+/u', trim((string)($user['display_name'] ?? ''))) ?: [];
    $userSurname = $parts ? (string)end($parts) : '';
    $userSurname = preg_replace('/\s+/u', '', $userSurname);
}

// SPAYD MSG must be ASCII and stays under qrcodejs' type-10 byte capacity
// (~108 bytes with correctLevel M). Czech diacritics transliterate to ASCII
// so Czech banking apps display the payment reference correctly.
function ny_ascii_msg(string $s): string {
    static $map = [
        'á'=>'a','č'=>'c','ď'=>'d','é'=>'e','ě'=>'e','í'=>'i','ň'=>'n',
        'ó'=>'o','ř'=>'r','š'=>'s','ť'=>'t','ú'=>'u','ů'=>'u','ý'=>'y','ž'=>'z',
        'Á'=>'A','Č'=>'C','Ď'=>'D','É'=>'E','Ě'=>'E','Í'=>'I','Ň'=>'N',
        'Ó'=>'O','Ř'=>'R','Š'=>'S','Ť'=>'T','Ú'=>'U','Ů'=>'U','Ý'=>'Y','Ž'=>'Z',
        '–'=>'-','—'=>'-','„'=>'"','"'=>'"','"'=>'"','‚'=>"'", '‘'=>"'", '’'=>"'",
    ];
    $s = strtr($s, $map);
    $s = preg_replace('/[^\x20-\x7E]/', '', $s);
    return (string)preg_replace('/\s+/', ' ', trim((string)$s));
}

// Rosters: first-name lists per class + date. Only exposed to signed-in
// members so casual visitors / bots don't scrape attendee lists.
$rosters = [];
if ($user) {
    $rosterStmt = $pdo->prepare(
        "SELECT r.class_id, r.class_date, u.display_name
           FROM ny_reservations r
           JOIN ny_users u ON u.id = r.user_id
          WHERE r.status = 'booked' AND r.class_date BETWEEN ? AND ?
          ORDER BY u.display_name"
    );
    $rosterStmt->execute([$weekStart, $weekEnd]);
    foreach ($rosterStmt as $r) {
        $first = trim((string)$r['display_name']);
        if ($first === '') continue;
        // Keep only the first token (first name).
        $first = preg_split('/\s+/u', $first, 2)[0] ?? '';
        if ($first === '') continue;
        $rosters[$r['class_id'] . '|' . $r['class_date']][] = $first;
    }
}
if ($user) {
    $mineStmt = $pdo->prepare(
        "SELECT class_id, class_date FROM ny_reservations
          WHERE user_id = ? AND status = 'booked'
            AND class_date BETWEEN ? AND ?"
    );
    $mineStmt->execute([$user['id'], $weekStart, $weekEnd]);
    foreach ($mineStmt as $r) {
        $mine[$r['class_id'] . '|' . $r['class_date']] = true;
    }
}

// ny_category() + ny_class_price() live in src/layout.php so they are shared
// with the credit-payment code path in ny_reserve_class().

$weekIsoNum = (int)$monday->format('W');

ny_render_header(t('rezervace.title'), 'schedule', ['description' => t('rezervace.meta.description')]);
?>
<section class="section-title-block">
    <div class="eyebrow"><?= e(t('rezervace.eyebrow')) ?></div>
    <h1 class="page-title"><?= e(t('rezervace.title')) ?></h1>
    <p class="page-lead">
        <?= e(t('rezervace.lead')) ?>
    </p>
</section>

<div class="schedule-toolbar">
    <div class="week-nav" role="navigation" aria-label="<?= e(t('rezervace.nav.aria')) ?>">
        <a href="?week=<?= e($prevWeek) ?>" aria-label="<?= e(t('rezervace.nav.prev')) ?>"><?= ny_icon('chevron-left', 16) ?></a>
        <div class="range">
            <strong><?= e($monday->format('j. n.')) ?> – <?= e($sunday->format('j. n. Y')) ?></strong>
            <small><?= e(t('rezervace.week.label')) ?> <?= $weekIsoNum ?></small>
        </div>
        <a href="?week=<?= e($nextWeek) ?>" aria-label="<?= e(t('rezervace.nav.next')) ?>"><?= ny_icon('chevron-right', 16) ?></a>
        <a href="?"><?= e(t('rezervace.nav.today')) ?></a>
    </div>
    <?php if (!$user): ?>
        <span class="hint">
            <?= t('rezervace.login.hint') ?>
        </span>
    <?php else: ?>
        <span class="hint">
            <?= sprintf(e(t('rezervace.credits.balance')), (int)$userBalance) ?>
            · <a href="myprofile.php#credits"><?= e(t('rezervace.credits.topup')) ?></a>
        </span>
    <?php endif; ?>
</div>

<p class="schedule-pay-hint">
    <?= ny_icon('qr-code', 16) ?>
    <span><?= e(t('rezervace.pay.hint')) ?></span>
</p>

<div class="week-grid">
<?php for ($d = 1; $d <= 7; $d++):
    $date       = $monday->modify('+' . ($d - 1) . ' days');
    $dateStr    = $date->format('Y-m-d');
    $isPast     = $date < $today;
    $isToday    = $date == $today;
    $dayClasses = array_values(array_filter($classes, function ($c) use ($d, $dateStr) {
        if ((int)$c['day_of_week'] !== $d) return false;
        if (!empty($c['starts_on']) && $dateStr < $c['starts_on']) return false;
        if (!empty($c['ends_on'])   && $dateStr > $c['ends_on'])   return false;
        return true;
    }));
?>
    <section class="day <?= $isPast ? 'is-past' : '' ?> <?= $isToday ? 'is-today' : '' ?>">
        <header class="day-head">
            <div class="day-name"><?= e(t('day.' . $d)) ?></div>
            <div class="day-date"><?= e($date->format('j. n.')) ?></div>
        </header>
        <div class="day-slots">
        <?php if (!$dayClasses): ?>
            <div class="day-empty"><?= e(t('rezervace.day.empty')) ?></div>
        <?php else: foreach ($dayClasses as $c):
            $key      = $c['id'] . '|' . $dateStr;
            $taken    = $counts[$key] ?? 0;
            $capacity = (int)$c['capacity'];
            $left     = max(0, $capacity - $taken);
            $booked   = isset($mine[$key]);
            $cat      = ny_category((string)$c['name']);
        ?>
            <?php
                $roster        = $rosters[$key] ?? [];
                $rosterTitle   = $c['name'] . ' · ' . $date->format('j. n.') . ' · ' . substr($c['start_time'], 0, 5);
                $canShowRoster = $user && $taken > 0;
            ?>
            <article class="class-card <?= $left === 0 ? 'is-full' : '' ?> <?= $canShowRoster ? 'has-roster' : '' ?>"
                     data-cat="<?= e($cat) ?>"
                     <?php if ($canShowRoster): ?>
                     data-roster='<?= e(json_encode($roster, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>'
                     data-title="<?= e($rosterTitle) ?>"
                     <?php endif; ?>>
                <div class="time"><?= e(substr($c['start_time'], 0, 5)) ?> – <?= e(substr($c['end_time'], 0, 5)) ?></div>
                <div class="title"><?= e($c['name']) ?></div>
                <div class="meta">
                    <?= e($c['teacher']) ?><?php if ($c['room']): ?> · <?= e($c['room']) ?><?php endif; ?>
                </div>
                <?php
                    $classPrice  = trim((string)($c['price'] ?? ''));
                    $price       = $classPrice !== '' ? $classPrice : ny_class_price((string)$c['name']);
                    $priceAmount = $price !== null ? ny_price_amount($price) : null;
                    $showQr      = $iban !== '' && $priceAmount !== null && $priceAmount > 0;
                ?>
                <?php if ($price !== null): ?>
                    <div class="class-price">
                        <span><?= e($price) ?></span>
                        <?php if ($showQr): ?>
                            <button type="button" class="class-qr-btn"
                                    data-qr-amount="<?= (int)$priceAmount ?>"
                                    data-qr-msg="<?= e(ny_ascii_msg('Lekce ' . $c['name'] . ($userSurname !== '' ? ' ' . $userSurname : ''))) ?>"
                                    data-qr-title="<?= e($c['name']) ?>"
                                    data-qr-date="<?= e($date->format('j. n.') . ' ' . substr($c['start_time'], 0, 5)) ?>"
                                    aria-label="<?= e(t('rezervace.qr.open')) ?>">
                                <?= ny_icon('qr-code', 14) ?>
                                <span><?= e(t('rezervace.qr.btn')) ?></span>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="row">
                    <?php if ($booked): ?>
                        <span class="badge badge-success"><span class="dot"></span><?= e(t('rezervace.badge.booked')) ?></span>
                    <?php elseif ($left === 0): ?>
                        <span class="badge badge-danger"><?= e(t('rezervace.badge.full')) ?></span>
                    <?php else: ?>
                        <span class="badge"><?= e(t('rezervace.badge.free')) ?>: <?= $left ?> / <?= $capacity ?></span>
                    <?php endif; ?>

                    <?php if ($canShowRoster): ?>
                        <button type="button" class="roster-link" aria-haspopup="dialog">
                            <?= ny_icon('user', 12) ?> <?= e(t('rezervace.roster.who')) ?> (<?= $taken ?>)
                        </button>
                    <?php endif; ?>

                    <?php if ($isPast): ?>
                        <span class="hint"><?= e(t('rezervace.past')) ?></span>
                    <?php elseif ($booked): ?>
                        <form method="post" action="cancel.php" class="inline">
                            <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
                            <input type="hidden" name="class_id" value="<?= (int)$c['id'] ?>">
                            <input type="hidden" name="class_date" value="<?= e($dateStr) ?>">
                            <button class="btn btn-ghost btn-sm" type="submit"><?= e(t('rezervace.btn.cancel')) ?></button>
                        </form>
                    <?php elseif ($user && $left > 0): ?>
                        <form method="post" action="reserve.php" class="inline">
                            <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
                            <input type="hidden" name="class_id" value="<?= (int)$c['id'] ?>">
                            <input type="hidden" name="class_date" value="<?= e($dateStr) ?>">
                            <button class="btn btn-primary btn-sm" type="submit"><?= e(t('rezervace.btn.reserve')) ?></button>
                        </form>
                        <?php if ($priceAmount !== null && $priceAmount > 0 && $userBalance >= $priceAmount): ?>
                        <form method="post" action="reserve.php" class="inline"
                              onsubmit="return confirm('<?= e(sprintf(t('rezervace.credits.confirm'), $priceAmount, $c['name'])) ?>');">
                            <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
                            <input type="hidden" name="class_id" value="<?= (int)$c['id'] ?>">
                            <input type="hidden" name="class_date" value="<?= e($dateStr) ?>">
                            <input type="hidden" name="pay" value="credits">
                            <button class="btn btn-sand btn-sm" type="submit" title="<?= e(sprintf(t('rezervace.credits.title'), $userBalance)) ?>">
                                <?= sprintf(e(t('rezervace.credits.btn')), (int)$priceAmount) ?>
                            </button>
                        </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php if (!$user && !$isPast): ?>
                    <a class="btn btn-secondary btn-sm class-card-login" href="login.php?class_date=<?= e($dateStr) ?>&amp;class_id=<?= (int)$c['id'] ?>"><?= e(t('rezervace.btn.login')) ?></a>
                <?php endif; ?>
            </article>
        <?php endforeach; endif; ?>
        </div>
    </section>
<?php endfor; ?>
</div>

<p class="schedule-price-hint hint">
    <?= t('rezervace.price.hint') ?>
</p>

<section class="room-equipment">
    <div class="room-equipment-eyebrow"><?= e(t('rezervace.equipment.eyebrow')) ?></div>
    <p><?= e(t('rezervace.equipment.text')) ?></p>
</section>

<?php if ($user): ?>
<div id="roster-modal" class="roster-modal" role="dialog" aria-modal="true" aria-labelledby="roster-title" hidden>
    <div class="roster-modal-backdrop" data-roster-close></div>
    <div class="roster-modal-inner" role="document">
        <header class="roster-modal-head">
            <h3 id="roster-title" class="roster-modal-title"><?= e(t('rezervace.roster.title')) ?></h3>
            <button type="button" class="roster-modal-close" aria-label="<?= e(t('rezervace.roster.close')) ?>" data-roster-close>×</button>
        </header>
        <ul id="roster-list" class="roster-list"></ul>
        <p id="roster-empty" class="roster-empty hint" hidden><?= e(t('rezervace.roster.empty')) ?></p>
    </div>
</div>
<script>
(function () {
    var modal   = document.getElementById('roster-modal');
    var listEl  = document.getElementById('roster-list');
    var titleEl = document.getElementById('roster-title');
    var emptyEl = document.getElementById('roster-empty');
    if (!modal || !listEl) return;
    var defaultTitle = <?= json_encode(t('rezervace.roster.title')) ?>;

    function openModal(title, names) {
        titleEl.textContent = title || defaultTitle;
        listEl.innerHTML = '';
        if (!names || !names.length) {
            emptyEl.hidden = false;
        } else {
            emptyEl.hidden = true;
            names.forEach(function (n) {
                var li = document.createElement('li');
                li.className = 'roster-item';
                li.textContent = n;
                listEl.appendChild(li);
            });
        }
        modal.hidden = false;
        document.body.classList.add('has-roster-open');
    }
    function closeModal() {
        modal.hidden = true;
        document.body.classList.remove('has-roster-open');
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-roster-close]')) { closeModal(); return; }

        // Direct trigger button always opens.
        var btn = e.target.closest('.roster-link');
        var card;
        if (btn) {
            card = btn.closest('.class-card');
        } else {
            // Whole card is clickable when it carries a roster — except when the
            // click landed on a form control or link (reserve, cancel, login).
            card = e.target.closest('.class-card.has-roster');
            if (!card) return;
            if (e.target.closest('button, a, input, form')) return;
        }
        if (!card) return;
        e.preventDefault();
        var names = [];
        try { names = JSON.parse(card.getAttribute('data-roster') || '[]'); }
        catch (err) { names = []; }
        openModal(card.getAttribute('data-title'), names);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) closeModal();
    });
})();
</script>
<?php endif; ?>

<?php if ($iban !== ''): ?>
<div id="qr-modal" class="qr-modal" role="dialog" aria-modal="true" aria-labelledby="qr-modal-title" hidden>
    <div class="qr-modal-backdrop" data-qr-close></div>
    <div class="qr-modal-inner" role="document">
        <header class="qr-modal-head">
            <div>
                <div class="eyebrow"><?= e(t('rezervace.qr.eyebrow')) ?></div>
                <h3 id="qr-modal-title" class="qr-modal-title"></h3>
                <p class="qr-modal-sub" id="qr-modal-sub"></p>
            </div>
            <button type="button" class="qr-modal-close" aria-label="<?= e(t('rezervace.qr.close')) ?>" data-qr-close>×</button>
        </header>
        <div class="qr-modal-box" id="qr-modal-box"></div>
        <dl class="qr-modal-bank">
            <dt><?= e(t('rezervace.qr.amount')) ?></dt>
            <dd class="mono" id="qr-modal-amount"></dd>
            <dt><?= e(t('rezervace.qr.iban')) ?></dt>
            <dd class="mono"><?= e($iban) ?></dd>
            <dt><?= e(t('rezervace.qr.msg')) ?></dt>
            <dd class="mono" id="qr-modal-msg"></dd>
        </dl>
        <p class="qr-modal-hint hint"><?= e(t('rezervace.qr.hint')) ?></p>
        <div class="qr-modal-actions">
            <button type="button" class="btn btn-secondary" id="qr-modal-download">
                <?= ny_icon('download', 14) ?>
                <span><?= e(t('rezervace.qr.download')) ?></span>
            </button>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js" defer></script>
<script>
(function () {
    var modal    = document.getElementById('qr-modal');
    var box      = document.getElementById('qr-modal-box');
    var titleEl  = document.getElementById('qr-modal-title');
    var subEl    = document.getElementById('qr-modal-sub');
    var amountEl = document.getElementById('qr-modal-amount');
    var msgEl    = document.getElementById('qr-modal-msg');
    var dlBtn    = document.getElementById('qr-modal-download');
    if (!modal || !box) return;

    var currentFileBase = 'qr-platba';
    function slugify(s) {
        return (s || '').toString()
            .normalize('NFD').replace(/[̀-ͯ]/g, '')
            .replace(/[^a-zA-Z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .toLowerCase() || 'qr-platba';
    }

    var iban = <?= json_encode(preg_replace('/\s+/', '', strtoupper($iban))) ?>;

    function buildSpayd(amount, msg) {
        var parts = ['SPD*1.0*ACC:' + iban];
        if (amount > 0) parts.push('AM:' + amount.toFixed(2));
        parts.push('CC:CZK');
        if (msg) parts.push('MSG:' + msg.substring(0, 60));
        return parts.join('*');
    }

    function qrSize() {
        var vw = Math.min(window.innerWidth, document.documentElement.clientWidth);
        var vh = window.innerHeight || document.documentElement.clientHeight;
        // modal padding + inner padding + box padding roughly reserves ~80px on small, ~96px on large
        var reserve = vw <= 560 ? 60 : 96;
        var max = vw <= 560 ? 320 : 260;
        var min = 180;
        var side = Math.min(vw - reserve, vh - 260, max);
        return Math.max(min, Math.round(side));
    }

    // Load qrcodejs on demand with a timeout. Also keeps us resilient when the
    // CDN is slow — render() can await the load before painting the box.
    var qrLoadState = 'idle'; // idle | loading | ready | error
    var qrLoadCallbacks = [];
    var QR_SRCS = [
        'https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js',
        'https://unpkg.com/qrcodejs@1.0.0/qrcode.min.js'
    ];
    function loadQr(cb) {
        if (typeof QRCode !== 'undefined') { qrLoadState = 'ready'; cb(null); return; }
        qrLoadCallbacks.push(cb);
        if (qrLoadState === 'loading' || qrLoadState === 'ready') return;
        qrLoadState = 'loading';
        var i = 0;
        function tryNext() {
            if (i >= QR_SRCS.length) {
                qrLoadState = 'error';
                qrLoadCallbacks.splice(0).forEach(function (c) { c(new Error('load failed')); });
                return;
            }
            var s = document.createElement('script');
            s.src = QR_SRCS[i++];
            s.async = true;
            s.onload = function () {
                if (typeof QRCode === 'undefined') { tryNext(); return; }
                qrLoadState = 'ready';
                qrLoadCallbacks.splice(0).forEach(function (c) { c(null); });
            };
            s.onerror = function () { tryNext(); };
            document.head.appendChild(s);
        }
        tryNext();
    }

    function paintQr(amount, msg) {
        box.innerHTML = '';
        var side = qrSize();
        new QRCode(box, {
            text: buildSpayd(amount, msg),
            width: side,
            height: side,
            correctLevel: QRCode.CorrectLevel.M
        });
    }

    function render(amount, msg) {
        box.innerHTML = '';
        box.textContent = '…';
        loadQr(function (err) {
            if (!lastRender || lastRender.amount !== amount || lastRender.msg !== msg) return;
            if (err || typeof QRCode === 'undefined') {
                box.textContent = <?= json_encode(t('rezervace.qr.err')) ?>;
                return;
            }
            paintQr(amount, msg);
        });
    }

    var lastRender = null;
    var resizeTimer;
    window.addEventListener('resize', function () {
        if (modal.hidden || !lastRender) return;
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            render(lastRender.amount, lastRender.msg);
        }, 150);
    });

    function openModal(btn) {
        var amount = parseInt(btn.getAttribute('data-qr-amount') || '0', 10) || 0;
        var msg    = btn.getAttribute('data-qr-msg')  || '';
        var title  = btn.getAttribute('data-qr-title') || '';
        var when   = btn.getAttribute('data-qr-date')  || '';
        titleEl.textContent  = title;
        subEl.textContent    = when;
        amountEl.textContent = amount > 0 ? amount.toLocaleString('cs-CZ') + ' Kč' : '—';
        msgEl.textContent    = msg;
        currentFileBase = 'qr-' + slugify(title + (when ? '-' + when : ''));
        lastRender = { amount: amount, msg: msg };
        render(amount, msg);
        modal.hidden = false;
        document.body.classList.add('has-qr-open');
    }

    function qrDataUrl() {
        var canvas = box.querySelector('canvas');
        if (canvas) {
            try { return canvas.toDataURL('image/png'); } catch (e) {}
        }
        var img = box.querySelector('img');
        return img ? img.src : null;
    }
    if (dlBtn) {
        dlBtn.addEventListener('click', function () {
            var url = qrDataUrl();
            if (!url) return;
            var a = document.createElement('a');
            a.href = url;
            a.download = currentFileBase + '.png';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        });
    }
    function closeModal() {
        modal.hidden = true;
        box.innerHTML = '';
        lastRender = null;
        document.body.classList.remove('has-qr-open');
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-qr-close]')) { closeModal(); return; }
        var btn = e.target.closest('.class-qr-btn');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        openModal(btn);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) closeModal();
    });
})();
</script>
<?php endif; ?>

<?php ny_render_footer();
