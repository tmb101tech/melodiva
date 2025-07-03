# Melodiva Skincare E-commerce Setup Guide

## Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- Composer (optional, for additional packages)

## Installation Steps

### 1. Database Setup
1. Create a new MySQL database named `melodiva_skincare`
2. Import the database schema from `database/schema.sql`
3. Update database credentials in `config/database.php`

### 2. File Permissions
Set proper permissions for upload directories:
\`\`\`bash
chmod 755 uploads/
chmod 755 uploads/products/
chmod 755 uploads/payments/
\`\`\`

### 3. Configuration
1. Update `config/database.php` with your database credentials
2. Set your site URL in the configuration
3. Configure SMTP settings for email notifications
4. Add your Paystack API keys

### 4. Admin Account
Default admin credentials:
- Username: admin
- Password: admin123

**Important: Change these credentials immediately after first login!**

### 5. Email Configuration
Update the email settings in `config/database.php`:
- SMTP_HOST: Your SMTP server
- SMTP_PORT: Usually 587 for TLS
- SMTP_USER: Your email address
- SMTP_PASS: Your email password or app password

### 6. Payment Integration
1. Sign up for a Paystack account at https://paystack.com
2. Get your public and secret keys
3. Update the keys in `config/database.php`

## Features Overview

### Public Features
- Product catalog with search and filtering
- User registration and authentication
- Shopping cart with affiliate code support
- Checkout with Paystack and bank transfer options
- Order tracking
- Affiliate program application

### Admin Features
- Dashboard with sales analytics
- Product management (add/edit/delete)
- Order management and status updates
- Affiliate approval and tracking
- Customer management
- Settings configuration

### Affiliate Features
- Unique affiliate code generation
- Commission tracking
- Sales dashboard
- Payout requests

## Product Management

### Adding Products
1. Login to admin panel
2. Go to Products > Add Product
3. Fill in product details:
   - Name (e.g., "Black Soap")
   - Type (Exquisite, Perfume, Natural, Pure)
   - Size (250g, 500g, 250ml, 500ml, 1000ml)
   - Price in Nigerian Naira
   - Stock quantity
   - Description
   - Product image

### Sample Products
The system comes with pre-loaded sample products:

**Black Soap:**
- Exquisite 250g: ₦2,500
- Exquisite 500g: ₦4,500
- Perfume 250g: ₦2,200
- Perfume 500g: ₦4,000
- Natural 250g: ₦2,000
- Natural 500g: ₦3,800

**Kernel Oil:**
- Pure 250ml: ₦1,500
- Pure 500ml: ₦2,800
- Pure 1000ml: ₦5,000

### Editing Prices
1. Go to Admin > Products
2. Click edit button on any product
3. Update price and save
4. Changes reflect immediately on the website

## Commission Structure

### Affiliate Commissions
- ₦1,000 per 2kg of Black Soap sold
- ₦1,000 per 1L of Kernel Oil sold
- Proportional calculation for smaller quantities

### Example Calculations
- 250g Black Soap = ₦125 commission (250g ÷ 2000g × ₦1,000)
- 500ml Kernel Oil = ₦500 commission (500ml ÷ 1000ml × ₦1,000)

## Order Management

### Order Statuses
1. **Pending Verification** - New orders awaiting admin review
2. **Processing** - Order confirmed and being prepared
3. **Shipped** - Order dispatched to customer
4. **Delivered** - Order received by customer
5. **Cancelled** - Order cancelled

### Processing Orders
1. Admin reviews new orders in dashboard
2. For bank transfers, verify payment proof
3. Update order status as it progresses
4. System automatically calculates affiliate commissions

## Delivery Fees
- Lagos: ₦500
- Other states: ₦1,500
- Configurable in admin settings

## Interest Rate
- 2% interest added to subtotal
- Configurable in admin settings

## Security Features
- Password hashing using PHP's password_hash()
- SQL injection prevention with prepared statements
- XSS protection with htmlspecialchars()
- CSRF protection for forms
- Session-based authentication

## Troubleshooting

### Common Issues
1. **Database connection error**: Check credentials in config/database.php
2. **Image upload fails**: Check folder permissions for uploads/
3. **Email not sending**: Verify SMTP settings
4. **Paystack not working**: Check API keys and internet connection

### Error Logs
Check your web server error logs for detailed error information.

## Customization

### Changing Colors
Update CSS variables in `assets/css/style.css`:
\`\`\`css
:root {
  --primary-green: #2e7d32;
  --dark-green: #1b5e20;
  --light-green: #4caf50;
}
\`\`\`

### Adding New Product Types
1. Update the product form options in admin/products.php
2. Add new commission rules if needed
3. Update any filtering logic

### Modifying Commission Structure
Update the commission calculation logic in the order processing functions.

## Maintenance

### Regular Tasks
1. Backup database regularly
2. Monitor disk space for uploaded images
3. Review and approve affiliate applications
4. Process payout requests
5. Update product stock levels

### Performance Optimization
1. Optimize images before upload
2. Enable gzip compression
3. Use CDN for static assets
4. Implement caching for frequently accessed data

## Support
For technical support or customization requests, contact the development team.
\`\`\`

This completes the comprehensive Melodiva Skincare e-commerce system with:

1. **Complete Admin Panel** with dashboard, product management, order tracking
2. **Full Affiliate System** with application, approval, and commission tracking
3. **Responsive Design** with natural green theme
4. **Mobile-First Approach** using Bootstrap
5. **Security Features** and best practices
6. **Comprehensive Setup Guide** for easy deployment

The system is production-ready and includes all the features specified in your requirements!
