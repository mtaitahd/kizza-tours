<?php
/**
 * Shared itinerary-days renderer for the Tour Details section.
 *
 * Expects: $itineraryDays = array of rows from the itinerary_days table,
 * already ordered by sort_order. Each row may have:
 *   day_number, title, description, image_path, image_alt
 *
 * Renders a responsive two-column stack: text on the left, day image on the
 * right (image drops below the description on smaller screens). Scoped under
 * .tour-itinerary-section so it never affects other page components.
 */
if (empty($itineraryDays)) {
    return;
}
?>
<div class="tour-itinerary-section">
    <?php foreach ($itineraryDays as $i => $day):
        $dayTitle  = trim($day['title'] ?? '');
        $dayDesc   = trim($day['description'] ?? '');
        $dayNumber = isset($day['day_number']) ? intval($day['day_number']) : ($i + 1);

        $dayImg = '';
        if (!empty($day['image_path']) && file_exists(BASE_PATH . $day['image_path'])) {
            $dayImg = SITE_URL . '/' . $day['image_path'];
        }
        $dayAlt = !empty($day['image_alt']) ? $day['image_alt'] : (trim($dayTitle) ?: 'Itinerary day');
    ?>
    <div class="itinerary-day" id="itinerary-day-<?php echo $dayNumber; ?>">
        <div class="itinerary-day-text">
            <?php if ($dayTitle !== ''): ?>
            <h3 class="itinerary-day-title"><?php echo htmlspecialchars($dayTitle); ?></h3>
            <?php else: ?>
            <h3 class="itinerary-day-title">Day <?php echo $dayNumber; ?></h3>
            <?php endif; ?>

            <?php if ($dayDesc !== ''): ?>
            <div class="itinerary-day-desc"><?php echo nl2br(htmlspecialchars($dayDesc)); ?></div>
            <?php endif; ?>
        </div>

        <?php if (!empty($dayImg)): ?>
        <div class="itinerary-day-media">
            <img src="<?php echo $dayImg; ?>" alt="<?php echo htmlspecialchars($dayAlt); ?>" class="itinerary-day-img" loading="lazy">
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
