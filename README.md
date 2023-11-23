# Proof of Concept for Composer-based (Functional) Test Systems in TYPO3

This changes the manually-symlinked setup of TYPO3 Functional Tests to using Composer,
with a `composer.json` file that is provided by the test.

Installable in the test system are only packages that are installed in the "mothership" Composer project.
Out of these packages, a local Composer repository is automatically created during `composer install` and used in the

## Setup

    ddev start
    ddev composer install
    ddev composer test:php:functional


## TODO

* All required parts for installation of the test system `composer.json` should be added automatically,
  i.e. the repositories part and the config/allow-plugins stanza.
* Paths in the test system `composer.lock` should probably be rewritten to automatically point to the correct location,
  so we can get rid of absolute paths in the .mono/packages.json.


## Credits

Special thanks to Helmut Hummel, who provided the overall idea and the Composer plugin for creating the local package repository.
