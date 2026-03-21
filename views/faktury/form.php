<?php
$isCopy = $isCopy ?? false;
$isEdit = !empty($faktura) && !$isCopy && !empty($faktura['id']);
$action = $isEdit ? "/faktury/{$faktura['id']}/update" : '/faktury/store';

// Pripravíme JSON pre Alpine
$polozkyJson = json_encode(array_values(array_map(fn($p) => [
    'nazov'    => $p['nazov'],
    'mnozstvo' => (float)$p['mnozstvo'],
    'jednotka' => $p['jednotka'],
    'jcena'    => (float)$p['jednotkova_cena'],
], $polozky)), JSON_UNESCAPED_UNICODE);
if (empty($polozky)) $polozkyJson = '[]';
?>

<div class="flex items-center gap-3 mb-6">
    <a href="/faktury" class="text-gray-400 hover:text-gray-600">← Faktúry</a>
    <span class="text-gray-300">/</span>
    <h1 class="text-2xl font-bold text-gray-900">
        <?php if ($isEdit): ?>
            Upraviť faktúru <?= e($faktura['cislo_faktury']) ?>
        <?php elseif ($isCopy): ?>
            Kópia faktúry
        <?php else: ?>
            Nová faktúra
        <?php endif; ?>
    </h1>
</div>

