# HTM Haridusasutuste Veebiplatvorm (HVP)

Estonian Educational Institutions Web Platform by Haridus- ja Teadusministeerium (HTM).

A unified Drupal-based platform for Estonian educational institution websites, providing a standardized, secure, and maintainable web solution for schools, gymnasiums, and other educational institutions across Estonia.

As the HTM-managed HVP service will be discontinued starting 22.09.2025, HTM has released the source code to enable schools to migrate their sites to alternative hosting. This repository is a fork of the original codebase from https://gituja.eenet.ee/avalik/veebiplatvorm-backend, enhanced with deployment fixes and an automated setup script for self-hosting.

## About HVP

HVP (Haridusasutuste Veebiplatvorm) is designed to:
- Provide a standardized web platform for all Estonian educational institutions
- Ensure security and compliance with Estonian regulations
- Enable easy content management for school administrators
- Support multi-site architecture for efficient maintenance
- Offer responsive, accessible design for all users

## Quick Start

```bash
# Run the interactive setup script
./setup.sh
```

The setup script will guide you through:
1. Entering your institution name and domain
2. Choosing environment (Development, Staging, or Production)
3. Configuring database connection
4. Setting up the site with appropriate settings

## Documentation

- [SETUP.md](SETUP.md) - Complete setup and deployment guide

## Project Structure

```
veebiplatvorm-backend/
├── web/
│   ├── core/           # Drupal core
│   ├── modules/        # Custom and contributed modules
│   ├── themes/         # Custom and contributed themes
│   └── sites/          # Multi-site directories
│       ├── default/    # Default site configuration
│       ├── torvakool.edu.ee/  # Tõrva Kool site
│       └── torva.edu.ee/      # Tõrva Gümnaasium site
├── config/            # Configuration sync directory
├── backup/            # Database and file backups
└── setup.sh          # Universal setup script
```

## Multi-site Architecture

Each site has its own:
- **Database**: Separate database for complete content isolation
- **Files**: Independent file storage in `web/sites/[domain]/files/`
- **Settings**: Site-specific configuration in `web/sites/[domain]/settings.php`
- **Domain**: Unique domain configuration in `web/sites/sites.php`

Shared components:
- **Codebase**: All sites use the same Drupal core and modules
- **Themes**: Can share themes or use site-specific themes
- **Modules**: All functionality available to all sites

## Requirements

- PHP 8.1+
- MariaDB 11.4+ or MySQL 8.0+
- Composer 2.x
- Apache/Nginx with mod_rewrite

## Security Notes

- Each site has its own hash salt
- Database credentials are site-specific
- File permissions are managed per site
- Production environments have hardened security settings

## Support

For issues or questions, please check the documentation or create an issue in the repository.