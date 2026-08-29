<?php
/**
 * Structured itinerary-days renderer for the full-width Tour Details section.
 *
 * Expects: $itineraryDays = array of rows from the itinerary_days table,
 * already ordered by sort_order (see getItineraryDays()). Each row may have:
 *   day_number, title, description, drive_time, meals, accommodation,
 *   image_path, image_alt
 *
 * Renders one two-column editorial card per day: text (title, description,
 * metadata) on the left and a cropped image on the right. Every card uses a
 * controlled collapsed height so the image never drives the row height and
 * portrait/oversized images are cropped with object-fit: cover. Long
 * descriptions get a per-day "Read More / Show Less" control that is created
 * entirely client-side: it is shown only when the text really overflows its
 * collapsed preview area (after render, font load, and resize).
 *
 * The toggle is a real <button> with aria-expanded/aria-controls, uses unique
 * IDs per day, expands the clicked card through normal document flow (pushing
 * the following days down), and never overlaps the next day or later sections.
 * Nothing is hard-coded: any number of days, any text length, any image shape,
 * and days without an image (which render as a clean full-width layout).
 */
if (empty($itineraryDays)) {
    return;
}

function itineraryHumanizeTitle($title) {
    $t = trim((string)$title);
    if ($t === '') return '';
    // Strip a leading "Day N:" / "DAY N –" prefix stored by legacy data.
    if (preg_match('/^Day\s+\d+\s*[-:–—]\s*/i', $t, $m)) {
        $t = trim(substr($t, strlen($m[0])));
    }
    // Remove a single trailing full stop (avoid mangling initials / acronyms).
    $t = preg_replace('/\.\s*$/', '', trim($t));

    // Only when the ENTIRE title is ALL-CAPS (shouting) do we convert it to
    // natural title case. This undoes the old uppercased DB values without ever
    // touching correctly mixed-case names or acronyms (left untouched).
    if ($t !== '' && mb_strtoupper($t, 'UTF-8') === $t && preg_match('/[A-Za-z]{3,}/', $t)) {
        $small = ['to', 'the', 'a', 'an', 'and', 'of', 'for', 'in', 'on', 'at', 'by', 'with', 'from', 'or', 'nor', 'but', '&'];
        $parts = preg_split('/([\s–—\-]+)/', mb_strtolower($t, 'UTF-8'), -1, PREG_SPLIT_DELIM_CAPTURE);
        $out = '';
        $first = true;
        foreach ($parts as $w) {
            if ($w === '') continue;
            if (preg_match('/^[\s–—\-]+$/', $w)) { $out .= $w; continue; }
            if (!$first && in_array($w, $small, true)) {
                $out .= $w;
            } else {
                $out .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($w, 1, null, 'UTF-8');
            }
            $first = false;
        }
        $t = $out;
    }
    return trim($t);
}
?>
<?php foreach ($itineraryDays as $i => $day):
    $rowKey     = $i + 1;
    $dayNumber  = isset($day['day_number']) ? intval($day['day_number']) : $rowKey;
    $destination = itineraryHumanizeTitle($day['title'] ?? '');
    $dayDesc    = trim($day['description'] ?? '');
    $drive      = trim($day['drive_time'] ?? '');
    $meals      = trim($day['meals'] ?? '');
    $accom      = trim($day['accommodation'] ?? '');

    $dayImg = '';
    if (!empty($day['image_path']) && file_exists(BASE_PATH . $day['image_path'])) {
        $dayImg = SITE_URL . '/' . $day['image_path'];
    }
    $dayAlt = !empty($day['image_alt']) ? trim($day['image_alt']) : (($destination !== '') ? $destination : 'Itinerary day');
    $hasImage = ($dayImg !== '');

    $hasText = ($destination !== '' || $dayDesc !== '' || $drive !== '' || $meals !== '' || $accom !== '');
    if (!$hasText && !$hasImage) {
        continue;
    }

    $dayPrefix  = __('itinerary_day_prefix');
    $descId = 'itinerary-day-' . $rowKey . '-description';
