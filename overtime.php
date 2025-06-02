<?php
require_once 'includes/db.php';

// Get date range from query parameters or default to current month
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Get employee filter
$employee_filter = isset($_GET['employee_id']) ? $_GET['employee_id'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Get all employees for the filter dropdown
$stmt = $pdo->query("SELECT id, nik, name, status FROM employees WHERE status IN ('bonus', 'lembur') ORDER BY name");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build the overtime query
$query = "
    SELECT 
        o.*, 
        e.nik,
        e.name,
        e.status,
        e.location,
        a.time_in,
        a.time_out
    FROM overtime_records o
    JOIN employees e ON o.employee_id = e.id
    JOIN attendance_records a ON o.attendance_id = a.id
    WHERE o.date BETWEEN ? AND ?
";
$params = [$start_date, $end_date];

if ($employee_filter) {
    $query .= " AND o.employee_id = ?";
    $params[] = $employee_filter;
}

if ($status_filter) {
    $query .= " AND e.status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY o.date DESC, e.name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$overtime_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$total_hours = 0;
$total_amount = 0;
foreach ($overtime_records as $record) {
    $total_hours += $record['overtime_hours'];
    $total_amount += $record['total_amount'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Lembur</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center">
                    <h1 class="text-3xl font-bold text-gray-900">Laporan Lembur</h1>
                    <div class="space-x-4">
                        <a href="attendance.php" class="bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg transition duration-200">
                            Halaman Absensi
                        </a>
                        <a href="index.php" class="bg-gray-500 hover:bg-gray-600 text-white font-semibold py-2 px-4 rounded-lg transition duration-200">
                            Kembali ke Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <!-- Filter Form -->
            <div class="bg-white shadow rounded-lg p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700">Tanggal Mulai</label>
                        <input type="date" name="start_date" id="start_date" value="<?php echo $start_date; ?>"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="end_date" class="block text-sm font-medium text-gray-700">Tanggal Akhir</label>
                        <input type="date" name="end_date" id="end_date" value="<?php echo $end_date; ?>"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="employee_id" class="block text-sm font-medium text-gray-700">Karyawan</label>
                        <select name="employee_id" id="employee_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Semua Karyawan</option>
                            <?php foreach ($employees as $employee): ?>
                            <option value="<?php echo $employee['id']; ?>" <?php echo $employee_filter == $employee['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($employee['nik'] . ' - ' . $employee['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                        <select name="status" id="status"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Semua Status</option>
                            <option value="bonus" <?php echo $status_filter == 'bonus' ? 'selected' : ''; ?>>Bonus</option>
                            <option value="lembur" <?php echo $status_filter == 'lembur' ? 'selected' : ''; ?>>Lembur</option>
                        </select>
                    </div>
                    <div class="md:col-span-4">
                        <button type="submit"
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200">
                            Tampilkan
                        </button>
                    </div>
                </form>
            </div>

            <!-- Summary -->
            <div class="bg-white shadow rounded-lg p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="text-center p-4 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-600">Total Jam Lembur</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($total_hours, 2); ?> Jam</p>
                    </div>
                    <div class="text-center p-4 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-600">Total Nominal Lembur</p>
                        <p class="text-2xl font-bold text-gray-900">Rp <?php echo number_format($total_amount, 0, ',', '.'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Overtime Records Table -->
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">NIK</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lokasi</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jam Kerja</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jam Lembur</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tarif/Jam</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($overtime_records as $record): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo date('d/m/Y', strtotime($record['date'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($record['nik']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($record['name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($record['status']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($record['location']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php 
                                    echo date('H:i', strtotime($record['time_in'])) . ' - ' . 
                                         date('H:i', strtotime($record['time_out']));
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo number_format($record['overtime_hours'], 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    Rp <?php echo number_format($record['overtime_rate'], 0, ',', '.'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    Rp <?php echo number_format($record['total_amount'], 0, ',', '.'); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($overtime_records)): ?>
                            <tr>
                                <td colspan="9" class="px-6 py-4 text-center text-gray-500">
                                    Tidak ada data lembur untuk periode yang dipilih.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
