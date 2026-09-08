<?php
/**
 * Redesigned Tour Overview (collage + content) for tour-details.php.
 *
 * Expects:
 *   $tour           tour_packages row (with destination_name/destination_country
 *                   joined from destinations) containing image, gallery,
 *                   description, highlights.
 *   $highlightsArr  normalized highlight items (see tourListItems()).
 *
 * Renders a two-column "premium" overview: layered image collage on the left
 * (~45%) and eyebrow + heading + description + highlights + Read More on the
 * right (~55%). The collage resolves images dynamically from the tour's
 * featured image and its gallery/media collection — nothing is hard-coded —
 * and gracefully degrades to 2, 1 or 0 images. Long descriptions get a
 * client-side "Read full overview / Show less" control (same reusable pattern
 * as the itinerary days: collapsible max-height, subtle fade, aria state,
 * keyboard friendly, recalculated on load/resize/font-load).
 *
 * All CSS/JS here is scoped with the .tour-overview-* prefix so it never
 * affects other headings, images, buttons, cards or sections on the site.
 */
if (empty($tour)) {
    return;
}

$overviewTitle = $tour['title'] ?? '';
$overviewDesc  = trim($tour['description'] ?? '');

/* ── Destination for the "DISCOVER {DESTINATION}" eyebrow ── */
$overviewDestination = trim($tour['destination_name'] ?? '');
if ($overviewDestination === '') {
    $overviewDestination = trim($tour['destination_country'] ?? '');
}

/* ── Resolve collage images ─────────────────────────────────────────
   Priority 1: the three explicitly saved Overview images (overview_image_1/2/3,
   set in Admin > Tours > that tour > Tour Overview Images). Slots left empty are
   skipped, so the collage renders with the images the admin chose.
   Priority 2 (only when ALL three are empty, e.g. older tours): fall back to the
   featured image first, then gallery images (only existing files, featured never
   repeated). The first URL becomes the large "main" image; the next two become
   the medium + small overlapping images. If nothing exists, the site's tour
   placeholder is used.                                                */
$placeholder = 'assets/images/placeholder.svg';
$placeholderUrl = file_exists(BASE_PATH . $placeholder) ? SITE_URL . '/' . $placeholder : $placeholder;

$rawCandidates = [];
$overviewImagesSet = false;
foreach ([1, 2, 3] as $oi) {
    $ovPath = trim($tour['overview_image_' . $oi] ?? '');
    if ($ovPath !== '') {
        $overviewImagesSet = true;
        $rawCandidates[] = $ovPath;
    }
}
if (!$overviewImagesSet) {
    $mainImgRaw = trim($tour['image'] ?? '');
    if ($mainImgRaw !== '' && file_exists(BASE_PATH . $mainImgRaw)) {
        $rawCandidates[] = $mainImgRaw;
    }
    foreach (array_filter(array_map('trim', explode(',', $tour['gallery'] ?? ''))) as $gi) {
        if ($gi === '') continue;
        $rawCandidates[] = $gi;
    }
}
$collageUrls = [];
$seenUrls = [];
foreach ($rawCandidates as $rc) {
    $clean = ltrim($rc, '/');
    if (!file_exists(BASE_PATH . $clean)) continue;
    $url = SITE_URL . '/' . $clean;
    if (isset($seenUrls[$url])) continue;
    $seenUrls[$url] = true;
    $collageUrls[] = $url;
    if (count($collageUrls) >= 3) break;
}
if (empty($collageUrls)) {
    $collageUrls[] = $placeholderUrl;
}

$collageImages = [];
foreach ($collageUrls as $ci => $url) {
    $collageImages[] = [
        'src' => $url,
        'alt' => str_replace(
            '{title}',
            $overviewTitle,
            ($ci === 0 ? __('tour_overview_img_main_alt') : __('tour_overview_img_overlap_alt'))
        ),
        'role' => ($ci === 0) ? 'main' : (($ci === 1) ? 'mid' : 'small'),
    ];
}
$collageCount = count($collageImages);

/* ── Max three highlights in this overview row ── */
$overviewHighlights = array_slice((array)$highlightsArr, 0, 3);

/* ── Escaped / translated labels ── */
$eyebrowPrefix = __('tour_overview_eyebrow');
$eyebrowText = $overviewDestination !== ''
    ? $eyebrowPrefix . ' ' . $overviewDestination
    : __('tour_overview_eyebrow_fallback');

