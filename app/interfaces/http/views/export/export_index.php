<?php
$title = 'Export dữ liệu AI Training';
ob_start();
?>

<div class="mb-4">
    <a href="/settings" class="text-sm text-blue-600 hover:underline">← Cài đặt</a>
</div>

<!-- Header -->
<div class="bg-gray-800 rounded-2xl p-4 mb-4">
    <div class="flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-white/10 flex items-center justify-center text-2xl">🤖</div>
        <div>
            <div class="font-bold text-white">Export AI Training Data</div>
            <div class="text-xs text-gray-400">
                <?= $stats['date_range_start'] ?? 'N/A' ?> →
                <?= $stats['date_range_end']   ?? 'N/A' ?>
            </div>
        </div>
    </div>
</div>

<!-- Stats -->
<div class="grid grid-cols-3 gap-2 mb-4">
<?php $stat_items = [
    ['label'=>'Chu kỳ',      'value'=>$stats['cycles'],          'icon'=>'🔄'],
    ['label'=>'Snapshots',   'value'=>$stats['snapshots'],       'icon'=>'📊'],
    ['label'=>'Lượt cho ăn', 'value'=>$stats['care_feeds'],      'icon'=>'🌾'],
    ['label'=>'Ghi chết',    'value'=>$stats['care_deaths'],     'icon'=>'💀'],
    ['label'=>'Xuất bán',    'value'=>$stats['care_sales'],      'icon'=>'💰'],
    ['label'=>'Sensor',      'value'=>$stats['sensor_readings'], 'icon'=>'🌡️'],
]; ?>
<?php foreach ($stat_items as $s): ?>
<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-3 text-center">
    <div class="text-lg"><?= $s['icon'] ?></div>
    <div class="text-lg font-bold"><?= number_format($s['value']) ?></div>
    <div class="text-xs text-gray-400"><?= $s['label'] ?></div>
</div>
<?php endforeach; ?>
</div>

<!-- Export options -->
<div class="space-y-3 mb-4">

<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4">
    <div class="flex items-center gap-3 mb-3">
        <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center text-xl">📊</div>
        <div>
            <div class="text-sm font-semibold">Daily Snapshots</div>
            <div class="text-xs text-gray-400"><?= number_format($stats['snapshots']) ?> rows — FCR, tăng trưởng, mortality theo ngày</div>
            <div class="text-xs text-blue-500 mt-0.5">Dự đoán FCR · tăng trưởng · thời điểm bán</div>
        </div>
    </div>
    <div class="flex gap-2">
        <a href="/export/download?type=snapshots&format=csv" class="flex-1 text-center text-xs font-semibold py-2 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600">📥 CSV</a>
        <a href="/export/download?type=snapshots&format=json" class="flex-1 text-center text-xs font-semibold py-2 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-500">📥 JSON</a>
    </div>
</div>

<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4">
    <div class="flex items-center gap-3 mb-3">
        <div class="w-10 h-10 rounded-xl bg-green-50 dark:bg-green-900/30 flex items-center justify-center text-xl">🌾</div>
        <div>
            <div class="text-sm font-semibold">Care Events Timeline</div>
            <div class="text-xs text-gray-400"><?= number_format($stats['care_feeds'] + $stats['care_deaths'] + $stats['care_sales']) ?> rows — feed + death + sale merged</div>
            <div class="text-xs text-blue-500 mt-0.5">Tối ưu cám · cảnh báo chết sớm</div>
        </div>
    </div>
    <div class="flex gap-2">
        <a href="/export/download?type=care&format=csv" class="flex-1 text-center text-xs font-semibold py-2 rounded-xl bg-green-50 dark:bg-green-900/20 text-green-600">📥 CSV</a>
        <a href="/export/download?type=care&format=json" class="flex-1 text-center text-xs font-semibold py-2 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-500">📥 JSON</a>
    </div>
</div>


