-- Create employee loans configuration table
CREATE TABLE loan_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    default_max_installments INT DEFAULT 3,
    min_work_years INT DEFAULT 1,
    max_loan_percentage DECIMAL(5,2) DEFAULT 50.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default configuration
INSERT INTO loan_config (default_max_installments, min_work_years, max_loan_percentage)
VALUES (3, 1, 50.00);

-- Create employee loans table
CREATE TABLE employee_loans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    loan_amount DECIMAL(15,2) NOT NULL,
    remaining_amount DECIMAL(15,2) NOT NULL,
    installment_amount DECIMAL(15,2) NOT NULL,
    total_installments INT NOT NULL,
    remaining_installments INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'active', 'completed') DEFAULT 'pending',
    approved_by INT,
    approved_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id),
    FOREIGN KEY (approved_by) REFERENCES employees(id)
);

-- Create loan payments table
CREATE TABLE loan_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    payment_amount DECIMAL(15,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_number INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (loan_id) REFERENCES employee_loans(id)
);

-- Add loan eligibility columns to employees table
ALTER TABLE employees
ADD COLUMN hire_date DATE,
ADD COLUMN has_active_loan BOOLEAN DEFAULT FALSE;
