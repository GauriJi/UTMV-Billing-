-- Sales Billing System Database
-- Database name: salesbilling

CREATE DATABASE IF NOT EXISTS salesbilling;
USE salesbilling;

-- Users Table for Authentication
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    role ENUM('admin', 'user') DEFAULT 'user',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- Insert default users (password: admin123 for admin, user123 for user)
INSERT INTO users (username, password, full_name, email, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin@billpro.com', 'admin'),
('user', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'Regular User', 'user@billpro.com', 'user');

-- Products/Items Table
CREATE TABLE IF NOT EXISTS products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_name VARCHAR(255) NOT NULL,
    hsn_code VARCHAR(50),
    unit VARCHAR(50) DEFAULT 'PCS',
    rate DECIMAL(10,2) DEFAULT 0.00,
    gst_rate DECIMAL(5,2) DEFAULT 18.00,
    stock_quantity INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Customers Table
CREATE TABLE IF NOT EXISTS customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(255),
    phone VARCHAR(20),
    email VARCHAR(255),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    pincode VARCHAR(10),
    gstin VARCHAR(15),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Suppliers Table
CREATE TABLE IF NOT EXISTS suppliers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    supplier_name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(255),
    phone VARCHAR(20),
    email VARCHAR(255),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    pincode VARCHAR(10),
    gstin VARCHAR(15),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Purchase Table
CREATE TABLE IF NOT EXISTS purchases (
    id INT PRIMARY KEY AUTO_INCREMENT,
    purchase_no VARCHAR(50) UNIQUE NOT NULL,
    supplier_id INT,
    purchase_date DATE NOT NULL,
    total_amount DECIMAL(12,2) DEFAULT 0.00,
    cgst_amount DECIMAL(12,2) DEFAULT 0.00,
    sgst_amount DECIMAL(12,2) DEFAULT 0.00,
    igst_amount DECIMAL(12,2) DEFAULT 0.00,
    grand_total DECIMAL(12,2) DEFAULT 0.00,
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Purchase Items Table
CREATE TABLE IF NOT EXISTS purchase_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    purchase_id INT NOT NULL,
    product_id INT,
    product_name VARCHAR(255),
    hsn_code VARCHAR(50),
    quantity INT NOT NULL,
    rate DECIMAL(10,2) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    gst_rate DECIMAL(5,2) DEFAULT 18.00,
    cgst DECIMAL(12,2) DEFAULT 0.00,
    sgst DECIMAL(12,2) DEFAULT 0.00,
    igst DECIMAL(12,2) DEFAULT 0.00,
    total DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

-- Sales Table
CREATE TABLE IF NOT EXISTS sales (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_no VARCHAR(50) UNIQUE NOT NULL,
    customer_id INT,
    sale_date DATE NOT NULL,
    total_amount DECIMAL(12,2) DEFAULT 0.00,
    cgst_amount DECIMAL(12,2) DEFAULT 0.00,
    sgst_amount DECIMAL(12,2) DEFAULT 0.00,
    igst_amount DECIMAL(12,2) DEFAULT 0.00,
    grand_total DECIMAL(12,2) DEFAULT 0.00,
    payment_status ENUM('pending', 'partial', 'paid') DEFAULT 'pending',
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Sales Items Table
CREATE TABLE IF NOT EXISTS sales_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sale_id INT NOT NULL,
    product_id INT,
    product_name VARCHAR(255),
    hsn_code VARCHAR(50),
    quantity INT NOT NULL,
    rate DECIMAL(10,2) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    gst_rate DECIMAL(5,2) DEFAULT 18.00,
    cgst DECIMAL(12,2) DEFAULT 0.00,
    sgst DECIMAL(12,2) DEFAULT 0.00,
    igst DECIMAL(12,2) DEFAULT 0.00,
    total DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

-- Company Settings Table
CREATE TABLE IF NOT EXISTS company_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_name VARCHAR(255) NOT NULL,
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    pincode VARCHAR(10),
    phone VARCHAR(20),
    email VARCHAR(255),
    gstin VARCHAR(15),
    logo_path VARCHAR(255),
    invoice_prefix VARCHAR(10) DEFAULT 'INV',
    purchase_prefix VARCHAR(10) DEFAULT 'PUR'
);

-- Insert default company settings
INSERT INTO company_settings (company_name, address, city, state, pincode, phone, email, gstin) 
VALUES ('Your Company Name', 'Your Address', 'Your City', 'Your State', '000000', '0000000000', 'info@company.com', '00XXXXX0000X0X0');

-- Insert sample products
INSERT INTO products (product_name, hsn_code, unit, rate, gst_rate, stock_quantity) VALUES
('Product A', '1001', 'PCS', 100.00, 18.00, 50),
('Product B', '1002', 'PCS', 200.00, 18.00, 30),
('Product C', '1003', 'KG', 150.00, 12.00, 100),
('Service Item', '9983', 'Service', 500.00, 18.00, 0);

-- Insert sample customers
INSERT INTO customers (customer_name, contact_person, phone, email, address, city, state, pincode, gstin) VALUES
('ABC Enterprises', 'John Doe', '9876543210', 'john@abc.com', '123 Main Street', 'Mumbai', 'Maharashtra', '400001', '27XXXXX0000X1Z5'),
('XYZ Industries', 'Jane Smith', '9876543211', 'jane@xyz.com', '456 Park Avenue', 'Delhi', 'Delhi', '110001', '07XXXXX0000X1Z5');

-- Insert sample suppliers
INSERT INTO suppliers (supplier_name, contact_person, phone, email, address, city, state, pincode, gstin) VALUES
('Global Suppliers', 'Mike Wilson', '9876543212', 'mike@global.com', '789 Industrial Area', 'Bangalore', 'Karnataka', '560001', '29XXXXX0000X1Z5'),
('Prime Vendors', 'Sarah Brown', '9876543213', 'sarah@prime.com', '321 Trade Center', 'Chennai', 'Tamil Nadu', '600001', '33XXXXX0000X1Z5');
