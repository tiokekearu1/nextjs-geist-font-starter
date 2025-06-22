# AWE Academy Management System

A comprehensive school management system built with PHP that handles student records, fee management, supply inventory, and more.

## Features

### User Management
- Role-based access control (Admin, Finance Officer, Supply Officer)
- Secure authentication and session management
- User profile management
- Activity logging

### Student Management
- Add, edit, and view student records
- Track student status (Active, Inactive, Graduated)
- View student payment history
- Manage student supplies

### Fee Management
- Create and manage fee types
- Track fee payments
- Generate payment receipts
- View payment history
- Generate collection reports

### Supply Management
- Track supply inventory
- Record supply distributions
- Monitor low stock items
- Generate distribution reports

### Reporting
- Fee collection reports
- Supply distribution reports
- Student statistics
- Payment trends

### System Settings
- School information configuration
- Email settings
- Notification preferences
- System preferences

## Technical Details

### Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache web server with mod_rewrite enabled
- PDO PHP Extension
- GD PHP Extension

### Installation

1. Clone the repository:
```bash
git clone https://github.com/yourusername/awe-academy-app.git
```

2. Import the database schema:
```bash
mysql -u your_username -p your_database < database/schema.sql
```

3. Configure the database connection:
- Copy `config/db.example.php` to `config/db.php`
- Update the database credentials in `config/db.php`

4. Set up the web server:
- Configure Apache to point to the project directory
- Ensure mod_rewrite is enabled
- Make sure .htaccess is allowed in Apache configuration

5. Set proper permissions:
```bash
chmod 755 -R /path/to/awe-academy-app
chmod 777 -R /path/to/awe-academy-app/uploads
```

### Directory Structure

```
awe-academy-app/
├── config/             # Configuration files
├── database/          # Database schema and migrations
├── includes/          # Common PHP includes
├── modules/           # Application modules
│   ├── fees/         # Fee management
│   ├── payments/     # Payment processing
│   ├── reports/      # Report generation
│   ├── settings/     # System settings
│   ├── students/     # Student management
│   ├── supplies/     # Supply management
│   └── users/        # User management
├── uploads/          # File uploads
└── assets/          # Static assets
    ├── css/         # Stylesheets
    ├── js/          # JavaScript files
    └── img/         # Images
```

### Security Features

- CSRF protection
- Password hashing
- SQL injection prevention
- XSS protection
- Session security
- Input validation and sanitization
- Role-based access control
- Activity logging
- Secure file uploads

### Default Users

After database initialization, the following default user is created:

```
Username: admin
Password: admin123
Role: Admin
```

**Important:** Change the default password immediately after first login.

## Usage

### Student Management
- Navigate to Students → Add New Student to create student records
- Use the student list to view, edit, or manage student information
- View individual student details including fee payments and supply distributions

### Fee Management
- Create fee types under Fees → Add New Fee
- Record payments through the student profile or fees section
- Generate receipts for payments
- View payment history and collection reports

### Supply Management
- Add supplies through Supplies → Add New Supply
- Track inventory levels
- Record distributions to students
- Monitor low stock alerts

### Reports
- Access various reports through the Reports module
- Filter data by date range
- Export or print reports as needed

### Settings
- Configure system settings through the Settings module
- Manage email configurations
- Set up notification preferences
- Update school information

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For support, email support@aweacademy.com or create an issue in the repository.

## Acknowledgments

- Bootstrap for the UI framework
- Font Awesome for icons
- PHP community for various libraries and inspiration
