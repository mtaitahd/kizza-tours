<?php
/**
 * Structured itinerary-days renderer for the full-width Tour Details section.
 *
 * Expects: $itineraryDays = array of rows from the itinerary_days table,
 * already ordered by sort_order (see getItineraryDays()). Each row may have:
 *   day_number, title, description, drive_time, meals, accommodation,
 *   image_path, image_alt
 *
 * Renders one continuous, full-width editorial row per day:
 *   text (title, description, metadata) on the left, landscape image on the
 *   right. Scoped under .tour-itinerary-section so it never affects other
 *   page components.
 *
 * The displayed title is always built as "Day {number} — {destination}".
 * Any "Day N:" / "DAY N –" prefix already stored in the title is stripped
 * first so admins never need to type it manually.
 */
if (empty($itineraryDays)) {
    return;
}

function itineraryStripDayPrefix($title) {
    $t = trim((string)$title);
    if ($t === '') return '';
    if (preg_match('/^Day\s+\d+\s*[-:–—]\s*/i', $t, $m)) {
        return trim(substr($t, strlen($m[0])));
    }
    return $t;
}
?>
<?php foreach ($itineraryDays as $i => $day):
    $dayNumber  = isset($day['day_number']) ? intval($day['day_number']) : ($i + 1);
    $destination = itineraryStripDayPrefix($day['title'] ?? '');
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
            <h3 class="itinerary-day-title"><?php echo htmlspecialchars('Day ' . $dayNumber . ' — ' . $destination); ?></h3>
            <?php else: ?>
            <h3 class="itinerary-day-title"><?php echo htmlspecialchars('Day ' . $dayNumber); ?></h3>
            <?php endif; ?>

            <?php if ($dayDesc !== ''): ?>
            <div class="itinerary-day-desc"><?php echo nl2br(htmlspecialchars($dayDesc)); ?></div>
            <?php endif; ?>

            <?php if ($drive !== '' || $meals !== '' || $accom !== ''): ?>
            <div class="itinerary-day-meta">
                <?php if ($drive !== ''): ?>
                <span class="itinerary-day-meta-item">
                    <i class="fas fa-car" aria-hidden="true"></i>
                    <span><strong>Drive:</strong> <?php echo htmlspecialchars($drive); ?></span>
                </span>
                <?php endif; ?>
                <?php if ($meals !== ''): ?>
                <span class="itinerary-day-meta-item">
                    <i class="fas fa-utensils" aria-hidden="true"></i>
                    <span><strong>Meals:</strong> <?php echo htmlspecialchars($meals); ?></span>
                </span>
                <?php endif; ?>
                <?php if ($accom !== ''): ?>
                <span class="itinerary-day-meta-item">
                    <i class="fas fa-bed" aria-hidden="true"></i>
                    <span><strong>Accommodation:</strong> <?php echo htmlspecialchars($accom); ?></span>
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
