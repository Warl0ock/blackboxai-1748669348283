<?php
require_once 'includes/db.php';

try {
    $stmt = $pdo->query("SELECT * FROM employees ORDER BY name");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Query error: " . $e->getMessage());
    $employees = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Manajemen Karyawan & Absensi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
                <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <!-- Quick Access Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <!-- Attendance Card -->
                <a href="attendance.php" class="bg-white overflow-hidden shadow rounded-lg hover:shadow-md transition-shadow duration-300">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                                <i class="fas fa-clock text-white text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-medium text-gray-900">Absensi</h3>
                                <p class="text-sm text-gray-500">Kelola absen masuk dan keluar karyawan</p>
                            </div>
                        </div>
                    </div>
                </a>

                <!-- Shift Management Card -->
                <a href="manage_shifts.php" class="bg-white overflow-hidden shadow rounded-lg hover:shadow-md transition-shadow duration-300">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                                <i class="fas fa-calendar-alt text-white text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-medium text-gray-900">Manajemen Shift</h3>
                                <p class="text-sm text-gray-500">Atur jadwal shift karyawan</p>
                            </div>
                        </div>
                    </div>
                </a>

                <!-- Loan Management Card -->
                <a href="manage_loans.php" class="bg-white overflow-hidden shadow rounded-lg hover:shadow-md transition-shadow duration-300">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-indigo-500 rounded-md p-3">
                                <i class="fas fa-hand-holding-usd text-white text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-medium text-gray-900">Pinjaman Karyawan</h3>
                                <p class="text-sm text-gray-500">Kelola pengajuan dan pembayaran pinjaman</p>
                            </div>
                        </div>
                    </div>
                </a>

                <!-- Penalties Management Card -->
                <a href="manage_penalties.php" class="bg-white overflow-hidden shadow rounded-lg hover:shadow-md transition-shadow duration-300">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-red-500 rounded-md p-3">
                                <i class="fas fa-exclamation-circle text-white text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-medium text-gray-900">Manajemen Keterlambatan</h3>
                                <p class="text-sm text-gray-500">Atur kebijakan keterlambatan dan potongan</p>
                            </div>
                        </div>
                    </div>
                </a>

                <!-- Overtime Report Card -->
                <a href="overtime.php" class="bg-white overflow-hidden shadow rounded-lg hover:shadow-md transition-shadow duration-300">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-purple-500 rounded-md p-3">
                                <i class="fas fa-chart-line text-white text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-medium text-gray-900">Laporan Lembur</h3>
                                <p class="text-sm text-gray-500">Lihat perhitungan lembur karyawan</p>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Employee List -->
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h2 class="text-xl font-semibold text-gray-900">Daftar Karyawan</h2>
                    <a href="add_edit_employee.php" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200">
                        Tambah Karyawan
                    </a>
                </div>
                
                <?php if (count($employees) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NIK/ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lokasi</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jabatan</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gaji Pokok</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($employees as $emp): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($emp['nik']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($emp['name']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php 
                                        echo match($emp['status']) {
                                            'lembur' => 'bg-green-100 text-green-800',
                                            'bonus' => 'bg-blue-100 text-blue-800',
                                            default => 'bg-gray-100 text-gray-800'
                                        };
                                        ?>">
                                        <?= htmlspecialchars($emp['status'] ?? 'non') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($emp['location'] ?? '-') ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($emp['position'] ?? '-') ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Rp <?= number_format($emp['salary'], 0, ',', '.') ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <a href="add_edit_employee.php?id=<?= $emp['id'] ?>" class="text-blue-600 hover:text-blue-900">Edit</a>
                                    <a href="delete_employee.php?id=<?= $emp['id'] ?>" 
                                       onclick="return confirm('Apakah anda yakin ingin menghapus data ini?')"
                                       class="text-red-600 hover:text-red-900">Hapus</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-12">
                    <p class="text-gray-500 text-lg">Belum ada data karyawan.</p>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
