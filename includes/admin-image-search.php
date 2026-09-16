<?php
/**
 * Shared admin "search images by category" menu for the global top header.
 *
 * Kept cheap on purpose: categories come from a shallow listing of the image
 * roots only (no recursive file scan), so this is safe to render on every admin
 * page without slowing navigation down. The category values match the folder
 * names shown on the Compress Images page (dirname of each image path), so the
 * search lands on that page already filtered.
 */

if (!function_exists('admin_image_categories')) {
    function admin_image_categories()
    {
        static $cats = null;
        if ($cats !== null) {
            return $cats;
        }

        $roots = [
            'uploads',
            'assets/images',
            'templates/assets/img',
        ];

        $found = [];
        foreach ($roots as $root) {
            $abs = rtrim(BASE_PATH, '/') . '/' . $root;
            if (!is_dir($abs)) {
                continue;
            }
            $found[$root] = true;
            $items = @scandir($abs);
            if (!is_array($items)) {
                continue;
            }
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                if (is_dir($abs . '/' . $item)) {
                    $found[$root . '/' . $item] = true;
                }
            }
        }

        $cats = array_keys($found);
        return $cats;
    }
}

if (!function_exists('admin_image_search_menu')) {
    function admin_image_search_menu()
    {
        if (!defined('BASE_PATH')) {
            return '';
        }
        $categories = admin_image_categories();
        ob_start();
        ?>
        <li class="nav-item dropdown no-arrow">
            <a class="nav-link dropdown-toggle" href="#" id="imageSearchDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Search images by category">
                <i class="fas fa-images fa-fw text-white"></i>
            </a>
            <div class="dropdown-menu dropdown-menu-right p-3 shadow animated--grow-in" aria-labelledby="imageSearchDropdown">
                <form action="compress-images" method="GET">
                    <div class="form-group mb-2">
                        <label class="small text-muted mb-1">Image category</label>
                        <select class="form-control form-control-sm" name="cat">
                            <option value="">All categories</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="input-group">
                        <input type="text" class="form-control bg-light border-1 small" name="q" placeholder="Image name (optional)" aria-label="Image name">
                        <div class="input-group-append">
                            <button class="btn btn-primary" type="submit" style="background-color: #0A2540; border-color: #0A2540;">
                                <i class="fas fa-search fa-sm"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </li>
        <?php
        return ob_get_clean();
    }
}
