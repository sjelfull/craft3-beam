# Beam Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 2.1.0 - 2018-11-24
### Added
- Added an model and template methods to make it easier to add content and change config on the fly, in loops etc.

### Changed
- The template syntax is now more flexible and easier to work with (this is a breaking change)
- Output will now be written to a temporary file, and the browser will redirect to another url for downloading. This gets rid of the problems with possible whitespace being included in the CSV.

## 2.0.0 - 2017-11-01
### Added
- Initial release
