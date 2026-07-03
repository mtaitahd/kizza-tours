<?php
$destinations = getDestinations();
if (empty($destinations)) {
    // Default destinations if DB is empty
    $destinations = [
        ['name' => 'Serengeti', 'country' => 'Tanzania', 'slug' => 'serengeti', 'description' => 'Endless plains, abundant wildlife, and the Great Migration.', 'image' => null, 'short_description' => ''],
        ['name' => 'Maasai Mara', 'country' => 'Kenya', 'slug' => 'maasai-mara', 'description' => 'Kenya\'s premier safari destination with the Big Five.', 'image' => null, 'short_description' => ''],
        ['name' => 'Ngorongoro', 'country' => 'Tanzania', 'slug' => 'ngorongoro', 'description' => 'The world\'s largest inactive volcanic caldera.', 'image' => null, 'short_description' => ''],
        ['name' => 'Kilimanjaro', 'country' => 'Tanzania', 'slug' => 'kilimanjaro', 'description' => 'Africa\'s highest peak at 5,895 meters.', 'image' => null, 'short_description' => ''],
        ['name' => 'Zanzibar', 'country' => 'Tanzania', 'slug' => 'zanzibar', 'description' => 'Pristine beaches, turquoise waters, and rich history.', 'image' => null, 'short_description' => ''],
        ['name' => 'Bwindi', 'country' => 'Uganda', 'slug' => 'bwindi', 'description' => 'Home to half the world\'s mountain gorillas.', 'image' => null, 'short_description' => ''],
        ['name' => 'Volcanoes', 'country' => 'Rwanda', 'slug' => 'volcanoes', 'description' => 'Misty mountains and rare mountain gorillas.', 'image' => null, 'short_description' => ''],
        ['name' => 'Amboseli', 'country' => 'Kenya', 'slug' => 'amboseli', 'description' => 'Iconic views of Kilimanjaro and large elephant herds.', 'image' => null, 'short_description' => ''],
        ['name' => 'Tarangire', 'country' => 'Tanzania', 'slug' => 'tarangire', 'description' => 'Famous for its massive baobab trees and elephant herds.', 'image' => null, 'short_description' => '']
    ];
}
?>
<section class="destination-explorer section-padding-lg" id="destinations">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-subtitle"><?php echo __('dest_subtitle'); ?></span>
            <h2 class="section-title"><?php echo __('dest_title'); ?></h2>
            <p class="section-description mx-auto">
                <?php echo __('dest_desc'); ?>
            </p>
        </div>
        <div class="destination-grid">
            <?php $delay = 100; foreach ($destinations as $dest): 
                $img = !empty($dest['image']) && file_exists(BASE_PATH . $dest['image']) ? SITE_URL . '/' . $dest['image'] : ASSETS_PATH . 'images/destinations/' . $dest['slug'] . '.jpg';
                $countryImg = strtolower($dest['country']);
            ?>
            <div class="destination-card" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                <img src="<?php echo $img; ?>" alt="<?php echo htmlspecialchars($dest['name']); ?>" class="destination-card-image" loading="lazy" onerror="this.src='https://placehold.co/600x400/0A2540/D4AF37?text=<?php echo urlencode($dest['name']); ?>'">
                <div class="destination-card-overlay">
                    <h3 class="destination-card-title"><?php echo htmlspecialchars($dest['name']); ?></h3>
                    <span class="destination-card-country"><?php echo htmlspecialchars($dest['country']); ?></span>
                </div>
                <div class="destination-card-hover">
                    <h3 class="destination-card-title"><?php echo htmlspecialchars($dest['name']); ?></h3>
                    <span class="destination-card-country"><?php echo htmlspecialchars($dest['country']); ?></span>
                    <p class="text-white-50 small mt-2"><?php echo htmlspecialchars($dest['description'] ?: $dest['short_description'] ?: __('dest_experience') . ' ' . $dest['name']); ?></p>
                    <div class="destination-card-stats">
                        <div class="destination-stat"><div class="destination-stat-number">5★</div><div class="destination-stat-label"><?php echo __('dest_rating'); ?></div></div>
                        <div class="destination-stat"><div class="destination-stat-number"><?php echo rand(50, 500); ?>+</div><div class="destination-stat-label"><?php echo __('dest_tours'); ?></div></div>
                        <div class="destination-stat"><div class="destination-stat-number"><?php echo rand(1000, 10000); ?>+</div><div class="destination-stat-label"><?php echo __('dest_visitors'); ?></div></div>
                        <div class="destination-stat"><div class="destination-stat-number">Premium</div><div class="destination-stat-label"><?php echo __('dest_class'); ?></div></div>
                    </div>
                    <a href="<?php echo SITE_URL; ?>/destination/<?php echo htmlspecialchars($dest['slug']); ?>" class="btn btn-premium btn-gold btn-sm mt-3"><?php echo __('dest_view_tours'); ?></a>
                </div>
            </div>
            <?php $delay += 100; endforeach; ?>
        </div>
    </div>
</section>
