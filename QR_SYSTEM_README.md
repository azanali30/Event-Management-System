# 🎯 QR Code System - Complete Setup Guide

A comprehensive QR code generation and scanning system for event attendance management.

## 🚀 Quick Start

### 1. **Setup Test Data**
```
http://localhost/event/add_test_data.php
```
This will create sample users, events, and registrations for testing.

### 2. **Test QR Generation**
```
http://localhost/event/test_qr_system.php
```
View all registrations and generate QR codes.

### 3. **QR Scanner (Admin)**
```
http://localhost/event/admin/qr_scanner.php
```
Scan QR codes to mark attendance.

## 📁 System Files

### **Core QR Generation**
- **`download_qr.php`** - Basic QR code generator
- **`download_qr_canva_style.php`** - Enhanced QR with styling options
- **`canva_qr_generator.js`** - JavaScript QR generation library

### **Admin Tools**
- **`admin/qr_scanner.php`** - QR code scanner for attendance
- **`test_qr_system.php`** - Testing interface
- **`add_test_data.php`** - Database setup script

### **Demo Pages**
- **`canva_qr_demo.php`** - Interactive QR customization
- **`qr_integration_example.php`** - Integration examples

## 🔧 Database Requirements

The system uses these tables:
- **`users`** - User accounts
- **`userdetails`** - User profile information
- **`events`** - Event information
- **`registrations`** - Event registrations
- **`attendance`** - Attendance tracking

## 🎨 QR Code Features

### **Basic QR Codes**
- High-resolution PNG output
- Error correction level M
- Secure data encoding
- Automatic filename generation

### **Canva-Style QR Codes**
- **Design Styles**: Professional, Modern, Colorful, Minimal
- **Color Schemes**: 6 pre-designed themes
- **Sizes**: Small (200px) to XL (800px)
- **Frame Options**: None, Square, Rounded, Circle
- **Logo Integration**: Optional logo embedding

### **QR Data Format**
```
REG:123
EVENT:Tech Conference 2024
STUDENT:John Doe
ID:1
DATE:2024-12-15
TIME:09:00:00
VENUE:Main Auditorium
STATUS:CONFIRMED
HASH:a1b2c3d4
```

## 📱 QR Scanner Features

### **Camera Scanning**
- Real-time camera feed
- Automatic QR detection
- Front/back camera switching
- Mobile-optimized interface

### **Manual Input**
- Paste QR data directly
- Bulk processing support
- Error validation

### **Attendance Tracking**
- Automatic attendance marking
- Duplicate detection
- Real-time statistics
- Audit logging

## 🛠️ Installation Steps

### 1. **File Setup**
Place all files in your event management directory:
```
/event/
├── download_qr.php
├── download_qr_canva_style.php
├── canva_qr_generator.js
├── admin/qr_scanner.php
├── test_qr_system.php
├── add_test_data.php
└── canva_qr_demo.php
```

### 2. **Database Setup**
Run the test data script to create sample data:
```
http://localhost/event/add_test_data.php
```

### 3. **Test the System**
1. Visit `test_qr_system.php` to see available registrations
2. Generate QR codes using the buttons
3. Go to `admin/qr_scanner.php` to scan codes
4. Check attendance in the database

## 🔐 Security Features

### **Access Control**
- Session-based authentication
- Role-based permissions (admin/user)
- Registration ownership validation

### **Data Protection**
- Hash verification in QR codes
- SQL injection prevention
- Input validation and sanitization

### **Audit Trail**
- QR generation logging
- Attendance marking logs
- Error tracking and reporting

## 📊 Usage Examples

### **Generate Basic QR Code**
```php
$qr_url = "download_qr.php?registration_id=123";
echo "<img src='$qr_url' alt='QR Code'>";
```

### **Generate Styled QR Code**
```php
$params = [
    'registration_id' => 123,
    'style' => 'modern',
    'color' => 'blue',
    'size' => 'large',
    'frame' => 'rounded'
];
$qr_url = "download_qr_canva_style.php?" . http_build_query($params);
```

### **JavaScript QR Generation**
```javascript
const qrGenerator = new CanvaQRGenerator();
const qrUrl = await qrGenerator.generateQR(qrData, {
    style: 'professional',
    colorScheme: 'blue',
    size: 'medium'
});
```

## 🧪 Testing Workflow

### **1. Setup Phase**
1. Run `add_test_data.php` to create test data
2. Verify database tables are populated
3. Check user accounts are created

### **2. QR Generation Testing**
1. Visit `test_qr_system.php`
2. Try different QR generation methods
3. Download and save QR codes
4. Verify QR data format

### **3. Scanner Testing**
1. Open `admin/qr_scanner.php`
2. Allow camera permissions
3. Scan generated QR codes
4. Test manual input feature
5. Verify attendance records

### **4. Integration Testing**
1. Test with real user sessions
2. Verify permission controls
3. Check error handling
4. Test mobile compatibility

## 🚨 Troubleshooting

### **QR Generation Issues**
- **"Column not found" error**: Database schema mismatch - check table names
- **"phpqrcode library not found"**: Install phpqrcode library
- **Empty QR codes**: Check registration data exists

### **Scanner Issues**
- **Camera not working**: Check browser permissions
- **QR not detected**: Ensure good lighting and focus
- **"Registration not found"**: Verify QR data format

### **Database Issues**
- **Connection failed**: Check database configuration
- **Missing tables**: Run database setup scripts
- **Permission denied**: Check user roles and sessions

## 📈 Performance Tips

### **QR Generation**
- Use appropriate image sizes for your needs
- Cache generated QR codes when possible
- Use API fallbacks for reliability

### **Scanner Performance**
- Good lighting improves scan speed
- Hold camera steady for better detection
- Use manual input for problematic codes

## 🔄 API Endpoints

### **QR Generation**
```
GET /download_qr.php?registration_id=123
GET /download_qr_canva_style.php?registration_id=123&style=modern&color=blue
```

### **QR Scanning**
```
POST /admin/qr_scanner.php
{
    "action": "scan_qr",
    "qr_data": "REG:123\nEVENT:..."
}
```

## 🎯 Next Steps

1. **Customize Styling**: Modify color schemes and designs
2. **Add Logo**: Place your logo for QR embedding
3. **Mobile App**: Consider mobile app integration
4. **Bulk Operations**: Add batch QR generation
5. **Analytics**: Add detailed reporting features

## 📞 Support

### **Test Credentials**
- **Admin**: admin@example.com / admin123
- **Student**: john.doe@example.com / password123

### **Quick Links**
- [QR System Test](test_qr_system.php)
- [QR Scanner](admin/qr_scanner.php)
- [Canva QR Demo](canva_qr_demo.php)
- [Setup Test Data](add_test_data.php)

---

**🎉 Your QR code system is ready for production use!**

The system provides reliable QR generation, professional styling options, and comprehensive attendance tracking with a user-friendly scanner interface.
