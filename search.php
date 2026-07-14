<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$location = isset($_GET['location']) ? trim($_GET['location']) : '';
$budget = isset($_GET['budget']) ? trim($_GET['budget']) : '';
$duration = isset($_GET['duration']) ? trim($_GET['duration']) : '';

$budgetMin = $budgetMax = '';
if ($budget) {
    $parts = explode('-', $budget);
    $budgetMin = $parts[0];
    $budgetMax = isset($parts[1]) ? $parts[1] : '';
}

$searchParams = [];
if ($q) $searchParams['q'] = $q;
if ($location) $searchParams['location'] = $location;
if ($budgetMin) $searchParams['budget_min'] = $budgetMin;
if ($budgetMax) $searchParams['budget_max'] = $budgetMax;
if ($duration) $searchParams['duration'] = $duration;

$packages = searchTourPackages($searchParams);

$pageSeo = seoPageMeta('search');
$pageSeo['title'] = 'Search Tours - ' . getSetting('site_name', 'Kizza Tours & Safaris');
$pageSeo['description'] = 'Search and filter our premium East Africa tour packages by destination, budget, and more.';
$pageSeo['canonical'] = SITE_URL . '/search';
$pageSeo['robots'] = 'noindex, nofollow';
?>
<?php include 'includes/header.php'; ?>

