-- Add new columns to employees table
ALTER TABLE employees 
ADD COLUMN status ENUM('non', 'bonus', 'lembur') NOT NULL DEFAULT 'non',
ADD COLUMN position VARCHAR(50),
ADD COLUMN location ENUM('Semarang', 'Jakarta'),
ADD COLUMN team_group VARCHAR(50);

-- Create shifts configuration table
CREATE TABLE shift_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    week_start_date DATE NOT NULL,
    week_end_date DATE NOT NULL,
    shift_type ENUM('2shift', '3shift') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create shift schedules table
CREATE TABLE shift_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shift_config_id INT,
    shift_number INT NOT NULL,
    day_type ENUM('weekday', 'saturday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    FOREIGN KEY (shift_config_id) REFERENCES shift_configs(id)
);

-- Create attendance records table
CREATE TABLE attendance_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    date DATE NOT NULL,
    time_in DATETIME,
    time_out DATETIME,
    shift_schedule_id INT,
    is_holiday BOOLEAN DEFAULT FALSE,
    status VARCHAR(20),
    FOREIGN KEY (employee_id) REFERENCES employees(id),
    FOREIGN KEY (shift_schedule_id) REFERENCES shift_schedules(id)
);

-- Create overtime records table
CREATE TABLE overtime_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attendance_id INT NOT NULL,
    employee_id INT NOT NULL,
    date DATE NOT NULL,
    overtime_hours DECIMAL(5,2),
    overtime_rate DECIMAL(10,2),
    is_holiday BOOLEAN DEFAULT FALSE,
    total_amount DECIMAL(10,2),
    FOREIGN KEY (attendance_id) REFERENCES attendance_records(id),
    FOREIGN KEY (employee_id) REFERENCES employees(id)
);

-- Create holidays table
CREATE TABLE holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    description VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default overtime rates
CREATE TABLE overtime_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    location ENUM('Semarang', 'Jakarta') NOT NULL,
    day_type ENUM('workday', 'holiday') NOT NULL,
    rate_per_hour DECIMAL(10,2) NOT NULL
);

-- Insert default rates
INSERT INTO overtime_rates (location, day_type, rate_per_hour) VALUES
('Semarang', 'workday', 10000),
('Semarang', 'holiday', 13000),
('Jakarta', 'workday', 15000),
('Jakarta', 'holiday', 19000);

-- Add indexes for better performance
CREATE INDEX idx_attendance_date ON attendance_records(date);
CREATE INDEX idx_attendance_employee ON attendance_records(employee_id);
CREATE INDEX idx_overtime_date ON overtime_records(date);
CREATE INDEX idx_overtime_employee ON overtime_records(employee_id);