?>
    <article class="itinerary-day<?php echo $hasImage ? '' : ' itinerary-day--no-image'; ?>" id="itinerary-day-<?php echo $rowKey; ?>">
        <div class="itinerary-day__content">
            <?php if ($destination !== ''): ?>
            <h3 class="itinerary-day__title"><?php echo htmlspecialchars($dayPrefix . ' ' . $dayNumber . ' — ' . $destination); ?></h3>
            <?php else: ?>
            <h3 class="itinerary-day__title"><?php echo htmlspecialchars($dayPrefix . ' ' . $dayNumber); ?></h3>
            <?php endif; ?>

            <?php if ($dayDesc !== ''): ?>
            <div class="itinerary-day__description-wrap">
                <div class="itinerary-day__description" id="<?php echo $descId; ?>"><?php echo nl2br(htmlspecialchars($dayDesc)); ?></div>
            </div>
            <?php endif; ?>

            <?php if ($drive !== '' || $meals !== '' || $accom !== ''): ?>
            <div class="itinerary-day__meta">
                <?php if ($drive !== ''): ?>
                <span class="itinerary-day__meta-item">
                    <i class="fas fa-car itinerary-day__meta-icon" aria-hidden="true"></i>
                    <span><span class="itinerary-day__meta-label">Drive:</span> <?php echo htmlspecialchars($drive); ?></span>
                </span>
                <?php endif; ?>
                <?php if ($meals !== ''): ?>
                <span class="itinerary-day__meta-item">
                    <i class="fas fa-utensils itinerary-day__meta-icon" aria-hidden="true"></i>
                    <span><span class="itinerary-day__meta-label">Meals:</span> <?php echo htmlspecialchars($meals); ?></span>
                </span>
                <?php endif; ?>
                <?php if ($accom !== ''): ?>
                <span class="itinerary-day__meta-item">
                    <i class="fas fa-bed itinerary-day__meta-icon" aria-hidden="true"></i>
                    <span><span class="itinerary-day__meta-label">Accommodation:</span> <?php echo htmlspecialchars($accom); ?></span>
                </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($dayDesc !== ''): ?>
            <button type="button" class="itinerary-day__toggle" hidden aria-expanded="false" aria-controls="<?php echo $descId; ?>"
                data-label-more="<?php echo htmlspecialchars(__('read_more')); ?>"
                data-label-less="<?php echo htmlspecialchars(__('show_less')); ?>">
                <span class="itinerary-day__toggle-label"><?php echo htmlspecialchars(__('read_more')); ?></span>
                <i class="fas fa-chevron-down itinerary-day__toggle-icon" aria-hidden="true"></i>
            </button>
            <?php endif; ?>
        </div>

        <?php if ($hasImage): ?>
        <div class="itinerary-day__media">
            <img src="<?php echo htmlspecialchars($dayImg); ?>" alt="<?php echo htmlspecialchars($dayAlt); ?>" loading="lazy">
        </div>
        <?php endif; ?>
    </article>
<?php endforeach; ?>

<noscript>
    <style>
        /* No-JS fallback: never clip text we cannot expand, never show a dead
           toggle. Days simply render at their natural content height. */
        .tour-itinerary-section .itinerary-day { height: auto !important; min-height: 0 !important; }
        .tour-itinerary-section .itinerary-day__description-wrap { flex: none !important; max-height: none !important; overflow: visible !important; }
        .tour-itinerary-section .itinerary-day__description-wrap::after { display: none !important; }
        .tour-itinerary-section .itinerary-day__toggle { display: none !important; }
    </style>
</noscript>

