# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

### Development
- `npm run dev` - Compile assets for development
- `npm run watch` - Watch files and recompile on changes
- `npm run prod` - Compile assets for production

### Laravel Framework
- `php artisan serve` - Start development server
- `php artisan migrate` - Run database migrations
- `php artisan migrate:fresh --seed` - Fresh migration with seeders
- `php artisan passport:install` - Install Laravel Passport for API authentication
- `php artisan queue:work` - Process background jobs
- `php artisan tinker` - Laravel REPL for debugging
- `php artisan key:generate` - Generate application key

### Testing
- `vendor/bin/phpunit` - Run PHPUnit tests
- `vendor/bin/phpunit --filter=TestName` - Run specific test

### Package Management
- `composer install` - Install PHP dependencies
- `composer update` - Update PHP dependencies
- `npm install` - Install JavaScript dependencies

## Architecture Overview

### Core Domain
This is a **Water Billing System Backend** built with Laravel 9, designed for managing water utility billing, meter readings, and payments. The system handles both prepaid and postpaid water meters.

### Key Components

**Authentication & Authorization:**
- Laravel Passport for API authentication
- Spatie Laravel Permission for role-based access control
- Supports admin and user authentication endpoints

**Water Meter Management:**
- Meter models with UUID primary keys and soft deletes
- Support for different meter types (prepaid/postpaid) and categories
- Concentrator-based meter communication system
- Meter readings with automated billing calculations
- Faulty meter tracking and management

**Billing System:**
- Monthly service charges with automated generation
- Connection fees for new users
- Token-based prepaid meter system
- Unaccounted debt tracking and management
- Multiple payment methods integration

**Payment Integration:**
- M-Pesa payment gateway integration
- Transaction validation and processing
- Unresolved transaction management system
- Credit account management

**Communication System:**
- SMS notifications and alerts
- Configurable SMS templates
- Alert contact management
- Automated billing reminders

**Background Processing:**
- Laravel Queue system for async operations
- Scheduled commands for meter readings, billing generation
- Automated meter valve control (on/off based on payments)
- Transaction processing and validation

### Database Design
- Uses UUIDs as primary keys across models
- Implements soft deletes for data integrity
- Activity logging with Spatie Activity Log
- Comprehensive migration system with proper foreign key relationships

### Key Models Relationships
- `User` -> `Meter` (one-to-one relationship for account holders)  
- `Meter` -> `MeterReading` (one-to-many for reading history)
- `User` -> `MpesaTransaction` (payment history)
- `Meter` -> `Concentrator` (communication infrastructure)
- `MeterStation` -> `Meter` (geographical grouping)

### API Structure
- RESTful API with versioning (v1 prefix)
- Role-based middleware protection
- Separate admin and user authentication flows
- Comprehensive CRUD operations for all entities

### Configuration Notes
- Requires Redis for caching and queue management
- Uses Laravel Mix for asset compilation
- Configured for multiple database connections
- Integrates with external services (M-Pesa, SMS providers)
- Activity logging enabled across all major models

### Background Jobs & Commands
The system includes numerous scheduled commands for:
- Automated meter reading collection
- Monthly service charge generation
- Payment processing and validation
- Meter disconnection/reconnection based on payment status
- SMS notifications and reminders