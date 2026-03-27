<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';

// Get borrowing history
$history_sql = "SELECT b.*, bk.title, bk.author,
                CONCAT(s.first_name, ' ', s.last_name) as student_name,
                s.admission_no,
                CONCAT(st.first_name, ' ', st.last_name) as staff_name,
                st.staff_no
                FROM borrowings b
                LEFT JOIN books bk ON b.book_id = bk.book_id
                LEFT JOIN students s ON b.student_id = s.student_id
                LEFT JOIN staff st ON b.staff_id = st.staff_id
                WHERE b.status != 'Borrowed'
                ORDER BY b.return_date DESC, b.borrow_date DESC
                LIMIT 50";
$history_result = $conn->query($history_sql);
?>

<?php if ($history_result->num_rows > 0): ?>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Book</th>
                    <th>Borrower</th>
                    <th>Borrow Date</th>
                    <th>Return Date</th>
                    <th>Fine</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($history = $history_result->fetch_assoc()): 
                    $borrower = $history['student_name'] ?: $history['staff_name'];
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars($history['title']); ?></td>
                        <td><?php echo htmlspecialchars($borrower); ?></td>
                        <td><?php echo formatDate($history['borrow_date']); ?></td>
                        <td><?php echo $history['return_date'] ? formatDate($history['return_date']) : '-'; ?></td>
                        <td><?php echo $history['fine_amount'] > 0 ? formatMoney($history['fine_amount']) : '-'; ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="text-center py-4">
        <p class="text-muted">No borrowing history available.</p>
    </div>
<?php endif; ?>