<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4">
    <div class="flex items-center gap-3 mb-3">
        <div class="w-10 h-10 rounded-xl bg-teal-50 dark:bg-teal-900/30 flex items-center justify-center text-xl">🌿</div>
        <div>
            <div class="text-sm font-semibold">ENV Readings</div>
            <div class="text-xs text-gray-400">Môi trường đầy đủ: temp, NH3, CO2, gió, mưa, lux + cycle_id</div>
            <div class="text-xs text-blue-500 mt-0.5">Anomaly detection · tương quan ENV vs FCR</div>
        </div>
    </div>
    <div class="flex gap-2">
        <a href="/export/download?type=env&format=csv" class="flex-1 text-center text-xs font-semibold py-2 rounded-xl bg-teal-50 dark:bg-teal-900/20 text-teal-600">📥 CSV</a>
        <a href="/export/download?type=env&format=json" class="flex-1 text-center text-xs font-semibold py-2 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-500">📥 JSON</a>
    </div>
</div>
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4">
    <div class="flex items-center gap-3 mb-3">
        <div class="w-10 h-10 rounded-xl bg-teal-50 dark:bg-teal-900/30 flex items-center justify-center text-xl">🌡️</div>
        <div>
            <div class="text-sm font-semibold">Sensor Readings</div>
            <div class="text-xs text-gray-400"><?= number_format($stats['sensor_readings']) ?> rows — nhiệt độ, độ ẩm, heat index</div>
            <div class="text-xs text-blue-500 mt-0.5">Anomaly detection · tương quan ENV vs tăng trưởng</div>
        </div>
    </div>
    <div class="flex gap-2">
        <a href="/export/download?type=sensor&format=csv" class="flex-1 text-center text-xs font-semibold py-2 rounded-xl bg-teal-50 dark:bg-teal-900/20 text-teal-600">📥 CSV</a>
        <a href="/export/download?type=sensor&format=json" class="flex-1 text-center text-xs font-semibold py-2 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-500">📥 JSON</a>
    </div>
</div>

<div class="bg-white dark:bg-gray-800 rounded-2xl border border-orange-100 dark:border-orange-900/30 p-4">
    <div class="flex items-center gap-3 mb-3">
        <div class="w-10 h-10 rounded-xl bg-orange-50 dark:bg-orange-900/30 flex items-center justify-center text-xl">🧠</div>
        <div>
            <div class="text-sm font-semibold">LLM Fine-tune Q&A</div>
            <div class="text-xs text-gray-400">JSONL — OpenAI/Gemini chat format</div>
            <div class="text-xs text-orange-500 mt-0.5">Fine-tune chatbot tư vấn chăn nuôi</div>
        </div>
    </div>
    <div class="flex gap-2">
        <a href="/export/download?type=qa&format=jsonl" class="flex-1 text-center text-xs font-semibold py-2 rounded-xl bg-orange-50 dark:bg-orange-900/20 text-orange-600">📥 JSONL</a>
    </div>
</div>

<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4">
    <div class="flex items-center gap-3 mb-3">
        <div class="w-10 h-10 rounded-xl bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-xl">📦</div>
        <div>
            <div class="text-sm font-semibold">Full Export</div>
            <div class="text-xs text-gray-400">Toàn bộ dữ liệu nested JSON — tất cả cycle + events</div>
            <div class="text-xs text-blue-500 mt-0.5">RAG · context injection</div>
        </div>
    </div>
    <div class="flex gap-2">
        <a href="/export/download?type=full&format=json" class="flex-1 text-center text-xs font-semibold py-2 rounded-xl bg-gray-100 dark:bg-gray-700 text-gray-600">📥 JSON</a>
    </div>
</div>

</div>

<!-- Colab guide -->
<div class="bg-blue-50 dark:bg-blue-900/20 rounded-2xl border border-blue-100 dark:border-blue-800 p-4">
    <div class="text-sm font-semibold text-blue-700 dark:text-blue-300 mb-2">🔬 Google Colab workflow</div>
    <div class="text-xs text-blue-600 dark:text-blue-400 space-y-1.5">
        <div><strong>FCR/Tăng trưởng:</strong> daily_snapshots.csv → XGBoost/LightGBM</div>
        <div><strong>Cảnh báo chết:</strong> care_events.csv → binary classification</div>
        <div><strong>Anomaly IoT:</strong> sensor_readings.csv → Isolation Forest / LSTM</div>
        <div><strong>Chatbot:</strong> training_qa.jsonl → fine-tune Gemini/GPT</div>
        <div class="pt-1 text-blue-500">💡 Càng nhiều chu kỳ thực tế → model càng chính xác</div>
    </div>
</div>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
?>