<main>
    <section class="search-hero section-padding">
        <div class="container">
            <div class="text-center mb-5">
                <h1 class="section-title text-white"><?php echo __('search_tours') ?: 'Find Your Perfect Safari'; ?></h1>
                <p class="section-subtitle"><?php echo __('search_subtitle') ?: 'Discover tailor-made adventures across East Africa'; ?></p>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <form class="search-form" action="<?php echo SITE_URL; ?>/search" method="GET">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <input type="text" name="q" class="form-control search-input" placeholder="<?php echo __('search_keyword') ?: 'Keyword...'; ?>" value="<?php echo htmlspecialchars($q); ?>">
                            </div>
                            <div class="col-md-3">
                                <select name="location" class="form-select search-input">
                                    <option value="">All Destinations</option>
                                    <option value="Tanzania" <?php echo $location === 'Tanzania' ? 'selected' : ''; ?>>Tanzania</option>
                                    <option value="Kenya" <?php echo $location === 'Kenya' ? 'selected' : ''; ?>>Kenya</option>
                                    <option value="Uganda" <?php echo $location === 'Uganda' ? 'selected' : ''; ?>>Uganda</option>
                                    <option value="Rwanda" <?php echo $location === 'Rwanda' ? 'selected' : ''; ?>>Rwanda</option>
                                    <option value="Zanzibar" <?php echo $location === 'Zanzibar' ? 'selected' : ''; ?>>Zanzibar</option>
                                    <option value="Burundi" <?php echo $location === 'Burundi' ? 'selected' : ''; ?>>Burundi</option>
                                    <option value="Kenya-Tanzania" <?php echo $location === 'Kenya-Tanzania' ? 'selected' : ''; ?>>Kenya & Tanzania</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="budget" class="form-select search-input">
                                    <option value="">Any Budget</option>
                                    <option value="0-1000" <?php echo $budget === '0-1000' ? 'selected' : ''; ?>>Under $1,000</option>
                                    <option value="1000-3000" <?php echo $budget === '1000-3000' ? 'selected' : ''; ?>>$1,000 - $3,000</option>
                                    <option value="3000-5000" <?php echo $budget === '3000-5000' ? 'selected' : ''; ?>>$3,000 - $5,000</option>
                                    <option value="5000-10000" <?php echo $budget === '5000-10000' ? 'selected' : ''; ?>>$5,000 - $10,000</option>
                                    <option value="10000" <?php echo $budget === '10000' ? 'selected' : ''; ?>>$10,000+</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="duration" class="form-select search-input">
                                    <option value=""><?php echo __('any_duration') ?: 'Any Duration'; ?></option>
                                    <option value="1" <?php echo $duration === '1' ? 'selected' : ''; ?>>1-3 Days</option>
                                    <option value="4" <?php echo $duration === '4' ? 'selected' : ''; ?>>4-7 Days</option>
                                    <option value="8" <?php echo $duration === '8' ? 'selected' : ''; ?>>8-14 Days</option>
                                    <option value="15" <?php echo $duration === '15' ? 'selected' : ''; ?>>15+ Days</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex gap-2">
                                <button type="submit" class="btn btn-premium btn-gold flex-grow-1">
                                    <i class="fas fa-search me-1"></i> <?php echo __('search_btn') ?: 'Search'; ?>
                                </button>
                                <?php if ($q || $location || $budget || $duration): ?>
                                <a href="<?php echo SITE_URL; ?>/search" class="btn btn-outline-gold">
                                    <i class="fas fa-times"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <section class="section-padding section-cream">
        <div class="container">
            <?php if ($q || $location || $budget || $duration): ?>
            <div class="mb-4">
                <p class="text-muted">
                    <?php echo count($packages); ?> <?php echo __('results_found') ?: 'tour(s) found'; ?>
                    <?php if ($q): ?><?php echo __('for_keyword') ?: 'for'; ?> "<strong><?php echo htmlspecialchars($q); ?></strong>"<?php endif; ?>
                </p>
            </div>
            <?php endif; ?>

            <?php if (empty($packages)): ?>
            <div class="text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h3><?php echo __('no_results') ?: 'No Tours Found'; ?></h3>
                <p class="text-muted"><?php echo __('no_results_desc') ?: 'Try adjusting your search criteria or browse all our tours below.'; ?></p>
                <a href="<?php echo SITE_URL; ?>/search" class="btn btn-premium btn-gold mt-3">
                    <i class="fas fa-undo me-1"></i> <?php echo __('reset_search') ?: 'Reset Search'; ?>
                </a>
            </div>
            <?php else: ?>
            <div class="row g-4">
                <?php foreach ($packages as $i => $pkg):
                    $pkgImg = !empty($pkg['image']) && file_exists(BASE_PATH . $pkg['image']) ? SITE_URL . '/' . $pkg['image'] : ASSETS_PATH . 'images/placeholder.svg';
                ?>
                <div class="col-lg-4 col-md-6">
                    <div class="package-card">
                        <div class="package-card-image">
                            <img src="<?php echo $pkgImg; ?>" alt="<?php echo htmlspecialchars($pkg['title']); ?>" loading="lazy" onerror="this.src='<?php echo ASSETS_PATH; ?>images/placeholder.svg'">
                            <?php if ($i === 0): ?>
                            <span class="package-card-badge"><?php echo __('bestseller') ?: 'Bestseller'; ?></span>
                            <?php elseif ($pkg['featured']): ?>
                            <span class="package-card-badge"><?php echo __('premium') ?: 'Premium'; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="package-card-body">
                            <div class="package-card-meta">
                                <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($pkg['duration']); ?></span>
                                <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($pkg['country']); ?></span>
                            </div>
                            <h3 class="package-card-title"><?php echo htmlspecialchars($pkg['title']); ?></h3>
                            <?php
                            $rating = floatval($pkg['rating']);
                            ?>
                            <div class="package-card-rating">
                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                <i class="fas fa-star<?php echo $s <= $rating ? '' : ' far fa-star'; ?>" style="color: <?php echo $s <= $rating ? 'var(--secondary)' : '#ddd'; ?>;"></i>
                                <?php endfor; ?>
                                <span class="ms-2">(<?php echo rand(20, 150); ?> <?php echo __('reviews') ?: 'reviews'; ?>)</span>
                            </div>
                            <div class="package-card-price">$<?php echo number_format(floatval($pkg['price'])); ?> <small>/ <?php echo __('per_person') ?: 'person'; ?></small></div>
                            <?php if (!empty($pkg['highlights'])): $highlights = array_slice(explode(',', $pkg['highlights']), 0, 4); ?>
                            <div class="package-card-highlights">
                                <?php foreach ($highlights as $hl): ?>
                                <span><?php echo htmlspecialchars(trim($hl)); ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            <div class="package-card-actions">
                                <a href="<?php echo SITE_URL; ?>/safari/<?php echo urlencode($pkg['slug']); ?>" class="btn btn-outline-gold">
                                    <i class="fas fa-info-circle"></i> <?php echo __('view_details') ?: 'View Details'; ?>
                                </a>
                                <a href="<?php echo SITE_URL; ?>/book-tour?package=<?php echo $pkg['id']; ?>" class="btn btn-premium btn-gold">
                                    <i class="fas fa-calendar-check"></i> <?php echo __('book_now') ?: 'Book Now'; ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
