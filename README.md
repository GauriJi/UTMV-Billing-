# MBSBill-billing-software-

Built in open-source PHP and MySQL for XAMPP, MBSBill is a basic GST billing and invoice management system. With a secure login, it facilitates sales, purchase, stock, customer, and GST reports. Perfect for small companies.

# 💰 MBSbill - GST Billing System with Authentication

A complete, professional GST-compliant billing system built with PHP and MySQL. Features user authentication with admin/user roles, purchase entry, sales invoice generation, inventory management, and comprehensive GST calculations (CGST/SGST/IGST).

# **IMPORTANT** Note to link with tally and calender mail me at my mail id given below in support  
## ✨ Features

### Authentication & Security
- 🔐 **User Login System** - Secure authentication with password hashing
- 👤 **Role-Based Access** - Admin and User roles
- 🔒 **Protected Pages** - All pages require authentication
- 🚪 **Session Management** - Secure session handling
- 👋 **User Profile Display** - Shows logged-in user info in sidebar

### Core Functionality
- ✅ **Sales Invoice Generation** with GST calculations
- ✅ **Purchase Entry System** with automatic stock updates
- ✅ **GST Compliance** - Automatic CGST/SGST/IGST calculation based on state
- ✅ **Inventory Management** - Product stock tracking
- ✅ **Customer Management** - Maintain customer database with GSTIN
- ✅ **Supplier Management** - Track suppliers and purchases
- ✅ **Professional Invoice Printing** - Print-ready GST invoices
- ✅ **Reports & Analytics** - Sales and purchase reports with filters

### Technical Features
- Modern, responsive UI design
- Real-time calculations
- Inter-state and intra-state GST handling
- HSN/SAC code support
- Auto-generated invoice/purchase numbers
- Payment status tracking
- Database-driven architecture

## 🚀 Installation Instructions

### Prerequisites
- XAMPP (or any LAMP/WAMP stack)
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Modern web browser

### Step-by-Step Installation

