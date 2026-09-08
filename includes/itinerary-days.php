<?php
/**
 * Structured itinerary-days renderer for the full-width Tour Details section.
 *
 * Expects: $itineraryDays = array of rows from the itinerary_days table,
 * already ordered by sort_order (see getItineraryDays()). Each row may have:
 *   day_number, title, description, drive_time, meals, accommodation,
 *   location_name, lat, lng, image_path, image_alt
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

<?php
// Build the array of itinerary days that have map coordinates for the Leaflet
// OpenStreetMap route map shown at the top of the day-by-day section.
$mapDays = [];
foreach ($itineraryDays as $i => $day) {
    $lat = $day['lat'] ?? null;
    $lng = $day['lng'] ?? null;
    if ($lat === null || $lng === null || $lat === '' || $lng === '') continue;
    $dayNum  = isset($day['day_number']) ? intval($day['day_number']) : ($i + 1);
    $mapDays[] = [
        'day'           => $dayNum,
        'title'         => itineraryHumanizeTitle($day['title'] ?? ''),
        'location_name' => trim($day['location_name'] ?? ''),
        'lat'           => (float)$lat,
        'lng'           => (float)$lng,
    ];
}
$mapJson = htmlspecialchars(json_encode($mapDays), ENT_QUOTES, 'UTF-8');
?>

<?php if (!empty($mapDays)): ?>
<!-- Day-by-day route map (OpenStreetMap / Leaflet) -->
<div class="itinerary-map-wrap">
    <div class="itinerary-map__toolbar">
        <div class="itinerary-map__legend">
            <span class="itinerary-map__legend-item"><i class="fas fa-map-marker-alt itinerary-map__legend-icon" aria-hidden="true"></i> Day stops</span>
        </div>
        <div class="itinerary-map__search">
            <input type="text" id="itinerary-map-search" class="itinerary-map__search-input" placeholder="Search a place to explore…" autocomplete="off">
            <button type="button" id="itinerary-map-search-btn" class="itinerary-map__search-btn" aria-label="Search">
                <i class="fas fa-search" aria-hidden="true"></i>
            </button>
        </div>
    </div>
    <div id="itinerary-route-map" class="itinerary-route-map" data-route='<?php echo $mapJson; ?>'></div>
    <p class="itinerary-map__hint">
        <i class="fas fa-hand-pointer" aria-hidden="true"></i> Click a marker to jump to that day. Scroll inside the map to zoom.
    </p>
</div>
<?php endif; ?>

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
    <article class="itinerary-day<?php echo $hasImage ? '' : ' itinerary-day--no-image'; ?>" id="itinerary-day-<?php echo $rowKey; ?>" data-day-num="<?php echo $dayNumber; ?>">
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

<style>
/* ── Day-by-day route map (OpenStreetMap / Leaflet) ─────────────────── */
.itinerary-map-wrap {
    margin-bottom: 2.5rem;
}
.itinerary-map__toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    align-items: center;
    justify-content: space-between;
    background: #fff;
    border: 1px solid #e8e3da;
    border-bottom: none;
    padding: 0.85rem 1rem;
    border-radius: 12px 12px 0 0;
}
.itinerary-map__legend {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    color: #5b5548;
    font-size: 0.9rem;
    font-weight: 600;
}
.itinerary-map__legend-icon {
    color: var(--gold, #c9a227);
}
.itinerary-map__search {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex: 1;
    max-width: 340px;
}
.itinerary-map__search-input {
    flex: 1;
    border: 1px solid #ddd5c6;
    background: #fbf9f4;
    border-radius: 8px;
    padding: 0.5rem 0.75rem;
    font-size: 0.9rem;
    color: #2c2a26;
    outline: none;
    transition: border-color 0.2s;
}
.itinerary-map__search-input:focus {
    border-color: var(--gold, #c9a227);
}
.itinerary-map__search-btn {
    border: none;
    background: var(--primary, #0a2540);
    color: #fff;
    border-radius: 8px;
    width: 38px;
    height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: opacity 0.2s;
}
.itinerary-map__search-btn:hover { opacity: 0.85; }
.itinerary-route-map {
    width: 100%;
    height: 420px;
    border-radius: 0 0 12px 12px;
    border: 1px solid #e8e3da;
    z-index: 0;
    position: relative;
    background: #e4e0d6;
}
.itinerary-map__hint {
    margin: 0.6rem 0 0;
    font-size: 0.82rem;
    color: #8a8374;
}
.itinerary-route-map .leaflet-container { font-family: inherit; }
.itinerary-route-map .leaflet-control-attribution { font-size: 10px; }
.itinerary-map__popup-title { font-weight: 700; color: #0a2540; }
.itinerary-map__popup-day { font-size: 0.8rem; color: #c9a227; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }
.itinerary-map__popup-loc { font-size: 0.85rem; color: #6b6455; font-style: italic; }
</style>

<script>
// ── Day-by-day route map (OpenStreetMap via Leaflet) ──────────────────
(function () {
    var wrap = document.querySelector('.itinerary-map-wrap');
    var el = document.getElementById('itinerary-route-map');
    if (!wrap || !el) return;

    var days;
    try { days = JSON.parse(el.getAttribute('data-route') || '[]'); }
    catch (e) { return; }
    if (!days || !days.length) return;

    function loadLeaflet(cb) {
        if (typeof L !== 'undefined') { cb(); return; }
        var css = document.createElement('link');
        css.rel = 'stylesheet';
        css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        document.head.appendChild(css);
        var js = document.createElement('script');
        js.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        js.onload = cb;
        js.onerror = function () { wrap.classList.add('itinerary-map-wrap--failed'); };
        document.head.appendChild(js);
    }

    loadLeaflet(function () {
        if (typeof L === 'undefined') return;
        var map = L.map(el, { scrollWheelZoom: false }).setView([-6.3690, 34.8888], 6);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        // Let AOS / layout settle, then recompute tile bounds so the map fills
        // its container even when it initialised before/while the fade-in ran.
        setTimeout(function () { map.invalidateSize(); }, 350);

        var bounds = L.latLngBounds();
        var markers = [];
        function escTxt(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }
        days.forEach(function (d) {
            var latlng = L.latLng(d.lat, d.lng);
            bounds.extend(latlng);
            var popup = '<div class="itinerary-map__popup-day">Day ' + d.day + '</div>'
                + '<div class="itinerary-map__popup-title">' + escTxt(d.title || ('Day ' + d.day)) + '</div>'
                + (d.location_name ? '<div class="itinerary-map__popup-loc">' + escTxt(d.location_name) + '</div>' : '');
            var marker = L.marker(latlng).addTo(map)
                .bindPopup(popup);
            marker._tdDay = d.day;
            markers.push(marker);
        });

        if (days.length === 1) {
            map.setView(bounds.getCenter(), 12);
        } else {
            map.fitBounds(bounds, { padding: [40, 40] });
        }

        if (markers.length >= 2) {
            var coords = markers.map(function (m) { return m.getLatLng(); });
            L.polyline(coords, { color: '#c13d31', weight: 3, opacity: 0.8, dashArray: '8 6' }).addTo(map);
        }

        // Clicking a marker scrolls to & opens that itinerary day.
        markers.forEach(function (m) {
            m.on('click', function () {
                var target = document.querySelector('.itinerary-day[data-day-num="' + m._tdDay + '"]');
                if (!target) {
                    // Fallback: match by id/order.
                    var all = document.querySelectorAll('.tour-itinerary-section .itinerary-day');
                    var idx = m._tdDay - 1;
                    if (all[idx]) target = all[idx];
                }
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    target.style.outline = '2px solid var(--gold, #c9a227)';
                    setTimeout(function () { target.style.outline = ''; }, 1800);
                }
            });
        });

        // Location search via OpenStreetMap Nominatim + FlyTo.
        var searchInput = document.getElementById('itinerary-map-search');
        var searchBtn = document.getElementById('itinerary-map-search-btn');
        function doSearch() {
            var q = (searchInput.value || '').trim();
            if (!q) return;
            var url = 'https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(q);
            (fetch ? fetch(url, { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }) : Promise.resolve([]))
                .then(function (results) {
                    if (!results || !results.length) {
                        // Lightweight no-results feedback.
                        searchInput.style.borderColor = '#c13d31';
                        setTimeout(function () { searchInput.style.borderColor = ''; }, 1500);
                        return;
                    }
                    var r = results[0];
                    map.flyTo([parseFloat(r.lat), parseFloat(r.lon)], Math.max(map.getZoom(), 12), { duration: 0.8 });
                    if (r.display_name) {
                        var s = document.createElement('div');
                        s.textContent = r.display_name;
                        L.popup()
                            .setLatLng([parseFloat(r.lat), parseFloat(r.lon)])
                            .setContent('<div class="itinerary-map__popup-title">' + s.innerHTML + '</div>')
                            .openOn(map);
                    }
                })
                ['catch'](function () {});
        }
        if (searchBtn) searchBtn.addEventListener('click', doSearch);
        if (searchInput) searchInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); doSearch(); } });
    });
})();
</script>

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