<?php
require_once 'includes/db.php';

// Get current date and time
$current_date = date('Y-m-d');
$current_time = date('H:i:s');
$current_datetime = date('Y-m-d H:i:s');

// Function to check if today is a holiday
function isHoliday($pdo, $date) {
    $stmt = $pdo->prepare("SELECT * FROM holidays WHERE date = ?");
    $stmt->execute([$date]);
    return $stmt->fetch() !== false;
}

// Function to get employee's shift schedule
function getEmployeeShift($pdo, $employee_id, $date) {
    $stmt = $pdo->prepare("
        SELECT ss.* 
        FROM shift_schedules ss
        JOIN shift_configs sc ON ss.shift_config_id = sc.id
        WHERE ? BETWEEN sc.week_start_date AND sc.week_end_date
        AND (
            (DAYOFWEEK(?) BETWEEN 2 AND 6 AND ss.day_type = 'weekday')
            OR 
            (DAYOFWEEK(?) = 7 AND ss.day_type = 'saturday')
        )
    ");
    $stmt->execute([$date, $date, $date]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to check late attendance and calculate penalties
function calculateLateAttendance($pdo, $time_in, $shift_start) {
    // Get late penalty configuration
    $stmt = $pdo->query("SELECT * FROM late_penalties_config LIMIT 1");
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $time_in_timestamp = strtotime($time_in);
    $shift_start_timestamp = strtotime($shift_start);
    
    // Calculate minutes late
    $minutes_late = max(0, round(($time_in_timestamp - $shift_start_timestamp) / 60));
    
    // If within grace period, consider as on time
    if ($minutes_late <= $config['grace_period_minutes']) {
        return ['late_minutes' => 0, 'penalty' => 0, 'status' => 'present'];
    }
    
    // If late more than max hours, consider as absent
    if ($minutes_late > ($config['max_late_hours'] * 60)) {
        return ['late_minutes' => $minutes_late, 'penalty' => 0, 'status' => 'absent'];
    }
    
    // Calculate penalty
    return [
        'late_minutes' => $minutes_late,
        'penalty' => $config['penalty_amount'],
        'status' => 'late'
    ];
}

// Function to handle absence
function handleAbsence($pdo, $employee_id, $attendance_id, $date) {
    // Check if employee has leave quota
    $stmt = $pdo->prepare("SELECT leave_quota, daily_salary FROM employees WHERE id = ?");
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($employee['leave_quota'] > 0) {
        // Deduct from leave quota
        $pdo->prepare("UPDATE employees SET leave_quota = leave_quota - 1 WHERE id = ?")->execute([$employee_id]);
        $pdo->prepare("UPDATE attendance_records SET leave_deducted = TRUE WHERE id = ?")->execute([$attendance_id]);
        
        // Record leave usage
        $stmt = $pdo->prepare("
            INSERT INTO leave_records (employee_id, date, attendance_record_id, leave_type)
            VALUES (?, ?, ?, 'annual')
        ");
        $stmt->execute([$employee_id, $date, $attendance_id]);
    } else {
        // Deduct from salary
        $pdo->prepare("UPDATE attendance_records SET salary_deducted = TRUE WHERE id = ?")->execute([$attendance_id]);
        
        // Record salary deduction
        $stmt = $pdo->prepare("
            INSERT INTO leave_records (employee_id, date, attendance_record_id, leave_type)
            VALUES (?, ?, ?, 'salary_deduction')
        ");
        $stmt->execute([$employee_id, $date, $attendance_id]);
    }
}

// Function to calculate overtime hours
function calculateOvertime($employee, $time_in, $time_out, $shift_schedule, $is_holiday) {
    if ($employee['status'] == 'non') return 0;

    $work_hours = (strtotime($time_out) - strtotime($time_in)) / 3600;
    
    if ($is_holiday) {
        return $work_hours; // Full hours count as overtime on holidays
    }

    // Regular workday overtime calculation
    $min_hours = (date('N', strtotime($time_in)) == 6) ? 4 : 5; // 4 hours for Saturday, 5 for weekdays
    $shift_end = strtotime($shift_schedule['end_time']);
    $overtime_start = $shift_end + 3600; // 1 hour after shift ends

    if ($work_hours < $min_hours) return 0;

    $overtime = (strtotime($time_out) - $overtime_start) / 3600;
    return max(0, $overtime);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $employee_id = $_POST['employee_id'];
    $action = $_POST['action']; // 'in' or 'out'

    try {
        // Get employee details
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
        $stmt->execute([$employee_id]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$employee) {
            throw new Exception("Karyawan tidak ditemukan.");
        }

        $is_holiday = isHoliday($pdo, $current_date);
        $shift_schedule = getEmployeeShift($pdo, $employee_id, $current_date);

        if ($action == 'in') {
            // Get shift start time
            $shift_start = date('Y-m-d ') . $shift_schedule['start_time'];
            
            // Calculate late attendance
            $late_info = calculateLateAttendance($pdo, $current_datetime, $shift_start);
            
            // Record attendance in
            $stmt = $pdo->prepare("
                INSERT INTO attendance_records 
                (employee_id, date, time_in, is_holiday, shift_schedule_id, late_minutes, late_penalty, attendance_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $employee_id, 
                $current_date, 
                $current_datetime, 
                $is_holiday, 
                $shift_schedule['id'] ?? null,
                $late_info['late_minutes'],
                $late_info['penalty'],
                $late_info['status']
            ]);

            // If marked as absent due to excessive lateness, handle absence
            if ($late_info['status'] == 'absent') {
                handleAbsence($pdo, $employee_id, $pdo->lastInsertId(), $current_date);
                $message = "Terlambat lebih dari batas maksimal. Dianggap tidak hadir.";
            } else {
                $message = $late_info['late_minutes'] > 0 
                    ? "Absen masuk berhasil dicatat. Terlambat " . $late_info['late_minutes'] . " menit."
                    : "Absen masuk berhasil dicatat.";
            }
        } else {
            // Record attendance out and calculate overtime if applicable
            $stmt = $pdo->prepare("
                UPDATE attendance_records 
                SET time_out = ? 
                WHERE employee_id = ? AND date = ? AND time_out IS NULL
            ");
            $stmt->execute([$current_datetime, $employee_id, $current_date]);

            if ($stmt->rowCount() > 0 && ($employee['status'] == 'bonus' || $employee['status'] == 'lembur')) {
                // Get the attendance record
                $stmt = $pdo->prepare("
                    SELECT * FROM attendance_records 
                    WHERE employee_id = ? AND date = ?
                ");
                $stmt->execute([$employee_id, $current_date]);
                $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

                // Calculate overtime
                $overtime_hours = calculateOvertime(
                    $employee,
                    $attendance['time_in'],
                    $current_datetime,
                    $shift_schedule,
                    $is_holiday
                );

                if ($overtime_hours > 0 && $employee['status'] == 'lembur') {
                    // Get overtime rate
                    $stmt = $pdo->prepare("
                        SELECT rate_per_hour 
                        FROM overtime_rates 
                        WHERE location = ? AND day_type = ?
                    ");
                    $day_type = $is_holiday ? 'holiday' : 'workday';
                    $stmt->execute([$employee['location'], $day_type]);
                    $rate = $stmt->fetch(PDO::FETCH_ASSOC)['rate_per_hour'];

                    // Record overtime
                    $total_amount = $overtime_hours * $rate;
                    $stmt = $pdo->prepare("
                        INSERT INTO overtime_records 
                        (attendance_id, employee_id, date, overtime_hours, overtime_rate, is_holiday, total_amount)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $attendance['id'],
                        $employee_id,
                        $current_date,
                        $overtime_hours,
                        $rate,
                        $is_holiday,
                        $total_amount
                    ]);
                }
            }
            $message = "Absen pulang berhasil dicatat.";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get all employees for the dropdown
$stmt = $pdo->query("SELECT id, nik, name, status, position FROM employees ORDER BY name");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Absensi Karyawan</title>
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
                    <h1 class="text-3xl font-bold text-gray-900">Sistem Absensi</h1>
                    <a href="index.php" class="bg-gray-500 hover:bg-gray-600 text-white font-semibold py-2 px-4 rounded-lg transition duration-200">
                        Kembali ke Dashboard
                    </a>
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

            <!-- Attendance Form -->
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-xl font-semibold mb-4">Absen Masuk/Keluar</h2>
                <form method="POST" class="space-y-4">
                    <div>
                        <label for="employee_id" class="block text-sm font-medium text-gray-700">Pilih Karyawan</label>
                        <select name="employee_id" id="employee_id" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Pilih Karyawan...</option>
                            <?php foreach ($employees as $employee): ?>
                            <option value="<?php echo $employee['id']; ?>">
                                <?php echo htmlspecialchars($employee['nik'] . ' - ' . $employee['name'] . 
                                      ' (' . $employee['position'] . ' - ' . $employee['status'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex space-x-4">
                        <button type="submit" name="action" value="in"
                                class="flex-1 bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200">
                            Absen Masuk
                        </button>
                        <button type="submit" name="action" value="out"
                                class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200">
                            Absen Pulang
                        </button>
                    </div>
                </form>
            </div>

            <!-- Today's Attendance -->
            <div class="mt-6 bg-white shadow rounded-lg p-6">
                <h2 class="text-xl font-semibold mb-4">Absensi Hari Ini (<?php echo date('d/m/Y'); ?>)</h2>
                <?php
                $stmt = $pdo->prepare("
                    SELECT a.*, e.nik, e.name, e.position, e.status
                    FROM attendance_records a
                    JOIN employees e ON a.employee_id = e.id
                    WHERE a.date = ?
                    ORDER BY a.time_in DESC
                ");
                $stmt->execute([$current_date]);
                $today_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <?php if (count($today_attendance) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">NIK</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jabatan</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jam Masuk</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Keterlambatan</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Potongan</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jam Keluar</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($today_attendance as $record): ?>
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
                                    <?php echo date('H:i', strtotime($record['time_in'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php 
                                    if ($record['late_minutes'] > 0) {
                                        echo $record['late_minutes'] . ' menit';
                                    } else {
                                        echo '-';
                                    }
                                    ?>
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
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $record['time_out'] ? date('H:i', strtotime($record['time_out'])) : '-'; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-gray-500 text-center py-4">Belum ada absensi hari ini.</p>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
