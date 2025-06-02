<?php
require_once 'includes/db.php';

// Function to check loan eligibility
function checkLoanEligibility($employee, $loan_amount) {
    global $pdo;
    
    // Get loan configuration
    $stmt = $pdo->query("SELECT * FROM loan_config LIMIT 1");
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if employee has active loan
    if ($employee['has_active_loan']) {
        return ['eligible' => false, 'reason' => 'Karyawan masih memiliki pinjaman aktif.'];
    }
    
    // Check work duration
    $hire_date = new DateTime($employee['hire_date']);
    $now = new DateTime();
    $work_years = $hire_date->diff($now)->y;
    
    if ($work_years < $config['min_work_years']) {
        return ['eligible' => false, 'reason' => 'Masa kerja belum mencukupi (minimal ' . $config['min_work_years'] . ' tahun).'];
    }
    
    // Check maximum loan amount
    $max_loan = $employee['salary'] * ($config['max_loan_percentage'] / 100);
    if ($loan_amount > $max_loan) {
        return ['eligible' => false, 'reason' => 'Jumlah pinjaman melebihi batas maksimal (Rp ' . number_format($max_loan, 0, ',', '.') . ').'];
    }
    
    return ['eligible' => true];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['action'])) {
            if ($_POST['action'] == 'apply') {
                // Process new loan application
                $employee_id = $_POST['employee_id'];
                $loan_amount = floatval($_POST['loan_amount']);
                $installments = intval($_POST['installments']);
                
                // Get employee details
                $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
                $stmt->execute([$employee_id]);
                $employee = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Check eligibility
                $eligibility = checkLoanEligibility($employee, $loan_amount);
                if (!$eligibility['eligible']) {
                    throw new Exception($eligibility['reason']);
                }
                
                // Calculate installment amount
                $installment_amount = $loan_amount / $installments;
                
                // Create loan record
                $stmt = $pdo->prepare("
                    INSERT INTO employee_loans 
                    (employee_id, loan_amount, remaining_amount, installment_amount, 
                     total_installments, remaining_installments, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'pending')
                ");
                $stmt->execute([
                    $employee_id,
                    $loan_amount,
                    $loan_amount,
                    $installment_amount,
                    $installments,
                    $installments
                ]);
                
                $message = "Pengajuan pinjaman berhasil disubmit.";
            } 
            else if ($_POST['action'] == 'approve' || $_POST['action'] == 'reject') {
                // Process loan approval/rejection
                $loan_id = $_POST['loan_id'];
                $status = $_POST['action'] == 'approve' ? 'approved' : 'rejected';
                
                if ($status == 'approved') {
                    // Update loan status and employee's active loan status
                    $pdo->beginTransaction();
                    
                    $stmt = $pdo->prepare("
                        UPDATE employee_loans 
                        SET status = ?, approved_by = ?, approved_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$status, 1, $loan_id]); // Using 1 as admin ID for now
                    
                    $stmt = $pdo->prepare("
                        UPDATE employees e
                        JOIN employee_loans l ON e.id = l.employee_id
                        SET e.has_active_loan = TRUE
                        WHERE l.id = ?
                    ");
                    $stmt->execute([$loan_id]);
                    
                    $pdo->commit();
                    $message = "Pinjaman berhasil disetujui.";
                } else {
                    // Just update loan status
                    $stmt = $pdo->prepare("
                        UPDATE employee_loans 
                        SET status = ?, approved_by = ?, approved_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$status, 1, $loan_id]); // Using 1 as admin ID for now
                    $message = "Pinjaman ditolak.";
                }
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get loan configuration
$stmt = $pdo->query("SELECT * FROM loan_config LIMIT 1");
$config = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all employees for the dropdown
$stmt = $pdo->query("
    SELECT id, nik, name, salary, hire_date, has_active_loan 
    FROM employees 
    ORDER BY name
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all loan applications
$stmt = $pdo->query("
    SELECT 
        l.*,
        e.nik,
        e.name,
        e.salary,
        DATEDIFF(CURRENT_DATE, e.hire_date) / 365 as years_of_service
    FROM employee_loans l
    JOIN employees e ON l.employee_id = e.id
    ORDER BY l.created_at DESC
");
$loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pinjaman Karyawan</title>
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
                    <h1 class="text-3xl font-bold text-gray-900">Manajemen Pinjaman</h1>
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

            <!-- Loan Application Form -->
            <div class="bg-white shadow rounded-lg p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4">Pengajuan Pinjaman Baru</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="apply">
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="employee_id" class="block text-sm font-medium text-gray-700">Karyawan</label>
                            <select name="employee_id" id="employee_id" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Pilih Karyawan...</option>
                                <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['id']; ?>" 
                                        <?php echo $employee['has_active_loan'] ? 'disabled' : ''; ?>>
                                    <?php 
                                    echo htmlspecialchars($employee['nik'] . ' - ' . $employee['name'] . 
                                          ' (Gaji: Rp ' . number_format($employee['salary'], 0, ',', '.') . ')');
                                    if ($employee['has_active_loan']) echo ' - Memiliki pinjaman aktif';
                                    ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="loan_amount" class="block text-sm font-medium text-gray-700">Jumlah Pinjaman</label>
                            <div class="mt-1 relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 sm:text-sm">Rp</span>
                                </div>
                                <input type="number" name="loan_amount" id="loan_amount" required
                                       class="pl-12 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                        </div>
                        
                        <div>
                            <label for="installments" class="block text-sm font-medium text-gray-700">Jumlah Cicilan</label>
                            <input type="number" name="installments" id="installments" 
                                   min="1" max="<?php echo $config['default_max_installments']; ?>" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <p class="mt-1 text-sm text-gray-500">Maksimal <?php echo $config['default_max_installments']; ?>x cicilan</p>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200">
                            Ajukan Pinjaman
                        </button>
                    </div>
                </form>
            </div>

            <!-- Loan Applications List -->
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-xl font-semibold">Daftar Pengajuan Pinjaman</h2>
                </div>
                
                <?php if (count($loans) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">NIK</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Masa Kerja</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jumlah</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cicilan</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($loans as $loan): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo date('d/m/Y', strtotime($loan['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($loan['nik']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($loan['name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo number_format($loan['years_of_service'], 1); ?> tahun
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    Rp <?php echo number_format($loan['loan_amount'], 0, ',', '.'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $loan['remaining_installments']; ?>/<?php echo $loan['total_installments']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                        <?php 
                                        echo match($loan['status']) {
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'approved' => 'bg-green-100 text-green-800',
                                            'rejected' => 'bg-red-100 text-red-800',
                                            'active' => 'bg-blue-100 text-blue-800',
                                            'completed' => 'bg-gray-100 text-gray-800',
                                            default => 'bg-gray-100 text-gray-800'
                                        };
                                        ?>">
                                        <?php 
                                        echo match($loan['status']) {
                                            'pending' => 'Menunggu',
                                            'approved' => 'Disetujui',
                                            'rejected' => 'Ditolak',
                                            'active' => 'Aktif',
                                            'completed' => 'Lunas',
                                            default => $loan['status']
                                        };
                                        ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <?php if ($loan['status'] == 'pending'): ?>
                                    <form method="POST" class="inline-block">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                                        <button type="submit" 
                                                class="text-green-600 hover:text-green-900 mr-3">
                                            Setujui
                                        </button>
                                    </form>
                                    <form method="POST" class="inline-block">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                                        <button type="submit"
                                                class="text-red-600 hover:text-red-900">
                                            Tolak
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <p class="text-gray-500">Belum ada pengajuan pinjaman.</p>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
