# WHMCS Phenix GSM Module

Module for WHMCS integration with Phenix GSM API v2.9 for mobile plan management.

## Features

- GSM line management (create, suspend, unsuspend, terminate)
- SIM and eSIM card ordering
- Real-time DATA consumption (SDTR) and historical (CDR)
- DATA recharge by zone
- Portability management (IN/OUT)
- Complete webhook notifications
- APN Switch and Operator Switch
- SFR Cut-Off unlock

## Installation

1. Copy the phenixgsm folder to /modules/servers/
2. Configure a server in WHMCS with type=phenixgsm
3. Add your Phenix credentials
4. Create products with module=phenixgsm

## Documentation

- [Complete Documentation](README.md)
- [Configuration Examples](EXAMPLE_CONFIG.md)
- [Quick Start Guide](QUICK_START.md)
- [Changelog](CHANGELOG.md)

## Support

For any questions, contact Phenix Partner support.

---

**Version**: 1.0.0
**API Compatible**: Phenix GSM v2.9
**Author**: T-otor
**License**: MIT