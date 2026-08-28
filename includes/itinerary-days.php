<?php
/**
 * Structured itinerary-days renderer for the full-width Tour Details section.
 *
 * Expects: $itineraryDays = array of rows from the itinerary_days table,
 * already ordered by sort_order (see getItineraryDays()). Each row may have:
 *   day_number, title, description, drive_time, meals, accommodation,
 *   image_path, image_alt
 *
 * Renders one continuous, full-width editorial row per day: text (title,
 * description, metadata) on the left and a landscape image on the right.
 * Scoped under .tour-itinerary-section so it never affects other components.
 *
 * The displayed title is always built as "Day {number} — {destination}".
 * Any "Day N:" / "DAY N –" prefix already stored is stripped first, and a
 * stray trailing full stop is removed, so admins never need to type the prefix
 * or worry about punctuation. Natural capitalization entered by the admin is
 * preserved (no automatic upper/lower-case conversion).
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
    $dayNumber  = isset($day['day_number']) ? intval($day['day_number']) : ($i + 1);
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
?>
    <article class="itinerary-day<?php echo $hasImage ? '' : ' itinerary-day--no-image'; ?>" id="itinerary-day-<?php echo $dayNumber; ?>">
        <div class="itinerary-day__content">
            <?php if ($destination !== ''): ?>
            <h3 class="itinerary-day__title"><?php echo htmlspecialchars('Day ' . $dayNumber . ' — ' . $destination); ?></h3>
            <?php else: ?>
            <h3 class="itinerary-day__title"><?php echo htmlspecialchars('Day ' . $dayNumber); ?></h3>
            <?php endif; ?>

            <?php if ($dayDesc !== ''): ?>
            <div class="itinerary-day__description"><?php echo nl2br(htmlspecialchars($dayDesc)); ?></div>
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
        </div>

        <?php if ($hasImage): ?>
        <div class="itinerary-day__media">
            <img src="<?php echo htmlspecialchars($dayImg); ?>" alt="<?php echo htmlspecialchars($dayAlt); ?>" loading="lazy">
        </div>
        <?php endif; ?>
    </article>
<?php endforeach; ?>
