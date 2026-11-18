<div class="flex items-center justify-between mb-6 gap-3">
    <h1 class="text-2xl font-bold text-gray-900">Prijaté e-faktúry</h1>
    <?php if ($peppolOn): ?>
    <form method="POST" action="/prijate/sync">
        <button type="submit" class="inline-flex items-center gap-1 bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg text-sm transition-colors cursor-pointer">
            <span class="material-icons" style="font-size:18px">sync</span> Stiahnuť nové
        </button>
    </form>
    <?php endif; ?>
</div>

<?php if (!$peppolOn): ?>
    <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-4 mb-4 text-sm">
        Poštár nie je nastavený – doplň PEPPOL_API_URL, PEPPOL_CLIENT_ID a PEPPOL_CLIENT_SECRET do .env.
    </div>
<?php endif; ?>

<?php if (empty($faktury)): ?>
    <div class="bg-white rounded-xl border border-gray-200 p-12 text-center text-gray-500">
        Zatiaľ žiadne prijaté e-faktúry.
    </div>
<?php else: ?>
    <!-- Desktop tabuľka -->
    <div class="hidden sm:block bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="text-left px-4 py-3 font-semibold text-gray-700">Číslo</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-700">Dodávateľ</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-700">Vystavená</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-700">Splatnosť</th>
                    <th class="text-right px-4 py-3 font-semibold text-gray-700">Suma</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($faktury as $f): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono font-semibold text-gray-900">
                        <?= e($f['cislo'] ?? '—') ?>
                        <?php if ($f['typ'] === 'dobropis'): ?><span class="ml-1 text-xs font-sans font-medium text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded">dobropis</span><?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-gray-700">
                        <?= e($f['dodavatel'] ?? $f['odosielatel_id']) ?>
                        <?php if ($f['dodavatel_ico']): ?><div class="text-xs text-gray-400">IČO <?= e($f['dodavatel_ico']) ?></div><?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-gray-600"><?= $f['datum_vystavenia'] ? date('d.m.Y', strtotime($f['datum_vystavenia'])) : '—' ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= $f['datum_splatnosti'] ? date('d.m.Y', strtotime($f['datum_splatnosti'])) : '—' ?></td>
                    <td class="px-4 py-3 text-right font-semibold text-gray-900">
                        <?= $f['suma'] !== null ? formatMoney((float)$f['suma']) . ' ' . e($f['mena'] ?? 'EUR') : '—' ?>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="/prijate/<?= $f['id'] ?>/xml" class="inline-flex align-middle" style="color:#64748b" title="Stiahnuť XML">
                            <span class="material-icons" style="font-size:20px">download</span>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile karty -->
    <div class="sm:hidden space-y-2">
        <?php foreach ($faktury as $f): ?>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="font-mono font-semibold text-gray-900"><?= e($f['cislo'] ?? '—') ?><?= $f['typ'] === 'dobropis' ? ' · dobropis' : '' ?></div>
                    <div class="text-gray-700 text-sm mt-0.5 truncate"><?= e($f['dodavatel'] ?? $f['odosielatel_id']) ?></div>
                </div>
                <div class="text-right shrink-0">
                    <div class="font-bold text-gray-900"><?= $f['suma'] !== null ? formatMoney((float)$f['suma']) . ' ' . e($f['mena'] ?? 'EUR') : '—' ?></div>
                    <div class="text-xs text-gray-400 mt-0.5"><?= $f['datum_vystavenia'] ? date('d.m.Y', strtotime($f['datum_vystavenia'])) : '' ?></div>
                </div>
            </div>
            <div class="flex justify-end mt-3 pt-3 border-t border-gray-100">
                <a href="/prijate/<?= $f['id'] ?>/xml" style="color:#64748b" title="Stiahnuť XML">
                    <span class="material-icons" style="font-size:22px">download</span>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
