<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once __DIR__ . '/../includes/admin-auth.php';

$db = db();
requireAdminOwner();
$module = (string)($_GET['module'] ?? '');
$id = max(0, (int)($_GET['id'] ?? 0));
$record = null;
$fields = [];
$queries = [
    'tours' => ["SELECT id, title, slug, status, duration, price, country, description, created_at, updated_at FROM tour_packages WHERE id = ?", ['id'=>'ID','title'=>'Title','slug'=>'Slug','status'=>'Status','duration'=>'Duration','price'=>'Price','country'=>'Country','description'=>'Description','created_at'=>'Created','updated_at'=>'Updated']],
    'destinations' => ["SELECT id, name, country, slug, status, short_description, description, created_at, updated_at FROM destinations WHERE id = ?", ['id'=>'ID','name'=>'Name','country'=>'Country','slug'=>'Slug','status'=>'Status','short_description'=>'Summary','description'=>'Description','created_at'=>'Created','updated_at'=>'Updated']],
    'gallery' => ["SELECT id, title, description, category, location, image, status, created_at FROM gallery WHERE id = ?", ['id'=>'ID','title'=>'Title','description'=>'Description','category'=>'Category','location'=>'Location','image'=>'Image path','status'=>'Status','created_at'=>'Created']],
    'testimonials' => ["SELECT id, customer_name, customer_title, review, rating, country, status, created_at FROM testimonials WHERE id = ?", ['id'=>'ID','customer_name'=>'Customer','customer_title'=>'Title','review'=>'Review','rating'=>'Rating','country'=>'Country','status'=>'Status','created_at'=>'Created']],
    'faqs' => ["SELECT id, question, answer, category, status, created_at FROM faq WHERE id = ?", ['id'=>'ID','question'=>'Question','answer'=>'Answer','category'=>'Category','status'=>'Status','created_at'=>'Created']],
    'pages' => ["SELECT id, title, slug, content, meta_title, meta_description, status, created_at, updated_at FROM pages WHERE id = ?", ['id'=>'ID','title'=>'Title','slug'=>'Slug','content'=>'Content','meta_title'=>'Meta title','meta_description'=>'Meta description','status'=>'Status','created_at'=>'Created','updated_at'=>'Updated']],
    'bookings' => ["SELECT id, booking_reference, full_name, email, phone, travel_date, guests, status, payment_status, created_at, updated_at FROM bookings WHERE id = ?", ['id'=>'ID','booking_reference'=>'Reference','full_name'=>'Customer','email'=>'Email','phone'=>'Phone','travel_date'=>'Travel date','guests'=>'Guests','status'=>'Status','payment_status'=>'Payment status','created_at'=>'Created','updated_at'=>'Updated']],
    'inquiries' => ["SELECT id, full_name, email, phone, subject, status, created_at FROM inquiries WHERE id = ?", ['id'=>'ID','full_name'=>'Customer','email'=>'Email','phone'=>'Phone','subject'=>'Subject','status'=>'Status','created_at'=>'Received']],
    'quotes' => ["SELECT id, quote_number, status, subtotal, tax_amount, discount, total, currency, valid_until, created_at, updated_at FROM quotes WHERE id = ?", ['id'=>'ID','quote_number'=>'Quote number','status'=>'Status','subtotal'=>'Subtotal','tax_amount'=>'Tax','discount'=>'Discount','total'=>'Total','currency'=>'Currency','valid_until'=>'Valid until','created_at'=>'Created','updated_at'=>'Updated']],
];
if ($id > 0 && isset($queries[$module])) {
    [$sql, $fields] = $queries[$module];
    $record = $db->fetchOne($sql, [$id]);
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Activity Record - Kizza Tours Admin</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css" rel="stylesheet"><link href="../templates/assets/css/ruang-admin.min.css" rel="stylesheet"></head><body><div class="container py-4">
<div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h3 mb-0">Activity record</h1><div class="text-muted"><?= htmlspecialchars(ucwords(str_replace('_',' ', $module))) ?> · #<?= $id ?></div></div><a href="activity" class="btn btn-outline-secondary">Back to activity</a></div>
<?php if (!isset($queries[$module])): ?><div class="alert alert-warning">This record type cannot be opened from the activity log.</div><?php elseif (!$record): ?><div class="alert alert-secondary">This record no longer exists. Its activity entry remains in the audit history.</div><?php else: ?><div class="card shadow-sm"><div class="card-body"><dl class="row mb-0"><?php foreach ($fields as $key => $label): ?><dt class="col-sm-3"><?= htmlspecialchars($label) ?></dt><dd class="col-sm-9" style="white-space:pre-wrap;word-break:break-word"><?= htmlspecialchars((string)($record[$key] ?? '—')) ?></dd><?php endforeach; ?></dl></div></div><?php endif; ?>
</div></body></html>
