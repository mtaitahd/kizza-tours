<?php
// Render gallery previews through the same resolver as public tour images.
$galleryPreviewUrl = resolveImageUrl($item['image'] ?? '', '');
$gallerySourcePath = (new TourImagePaths(BASE_PATH))->normalize($item['image'] ?? '');
$galleryOriginalUrl = $galleryPreviewUrl ?: SITE_URL . '/' . implode('/', array_map('rawurlencode', explode('/', $gallerySourcePath)));
?>
<img src="<?php echo htmlspecialchars($galleryPreviewUrl ?: SITE_URL . '/assets/images/placeholder.svg', ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($item['title'] ?: 'Untitled', ENT_QUOTES); ?>" data-gallery-image data-image-path="<?php echo htmlspecialchars($item['image'] ?? '', ENT_QUOTES); ?>" data-image-url="<?php echo htmlspecialchars($galleryOriginalUrl, ENT_QUOTES); ?>" data-image-missing="<?php echo $galleryPreviewUrl === '' ? '1' : '0'; ?>" loading="lazy" decoding="async" onerror="markGalleryImageError(this)">
