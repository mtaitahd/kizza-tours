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

$page_title = $page['meta_title'] ?: $page['title'] . ' - Kizza Tours & Safaris';
$page_desc = $page['meta_description'] ?: 'Learn more about ' . $page['title'] . ' with Kizza Tours & Safaris.';
$page_keywords = $page['meta_keywords'] ?: '';
$og_image = $page['image'] ? SITE_URL . '/' . $page['image'] : '';

require_once 'includes/header.php';
?>

<section class="page-header-section section-padding" style="background: linear-gradient(135deg, #0A2540, #1A3A5C); padding-top: 150px;">
    <div class="container text-center">
        <h1 class="text-white" style="font-size: clamp(2rem, 4vw, 3rem);"><?= htmlspecialchars($page['title']) ?></h1>
        <?php if ($page['image'] && file_exists(BASE_PATH . $page['image'])): ?>
        <img src="<?= htmlspecialchars(SITE_URL . '/' . $page['image']) ?>" alt="<?= htmlspecialchars($page['title']) ?>" class="mt-3 rounded" style="max-height:300px;width:auto;border-radius:12px;">
        <?php endif; ?>
    </div>
</section>

<section class="section-padding">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="page-content" style="font-size:1.05rem;line-height:1.8;color:#334155;">
                    <?= $page['content'] ?>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
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
