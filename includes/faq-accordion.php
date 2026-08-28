<?php
// Reusable FAQ accordion partial.
// Expects: $faqs (array of arrays with 'question' and 'answer'; optional 'id')
// Accepts an optional $n (unique namespace) to avoid duplicate DOM ids across pages.
if (!isset($faqs) || empty($faqs)) return;
$faqNs = isset($n) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $n) : 'faq';
$questionIds = [];
$uniqueFaqs = [];
foreach ($faqs as $f) {
    $q = trim($f['question'] ?? '');
    if ($q === '') continue;
    $key = mb_strtolower($q);
    if (isset($questionIds[$key])) continue;
    $questionIds[$key] = true;
    $uniqueFaqs[] = $f;
}
$usedIds = [];
$seq = 0;
foreach ($uniqueFaqs as $f):
    $seq++;
    $baseId = isset($f['id']) ? intval($f['id']) : $seq;
    while (isset($usedIds[$baseId])) { $baseId++; $seq++; }
    $usedIds[$baseId] = true;
    $faqId = $baseId;
    $domId = $faqNs . 'Collapse' . $faqId;
    $headId = $faqNs . 'Head' . $faqId;
    $idx = $seq - 1;
?>
<div class="accordion-item">
    <h3 class="accordion-header" id="<?php echo $headId; ?>">
        <button class="accordion-button <?php echo $idx > 0 ? 'collapsed' : ''; ?>" type="button"
            data-bs-toggle="collapse"
            data-bs-target="#<?php echo $domId; ?>"
            aria-expanded="<?php echo $idx === 0 ? 'true' : 'false'; ?>"
            aria-controls="<?php echo $domId; ?>">
            <span class="faq-question-text"><?php echo htmlspecialchars($f['question']); ?></span>
        </button>
    </h3>
    <div id="<?php echo $domId; ?>" class="accordion-collapse collapse <?php echo $idx === 0 ? 'show' : ''; ?>" aria-labelledby="<?php echo $headId; ?>" data-bs-parent="#<?php echo $faqNs; ?>Accordion">
        <div class="accordion-body"><?php echo nl2br(htmlspecialchars($f['answer'])); ?></div>
    </div>
</div>
<?php endforeach; ?>
