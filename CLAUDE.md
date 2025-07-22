# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a **TimeTrack** application - a Laravel/Vue.js full-stack web application for managing IVA (Independent Virtual Assistant) time tracking and performance reporting. The system integrates with TimeDoctor for automated time tracking and provides comprehensive reporting capabilities for HR, Finance, and management teams.

## Architecture

### Backend (Laravel 11)
- **Framework**: Laravel 11.31 with PHP 8.2+
- **Authentication**: JWT-based authentication using `firebase/php-jwt`
- **Authorization**: Role-based permissions using `spatie/laravel-permission`
- **Database**: MySQL with comprehensive migrations for user management, time tracking, and reporting
- **API**: RESTful API structure with middleware-protected routes

### Frontend (Vue.js 3)
- **Framework**: Vue 3.5+ with Composition API
- **UI Library**: Vuetify 3.7+ with Material Design components
- **State Management**: Pinia for application state
- **Routing**: Vue Router with permission-based route guards
- **Build Tool**: Vite with Laravel integration

### Key Integrations
- **TimeDoctor V1 & V2**: OAuth-based integration for automated time tracking
- **Chart.js/ApexCharts**: Performance visualization and reporting
- **Role-Based Access Control**: 6 roles (admin, hr, finance, rtl, artl, iva) with granular permissions

## Development Commands

### Frontend Development
```bash
# Install dependencies
npm install

# Development server (Vite)
npm run dev

# Production build
npm run build

# Linting
npm run lint

# Build icons (auto-runs on postinstall)
npm run build:icons
```

### Backend Development
```bash
# Install PHP dependencies
composer install

# Run all development services (recommended)
composer run dev
# This starts: Laravel server, queue worker, log viewer, and Vite dev server concurrently

# Individual Laravel commands
php artisan serve
php artisan queue:work
php artisan migrate
php artisan db:seed
php artisan db:seed --class=RolePermissionSeeder
php artisan db:seed --class=TaskCategorySeeder

# Code formatting
./vendor/bin/pint
```

### Testing
```bash
# Run PHP tests
./vendor/bin/phpunit

# No specific JavaScript test runner configured
```

## Key Domain Concepts

### User Management
- **IVA Users**: Virtual assistants with customizable settings and manager assignments
- **IVA Managers**: Managers who oversee multiple IVA users within regions
- **Regions & Cohorts**: Organizational structures for grouping users

### Time Tracking
- **WorklogsData**: Core time tracking records from TimeDoctor integration
- **Manual Time Entries**: User-created time records requiring approval
- **TimeDoctor Integration**: Dual V1/V2 API support with OAuth authentication

### Reporting System
- **Daily Performance Reports**: Individual IVA performance metrics
- **Weekly Performance Reports**: Team and individual weekly summaries  
- **Region Performance Reports**: Cross-region performance comparisons
- **Report Categories**: Configurable task categorization for reporting

### Permission System
- **Roles**: admin, hr, finance, rtl, artl, iva
- **Granular Permissions**: 20+ specific permissions for feature access
- **Route Guards**: Both API middleware and frontend route protection

## File Structure Conventions

### Backend Structure
- `app/Http/Controllers/`: Feature-based controller organization
- `app/Models/`: Eloquent models with relationships
- `app/Services/`: Business logic layer (TimeDoctor services)
- `app/Jobs/`: Queue-based background tasks
- `database/migrations/`: Schema definitions with descriptive timestamps
- `routes/api.php`: All API routes with middleware groups

### Frontend Structure  
- `resources/js/pages/`: Route-based page components
- `resources/js/components/`: Reusable UI components
- `resources/js/@core/`: Core utilities and base components
- `resources/js/@layouts/`: Layout components and navigation
- `resources/js/plugins/`: Vue plugins and configurations
- `resources/styles/`: SCSS styling with Vuetify theming

## Configuration Files

### Key Configuration
- `config/constants.php`: Role definitions, permissions mapping, and business constants
- `config/services.php`: Third-party service configurations (TimeDoctor)
- `vite.config.js`: Frontend build configuration with auto-imports
- `.env`: Environment-specific settings (database, API keys)

### Important Constants
- Pagination defaults: 20 items per page (configurable per feature)
- Role hierarchy: admin > hr > finance > rtl > artl > iva
- TimeDoctor integration supports both V1 and V2 APIs

## Development Workflow

1. **Database Setup**: Run migrations and seed role/permission data
2. **TimeDoctor Integration**: Configure OAuth credentials in `.env`
3. **Frontend Development**: Use `npm run dev` for hot reloading
4. **API Development**: Test with middleware-protected routes
5. **Permission Testing**: Verify role-based access controls
6. **Performance**: Use caching for reports (Redis recommended)

## Common Patterns

### API Controllers
- JWT middleware protection on all authenticated routes
- Permission-based access control using middleware
- Activity logging for audit trails
- Standardized JSON responses with pagination

### Vue Components
- Composition API with `<script setup>` syntax
- Vuetify components for consistent UI
- Pinia stores for state management
- Route guards for permission-based navigation

### Database Relationships
- User belongs to Region and Cohort
- IVA Users have many Managers (many-to-many)
- TimeDoctor records linked to Users and Projects
- Activity logs track all user actions