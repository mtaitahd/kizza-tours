<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
http_response_code(500);
$pageSeo = seoPageMeta('home');
$pageSeo['title'] = 'Server Error | Kizza Tours';
$pageSeo['description'] = 'An unexpected error occurred. Please try again later or contact us for assistance.';
$pageSeo['canonical'] = SITE_URL . '/';
$pageSeo['robots'] = 'noindex, follow';
?>
<?php include 'includes/header.php'; ?>
<section style="padding: 180px 0 100px; background: linear-gradient(135deg, var(--primary) 0%, #0D2E4A 100%);">
    <div class="container text-center">
        <div style="font-size: 8rem; font-weight: 800; color: var(--secondary); line-height: 1; opacity: 0.8;">500</div>
        <h1 style="color: var(--white); font-size: clamp(2rem, 4vw, 3rem);" class="mt-3"><?php echo __('500_title'); ?></h1>
        <p style="color: rgba(255,255,255,0.7); max-width: 500px; margin: 1rem auto 2rem; font-size: 1.1rem;">
            <?php echo __('500_desc'); ?>
        </p>
        <div class="d-flex flex-wrap justify-content-center gap-3">
            <a href="<?php echo SITE_URL; ?>/" class="btn btn-premium btn-gold btn-lg"><i class="fas fa-home"></i> <?php echo __('500_home'); ?></a>
            <a href="<?php echo SITE_URL; ?>/contact-us" class="btn btn-premium btn-outline btn-lg"><i class="fas fa-envelope"></i> <?php echo __('500_contact'); ?></a>
        </div>
    </div>
</section>
<?php include 'includes/footer.php'; ?>
