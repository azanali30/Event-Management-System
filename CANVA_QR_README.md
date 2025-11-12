# 🎨 Canva-Style QR Code Generator

A professional, feature-rich QR code generation system with Canva-style design customization options for your Event Management System.

## ✨ Features

### 🎯 **Professional Design Options**
- **Multiple Styles**: Professional, Modern, Colorful, Minimal
- **Color Schemes**: 6 pre-designed color themes (Default, Blue, Green, Red, Purple, Orange)
- **Size Options**: Small (200px), Medium (400px), Large (600px), XL (800px)
- **Frame Styles**: None, Square, Rounded, Circle
- **Logo Integration**: Optional logo embedding

### ⚡ **Reliable Generation**
- **Multiple APIs**: QR Server, QuickChart, Custom Design APIs
- **Fallback System**: Automatic fallback to ensure QR codes are always generated
- **High Quality**: Professional-grade output with error correction
- **Mobile Optimized**: Perfect scanning on all devices

### 🔒 **Security & Integration**
- **Secure Authentication**: User session validation
- **Data Validation**: Comprehensive input validation
- **Error Handling**: Graceful error handling with logging
- **Database Integration**: Seamless integration with existing system

## 📁 Files Overview

### Core Files
- **`download_qr_canva_style.php`** - Enhanced server-side QR generator
- **`canva_qr_generator.js`** - Client-side JavaScript QR generator
- **`canva_qr_demo.php`** - Interactive demo page
- **`qr_integration_example.php`** - Integration example

## 🚀 Quick Start

### 1. **Basic Usage (Server-side)**

```php
// Generate QR code with custom styling
$qr_url = "download_qr_canva_style.php?" . http_build_query([
    'registration_id' => 123,
    'style' => 'professional',
    'color' => 'blue',
    'size' => 'medium',
    'frame' => 'rounded',
    'logo' => 'true'
]);

echo "<img src='$qr_url' alt='QR Code'>";
```

### 2. **JavaScript Usage (Client-side)**

```javascript
// Initialize generator
const qrGenerator = new CanvaQRGenerator();

// Generate QR code
const qrData = JSON.stringify({
    type: 'event_registration',
    registration_id: 123,
    student_name: 'John Doe',
    event_name: 'Tech Conference 2024'
});

const qrImageUrl = await qrGenerator.generateQR(qrData, {
    style: 'modern',
    colorScheme: 'blue',
    size: 'large',
    frameStyle: 'rounded'
});

document.getElementById('qr-preview').innerHTML = 
    `<img src="${qrImageUrl}" alt="QR Code">`;
```

## 🎨 Customization Options

### **Design Styles**
- **Professional**: Clean, business-appropriate design
- **Modern**: Contemporary styling with rounded elements
- **Colorful**: Vibrant colors and dynamic styling
- **Minimal**: Simple, clean design with minimal elements

### **Color Schemes**
```javascript
const colorSchemes = {
    default: { fg: '#000000', bg: '#ffffff', accent: '#333333' },
    blue: { fg: '#1e40af', bg: '#f0f9ff', accent: '#3b82f6' },
    green: { fg: '#166534', bg: '#f0fdf4', accent: '#22c55e' },
    red: { fg: '#dc2626', bg: '#fef2f2', accent: '#ef4444' },
    purple: { fg: '#7c3aed', bg: '#faf5ff', accent: '#a855f7' },
    orange: { fg: '#ea580c', bg: '#fff7ed', accent: '#f97316' }
};
```

### **Size Configurations**
```javascript
const sizeConfigs = {
    small: { size: 200, pixel: 6, margin: 8 },
    medium: { size: 400, pixel: 10, margin: 10 },
    large: { size: 600, pixel: 15, margin: 12 },
    xl: { size: 800, pixel: 20, margin: 15 }
};
```

## 🔧 API Endpoints

### **Server-side Generator**
```
GET /download_qr_canva_style.php
```

**Parameters:**
- `registration_id` (required) - Registration ID
- `style` (optional) - Design style: professional, modern, colorful, minimal
- `color` (optional) - Color scheme: default, blue, green, red, purple, orange
- `size` (optional) - Size: small, medium, large, xl
- `frame` (optional) - Frame style: none, square, rounded, circle
- `logo` (optional) - Include logo: true, false

**Example:**
```
/download_qr_canva_style.php?registration_id=123&style=modern&color=blue&size=large&frame=rounded&logo=true
```

## 🛠️ Installation & Setup

### 1. **File Placement**
Place all files in your event management system root directory:
```
/event/
├── download_qr_canva_style.php
├── canva_qr_generator.js
├── canva_qr_demo.php
├── qr_integration_example.php
└── CANVA_QR_README.md
```

### 2. **Dependencies**
- PHP 7.4+ with cURL support
- Existing database connection (`config/database.php`)
- Session management
- Internet connection for API-based generation

### 3. **Optional Enhancements**
- **Logo File**: Place your logo at `assets/images/logo.png`
- **QRCode.js**: Include for client-side fallback generation
- **Font Awesome**: For icons in demo pages

## 📱 Usage Examples

### **Integration in Registration System**
```php
// In your registration confirmation page
if ($registration['status'] === 'approved') {
    $qr_params = [
        'registration_id' => $registration['id'],
        'style' => 'professional',
        'color' => 'blue',
        'size' => 'medium'
    ];
    
    echo '<div class="qr-section">';
    echo '<h3>Your QR Code</h3>';
    echo '<img src="download_qr_canva_style.php?' . http_build_query($qr_params) . '">';
    echo '<p>Show this QR code at the event entrance</p>';
    echo '</div>';
}
```

### **Batch QR Generation**
```javascript
// Generate QR codes for multiple registrations
const registrations = [/* your registration data */];

for (const registration of registrations) {
    const qrData = QRUtils.createRegistrationData(registration);
    const qrUrl = await qrGenerator.generateQR(qrData, {
        style: 'professional',
        colorScheme: 'default',
        size: 'medium'
    });
    
    // Save or display QR code
    console.log(`QR for ${registration.student_name}: ${qrUrl}`);
}
```

## 🔍 Testing

### **Demo Page**
Visit `canva_qr_demo.php` to test all features:
- Interactive customization options
- Real-time QR code generation
- Download functionality
- Multiple design previews

### **Integration Example**
Visit `qr_integration_example.php` to see how the generator integrates with your existing system:
- User registration display
- Dynamic QR generation
- Download and sharing features

## 🚨 Troubleshooting

### **Common Issues**

1. **QR Code Not Generating**
   - Check internet connection (APIs require internet)
   - Verify registration_id exists in database
   - Check PHP error logs

2. **Permission Denied**
   - Ensure user is logged in
   - Verify user has access to the registration

3. **API Failures**
   - System automatically falls back to other methods
   - Check error logs for specific API issues

### **Error Logging**
All errors are logged to PHP error log:
```php
error_log("QR generation error: " . $error_message);
```

## 🎯 Next Steps

1. **Test the demo page**: `canva_qr_demo.php`
2. **Integrate into your system**: Use `qr_integration_example.php` as reference
3. **Customize colors and styles**: Modify color schemes in the code
4. **Add your logo**: Place logo file and update `getLogoUrl()` function
5. **Deploy to production**: Test thoroughly before going live

## 📞 Support

For issues or questions:
1. Check the troubleshooting section
2. Review PHP error logs
3. Test with the demo page first
4. Verify all dependencies are installed

---

**🎉 Your Canva-style QR code generator is ready to use!**

The system provides professional, customizable QR codes with multiple fallback methods to ensure reliability. Perfect for event management, registration systems, and any application requiring high-quality QR codes.