$overviewId = 'tour-overview';
$overviewDescId = 'tour-overview-description';
$overviewToggleId = 'tour-overview-toggle';
?>
<div class="tour-overview" id="<?php echo $overviewId; ?>" data-aos="fade-up">
    <div class="tour-overview__media tour-overview__media--count-<?php echo $collageCount; ?>">
        <div class="tour-overview__media-stage">
            <?php foreach ($collageImages as $ci):
                $imgClass = 'tour-overview__img tour-overview__img--'
                    . ($ci['role'] === 'main' ? 'main' : ($ci['role'] === 'mid' ? 'mid' : 'small'));
                $onError = ($ci['role'] === 'main')
                    ? "onerror=\"if(this.getAttribute('data-failed')!=='1'){this.setAttribute('data-failed','1');this.src='" . htmlspecialchars($placeholderUrl) . "';}\""
                    : "onerror=\"this.closest('.tour-overview__frame').style.display='none';\"";
            ?>
            <div class="tour-overview__frame <?php echo $imgClass; ?>">
                <img src="<?php echo htmlspecialchars($ci['src']); ?>" alt="<?php echo htmlspecialchars($ci['alt']); ?>" loading="lazy" <?php echo $onError; ?>>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="tour-overview__content">
        <p class="tour-overview__eyebrow"><span class="tour-overview__eyebrow-text"><?php echo htmlspecialchars($eyebrowText); ?></span></p>
        <h2 class="tour-overview__heading"><?php echo __('tour_details_overview'); ?></h2>

        <?php if ($overviewDesc !== ''): ?>
        <div class="tour-overview__desc" data-overview-desc>
            <div class="tour-overview__desc-inner" id="<?php echo $overviewDescId; ?>">
                <?php echo nl2br(htmlspecialchars($overviewDesc)); ?>
            </div>
        </div>
        <button type="button" class="tour-overview__toggle" id="<?php echo $overviewToggleId; ?>" hidden
            aria-expanded="false" aria-controls="<?php echo $overviewDescId; ?>"
            data-label-more="<?php echo htmlspecialchars(__('tour_overview_read_full')); ?>"
            data-label-less="<?php echo htmlspecialchars(__('tour_overview_show_less')); ?>">
            <span class="tour-overview__toggle-label"><?php echo htmlspecialchars(__('tour_overview_read_full')); ?></span>
            <span class="tour-overview__toggle-arrow" aria-hidden="true">→</span>
        </button>
        <?php endif; ?>

        <?php if (!empty($overviewHighlights)): ?>
        <ul class="tour-overview__highlights">
            <?php foreach ($overviewHighlights as $hl): ?>
            <li class="tour-overview__highlight">
                <span class="tour-overview__highlight-icon" aria-hidden="true"><i class="fas fa-check"></i></span>
                <span class="tour-overview__highlight-text"><?php echo htmlspecialchars($hl); ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <div class="tour-overview__divider" aria-hidden="true"></div>
        <?php endif; ?>
    </div>
</div>

<style>
/* ── Tour Overview (scoped) ───────────────────────────────────────
   Two columns across the FULL container: image collage left (≈46%),
   content right (≈54%), 70–90px gap on desktop, columns top-aligned so
   the collage sits level with the eyebrow and never moves when the long
   overview text expands below it.                            */
.tour-overview {
    display: grid;
    grid-template-columns: 46fr 54fr;
    gap: clamp(70px, 6vw, 90px);
    align-items: start;
}
.tour-overview__media,
.tour-overview__content {
    min-width: 0;
}

/* Collage */
.tour-overview__media-stage {
    position: relative;
    width: 100%;
    aspect-ratio: 4 / 5;
    margin: 0;
}
.tour-overview__frame {
    position: absolute;
    border-radius: 28px;
    border: 6px solid #fff;
    box-shadow: 0 14px 40px rgba(10, 37, 64, 0.14);
    overflow: hidden;
    background: var(--off-white);
}
.tour-overview__frame img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
/* 3-image collage: large main (≈4:5) + landscape overlaps */
.tour-overview__img--main {
    top: 0;
    left: 0;
    width: 78%;
    height: 78%;
    z-index: 1;
}
.tour-overview__img--mid {
    top: 24%;
    right: 0;
    width: 54%;
    height: 34%;
    z-index: 2;
}
.tour-overview__img--small {
    bottom: 0;
    left: 2%;
    width: 50%;
    height: 32%;
    z-index: 3;
}

