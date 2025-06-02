-- Create the database if it doesn't exist
CREATE DATABASE IF NOT EXISTS employee_db;
USE employee_db;

-- Create employees table
CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nik VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    address TEXT NOT NULL,
    phone VARCHAR(20),
    birth_date DATE,
    salary DECIMAL(15,2) DEFAULT 0.00,
    deduction DECIMAL(15,2) DEFAULT 0.00,
    allowance DECIMAL(15,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add some sample data
INSERT INTO employees (nik, name, address, phone, birth_date, salary, deduction, allowance) VALUES
('EMP001', 'John Doe', 'Jl. Sudirman No. 123, Jakarta', '081234567890', '1990-01-15', 5000000, 200000, 500000),
('EMP002', 'Jane Smith', 'Jl. Thamrin No. 45, Jakarta', '082345678901', '1992-05-20', 4500000, 150000, 400000),
('EMP003', 'Ahmad Rahman', 'Jl. Gatot Subroto No. 67, Jakarta', '083456789012', '1988-11-30', 6000000, 250000, 600000);
