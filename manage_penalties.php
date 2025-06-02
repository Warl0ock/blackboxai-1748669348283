<?php
require_once 'includes/db.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $penalty_amount = floatval($_POST['penalty_amount']);
        $grace_period = intval($_POST['grace_period']);
        $max_late_hours = intval($_POST['max_late_hours']);

        // Update configuration
        $stmt = $pdo->prepare("
            UPDATE late_penalties_config 
            SET penalty_amount = ?, 
                grace_period_minutes = ?, 
                max_late_hours = ?
            WHERE id = 1
        ");
        $stmt->execute([$penalty_amount, $grace_period, $max_late_hours]);
        
        $message = "Konfigurasi berhasil diperbarui.";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get current configuration
$stmt = $pdo->query("SELECT * FROM late_penalties_config LIMIT 1");
$config = $stmt->fetch(PDO::FETCH_ASSOC);

// Get late attendance summary for today
$today = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT 
        a.*,
        e.nik,
        e.name,
        e.position,
        s.start_time as shift_start
    FROM attendance_records a
    JOIN employees e ON a.employee_id = e.id
    LEFT JOIN shift_schedules s ON a.shift_schedule_id = s.id
    WHERE a.date = ? AND (a.late_minutes > 0 OR a.attendance_status = 'absent')
    ORDER BY a.late_minutes DESC
");
$stmt->execute([$today]);
$late_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Keterlambatan</title>
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
                    <h1 class="text-3xl font-bold text-gray-900">Manajemen Keterlambatan</h1>
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
            <!-- Alert Messages -->
            <?php if (isset($message)): ?>
            <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
            <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <!-- Penalty Configuration -->
            <div class="bg-white shadow rounded-lg p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4">Konfigurasi Potongan Keterlambatan</h2>
                <form method="POST" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="penalty_amount" class="block text-sm font-medium text-gray-700">
                                Nominal Potongan (Rp)
                            </label>
                            <input type="number" name="penalty_amount" id="penalty_amount" 
                                   value="<?php echo $config['penalty_amount']; ?>" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="grace_period" class="block text-sm font-medium text-gray-700">
                                Toleransi Keterlambatan (Menit)
                            </label>
                            <input type="number" name="grace_period" id="grace_period" 
                                   value="<?php echo $config['grace_period_minutes']; ?>" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="max_late_hours" class="block text-sm font-medium text-gray-700">
                                Batas Maksimal Terlambat (Jam)
                            </label>
                            <input type="number" name="max_late_hours" id="max_late_hours" 
                                   value="<?php echo $config['max_late_hours']; ?>" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200">
                            Simpan Konfigurasi
                        </button>
                    </div>
                </form>
            </div>

            <!-- Today's Late Attendance -->
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-xl font-semibold">Keterlambatan Hari Ini (<?php echo date('d/m/Y'); ?>)</h2>
                </div>
                
                <?php if (count($late_records) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">NIK</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jabatan</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jam Masuk</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Keterlambatan</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Potongan</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($late_records as $record): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($record['nik']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($record['name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($record['position']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $record['time_in'] ? date('H:i', strtotime($record['time_in'])) : '-'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php 
                                    if ($record['attendance_status'] == 'absent') {
                                        echo 'Tidak Hadir';
                                    } else {
                                        echo $record['late_minutes'] . ' menit';
                                    }
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php 
                                        echo match($record['attendance_status']) {
                                            'late' => 'bg-yellow-100 text-yellow-800',
                                            'absent' => 'bg-red-100 text-red-800',
                                            'leave' => 'bg-blue-100 text-blue-800',
                                            default => 'bg-green-100 text-green-800'
                                        };
                                        ?>">
                                        <?php 
                                        echo match($record['attendance_status']) {
                                            'late' => 'Terlambat',
                                            'absent' => 'Tidak Hadir',
                                            'leave' => 'Cuti',
                                            default => 'Hadir'
                                        };
                                        ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php 
                                    if ($record['late_penalty'] > 0) {
                                        echo 'Rp ' . number_format($record['late_penalty'], 0, ',', '.');
                                    } else if ($record['attendance_status'] == 'absent') {
                                        if ($record['leave_deducted']) {
                                            echo 'Potong Cuti';
                                        } else if ($record['salary_deducted']) {
                                            echo 'Potong Gaji';
                                        } else {
                                            echo '-';
                                        }
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <p class="text-gray-500">Tidak ada keterlambatan hari ini.</p>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
