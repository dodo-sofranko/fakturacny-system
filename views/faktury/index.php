<?php $pageTitle = 'Faktúry'; ?>

<div class="flex items-center justify-between mb-6" x-data>
    <h1 class="text-2xl font-bold text-gray-900">Faktúry</h1>
    <div class="flex items-center gap-3">
        <!-- Filter odberateľa -->
        <form method="GET" action="/faktury" class="flex items-center gap-2">
            <select name="odberatel_id" onchange="this.form.submit()"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Všetci odberatelia</option>
                <?php foreach ($odberatelia as $o): ?>
                    <option value="<?= $o['id'] ?>" <?= $filterOd == $o['id'] ? 'selected' : '' ?>>
                        <?= e($o['nazov']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <a href="/faktury/create"
            class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg text-sm transition-colors">
            + Nová faktúra
        </a>
    </div>
</div>

<?php if (empty($podlaRoku)): ?>
    <div class="bg-white rounded-xl border border-gray-200 p-12 text-center text-gray-500">
        Žiadne faktúry. <a href="/faktury/create" class="text-blue-600 hover:underline">Vytvorte prvú.</a>
    </div>
<?php else: ?>
    <?php foreach ($podlaRoku as $rok => $faktury): ?>
    <div class="mb-8">
        <h2 class="text-lg font-semibold text-gray-500 mb-3 flex items-center gap-2">
            <span class="inline-block w-8 h-0.5 bg-gray-300"></span>
            <?= e($rok) ?>
            <span class="text-sm font-normal text-gray-400">(<?= count($faktury) ?> faktúr)</span>
        </h2>

        <!-- Desktop tabuľka -->
        <div class="hidden sm:block bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-gray-700">Číslo</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-700">Odberateľ</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-700">Dátum vystavenia</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-700">Splatnosť</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-700">Suma</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($faktury as $f): ?>
                    <?php
                        $dnes = new DateTime();
                        $splatnost = new DateTime($f['datum_splatnosti']);
                        $expired = $splatnost < $dnes;
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <a href="/faktury/<?= $f['id'] ?>/pdf?v=<?= strtotime($f['pdf_generated_at'] ?? '') ?>" target="_blank"
                                class="font-mono font-semibold text-blue-600 hover:underline">
                                <?= e($f['cislo_faktury']) ?>
                            </a>
                        </td>
                        <td class="px-4 py-3 text-gray-700"><?= e($f['odberatel_nazov']) ?></td>
                        <td class="px-4 py-3 text-gray-600"><?= date('d.m.Y', strtotime($f['datum_vystavenia'])) ?></td>
                        <td class="px-4 py-3">
                            <span class="<?= $expired ? 'text-red-600 font-medium' : 'text-gray-600' ?>">
                                <?= date('d.m.Y', strtotime($f['datum_splatnosti'])) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right font-semibold text-gray-900">
                            <?= formatMoney((float)$f['celkova_suma']) ?> EUR
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="/faktury/<?= $f['id'] ?>/pdf?v=<?= strtotime($f['pdf_generated_at'] ?? '') ?>" target="_blank"
                                class="inline-flex text-red-400 hover:text-red-600 align-middle" title="Otvoriť PDF">
                                <span class="material-icons" style="font-size:20px">picture_as_pdf</span>
                            </a>
                            <a href="/faktury/<?= $f['id'] ?>/copy"
                                class="inline-flex text-green-500 hover:text-green-700 align-middle ml-1" title="Kopírovať faktúru">
                                <span class="material-icons" style="font-size:20px">content_copy</span>
                            </a>
                            <a href="/faktury/<?= $f['id'] ?>/edit"
                                class="inline-flex text-blue-500 hover:text-blue-700 align-middle ml-1" title="Upraviť">
                                <span class="material-icons" style="font-size:20px">edit</span>
                            </a>
                            <form method="POST" action="/faktury/<?= $f['id'] ?>/delete" class="inline"
                                onsubmit="return confirm('Naozaj vymazať faktúru <?= e($f['cislo_faktury']) ?>?')">
                                <button type="submit" class="inline-flex text-red-400 hover:text-red-600 align-middle ml-1 cursor-pointer" title="Vymazať">
                                    <span class="material-icons" style="font-size:20px">delete</span>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-gray-50 border-t border-gray-200">
                    <tr>
                        <td colspan="4" class="px-4 py-2 text-sm font-medium text-gray-500">Spolu za rok <?= e($rok) ?></td>
                        <td class="px-4 py-2 text-right font-bold text-gray-900">
                            <?= formatMoney(array_sum(array_column($faktury, 'celkova_suma'))) ?> EUR
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Mobile karty -->
        <div class="sm:hidden space-y-2">
            <?php foreach ($faktury as $f): ?>
            <?php
                $dnes = new DateTime();
                $splatnost = new DateTime($f['datum_splatnosti']);
                $expired = $splatnost < $dnes;
            ?>
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                <div class="flex items-start justify-between mb-2">
                    <div>
                        <a href="/faktury/<?= $f['id'] ?>/pdf?v=<?= strtotime($f['pdf_generated_at'] ?? '') ?>" target="_blank"
                            class="font-mono font-semibold text-blue-600 text-base">
                            <?= e($f['cislo_faktury']) ?>
                        </a>
                        <div class="text-gray-700 text-sm mt-0.5"><?= e($f['odberatel_nazov']) ?></div>
                    </div>
                    <div class="text-right">
                        <div class="font-bold text-gray-900"><?= formatMoney((float)$f['celkova_suma']) ?> EUR</div>
                        <div class="text-xs <?= $expired ? 'text-red-600 font-medium' : 'text-gray-400' ?> mt-0.5">
                            do <?= date('d.m.Y', strtotime($f['datum_splatnosti'])) ?>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-100">
                    <div class="text-xs text-gray-400"><?= date('d.m.Y', strtotime($f['datum_vystavenia'])) ?></div>
                    <div class="flex items-center gap-3">
                        <a href="/faktury/<?= $f['id'] ?>/pdf?v=<?= strtotime($f['pdf_generated_at'] ?? '') ?>" target="_blank"
                            style="color:#f87171" title="PDF">
                            <span class="material-icons" style="font-size:22px">picture_as_pdf</span>
                        </a>
                        <a href="/faktury/<?= $f['id'] ?>/copy"
                            style="color:#22c55e" title="Kopírovať">
                            <span class="material-icons" style="font-size:22px">content_copy</span>
                        </a>
                        <a href="/faktury/<?= $f['id'] ?>/edit"
                            style="color:#3b82f6" title="Upraviť">
                            <span class="material-icons" style="font-size:22px">edit</span>
                        </a>
                        <form method="POST" action="/faktury/<?= $f['id'] ?>/delete" class="inline"
                            onsubmit="return confirm('Naozaj vymazať faktúru <?= e($f['cislo_faktury']) ?>?')">
                            <button type="submit" style="color:#f87171" title="Vymazať">
                                <span class="material-icons" style="font-size:22px">delete</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <div class="text-right text-sm font-semibold text-gray-600 px-1 pt-1">
                Spolu: <?= formatMoney(array_sum(array_column($faktury, 'celkova_suma'))) ?> EUR
            </div>
        </div>

    </div>
    <?php endforeach; ?>
<?php endif; ?>
