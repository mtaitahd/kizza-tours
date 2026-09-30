<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once __DIR__ . '/../includes/admin-auth.php';

$db = db();
requireAdminOwner();

function validActivityDate($value)
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$value)) return false;
    return checkdate((int)substr($value, 5, 2), (int)substr($value, 8, 2), (int)substr($value, 0, 4));
}

$userId = max(0, (int)($_GET['user_id'] ?? 0));
$module = trim($_GET['module'] ?? '');
$action = trim($_GET['action'] ?? '');
$dateFromInput = trim($_GET['date_from'] ?? '');
$dateToInput = trim($_GET['date_to'] ?? '');
$dateFrom = validActivityDate($dateFromInput) ? $dateFromInput : date('Y-m-d', strtotime('-30 days'));
$dateTo = validActivityDate($dateToInput) ? $dateToInput : date('Y-m-d');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$validModules = ['users','login','logout','bookings','tours','destinations','gallery','testimonials','faqs','inquiries','quotes','pages','settings','media','sitemap'];
$validActions = ['created','updated','deleted','status_changed','activated','deactivated','password_reset','password_changed','login','logout','replied','regenerated','replaced','started','sent','tested'];
if (!in_array($module, $validModules, true)) $module = '';
if (!in_array($action, $validActions, true)) $action = '';

