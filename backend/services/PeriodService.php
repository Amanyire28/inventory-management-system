<?php
/**
 * Period Service
 * 
 * Manages financial periods (OPEN/CLOSED)
 * Closed periods are read-only - no edits or deletions allowed
 * Only additive corrections (reversals) are allowed in closed periods
 */

class PeriodService {
    
    /**
     * Get all periods
     */
    public static function getAllPeriods() {
        $db = Database::getInstance();
        
        return $db->fetchAll(
            "SELECT * FROM periods ORDER BY start_date DESC"
        );
    }
    
    /**
     * Get single period
     */
    public static function getPeriod($id) {
        $db = Database::getInstance();
        
        return $db->fetch("SELECT * FROM periods WHERE id = ?", [$id]);
    }
    
    /**
     * Get current open period
     */
    public static function getCurrentPeriod() {
        $db = Database::getInstance();
        
        return $db->fetch("SELECT * FROM periods WHERE status = 'OPEN' ORDER BY start_date DESC LIMIT 1");
    }
    
    /**
     * Create new period
     */
    public static function createPeriod($period_name, $start_date, $end_date) {
        
        Auth::requireRole(['admin']);
        
        $db = Database::getInstance();
        
        if (empty($period_name)) {
            throw new Exception("Period name is required");
        }
        
        $stmt = $db->getConnection()->prepare(
            "INSERT INTO periods (period_name, status, start_date, end_date) 
             VALUES (?, 'OPEN', ?, ?)"
        );
        
        $stmt->bind_param('sss', $period_name, $start_date, $end_date);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create period: " . $stmt->error);
        }
        
        $period_id = $db->getConnection()->insert_id;
        
        AuditLog::log('CREATE_PERIOD', 'periods', $period_id, null, [
            'period_name' => $period_name,
            'start_date' => $start_date,
            'end_date' => $end_date
        ]);
        
        return $period_id;
    }
    
    /**
     * Close period (CRITICAL: Admin only, irreversible)
     * 
     * Once closed:
     * - No new transactions can be recorded
     * - No edits allowed (enforced at transaction level)
     * - Only reversals for corrections are allowed
     */
    public static function closePeriod($id) {
        
        Auth::requireRole(['admin']);
        
        $db = Database::getInstance();
        
        // Validate period exists and is open
        $period = self::getPeriod($id);
        if (!$period) {
            throw new Exception("Period not found");
        }
        
        if ($period['status'] === 'CLOSED') {
            throw new Exception("Period is already closed");
        }
        
        $user = Auth::getCurrentUser();
        $user_id = $user['user_id'];
        $closed_at = date('Y-m-d H:i:s');
        
        // Update period status
        $stmt = $db->getConnection()->prepare(
            "UPDATE periods SET status = 'CLOSED', closed_by = ?, closed_at = ? WHERE id = ?"
        );
        
        $stmt->bind_param('isi', $user_id, $closed_at, $id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to close period: " . $stmt->error);
        }
        
        AuditLog::log('CLOSE_PERIOD', 'periods', $id, $period, [
            'status' => 'CLOSED',
            'closed_by' => $user_id,
            'closed_at' => $closed_at
        ]);
        
        return true;
    }
    
    /**
     * Get period transactions count
     */
    public static function getPeriodSummary($period_id) {
        $db = Database::getInstance();
        
        $summary = $db->fetch(
            "SELECT 
                COUNT(*) as total_transactions,
                SUM(CASE WHEN type = 'SALE' THEN quantity ELSE 0 END) as total_sales_qty,
                SUM(CASE WHEN type = 'PURCHASE' THEN quantity ELSE 0 END) as total_purchases_qty,
                SUM(CASE WHEN type = 'SALE' THEN total_amount ELSE 0 END) as total_sales_amount,
                SUM(CASE WHEN type = 'PURCHASE' THEN total_amount ELSE 0 END) as total_purchases_amount
            FROM transactions 
            WHERE period_id = ? AND status = 'COMMITTED'",
            [$period_id]
        );
        
        return $summary;
    }
}
?>
