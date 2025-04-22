<?php
// ========================================================================
// ไฟล์: includes/functions.php (หรือชื่ออื่นตามต้องการ)
// หน้าที่: เก็บฟังก์ชันที่ใช้งานร่วมกันในโปรเจกต์
// ========================================================================

if (!function_exists('format_datetime_th')) {
    /**
     * แปลง datetime string เป็นรูปแบบภาษาไทย
     * @param string|null $datetime_str วันที่เวลาในรูปแบบที่ DateTime() รู้จัก
     * @param bool $include_time ต้องการแสดงเวลาด้วยหรือไม่ (true/false)
     * @return string วันที่เวลาภาษาไทย หรือ '-' ถ้าข้อมูลผิดพลาด
     */
    function format_datetime_th($datetime_str, $include_time = false) {
        if (empty($datetime_str)) return '-';
        try {
            $dt = new DateTime($datetime_str);
            $thai_months_short = [
                1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
                5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
                9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
            ];
            $day = $dt->format('d');
            $month_num = (int)$dt->format('n');
            $thai_month = $thai_months_short[$month_num] ?? '?';
            $buddhist_year = $dt->format('Y') + 543;

            $formatted_date = $day . ' ' . $thai_month . ' ' . $buddhist_year;

            if ($include_time) {
                $formatted_date .= ' ' . $dt->format('H:i'); // เพิ่มเวลาถ้าต้องการ
            }

            return $formatted_date;
        } catch (Exception $e) {
            error_log("Error formatting date: " . $e->getMessage()); // Log error for debugging
            return '-'; // คืนค่า '-' ถ้าเกิดข้อผิดพลาด
        }
    }
}

if (!function_exists('format_booking_status')) {
    /**
     * แปลงสถานะการจองเป็น Badge HTML
     * @param string|null $status สถานะการจอง
     * @return string HTML Badge
     */
    function format_booking_status($status) {
        if ($status === 'booked') return '<span class="badge badge-sm bg-gradient-primary">จองแล้ว</span>';
        if ($status === 'cancelled') return '<span class="badge badge-sm bg-gradient-secondary">ยกเลิกแล้ว</span>';
        return '<span class="badge badge-sm bg-gradient-light text-dark">' . htmlspecialchars($status ?? '') . '</span>';
    }
}

if (!function_exists('format_attendance_status')) {
    /**
     * แปลงสถานะการเข้าร่วมเป็น Badge HTML
     * @param string|null $status สถานะการเข้าร่วม
     * @return string HTML Badge
     */
    function format_attendance_status($status) {
         switch ($status) {
            case 'attended': return '<span class="badge badge-sm bg-gradient-success">เข้าร่วม</span>';
            case 'absent': return '<span class="badge badge-sm bg-gradient-danger">ไม่เข้าร่วม</span>';
            // อาจจะเพิ่ม case อื่นๆ ถ้ามีกลับมาใช้
            default: return '<span class="badge badge-sm bg-gradient-light text-dark">' . htmlspecialchars($status ?? '') . '</span>';
        }
    }
}

// --- สามารถเพิ่มฟังก์ชัน Helper อื่นๆ ที่ใช้บ่อยๆ ไว้ในไฟล์นี้ได้ ---

?>
