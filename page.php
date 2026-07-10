<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

$slug = trim($_GET['slug'] ?? '');
if (!$slug) {
    header('Location: /');
    exit;
}

$db = db();
$page = $db->fetchOne("SELECT * FROM pages WHERE slug = ? AND status = 'active' LIMIT 1", [$slug]);

if (!$page) {
    header('HTTP/1.0 404 Not Found');
    require '404.php';
    exit;
}

$pageSeo = seoPageMeta('home');
$pageSeo['title'] = htmlspecialchars($page['meta_title'] ?: $page['title'] . ' - Kizza Tours & Safaris');
$pageSeo['description'] = htmlspecialchars(substr($page['meta_description'] ?: 'Learn more about ' . $page['title'] . ' with Kizza Tours & Safaris.', 0, 160));
$pageSeo['canonical'] = SITE_URL . '/' . urlencode($page['slug']);
$pageSeo['ogTitle'] = htmlspecialchars($page['meta_title'] ?: $page['title'] . ' - Kizza Tours');
$pageSeo['ogDesc'] = htmlspecialchars(substr($page['meta_description'] ?: 'Learn more about ' . $page['title'] . ' with Kizza Tours & Safaris.', 0, 200));
$pageSeo['h1'] = htmlspecialchars($page['title']);

require_once 'includes/header.php';
?>

<?php
$heroBg = $page['hero_image'] && file_exists(BASE_PATH . $page['hero_image'])
    ? 'background: linear-gradient(rgba(10,37,64,0.65), rgba(10,37,64,0.65)), url(' . htmlspecialchars(SITE_URL . '/' . $page['hero_image']) . ') center/cover no-repeat;'
    : 'background: linear-gradient(135deg, #0A2540, #1A3A5C);';
?>
<section class="page-header-section section-padding" style="<?= $heroBg ?> padding-top: 150px; min-height: 350px; display: flex; align-items: center;">
    <div class="container text-center">
        <h1 class="text-white" style="font-size: clamp(2rem, 4vw, 3rem); text-shadow: 0 2px 8px rgba(0,0,0,0.3);"><?= htmlspecialchars($page['title']) ?></h1>
    </div>
</section>

<section class="section-padding">
    <div class="container">
        <?php if ($page['image'] && file_exists(BASE_PATH . $page['image'])): ?>
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="page-layout-with-image">
                    <div class="page-content" style="font-size:1.05rem;line-height:1.8;color:#334155;">
                        <?= $page['content'] ?>
                    </div>
                    <div class="page-featured-sidebar">
                        <img src="<?= htmlspecialchars(SITE_URL . '/' . $page['image']) ?>" alt="<?= htmlspecialchars($page['title']) ?>" class="rounded shadow-sm">
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="page-content" style="font-size:1.05rem;line-height:1.8;color:#334155;">
                    <?= $page['content'] ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<style>
.page-layout-with-image { display: flex; gap: 2rem; align-items: flex-start; }
.page-layout-with-image .page-content { flex: 1; min-width: 0; }
.page-featured-sidebar { flex: 0 0 350px; max-width: 350px; }
.page-featured-sidebar img { width: 100%; height: auto; border-radius: 12px; }
@media (max-width: 991px) {
    .page-layout-with-image { flex-direction: column-reverse; }
    .page-featured-sidebar { flex: none; max-width: 100%; }
}
.page-content h1, .page-content h2, .page-content h3 { font-family: var(--font-primary); color: var(--primary); margin-top: 1.5rem; margin-bottom: 1rem; }
.page-content h1 { font-size: 2rem; }
.page-content h2 { font-size: 1.6rem; }
.page-content h3 { font-size: 1.3rem; }
.page-content p { margin-bottom: 1rem; }
.page-content ul, .page-content ol { margin-bottom: 1rem; padding-left: 1.5rem; }
.page-content img { max-width: 100%; border-radius: 8px; margin: 1rem 0; }
.page-content blockquote { border-left: 4px solid var(--secondary); padding-left: 1rem; margin: 1rem 0; color: #6B7280; font-style: italic; }
.page-content a { color: var(--secondary); text-decoration: underline; }
</style>

<?php require_once 'includes/footer.php'; ?>
