<?php
/**
 * Payment Processing
 * Part of Billing System
 * Philippines Hospital Context
 */
require_once '../../config/config.php';
requireAuth();
checkRole(['admin', 'billing_staff']);

$page_title = "Payment Processing";

// Use patient_billing view if it exists, else billing table (with column aliases)
$use_billing_view = false;
try {
    $chk = $db->query("SELECT 1 FROM patient_billing LIMIT 0");
    $use_billing_view = true;
} catch (PDOException $e) {
    // View/table doesn't exist; we'll use billing table
}

// Handle payment
if ($_POST) {
    try {
        $billing_id = (int)sanitizeInput($_POST['billing_id']);
        $payment_amount = (float)sanitizeInput($_POST['payment_amount']);
        $payment_method = sanitizeInput($_POST['payment_method']);
        $payment_reference = sanitizeInput($_POST['payment_reference'] ?? '');
        $notes = sanitizeInput($_POST['notes'] ?? '');
        
        if ($use_billing_view) {
            $billing_query = "SELECT * FROM patient_billing WHERE id = :billing_id";
        } else {
            $billing_query = "SELECT id, bill_number AS billing_number, patient_id, total_amount, paid_amount, balance_amount AS balance, payment_status AS status, bill_date AS billing_date
                              FROM billing WHERE id = :billing_id";
        }
        $billing_stmt = $db->prepare($billing_query);
        $billing_stmt->bindParam(':billing_id', $billing_id, PDO::PARAM_INT);
        $billing_stmt->execute();
        $billing = $billing_stmt->fetch();
        
        if (!$billing) {
            throw new Exception("Billing record not found.");
        }
        
        // Generate payment reference number
        $payment_number = 'PAY' . date('Ymd') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        
        // Insert payment (payments table uses billing_id and amount)
        $payment_query = "INSERT INTO payments (
            payment_number, billing_id, patient_id, amount, payment_method,
            reference_number, payment_date, status, notes, processed_by
        ) VALUES (
            :payment_number, :billing_id, :patient_id, :amount, :payment_method,
            :reference_number, NOW(), 'completed', :notes, :processed_by
        )";
        
        $payment_stmt = $db->prepare($payment_query);
        $payment_stmt->bindParam(':payment_number', $payment_number);
        $payment_stmt->bindParam(':billing_id', $billing_id, PDO::PARAM_INT);
        $payment_stmt->bindParam(':patient_id', $billing['patient_id'], PDO::PARAM_INT);
        $payment_stmt->bindParam(':amount', $payment_amount);
        $payment_stmt->bindParam(':payment_method', $payment_method);
        $payment_stmt->bindParam(':reference_number', $payment_reference);
        $payment_stmt->bindParam(':notes', $notes);
        $payment_stmt->bindParam(':processed_by', $_SESSION['user_id'], PDO::PARAM_INT);
        $payment_stmt->execute();
        
        // Update billing balance
        $new_balance = $billing['total_amount'] - $billing['paid_amount'] - $payment_amount;
        $new_paid = $billing['paid_amount'] + $payment_amount;
        $billing_status = $new_balance <= 0 ? 'paid' : 'partial';
        
        if ($use_billing_view) {
            $update_billing = $db->prepare("UPDATE patient_billing SET paid_amount = :paid_amount, balance = :balance, status = :status, updated_at = NOW() WHERE id = :billing_id");
        } else {
            $update_billing = $db->prepare("UPDATE billing SET paid_amount = :paid_amount, balance_amount = :balance, payment_status = :status, updated_at = NOW() WHERE id = :billing_id");
        }
        $update_billing->bindParam(':paid_amount', $new_paid);
        $update_billing->bindParam(':balance', $new_balance);
        $update_billing->bindParam(':status', $billing_status);
        $update_billing->bindParam(':billing_id', $billing_id, PDO::PARAM_INT);
        $update_billing->execute();
        
        $_SESSION['success'] = "Payment processed successfully! Payment #: " . $payment_number;
        header("Location: payments.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error processing payment: " . $e->getMessage();
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Get pending bills (from view or billing table)
try {
    if ($use_billing_view) {
        $bills_query = "SELECT pb.*, p.first_name, p.last_name, p.hospital_id, p.contact_number
                        FROM patient_billing pb
                        INNER JOIN patients p ON pb.patient_id = p.id
                        WHERE pb.status IN ('pending', 'partial')
                        ORDER BY pb.billing_date DESC
                        LIMIT 50";
    } else {
        $bills_query = "SELECT b.id, b.bill_number AS billing_number, b.patient_id, b.total_amount, b.paid_amount,
                        b.balance_amount AS balance, b.payment_status AS status, b.bill_date AS billing_date,
                        b.payment_method AS billing_type, p.first_name, p.last_name, p.hospital_id, p.contact_number
                        FROM billing b
                        INNER JOIN patients p ON b.patient_id = p.id
                        WHERE b.payment_status IN ('pending', 'partial')
                        ORDER BY b.bill_date DESC
                        LIMIT 50";
    }
    $bills_stmt = $db->prepare($bills_query);
    $bills_stmt->execute();
    $pending_bills = $bills_stmt->fetchAll();
} catch (PDOException $e) {
    $pending_bills = [];
}

// Billing table/view name for joins (payments.billing_id -> billing.id)
$billing_from = $use_billing_view ? 'patient_billing' : 'billing';
$billing_id_col = $use_billing_view ? 'id' : 'id';
$billing_number_col = $use_billing_view ? 'billing_number' : 'bill_number';

// Get recent payments (alias amount AS payment_amount for display; join to billing/view)
try {
    $payments_query = "SELECT py.id, py.payment_number, py.billing_id, py.patient_id, py.amount AS payment_amount,
                      py.payment_method, py.payment_date, py.reference_number, py.status,
                      p.first_name, p.last_name, p.hospital_id,
                      pb.{$billing_number_col} AS billing_number,
                      u.first_name AS staff_fname, u.last_name AS staff_lname
                      FROM payments py
                      INNER JOIN patients p ON py.patient_id = p.id
                      INNER JOIN {$billing_from} pb ON py.billing_id = pb.id
                      LEFT JOIN users u ON py.processed_by = u.id
                      ORDER BY py.payment_date DESC
                      LIMIT 20";
    $payments_stmt = $db->prepare($payments_query);
    $payments_stmt->execute();
    $recent_payments = $payments_stmt->fetchAll();
} catch (PDOException $e) {
    $recent_payments = [];
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Payment Processing</h1>
    <p class="text-gray-600 dark:text-gray-400">Process payments for patient bills</p>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <!-- Pending Bills -->
    <div class="lg:col-span-2">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Pending Bills</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400"><?php echo count($pending_bills); ?> bills pending payment</p>
            </div>
            <div class="px-4 py-5 sm:p-6">
                <?php if (count($pending_bills) > 0): ?>
                    <div class="space-y-4">
                        <?php foreach ($pending_bills as $bill): ?>
                            <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4 bg-gray-50 dark:bg-gray-700">
                                <div class="flex justify-between items-start mb-3">
                                    <div class="flex-1">
                                        <h4 class="text-sm font-medium text-gray-900">
                                            <?php echo $bill['first_name'] . ' ' . $bill['last_name']; ?>
                                            (<?php echo $bill['hospital_id']; ?>)
                                        </h4>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            Bill #<?php echo $bill['billing_number']; ?> • 
                                            <?php echo formatDate($bill['billing_date']); ?>
                                        </p>
                                        <div class="mt-2 grid grid-cols-3 gap-4 text-xs">
                                            <div>
                                                <span class="text-gray-500 dark:text-gray-400">Total:</span>
                                                <span class="font-medium text-gray-900 dark:text-white">₱<?php echo number_format($bill['total_amount'], 2); ?></span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500 dark:text-gray-400">Paid:</span>
                                                <span class="font-medium text-green-600 dark:text-green-400">₱<?php echo number_format($bill['paid_amount'], 2); ?></span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500 dark:text-gray-400">Balance:</span>
                                                <span class="font-medium text-red-600 dark:text-red-400">₱<?php echo number_format($bill['balance'], 2); ?></span>
                                            </div>
                                        </div>
                                        <?php $btype = $bill['billing_type'] ?? $bill['payment_method'] ?? ''; if ($btype): ?>
                                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-2">Type: <?php echo ucfirst($btype); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        <?php echo $bill['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800'; ?>">
                                        <?php echo ucfirst($bill['status']); ?>
                                    </span>
                                </div>
                                
                                <!-- Payment Form -->
                                <details class="group">
                                    <summary class="cursor-pointer px-3 py-2 bg-primary-600 text-white text-sm font-medium rounded-md hover:bg-primary-700 text-center">
                                        Process Payment
                                    </summary>
                                    <div class="mt-3 p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg">
                                        <form method="POST" class="space-y-3">
                                            <input type="hidden" name="billing_id" value="<?php echo $bill['id']; ?>">
                                            
                                            <div class="bg-gray-50 p-3 rounded border">
                                                <p class="text-xs text-gray-600">Outstanding Balance:</p>
                                                <p class="text-lg font-bold text-gray-900">₱<?php echo number_format($bill['balance'], 2); ?></p>
                                            </div>
                                            
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700">Payment Amount (₱) *</label>
                                                <input type="number" name="payment_amount" step="0.01" min="0.01" max="<?php echo $bill['balance']; ?>" required
                                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm"
                                                    placeholder="0.00">
                                            </div>
                                            
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700">Payment Method *</label>
                                                <select name="payment_method" required
                                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm">
                                                    <option value="">Select Method</option>
                                                    <option value="cash">Cash</option>
                                                    <option value="check">Check</option>
                                                    <option value="credit_card">Credit Card</option>
                                                    <option value="debit_card">Debit Card</option>
                                                    <option value="gcash">GCash</option>
                                                    <option value="paymaya">PayMaya</option>
                                                    <option value="bank_transfer">Bank Transfer</option>
                                                    <option value="insurance">Insurance</option>
                                                </select>
                                            </div>
                                            
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Payment Reference</label>
                                                <input type="text" name="payment_reference"
                                                    class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                                    placeholder="Check number, transaction ID, etc.">
                                            </div>
                                            
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Notes</label>
                                                <textarea name="notes" rows="2"
                                                    class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                                    placeholder="Additional notes..."></textarea>
                                            </div>
                                            
                                            <button type="submit"
                                                class="w-full px-3 py-2 bg-primary-600 text-white text-sm font-medium rounded-md hover:bg-primary-700">
                                                Process Payment
                                            </button>
                                        </form>
                                    </div>
                                </details>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2"><circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path></svg>
                        <p class="text-gray-500">No pending bills</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Payments -->
    <div class="lg:col-span-1">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Recent Payments</h3>
            </div>
            <div class="px-4 py-5 sm:p-6">
                <?php if (count($recent_payments) > 0): ?>
                    <div class="space-y-3">
                        <?php foreach ($recent_payments as $payment): ?>
                            <div class="p-3 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    <?php echo $payment['first_name'] . ' ' . $payment['last_name']; ?>
                                </p>
                                <p class="text-xs text-gray-500"><?php echo $payment['hospital_id']; ?></p>
                                <p class="text-xs font-medium text-green-600 mt-1">
                                    ₱<?php echo number_format($payment['payment_amount'], 2); ?>
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                </p>
                                <p class="text-xs text-gray-500">
                                    <?php echo formatDate($payment['payment_date']); ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"></path><path d="M14 8H8"></path><path d="M16 12H8"></path><path d="M13 16H8"></path></svg>
                        <p class="text-gray-500">No recent payments</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>



