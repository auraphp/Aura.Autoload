Introduction
============

The Aura Autoload package provides a [PSR-0](http://groups.google.com/group/php-standards/web/psr-0-final-proposal) compliant SPL autoloader implementation for PHP 5.3+.


Implicit Usage
==============

Create an instance of the `Loader` and register it with SPL.

    <?php
    $loader = require '/path/to/aura.autoloader/scripts/instance.php';
    $loader->register();

The `Loader` will now look for PSR-0 compliant class names in the include-path, and throw an `Exception_NotFound` if it cannot find one.


Explicit Usage
==============

You can tell the `Loader` to search explicit paths for classes with specific prefixes. The prefixes can be the older PEAR-style class prefixes, or the newer PHP 5.3 formal namespace prefixes.
    
    <?php
    // look for all Zend_* classes in this path:
    $loader->addPath('Zend_', '/path/to/zf/lib/Zend');
    
    // look for vendor\package classes in this path:
    $loader->addPath('vendor\package\\', '/path/to/vendor.package/src');
    
    // additionally, e.g. in testing modes, also look for vendor\package
    // classes in this path as well:
    $loader->addPath('vendor\package\\', '/path/to/vendor.package/tests');

(Note that you should end the prefixes with a double-backslash in the case of namespaces, not a single backslash.)

If the `Loader` cannot find a class in the explicit paths for the specific prefix given, it will fall back to looking in the include-path.
