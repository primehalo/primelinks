# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2018-02-12
### Changed
- Updated the description to more accurately represent what this extension has become.

## [1.1.0 BETA 3] - 2018-02-09
### Added
- Applied external link attributes to member website links on the viewtopic page (excluding the class attribute because such links are represented as icons and not text links).

### Changed
- For assigning template variables to the member list page, switched from using a core event that was new to phpBB 3.2 to one that has existed since phpBB 3.1.7 to maintain compatibility with phpBB 3.1.

## [1.1.0 BETA 2] - 2018-02-07
### Added
- Applied external link attributes to member website links on the member list page.
- URL prefix option for internal links

### Changed
- Moved the JavaScript code from separate HTML files into a single HTML file
- Switched from phpBB template syntax to Twig template syntax

### Fixed
- A warning about the regular expression /e modifier not being supported in php 7.


## [1.1.0 BETA] - 2018-02-03
### Added
- A module to the ACP for setting options. Options are configured through this ACP module and stored in the database rather than being set directly in a PHP file.

## [1.0.0] - 2015-01-22
- First release for phpBB 3.1, ported from the Prime Links extension for phpBB 3.0.