/* 2-image balanced layout */
.tour-overview__media--count-2 .tour-overview__img--main {
    width: 80%;
    height: 90%;
}
.tour-overview__media--count-2 .tour-overview__img--mid {
    top: 16%;
    right: 0;
    width: 50%;
    height: 38%;
}
.tour-overview__media--count-2 .tour-overview__img--small {
    bottom: 0;
    left: auto;
    right: 2%;
    width: 50%;
    height: 38%;
}

/* 1-image layout: large portrait filling the whole left column */
.tour-overview__media--count-1 .tour-overview__img--main {
    width: 100%;
    height: 100%;
}

/* Content */
.tour-overview__eyebrow {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.85rem;
    margin: 0 0 0.75rem;
    font-family: var(--font-secondary);
    font-size: 0.85rem;
    font-weight: 600;
    letter-spacing: 0.3em;
    text-transform: uppercase;
    color: var(--secondary);
}
.tour-overview__eyebrow::after {
    content: '';
    width: 42px;
    height: 2px;
    background: linear-gradient(90deg, var(--secondary), rgba(212, 175, 55, 0));
    border-radius: 2px;
}
.tour-overview__heading {
    font-family: var(--font-primary);
    font-size: clamp(3.375rem, 4.25vw, 4rem);
    line-height: 1.12;
    color: var(--primary);
    margin: 0 0 1.5rem;
}

/* Description + Read More */
.tour-overview__desc {
    position: relative;
    color: var(--text-light);
    font-size: clamp(1.05rem, 1.35vw, 1.125rem);
    line-height: 1.78;
    overflow: hidden;
    /* Collapsed cap (desktop) — JS adjusts to a whole-line height and hides
       the cap entirely when content fits. */
    max-height: 300px;
    transition: max-height 0.38s ease;
}
.tour-overview__desc::after {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    height: 3.5rem;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.92));
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.3s ease;
}
.tour-overview__desc.is-truncated::after {
    opacity: 1;
}
.tour-overview__toggle {
    margin-top: 0.5rem;
    padding: 0.25rem 0 0.5rem;
    border: 0;
    background: transparent;
    font-family: var(--font-body);
    font-size: 1rem;
    font-weight: 600;
    color: var(--secondary);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    transition: color 0.2s ease;
}
.tour-overview__toggle[hidden] {
    display: none !important;
}
.tour-overview__toggle:hover,
.tour-overview__toggle:focus-visible {
    color: var(--accent);
    outline: none;
}
.tour-overview__toggle-arrow {
    font-size: 1.05rem;
    line-height: 1;
    transition: transform 0.3s ease;
}
.tour-overview__toggle[aria-expanded="true"] .tour-overview__toggle-arrow {
    transform: rotate(180deg);
}

/* Highlights row: horizontal on desktop, wraps gracefully */
.tour-overview__highlights {
    list-style: none;
    margin: 1.75rem 0 0;
    padding: 0;
    display: flex;
    flex-wrap: wrap;
    gap: 1rem 2rem;
}
.tour-overview__highlight {
    display: flex;
    align-items: center;
    gap: 0.7rem;
}
.tour-overview__highlight-icon {
    flex: 0 0 auto;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--secondary);
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.78rem;
}
.tour-overview__highlight-text {
    color: var(--primary);
    font-family: var(--font-primary);
    font-size: 1.125rem;
    font-weight: 500;
    line-height: 1.4;
}
.tour-overview__divider {
    height: 2px;
    width: 100%;
    max-width: 320px;
    margin: 1.75rem 0 0;
    background: linear-gradient(90deg, rgba(212, 175, 55, 0.85), rgba(212, 175, 55, 0.12));
    border-radius: 2px;
}

/* Tablet: stack — collage first, content below, single column */
@media (max-width: 991.98px) {
    .tour-overview {
        grid-template-columns: 1fr;
        gap: 2.25rem;
    }
    .tour-overview__media-stage {
        margin: 0 auto;
    }
}
@media (prefers-reduced-motion: reduce) {
    .tour-overview__desc {
        transition: none;
    }
}
/* Mobile: full-width main image, scaled-down overlap, clear of WhatsApp */
@media (max-width: 767.98px) {
    .tour-overview {
        grid-template-columns: 1fr;
        gap: 1.75rem;
    }
    .tour-overview__media-stage {
        width: 100%;
        max-width: 100%;
        aspect-ratio: 4 / 5;
        margin: 0;
    }
    .tour-overview__frame {
        border-width: 4px;
        border-radius: 22px;
    }
    .tour-overview__heading {
        font-size: clamp(2.4rem, 8vw, 3.25rem);
    }
    .tour-overview__desc {
        max-height: 220px;
    }
    .tour-overview__highlight-text {
        font-size: 1.05rem;
    }
    .tour-overview__highlights {
        gap: 0.9rem 1.25rem;
    }
    /* Keep the Read More control + highlights clear of the floating
       WhatsApp circle while scrolling on small screens. */
    .tour-overview__content {
        padding-bottom: 2rem;
    }
}
</style>

