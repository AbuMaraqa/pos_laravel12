<div>
    <div class="flex gap-4 mb-4">
        <select wire:model="reportType" class="border px-2 py-1">
            <option value="orders">الطلبات</option>
            <option value="sales">المبيعات</option>
            <option value="customers">العملاء</option>
        </select>

        <select wire:model="period" class="border px-2 py-1">
            <option value="month">هذا الشهر</option>
            <option value="last_month">الشهر الماضي</option>
            <option value="7days">آخر 7 أيام</option>
            <option value="year">هذا العام</option>
        </select>

        <input type="date" wire:model="dateMin" class="border px-2 py-1">
        <input type="date" wire:model="dateMax" class="border px-2 py-1">
    </div>

    <h2>الرسم البياني الأول (Bar)</h2>
    <canvas id="barChart" width="400" height="200"></canvas>

    <h2>الرسم البياني الثاني (Line)</h2>
    <canvas id="lineChart" width="400" height="200"></canvas>
</div>
<script>
    const labels = @json($labels);
    const values = @json($values);

    const barChart = new Chart(document.getElementById('barChart'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'عدد الطلبات حسب الحالة',
                data: values,
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    const lineChart = new Chart(document.getElementById('lineChart'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'عدد الطلبات حسب الحالة',
                data: values,
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
</script>