#### 1. Install XAMPP
Download and install XAMPP from [https://www.apachefriends.org/](https://www.apachefriends.org/)

#### 2. Setup Files
1. Copy all project files to `C:\xampp\htdocs\MBSBill\` (Windows)
   or `/opt/lampp/htdocs/MBSBill
   /` (Linux)

2. The directory structure should look like:
```
MBSBill/
├── auth.php                  # Authentication middleware
├── config.php               # Configuration
├── database.php             # Database class
├── login.php                # Login page
├── logout.php               # Logout handler
├── index.php                # Dashboard
├── sales.php                # Sales invoice
├── purchase.php             # Purchase entry
├── invoice_print.php        # Invoice printing
├── products.php             # Product management
├── customers.php            # Customer management
├── suppliers.php            # Supplier management
├── reports.php              # Reports
├── sidebar.php              # Navigation sidebar
├── style.css                # Styles
├── script.js                # JavaScript
└── salesbilling.sql  # Database with users table
```

#### 3. Create Database
1. Start XAMPP Control Panel
2. Start Apache and MySQL services
3. Open browser and go to: `http://localhost/phpmyadmin/`
4. Click on "Import" tab
5. Click "Choose File" and select **`salesbilling.sql`** (NEW FILE with users table)
6. Click "Go" to import the database

**IMPORTANT**: Use the new SQL file `salesbilling.sql` which includes the users table!

#### 4. Configure Database Connection (if needed)
Open `config.php` and verify these settings:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');  // Leave empty for default XAMPP
define('DB_NAME', 'salesbilling');
```

#### 5. Access the Application
Open your browser and navigate to:
```
http://localhost/MBSBill/
```

You will be redirected to the login page.

## 🔐 Default Login Credentials

### Admin Account
- **Username**: `admin`
- **Password**: `admin123`
- **Role**: Administrator (full access)

### User Account
- **Username**: `user`
- **Password**: `user123`
- **Role**: Regular User (limited access)

**IMPORTANT**: Change these passwords after first login in production!

## 👥 User Roles & Permissions

### Admin Role
- Full access to all features
- Can manage products, customers, suppliers
- Can create and view sales and purchases
- Can view all reports
- Can manage system settings

### User Role
- Can create sales invoices
- Can create purchase entries
- Can view products, customers, suppliers
- Can view reports
- Cannot delete critical data (admin only)

## 📊 Default Login Data

The system comes pre-loaded with sample data:

### Default Users (Pre-configured)
- **Admin User**: username: `admin` | password: `admin123`
- **Regular User**: username: `user` | password: `user123`

### Sample Products
- Product A (HSN: 1001, GST: 18%)
- Product B (HSN: 1002, GST: 18%)
- Product C (HSN: 1003, GST: 12%)
- Service Item (HSN: 9983, GST: 18%)

### Sample Customers
- ABC Enterprises (GSTIN: 27XXXXX0000X1Z5)
- XYZ Industries (GSTIN: 07XXXXX0000X1Z5)

### Sample Suppliers
- Global Suppliers (GSTIN: 29XXXXX0000X1Z5)
- Prime Vendors (GSTIN: 33XXXXX0000X1Z5)

## 🎯 Usage Guide

### Logging In

1. Open `http://localhost/MBSBill/`
2. You'll be redirected to the login page
3. Enter username and password
4. Click "Sign In"
5. You'll be redirected to the dashboard

### Creating a Sales Invoice

1. Click on **Sales Invoice** in the sidebar
2. Select customer (or leave as "Walk-in Customer")
3. Choose invoice date and payment status
4. Click **+ Add Item** to add products
5. Select product from dropdown (auto-fills HSN, Rate, GST)
6. Enter quantity
7. System automatically calculates:
   - Amount (Qty × Rate)
   - GST (CGST/SGST for intra-state, IGST for inter-state)
   - Total amount
8. Add notes if needed
9. Click **Save & Print Invoice**
10. Invoice opens in new window ready to print

### Creating a Purchase Entry

1. Click on **Purchase Entry** in the sidebar
2. Select supplier from dropdown
3. Choose purchase date
4. Click **+ Add Item** to add products
5. Select product or enter new item details
6. Enter quantity and rate
7. System calculates GST based on supplier state
8. Click **Save Purchase Entry**
9. Stock is automatically updated

### Logging Out

1. Click the **Logout** button at the bottom of the sidebar
2. Confirm logout
3. You'll be redirected to the login page

## 🔧 Configuration

### Add New Users

To add new users to the system:

1. Go to phpMyAdmin
2. Select `salesbilling` database
3. Open `users` table
4. Click "Insert" tab
5. Fill in the details:
   - **username**: Unique username
   - **password**: Use this PHP code to generate password hash:
     ```php
     echo password_hash('your_password', PASSWORD_DEFAULT);
     ```
   - **full_name**: User's full name
   - **email**: User's email
   - **role**: 'admin' or 'user'
   - **is_active**: 1 (active) or 0 (inactive)

### Change User Password

To change a user's password:

1. Generate new password hash using PHP:
   ```php
   echo password_hash('new_password', PASSWORD_DEFAULT);
   ```
2. Go to phpMyAdmin → users table
3. Edit the user's row
4. Replace the password field with the new hash

### Customize Company Details

1. Go to phpMyAdmin
2. Select `salesbilling` database
3. Open `company_settings` table
4. Edit the single row with your company details:
   - Company Name
   - Address, City, State, Pincode
   - Phone, Email
   - GSTIN
   - Invoice Prefix (default: INV)
   - Purchase Prefix (default: PUR)

## 🎨 Features in Detail

### Authentication System
- **Secure Password Storage**: Uses PHP's `password_hash()` with bcrypt
- **Session Management**: Server-side sessions with security flags
- **Remember Me**: Optional persistent login (if implemented)
- **Auto-redirect**: Logged-in users can't access login page
- **Protected Routes**: All pages check authentication status

### GST Calculation Logic
- **Intra-State**: CGST (9%) + SGST (9%) = 18% total
- **Inter-State**: IGST (18%) = 18% total
- System automatically detects state and applies correct tax

### Stock Management
- Purchase entries **increase** stock
- Sales invoices **decrease** stock
- Real-time stock tracking

### User Profile Display
- Shows user's name and role in sidebar
- Avatar with first letter of name
- Color-coded role badge

## 🔒 Security Features

### Password Security
- Passwords are hashed using bcrypt (PASSWORD_DEFAULT)
- Never stored in plain text
- Uses PHP's built-in `password_verify()` for authentication

### Session Security
- HTTP-only session cookies
- Session validation on every page load
- Auto-logout on session expiry

### SQL Injection Prevention
- All database queries use prepared statements
- PDO with parameter binding
- No direct string concatenation in queries

### XSS Protection
- All user inputs are escaped with `htmlspecialchars()`
- Content Security Policy headers (can be added)

## 🔒 Security Notes for Production

### For Production Use:

1. **Change Default Passwords**:
   ```sql
   UPDATE users SET password = 'NEW_HASH' WHERE username = 'admin';
   ```

2. **Change Database Password**:
   ```php
   define('DB_PASS', 'your-secure-password');
   ```

3. **Disable Error Display** in `config.php`:
   ```php
   error_reporting(0);
   ini_set('display_errors', 0);
   ```

4. **Enable HTTPS** for secure connections

5. **Regular Database Backups**

6. **Add CAPTCHA** to login page to prevent brute force

7. **Implement Password Reset** functionality

8. **Add Activity Logging** to track user actions

## 🐛 Troubleshooting

### Can't Login / Invalid Credentials
- Verify you're using the correct username and password
- Check that the users table was imported correctly
- Default credentials: admin/admin123 or user/user123

### Redirected to Login Page Immediately
- Check if sessions are enabled in PHP
- Verify `session_start()` is called in config.php
- Check browser cookies are enabled

### Database Connection Error
- Verify XAMPP MySQL is running
- Check database credentials in `config.php`
- Ensure database `salesbilling` exists with users table

### Access Denied Error
- You're trying to access admin-only pages with user account
- Login with admin credentials for full access

### Page Not Found (404)
- Check file placement in htdocs folder
- Verify Apache is running in XAMPP
- Check URL: `http://localhost/MBSBill/`

## 📝 Database Schema

### Key Tables

#### users
- User authentication and authorization
- Stores username, hashed password, role

#### products
- Product catalog with HSN and GST rates

#### customers
- Customer information with GSTIN

#### suppliers
- Supplier information

#### sales
- Sales invoice headers with user tracking

#### sales_items
- Individual items in each sale

#### purchases
- Purchase entry headers with user tracking

#### purchase_items
- Individual items in each purchase

#### company_settings
- Company configuration

## 💡 Tips

1. **Regular Backups**: Export database regularly from phpMyAdmin
2. **Change Passwords**: Change default passwords immediately
3. **Test with User Account**: Test features with both admin and user accounts
4. **Monitor Sessions**: Check for unusual login activity
5. **Update Regularly**: Keep PHP and MySQL updated

## 📞 Support


For issues or questions:
1. Check the troubleshooting section
2. Verify XAMPP services are running
3. Check browser console for JavaScript errors
4. Review database connection settings
5. Verify you're using the correct SQL file (salesbilling.sql)
6.  mail me at anupgoswamimb@gmail.com
## 🎉 What's New in This Version

### Added Features
- ✅ Complete user authentication system
- ✅ Login page with beautiful UI
- ✅ Admin and User roles
- ✅ Session management
- ✅ Protected routes
- ✅ User profile display in sidebar
- ✅ Logout functionality
- ✅ Password hashing and security
- ✅ Access control system

### Updated Files
- `salesbilling.sql` - NEW database with users table
- `login.php` - NEW login page
- `logout.php` - NEW logout handler
- `auth.php` - NEW authentication middleware
- All main pages now include authentication checks
- `sidebar.php` - Updated with user profile and logout
- `style.css` - Updated with new styles for auth UI

## 📄 License
This project is open-source and free to use for commercial and personal purposes.

# Screenshot 

<img width="1793" height="909" alt="image" src="https://github.com/user-attachments/assets/3e70a1ab-5edd-49af-9a0f-231ca01e688c" />


## 🎉 Credits

Built with modern web technologies:
- PHP 7.4+ with password_hash()
- MySQL with prepared statements
- HTML5/CSS3
- Vanilla JavaScript
- Google Fonts (Outfit, JetBrains Mono)

---

**Version**: 2.0 (With Authentication)  
**Last Updated**: February 2026  
**Tested On**: XAMPP 8.2.4, PHP 8.2, MySQL 8.0

Happy Billing! 💰✨🔐