<script>
// Tour Overview "Read full overview / Show less". Same reusable approach as
// the itinerary days: fully client-side collapsible with a subtle fade, real
// button + aria, no absolute positioning of text, overflow re-measured on
// load / fonts / resize via requestAnimationFrame. The control only appears
// when the stored text really exceeds the collapsed cap.
(function () {
    'use strict';

    var section = document.getElementById('tour-overview');
    var descEl = section.querySelector('.tour-overview__desc');
    var innerEl = descEl.querySelector('.tour-overview__desc-inner');
    var btn = document.getElementById('tour-overview-toggle');
    if (!section || !descEl || !innerEl || !btn) return;

    var REDUCED = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Collapsed cap comes from CSS (varies by breakpoint). Reset any inline
    // value first so it reads the current breakpoint rule, then floor it to a
    // whole number of line-heights so the last visible line is never sliced.
    function collapsedCap() {
        descEl.style.maxHeight = '';
        var css = getComputedStyle(descEl);
        var cap = parseFloat(css.maxHeight);
        if (!isFinite(cap) || cap <= 0) return null;
        var line = parseFloat(getComputedStyle(innerEl).lineHeight) || 26;
        if (line <= 0) return cap;
        return Math.max(line, Math.floor(cap / line) * line);
    }

    function applyCollapsed() {
        var cap = collapsedCap();
        if (cap === null) {
            descEl.style.maxHeight = '';
            descEl.classList.remove('is-truncated');
            return;
        }
        descEl.style.maxHeight = cap + 'px';
        var truncated = innerEl.scrollHeight > cap + 1;
        descEl.classList.toggle('is-truncated', truncated);
        btn.hidden = !truncated;
        btn.setAttribute('aria-expanded', 'false');
        btn.classList.remove('is-open');
    }

    function setLabel(open) {
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        btn.classList.toggle('is-open', open);
        var txt = btn.getAttribute(open ? 'data-label-less' : 'data-label-more');
        var label = btn.querySelector('.tour-overview__toggle-label');
        if (label && txt) label.textContent = txt;
    }

    function isOpen() {
        return btn.classList.contains('is-open');
    }

    function fullHeight() {
        return innerEl.scrollHeight;
    }

    function expand() {
        // Fade out the collapsed hint, then grow to the text's real height so
        // the max-height transition animates smoothly and nothing gets clipped.
        descEl.classList.remove('is-truncated');
        descEl.style.maxHeight = fullHeight() + 'px';
        setLabel(true);
    }

    function collapse() {
        var cap = collapsedCap();
        descEl.classList.add('is-truncated');
        descEl.style.maxHeight = cap ? cap + 'px' : '';
        setLabel(false);
        // If the user has scrolled below the overview heading, glide back to it.
        var heading = section.querySelector('.tour-overview__heading');
        var target = heading || section;
        var top = target.getBoundingClientRect().top + window.pageYOffset;
        if (window.pageYOffset > top - 80) {
            target.scrollIntoView({ behavior: REDUCED ? 'auto' : 'smooth', block: 'start' });
        }
    }

    btn.addEventListener('click', function () {
        if (isOpen()) {
            collapse();
        } else {
            expand();
        }
    });

    // Keep the open state's max-height in sync with the real text height when
    // the layout settles (images, fonts, resize). Closed states re-classify.
    var rafPending = false;
    function scheduleApply() {
        if (rafPending) return;
        rafPending = true;
        requestAnimationFrame(function () {
            rafPending = false;
            if (isOpen()) {
                descEl.style.maxHeight = fullHeight() + 'px';
            } else {
                applyCollapsed();
            }
        });
    }
    window.addEventListener('resize', scheduleApply);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(scheduleApply);
    window.addEventListener('load', scheduleApply);
    if (window.ResizeObserver) {
        new ResizeObserver(scheduleApply).observe(section);
    }

    applyCollapsed();
})();
</script>