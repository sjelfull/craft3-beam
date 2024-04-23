# Beam Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 5.0.0 - 2024-04-23

### Added
- Craft 5 support

## 3.0.0 - 2022-10-03

### Added
- Craft 4 support
- Support for formatting cell values

### Fixed
- Fixes PHP deprecation notice

### Changed
- Updated `mk-j/php_xlsxwriter` to ^0.38.0

## 2.1.5 - 2020-01-27
### Added
- Added BOM sequence to improve interoperability with programs interacting with CSV, like Microsoft Excel. ([#13](https://github.com/sjelfull/craft3-beam/pull/13))

## 2.1.4 - 2019-06-06
### Fixed
- Fixed error when trying to download export for a user without access to the CP

## 2.1.3 - 2019-02-02
### Added
- Added route so Beam can be used in the control panel

## 2.1.2 - 2019-01-30
### Fixed
- Fixed exception when league/csv 8.x is installed

## 2.1.1 - 2019-01-05
### Changed
- Changed league/csv dependency to not conflict with Feed Me

## 2.1.0 - 2018-11-24
### Added
- Added an model and template methods to make it easier to add content and change config on the fly, in loops etc.

### Changed
- The template syntax is now more flexible and easier to work with (this is a breaking change)
- Output will now be written to a temporary file, and the browser will redirect to another url for downloading. This gets rid of the problems with possible whitespace being included in the CSV.

## 2.0.0 - 2017-11-01
### Added
- Initial release
