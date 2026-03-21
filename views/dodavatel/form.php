<?php $pageTitle = 'Môj účet'; ?>
<div class="max-w-2xl">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Môj účet (Dodávateľ)</h1>

    <form method="POST" action="/dodavatel/update" enctype="multipart/form-data" class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Meno / Názov firmy *</label>
            <input type="text" name="nazov" value="<?= e($dodavatel['nazov'] ?? '') ?>" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Ulica</label>
                <input type="text" name="ulica" value="<?= e($dodavatel['ulica'] ?? '') ?>"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Mesto</label>
                <input type="text" name="mesto" value="<?= e($dodavatel['mesto'] ?? '') ?>"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">PSČ</label>
                <input type="text" name="psc" value="<?= e($dodavatel['psc'] ?? '') ?>"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">IČO</label>
                <input type="text" name="ico" value="<?= e($dodavatel['ico'] ?? '') ?>"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">DIČ</label>
                <input type="text" name="dic" value="<?= e($dodavatel['dic'] ?? '') ?>"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">IČ DPH</label>
                <input type="text" name="ic_dph" value="<?= e($dodavatel['ic_dph'] ?? '') ?>"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="flex items-end pb-2">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="dph_platca" value="1" <?= !empty($dodavatel['dph_platca']) ? 'checked' : '' ?>
                        class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="text-sm font-medium text-gray-700">Platiteľ DPH</span>
                </label>
            </div>
        </div>

        <hr class="border-gray-200">

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">IBAN</label>
            <input type="text" name="iban" value="<?= e($dodavatel['iban'] ?? '') ?>"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">SWIFT / BIC</label>
                <input type="text" name="swift" value="<?= e($dodavatel['swift'] ?? '') ?>"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Banka</label>
                <input type="text" name="banka" value="<?= e($dodavatel['banka'] ?? '') ?>"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" value="<?= e($dodavatel['email'] ?? '') ?>"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Telefón</label>
                <input type="text" name="telefon" value="<?= e($dodavatel['telefon'] ?? '') ?>"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Text pod podpisom (napr. register)</label>
            <input type="text" name="podpis_text" value="<?= e($dodavatel['podpis_text'] ?? '') ?>"
                placeholder="Zapísaný v živnostenskom registri..."
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div x-data="{ preview: null }">
            <label class="block text-sm font-medium text-gray-700 mb-1">Obrázok podpisu (PNG/JPG)</label>
            <?php if (!empty($dodavatel['podpis_png'])): ?>
                <div class="mb-2">
                    <img src="/dodavatel/podpis-img" alt="Aktuálny podpis"
                        class="max-h-16 border border-gray-200 rounded p-1 bg-gray-50">
                    <p class="text-xs text-gray-400 mt-1">Aktuálny podpis — nahrajte nový pre zmenu</p>
                </div>
            <?php endif; ?>
            <input type="file" name="podpis_png" accept="image/png,image/jpeg,image/webp"
                @change="preview = URL.createObjectURL($event.target.files[0])"
                class="w-full text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
            <img x-show="preview" :src="preview" class="mt-2 max-h-16 border border-gray-200 rounded p-1 bg-gray-50">
        </div>

        <div class="flex justify-end pt-2">
            <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-6 py-2 rounded-lg text-sm transition-colors">
                Uložiť
            </button>
        </div>
    </form>
</div>
