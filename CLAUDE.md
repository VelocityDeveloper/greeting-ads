# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a WordPress plugin called "Greeting Ads" that manages advertising greetings through CSV import and database operations. The plugin tracks visitor traffic from Google Ads campaigns and stores personalized greetings for different keyword/ad group combinations.

## Key Architecture Components

### Database Schema
- **Table**: `{wp_prefix}greeting_ads_data` 
- **Structure**: id, kata_kunci (keywords), grup_iklan (ad group), id_grup_iklan (ad group ID), nomor_kata_kunci (keyword number), greeting (personalized message)

### Core Logic Flow
1. **Traffic Detection**: `includes/function.php:get_ads_logic()` detects Google Ads traffic via cookies (_gcl_aw, greeting) or UTM parameters
2. **Cookie Management**: `save_utm_cookies()` extracts keyword numbers from utm_medium (format: kwd-{number}) and stores greeting data in cookies
3. **Database Lookup**: Matches utm_content (ad group ID) + utm_medium (keyword number) to retrieve personalized greetings
4. **API Access**: REST endpoint `/greeting/v1/get` provides external access with Bearer token authentication

### File Structure
- **Main Plugin**: `greeting-ads.php` - Plugin initialization, database table creation, admin menu
- **Core Functions**: `includes/function.php` - UTM tracking, cookie management, greeting lookup
- **Data Management**: 
  - `includes/crud-functions.php` - Database CRUD operations and AJAX handlers
  - `includes/import-csv.php` - CSV import functionality
- **API**: `api/greeting.php` - REST API endpoints with token authentication
- **UI Components**: 
  - `includes/table-display.php` - Admin interface (referenced but not examined)
  - `includes/ajax.php`, `includes/form-chat.php`, `includes/floating-whatsapp.php` - Additional UI features

### External Integrations
- **Telegram Bot**: `kirim_telegram()` function sends notifications to multiple chat IDs
- **OpenAI API**: `validasi_jenis_web()` validates website types using GPT-4.1
- **WhatsApp Validation**: `validasi_no_wa()` validates Indonesian phone numbers (62/08 prefix)

## Important Implementation Notes

- Plugin uses WordPress hooks: `admin_menu`, `init`, `rest_api_init`, `wp_ajax_*`
- Database operations use `$wpdb` with prepared statements for security
- Cookie settings include secure flags, httponly, and SameSite=Lax
- UTM parameter extraction handles both direct numbers and kwd-{number} format
- CSV import checks for duplicate greetings before insertion
- API authentication uses hardcoded Bearer token: `c2e1a7f62f8147e48a1c3f960bdcb176`

## WordPress Environment Requirements

This plugin requires:
- WordPress installation with database access
- External API keys stored in WordPress options: `openai_api_key`, `prompt_jenis_web`
- Telegram bot token defined as `TOKEN_TELEGRAM_BOT` constant
- Admin capabilities for accessing plugin management interface