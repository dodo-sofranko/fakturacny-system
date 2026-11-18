<?php
/** Tlačidlá e-faktúry (Peppol) v zozname. Vstup: $f (riadok faktúry), $size (px ikony). */
$size ??= 20;
$st = $f['peppol_status'] ?? null;
$badge = [
    'ACCEPTED'      => ['mark_email_read', '#16a34a', 'Odoslané cez Peppol'],
    'UNDELIVERABLE' => ['report', '#d97706', 'Odberateľ nie je v sieti Peppol'],
    'REJECTED'      => ['error', '#dc2626', 'Poštár odmietol'],
    'ERROR'         => ['error', '#dc2626', 'Chyba pri odosielaní'],
][$st] ?? null;
$tip = $badge ? $badge[2] . (!empty($f['peppol_sent_at']) ? ' ' . date('d.m.Y H:i', strtotime($f['peppol_sent_at'])) : '') . (!empty($f['peppol_detail']) ? ' – ' . $f['peppol_detail'] : '') : '';
?>
<a href="/faktury/<?= $f['id'] ?>/ubl" class="inline-flex align-middle ml-1" style="color:#64748b" title="Stiahnuť e-faktúru (XML)">
    <span class="material-icons" style="font-size:<?= $size ?>px">code</span>
</a>
<?php if ($peppolOn): ?>
<form method="POST" action="/faktury/<?= $f['id'] ?>/peppol" class="inline"
    onsubmit="return confirm('<?= $st === 'ACCEPTED' ? 'Faktúra už bola odoslaná. Poslať znova (len ak si ju upravil)?' : 'Odoslať e-faktúru ' . e($f['cislo_faktury']) . ' odberateľovi cez Peppol?' ?>')">
    <button type="submit" class="inline-flex align-middle ml-1 cursor-pointer" style="color:<?= $badge[1] ?? '#6366f1' ?>"
        title="<?= e($tip ?: 'Odoslať e-faktúru cez Peppol') ?>">
        <span class="material-icons" style="font-size:<?= $size ?>px"><?= $badge[0] ?? 'send' ?></span>
    </button>
</form>
<?php elseif ($badge): ?>
<span class="inline-flex align-middle ml-1" style="color:<?= $badge[1] ?>" title="<?= e($tip) ?>">
    <span class="material-icons" style="font-size:<?= $size ?>px"><?= $badge[0] ?></span>
</span>
<?php endif; ?>
