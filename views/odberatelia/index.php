<?php $pageTitle = 'Odberatelia'; ?>
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Odberatelia</h1>
    <a href="/odberatelia/create"
        class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg text-sm transition-colors">
        + Nový odberateľ
    </a>
</div>

<?php if (empty($odberatelia)): ?>
    <div class="bg-white rounded-xl border border-gray-200 p-12 text-center text-gray-500">
        Žiadni odberatelia. <a href="/odberatelia/create" class="text-blue-600 hover:underline">Pridajte prvého.</a>
    </div>
<?php else: ?>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="text-left px-4 py-3 font-semibold text-gray-700">Názov</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-700">Adresa</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-700">IČO</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-700">DIČ</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($odberatelia as $o): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-900"><?= e($o['nazov']) ?></td>
                    <td class="px-4 py-3 text-gray-600">
                        <?= e($o['ulica']) ?><?= $o['ulica'] ? ', ' : '' ?><?= e($o['psc']) ?> <?= e($o['mesto']) ?>
                    </td>
                    <td class="px-4 py-3 text-gray-600"><?= e($o['ico']) ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= e($o['dic']) ?></td>
                    <td class="px-4 py-3 text-right">
                        <a href="/odberatelia/<?= $o['id'] ?>/edit" class="text-blue-600 hover:underline mr-3">Upraviť</a>
                        <form method="POST" action="/odberatelia/<?= $o['id'] ?>/delete" class="inline"
                            onsubmit="return confirm('Naozaj vymazať tohto odberateľa?')">
                            <button type="submit" class="text-red-500 hover:underline">Vymazať</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
