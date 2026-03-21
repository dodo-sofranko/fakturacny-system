<?php
$isEdit = !empty($odberatel);
$pageTitle = $isEdit ? 'Upraviť odberateľa' : 'Nový odberateľ';
$action = $isEdit ? "/odberatelia/{$odberatel['id']}/update" : '/odberatelia/store';
?>
<div class="max-w-2xl">
    <div class="flex items-center gap-3 mb-6">
        <a href="/odberatelia" class="text-gray-400 hover:text-gray-600">← Odberatelia</a>
        <span class="text-gray-300">/</span>
        <h1 class="text-2xl font-bold text-gray-900"><?= $isEdit ? 'Upraviť odberateľa' : 'Nový odberateľ' ?></h1>
    </div>

    <form method="POST" action="<?= $action ?>" class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Názov *</label>
            <input type="text" name="nazov" value="<?= e($odberatel['nazov'] ?? '') ?>" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Ulica</label>
                <input type="text" name="ulica" value="<?= e($odberatel['ulica'] ?? '') ?>"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Mesto</label>
                <input type="text" name="mesto" value="<?= e($odberatel['mesto'] ?? '') ?>"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">PSČ</label>
                <input type="text" name="psc" value="<?= e($odberatel['psc'] ?? '') ?>"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Štát</label>
                <input type="text" name="stat" value="<?= e($odberatel['stat'] ?? 'Slovenská republika') ?>"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">IČO</label>
                <input type="text" name="ico" value="<?= e($odberatel['ico'] ?? '') ?>"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">DIČ</label>
                <input type="text" name="dic" value="<?= e($odberatel['dic'] ?? '') ?>"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">IČ DPH</label>
                <input type="text" name="ic_dph" value="<?= e($odberatel['ic_dph'] ?? '') ?>"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" value="<?= e($odberatel['email'] ?? '') ?>"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Telefón</label>
                <input type="text" name="telefon" value="<?= e($odberatel['telefon'] ?? '') ?>"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>

        <div class="flex justify-between pt-2">
            <a href="/odberatelia" class="text-gray-500 hover:text-gray-700 text-sm py-2">Zrušiť</a>
            <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-6 py-2 rounded-lg text-sm transition-colors">
                <?= $isEdit ? 'Uložiť zmeny' : 'Pridať odberateľa' ?>
            </button>
        </div>
    </form>
</div>
