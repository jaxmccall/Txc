# Tripple Exchange - Complete Cryptocurrency Trading Platform

A comprehensive cryptocurrency trading platform with admin management, KYC verification, and advanced trading features.

## 🚀 Features

### User Features
- **User Registration & Authentication**
  - Email/username based registration
  - Secure login with password validation
  - Real user data collection (name, phone, country)
  - Account starts with balance=0, score=100

- **KYC Verification System**
  - Selfie upload (webcam capture or file upload)
  - ID document upload (front/back/additional files)
  - Real-time file validation and status tracking
  - Professional verification workflow

- **Trading Interface**
  - Modern, responsive trading dashboard
  - Portfolio management
  - Balance tracking for multiple cryptocurrencies
  - Trade history and analytics

- **Profile Management**
  - Real user data display (no dummy data)
  - Profile editing capabilities
  - Activity history with transaction details
  - Account information and statistics

### Admin Features
- **Comprehensive Admin Dashboard**
  - User management (view, edit, create)
  - Balance and credit score management
  - User impersonation functionality
  - Platform statistics and analytics

- **KYC Management**
  - Document review and approval system
  - Individual document status updates
  - KYC statistics and reporting
  - Admin notes and feedback

- **Trade Management**
  - Complete trade oversight
  - Win/loss marking functionality
  - Trading pair management
  - Profit/loss calculations with balance adjustments

- **Notification System**
  - Admin-to-user notifications
  - Bulk notification sending
  - Notification templates
  - Real-time notification counts

- **Audit & Logging**
  - Complete admin action logging
  - User activity tracking
  - Session management
  - Security monitoring

## 📋 Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- SSL certificate (recommended for production)

## 🛠️ Installation

### 1. Database Setup

1. **Configure Database Connection**
   - Update `config.php` with your MySQL credentials
   - For Hostinger, use the provided database details

2. **Run Database Setup**
   ```bash
   # Option 1: Run setup script via web browser
   # Navigate to: https://yoursite.com/setup-database.php
   
   # Option 2: Import SQL directly
   mysql -u your_username -p your_database < complete_database_schema.sql
   ```

### 2. File Permissions

```bash
# Create uploads directory and set permissions
mkdir -p uploads/kyc
chmod 755 uploads
chmod 755 uploads/kyc
```

### 3. Security Configuration

1. **Update Database Credentials**
   ```php
   // config.php
   $DB_HOST = 'your_host';
   $DB_USER = 'your_username';
   $DB_PASS = 'your_password';
   $DB_NAME = 'your_database';
   ```

2. **Configure File Upload Security**
   - Ensure `uploads/kyc/` directory exists
   - Set appropriate file permissions
   - Configure max file size in PHP settings

### 4. Default Admin Account

After database setup, use these credentials:
- **Username:** `admin`
- **Password:** `admin@123`
- **Email:** `admin@trippleexchange.com`

**⚠️ IMPORTANT:** Change the default admin password immediately after first login.

## 📁 Project Structure

```
Tripple Exchange/
├── admin-dashboard-enhanced.html    # Main admin interface
├── admin-users-api.php             # User management API
├── admin-kyc-api.php              # KYC management API
├── admin-trades-api.php           # Trade management API
├── admin-notifications-api.php    # Notification system API
├── kyc-enhanced.html              # Enhanced KYC interface
├── kyc-upload.php                 # File upload handler
├── signup.php                     # User registration
├── login.php                      # User authentication
├── profile-api.php                # Profile management
├── user-notifications-api.php     # User notifications
├── complete_database_schema.sql   # Complete database setup
├── setup-database.php            # Database setup script
└── uploads/kyc/                   # KYC document storage
```

## 🔧 Configuration

### Environment Setup

1. **Production Environment**
   ```php
   // For production, update config.php with production settings
   ini_set('display_errors', 0);
   error_reporting(0);
   ```

2. **Development Environment**
   ```php
   // For development, enable error reporting
   ini_set('display_errors', 1);
   error_reporting(E_ALL);
   ```

### File Upload Configuration

```php
// Recommended PHP settings for file uploads
upload_max_filesize = 5M
post_max_size = 5M
max_execution_time = 30
memory_limit = 128M
```

## 🎯 Usage

### For Users

1. **Registration**
   - Visit `signup.html`
   - Complete registration with real details
   - Account starts with balance=0, score=100

2. **KYC Verification**
   - Visit `kyc-enhanced.html`
   - Upload selfie and ID documents
   - Wait for admin approval

3. **Trading**
   - Access dashboard after KYC approval
   - View balance and trade history
   - Manage profile and settings

### For Administrators

1. **Access Admin Panel**
   - Visit `admin-login.html`
   - Login with admin credentials
   - Access `admin-dashboard-enhanced.html`

2. **User Management**
   - View all registered users
   - Edit user details and balances
   - Impersonate users for support

3. **KYC Management**
   - Review submitted documents
   - Approve or reject verifications
   - Add admin notes and feedback

4. **Trade Management**
   - Monitor all trades
   - Mark trades as win/loss
   - Adjust balances and profits

5. **Send Notifications**
   - Send notifications to users
   - Use templates or custom messages
   - Target specific user groups

## 🔒 Security Features

- **Authentication**
  - Secure password hashing
  - Session management
  - Auto-logout on inactivity

- **File Upload Security**
  - File type validation
  - Size limits
  - Secure storage

- **Admin Security**
  - Role-based access control
  - Action logging
  - Impersonation tracking

- **Data Protection**
  - Input sanitization
  - SQL injection prevention
  - XSS protection

## 📊 API Endpoints

### User APIs
- `profile-api.php` - Profile management
- `kyc-upload.php` - KYC document upload
- `get-kyc-status.php` - KYC status checking
- `user-notifications-api.php` - User notifications

### Admin APIs
- `admin-users-api.php` - User management
- `admin-kyc-api.php` - KYC management
- `admin-trades-api.php` - Trade management
- `admin-notifications-api.php` - Notification system
- `admin-stats-api.php` - Platform statistics

## 🚀 Deployment

### Hostinger Deployment

1. Upload all files to your hosting directory
2. Import the database schema
3. Update `config.php` with Hostinger database credentials
4. Set file permissions for uploads directory
5. Access the platform via your domain

### SSL Configuration

Ensure SSL is properly configured for secure data transmission, especially for:
- Login/registration forms
- File uploads
- Admin panel access

## 🆘 Support

### Common Issues

1. **Database Connection Errors**
   - Verify credentials in `config.php`
   - Check database server status
   - Ensure database exists

2. **File Upload Issues**
   - Check directory permissions
   - Verify PHP upload settings
   - Ensure adequate disk space

3. **Login Problems**
   - Clear browser cache/cookies
   - Verify user credentials
   - Check session settings

### Troubleshooting

- Check PHP error logs
- Verify database connectivity
- Test file permissions
- Review admin action logs

## 🔄 Updates & Maintenance

- Regular database backups
- Monitor file upload directory
- Review admin action logs
- Update admin passwords regularly
- Keep PHP and MySQL updated

## 📝 License

This project is proprietary software developed for Tripple Exchange.

## 🤝 Contributing

This is a private project. For support or modifications, contact the development team.

---

**Tripple Exchange** - Professional Cryptocurrency Trading Platform