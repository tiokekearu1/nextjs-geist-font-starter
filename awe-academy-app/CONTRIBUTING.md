# Contributing to AWE Academy Management System

Thank you for your interest in contributing to the AWE Academy Management System! This document provides guidelines and instructions for contributing.

## Code of Conduct

By participating in this project, you agree to abide by our Code of Conduct:
- Be respectful and inclusive
- Exercise consideration and empathy
- Focus on constructive feedback
- Maintain professional discourse

## How to Contribute

### Reporting Bugs

1. Check if the bug has already been reported in the Issues section
2. If not, create a new issue with:
   - Clear title and description
   - Steps to reproduce
   - Expected vs actual behavior
   - Screenshots if applicable
   - System information

### Suggesting Enhancements

1. Check existing issues for similar suggestions
2. Create a new issue with:
   - Clear description of the enhancement
   - Rationale and use cases
   - Potential implementation approach
   - Any relevant examples or mockups

### Pull Requests

1. Fork the repository
2. Create a new branch:
   ```bash
   git checkout -b feature/your-feature-name
   ```
   or
   ```bash
   git checkout -b fix/your-fix-name
   ```

3. Make your changes following our coding standards
4. Test your changes thoroughly
5. Commit your changes:
   ```bash
   git commit -m "Description of changes"
   ```
6. Push to your fork:
   ```bash
   git push origin feature/your-feature-name
   ```
7. Create a Pull Request

## Development Setup

1. Clone the repository:
   ```bash
   git clone https://github.com/yourusername/awe-academy-app.git
   ```

2. Set up the development environment:
   - PHP 7.4 or higher
   - MySQL 5.7 or higher
   - Apache with mod_rewrite
   - Composer for dependencies

3. Install dependencies:
   ```bash
   composer install
   ```

4. Configure the database:
   - Copy config/db.example.php to config/db.php
   - Update database credentials

5. Run database migrations:
   ```bash
   mysql -u username -p database_name < database/schema.sql
   ```

## Coding Standards

### PHP
- Follow PSR-12 coding style
- Use meaningful variable and function names
- Add comments for complex logic
- Keep functions focused and concise
- Write unit tests for new features

### HTML/CSS
- Follow BEM naming convention
- Maintain responsive design
- Ensure accessibility standards
- Test across different browsers

### JavaScript
- Use ES6+ features appropriately
- Follow clean code principles
- Add comments for complex logic
- Ensure browser compatibility

### Database
- Use meaningful table and column names
- Follow naming conventions
- Include proper indexes
- Write optimized queries

## Testing

1. Run PHP unit tests:
   ```bash
   ./vendor/bin/phpunit
   ```

2. Test frontend components:
   - Cross-browser testing
   - Responsive design testing
   - Accessibility testing

3. Manual testing:
   - Feature functionality
   - User workflows
   - Error handling
   - Security measures

## Documentation

- Update README.md for new features
- Add PHPDoc comments to classes and methods
- Update CHANGELOG.md
- Include inline code comments
- Update user documentation if needed

## Review Process

1. All submissions require review
2. Changes must pass automated tests
3. Code style must meet standards
4. Documentation must be updated
5. Changes should be focused and atomic

## Security

- Report security vulnerabilities privately
- Follow secure coding practices
- Avoid committing sensitive data
- Use prepared statements for queries
- Validate and sanitize all input

## Questions?

Feel free to:
- Open an issue for questions
- Join our community discussions
- Contact the maintainers
- Check our documentation

## License

By contributing, you agree that your contributions will be licensed under the MIT License.

Thank you for contributing to making the AWE Academy Management System better!
