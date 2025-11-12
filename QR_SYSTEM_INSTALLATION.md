# QR Code Attendance System - Installation Guide

## 🎯 Overview

This QR code attendance system integrates with your existing event management system to provide:

- **QR Code Generation** for approved registrations
- **Admin Panel** for QR management and downloads
- **Attendance Tracking** via QR code scanning
- **Canva Integration** prompts for custom QR designs

## 📋 Prerequisites

1. **Existing Event Management System** (your current setup)
2. **PHP 7.4+** with PDO extension
3. **MySQL/MariaDB** database
4. **Composer** for dependency management
5. **Web server** (Apache/Nginx)

## 🚀 Installation Steps

### Step 1: Install Dependencies

Run this command in your project root:

```bash
composer require endroid/qr-code
```

If you don't have composer.json, create it first:

```bash
composer init
composer require endroid/qr-code
```

### Step 2: Database Setup

Run the setup script to update your database:

```
http://localhost/event/setup_qr_system.php
```

Or manually run the SQL:

```sql
ALTER TABLE `registrations` 
ADD COLUMN `uid` VARCHAR(50) UNIQUE NULL AFTER `status`,
ADD COLUMN `qr_path` VARCHAR(255) NULL AFTER `uid`,
ADD COLUMN `attendance_status` ENUM('absent', 'present') DEFAULT 'absent' AFTER `qr_path`,
ADD COLUMN `attendance_time` TIMESTAMP NULL AFTER `attendance_status`,
ADD COLUMN `attendance_ip` VARCHAR(45) NULL AFTER `attendance_time`;
```

### Step 3: File Permissions

Ensure the `qr_codes/` directory has proper permissions:

```bash
mkdir qr_codes
chmod 755 qr_codes
```

### Step 4: Test the System

1. **Access Admin Panel**: `http://localhost/event/admin/qr_management.php`
2. **Generate QR Code** for a confirmed registration
3. **Download QR Code** using the download button
4. **Test Attendance**: Scan QR code or visit the attendance URL

## 📁 File Structure

```
your-project/
├── admin/
│   ├── qr_management.php          # Main QR management panel
│   └── get_canva_prompt.php       # Canva prompt generator
├── includes/
│   └── QRCodeGenerator.php        # QR code generation class
├── qr_codes/                      # QR code storage directory
├── attendance.php                 # QR scanning & attendance marking
├── download_qr_code.php          # Secure QR download
├── setup_qr_system.php           # One-time setup script
└── vendor/                       # Composer dependencies
```

## 🎯 How It Works

### 1. Registration Approval
- Admin approves registrations in your existing system
- System generates unique UID for approved registrations

### 2. QR Code Generation
- Admin clicks "Generate QR Code" in the management panel
- System creates QR code with attendance URL: `attendance.php?uid=USER123`
- QR code saved as PNG file in `qr_codes/` directory

### 3. QR Code Distribution
- **Option A**: Download QR code directly from admin panel
- **Option B**: Copy Canva prompt and create custom QR in Canva
- **Option C**: Email QR code to participants

### 4. Attendance Marking
- Participant scans QR code
- System validates UID and marks attendance
- Prevents duplicate attendance marking
- Logs IP address and timestamp

## 🔧 Admin Panel Features

### QR Management Dashboard
- View all registrations with QR status
- Generate QR codes for approved registrations
- Download QR codes as PNG files
- Copy Canva prompts for custom QR design
- Delete and regenerate QR codes
- View attendance statistics

### Canva Integration
- One-click copy of formatted prompt for Canva
- Includes attendance URL and instructions
- Allows custom QR design while maintaining functionality

## 🛡️ Security Features

- **UID Validation**: Unique identifiers prevent tampering
- **Admin Authentication**: Only authenticated admins can manage QR codes
- **File Protection**: QR codes stored in protected directory
- **IP Logging**: Track attendance marking with IP addresses
- **Duplicate Prevention**: Prevents multiple attendance marking

## 📱 Mobile-Friendly

- Responsive design for all interfaces
- Mobile-optimized QR scanning
- Touch-friendly admin controls
- Works on all devices and browsers

## 🔍 Troubleshooting

### Common Issues

1. **"Class not found" error**
   - Run: `composer require endroid/qr-code`
   - Check if `vendor/autoload.php` exists

2. **QR codes not generating**
   - Check `qr_codes/` directory permissions (755)
   - Verify database columns were added
   - Check PHP error logs

3. **Download not working**
   - Ensure admin authentication is working
   - Check file permissions on `qr_codes/` directory
   - Verify QR code files exist

4. **Attendance not marking**
   - Check if registration status is 'confirmed'
   - Verify UID format and database entry
   - Check database connection

### Debug Steps

1. Run `setup_qr_system.php` to check system status
2. Check PHP error logs for detailed errors
3. Verify database table structure
4. Test with a simple registration first

## 🎨 Customization

### Styling
- Modify CSS in admin panel files
- Customize attendance page design
- Add your branding and colors

### Functionality
- Extend QRCodeGenerator class for custom features
- Add email notifications for attendance
- Integrate with existing user dashboard

## 📞 Support

If you encounter issues:

1. Check the troubleshooting section above
2. Verify all installation steps were completed
3. Check PHP and database error logs
4. Ensure all dependencies are installed

## 🎉 Success!

Once installed, you'll have a complete QR code attendance system that:

- ✅ Generates professional QR codes
- ✅ Provides secure download functionality
- ✅ Tracks attendance automatically
- ✅ Integrates with Canva for custom designs
- ✅ Works on all devices
- ✅ Maintains security and prevents fraud

Your event management system is now enhanced with powerful QR code attendance tracking!
