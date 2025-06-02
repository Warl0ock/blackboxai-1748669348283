-- Add leave quota to employees table
ALTER TABLE employees 
ADD COLUMN leave_quota INT DEFAULT 12,
ADD COLUMN daily_salary DECIMAL(15,2) GENERATED ALWAYS AS (salary / 22) STORED;

-- Create late penalties configuration table
CREATE TABLE late_penalties_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    penalty_amount DECIMAL(10,2) NOT NULL,
    grace_period_minutes INT DEFAULT 10,
    max_late_hours INT DEFAULT 4,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default late penalty configuration
INSERT INTO late_penalties_config (penalty_amount, grace_period_minutes, max_late_hours)
VALUES (50000, 10, 4);

-- Add columns to attendance_records for late tracking and leave management
ALTER TABLE attendance_records 
ADD COLUMN late_minutes INT DEFAULT 0,
ADD COLUMN late_penalty DECIMAL(10,2) DEFAULT 0,
ADD COLUMN is_leave BOOLEAN DEFAULT FALSE,
ADD COLUMN leave_deducted BOOLEAN DEFAULT FALSE,
ADD COLUMN salary_deducted BOOLEAN DEFAULT FALSE,
ADD COLUMN attendance_status ENUM('present', 'late', 'absent', 'leave') DEFAULT 'present';

-- Create leave records table
CREATE TABLE leave_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    date DATE NOT NULL,
    attendance_record_id INT,
    leave_type ENUM('annual', 'salary_deduction') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id),
    FOREIGN KEY (attendance_record_id) REFERENCES attendance_records(id)
);

-- Create indexes for better performance
CREATE INDEX idx_attendance_late ON attendance_records(late_minutes);
CREATE INDEX idx_leave_date ON leave_records(date);
CREATE INDEX idx_leave_employee ON leave_records(employee_id);
