<?php
$packages = getTourPackages(['featured' => false], 6);
// If no packages in DB, use defaults
if (empty($packages)) {
    $packages = [
        ['id' => 0, 'title' => 'Serengeti Luxury Safari Experience', 'slug' => 'serengeti-luxury-safari', 'duration' => '7 Days / 6 Nights', 'price' => 4200, 'country' => 'Tanzania', 'rating' => 5.0, 'highlights' => 'Game Drives,Great Migration Viewing,Big Five Tracking,Luxury Lodge Accommodation,Professional Guide', 'image' => null, 'description' => 'A premium luxury safari through the Serengeti ecosystem. Witness the Great Migration, track the Big Five, and stay in award-winning luxury lodges with panoramic views of the African savannah.', 'gallery' => '', 'itinerary' => 'Day 1: Arrival & transfer to luxury lodge.\nDay 2: Full day Serengeti game drive.\nDay 3: Great Migration tracking.\nDay 4: Big Five safari.\nDay 5: Bush breakfast & walking safari.\nDay 6: Sunset sundowners.\nDay 7: Departure.', 'includes' => 'All Accommodation,Full Board Meals,Professional Guide,Game Drives,Park Fees,Airport Transfers,Drinking Water', 'excludes' => 'International Flights,Visa Fees,Travel Insurance,Personal Expenses,Tips'],
        ['id' => 0, 'title' => 'Maasai Mara Great Migration Safari', 'slug' => 'maasai-mara-migration', 'duration' => '5 Days / 4 Nights', 'price' => 3800, 'country' => 'Kenya', 'rating' => 5.0, 'highlights' => 'River Crossings,Hot Air Balloon Safari,Big Five Game Drives,Bush Dinner,Maasai Village Visit', 'image' => null, 'description' => 'Witness one of nature\'s greatest spectacles — the Great Migration river crossings in the Maasai Mara. Stay in premium tented camps and enjoy hot air balloon safaris over the savannah.', 'gallery' => '', 'itinerary' => 'Day 1: Arrival Mara.\nDay 2: Full day migration viewing.\nDay 3: Balloon safari & bush breakfast.\nDay 4: Maasai village visit.\nDay 5: Departure.', 'includes' => 'Luxury Tented Camp,All Meals,Game Drives,Park Fees,Balloon Safari,Professional Guide', 'excludes' => 'International Flights,Visa,Tips,Drinks'],
        ['id' => 0, 'title' => 'Uganda Gorilla Trekking Adventure', 'slug' => 'uganda-gorilla-trekking', 'duration' => '4 Days / 3 Nights', 'price' => 5500, 'country' => 'Uganda', 'rating' => 5.0, 'highlights' => 'Gorilla Encounter,Nature Walks,Bird Watching,Luxury Eco-Lodge,Community Visit', 'image' => null, 'description' => 'Trek through the misty Bwindi Impenetrable Forest to spend an unforgettable hour with a mountain gorilla family. A life-changing wildlife encounter in one of Africa\'s most beautiful settings.', 'gallery' => '', 'itinerary' => 'Day 1: Arrival Kigali & transfer to Bwindi.\nDay 2: Gorilla trekking experience.\nDay 3: Nature walk & community visit.\nDay 4: Departure.', 'includes' => 'Gorilla Permit,Luxury Lodge,Full Board,Professional Guide,Park Fees,Transfers', 'excludes' => 'Flights,Visa,Travel Insurance,Tips,Personal Expenses'],
        ['id' => 0, 'title' => 'Kilimanjaro Machame Route Expedition', 'slug' => 'kilimanjaro-machame', 'duration' => '7 Days / 6 Nights', 'price' => 3200, 'country' => 'Tanzania', 'rating' => 5.0, 'highlights' => 'Summit at Uhuru Peak,Professional Guides,Quality Camping Gear,Summit Certificate,Porters & Cooks', 'image' => null, 'description' => 'Conquer Africa\'s highest peak via the scenic Machame Route. Experience five climate zones, from lush rainforest to arctic summit, with expert guides and premium camping equipment.', 'gallery' => '', 'itinerary' => 'Day 1: Machame Gate to Machame Camp.\nDay 2: Shira Camp.\nDay 3: Barranco Camp via Lava Tower.\nDay 4: Karanga Camp.\nDay 5: Barafu Camp.\nDay 6: Summit day! Uhuru Peak.\nDay 7: Descent & departure.', 'includes' => 'Professional Guide Team,All Meals,Camping Equipment,Park Fees,Summit Certificate,Transfers', 'excludes' => 'Flights,Visa,Tips,Personal Gear,Travel Insurance'],
        ['id' => 0, 'title' => 'Zanzibar Beach & Culture Holiday', 'slug' => 'zanzibar-beach', 'duration' => '6 Days / 5 Nights', 'price' => 3100, 'country' => 'Zanzibar', 'rating' => 5.0, 'highlights' => 'Beach Resort,Snorkeling,Stone Town Tour,Spice Farm Visit,Sunset Dhow Cruise', 'image' => null, 'description' => 'Unwind on the pristine beaches of Zanzibar at a premium beach resort. Enjoy sunset dhow cruises, spice tours, snorkeling in turquoise waters, and explore historic Stone Town.', 'gallery' => '', 'itinerary' => 'Day 1: Arrival & beach resort check-in.\nDay 2: Snorkeling & water sports.\nDay 3: Stone Town tour.\nDay 4: Spice farm & cooking class.\nDay 5: Sunset dhow cruise.\nDay 6: Departure.', 'includes' => 'Luxury Resort,Breakfast,Park Fees,Stone Town Tour,Airport Transfers', 'excludes' => 'Flights,Visa,Lunch & Dinner,Spa Treatments,Tips'],
        ['id' => 0, 'title' => 'Rwanda Luxury Gorilla Safari', 'slug' => 'rwanda-gorilla', 'duration' => '4 Days / 3 Nights', 'price' => 7200, 'country' => 'Rwanda', 'rating' => 5.0, 'highlights' => 'Gorilla Trekking,Luxury Lodge,Golden Monkey Trek,Kigali City Tour,Private Butler Service', 'image' => null, 'description' => 'The ultimate luxury gorilla trekking experience in Rwanda\'s Volcanoes National Park. Stay at a world-class lodge, trek to see mountain gorillas, and explore the land of a thousand hills.', 'gallery' => '', 'itinerary' => 'Day 1: Arrival Kigali & city tour.\nDay 2: Gorilla trekking.\nDay 3: Golden monkey trek & spa.\nDay 4: Departure.', 'includes' => 'Gorilla Permit,Ultra-Luxury Lodge,All Meals,Private Guide,Transfers,Personalized Service', 'excludes' => 'International Flights,Visa,Tips,Spa Treatments']
    ];
}
?>
<section class="section-padding" id="packages">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-subtitle"><?php echo __('pkg_subtitle'); ?></span>
            <h2 class="section-title"><?php echo __('pkg_title'); ?></h2>
            <p class="section-description mx-auto"><?php echo __('pkg_desc'); ?></p>
        </div>
        <div class="packages-filter text-center mb-4" data-aos="fade-up">
            <div class="gallery-filter">
                <button class="active" data-filter="all"><?php echo __('pkg_filter_all'); ?></button>
                <button data-filter="tanzania"><?php echo __('pkg_filter_tz'); ?></button>
                <button data-filter="kenya"><?php echo __('pkg_filter_ke'); ?></button>
                <button data-filter="uganda"><?php echo __('pkg_filter_ug'); ?></button>
                <button data-filter="rwanda"><?php echo __('pkg_filter_rw'); ?></button>
                <button data-filter="zanzibar"><?php echo __('pkg_filter_zb'); ?></button>
                <button data-filter="kilimanjaro"><?php echo __('pkg_filter_kili'); ?></button>
            </div>
        </div>
        <div class="row g-4 packages-container">
            <?php foreach ($packages as $i => $pkg): 
                $countrySlug = strtolower($pkg['country']);
                $pkgTitle = $pkg['title'] ?? '';
                $img = !empty($pkg['image']) && file_exists(BASE_PATH . $pkg['image']) ? SITE_URL . '/' . $pkg['image'] : '';
                if (empty($img)) {
                    $imgKey = '';
                    if (stripos($pkgTitle, 'maasai mara') !== false || stripos($pkgTitle, 'migration') !== false) $imgKey = 'maasai_mara_image';
                    elseif (stripos($pkgTitle, 'uganda gorilla trekking') !== false) $imgKey = 'uganda_gorilla_adventure_image';
                    elseif (stripos($pkgTitle, 'rwanda luxury gorilla') !== false || stripos($pkgTitle, 'rwanda gorilla') !== false) $imgKey = 'rwanda_luxury_gorilla_image';
                    elseif (stripos($pkgTitle, 'amboseli') !== false) $imgKey = 'amboseli_kilimanjaro_image';
                    if ($imgKey) $img = getMediaUrl($imgKey, '');
                }
                if (empty($img)) $img = ASSETS_PATH . 'images/destinations/' . $countrySlug . '.jpg';
                $highlightsArr = array_filter(array_map('trim', explode(',', $pkg['highlights'] ?? '')));
                $rating = intval($pkg['rating'] ?? 5);
                $pkgId = $pkg['id'] ?? 0;
            ?>
            <div class="col-lg-4 col-md-6 package-item" data-category="<?php echo $countrySlug; ?>" data-aos="fade-up" data-aos-delay="<?php echo 100 + ($i * 100); ?>">
                <div class="package-card" data-package-id="<?php echo $pkgId; ?>">
                    <div class="package-card-image">
                        <img src="<?php echo $img; ?>" alt="<?php echo htmlspecialchars($pkg['title']); ?>" loading="lazy" onerror="this.src='assets/images/placeholder.svg'">
                        <?php if ($i === 0): ?><span class="package-card-badge"><?php echo __('pkg_badge_bestseller'); ?></span><?php endif; ?>
                        <?php if ($i === 2): ?><span class="package-card-badge"><?php echo __('pkg_badge_premium'); ?></span><?php endif; ?>
                    </div>
                    <div class="package-card-body">
                        <div class="package-card-meta">
                            <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($pkg['duration'] ?: 'N/A'); ?></span>
                            <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($pkg['country']); ?></span>
                        </div>
                        <h3 class="package-card-title"><?php echo htmlspecialchars($pkg['title']); ?></h3>
                        <div class="package-card-rating">
                            <?php for ($s = 0; $s < 5; $s++): ?>
                                <i class="fas fa-star" style="color: <?php echo $s < $rating ? 'var(--secondary)' : '#ddd'; ?>;"></i>
                            <?php endfor; ?>
                            <span class="ms-2">(<?php echo rand(50, 200); ?> <?php echo __('pkg_reviews'); ?>)</span>
                        </div>
                        <div class="package-card-price">$<?php echo number_format($pkg['price'], 0); ?> <small><?php echo __('pkg_per_person'); ?></small></div>
                        <?php if (!empty($highlightsArr)): ?>
                        <div class="package-card-highlights">
                            <?php foreach (array_slice($highlightsArr, 0, 4) as $hl): ?>
                                <span><?php echo htmlspecialchars(trim($hl)); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <div class="package-card-actions">
                            <a href="<?php echo SITE_URL; ?>/safari/<?php echo htmlspecialchars($pkg['slug'] ?? ''); ?>" class="btn btn-outline-gold view-details-btn" data-package-index="<?php echo $i; ?>">
                                <i class="fas fa-info-circle"></i> <?php echo __('pkg_view_details'); ?>
                            </a>
                            <a href="#booking" class="btn btn-premium btn-gold">
                                <i class="fas fa-calendar-check"></i> <?php echo __('pkg_book_now'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-5" data-aos="fade-up">
            <a href="#booking" class="btn btn-premium btn-gold btn-lg"><i class="fas fa-customize"></i> <?php echo __('pkg_custom_safari'); ?></a>
        </div>
    </div>
</section>

<!--=============================================
PACKAGE DETAIL MODAL
=============================================-->
<div class="pkg-modal-overlay" id="pkgModalOverlay">
    <div class="pkg-modal" id="pkgModal">
        <button type="button" class="pkg-modal-close" id="closePkgModal">
            <i class="fas fa-times"></i>
        </button>
        <div class="pkg-modal-content">
            <!-- Filled dynamically by JS -->
        </div>
    </div>
</div>

<script>
// Package data for the modal (JS fallback when no DB)
window.__packageData = <?php echo json_encode($packages, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
