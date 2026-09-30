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
        <?php if (function_exists('adminIsOwner') && adminIsOwner()):
            $clearedActivityId = (int)($_SESSION['admin_activity_cleared_id'] ?? 0);
            $todayNotifications = db()->fetchAll(
                "SELECT id, actor_admin_id, actor_name, action, module, record_title, created_at FROM admin_activity_log WHERE created_at >= CURDATE() AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND id > ? ORDER BY id DESC LIMIT 6",
                [$clearedActivityId]
            );
            $todayNotificationCount = (int)(db()->fetchOne(
                "SELECT COUNT(*) AS total FROM admin_activity_log WHERE created_at >= CURDATE() AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND id > ?",
                [$clearedActivityId]
            )['total'] ?? 0);
        ?>
        <li class="nav-item dropdown no-arrow">
            <a class="nav-link dropdown-toggle" href="#" id="adminActivityNotifications" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Today's staff activity">
                <i class="fas fa-bell fa-fw text-white"></i><?php if ($todayNotificationCount > 0): ?><span class="badge badge-danger badge-counter"><?= number_format($todayNotificationCount) ?></span><?php endif; ?>
            </a>
            <div class="dropdown-list dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="adminActivityNotifications" style="min-width:340px">
                <h6 class="dropdown-header">Staff activity today</h6>
                <?php if (!$todayNotifications): ?><span class="dropdown-item text-muted small">No new activity today.</span><?php endif; ?>
                <?php foreach ($todayNotifications as $notice): $noticeUrl = 'activity?' . http_build_query(['module' => $notice['module'], 'user_id' => (int)($notice['actor_admin_id'] ?? 0), 'date_from' => date('Y-m-d'), 'date_to' => date('Y-m-d')]); ?>
                    <a class="dropdown-item d-flex align-items-start" href="<?= htmlspecialchars($noticeUrl) ?>"><span class="mr-3 mt-1 text-primary"><i class="fas fa-clipboard-list"></i></span><span><span class="font-weight-bold d-block"><?= htmlspecialchars($notice['actor_name']) ?> <?= htmlspecialchars(strtolower(str_replace('_', ' ', $notice['action']))) ?> <?= htmlspecialchars(strtolower(str_replace('_', ' ', $notice['module']))) ?></span><span class="small text-muted"><?= htmlspecialchars($notice['record_title'] ?: date('H:i', strtotime($notice['created_at']))) ?> · <?= htmlspecialchars(date('H:i', strtotime($notice['created_at']))) ?></span></span></a>
                <?php endforeach; ?>
                <a class="dropdown-item text-center small text-primary" href="activity?module=tours">View daily activity summary</a>
                <form method="post" action="activity?module=tours" class="px-3 pb-2 text-center"><input type="hidden" name="action" value="clear_notifications"><?php csrf_field(); ?><button class="btn btn-sm btn-outline-secondary" type="submit" <?= $todayNotificationCount < 1 ? 'disabled' : '' ?>>Clear notifications</button></form>
            </div>
        </li>
        <?php endif; ?>
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
