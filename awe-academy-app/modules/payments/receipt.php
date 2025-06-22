<?php
require_once __DIR__ . '/../../includes/auth.php';

// Check if user has permission to access this page
if (!in_array($_SESSION['role'], ['admin', 'finance_officer'])) {
    header("Location: /index.php");
    exit;
}

// Get payment ID from URL
$paymentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$paymentId) {
    header("Location: /modules/fees/index.php");
    exit;
}

try {
    // Fetch payment details with all related information
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            sf.student_id,
            sf.fee_id,
            s.student_number,
            s.first_name as student_first_name,
            s.last_name as student_last_name,
            s.class_year,
            f.name as fee_name,
            f.amount as total_fee_amount,
            u.full_name as recorded_by
        FROM payments p
        JOIN student_fees sf ON p.student_fee_id = sf.id
        JOIN students s ON sf.student_id = s.id
        JOIN fees f ON sf.fee_id = f.id
        JOIN users u ON p.created_by = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch();

    if (!$payment) {
        throw new Exception('Payment record not found');
    }

} catch (Exception $e) {
    error_log("Error fetching payment details: " . $e->getMessage());
    die("Error: Unable to generate receipt");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - <?php echo $payment['receipt_number']; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 2rem;
        }
        
        .receipt {
            background: white;
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .receipt-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #002147;
        }
        
        .receipt-title {
            color: #002147;
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }
        
        .receipt-number {
            color: #004080;
            font-size: 1.1rem;
            margin-top: 0.5rem;
        }
        
        .receipt-body {
            margin-bottom: 2rem;
        }
        
        .receipt-footer {
            margin-top: 3rem;
            padding-top: 1rem;
            border-top: 1px solid #dee2e6;
        }
        
        .signature-line {
            border-top: 1px solid #000;
            width: 200px;
            margin-top: 4rem;
            margin-bottom: 0.5rem;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .receipt {
                box-shadow: none;
            }
            
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="receipt-header">
            <h1 class="receipt-title">AWE Academy</h1>
            <div class="receipt-number">Receipt #<?php echo htmlspecialchars($payment['receipt_number']); ?></div>
        </div>

        <div class="receipt-body">
            <div class="row mb-4">
                <div class="col-6">
                    <h5>Student Information</h5>
                    <p class="mb-1">
                        <strong>Name:</strong> 
                        <?php echo htmlspecialchars($payment['student_first_name'] . ' ' . $payment['student_last_name']); ?>
                    </p>
                    <p class="mb-1">
                        <strong>ID:</strong> 
                        <?php echo htmlspecialchars($payment['student_number']); ?>
                    </p>
                    <p class="mb-1">
                        <strong>Class Year:</strong> 
                        <?php echo htmlspecialchars($payment['class_year']); ?>
                    </p>
                </div>
                <div class="col-6 text-end">
                    <h5>Payment Details</h5>
                    <p class="mb-1">
                        <strong>Date:</strong> 
                        <?php echo date('F j, Y', strtotime($payment['payment_date'])); ?>
                    </p>
                    <p class="mb-1">
                        <strong>Method:</strong> 
                        <?php echo ucwords(str_replace('_', ' ', $payment['payment_method'])); ?>
                    </p>
                </div>
            </div>

            <div class="table-responsive mb-4">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Description</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($payment['fee_name']); ?>
                                <?php if ($payment['notes']): ?>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($payment['notes']); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">$<?php echo number_format($payment['amount'], 2); ?></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>Total Amount Paid</th>
                            <th class="text-end">$<?php echo number_format($payment['amount'], 2); ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="row">
                <div class="col-12">
                    <p class="mb-1">
                        <strong>Amount in Words:</strong>
                        <?php
                        // Simple function to convert number to words
                        function numberToWords($number) {
                            $f = new NumberFormatter("en", NumberFormatter::SPELLOUT);
                            return ucfirst($f->format($number));
                        }
                        
                        echo numberToWords($payment['amount']) . ' dollars';
                        ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="receipt-footer">
            <div class="row">
                <div class="col-6">
                    <div class="signature-line"></div>
                    <p class="mb-0">Received By</p>
                </div>
                <div class="col-6 text-end">
                    <div class="signature-line ms-auto"></div>
                    <p class="mb-0">
                        <?php echo htmlspecialchars($payment['recorded_by']); ?><br>
                        <small class="text-muted">Authorized Signatory</small>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center mt-4 no-print">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fas fa-print me-2"></i>Print Receipt
        </button>
        <button onclick="window.close()" class="btn btn-secondary">
            <i class="fas fa-times me-2"></i>Close
        </button>
    </div>

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</body>
</html>