<form method="POST" action="<?= $action ?>"
    x-data="invoiceForm()"
    x-init="init()"
    class="space-y-6">

    <!-- Hlavičkový riadok -->
    <div class="grid grid-cols-3 gap-6">

        <!-- Dodávateľ info -->
        <div class="col-span-1 bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h2 class="font-semibold text-gray-700 mb-3 text-sm uppercase tracking-wide">Dodávateľ</h2>
            <div class="text-sm text-gray-600 space-y-0.5">
                <div class="font-semibold text-gray-900"><?= e($dodavatel['nazov'] ?? '') ?></div>
                <div><?= e($dodavatel['ulica'] ?? '') ?></div>
                <div><?= e($dodavatel['psc'] ?? '') ?> <?= e($dodavatel['mesto'] ?? '') ?></div>
                <?php if ($dodavatel['ico']): ?><div>IČO: <?= e($dodavatel['ico']) ?></div><?php endif; ?>
                <?php if ($dodavatel['dic']): ?><div>DIČ: <?= e($dodavatel['dic']) ?></div><?php endif; ?>
                <?php if (!$dodavatel['dph_platca']): ?><div class="text-gray-400 mt-1">Nie je platiteľ DPH.</div><?php endif; ?>
            </div>
            <a href="/dodavatel" class="text-xs text-blue-500 hover:underline mt-3 inline-block">Upraviť</a>
        </div>

        <!-- Odberateľ select -->
        <div class="col-span-1 bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h2 class="font-semibold text-gray-700 mb-3 text-sm uppercase tracking-wide">Odberateľ *</h2>
            <select name="odberatel_id" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">— Vyberte odberateľa —</option>
                <?php foreach ($odberatelia as $o): ?>
                    <option value="<?= $o['id'] ?>"
                        <?= (($isEdit || $isCopy) && $faktura['odberatel_id'] == $o['id']) ? 'selected' : '' ?>>
                        <?= e($o['nazov']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <a href="/odberatelia/create" class="text-xs text-blue-500 hover:underline mt-3 inline-block">+ Nový odberateľ</a>
        </div>

        <!-- Číslo faktúry + dátumy -->
        <div class="col-span-1 bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h2 class="font-semibold text-gray-700 mb-3 text-sm uppercase tracking-wide">Doklad</h2>
            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Číslo faktúry *</label>
                    <input type="text" name="cislo_faktury" value="<?= e($nextNumber) ?>" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Dátum vystavenia *</label>
                    <input type="date" name="datum_vystavenia" value="<?= e($today) ?>" required
                        x-ref="datumV"
                        @change="updateSplatnost()"
                        class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Dátum dodania *</label>
                    <input type="date" name="datum_dodania" value="<?= e($today) ?>" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Splatnosť *</label>
                    <input type="date" name="datum_splatnosti" required
                        x-ref="splatnost"
                        :value="splatnostDate"
                        class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Variabilný symbol</label>
                    <input type="text" name="variabilny_symbol"
                        value="<?= e($isEdit ? $faktura['variabilny_symbol'] : $nextNumber) ?>"
                        class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
        </div>
    </div>

    <!-- Položky -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-semibold text-gray-700">Položky faktúry</h2>
            <button type="button" @click="addItem()"
                class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                + Pridať položku
            </button>
        </div>

        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left px-4 py-2 font-medium text-gray-600 w-8">#</th>
                    <th class="text-left px-4 py-2 font-medium text-gray-600">Názov / Popis</th>
                    <th class="text-left px-4 py-2 font-medium text-gray-600 w-24">Množstvo</th>
                    <th class="text-left px-4 py-2 font-medium text-gray-600 w-20">Jedn.</th>
                    <th class="text-right px-4 py-2 font-medium text-gray-600 w-28">Jedn. cena</th>
                    <th class="text-right px-4 py-2 font-medium text-gray-600 w-28">Spolu</th>
                    <th class="w-10 px-2"></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="(item, index) in items" :key="index">
                    <tr class="border-t border-gray-100">
                        <td class="px-4 py-2 text-gray-400" x-text="index + 1"></td>
                        <td class="px-4 py-2 relative">
                            <input type="text"
                                :name="'polozka_nazov[' + index + ']'"
                                x-model="item.nazov"
                                @input.debounce.300ms="fetchSuggestions(index, $event.target.value)"
                                @keydown.escape="closeSuggestions(index)"
                                @blur.debounce.200ms="closeSuggestions(index)"
                                placeholder="Názov položky..."
                                class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                autocomplete="off">
                            <!-- Autocomplete dropdown -->
                            <ul x-show="item.suggestions && item.suggestions.length > 0"
                                class="absolute z-10 top-full left-4 right-4 bg-white border border-gray-200 rounded-lg shadow-lg mt-0.5 max-h-48 overflow-y-auto">
                                <template x-for="(s, si) in item.suggestions" :key="si">
                                    <li @mousedown.prevent="pickSuggestion(index, s)"
                                        class="px-3 py-2 hover:bg-blue-50 cursor-pointer text-sm flex justify-between">
                                        <span x-text="s.nazov"></span>
                                        <span class="text-gray-400 text-xs" x-text="s.posledna_cena ? s.posledna_cena + ' EUR' : ''"></span>
                                    </li>
                                </template>
                            </ul>
                        </td>
                        <td class="px-4 py-2">
                            <input type="number" step="0.001" min="0"
                                :name="'polozka_mnozstvo[' + index + ']'"
                                x-model="item.mnozstvo"
                                @input="calcItem(index)"
                                @focus="$event.target.select()"
                                class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-right focus:outline-none focus:ring-2 focus:ring-blue-500"
                                style="-moz-appearance:textfield"
                                onwheel="this.blur()">
                        </td>
                        <td class="px-4 py-2">
                            <input type="text"
                                :name="'polozka_jednotka[' + index + ']'"
                                x-model="item.jednotka"
                                placeholder="ks"
                                class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </td>
                        <td class="px-4 py-2">
                            <input type="number" step="0.01" min="0"
                                :name="'polozka_jcena[' + index + ']'"
                                x-model="item.jcena"
                                @input="calcItem(index)"
                                @focus="$event.target.select()"
                                class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-right focus:outline-none focus:ring-2 focus:ring-blue-500"
                                style="-moz-appearance:textfield"
                                onwheel="this.blur()">
                        </td>
                        <td class="px-4 py-2 text-right font-semibold text-gray-800"
                            x-text="formatNum(item.spolu) + ' EUR'">
                        </td>
                        <td class="px-2 py-2 text-center">
                            <button type="button" @click="removeItem(index)"
                                class="text-gray-300 hover:text-red-500 text-lg leading-none">×</button>
                        </td>
                    </tr>
                </template>
            </tbody>
            <tfoot class="border-t-2 border-gray-200 bg-gray-50">
                <tr>
                    <td colspan="5" class="px-4 py-3 text-right font-semibold text-gray-700">Celková suma:</td>
                    <td colspan="2" class="px-4 py-3 text-right font-bold text-xl text-gray-900"
                        x-text="formatNum(total) + ' EUR'"></td>
                </tr>
            </tfoot>
        </table>

        <div class="px-5 py-3 border-t border-gray-100">
            <button type="button" @click="addItem()"
                class="text-sm text-gray-400 hover:text-blue-600">+ Pridať ďalšiu položku</button>
        </div>
    </div>

    <!-- Poznámka -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <label class="block text-sm font-medium text-gray-700 mb-2">Poznámka</label>
        <textarea name="poznamka" rows="2"
            placeholder="Voliteľná poznámka na faktúre..."
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"><?= e(($isEdit || $isCopy) ? ($faktura['poznamka'] ?? '') : '') ?></textarea>
    </div>

    <!-- Submit -->
    <div class="flex justify-between items-center">
        <a href="/faktury" class="text-gray-500 hover:text-gray-700">Zrušiť</a>
        <button type="submit"
            class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-8 py-2.5 rounded-lg transition-colors">
            <?= $isEdit ? 'Uložiť faktúru' : 'Vytvoriť faktúru' ?>
        </button>
    </div>
</form>

<script>
const INIT_ITEMS = <?= $polozkyJson ?>;
const DEFAULT_SPLATNOST = '<?= e($splatnost) ?>';

function invoiceForm() {
    return {
        items: [],
        splatnostDate: DEFAULT_SPLATNOST,

        init() {
            if (INIT_ITEMS.length > 0) {
                this.items = INIT_ITEMS.map(p => ({
                    nazov: p.nazov,
                    mnozstvo: p.mnozstvo,
                    jednotka: p.jednotka || 'ks',
                    jcena: p.jcena,
                    spolu: Math.round(p.mnozstvo * p.jcena * 100) / 100,
                    suggestions: [],
                }));
            } else {
                this.addItem();
            }
        },

        get total() {
            return Math.round(this.items.reduce((s, i) => s + (parseFloat(i.spolu) || 0), 0) * 100) / 100;
        },

        addItem() {
            this.items.push({ nazov: '', mnozstvo: 1, jednotka: 'ks', jcena: 0, spolu: 0, suggestions: [] });
        },

        removeItem(index) {
            if (this.items.length === 1) {
                this.items[index] = { nazov: '', mnozstvo: 1, jednotka: 'ks', jcena: 0, spolu: 0, suggestions: [] };
            } else {
                this.items.splice(index, 1);
            }
        },

        calcItem(index) {
            const item = this.items[index];
            item.spolu = Math.round((parseFloat(item.mnozstvo) || 0) * (parseFloat(item.jcena) || 0) * 100) / 100;
        },

        formatNum(val) {
            return Number(val).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        },

        updateSplatnost() {
            const datumV = this.$refs.datumV.value;
            if (!datumV) return;
            const d = new Date(datumV);
            d.setDate(d.getDate() + 14);
            this.splatnostDate = d.toISOString().split('T')[0];
        },

        async fetchSuggestions(index, q) {
            if (q.length < 2) {
                this.items[index].suggestions = [];
                return;
            }
            try {
                const res = await fetch('/api/suggestions?q=' + encodeURIComponent(q));
                const data = await res.json();
                this.items[index].suggestions = data;
            } catch (e) {
                this.items[index].suggestions = [];
            }
        },

        pickSuggestion(index, s) {
            this.items[index].nazov = s.nazov;
            if (s.posledna_cena !== null) {
                this.items[index].jcena = parseFloat(s.posledna_cena);
                this.calcItem(index);
            }
            this.items[index].suggestions = [];
        },

        closeSuggestions(index) {
            this.items[index].suggestions = [];
        },
    };
}
</script>
