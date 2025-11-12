# 🔐 Forgot Password System Documentation

## Overview

This forgot password system provides secure password reset functionality using OTP (One-Time Password) verification via Email and Mobile SMS. The system includes rate limiting, security logging, and comprehensive error handling.

## 🚀 Quick Start

### 1. Database Setup
Run the database setup script to create required tables:
```
http://localhost/event/setup_forgot_password_tables.php
```

### 2. Test the System
Use the test page to verify functionality:
```
http://localhost/event/test_forgot_password.php
```

### 3. Access Forgot Password
Users can access the forgot password page from:
```
http://localhost/event/forgot_password.php
```

## 📁 Files Structure

```
├── forgot_password.php              # Main forgot password interface
├── includes/
│   ├── OTPService.php              # Core OTP service class
│   └── EmailService.php            # Email service (already exists)
├── test_forgot_password.php        # Testing interface
├── event/
│   └── setup_forgot_password_tables.php  # Database setup script
└── FORGOT_PASSWORD_README.md       # This documentation
```

## 🗄️ Database Tables

### `password_reset_otps`
Stores OTP codes and verification status:
- `id` - Primary key
- `user_id` - User ID from users table
- `email` - User email address
- `mobile` - User mobile number (optional)
- `otp_code` - 6-digit OTP code
- `otp_type` - 'email' or 'mobile'
- `token` - Unique verification token
- `expires_at` - OTP expiration time
- `is_verified` - Verification status
- `is_used` - Usage status
- `attempts` - Verification attempts count

### `password_reset_logs`
Logs all password reset activities:
- `id` - Primary key
- `user_id` - User ID
- `email` - User email
- `mobile` - User mobile (optional)
- `action` - Action type (otp_sent, otp_verified, password_reset, etc.)
- `otp_type` - 'email' or 'mobile'
- `ip_address` - User IP address
- `user_agent` - User browser info
- `details` - JSON details

### `sms_config`
SMS provider configuration:
- `id` - Primary key
- `provider` - SMS provider name
- `api_key` - Provider API key
- `api_secret` - Provider API secret
- `sender_id` - SMS sender ID
- `base_url` - Provider API URL
- `is_active` - Active status

### `sms_usage`
SMS usage tracking:
- `id` - Primary key
- `user_id` - User ID
- `mobile` - Mobile number
- `message` - SMS message content
- `provider` - SMS provider used
- `status` - Delivery status
- `cost` - SMS cost

## 🔧 Configuration

### Email Configuration
The system uses the existing `EmailService` class. Make sure your email configuration is set up in:
- `config/email_config.php`

### SMS Configuration
Currently uses a mock SMS service. To integrate with a real SMS provider:

1. Update the `sendOTPSMS()` method in `OTPService.php`
2. Configure SMS provider settings in the `sms_config` table
3. Implement actual SMS gateway integration (Twilio, AWS SNS, etc.)

## 🔒 Security Features

### Rate Limiting
- **Daily OTP Limit:** 10 OTPs per user per day
- **Verification Attempts:** Maximum 5 attempts per OTP
- **OTP Expiry:** 15 minutes from generation
- **Reset Token Expiry:** 30 minutes after verification

### Security Logging
All activities are logged including:
- OTP generation and sending
- Verification attempts (success/failure)
- Password reset actions
- IP addresses and user agents

### Data Protection
- OTP codes are not stored in plain text logs
- Tokens are cryptographically secure
- Mobile numbers are masked in responses
- Session-based flow prevents token reuse

## 🎯 Usage Flow

### Email OTP Flow
1. User enters email address
2. System validates email exists in database
3. Generates 6-digit OTP and secure token
4. Sends OTP via email
5. User enters OTP code
6. System verifies OTP and marks token as verified
7. User sets new password
8. Password is updated and token is marked as used

### Mobile OTP Flow
1. User enters mobile number
2. System validates mobile exists in database
3. Generates 6-digit OTP and secure token
4. Sends OTP via SMS (currently mocked)
5. User enters OTP code
6. System verifies OTP and marks token as verified
7. User sets new password
8. Password is updated and token is marked as used

## 🧪 Testing

### Using the Test Interface
1. Open `test_forgot_password.php`
2. Test each component individually:
   - Email OTP generation
   - Mobile OTP generation
   - OTP verification
   - Password reset

### Finding OTP Codes
During testing, OTP codes are logged to:
- Server error logs
- Console output
- Email files (if SMTP fails, saved in `logs/` directory)

### Test Data Requirements
- Email addresses must exist in the `users` table
- Mobile numbers must exist in the `userdetails` table
- Make sure database tables are created first

## 🔍 Troubleshooting

### Common Issues

**Database Connection Errors:**
- Check `config/database.php` configuration
- Ensure MySQL service is running
- Verify database credentials

**Email Not Sending:**
- Check `config/email_config.php` settings
- Verify SMTP credentials
- Check `logs/` directory for saved email files

**OTP Not Found:**
- Check server error logs for generated OTP codes
- Verify user exists in database
- Check OTP expiry time (15 minutes)

**Tables Not Created:**
- Run the database setup script
- Check MySQL user permissions
- Verify database exists

### Debug Mode
Enable debug logging by checking:
- Server error logs
- `logs/email_service.log`
- `logs/` directory for email files

## 🚀 Production Deployment

### Before Going Live:

1. **Configure Real SMTP:**
   - Set up proper SMTP credentials
   - Test email delivery

2. **Integrate SMS Provider:**
   - Choose SMS provider (Twilio, AWS SNS, etc.)
   - Update `sendOTPSMS()` method
   - Configure provider credentials

3. **Security Review:**
   - Review rate limits
   - Check logging configuration
   - Verify SSL/HTTPS setup

4. **Performance:**
   - Add database indexes if needed
   - Monitor OTP table growth
   - Set up log rotation

### Recommended Settings:
- Use HTTPS for all password reset pages
- Implement CAPTCHA for additional security
- Set up monitoring for failed attempts
- Regular cleanup of expired OTP records

## 📞 Support

For issues or questions:
1. Check the test interface for system status
2. Review server error logs
3. Verify database table structure
4. Test email configuration

## 🔄 Updates and Maintenance

### Regular Maintenance:
- Clean up expired OTP records
- Monitor SMS usage and costs
- Review security logs for suspicious activity
- Update rate limits as needed

### Database Cleanup Query:
```sql
-- Clean up expired OTPs older than 24 hours
DELETE FROM password_reset_otps 
WHERE expires_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);

-- Clean up old logs older than 30 days
DELETE FROM password_reset_logs 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

---

**Version:** 1.0  
**Last Updated:** 2024-01-15  
**Compatibility:** PHP 7.4+, MySQL 5.7+