$where = [];
$params = [];
if ($userId > 0) { $where[] = 'actor_admin_id = ?'; $params[] = $userId; }
if ($module !== '') { $where[] = 'module = ?'; $params[] = $module; }
if ($action !== '') { $where[] = 'action = ?'; $params[] = $action; }
if (validActivityDate($dateFrom)) { $where[] = 'created_at >= ?'; $params[] = $dateFrom . ' 00:00:00'; }
if (validActivityDate($dateTo)) { $where[] = 'created_at < DATE_ADD(?, INTERVAL 1 DAY)'; $params[] = $dateTo . ' 00:00:00'; }
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$total = (int)($db->fetchOne("SELECT COUNT(*) AS n FROM admin_activity_log{$whereSql}", $params)['n'] ?? 0);
$pageCount = max(1, (int)ceil($total / $perPage));
$page = min($page, $pageCount);
$offset = ($page - 1) * $perPage;
$activities = $db->fetchAll(
    "SELECT id, actor_admin_id, actor_name, action, module, record_id, record_title, metadata, created_at
     FROM admin_activity_log{$whereSql} ORDER BY created_at DESC, id DESC LIMIT {$perPage} OFFSET {$offset}",
    $params
);
$daily = $db->fetchAll(
    "SELECT DATE(created_at) AS activity_date, actor_admin_id, actor_name, COUNT(*) AS total
     FROM admin_activity_log{$whereSql} GROUP BY DATE(created_at), actor_admin_id, actor_name
     ORDER BY activity_date DESC, total DESC LIMIT 150",
    $params
);
$users = $db->fetchAll("SELECT id, full_name, username FROM admin_users ORDER BY full_name");
$selectedUser = $userId ? $db->fetchOne("SELECT full_name FROM admin_users WHERE id = ?", [$userId]) : null;
$usersRepresented = (int)($db->fetchOne("SELECT COUNT(DISTINCT actor_admin_id) AS n FROM admin_activity_log{$whereSql}", $params)['n'] ?? 0);
$pageBase = $_GET;
unset($pageBase['page']);
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Activity Log - Kizza Tours Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css" rel="stylesheet"><link href="../templates/assets/css/ruang-admin.min.css" rel="stylesheet"><link href="css/admin.css" rel="stylesheet"><link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet"></head>
<body><div class="container-fluid py-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h3 mb-1">User Activity Log</h1><p class="text-muted mb-0"><?= $selectedUser ? 'Activity for ' . htmlspecialchars($selectedUser['full_name']) : 'Review staff actions and daily totals.' ?></p></div><a class="btn btn-outline-secondary" href="users">User Management</a></div>
    <div class="row mb-4"><div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><div class="text-muted">Matching activities</div><div class="h3 mb-0"><?= number_format($total) ?></div></div></div></div><div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><div class="text-muted">Users represented</div><div class="h3 mb-0"><?= number_format($usersRepresented) ?></div></div></div></div><div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><div class="text-muted">Page</div><div class="h3 mb-0"><?= $page ?> / <?= $pageCount ?></div></div></div></div></div>
    <div class="card shadow-sm mb-4"><div class="card-header font-weight-bold">Filters</div><div class="card-body"><form method="get" class="form-row align-items-end">
        <div class="form-group col-md-2"><label>User</label><select class="form-control" name="user_id"><option value="">All users</option><?php foreach ($users as $user): ?><option value="<?= (int)$user['id'] ?>" <?= $userId === (int)$user['id'] ? 'selected' : '' ?>><?= htmlspecialchars($user['full_name']) ?></option><?php endforeach; ?></select></div>
        <div class="form-group col-md-2"><label>Module</label><select class="form-control" name="module"><option value="">All modules</option><?php foreach ($validModules as $item): ?><option value="<?= $item ?>" <?= $module === $item ? 'selected' : '' ?>><?= htmlspecialchars(ucwords(str_replace('_', ' ', $item))) ?></option><?php endforeach; ?></select></div>
        <div class="form-group col-md-2"><label>Action</label><select class="form-control" name="action"><option value="">All actions</option><?php foreach ($validActions as $item): ?><option value="<?= $item ?>" <?= $action === $item ? 'selected' : '' ?>><?= htmlspecialchars(ucwords(str_replace('_', ' ', $item))) ?></option><?php endforeach; ?></select></div>
        <div class="form-group col-md-2"><label>From</label><input class="form-control" type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>"></div><div class="form-group col-md-2"><label>To</label><input class="form-control" type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>"></div><div class="form-group col-md-2"><button class="btn btn-primary" type="submit">Filter</button> <a class="btn btn-light" href="activity">Clear</a></div>
    </form></div></div>
    <div class="card shadow-sm mb-4"><div class="card-header font-weight-bold">Daily totals by user</div><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr><th>Date</th><th>User</th><th>Activities</th></tr></thead><tbody><?php if (!$daily): ?><tr><td colspan="3" class="text-muted text-center py-3">No activity recorded for these filters.</td></tr><?php endif; ?><?php foreach ($daily as $row): ?><tr><td><a href="activity?<?= htmlspecialchars(http_build_query(['user_id' => (int)$row['actor_admin_id'], 'date_from' => $row['activity_date'], 'date_to' => $row['activity_date']])) ?>"><?= htmlspecialchars($row['activity_date']) ?></a></td><td><a href="activity?user_id=<?= (int)$row['actor_admin_id'] ?>"><?= htmlspecialchars($row['actor_name']) ?></a></td><td><?= number_format((int)$row['total']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
    <div class="card shadow-sm"><div class="card-header font-weight-bold">Activity details</div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Date / time</th><th>User</th><th>Module</th><th>Action</th><th>Record</th></tr></thead><tbody>
    <?php if (!$activities): ?><tr><td colspan="5" class="text-center text-muted py-4">No activity has been recorded yet.</td></tr><?php endif; ?>
    <?php foreach ($activities as $entry): ?><tr><td><?= htmlspecialchars(date('d M Y H:i', strtotime($entry['created_at']))) ?></td><td><?= htmlspecialchars($entry['actor_name']) ?></td><td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $entry['module']))) ?></td><td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $entry['action']))) ?></td><td><?php if ($entry['record_id'] !== null && $entry['module'] === 'users'): ?><a href="users?edit=<?= (int)$entry['record_id'] ?>"><?= htmlspecialchars($entry['record_title'] ?: ('User #' . $entry['record_id'])) ?></a><?php elseif ($entry['record_id'] !== null && in_array($entry['module'], ['bookings','tours','destinations','gallery','testimonials','faqs','inquiries','quotes','pages'], true)): ?><a href="activity-record?module=<?= rawurlencode($entry['module']) ?>&amp;id=<?= (int)$entry['record_id'] ?>"><?= htmlspecialchars($entry['record_title'] ?: ('Record #' . $entry['record_id'])) ?></a><?php else: ?><?= htmlspecialchars($entry['record_title'] ?: '—') ?><?php endif; ?></td></tr><?php endforeach; ?>
    </tbody></table></div><div class="card-footer d-flex justify-content-between"><span class="text-muted">Showing <?= $total ? $offset + 1 : 0 ?>–<?= min($offset + $perPage, $total) ?> of <?= number_format($total) ?></span><div><?php if ($page > 1): $pageBase['page'] = $page - 1; ?><a class="btn btn-sm btn-outline-secondary" href="activity?<?= htmlspecialchars(http_build_query($pageBase)) ?>">Previous</a><?php endif; ?> <?php if ($page < $pageCount): $pageBase['page'] = $page + 1; ?><a class="btn btn-sm btn-outline-secondary" href="activity?<?= htmlspecialchars(http_build_query($pageBase)) ?>">Next</a><?php endif; ?></div></div></div>
</div></body></html>
