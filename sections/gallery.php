<?php
$galleryItems = getGalleryItems(null, 6);
if (empty($galleryItems)) {
    $galleryItems = [
        ['title' => 'King of the Savannah', 'image' => '', 'category' => 'wildlife', 'location' => 'Serengeti, Tanzania'],
        ['title' => 'Giants at Dusk', 'image' => '', 'category' => 'wildlife', 'location' => 'Amboseli, Kenya'],
        ['title' => 'Paradise Found', 'image' => '', 'category' => 'beaches', 'location' => 'Zanzibar, Tanzania'],
        ['title' => 'Roof of Africa', 'image' => '', 'category' => 'mountains', 'location' => 'Kilimanjaro, Tanzania'],
        ['title' => 'Warrior Spirit', 'image' => '', 'category' => 'culture', 'location' => 'Maasai Mara, Kenya'],
        ['title' => 'Luxury in the Wild', 'image' => '', 'category' => 'lodges', 'location' => 'Serengeti, Tanzania']
    ];
}
?>
<section class="section-padding section-cream" id="gallery">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-subtitle"><?php echo __('gallery_subtitle'); ?></span>
            <h2 class="section-title"><?php echo __('gallery_title'); ?></h2>
            <p class="section-description mx-auto"><?php echo __('gallery_desc'); ?></p>
        </div>
        <div class="gallery-filter" data-aos="fade-up">
            <button class="active" data-filter="all"><?php echo __('gallery_filter_all'); ?></button>
            <button data-filter="wildlife"><?php echo __('gallery_filter_wildlife'); ?></button>
            <button data-filter="beaches"><?php echo __('gallery_filter_beaches'); ?></button>
            <button data-filter="mountains"><?php echo __('gallery_filter_mountains'); ?></button>
            <button data-filter="culture"><?php echo __('gallery_filter_culture'); ?></button>
            <button data-filter="lodges"><?php echo __('gallery_filter_lodges'); ?></button>
        </div>
        <div class="gallery-grid" id="galleryGrid">
            <?php foreach ($galleryItems as $idx => $item): 
                $img = !empty($item['image']) && file_exists(BASE_PATH . $item['image']) ? SITE_URL . '/' . $item['image'] : 'https://placehold.co/800x600/0A2540/D4AF37?text=' . urlencode($item['title'] ?: 'Gallery Image');
                $title = htmlspecialchars($item['title'] ?: 'Untitled');
                $location = htmlspecialchars($item['location'] ?: 'East Africa');
            ?>
            <div class="gallery-item" data-category="<?php echo htmlspecialchars($item['category'] ?: 'wildlife'); ?>" data-aos="zoom-in" data-aos-delay="<?php echo $idx * 100; ?>">
                <a href="<?php echo $img; ?>" data-lightbox="gallery" data-title="<?php echo $title; ?>">
                    <img src="<?php echo $img; ?>" alt="<?php echo $title; ?>" loading="lazy" onerror="this.src='https://placehold.co/800x600/0A2540/D4AF37?text=Photo'">
                    <div class="gallery-item-overlay">
                        <h5><?php echo $title; ?></h5>
                        <span><?php echo $location; ?></span>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
