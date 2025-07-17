# Changelog

![keep a changelog](https://img.shields.io/badge/Keep%20a%20Changelog-v1.1.0-brightgreen.svg?logo=data%3Aimage%2Fsvg%2Bxml%3Bbase64%2CPHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIGZpbGw9IiNmMTVkMzAiIHZpZXdCb3g9IjAgMCAxODcgMTg1Ij48cGF0aCBkPSJNNjIgN2MtMTUgMy0yOCAxMC0zNyAyMmExMjIgMTIyIDAgMDAtMTggOTEgNzQgNzQgMCAwMDE2IDM4YzYgOSAxNCAxNSAyNCAxOGE4OSA4OSAwIDAwMjQgNCA0NSA0NSAwIDAwNiAwbDMtMSAxMy0xYTE1OCAxNTggMCAwMDU1LTE3IDYzIDYzIDAgMDAzNS01MiAzNCAzNCAwIDAwLTEtNWMtMy0xOC05LTMzLTE5LTQ3LTEyLTE3LTI0LTI4LTM4LTM3QTg1IDg1IDAgMDA2MiA3em0zMCA4YzIwIDQgMzggMTQgNTMgMzEgMTcgMTggMjYgMzcgMjkgNTh2MTJjLTMgMTctMTMgMzAtMjggMzhhMTU1IDE1NSAwIDAxLTUzIDE2bC0xMyAyaC0xYTUxIDUxIDAgMDEtMTItMWwtMTctMmMtMTMtNC0yMy0xMi0yOS0yNy01LTEyLTgtMjQtOC0zOWExMzMgMTMzIDAgMDE4LTUwYzUtMTMgMTEtMjYgMjYtMzMgMTQtNyAyOS05IDQ1LTV6TTQwIDQ1YTk0IDk0IDAgMDAtMTcgNTQgNzUgNzUgMCAwMDYgMzJjOCAxOSAyMiAzMSA0MiAzMiAyMSAyIDQxLTIgNjAtMTRhNjAgNjAgMCAwMDIxLTE5IDUzIDUzIDAgMDA5LTI5YzAtMTYtOC0zMy0yMy01MWE0NyA0NyAwIDAwLTUtNWMtMjMtMjAtNDUtMjYtNjctMTgtMTIgNC0yMCA5LTI2IDE4em0xMDggNzZhNTAgNTAgMCAwMS0yMSAyMmMtMTcgOS0zMiAxMy00OCAxMy0xMSAwLTIxLTMtMzAtOS01LTMtOS05LTEzLTE2YTgxIDgxIDAgMDEtNi0zMiA5NCA5NCAwIDAxOC0zNSA5MCA5MCAwIDAxNi0xMmwxLTJjNS05IDEzLTEzIDIzLTE2IDE2LTUgMzItMyA1MCA5IDEzIDggMjMgMjAgMzAgMzYgNyAxNSA3IDI5IDAgNDJ6bS00My03M2MtMTctOC0zMy02LTQ2IDUtMTAgOC0xNiAyMC0xOSAzN2E1NCA1NCAwIDAwNSAzNGM3IDE1IDIwIDIzIDM3IDIyIDIyLTEgMzgtOSA0OC0yNGE0MSA0MSAwIDAwOC0yNCA0MyA0MyAwIDAwLTEtMTJjLTYtMTgtMTYtMzEtMzItMzh6bS0yMyA5MWgtMWMtNyAwLTE0LTItMjEtN2EyNyAyNyAwIDAxLTEwLTEzIDU3IDU3IDAgMDEtNC0yMCA2MyA2MyAwIDAxNi0yNWM1LTEyIDEyLTE5IDI0LTIxIDktMyAxOC0yIDI3IDIgMTQgNiAyMyAxOCAyNyAzM3MtMiAzMS0xNiA0MGMtMTEgOC0yMSAxMS0zMiAxMXptMS0zNHYxNGgtOFY2OGg4djI4bDEwLTEwaDExbC0xNCAxNSAxNyAxOEg5NnoiLz48L3N2Zz4K)

All notable changes to this project will be documented in this file.

See [keep a changelog] for information about writing changes to this log.

## [Unreleased]

## [1.0.6] - 2025-07-17

- Update all packages within minor versions
- Added an extra check for an empty application mail address (made checks fail)

## [1.0.5] - 2024-11-28

- Fixed logic error in isGatewaySilenced.

## [1.0.4] - 2024-11-20

- Removed ITKDev docker setup.

## [1.0.3] - 2024-11-19

- Fixed logic error in isDeviceSilenced.

## [1.0.2] - 2024-11-18

- Added application name to subject.
- Wrapped application name in device info with link to the application.
- Added from name to mailge  address.
- Added device EUI to device model.
- Added 'References' header with EUI to mails.

## [1.0.1] - 2024-11-13

- Added `skipBasedOnAppEndDate` to checks.

## [1.0.0] - 2024-10-22

- Bumped version

## [1.0.0-beta4] - 2024-10-17

- Added cache to gateway information to get application names for alert mails

## [1.0.0-beta3] - 2024-09-26

- Fixed typos in configuration and readme.

## [1.0.0-beta2] - 2024-09-25

- Updated mail templets.

## [1.0.0-beta1] - 2024-09-23

- Added support for silencing alert using metadata on device and tags on gateways.
- Added logging to check command.
- Added SMS templates and service to alerts
- Added application last-seen checker.
- Added device last-seen checker.
- Added Gateway last-seen checker.
- Added mail service and templates for mails.
- Added SMS gateway and command to send test SMS.
- Added API client and commands to test it.
- Metrics bundlen added.
- Data model.
- Symfony core.

[keep a changelog]: https://keepachangelog.com/en/1.1.0/
[Unreleased]: https://github.com/itk-dev/iot_alert_manager/compare/main...develop
[1.0.6]: https://github.com/itk-dev/iot_alert_manager/compare/1.0.6...1.0.5
[1.0.5]: https://github.com/itk-dev/iot_alert_manager/compare/1.0.5...1.0.4
[1.0.4]: https://github.com/itk-dev/iot_alert_manager/compare/1.0.4...1.0.3
[1.0.3]: https://github.com/itk-dev/iot_alert_manager/compare/1.0.3...1.0.2
[1.0.2]: https://github.com/itk-dev/iot_alert_manager/compare/1.0.2...1.0.1
[1.0.1]: https://github.com/itk-dev/iot_alert_manager/compare/1.0.1...1.0.0
[1.0.0]: https://github.com/itk-dev/iot_alert_manager/compare/1.0.0-beta4...1.0.0
[1.0.0-beta4]: https://github.com/itk-dev/iot_alert_manager/compare/1.0.0-beta3...1.0.0-beta4
[1.0.0-beta3]: https://github.com/itk-dev/iot_alert_manager/compare/1.0.0-beta2...1.0.0-beta3
[1.0.0-beta2]: https://github.com/itk-dev/iot_alert_manager/compare/1.0.0-beta1...1.0.0-beta2
[1.0.0-beta1]: https://github.com/itk-dev/iot_alert_manager/releases/tag/1.0.0-beta1
