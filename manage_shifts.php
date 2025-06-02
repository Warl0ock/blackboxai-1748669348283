<?php
require_once 'includes/db.php';

// Function to get week dates
function getWeekDates($date) {
    $week_start = date('Y-m-d', strtotime('monday this week', strtotime($date)));
    $week_end = date('Y-m-d', strtotime('sunday this week', strtotime($date)));
    return ['start' => $week_start, 'end' => $week_end];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $week_dates = getWeekDates($_POST['week_start']);
        $shift_type = $_POST['shift_type'];

        // Begin transaction
        $pdo->beginTransaction();

        // Insert shift configuration
        $stmt = $pdo->prepare("
            INSERT INTO shift_configs (week_start_date, week_end_date, shift_type)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$week_dates['start'], $week_dates['end'], $shift_type]);
        $config_id = $pdo->lastInsertId();

        // Define shift schedules based on type
        $schedules = [];
        
        if ($shift_type == '2shift') {
            // Weekday schedules (Monday-Friday)
            $schedules[] = ['weekday', 1, '08:00:00', '16:00:00'];
            $schedules[] = ['weekday', 2, '00:00:00', '08:00:00'];
            
            // Saturday schedules
            $schedules[] = ['saturday', 1, '08:00:00', '14:00:00'];
            $schedules[] = ['saturday', 2, '14:00:00', '20:00:00'];
        } else { // 3shift
            // Weekday schedules (Monday-Friday)
            $schedules[] = ['weekday', 1, '08:00:00', '16:00:00'];
            $schedules[] = ['weekday', 2, '16:00:00', '00:00:00'];
            $schedules[] = ['weekday', 3, '00:00:00', '08:00:00'];
            
            // Saturday schedules
            $schedules[] = ['saturday', 1, '08:00:00', '16:00:00'];
            $schedules[] = ['saturday', 2, '16:00:00', '00:00:00'];
            $schedules[] = ['saturday', 3, '00:00:00', '20:00:00'];
        }

        // Insert shift schedules
        $stmt = $pdo->prepare("
            INSERT INTO shift_schedules 
            (shift_config_id, day_type, shift_number, start_time, end_time)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($schedules as $schedule) {
            $stmt->execute([
                $config_id,
                $schedule[0], // day_type
                $schedule[1], // shift_number
                $schedule[2], // start_time
                $schedule[3]  // end_time
            ]);
        }

        $pdo->commit();
        $message = "Jadwal shift berhasil disimpan.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// Get current week's configuration
$current_week = getWeekDates(date('Y-m-d'));
$stmt = $pdo->prepare("
    SELECT * FROM shift_configs 
    WHERE week_start_date = ? AND week_end_date = ?
");
$stmt->execute([$current_week['start'], $current_week['end']]);
$current_config = $stmt->fetch(PDO::FETCH_ASSOC);

// Get shift schedules if config exists
$current_schedules = [];
if ($current_config) {
    $stmt = $pdo->prepare("
        SELECT * FROM shift_schedules 
        WHERE shift_config_id = ?
        ORDER BY day_type, shift_number
    ");
    $stmt->execute([$current_config['id']]);
    $current_schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Shift</title>
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
                    <h1 class="text-3xl font-bold text-gray-900">Manajemen Shift</h1>
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

            <!-- Current Week Schedule -->
            <div class="bg-white shadow rounded-lg p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4">Jadwal Minggu Ini (<?php echo date('d/m/Y', strtotime($current_week['start'])); ?> - <?php echo date('d/m/Y', strtotime($current_week['end'])); ?>)</h2>
                
                <?php if ($current_config): ?>
                <div class="mb-4">
                    <p class="text-lg font-medium">Tipe Shift: <?php echo $current_config['shift_type'] == '2shift' ? '2 Shift' : '3 Shift'; ?></p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Weekday Schedule -->
                    <div class="border rounded-lg p-4">
                        <h3 class="text-lg font-medium mb-3">Jadwal Senin - Jumat</h3>
                        <div class="space-y-2">
                            <?php foreach ($current_schedules as $schedule): ?>
                                <?php if ($schedule['day_type'] == 'weekday'): ?>
                                <div class="flex justify-between items-center">
                                    <span class="font-medium">Shift <?php echo $schedule['shift_number']; ?></span>
                                    <span><?php echo date('H:i', strtotime($schedule['start_time'])); ?> - <?php echo date('H:i', strtotime($schedule['end_time'])); ?></span>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Saturday Schedule -->
                    <div class="border rounded-lg p-4">
                        <h3 class="text-lg font-medium mb-3">Jadwal Sabtu</h3>
                        <div class="space-y-2">
                            <?php foreach ($current_schedules as $schedule): ?>
                                <?php if ($schedule['day_type'] == 'saturday'): ?>
                                <div class="flex justify-between items-center">
                                    <span class="font-medium">Shift <?php echo $schedule['shift_number']; ?></span>
                                    <span><?php echo date('H:i', strtotime($schedule['start_time'])); ?> - <?php echo date('H:i', strtotime($schedule['end_time'])); ?></span>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <p class="text-gray-500">Belum ada konfigurasi shift untuk minggu ini.</p>
                <?php endif; ?>
            </div>

            <!-- Set New Schedule -->
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-xl font-semibold mb-4">Atur Jadwal Shift Baru</h2>
                <form method="POST" class="space-y-4">
                    <div>
                        <label for="week_start" class="block text-sm font-medium text-gray-700">Pilih Minggu</label>
                        <input type="date" name="week_start" id="week_start" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="shift_type" class="block text-sm font-medium text-gray-700">Tipe Shift</label>
                        <select name="shift_type" id="shift_type" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="2shift">2 Shift</option>
                            <option value="3shift">3 Shift</option>
                        </select>
                    </div>

                    <div class="pt-4">
                        <button type="submit"
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200">
                            Simpan Jadwal
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