<script>
// Itinerary day "Read More / Show Less". Fully client-side: the button stays
// hidden until the description actually overflows its collapsed preview area,
// re-checked on load, font load, resize, and content resize (debounced via
// requestAnimationFrame + ResizeObserver, no expensive resize loops).
//
// Each day is independent and driven by two marker classes:
//   itinerary-day--has-toggle : description overflows -> fixed compact height
//                               with a preview + visible Read More button.
//   itinerary-day--no-toggle  : everything fits -> the card hugs its content
//                               (auto height) so short text never leaves a big
//                               blank area, and the image still crops to match.
// Expanding swaps to `.is-expanded`: fixed height is replaced by natural
// content-driven height in normal flow, so later days are pushed down and the
// image column keeps filling with object-fit: cover. Height changes animate
// smoothly and never create horizontal overflow.
(function () {
    'use strict';

    var REDUCED = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function each(list, fn) {
        Array.prototype.forEach.call(list, fn);
    }

    function getDays() {
        return document.querySelectorAll('.tour-itinerary-section .itinerary-day');
    }

    function wrapOf(day) { return day.querySelector('.itinerary-day__description-wrap'); }
    function toggleOf(day) { return day.querySelector('.itinerary-day__toggle'); }

    // Force the pure collapsed geometry (fixed compact height, bounded preview
    // area, hidden toggle) so overflow can be measured. Called synchronously in
    // the same frame and instantly reverted, so it never paints or flickers.
    function measureOverflow(day) {
        var wrap = wrapOf(day);
        var desc = day.querySelector('.itinerary-day__description');
        if (!wrap || !desc) return false;
        day.classList.remove('itinerary-day--has-toggle');
        day.classList.remove('itinerary-day--no-toggle');
        void day.offsetHeight;
        return desc.scrollHeight > wrap.clientHeight + 1;
    }

    function setLabel(btn, collapsed) {
        if (!btn) return;
        btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        var label = btn.querySelector('.itinerary-day__toggle-label');
        var txt = btn.getAttribute(collapsed ? 'data-label-more' : 'data-label-less');
        if (label && txt) label.textContent = txt;
    }

    function evaluate(day) {
        // Open days keep their state; closed days re-classify cheaply.
        if (day.classList.contains('is-expanded')) return;
        var btn = toggleOf(day);
        var show = measureOverflow(day);
        day.classList.remove('itinerary-day--has-toggle');
        day.classList.remove('itinerary-day--no-toggle');
        day.classList.add(show ? 'itinerary-day--has-toggle' : 'itinerary-day--no-toggle');
        if (btn) {
            btn.hidden = !show;
            if (!show) setLabel(btn, true);
        }
    }

    function evaluateAll() {
        each(getDays(), evaluate);
    }

    var rafPending = false;
    function scheduleEvaluate() {
        if (rafPending) return;
        rafPending = true;
        requestAnimationFrame(function () {
            rafPending = false;
            evaluateAll();
        });
    }

    // Lightweight height animation between two explicit px endpoints, with a
    // safety timer in case transitionend does not fire. An .itinerary-day--
    // animating class keeps the description clipped to the box while it is
    // shrinking/growing so it never spills over the next day.
    function animateHeight(day, from, to) {
        var animating = 'itinerary-day--animating';
        day.style.transition = 'height 0.38s ease';
        day.classList.add(animating);
        day.style.height = from + 'px';
        void day.offsetHeight;
        day.style.height = to + 'px';

        function finish() {
            day.classList.remove(animating);
            day.style.transition = '';
            day.style.height = '';
            day.removeEventListener('transitionend', onEnd);
            clearTimeout(timer);
        }
        function onEnd(e) {
            if (e.target !== day || e.propertyName !== 'height') return;
            finish();
        }
        day.addEventListener('transitionend', onEnd);
        var timer = setTimeout(function () {
            if (day.classList.contains(animating)) finish();
        }, 550);
    }

    function toggleDay(day, btn) {
        if (day.classList.contains('itinerary-day--animating')) return;
        var expanding = btn.getAttribute('aria-expanded') !== 'true';

        if (REDUCED) {
            day.classList.toggle('is-expanded', expanding);
            setLabel(btn, !expanding);
            scheduleEvaluate();
            return;
        }

        var from = day.offsetHeight;

        if (!expanding) {
            // Collapse: read the collapsed target height without a visible
            // change, then animate from the current open height to it while
            // the expanded class stays on (inline height wins over auto).
            var savedClass = day.classList.contains('is-expanded');
            day.classList.remove('is-expanded');
            var collapsedH = day.offsetHeight;
            day.classList.add('is-expanded');
            void day.offsetHeight;
            day.classList.remove('is-expanded');
            animateHeight(day, from, collapsedH);
            setLabel(btn, true);
            return;
        }

        // Expand to natural height.
        day.classList.add('is-expanded');
        var to = day.offsetHeight;
        if (to <= from) {
            day.classList.remove('is-expanded');
            return;
        }
        animateHeight(day, from, to);
        setLabel(btn, false);
    }

    function wireDay(day) {
        var btn = toggleOf(day);
        if (btn && !btn.getAttribute('data-initialized')) {
            btn.setAttribute('data-initialized', '1');
            btn.addEventListener('click', function () { toggleDay(day, btn); });
        }
    }

    function init() {
        each(getDays(), wireDay);
        evaluateAll();

        // Re-check whenever a card is resized (includes collapsing/expanding)
        // and on window resize — both debounced through rAF.
        if (window.ResizeObserver) {
            var ro = new ResizeObserver(scheduleEvaluate);
            function observeDay(day) {
                if (!day.getAttribute('data-observed')) {
                    day.setAttribute('data-observed', '1');
                    ro.observe(day);
                }
            }
            each(getDays(), observeDay);

            // Cards injected into the section after load (AJAX etc.) get the
            // same wiring + observation automatically.
            var section = document.querySelector('.tour-itinerary-section');
            if (section && window.MutationObserver) {
                var mo = new MutationObserver(function () {
                    each(getDays(), function (day) { wireDay(day); observeDay(day); });
                    scheduleEvaluate();
                });
                mo.observe(section, { childList: true, subtree: true });
            }
        }
        window.addEventListener('resize', scheduleEvaluate);
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(scheduleEvaluate);
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', evaluateAll);
        }
        window.addEventListener('load', evaluateAll);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>